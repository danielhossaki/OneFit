// Run only against the local synthetic fixture prepared by prepare-interface-web.ps1.
const { chromium } = require(process.env.TEMP + '/onefit-interface-tools/node_modules/playwright-core');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8765/AN25/OneFit/';
const output = process.env.TEMP + '/onefit-i18n-browser.json';
async function audit(page, context) {
    const result = await page.evaluate(() => {
        const data = window.OneFit;
        const remaining = [];
        const texts = [];
        const check = (text, element, attribute = '') => {
            text = text.trim();
            if (!text) return;
            texts.push(text);
            if (data?.locale !== 'pt-BR' && data?.messages[text] && data.messages[text] !== text) {
                remaining.push({ text, tag: element.tagName, attribute });
            }
        };
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (!node.parentElement.closest('script,style')) check(node.textContent, node.parentElement);
        }
        for (const element of document.querySelectorAll('[title],[placeholder],[aria-label],[data-of-confirm]')) {
            for (const attribute of ['title','placeholder','aria-label','data-of-confirm']) {
                const text = element.getAttribute(attribute);
                if (text) check(text, element, attribute);
            }
        }
        return { locale: data?.locale, lang: document.documentElement.lang, remaining, texts: [...new Set(texts)],
            options: [...document.querySelectorAll('select')].filter(e => !e.closest('[data-of-country],[data-of-state]')).map(e => ({name:e.name,id:e.id,values:[...e.options].map(o=>o.value)})) };
    });
    assert.equal(result.locale, context.locale === 'pt' ? 'pt-BR' : context.locale);
    assert.equal(result.lang, result.locale);
    return { ...context, ...result };
}
(async () => {
    const fixture = JSON.parse(fs.readFileSync(process.env.TEMP + '/onefit-interface-fixture/database.json'));
    assert.match(fixture.database, /^onefit_interface_test_/);
    const browser = await chromium.launch({channel:'chrome',headless:true});
    const report = { pages: [], errors: [], themes: [] };
    try {
        for (const [role,id] of [['admin',1],['aluno',3],['profissional',4],['vendedor',5]]) {
            const context = await browser.newContext({reducedMotion:'reduce'});
            await context.route('https://**/*', async route => {
                const url = new URL(route.request().url());
                const name = url.hostname === 'fonts.googleapis.com' ? 'manrope.css' : url.pathname.split('/').pop();
                const file = process.env.TEMP + '/onefit-dashboard-review/vendor/' + name;
                if (fs.existsSync(file)) await route.fulfill({path:file});
                else await route.abort();
            });
            const page = await context.newPage();
            page.on('pageerror', error => {
                // The isolated fixture intentionally blocks the external landing-page animation CDN.
                if (error.message !== 'AOS is not defined') report.errors.push({role,url:page.url(),error:error.message});
            });
            await page.goto(base+'pages/login/login.php');
            await page.locator('#email').fill(`fixture${id}@example.test`);
            await page.locator('#password').fill('Fixture-only-123!');
            await Promise.all([page.waitForURL('**/dashboard.php*'),page.locator('button[type=submit]').first().click()]);
            for (const locale of ['en','es','pt']) {
                const csrf = await page.locator('input[name=csrf_token]').first().inputValue();
                const response = await context.request.post(base+'pages/dashboard/actions/interface.php', {form:{csrf_token:csrf,configuracao:'idioma',valor:locale}});
                assert.equal(response.status(),200);
                const paths = ['pages/dashboard/dashboard.php','index.php','pages/marketplace/marketplace.php','pages/carrinho/carrinho.php',
                    'pages/login/login.php?msg=1','pages/matricula/matricula.php','pages/auth/senha/esqueci-senha.php',
                    'pages/auth/senha/redefinir-senha.php','pages/auth/email/verificar-email.php','pages/errors/404.php'];
                for (const path of paths) {
                    await page.goto(base+path, {waitUntil:'domcontentloaded'});
                    await page.waitForTimeout(50);
                    report.pages.push(await audit(page,{role,locale,path}));
                }
                await page.goto(base+'pages/dashboard/dashboard.php');
                const validation = await page.evaluate(() => {
                    const form = document.createElement('form');
                    const input = document.createElement('input');
                    input.required = true; form.append(input); document.body.append(form);
                    const invalid = !input.checkValidity();
                    const translated = input.validationMessage === ofT('Preencha este campo.');
                    input.value = 'Example'; input.dispatchEvent(new Event('input', {bubbles:true}));
                    const corrected = input.checkValidity();
                    input.setCustomValidity('Custom rule'); input.checkValidity();
                    const preserved = input.validationMessage === 'Custom rule';
                    form.remove(); return {invalid,translated,corrected,preserved};
                });
                assert.deepEqual(validation,{invalid:true,translated:true,corrected:true,preserved:true});
                assert.ok(await page.evaluate(() => BO_NATIONALITY_OPTIONS.every(code => /^[A-Z]{2}$/.test(code))), 'Localized nationality labels must retain ISO values');
                if (role === 'admin') {
                    for (const theme of ['dourado','roxo','vermelho','azul','verde']) {
                        const token = await page.locator('input[name=csrf_token]').first().inputValue();
                        await context.request.post(base+'pages/dashboard/actions/interface.php',{form:{csrf_token:token,configuracao:'tema_cores',valor:theme}});
                        await page.reload({waitUntil:'domcontentloaded'});
                        const brand = await page.evaluate(() => {
                            const name = OneFit.brands[document.documentElement.dataset.siteTheme].name;
                            return {name,translated:ofT(name),theme:document.documentElement.dataset.siteTheme};
                        });
                        assert.equal(brand.theme,theme);
                        assert.equal(brand.translated,brand.name);
                        report.themes.push({locale,...brand});
                    }
                }
            }
            await context.close();
        }
        assert.deepEqual(report.errors,[],'Browser runtime errors');
        for (const reference of report.pages.filter(p => p.locale === 'pt')) {
            const canonical = options => options.map(o => ({...o,values:[...o.values].sort()}));
            for (const translated of report.pages.filter(p => p.role === reference.role && p.path === reference.path && p.locale !== 'pt')) {
                assert.deepEqual(canonical(translated.options), canonical(reference.options), 'Translation changed form values: ' + reference.path);
            }
        }
        const leftovers = report.pages.filter(p=>p.remaining.length).map(({role,locale,path,remaining})=>({role,locale,path,remaining}));
        assert.deepEqual(leftovers,[],'Portuguese source labels still rendered in another locale');
        console.log(`i18n browser: ${report.pages.length} page/locale checks, ${report.themes.length} theme/locale checks passed.`);
    } finally {
        fs.writeFileSync(output,JSON.stringify(report,null,2));
        await browser.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
