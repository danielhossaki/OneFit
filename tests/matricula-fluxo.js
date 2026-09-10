const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function run(auth, saved) {
  const handlers = {}; const storage = new Map(saved ? [['onefit.matricula.plano', saved]] : []);
  const node = extra => Object.assign({value: '', dataset: {}, style: {}, classList: {toggle(){}, contains(){return false}, add(){}, remove(){}}, addEventListener(k,f){this[k]=f}, setAttribute(){}, closest(){return null}, focus(){}, scrollIntoView(){}, setCustomValidity(){}, checkValidity(){return true}}, extra);
  const inputs = ['1','2'].map(value => node({value, type:'radio', checked:value==='1'}));
  const options = inputs.map(input => node({querySelector(){return input}}));
  const next = [1,2,3].map(() => node()); const prev = [2,3,4].map(() => node());
  const planWrap = node(); const submit=node();
  const steps=[1,2,3,4].map(n=>node({dataset:{step:String(n)}, querySelectorAll(){return n===3?inputs:[]}, querySelector(q){if(q.includes(':checked'))return inputs.find(i=>i.checked);if(q==='.plan-select')return planWrap;if(q==='[data-next]')return next[n-1];if(q==='[data-prev]')return prev[n-2];return null}}));
  const progress=[1,2,3,4].map(n=>node({dataset:{stepLabel:String(n)}}));
  const form=node({dataset:{authenticated:auth?'1':'0'},querySelectorAll(q){return ({'.form-step':steps,'[data-next]':next,'[data-prev]':prev,'.plan-option':options,'input, select':inputs})[q]||[]},querySelector(q){return q==='.plan-select'?planWrap:q.includes('submit')?submit:null}});
  const subtitle=node(); const document={querySelector:q=>q==='.matricula-form'?form:node(),querySelectorAll:()=>progress,getElementById:id=>id==='step-subtitle'?subtitle:id==='progress-fill'?node():['password','confirmar-senha'].includes(id)?node():null};
  let finalCalls=0;let rejectStep=false;form.action='final';form.dataset.stepCurrent='1';
  class FakeFormData extends Map {constructor(){super([['id_plano','2'],['termos','on']]);}}
  vm.runInNewContext(fs.readFileSync('assets/js/matricula.js','utf8'),{document,FormData:FakeFormData,window:{innerWidth:600,location:{assign(){}},addEventListener:(k,f)=>handlers[k]=f},sessionStorage:{removeItem:k=>storage.delete(k)},
    async fetch(url,options){if(url==='final'){finalCalls++;return {ok:true,redirected:true,url:'/pix'};}return {ok:!rejectStep,json:async()=>rejectStep?{erro:'invalid'}:{etapa:Number(options.body.get('etapa'))+1}};},console});
  return {form,inputs,next,prev,submit,handlers,subtitle,storage,get finalCalls(){return finalCalls;},reject(v){rejectStep=v;}};
}
(async()=>{
for(const auth of [false,true]) {
  const r=run(auth,'2');assert.equal(r.inputs[0].checked,true);assert.equal(r.storage.size,0);
  assert.ok(r.subtitle.textContent.startsWith('Preencha'));
  r.reject(true);await r.next[0].click();assert.ok(r.subtitle.textContent.startsWith('Preencha'));
  r.reject(false);await r.next[0].click();assert.ok(r.subtitle.textContent.startsWith('Precisamos'));
  await r.next[1].click();assert.ok(r.subtitle.textContent.startsWith('Escolha'));
  r.inputs[0].checked=false;r.inputs[1].checked=true;r.inputs[1].change();
  await r.next[2].click();r.prev[2].click();assert.equal(r.inputs[1].checked,true);await r.next[2].click();
  let prevented=0;const event={preventDefault(){prevented++}};
  const first=r.form.submit(event);await r.form.submit(event);await first;assert.equal(r.finalCalls,1);assert.equal(r.submit.disabled,true);
  r.handlers.pageshow();assert.equal(r.submit.disabled,false);
}
console.log('Wizard: inicio em Dados, ordem validada, erro, voltar e envio unico OK; rede=0; banco=0.');
})().catch(e=>{console.error(e);process.exit(1)});
