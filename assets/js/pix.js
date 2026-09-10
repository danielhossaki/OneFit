(()=>{'use strict';const root=document.getElementById('pix');if(!root)return;
const el=id=>document.getElementById(id);let stopped=false,busy=false,timer,redirect=false;
const labels={criado:'Aguardando pagamento',pendente:'Aguardando pagamento',aprovado:'Pagamento aprovado',recusado:'Pagamento rejeitado',cancelado:'Pagamento cancelado',expirado:'Pagamento expirado',estornado:'Pagamento estornado'};
function render(d){
const pending=['criado','pendente'].includes(d.status);stopped=!pending&&Object.hasOwn(labels,d.status);
el('status').textContent=labels[d.status]||'Aguardando confirma\u00e7\u00e3o';el('pending').hidden=!pending;el('approved').hidden=d.status!=='aprovado';
el('code').value=pending?(d.qr_code||''):'';el('copy').disabled=!el('code').value;
el('qr').hidden=true;el('qr').removeAttribute('src');
if(pending&&d.qr_code_base64&&/^[A-Za-z0-9+/=]+$/.test(d.qr_code_base64)){el('qr').src='data:image/png;base64,'+d.qr_code_base64;el('qr').hidden=false;}
el('ticket').hidden=true;if(pending&&d.ticket_url){try{const u=new URL(d.ticket_url);if(u.protocol==='https:'&&['mercadopago.com.br','www.mercadopago.com.br'].includes(u.hostname)&&!u.username&&!u.password&&!u.port){el('ticket').href=u.href;el('ticket').hidden=false;}}catch{}}
el('missing').hidden=!pending||!!(d.qr_code&&d.qr_code_base64);el('recover').hidden=el('missing').hidden;
el('expiry').textContent=d.vencimento?new Date(d.vencimento).toLocaleString('pt-BR'):'Aguardando provedor';el('retry').hidden=!d.nova_tentativa;
if(d.status==='aprovado'&&el('continue')&&!redirect){redirect=true;setTimeout(()=>{window.location.assign('../login/login.php');},8000);}
}
el('copy').addEventListener('click',async()=>{if(!el('code').value)return;try{await navigator.clipboard.writeText(el('code').value);el('copy').textContent='C\u00f3digo copiado!';}catch{el('code').select();el('copy').textContent='Selecione e copie o c\u00f3digo';}});
el('qr').addEventListener('error',()=>{el('qr').hidden=true;el('missing').hidden=false;el('recover').hidden=false;});
el('recover').addEventListener('click',()=>{clearTimeout(timer);poll('consultar');});
async function poll(action='status'){if(busy||stopped)return;busy=true;el('recover').disabled=true;
try{const body=new URLSearchParams({acao:action,referencia:root.dataset.ref,csrf_token:root.dataset.csrf});const r=await fetch('api.php',{method:'POST',body,credentials:'same-origin',cache:'no-store'});if(r.status===401||r.status===403){stopped=true;throw Error();}if(!r.ok)throw Error();render(await r.json());
}catch{el('status').textContent='N\u00e3o foi poss\u00edvel atualizar agora. Aguarde uma nova consulta.';}finally{busy=false;el('recover').disabled=stopped;if(!stopped)timer=setTimeout(poll,6000);}}
try{const initial=el('pix-initial');if(initial)render(JSON.parse(initial.textContent));}catch{}
if(!stopped)poll();})();
