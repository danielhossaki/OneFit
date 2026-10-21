# Funcionários (Etapa 2)

Tela **Funcionários** do painel do administrador para cadastrar e gerenciar a equipe. Todos os funcionários ficam em `usuarios`, identificados por `usuarios.tipo_usuario`; não existe tabela paralela de funcionários.

## Cargos

| Cargo (`tipo_usuario`) | Admin atribui? | Diretor atribui? | Entra no painel | Link de primeira senha | Catraca (regra prevista) |
|---|---|---|---|---|---|
| `professor` | sim | sim | sim (painel do profissional, ver abaixo) | sim | conta ativa **e** `cadastro_profissional.status = 'ativo'` |
| `recepcionista` | sim | sim | sim (painel da recepção, ver abaixo) | sim | conta ativa |
| `vendedor` | sim | sim | sim (painel do vendedor, ver abaixo) | sim | conta ativa |
| `faxineira` | sim | sim | **nunca** | nunca | conta ativa |
| `admin` | **não** | sim | sim (painel administrativo) | sim | conta ativa |
| `diretor` | **não** | sim | sim (painel administrativo) | sim | conta ativa |
| `profissional` (legado) | não (só contas antigas) | não | sim, como professor | sim | igual a professor |

A política fica em `config/papeis.php` (`onefitCargosAtribuiveis`, `onefitPodeGerenciarConta`, `onefitSituacaoCatracaFuncionario`). Contas antigas com `profissional` continuam funcionando e mantêm o valor gravado; cadastros novos usam `professor`.

## Seções do painel por cargo

O menu (`BO_PERFIS` em `assets/js/dashboard.js`) mostra só estas seções, e o servidor só envia o HTML delas (`dashboard.php` escolhe o componente pelo cargo lido do banco). "Só consulta" quer dizer: a tela sai sem botões, formulários nem modais de gravação, e a rota de gravação correspondente (`funcionalidades/*.php`) continua aceitando só admin e diretor.

| Cargo | Seções | Observações |
|---|---|---|
| Recepcionista | Alunos, Pagamentos, Modalidades e Cashback (só consulta); Cadastro facial; Marketplace (link para a loja); Configurações | No cadastro facial, a própria conta, outras recepcionistas ativas e cargos não administrativos (D5). |
| Professor | Meu perfil, Agenda, Treino, Configurações | Treino: lista os alunos vinculados (`profissional_aluno`) e acrescenta exercícios à ficha (`treino_exercicio`) só de aluno ativo com vínculo **ativo** com o próprio cadastro profissional, conferido no banco a cada envio. Editar, excluir e limpar a ficha continuam com o aluno. Sem Dashboard, Histórico, Meu cashback e Minhas compras: as rotas `meus-pedidos.php` e `buscar-compras.php` também o recusam. |
| Vendedor | Marketplace (vendas e logística, com link para a loja), Produtos, Categorias e Cashback (só consulta), Configurações | Produtos: só os próprios (`produtos.php` confere `id_vendedor` em editar, ativar/inativar e excluir; produto novo nasce do vendedor). Categorias: só as ativas. |
| Admin e Diretor | sem mudança | Telas e gravações de antes (Alunos, Pagamentos, Cashbacks, Categorias e Modalidades viraram componentes compartilhados, com a mesma marcação). |

O Cashback em consulta é a mesma tela do admin (saldo geral e lançamentos, com o ID do usuário). A tabela `cashback` não guarda produto nem pedido, então não há como limitar o vendedor ao cashback das próprias vendas.

## Hierarquia: Diretor e Administrador

O **Diretor** (`diretor`) é um cargo próprio, acima do **Administrador** (`admin`); substitui a antiga ideia de uma lista restrita de administradores (D2).

| | Diretor | Administrador |
|---|---|---|
| Painel e rotas administrativas (alunos, pagamentos, planos, produtos, vendas, profissionais, modalidades, cadastro facial, tema do site, notificações de pedidos) | sim | sim |
| Funcionários comuns (professor, recepcionista, vendedor, faxineira) | sim | sim |
| Criar, editar, desativar e reativar contas `admin` e `diretor`; alterar e-mail, cargo e status delas | **sim** | não (só consulta) |
| Enviar o link de definição de senha (redefinição) para `admin` e `diretor` | **sim** | não |
| Atribuir os cargos `admin` e `diretor` (promover) | **sim** | não |
| Cadastro facial de contas `admin` e `diretor` | **sim** | não |
| Alterar a própria conta na tela Funcionários | não | não |

- Tudo é conferido no servidor, com o cargo do operador lido do banco e travado na transação; a tela só esconde o que o servidor já recusaria. Um diretor rebaixado perde a autoridade na próxima requisição.
- `onefitPapelPermitido()`: o diretor cumpre exigências de `admin` (inclusão declarada na política, `inclui`), nunca o contrário. Nenhum outro cargo inclui o diretor.
- **Nunca sem Diretor ativo:** ninguém altera a própria conta na tela (o último diretor não se desativa nem se rebaixa) e, em toda mudança de conta diretor, a transação confere que resta pelo menos um diretor ativo (linhas travadas).
- **Recuperação** (também usada para escolher o primeiro diretor depois da migração): `php database/diretor-recuperar.php --host=H --database=D --id=N --confirmar=PROMOVER-N`, só pela linha de comando, só quando não há nenhum diretor ativo e só para uma conta `admin` ativa; registra `recuperacao_diretor` na auditoria. A senha não muda.
- Nenhuma conta vira diretor automaticamente: a migração só passa a aceitar o valor.

## Tela e operações

`pages/dashboard/components/section-funcionarios.php` (lista, busca por nome ou e-mail, filtros de cargo e situação) e `pages/dashboard/funcionalidades/funcionarios.php` (handler). As regras estão em `pages/dashboard/includes/funcionarios.php`:

- **Cadastrar:** todos os campos obrigatórios de `usuarios`, com CPF válido (dígitos verificadores), e-mail válido e exclusivo, celular com DDD, nacionalidade ISO e UF. E-mail ou CPF já existentes são recusados; nenhuma conta existente é reaproveitada.
- **Senha:** a conta nasce com o hash de uma senha aleatória que ninguém conhece (`usuarios.senha` continua obrigatória). Quem entra no painel recebe por e-mail um link de uso único (`recuperacao_senha_tokens`, 30 minutos) para definir a própria senha e um link de confirmação de e-mail (24 horas). A faxineira (sem painel) não recebe link.
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

- Permissão conferida no servidor em toda operação: sessão revalidada (`config/conn.php`) e, dentro da transação, operador (admin ou diretor ativo) e conta alvo travados (`FOR UPDATE`).
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

## Migração `20261020_diretor_v1`

```
php database/migrate-diretor.php --inspecionar --host=H --database=D
php database/migrate-diretor.php --aplicar --host=H --database=D --backup-dir=DIRETORIO_PRIVADO
php database/migrate-diretor.php --reverter --host=H --database=D --backup-dir=DIRETORIO_PRIVADO
```

- Exige `20261020_funcionarios_v1`. Faz backup verificado de `usuarios`, acrescenta `diretor` ao **final** do ENUM (modo estrito, demais atributos preservados), confere que nenhuma conta mudou e registra a versão. Rodar de novo não altera nada.
- `--reverter` remove `diretor` do ENUM e a versão, só se nenhuma conta tiver esse cargo.
- Sem a migração, a tela recusa atribuir `diretor` e não existe diretor: as contas admin continuam só para consulta.

## Testes (somente banco local descartável)

```
powershell -File tests/funcionarios-preparar.ps1     # banco novo + cópia sintética + migração local verificada
php tests/funcionarios.php                            # regras, vínculos, auditoria, falhas
powershell -File tests/papeis-http.ps1 -Script funcionarios-http.php
powershell -File tests/papeis-http.ps1                # regras de cargo e sessão (Etapa 1)
powershell -File tests/papeis-http.ps1 -Script permissoes-http.php   # cada rota do painel x cada cargo sem permissão
powershell -File tests/papeis-http.ps1 -Script recepcao-http.php      # painel da recepção: login, cadastro facial permitido e recusas
powershell -File tests/papeis-http.ps1 -Script secoes-cargos-http.php # menu e seções exatas por cargo, telas só de consulta, treino do professor, produtos do vendedor
php tests/papeis.php
php tests/diretor.php                                 # hierarquia diretor > admin (serviço)
powershell -File tests/papeis-http.ps1 -Script diretor-http.php      # hierarquia via HTTP, requisições forjadas do admin
php tests/diretor-migracao.php                        # migração, recuperação e reversão (esquema do remoto)
```

Nenhum teste usa o `.env` da aplicação nem envia e-mail.

## Pendências

- Escolher quem será o primeiro Diretor em cada banco (procedimento de recuperação acima) depois da migração.
- Prazo do link de primeira senha (P9): hoje 30 minutos, o mesmo da recuperação de senha.
- Aplicar a migração no banco remoto exige autorização explícita, backup e a troca prévia das credenciais expostas.
