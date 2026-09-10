# Persistencia Pix de matricula

Este documento descreve o núcleo da persistência. A integração atual de telas,
marketplace, reservas e webhook está em [PIX-INTEGRADO.md](PIX-INTEGRADO.md), que
prevalece sobre as limitações da etapa isolada descritas abaixo. O esquema atual
inclui a migração 003. Checkout Pro continua separado.

`MatriculaPixService` recebe uma conexao dedicada, gateway injetado e relogio
opcional. Obtem o recebedor por `consultarIdentidade`, fora de transacoes, antes
de persistir. Exige BR/MLB e test_user explicito. O ID do usuario deve vir da sessao
autenticada, nunca de parametros livres do navegador. Nao passar conexao com uma
transacao externa aberta. As APIs internas nao sao controllers publicos.

- `MatriculaPixRepositorio`: plano ativo/preco/duracao e matricula pendente.
- `CobrancaPixRepositorio`: propriedade e bloqueios de cobranca/tentativa.
- `PixRepositorio`: consultas preparadas e transacoes com erros genericos.
- `MatriculaPixService`: deduplicacao, referencia, UUID persistido e lease.
- `PixConciliacaoService`: validacao do resultado do gateway e liquidacao unica.
- `OrdersPixGatewayInterface`: contrato injetavel; nenhum SQL no gateway.

Fluxo: bloquear usuario e ler plano; reutilizar slot ou criar registros locais;
commit; adquirir lease de 60 segundos e incrementar versao; commit; chamar gateway;
em outra transacao validar resultado e persistir. O worker antigo nao pode aplicar
resultado apos outro worker adquirir uma versao nova. Cliques comuns nao fazem
retry de envio incerto. `recuperar` e uma operacao interna explicita: consulta
a Order conhecida ou reutiliza UUID/referencia/valor. Sem ID apos dez minutos,
interrompe para revisao manual. Esse limite conservador NAO e garantia de
idempotencia do provedor; habilitar transporte real exige revisar sua janela.

Uma cobranca por tentativa, com historico preservado; `aberta` significa pendente.
Rejeicao/cancelamento/expiracao confirmada libera slot. Expiracao de lease nao
significa expiracao financeira. Uma nova tentativa cria nova matricula pendente;
nao adota registros legados. Aprovacao tardia de tentativa encerrada e divergencia
exige revisao, sem ativacao automatica. Nenhuma escrita em `pagamento` singular.

Conciliacao exige valor, moeda, referencia, recebedor e IDs coerentes; compara
status da Order e pagamento e exige processed/accredited para ativar. Data de
inicio e a data da primeira conciliacao aprovada pelo relogio local no fuso de Sao Paulo;
fim e inicio mais duracao preservada. Eventos de mesmo estado sao deduplicados.
Respostas divergentes/timeout deixam envio incerto e matricula pendente. Nenhuma
resposta completa ou dado do pagador e persistido. No modo integrado, apenas os
artefatos mínimos validados do QR/ticket são persistidos; nunca são registrados em logs.

## Adaptador real e identidade independente

O bloqueio de classe foi removido. `OrdersPixGateway` implementa a interface e
aceita o envelope persistido. `consultarIdentidade` faz GET /users/me somente
quando chamado, retornando id/pais/site/test_user, sem outros dados. A presenca
exata de test_user em tags e evidencia explicita; ausencia resulta em NULL e
bloqueia testing. Antes do POST, o adaptador reconfirma a identidade e compara
com o recebedor esperado persistido. A Order fornece recebedor_id a partir de
user_id raiz; ausencia ou divergencia impede conciliacao. live_mode nao decide
sozinho o ambiente. Producao permanece bloqueada pela configuracao testing.

O adaptador recebe `pagador: PagadorPix` no envelope, sem callback livre de email.
`MatriculaPixService` utiliza `PagadorPixResolver` antes de criar registros e antes
do envio. A fonte e SELECT por ID autenticado em usuarios: email, email_verificado,
status. Exige conta ativa e email valido/verificado em ambos os ambientes.
O endpoint obtém o ID de $_SESSION['id_usuario'], nunca do POST;
o email da sessao tambem nao e fonte confiavel para resolver o cadastro atual.

Testing retorna sempre test_user_br@testuser.com, sem dados reais ou nome.
Production retorna exclusivamente email verificado do banco, sem nome/CPF;
resolver um DTO production nao desbloqueia o gateway, que o rejeita antes de I/O.
`PagadorPix` e readonly, oculta dados em debug/JSON e bloqueia serializacao.
Somente paraApi() expoe dados minimos na fronteira HTTP; nunca logar esse array.
Nenhum dado do pagador e persistido nos eventos/registro financeiro.

APRO e opcional somente com PagadorPix::testing(true) e envelope fixture_oficial
explicito de 5000 centavos. Matricula usa testing() sem APRO, sem mudar o preco.
A fixture oficial nao e uma regra comercial ou evidencia financeira. O email
sintetico constante preserva os dados de retry. Antes de habilitar producao,
planejar o snapshot seguro do email para preservar payload em alteracoes de cadastro.
Referencia da fixture: https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-test/pix
O wrapper técnico da integração usa essa fixture apenas para registros autorizados
na lista técnica. APRO nunca é evidência de identidade, ambiente ou aprovação.

created_date e last_updated_date sao normalizados como provedor_criado_em e
provedor_atualizado_em. Exigem ISO 8601 com offset, calendario valido, data a
partir de 2000 e no maximo cinco minutos alem do relogio injetado; atualizacao
nao pode anteceder criacao. Saida UTC com seis casas; nanossegundos sao truncados.
Ausencia/invalidez bloqueia o resultado. last_updated_date NAO e data exata de
aprovacao. A migração 003 acrescentou colunas próprias para persistir essas datas
no modo integrado; não reaproveitam nem substituem os campos locais.

aprovado_em registra a primeira confirmacao local do pagamento nesta integracao;
ultima_consulta_em usa o mesmo relogio. A vigencia parte dessa confirmacao em
America/Sao_Paulo; reprocessar nao altera datas. Nao se afirma o instante bancario
exato da aprovacao. Checkout Pro conserva seu contrato anterior.

Referencia do contrato Orders:
https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api/get-order/get

## Testes de integracao

`php tests/pix-persistencia.php --development-fixtures`

Quando escritas no banco nao estiverem autorizadas, NAO executar essa suite.
Executar `php tests/orders-pix-identidade.php`, `php tests/orders-pix.php` e
`php tests/pagamentos.php`: transporte e repositorio falsos, sem banco/rede.
Executar tambem `php tests/pagador-pix.php` para validacao das estrategias e DTO.

Somente CLI, opt-in explicito, banco de desenvolvimento autorizado. Cria usuarios
e planos sinteticos, sem aproveitar dados de pessoas reais; usa gateway falso e
duas conexoes para sobrepor uma segunda requisicao durante o envio. Verifica
unicidade no banco, timeout, recuperacao, isolamento e conciliacao. Em finally,
remove apenas IDs das fixtures, em ordem de FKs, e compara contagens e hashes de
todas as linhas anteriores (incluindo legado e pedidos). Nao imprime os registros.
Sequencias AUTO_INCREMENT podem avancar e nao sao restauradas; nao ha DDL.
Executar sem outros escritores concorrentes para a comparacao global ser valida.
