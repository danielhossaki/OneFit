# Servico de pagamentos — etapa 8

Preparacao e adaptador apenas: nenhum endpoint, banco, webhook ou integracao
com formularios. Incluir/construir as classes nao carrega credenciais nem chama
a rede. Chamar criarPreferencia explicitamente no gateway/service chama a API;
nao chamar em testes reais antes da revisao da proxima etapa.

## Contrato do chamador futuro

- Consultar o plano ativo e o preco no banco, validar usuario e matricula.
- Converter o DECIMAL do banco para centavos por manipulacao decimal exata.
- Fornecer a prepararMatricula o inteiro calculado no servidor, nunca POST/GET.
- Titulo/descricao sao textos do catalogo; nao incluir nome do aluno, CPF ou e-mail.
- URLs sao configuracao interna HTTPS, nunca entradas livres do navegador.
- Persistir referencia, idempotencia, valor e expiracao antes de enviar a API.
- Reutilizar a preparacao em retries; reconciliar timeouts antes de reenviar.
- O header de idempotencia nao substitui unicidade/transacoes locais nem garante
  por si so que o endpoint de preferencias deduplique solicitacoes.
- Nenhum status retornado aqui ativa matricula. Confirmacao futura exige webhook,
  consulta de pagamento, valor, moeda, referencia, conta e idempotencia de negocio.
- Marketplace, reserva de estoque e cashback nao estao implementados.

## Ambiente e checkout

A API usa https://api.mercadopago.com em ambos os ambientes. Nao existe um host
de API sandbox alternativo a inventar. A configuracao central exige testing;
a conta vendedora deve continuar sendo a conta de teste validada na etapa 7.
O gateway rejeita pagamentos cujo live_mode nao seja explicitamente false.

Politica atual: usar exclusivamente init_point, conforme orientacao especifica do
suporte Mercado Pago no ticket WCS-49381, bug DXT40, informada pelo responsavel
pelo projeto. Aplicacao criada dentro de conta vendedora de teste, credenciais
desse usuario e comprador de teste. GET /users/me confirmou test_user, BR e MLB.
Nao foi necessario consultar novamente essa API para alterar esta politica.

O gateway exige testing na configuracao central e retorna ambiente=testing.
init_point deve ser HTTPS em mercadopago.com.br ou www.mercadopago.com.br;
sandbox_init_point nao e obrigatorio e nao e usado como fallback. Se init_point
estiver ausente ou invalido, a operacao falha de forma sanitizada. A URL completa
nao e registrada nos logs. Seu hostname nao e prova de ambiente produtivo/teste.

A politica CHECKOUT_FIELD e explicita no gateway, independente do valor/prefixo
do token. Trocar somente o token nao muda a selecao nem habilita production.
Production continua bloqueado mesmo se definido no .env: exigira uma politica
de configuracao diferente, revisao e autorizacao de entrada em producao. Trocas
de credenciais de teste exigem nova validacao da conta em etapa autorizada.
Nenhuma preferencia foi criada para validar redirecionamento nesta alteracao.

Nenhum metodo e definido como padrao. default_payment_method_id e
default_payment_type_id sao omitidos, nunca enviados como null ou string vazia.
Um eventual metodo padrao futuro nunca pode estar excluido: isso causou o HTTP
400 `invalid default_payment_method_id: default payment method is excluded`.
Para diagnostico sem meiosConfirmados, payment_methods inteiro e omitido,
inclusive excluded_payment_methods e excluded_payment_types. O Mercado Pago
apresenta automaticamente as opcoes da conta; verificar manualmente no checkout.
Quando exclusoes forem explicitamente solicitadas, somente tipos obtidos em /v1/payment_methods sao candidatos a
exclusao. O chamador fornece meiosConfirmados consultados pelo backend; nunca
aceitar essa lista do navegador. Sem lista, nao enviar exclusoes especulativas.
Tipos do Pix/bank_transfer sao preservados. Saldo de conta (account_money)
nao pode ser excluido no Checkout Pro via preferencias. Portanto nao prometer
exclusividade absoluta de Pix.

Expiracao Pix: tres dias em UTC com offset ISO 8601 explicito. date_of_expiration
(vencimento Pix) e expiration_date_to (vigencia da preferencia) usam o mesmo
instante, retornado como expira_em para futura persistencia na cobranca local.
Nao usar mais 30 minutos. Fonte:
https://www.mercadopago.com.br/developers/pt/docs/checkout-pro-preferences/additional-settings/expiration-date

Referencias oficiais consultadas:
- https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/additional-settings/payment-methods
- https://www.mercadopago.com.br/developers/en/reference/online-payments/checkout-pro-preferences/overview
- https://github.com/mercadopago/mercadopago-claude-marketplace/blob/main/plugins/mercadopago/skills/mp-integrate/SKILL.md

## Transporte e erros

SDK oficial cria/consulta recursos; MercadoPagoHttpClient implementa o contrato
de transporte do SDK para impor conexao de 10s, total de 25s, TLS, ausencia de
redirect/retry e captura de x-request-id. MPResponse 3.16.0 nao preserva headers.
Logs contem somente operacao fixa, HTTP e request ID. Excecoes publicas sao
genericas e nao encadeiam a resposta original. Nao imprimir stack traces em UI.
diagnosticoTecnico() fornece apenas HTTP, request ID e campos de erro filtrados
para desenvolvimento. DiagnosticoPagamento usa vocabulario tecnico permitido:
palavras/valores desconhecidos sao omitidos, mesmo em message/description.
cause.data aceita somente field/path/parameter reconhecidos; demais dados sao
descartados. O corpo original nunca e mantido em excecoes ou logs. Os detalhes
nao sao enviados automaticamente ao navegador nem registrados automaticamente.

## Testes

Executar `php tests/pagamentos.php`. Gateway e transporte falsos; nenhuma conexao
ao banco ou API. Credenciais ficticias sao aplicadas somente ao processo CLI,
sem alterar o .env. O teste e bloqueado quando acessado pelo navegador.
