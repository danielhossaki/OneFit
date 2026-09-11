const { chromium } = require(process.env.TEMP + '/onefit-interface-tools/node_modules/playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base='http://127.0.0.1:8765/AN25/OneFit/';
(async()=>{
 const browser=await chromium.launch({channel:'chrome',headless:true});
 try {
  const page=await browser.newPage();
  await page.goto(base+'index.php');
  const results=await page.evaluate(()=>{
   const rgb=hex=>{hex=hex.trim().replace('#','');if(hex.length===3)hex=hex.split('').map(c=>c+c).join('');return [0,2,4].map(i=>parseInt(hex.slice(i,i+2),16)/255);};
   const lum=hex=>rgb(hex).map(c=>c<=.04045?c/12.92:((c+.055)/1.055)**2.4).reduce((sum,c,i)=>sum+c*[.2126,.7152,.0722][i],0);
   const contrast=(a,b)=>(Math.max(lum(a),lum(b))+.05)/(Math.min(lum(a),lum(b))+.05);
   const results=[];
   for(const color of ['dourado','azul','verde','vermelho','roxo']) for(const mode of ['dark','light']){
    document.documentElement.dataset.siteTheme=color;document.documentElement.dataset.theme=mode;
    const css=getComputedStyle(document.documentElement);const token=name=>css.getPropertyValue(name).trim();
    results.push({color,mode,text:contrast(token('--accent'),token('--surface')),button:Math.min(contrast(token('--accent-ink'),token('--accent')),contrast(token('--accent-ink'),token('--accent-bright')))});
   }
   return results;
  });
  for(const result of results){assert.ok(result.text>=4.5,JSON.stringify(result));assert.ok(result.button>=4.5,JSON.stringify(result));}
  await page.goto(base+'pages/matricula/matricula.php');
  const states=await page.locator('#estado option').evaluateAll(options=>options.filter(o=>o.value).map(o=>({code:o.value,label:o.textContent})));
  assert.equal(states.length,27);
  await page.setContent('<main style="display:grid;grid-template-columns:repeat(5,1fr);gap:20px;padding:20px;font:16px sans-serif"></main>');
  await page.evaluate(({states,base})=>{const root=document.querySelector('main');for(const state of states){const cell=document.createElement('div');const img=document.createElement('img');img.src=base+'assets/img/flags/states/'+state.code.toLowerCase()+'.svg';img.style.cssText='display:block;width:160px;height:100px;object-fit:contain';const label=document.createElement('p');label.textContent=state.label;cell.append(img,label);root.append(cell);}}, {states,base});
  await page.waitForFunction(()=>[...document.images].every(img=>img.complete&&img.naturalWidth>0));
  const directory=process.env.TEMP+'/onefit-interface-fixture/screenshots';fs.mkdirSync(directory,{recursive:true});
  await page.screenshot({path:directory+'/state-flags.png',fullPage:true});
  fs.writeFileSync(process.env.TEMP+'/onefit-interface-fixture/contrast-results.json',JSON.stringify(results,null,2));
  console.log('Contrast: 20 combinations passed (>=4.5:1). All 27 state SVGs rendered.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
