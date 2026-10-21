# Professores (disponibilidade para agendamentos)

A antiga tela **Profissionais** do painel administrativo passou a se chamar **Professores** e deixou de cadastrar pessoas. O cadastro de professores acontece só na tela **Funcionários** (cargo Professor). A tela Professores serve para definir quem pode receber agendamentos dos alunos.

A chave da seção e a rota continuam `profissionais` (`?section=profissionais`, `funcionalidades/profissionais.php`). Nenhuma tabela ou coluna foi renomeada.

## Conta, cadastro legado e disponibilidade

| Conceito | Onde fica | Quem altera | Efeito |
|---|---|---|---|
| Conta | `usuarios.status` | Funcionários | Painel e catraca de qualquer cargo |
| Cadastro profissional legado | `cadastro_profissional.status` | Preservado; sem controle de edição na tela | Continua bloqueando catraca e agenda quando inativo |
| Disponibilidade | `cadastro_profissional.disponivel_agendamento` | Professores (interruptor) | Só novos agendamentos de alunos |

`cadastro_profissional.status` continua sendo lido pela catraca (`OneFit.Turnstile`, `AutorizacaoCatraca.cs`) e por `onefitSituacaoCatracaFuncionario()`. O formulário de dados profissionais não aceita alterar esse campo, inclusive por POST forjado. A conta é gerenciada em Funcionários e a pausa de agendamentos em Professores.

A disponibilidade não ativa conta, não dá permissão, não muda cargo, catraca nem agendamentos já marcados.

## Quem aparece

- **Tela Professores:** contas com cargo `professor` (ou o legado `profissional`) que têm cadastro profissional vinculado por `cadastro_profissional.id_usuario`. Contas de outros cargos nunca aparecem, mesmo com um cadastro antigo ligado a elas. A tela avisa quantos professores estão sem cadastro e quantos cadastros antigos estão sem conta. Esses casos se resolvem em Funcionários ("Vincular cadastro existente").
- **Agenda do aluno** (`bo_professores_condicao_agenda()`, em `includes/professores.php`): cargo professor, `usuarios.status = 'ativo'`, `cadastro_profissional.status = 'ativo'` e `disponivel_agendamento = 1`. A mesma condição filtra os horários simulados e os cadastrados pelo professor (`disponibilidade_profissional`).
- **Busca do cabeçalho:** professores com conta e cadastro ativos. É só a consulta do perfil; a disponibilidade vale para a agenda.

## Regra em todo agendamento novo

Só dois pontos do sistema criam agendamentos, e os dois passam pelo mesmo serviço (`includes/agenda-service.php`):

| Fluxo | Onde | Como cria |
|---|---|---|
| Aluno escolhe horário | `funcionalidades/agenda.php` | `bo_agenda_transacao()` + `bo_agenda_inserir()`, status `confirmado` |
| Professor agenda um aluno vinculado | `components/section-profissional.php` (`prof_action=agendar`) | `bo_agenda_transacao()` + `bo_agenda_inserir()`, status `agendado` |
| Recepção agenda para aluno ativo | `funcionalidades/agenda-recepcao.php` | Busca aluno autorizada, recalcula o slot dentro da transação e registra operador e alvo na auditoria |

- `bo_agenda_inserir()` é a única função que grava em `agendamento`. Antes de gravar, ela chama `bo_professor_agendavel()`: cargo professor, conta ativa, cadastro profissional ativo e disponível. Se faltar qualquer um, lança `AgendamentoRecusado` e nada é gravado. Fora de `bo_agenda_transacao()` ela se recusa a rodar.
- Aluno: só vale um horário que `bo_agenda_slots()` oferece, e essa lista já exclui professores indisponíveis. O professor usa sempre o próprio cadastro, descoberto pela conta da sessão, então um `id_profissional` enviado no formulário é ignorado.
- O painel do professor avisa quando novos agendamentos estão bloqueados (indisponível, cadastro inativo) e desabilita o botão "Agendar". O aviso é só para a tela; quem decide é o servidor.
- Cancelamentos (aluno e professor) e a criação de horários livres pelo professor (`disponibilidade_profissional`) seguem as regras de antes. Horários livres de um professor indisponível não aparecem para os alunos.
- Os três fluxos usam a mesma trava e validação central. Uma carga de demonstração feita uma vez fora do repositório, em 2026-10-20, não é um fluxo do sistema.

## Concorrência

- `bo_agenda_transacao()` pega a trava nomeada `onefit_agenda_reserva` (espera até 10 s), abre a transação, roda a operação, confirma ou desfaz tudo e solta a trava. Isso serializa todas as reservas e a geração do ID (`MAX + 1`; a tabela não tem AUTO_INCREMENT), nos três fluxos.
- Dentro da transação, `bo_professor_agendavel()` lê conta e cadastro com trava compartilhada (`LOCK IN SHARE MODE`), nessa ordem. A troca de disponibilidade, a edição do cadastro e a mudança de conta travam as mesmas linhas para escrita, na mesma ordem. Resultado:
  - uma troca feita durante uma reserva espera a reserva terminar;
  - uma reserva feita durante uma troca espera a troca terminar e lê o valor já confirmado, nunca o antigo;
  - a ordem fixa de travas evita impasse entre as telas.
- `tests/professores.php` prova isso com duas conexões e espera de trava curta: a troca não passa por cima de uma reserva aberta, a reserva não passa por cima de uma troca aberta, e com a trava nomeada ocupada nada é gravado.

## Telas e operações administrativas

- A tela Professores (`funcionalidades/profissionais.php`) só aceita admin e diretor ativos (`_shared.php`), com CSRF. Cada operação reconfere o operador no banco e trava conta e cadastro. A disponibilidade é enviada como valor explícito (`0` ou `1`), nunca como alternância. As ações `create` e `delete` são recusadas, e a página de confirmação de exclusão não aceita mais `profissionais`.
- Dados profissionais editáveis: especialidade, modalidades (só as cadastradas), registro, descrição e foto (só por upload novo). Nome, e-mail e celular são da conta e vêm de Funcionários.
- Auditoria em `auditoria_eventos`, categoria `professor`: `disponibilidade` (de/para) e `editar_cadastro` (campos alterados; descrição e foto só como "alterado").

## Sincronização com Funcionários

- **Cadastrar professor:** cria ou vincula o cadastro profissional (como antes). Ele aparece em Professores já disponível, que é o padrão da coluna e a regra anterior.
- **Editar professor:** nome, e-mail e celular salvos em Funcionários também atualizam o cadastro vinculado. Se o e-mail já pertencer a outro cadastro profissional, a edição inteira é recusada e nada muda.
- **Mudar de cargo e voltar:** o cadastro é preservado e reaproveitado (sem duplicar), com a disponibilidade de antes.
- **Inativar a conta:** o professor sai da agenda, seja qual for a disponibilidade. Ao reativar, vale a disponibilidade que estava configurada.
- **Duplicados:** a migração cria a chave única `uk_cadastro_profissional_usuario (id_usuario)`. Cadastros antigos sem conta (`NULL`) continuam permitidos.

## Agendamentos existentes

Ficar indisponível não cancela nem altera agendamentos ou horários já reservados. Aluno e professor continuam vendo os agendamentos e podem cancelá-los, pela regra atual. A indisponibilidade bloqueia só os novos, nos dois fluxos.

## Migração `20261021_professores_v1`

`database/migrate-professores.php`, só pela linha de comando. O destino tem de coincidir com o `.env` da cópia, e quem grava ainda repete o nome do banco em `--confirmar`.

```
php database/migrate-professores.php --inspecionar --host=HOST --database=BANCO
php database/migrate-professores.php --aplicar  --host=HOST --database=BANCO --confirmar=BANCO --backup-dir=DIRETORIO_PRIVADO
php database/migrate-professores.php --reverter --host=HOST --database=BANCO --confirmar=BANCO --backup-dir=DIRETORIO_PRIVADO
php database/migrate-professores.php --verify=ARQUIVO_DE_BACKUP
```

- **Recusa sem gravar nada:** destino diferente do `.env`, `--confirmar` ausente ou diferente, conexão que abre outro banco, pasta de backup dentro do repositório ou do htdocs, falta de `20261020_funcionarios_v1`, tabela fora do esperado (InnoDB com `id_profissional`, `id_usuario` e `status`), usuário sem `ALTER`/`INDEX`, conta com dois cadastros profissionais, estado parcial (versão, coluna ou índice só em parte).
- **Aplicação:** backup verificado de `cadastro_profissional`, depois `database/migrations/professores-20261021.sql`. Esse arquivo cria `disponivel_agendamento TINYINT(1) NOT NULL DEFAULT 1` depois de `status` e a chave única `uk_cadastro_profissional_usuario (id_usuario)`; vários `NULL` continuam permitidos.
- **Conferência depois do DDL:**
  - definição da coluna e do índice;
  - colunas e linhas anteriores de `cadastro_profissional` idênticas;
  - todo cadastro com `disponivel_agendamento = 1`;
  - os mesmos professores recebendo agendamento antes e depois;
  - retrato de `usuarios`, `agendamento`, `disponibilidade_profissional` e `profissional_aluno`. A migração não escreve nelas; diferença ali indica atividade concorrente e gera aviso.
  - Só então a versão é registrada. Rodar de novo não altera nada.
- **Padrão 1 = comportamento atual:** todo professor que hoje recebe agendamento continua recebendo. Quem não recebe (conta ou cadastro inativo) continua sem receber.
- **Reversão:** `--reverter` remove índice, coluna e versão, com backup e as mesmas conferências. Só roda se nenhum professor estiver indisponível, porque reverter devolveria essas pessoas à agenda. Foi testada em banco descartável e numa cópia restaurada do banco oficial.
- **Enquanto não for aplicada:** a agenda segue a regra anterior (todo professor com conta e cadastro ativos), a tela mostra o aviso e o interruptor não aparece. Os dados profissionais continuam editáveis.

## Procedimento para o banco oficial

O backup completo e a restauração isolada usam `database/backup-verificar.php`. Ele nunca escreve no banco de origem: o dump é `mysqldump --single-transaction` (só leitura, sem `LOCK TABLES` nem `FLUSH`) e o retrato de conferência é lido numa transação `READ ONLY`. A restauração usa um MariaDB local, num banco `onefit_restauracao_teste_*` apagado no fim. Nenhuma senha é impressa: as credenciais vão num arquivo de opções temporário, apagado logo depois.

1. **Inspecionar** (só leitura): `php database/migrate-professores.php --inspecionar --host=HOST --database=BANCO`.
2. **Backup completo**, numa pasta privada fora do htdocs (ex.: `C:\Users\dsiqu\OneFit-private-backups\professores-AAAAMMDD\`):
   `php database/backup-verificar.php --criar --host=HOST --database=BANCO --backup-dir=PASTA`.
   Gera `onefit-backup-BANCO-…Z.sql` e `….sql.manifest.json`. O manifesto guarda SHA-256 e tamanho do dump e, por tabela, linhas e hash das linhas (TIMESTAMP em UTC), lidos antes e depois do dump. Se algo mudou durante o dump, a tentativa é descartada e refeita (até 3 vezes). A ferramenta também confere `CREATE TABLE` de cada tabela, dados de cada tabela não vazia, as visões e a linha final `Dump completed`.
3. **Verificar o arquivo:** `php database/backup-verificar.php --verificar=ARQUIVO.sql` (hash, tamanho e estrutura).
4. **Restaurar isolado e ensaiar:** `php database/backup-verificar.php --restaurar-isolado=ARQUIVO.sql --ensaiar-migracao`.
   - Importa num banco local novo e compara cada tabela com o manifesto. Também consulta as visões, depois de tirar o `DEFINER` e o prefixo do banco de origem.
   - Na cópia restaurada roda a migração: aplicar, reaplicar, conferir, reverter. No fim, o banco tem de voltar idêntico ao backup.
   - Um arquivo adulterado é reprovado (testado).
5. **Autorização explícita do usuário.** Sem ela, nada é aplicado.
6. **Aplicar:** `php database/migrate-professores.php --aplicar --host=HOST --database=BANCO --confirmar=BANCO --backup-dir=PASTA`, de preferência num horário sem uso. Fazer o backup do passo 2 logo antes.
7. **Conferir depois:**
   - a saída do executor (`mesmos_professores_na_agenda`, `tabelas_relacionadas_inalteradas`);
   - um novo `--inspecionar`;
   - a tela Professores com o interruptor;
   - `--verify` do backup de tabela que o executor gravou.
   - Para desfazer: `--reverter` (com todos disponíveis) ou restaurar o backup do passo 2.

Limite: a restauração de validação roda no MariaDB 10.4 local, não no MySQL 5.7 do servidor. Prova que o dump está íntegro e reconstrói os mesmos dados, mas não testa a importação no próprio servidor de produção.

### Aplicação no banco oficial (2026-10-21, com autorização explícita)

- **Antes** (inspeção somente leitura, MySQL 5.7.32):
  - coluna e chave única ausentes;
  - 3 cadastros profissionais, todos vinculados a professores, sem duplicados;
  - nada depende da tabela;
  - `ALTER`/`INDEX` disponíveis;
  - 2 professores recebiam agendamento (#15 e #17).
- **Backup completo** logo antes de aplicar: `C:\Users\dsiqu\OneFit-private-backups\professores-20261021\onefit-backup-onefit-20261021-200056Z.sql`, SHA-256 `55c3504ce9574e0292b30f5cbf2811e9e91e2040842dc72deb8c5030c847954d`.
  - Contém 43 tabelas, 293 linhas e 2 visões; ficou estável durante o dump.
  - Restaurado isolado sem diferenças, e o ensaio da migração (ida e volta) passou.
- **Aplicada** com `--confirmar`. Backup de tabela do executor: `onefit-before-professores-aplicar-20261021-200154-6f8da04c.json`, SHA-256 `7f9260594e9f5c6b3ec5896047bd141a3d2162a8ad235a28c36c74918c927b1d`.
  - Mesmos professores na agenda.
  - `usuarios`, `agendamento`, `disponibilidade_profissional` e `profissional_aluno` inalteradas.
  - Reexecução: "já aplicada; nada foi alterado".
- **Depois** (conferência somente leitura):
  - 42 de 43 tabelas idênticas ao backup; só `onefit_migrations` ganhou a linha da versão;
  - colunas antigas de `cadastro_profissional` intactas e os 3 cadastros com `disponivel_agendamento = 1`;
  - a aplicação lista os 3 professores e oferece horários para #15 e #17.
- **Para desfazer:** `--reverter` com todos disponíveis, ou restaurar o backup completo acima.

## Testes (somente banco local descartável)

```
powershell -File tests/funcionarios-preparar.ps1     # inclui professores.php --antes, a migração local e professores-migracao.php
php tests/professores.php                             # regras, criação central, concorrência, sincronização, permissões, auditoria
powershell -File tests/papeis-http.ps1 -Script professores-http.php   # tela, agenda do aluno, painel do professor, POST forjado, cargos
php tests/professores-migracao.php                    # recusas (inclusive --confirmar), aplicação, chave única e reversão (esquema remoto)
powershell -File tests/catraca-autorizacao.ps1        # catraca real (C#): indisponível continua liberado
```

Testes que usam o `.env` da aplicação, ou seja, o banco oficial: `tests/treino.php`, `tests/checkout.php` e `tests/pix-sem-frete.php` (tabelas TEMPORARY), `tests/compras.php`, `tests/compras-render.php` e `tests/credenciais-verificar.php` (leitura), e `tests/student-profile.php --schema`. Os de tabela temporária não devem rodar com o `.env` apontando para o oficial.
