// Run against the synthetic fixture from prepare-interface-fixture.php only.
const {chromium} = require(process.env.TEMP + '/onefit-interface-tools/node_modules/playwright-core');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const base = 'http://127.0.0.1:8765/AN25/OneFit/';
function luminance(color) {
 const channels = color.startsWith('color(srgb') ? color.match(/[\d.]+/g).slice(0,3).map(Number) : color.match(/[\d.]+/g).slice(0,3).map(n=>Number(n)/255);
 return channels.map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
}
function contrast(a,b){const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
const out = process.env.TEMP + '/onefit-dashboard-review';
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser = await chromium.launch({channel:'chrome',headless:true});
 const report = {sections:[],themes:[],errors:[],overflows:[]};
 try {
  for (const [role,id] of [['admin',1],['aluno',3],['profissional',4],['vendedor',5]]) {
   const context = await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
   const page = await context.newPage();
   await context.route('https://**/*', async route => {
    const url=new URL(route.request().url());
    const name=url.hostname==='fonts.googleapis.com'?'manrope.css':url.pathname.split('/').pop();
    const file=out+'/vendor/'+name;
    if(fs.existsSync(file)) await route.fulfill({path:file});
    else await route.abort();
   });
   page.on('pageerror',e=>{if(page.url().includes('/dashboard/'))report.errors.push(role+': '+e.message)});
   await page.goto(base+'pages/login/login.php');
   await page.locator('#email').fill(`fixture${id}@example.test`);
   await page.locator('#password').fill('Fixture-only-123!');
   await Promise.all([page.waitForURL('**/dashboard.php*'),page.locator('button[type=submit]').first().click()]);
   await page.locator('#boNav .bo-nav-item').first().waitFor();
   assert.ok(await page.evaluate(()=>typeof bootstrap !== 'undefined'), 'Bootstrap must load for a valid review');
   assert.equal(await page.evaluate(()=>BO_PERFIL_LOGADO),role,'The authenticated role must match the fixture');
   const sections = await page.locator('#boNav .bo-nav-item[data-section]').evaluateAll(es=>es.filter(e=>e.tagName==='BUTTON').map(e=>e.dataset.section));
   for (const width of [1440,1024,768,390,320]) {
    await page.setViewportSize({width,height:900});
    for(const section of sections) {
     // Use the existing navigation handler, including off-canvas behavior.
     if(width<992) await page.locator('#boSidebarToggle').click();
     await page.locator(`#boNav [data-section="${section}"]`).click();
     const result=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,visible:document.querySelector('.bo-content-section.active')?.dataset.section,offenders:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+2 && e.getBoundingClientRect().width && !e.closest('.table-responsive,.bo-table-wrap,.bo-sidebar,.modal')).slice(0,5).map(e=>e.className)}));
     if(result.scroll>width+2)report.overflows.push({role,section,...result});
     report.sections.push({role,section,width});
    }
   }
   await page.setViewportSize({width:1440,height:1000});
   await page.locator('#boNav .bo-nav-item').first().click();
   for(const theme of ['dourado','roxo','vermelho','azul','verde']) for(const mode of ['dark','light']) {
    await page.evaluate(({theme,mode})=>{document.documentElement.dataset.siteTheme=theme;document.documentElement.dataset.theme=mode},{theme,mode});
    const colors=await page.evaluate(()=>{
     const color=el=>{const s=getComputedStyle(el);return {fg:s.color,bg:s.backgroundColor,radius:s.borderRadius}};
     const probe=document.createElement('span');document.body.append(probe);
     const resolve=token=>{probe.style.color=`var(${token})`;return getComputedStyle(probe).color};
     const tokens=Object.fromEntries(['--text','--text-muted','--surface','--surface-2','--accent','--accent-ink','--ui-accent-text'].map(t=>[t,resolve(t)]));probe.remove();
     return {body:color(document.body),sidebar:color(document.querySelector('.bo-sidebar')),header:color(document.querySelector('.bo-header')),card:color(document.querySelector('.bo-metric-card,.bo-card,.bo-data-panel')),tokens};
    });
        const t=colors.tokens;
    for(const [fg,bg] of [['--text','--surface'],['--text-muted','--surface-2'],['--ui-accent-text','--surface'],['--accent-ink','--accent']]) {
     const ratio=contrast(t[fg],t[bg]);assert.ok(ratio>=4.5,`${role}/${theme}/${mode}: ${fg} ${ratio}`);
    }
    report.themes.push({role,theme,mode,...colors});
    await page.screenshot({path:`${out}/${role}-${theme}-${mode}.png`,fullPage:true});
   }
      // Verify representative real forms and their responsive modal geometry.
   const modalSection=role==='admin'?'usuarios':role==='profissional'?'alunos':role==='aluno'?'perfil':null;
   if(modalSection){
    await page.locator(`#boNav [data-section="${modalSection}"]`).click();
    const trigger=page.locator('.bo-content-section.active [data-bs-toggle="modal"], .bo-content-section.active [onclick*="boOpenForm"]').first();
    if(await trigger.count()){
     await trigger.click();
     await page.locator('.modal.show').waitFor();
     for(const width of [1440,390,320]){
      await page.setViewportSize({width,height:900});
      assert.ok(await page.locator('.modal.show .modal-content').evaluate(e=>e.getBoundingClientRect().right<=innerWidth+1),'modal overflow');
      await page.screenshot({path:`${out}/${role}-modal-${width}.png`});
     }
     await page.locator('.modal.show [data-bs-dismiss="modal"]').first().click();
    }
   }
   await page.setViewportSize({width:390,height:844});
   await page.screenshot({path:`${out}/${role}-mobile.png`,fullPage:true});
   await page.locator('#boNotificationsToggle').click();
   await page.locator('#boNotificationsPanel').waitFor({state:'visible'});
   await page.screenshot({path:`${out}/${role}-notifications-mobile.png`});
   await page.locator('#boNotificationsToggle').click();
   await page.locator('#boAvatar').click();
   await page.locator('#boUserMenu').waitFor({state:'visible'});
   await page.keyboard.press('Escape');
   await page.goto(base+'pages/dashboard/alterar-senha.php');
   assert.equal(await page.locator('input[type=password]').count(),3);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),'password overflow');
   await page.screenshot({path:`${out}/${role}-password-mobile.png`,fullPage:true});
   await context.close();
   console.log(role+': reviewed '+sections.length+' sections, 5 sizes, 10 theme/mode combinations');
  }
 } finally { fs.writeFileSync(out+'/report.json',JSON.stringify(report,null,2));await browser.close(); }
 assert.equal(report.errors.length,0,JSON.stringify(report.errors));
 assert.equal(report.overflows.length,0,JSON.stringify(report.overflows));
 console.log('PASS: '+report.sections.length+' section/viewport checks, '+report.themes.length+' theme checks. '+out);
})().catch(e=>{console.error(e);process.exitCode=1});