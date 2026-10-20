# Matrículas e pagamentos

Ciclo de vida da matrícula no `main` e regras usadas pela catraca. As rotinas ficam em `pages/dashboard/includes/matriculas.php`.

## Fluxos

| Fluxo | Arquivo | Matrícula | Cobrança (`pagamento`) | Datas |
|---|---|---|---|---|
| Inscrição pública | `pages/matricula/matricula.php` | cria `pendente` | cria `pendente` (`MAT-…`, sem data de pagamento) | `data_inicio = data_fim = hoje` até a confirmação |
| Confirmação de pagamento | `bo_matricula_confirmar_pagamento` | `pendente` → `ativa`, uma única vez | `pendente`/`atrasado` → `aprovado` | `data_inicio = hoje`, `data_fim = hoje + duracao_dias` do plano |
| "Pagar plano" do aluno | `funcionalidades/pagar-plano.php` | não muda | não muda | não muda |
| Admin atribui plano a aluno sem matrícula | `funcionalidades/usuarios.php` | cria `ativa` | nenhuma | hoje → hoje + `duracao_dias` |
| Admin troca plano de quem já tem matrícula | `funcionalidades/usuarios.php` | só `id_plano` e `valor_contratado` | nenhuma | não muda |
| Admin edita datas | `funcionalidades/usuarios.php` | status não muda | nenhuma | datas digitadas pelo admin |
| Admin lança pagamento | `funcionalidades/pagamentos.php` | não muda | cria `aprovado` | não muda |
| Admin edita ou exclui pagamento | `funcionalidades/pagamentos.php` | não muda | edita valores ou **apaga a linha** | não muda |
| Vencimento, cancelamento, inadimplência | não existe rotina | `vencida`/`cancelada` nunca são gravadas pelo sistema | `recusado`/`cancelado`/`atrasado` também não | — |
| Catraca | `OneFit.Turnstile/AutorizacaoCatraca.cs` ([catraca.md](catraca.md)) | só `ativa` com datas válidas e hoje entre `data_inicio` e `data_fim` libera | — | — |

## Regras da confirmação

- É a única rotina que aprova a cobrança da inscrição e ativa a matrícula. Só uma fonte confiável pode chamá-la (gateway/conciliação ou, quando for decidido, confirmação manual). **Nenhuma página a chama hoje**, porque o `main` não tem gateway integrado.
- Idempotente: a ativação acontece só na passagem da cobrança de `pendente`/`atrasado` para `aprovado`. Uma confirmação repetida devolve `ja_confirmado` sem alterar nada.
- Recusa sem gravar nada: cobrança `recusado`/`cancelado`, valor diferente do cobrado, conta inativa, plano sem duração e matrícula que não está `pendente`. Esse último caso cobre renovação e prorrogação, que ainda dependem de decisão.
- Operador e matrícula ficam travados durante a confirmação: duas confirmações simultâneas ativam uma só vez.
- Registra em `auditoria_eventos` (categoria `matricula`) a origem e as datas anteriores e novas. Nada é apagado.
- As datas usam o `CURDATE()` do banco, o mesmo relógio da catraca.

## Por que as matrículas de produção estão pendentes

- A inscrição cria matrícula e cobrança `pendente` por definição.
- Não existe gateway nem confirmação manual que ative.
- O único caminho era o botão "Pagar" do aluno, que **aprovava a cobrança e ativava a matrícula sem pagamento real** (Pix demonstrativo, cartão só visual). Ele não definia `data_fim`, deixava repetir pagamentos aprovados a cada clique e não foi usado em produção: há 0 cobranças aprovadas.
- O `INSERT` antigo da inscrição omitia `data_fim` (NOT NULL). Num MySQL sem modo estrito, como o remoto, isso grava `0000-00-00`; com modo estrito, a inscrição falha.

## Decisões pendentes

- Fonte de confirmação: gateway (a branch `pagamento1` tem um fluxo PIX não mesclado) e/ou confirmação manual pelo admin, com quem pode confirmar e que comprovante exigir.
- Renovação e prorrogação: pagamento de matrícula `ativa` ou `vencida`, renovação antecipada (somar ao fim atual ou começar hoje) e extensão por pagamento manual.
- Contagem do período: `data_fim = início + duracao_dias` com comparação inclusiva dá `duracao_dias + 1` dias de acesso, a mesma regra de `usuarios.php` e da branch `pagamento1`. Também falta definir se a duração fica congelada na inscrição ou se vale a do plano na confirmação.
- Rotina para marcar `vencida`/`cancelada`. A catraca já nega pela data, então isso não afeta o acesso.
- Excluir pagamento: hoje o admin apaga a linha. Decidir se deve virar cancelamento, para preservar o histórico.
- As 5 matrículas `pendente` de produção (todas com `data_fim` zerada) não foram alteradas.

## Testes (somente banco descartável)

```
php tests/matriculas.php
powershell -File tests/papeis-http.ps1 -Script matriculas-http.php
powershell -File tests/catraca-autorizacao.ps1
```

Todos exigem antes `powershell -File tests/funcionarios-preparar.ps1`.
