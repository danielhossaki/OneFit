const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/pix.js', 'utf8');
async function run(data, code = 200, enrollment = false) {
  const elements = {}; let copied; let polls = 0; let redirected = null; const delays = [];
  for (const id of ['pix', 'status', 'code', 'copy', 'ticket', 'qr', 'expiry', 'retry', 'pending', 'approved', 'missing', 'recover']) elements[id] = {
    hidden: true, textContent: '', value: '', dataset: {ref: 'fixture', csrf: 'fixture'},
    addEventListener(name, fn) {this[name] = fn;}, select() {}, removeAttribute() {}
  };
  if(enrollment) elements.continue = {};
  vm.runInNewContext(source, {document: {getElementById: id => elements[id]}, URL, URLSearchParams, Date,
    navigator: {clipboard: {writeText: async value => {copied = value;}}},
    fetch: async (url, options) => {assert.equal(url, 'api.php'); assert.equal(options.method, 'POST'); assert.equal(options.cache, 'no-store'); return {ok: code === 200, status: code, json: async () => data};},
    window: {location: {assign: url => {redirected = url;}}}, clearTimeout: () => {}, setTimeout: (fn, ms) => {delays.push(ms); if(ms===8000) fn(); else polls++;}
  });
  await new Promise(resolve => setImmediate(resolve));
  return {elements, polls, redirected, delays, copy: async () => {await elements.copy.click(); return copied;}};
}
(async () => {
  let r = await run({status: 'pendente', qr_code: 'fixture', ticket_url: 'https://evil.example', nova_tentativa: false});
  assert.equal(r.elements.status.textContent, 'Aguardando pagamento'); assert.equal(r.elements.ticket.hidden, true); assert.equal(r.polls, 1); assert.equal(await r.copy(), 'fixture');
  r = await run({status: 'aprovado', nova_tentativa: false}); assert.equal(r.polls, 0); assert.equal(r.elements.copy.disabled, true); assert.equal(r.elements.pending.hidden,true); assert.equal(r.elements.approved.hidden,false); assert.equal(r.elements.code.value,'');
  r = await run({status: 'expirado', nova_tentativa: true}); assert.equal(r.elements.retry.hidden, false); assert.equal(r.polls, 0);
  r = await run({status:'aprovado'},200,true); assert.equal(r.redirected,'../login/login.php'); assert.deepEqual(r.delays,[8000]);
  r = await run({status:'pendente'},200,true); assert.equal(r.redirected,null); assert.equal(r.elements.recover.hidden,false);
  r = await run({status:'pendente',qr_code:'fixture',qr_code_base64:'aGVsbG8='}); assert.equal(r.elements.qr.hidden,false); assert.equal(r.elements.missing.hidden,true);
  r = await run({}, 401); assert.equal(r.polls, 0);
  r = await run({}, 429); assert.equal(r.polls, 1);
  console.log('Pix UI: polling, copia, URL, estados e falhas OK; rede=0.');
})().catch(() => {console.error('Falha nos testes Pix UI'); process.exit(1);});
