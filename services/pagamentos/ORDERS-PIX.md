# Orders/Pix isolado

OrdersPixService prepara o envelope e normaliza a resposta. OrdersPixGateway
cria/consulta somente Orders, sem SQL ou formulários dentro do adaptador. A
integração usa [PixCheckout e os serviços de persistência](PIX-INTEGRADO.md). Checkout Pro
permanece independente. O transporte compartilhado apenas ganhou a allowlist
/v1/orders; seus timeouts, TLS, ausencia de retries e sanitizacao foram mantidos.

prepararTesteOficial usa 5000 centavos exclusivamente como fixture oficial.
Nao e minimo da aplicacao. Valores gerais positivos sao tratados em centavos,
com strings decimais na API. BRL e derivado de country_code=BRA, nao de um campo
currency_id inventado. Payer e somente APRO/test_user_br@testuser.com.

UUID v4 e gerado uma vez no envelope. criarOrder reutiliza exatamente a chave
recebida. O orquestrador persiste os campos estáveis desse envelope antes de enviar e
reutiliza-lo ao conciliar; nao gerar nova Order cegamente apos timeout.

Normalizacao e preparacao nao ativam matricula nem aprovam cobranca local.
Status desconhecido falha fechado. action_required/processing sao pendentes;
processed/accredited indica aprovacao. Estorno parcial mantem aprovado com
detalhe; contestacoes exigem tratamento futuro, nao sao aprovadas implicitamente.

ticket_url, qr_code e qr_code_base64 sao devolvidos apenas para futuro consumo
controlado. Nunca logar arrays de resposta, QR, URLs, payer ou credenciais.
O relatorio tecnico deve mostrar somente presenca dos artefatos e IDs mascarados.
live_mode e preservado quando retornado; nao inferir producao/teste apenas dele.

O teste APRO retorna action_required e e atualizado automaticamente para aprovado.
A documentacao orienta consultar GET /v1/orders/{id}, sem determinar um intervalo
fixo. O diagnóstico manual mantém uma criação e uma consulta. O fluxo integrado
autenticado possui polling com lease e limite de frequência, sem gerar nova chave
em recuperação. Consulte [PIX-INTEGRADO.md](PIX-INTEGRADO.md).

Fonte: https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-test/pix
Testes sem rede: php tests/orders-pix.php; php tests/pagamentos.php.
