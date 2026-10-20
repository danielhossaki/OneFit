# Funcionários (Etapa 2)

Tela **Funcionários** do painel do administrador para cadastrar e gerenciar a equipe. Todos os funcionários ficam em `usuarios`, identificados por `usuarios.tipo_usuario`; não existe tabela paralela de funcionários.

## Cargos

| Cargo (`tipo_usuario`) | Admin comum atribui? | Entra no painel | Link de primeira senha | Catraca (regra prevista) |
|---|---|---|---|---|
| `professor` | sim | sim (painel do profissional) | sim | conta ativa **e** `cadastro_profissional.status = 'ativo'` |
| `recepcionista` | sim | ainda não (painel da recepção não existe) | não, até haver painel | conta ativa |
| `vendedor` | sim | sim | sim | conta ativa |
| `faxineira` | sim | **nunca** | nunca | conta ativa |
| `admin` | **não** | sim | — | conta ativa |
| `profissional` (legado) | não (só contas antigas) | sim, como professor | sim | igual a professor |

A política fica em `config/papeis.php` (`onefitCargosAtribuiveisPorAdmin`, `onefitFuncionarioGerenciavelPorAdmin`, `onefitSituacaoCatracaFuncionario`). Contas antigas com `profissional` continuam funcionando e mantêm o valor gravado; cadastros novos usam `professor`.

**Administradores:** criar, promover ou alterar contas `admin` é exclusivo dos administradores da lista restrita (decisão D2). Como a lista ainda não foi definida, `onefitAdminRestrito()` devolve sempre `false` e nenhuma tela altera contas admin; elas aparecem só para consulta.

## Tela e operações

`pages/dashboard/components/section-funcionarios.php` (lista, busca por nome ou e-mail, filtros de cargo e situação) e `pages/dashboard/funcionalidades/funcionarios.php` (handler). As regras estão em `pages/dashboard/includes/funcionarios.php`:

- **Cadastrar:** todos os campos obrigatórios de `usuarios`, com CPF válido (dígitos verificadores), e-mail válido e exclusivo, celular com DDD, nacionalidade ISO e UF. E-mail ou CPF já existentes são recusados; nenhuma conta existente é reaproveitada.
- **Senha:** a conta nasce com o hash de uma senha aleatória que ninguém conhece (`usuarios.senha` continua obrigatória). Quem entra no painel recebe por e-mail um link de uso único (`recuperacao_senha_tokens`, 30 minutos) para definir a própria senha e um link de confirmação de e-mail (24 horas). Faxineira e recepcionista (sem painel) não recebem link.
- **Editar:** dados pessoais e cargo entre os quatro atribuíveis. A senha nunca é alterada. Se o e-mail mudar, a confirmação é zerada e os links enviados ao e-mail antigo são cancelados.
- **Ativar / inativar:** envia o status desejado (nunca alterna). Inativar cancela links pendentes; a revalidação de sessão tira a pessoa do painel na próxima requisição.
- **Enviar link de acesso:** gera novos links (os anteriores deixam de valer) para conta ativa com painel.
- **Matrícula:** funcionários nunca recebem matrícula.

## Professores e `cadastro_profissional`

- O vínculo é sempre explícito, por `cadastro_profissional.id_usuario`: o admin escolhe **criar um cadastro novo** ou **vincular um cadastro existente ainda sem conta**.
- Nunca há vínculo por coincidência de e-mail. Se já existir um cadastro com o e-mail da conta, a tela pede o vínculo explícito.
- Vincular só grava `id_usuario`; os demais dados do cadastro são preservados. Cadastros de outros professores não podem ser tomados.
- O antigo vínculo automático por e-mail ao abrir o painel do professor (`section-profissional.php`) foi removido: abrir uma página não altera dados.

## Tela de alunos

A tela antiga (`usuarios.php`, seção "usuarios") passou a se chamar **Alunos** e lista, edita, inativa e exclui somente contas `aluno`. Cada `UPDATE`/`DELETE` repete `tipo_usuario = 'aluno'`, então nem uma requisição direta altera funcionários ou administradores por ali. Planos, matrículas e pagamentos dos alunos seguem como antes.

## Segurança

- Permissão conferida no servidor em toda operação: sessão revalidada (`config/conn.php`) e, dentro da transação, operador admin ativo e conta alvo travados (`FOR UPDATE`).
- CSRF em todos os POST; consultas preparadas; cargo e status validados contra a política, nunca o que o formulário diz sobre a conta.
- Nega por padrão: migração ausente, erro de banco ou dado inválido não gravam nada (transação desfeita).
- Auditoria em `auditoria_eventos` (criar, editar, status, vínculo, reenvio), sem senha, hash ou token; CPF mascarado.
- `permissoes.php` e `funcoes.php` continuam desativados.

## Migração `20261020_funcionarios_v1`

`database/migrate-funcionarios.php` (somente linha de comando; o destino tem de coincidir com o `.env` da cópia em que roda):

```
php database/migrate-funcionarios.php --inspecionar --host=HOST --database=BANCO
php database/migrate-funcionarios.php --aplicar --host=HOST --database=BANCO --backup-dir=DIRETORIO_PRIVADO
php database/migrate-funcionarios.php --verify=ARQUIVO_DE_BACKUP
```

- `--inspecionar` é somente leitura e imprime só contagens (cargos, CPFs com máscara, e-mails duplicados, cadastros profissionais sem conta ou vinculados a quem não é professor).
- `--aplicar` grava antes um backup verificado da tabela `usuarios` (fora do repositório e da raiz pública; contém dados pessoais e hashes de senha, guarde com acesso restrito), acrescenta ao **final** do ENUM os cargos atribuíveis que faltarem (`vendedor`, `professor`, `recepcionista`, `faxineira`, nessa ordem) preservando valores, ordem, nulidade, padrão e collation (modo estrito), confere que nenhuma linha de `usuarios` mudou, cria `auditoria_eventos` e registra a versão em `onefit_migrations`. Rodar de novo não altera nada.
- O banco remoto (MySQL 5.7, `sql_mode` vazio) tinha só `aluno, profissional, admin`: nenhuma migração anterior acrescentou `vendedor` (só a fixture de testes o fazia). Sem modo estrito, gravar um cargo fora do ENUM guardaria `''` em silêncio; por isso a tela exige todos os cargos atribuíveis no ENUM e confere o cargo gravado após cada cadastro. `tests/funcionarios-migracao-remota.php` repete a migração num banco descartável com a mesma estrutura do remoto.
- Enquanto a migração não for aplicada, a tela mostra o aviso e bloqueia cadastro e edição.

## Testes (somente banco local descartável)

```
powershell -File tests/funcionarios-preparar.ps1     # banco novo + cópia sintética + migração local verificada
php tests/funcionarios.php                            # regras, vínculos, auditoria, falhas
powershell -File tests/papeis-http.ps1 -Script funcionarios-http.php
powershell -File tests/papeis-http.ps1                # regras de cargo e sessão (Etapa 1)
php tests/papeis.php
```

Nenhum teste usa o `.env` da aplicação nem envia e-mail.

## Pendências

- Lista de administradores restritos (P1): até lá, contas admin não são gerenciadas por nenhuma tela.
- Painel da recepção: recepcionistas são cadastradas, mas só recebem link de acesso quando o painel existir.
- Prazo do link de primeira senha (P9): hoje 30 minutos, o mesmo da recuperação de senha.
- Aplicar a migração no banco remoto exige autorização explícita, backup e a troca prévia das credenciais expostas.
