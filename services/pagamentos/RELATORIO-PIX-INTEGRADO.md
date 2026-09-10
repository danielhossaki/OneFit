# Resultado — 09/09/2026

Implementada a integração Orders/Pix de matrícula e marketplace em testing,
restrita a fixtures técnicas. Produção permanece bloqueada. Checkout Pro
preservado. Nenhum commit/push, publicação, túnel ou alteração do `.env`.

## Validação real única

- `/users/me`: test_user explícito, BR/MLB; identidade obtida uma vez.
- Um POST de R$ 50,00: HTTP 201, `action_required/waiting_transfer`.
- Order `ORDTS…K8CQ`; pagamento `PAY01…3CFD`.
- QR, imagem e ticket presentes na criação, sem exposição de seus conteúdos.
- Um GET posterior: HTTP 200, `processed/accredited`.
- Matrícula técnica ativada pela conciliação local. Fixtures integralmente removidas.
- Vencimento: 10/09/2026 19:57:57.149 UTC (16:57:57.149 em São Paulo).
- `live_mode` não retornado; não foi inferido por prefixo.
- Request IDs: criação `a4082aae-5098-4264-b9b3-f56ad75ac424`;
  consulta `715c88e3-f22c-4ba2-b781-729b9861117b`.
- Trava privada impede repetir automaticamente o diagnóstico. A Order técnica
  continua no ambiente do provedor; limpar fixtures locais não apaga seu histórico.

## Migração e integridade

`20260909_003_pix-integrado.sql` executada uma vez: seis instruções aditivas,
três ALTER TABLE e três CREATE TABLE. Compatibilidade MySQL 5.7, tipos,
nulabilidade, padrões, índices únicos e FKs conferidos. DDL tem commits implícitos.

Backup privado validado antes da execução:
`C:\Users\dsiqu\OneFit-backups\20260909_011322_pix_integrado\backup.json`

SHA-256: `a714a7959792548e9bb7cacc9e0e12a3ad38f3f83e682b52769dfb18cbd53c6f`.
Instruções concluídas registradas no arquivo privado `execution003.json`.

| Tabela | Antes | Após limpeza |
|---|---:|---:|
| usuarios | 38 | 38 |
| cadastro_planos | 4 | 4 |
| matricula | 32 | 32 |
| pagamento (legado) | 34 | 34 |
| cobrancas / pagamentos / pagamento_eventos | 0 / 0 / 0 | 0 / 0 / 0 |
| pedido / pedido_item | 40 / 67 | 40 / 67 |
| produtos | 22 | 22 |
| cashback | 170 | 170 |
| notificacoes | 10 | 10 |
| preferencias_usuario | 3 | 3 |
| enderecos_entrega | 2 | 2 |
| transportadoras / faixas_cep_frete | 3 / 2 | 3 / 2 |

Todas as linhas antigas e seus valores foram comparados com o backup; iguais.
A tabela legada também permaneceu estruturalmente igual. As três novas tabelas
de apoio estão vazias. Sequências AUTO_INCREMENT podem ter avançado pelos testes.

## Testes

266 verificações PHP de pagamentos: 47 Checkout Pro, 26 Orders, 46 identidade e
datas, 23 pagador, 30 segurança/vencimento, 55 persistência e 39 integração.
Passaram também as suítes PHP/JavaScript de perfil e a suíte JavaScript da tela
Pix. Lint PHP passou. HTTP autenticado: matrícula, carrinho, dashboard e Pix
retornaram 200 sem erro PHP; CSRF inválido retornou 403. Rotas sem autenticação
exigiram login. Testes financeiros usaram gateway falso, exceto a única execução
real separada descrita acima. Nenhuma segunda Order real foi criada.

Cobertos: lease com duas conexões, polling sobreposto, tentativa duplicada,
isolamento, inatividade, valores divergentes, datas, timeout, mesma idempotência,
reserva e liberação, estoque único, cashback, notificações, falha secundária,
HMAC adulterado/repetido/expirado, evento desconhecido e reprocessamento.
A avaliação da interface foi por HTTP e testes DOM; não houve navegação visual
autenticada em navegador nem entrega externa de webhook.

## Arquivos desta etapa

Criados:
- `database/migrations/20260909_003_pix-integrado.sql`
- `services/pagamentos/PixSeguranca.php`
- `services/pagamentos/PixEfeitos.php`
- `services/pagamentos/MarketplacePixService.php`
- `services/pagamentos/PixCheckout.php`
- `services/pagamentos/PixWebhook.php`
- `pages/pagamentos/api.php`, `pix.php`, `planos.php`, `webhook.php`
- `assets/css/pix.css`, `assets/js/pix.js`
- `scripts/pix-conciliar.php`
- `tests/pix-integrado.php`, `pix-seguranca.php`, `pix-ui.js`,
  `pix-auditar.php`, `pix-real-controlado.php`
- `services/pagamentos/PIX-INTEGRADO.md` e este relatório

Modificados:
- `services/pagamentos/CobrancaPixRepositorio.php`
- `services/pagamentos/MatriculaPixService.php`
- `services/pagamentos/PixConciliacaoService.php`
- `services/pagamentos/OrdersPixGateway.php`
- `services/pagamentos/PERSISTENCIA-PIX.md`, `ORDERS-PIX.md`
- `pages/matricula/matricula.php`, `pages/carrinho/carrinho.php`
- `pages/dashboard/funcionalidades/vendas.php`, `meus-pedidos.php`
- `tests/orders-pix-identidade.php`
- `README.md`

Alterações anteriores em Composer, configuração, `.gitignore`, `.env.example`,
migrações 001/002 e outros arquivos foram preservadas.

## Situação operacional

- [x] Criação autenticada, tela Pix, polling e conciliação em testing.
- [x] Matrícula e pedido aguardam confirmação confiável.
- [x] Reserva de estoque, cashback e sino idempotentes.
- [x] Webhook com assinatura e fila implementado/testado localmente.
- [x] Teste oficial único e limpeza verificados.
- [ ] Configurar URL HTTPS pública do webhook Orders e segredo correspondente:
  segredo atualmente ausente; endpoint falha fechado com 503.
- [ ] Agendar o worker CLI para processar a fila sem depender de navegador.
- [ ] Produção: liberação explícita e revisão comercial de recuperação,
  cancelamentos/estornos, total zero por cashback, retenção do pagador e monitoração.

A lista de fixtures está vazia após a limpeza; o teste oficial não fica disponível
para contas reais. Reservas vencidas permanecem protegidas enquanto a API não
confirmar estado final. Pedidos com valor Pix zero são bloqueados neste fluxo.
Instruções e fontes oficiais: [PIX-INTEGRADO.md](PIX-INTEGRADO.md).
