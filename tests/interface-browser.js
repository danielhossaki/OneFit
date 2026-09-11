const { chromium } = require(process.env.TEMP + '/onefit-interface-tools/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = 'http://127.0.0.1:8765/AN25/OneFit/';
const results = [];
const test = (label, condition) => { assert.ok(condition, label); results.push(label); };
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const context = await browser.newContext({ viewport: { width: 1365, height: 900 } });
        const page = await context.newPage();
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        async function login(p, id) {
            await p.goto(base + 'pages/login/login.php');
            await p.locator('#email').fill(`fixture${id}@example.test`);
            await p.locator('#password').fill('Fixture-only-123!');
            await Promise.all([p.waitForURL('**/dashboard.php*'), p.locator('form').first().locator('button[type=submit]').click()]);
        }
        await login(page, 3);
        await page.goto(base + 'pages/dashboard/dashboard.php?section=perfil');
        await page.locator('[data-bs-target="#modalPerfilEditar"]').first().click();
        await page.locator('#modalPerfilEditar.show').waitFor();
        test('Name and document read-only', await page.locator('#student-nome').getAttribute('readonly') !== null && await page.locator('#student-documento').getAttribute('readonly') !== null);
        const country = page.locator('#student-nacionalidade-combobox');
        await country.fill('Portugal'); await country.press('ArrowDown'); await country.press('Enter');
        test('Country keyboard selection synchronizes ISO', await page.locator('#student-nacionalidade').inputValue() === 'PT');
        const state = page.locator('#student-estado-combobox');
        await state.fill('Minas'); await state.press('ArrowDown'); await state.press('Enter');
        test('State keyboard selection synchronizes UF', await page.locator('#student-estado').inputValue() === 'MG');
        await state.fill('Sao Paulo'); await state.press('ArrowDown'); await state.press('Enter');
        test('Accent-insensitive search', await page.locator('#student-estado').inputValue() === 'SP');
        await state.click(); await state.press('Escape');
        test('Escape closes only select', await state.getAttribute('aria-expanded') === 'false' && await page.locator('#modalPerfilEditar').isVisible());
        await page.locator('#modalPerfilEditar button[type=submit]').click();
        await page.waitForURL('**/dashboard.php?section=perfil');
        await page.locator('[data-bs-target="#modalPerfilEditar"]').first().click();
        test('Country persists after POST', await page.locator('#student-nacionalidade').inputValue() === 'PT');
        const originalName = await page.locator('#student-nome').inputValue();
        const originalDocument = await page.locator('#student-documento').inputValue();
        const formData = await page.locator('#modalPerfilEditar form').evaluate(form => Object.fromEntries([...new FormData(form)].filter(([key,value]) => typeof value === 'string')));
        const manual = await context.request.post(base + 'pages/dashboard/actions/update-profile.php', { form: { ...formData, nome:'FORGED NAME',documento:'99999999999',id_usuario:'5' } });
        test('Manual profile POST responds', manual.ok());
        await page.reload(); await page.locator('[data-bs-target="#modalPerfilEditar"]').first().click();
        test('Manual identity change ignored', await page.locator('#student-nome').inputValue() === originalName && await page.locator('#student-documento').inputValue() === originalDocument);
        await context.request.post(base + 'pages/dashboard/actions/update-profile.php', { form: { ...formData,nacionalidade:'XX' } });
        await page.reload(); await page.locator('[data-bs-target="#modalPerfilEditar"]').first().click();
        test('Unknown nationality rejected', await page.locator('#student-nacionalidade').inputValue() === 'PT');
        await Promise.all([
            page.locator('#modalPerfilEditar').evaluate(el => new Promise(resolve => el.addEventListener('hidden.bs.modal', resolve, {once:true}))),
            page.locator('#modalPerfilEditar [data-bs-dismiss=modal]').first().click(),
        ]);
        await page.locator('#modalPerfilEditar').waitFor({state:'hidden'});
        const photo = page.locator('[data-photo-menu-toggle]');
        await photo.focus(); await photo.press('Enter'); await page.locator('[data-photo-open]').click();
        test('Photo opens with keyboard', await page.locator('#studentPhotoLightbox').evaluate(d=>d.open));
        await page.keyboard.press('Tab'); test('Lightbox traps focus', await page.locator('#studentPhotoLightbox').evaluate(d=>d.contains(document.activeElement)));
        await page.keyboard.press('Escape');
        await page.waitForFunction(()=>document.activeElement === document.querySelector('[data-photo-menu-toggle]'));
        test('Lightbox restores focus', await photo.evaluate(p=>p===document.activeElement));
        await photo.click(); await page.locator('[data-photo-open]').click(); await page.locator('[data-photo-close]').click();
        test('Close button closes modal', !(await page.locator('#studentPhotoLightbox').evaluate(d=>d.open)));
        await photo.click(); await page.locator('[data-photo-open]').click(); await page.mouse.click(2,2);
        test('Backdrop closes modal', !(await page.locator('#studentPhotoLightbox').evaluate(d=>d.open)));
        await page.goto(base + 'pages/dashboard/dashboard.php?section=configuracoes');
        const csrf = await page.locator('input[name=csrf_token]').first().inputValue();
        const forbidden = await context.request.post(base + 'pages/dashboard/actions/interface.php', {form:{configuracao:'tema_cores',valor:'azul',csrf_token:csrf}});
        test('Non-admin cannot change global theme', forbidden.status()===403);
        const forged = await context.request.post(base + 'pages/dashboard/actions/interface.php', {form:{configuracao:'idioma',valor:'en',csrf_token:'forged'}});
        test('CSRF rejected', forged.status()===403);
        const invalid = await context.request.post(base + 'pages/dashboard/actions/interface.php', {form:{configuracao:'idioma',valor:'xx',csrf_token:csrf}});
        test('Unknown language rejected', invalid.status()===422);
        await page.locator('#of-language').selectOption('en');
        await page.locator('#of-language').locator('..').locator('button[type=submit]').click();
        await page.waitForURL('**/dashboard.php?section=configuracoes');
        test('Language applied after saving', await page.locator('html').getAttribute('lang') === 'en' && (await page.locator('#boSettingsSection').textContent()).includes('Settings'));
        for (const path of ['index.php','pages/login/login.php','pages/matricula/matricula.php','pages/marketplace/marketplace.php','pages/carrinho/carrinho.php']) {
            await page.goto(base+path);
            test('Language on '+path, await page.locator('html').getAttribute('lang')==='en');
        }
        await page.goto(base+'config/logout.php'); await login(page,3);
        test('Language survives new login', await page.locator('html').getAttribute('lang')==='en');
        const adminContext = await browser.newContext(); const admin = await adminContext.newPage();
        await login(admin,1);
        const colors = [];
        for (const theme of ['dourado','azul','verde','vermelho','roxo']) {
            await admin.goto(base+'pages/dashboard/dashboard.php?section=configuracoes');
            const radio = admin.locator(`input[name=valor][value=${theme}]`); await radio.check();
            await radio.locator('xpath=ancestor::form').locator('button[type=submit]').click();
            await admin.waitForURL('**/dashboard.php?section=configuracoes');
            await page.goto(base+'index.php');
            test('Global theme shared: '+theme, await page.locator('html').getAttribute('data-site-theme')===theme);
            colors.push(await page.locator('html').evaluate(e=>getComputedStyle(e).getPropertyValue('--accent').trim()));
        }
        test('Five distinct color palettes',new Set(colors).size===5);
        await page.goto(base+'pages/marketplace/marketplace.php');
        test('Product content is not translated',(await page.content()).includes('User content must stay unchanged'));
        const mobileContext = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
        const mobile = await mobileContext.newPage(); await login(mobile,3);
        await mobile.goto(base+'pages/dashboard/dashboard.php?section=perfil');
        await mobile.locator('[data-bs-target="#modalPerfilEditar"]').first().tap();
        await mobile.locator('#student-estado-combobox').fill('Acre');
        await mobile.locator('#student-estado-listbox [role=option]').first().tap();
        test('Touch selection',await mobile.locator('#student-estado').inputValue()==='AC');
        test('Mobile no horizontal overflow',await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        const screenshots=process.env.TEMP+'/onefit-interface-fixture/screenshots'; fs.mkdirSync(screenshots,{recursive:true});
        await mobile.screenshot({path:screenshots+'/profile-mobile.png',fullPage:true});
        await admin.screenshot({path:screenshots+'/settings-desktop.png',fullPage:true});
        test('No JavaScript runtime errors',errors.length===0);
        fs.writeFileSync(process.env.TEMP+'/onefit-interface-fixture/browser-results.json',JSON.stringify(results,null,2));
        console.log(`Browser checks: ${results.length} passed.`);
    } finally { await browser.close(); }
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
