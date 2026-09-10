# Pix integrado (somente testing)

Os endpoints de matrícula e carrinho usam `PixCheckout`, com sessão e CSRF. O
valor contratado vem do banco. A matrícula aguarda com datas nulas; o pedido
aguarda com estoque reservado e cashback indisponível. `PixConciliacaoService`
valida recebedor, valor, moeda, referência, IDs e estados retornados pelo gateway.
A aprovação válida ativa a matrícula ou confirma o pedido uma única vez.
Checkout Pro permanece separado, com a regra do ticket WCS-49381 preservada.

## Restrição do teste oficial

Na matrícula manual, `PixTesteLocal` permite o usuário autenticado em `testing`
somente quando host e endereços reais do cliente e servidor são loopback.
Cabeçalhos Forwarded/X-Forwarded-For não autorizam esse fluxo. Produção e acesso
remoto permanecem bloqueados. A lista `pix_testes_tecnicos` continua sendo usada
pelas fixtures automatizadas e pelo marketplace, sem mudança nessas regras.

O preço normal do banco permanece em `matricula.valor_contratado`. A cobrança
técnica e sua tentativa registram explicitamente R$ 50,00, identificadas pelo
prefixo `pix_local_v1:` na chave de negócio. Isso não altera o preço do plano nem
representa desconto comercial. O adaptador acrescenta somente o pagador
sintético oficial e APRO. APRO nunca participa da validação financeira.
Cobranças marcadas só podem ser conciliadas e ativadas pelo fluxo local de
testing; o polling local faz essa confirmação. Worker e webhook externos não
liberam essa matrícula técnica. Não promover esses registros para produção.
O resolvedor continua exigindo cadastro ativo e e-mail verificado; nenhum dado
pessoal do cadastro é enviado no teste. Produção continua bloqueada.

## Rotas

- `pages/matricula/matricula.php`: criação de conta para visitantes; seleção de
  plano nos cards originais da etapa 3 e confirmação Pix na etapa 4. Toda entrada
  comum inicia Dados → Endereço → Plano → Pagamento, mesmo com login. Cada avanço
  é validado no servidor por `MatriculaWizard`, vinculado à sessão e a um ID aleatório
  de fluxo. URL/JavaScript não concedem acesso a etapas futuras. Recarregar a URL
  do fluxo mantém seu progresso; links comuns limpam apenas esse rascunho.
  Senhas não são armazenadas no rascunho. Erros AJAX mantêm os campos no navegador.
  Uma referência Pix já associada ao fluxo é reutilizada, nunca cancelada por
  iniciar outro assistente. O cadastro ainda exige verificação de e-mail e login.
  A página duplicada
  `pages/pagamentos/planos.php` foi removida e retorna 404; não há rota intermediária.
  O aviso de teste técnico aparece na confirmação da etapa 4 e na tela Pix,
  junto do plano e seu preço normal, separado dos R$ 50,00 simulados. O cadastro não cria
  matrícula ativa ou pagamento legado.
- `pages/pagamentos/api.php`: POST autenticado com CSRF; criação e polling com
  limite por sessão e lease por tentativa. Sem aprovação fornecida pelo cliente.
- `pages/pagamentos/pix.php`: tela comum, propriedade por referência, no-store,
  QR validado, cópia, ticket HTTPS autorizado, estado e polling a cada seis segundos.
- `pages/carrinho/carrinho.php`: endereço/carrinho da sessão, frete e preço do banco;
  mantém o carrinho até a confirmação. Logística exige pedido pago.
- `pages/pagamentos/webhook.php`: POST JSON até 16 KiB, assinatura HMAC SHA-256,
  comparação constante, `data.id` coerente com a query, deduplicação e HTTP 202.
  A assinatura segue o manifest oficial. A tolerância de ±300 segundos é política
  local de replay, não um prazo prometido pelo Mercado Pago. O relógio precisa
  estar sincronizado. Sem segredo, responde 503 e não aceita eventos.

## Worker, reservas e falhas

`php scripts/pix-conciliar.php --processar` processa a fila, consulta Orders
pendentes salvas e entrega notificações pendentes. Deve ser agendado a cada minuto
no ambiente autorizado. Não foi instalado agendamento nem publicado um endpoint.
O worker só faz GET; nunca cria uma Order para recuperar evento desconhecido.
Eventos sem vínculo tentam novamente e são retidos como ignorados após 24 horas.
Uma notificação do sino usa outbox e transação separada, sem desfazer pagamento.

O vencimento é sincronizado com o provedor, inclusive duração ISO 8601. Sem campo
explícito, Orders/Pix usa o padrão documentado de 24 horas a partir da criação.
Isso é diferente dos três dias configurados no Checkout Pro. Cobrança, pagamento
e reserva compartilham o vencimento. O prazo sozinho NÃO autoriza liberar estoque:
se a API estiver indisponível ou ainda indicar pendência, conserva-se a reserva.
Cancelamento/expiração/rejeição confirmados liberam a reserva e a tentativa.
O worker reconsulta pendências vencidas; cancelamento remoto automático não foi
implementado. A documentação admite atraso entre vencimento e estado final.

UUID/referência são persistidos antes do POST e reutilizados na recuperação.
Nenhuma transação fica aberta durante HTTP. Lease de 60 segundos e versão
impedem aplicar respostas de workers antigos. Envio incerto sem Order conhecida
por mais de dez minutos exige revisão manual; não se gera outra chave.
Pagamento divergente, estorno ou aprovação tardia contraditória não são aprovados
silenciosamente. Pedidos totalmente cobertos por cashback são bloqueados neste
fluxo Pix; exigem um fluxo local sem provedor antes da futura liberação comercial.

## Esquema e privacidade

A migração aditiva `20260909_003_pix-integrado.sql` acrescenta artefatos mínimos
do QR e datas do provedor em pagamentos; cashback ganho na cobrança; vínculo
único do cashback central; reservas; outbox; e lista de fixtures técnicas.
O banco conserva a origem `marketplace` prevista nas FKs/ENUM; a tela identifica
essa origem como `pedido`. Não se renomeiam registros existentes.
Datas de criação/atualização do provedor não representam aprovação bancária.
`aprovado_em` e vigência usam a primeira confirmação local. Reprocessamento
preserva essas datas. Após aprovação, os artefatos do QR são removidos.
Não há escrita na tabela singular legada `pagamento`.

Não registrar QR, URL de ticket, respostas integrais, pagador ou credenciais.
Exceções públicas são genéricas. A identidade do vendedor é consultada uma vez
por instância de gateway/requisição, validando explicitamente test_user BR/MLB.
Não reutilizar instâncias entre credenciais ou requisições distintas.

## Validação e operação

A tela Pix separa pendência e estados finais. Dados visuais ausentes em consultas
pendentes preservam o QR validado anteriormente; estados finais limpam esses
artefatos e escondem a área de pagamento. A recuperação manual usa `consultar`,
que nunca cria Order. O polling habitual mantém sua recuperação existente.
Matrículas aprovadas seguem para o login em oito segundos, sem criar sessão ou
enviar e-mail. Mantém-se a ordem atual: verificar e-mail e autenticar antes do Pix.
Pedidos não recebem esse redirecionamento de matrícula. `tests/pix-visual.php`
e `tests/pix-ui.js` cobrem preservação, estados e redirecionamento sem rede.

Suítes sem rede: `pagamentos.php`, `orders-pix.php`, `orders-pix-identidade.php`,
`pagador-pix.php`, `pix-seguranca.php`, `student-profile.php`, `pix-ui.js` e
`student-profile.js`, dentro de `tests/`.

`tests/pix-local.php` verifica loopback, produção e valor da fixture sem rede.
`tests/pix-local-http.php --uma-order-local <diretorio-privado>` é um diagnóstico
manual separado: percorre as quatro etapas com usuário sintético sem allowlist,
faz no máximo uma criação real de teste e verifica polling e reenvio. Exige trava
exclusiva fora da pasta pública e limpa somente suas fixtures, comparando os
dados anteriores. Não executar automaticamente nem remover a trava para repetir.

Integração autorizada: `php tests/pix-integrado.php --development-fixtures` e
`php tests/pix-persistencia.php --development-fixtures`. Usam gateway falso,
fixtures isoladas, duas conexões, HTTP local e limpeza em finally. Comparam hashes
e contagens; AUTO_INCREMENT pode avançar e não é restaurado. Executar sem outros
escritores. Não utilizar essas suítes em produção.

`tests/pix-real-controlado.php` é um diagnóstico CLI manual, separado das suítes.
Exige `--uma-order-tecnica` e diretório privado de backup. Cria trava exclusiva
antes da API, aceita no máximo um POST e um GET da Order e sempre limpa fixtures.
Não remover a trava para repetir uma tentativa automaticamente.

Para webhook externo falta configurar a URL HTTPS pública desta rota no tópico
Orders da aplicação e o segredo correspondente no `.env`; nenhum túnel, painel
ou credencial foi alterado. A variável já existe no `.env.example`.
Antes de produção: autorização explícita, credenciais/estratégia comercial,
revisão da recuperação/idempotência, cancelamentos e estornos, total zero,
retenção segura do pagador, monitoração e testes de ponta a ponta em homologação.

Referências oficiais:
- https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-test/pix
- https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/pix
- https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/notifications
