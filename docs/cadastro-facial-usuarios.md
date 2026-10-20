# Cadastro facial de usuários

A seção **Cadastro facial** fica no painel. O operador pesquisa e escolhe explicitamente uma conta, confere o nome e o ID e captura a imagem da pessoa diante da webcam. O ID escolhido é enviado ao endpoint e validado de novo no servidor; o ID do operador nunca é usado automaticamente como titular.

## Quem opera e quem pode ser fotografado

As regras ficam em `config/papeis.php` (`onefitAvaliarCadastroFacial`) e são conferidas no banco em toda requisição, com o operador e a pessoa lidos na mesma transação. O que a tela mostra ou o formulário envia não muda a decisão.

| Operador | Pode fotografar |
|---|---|
| `admin` ativo | alunos, professores (inclusive o valor legado `profissional`), vendedores, recepcionistas e faxineiras ativos |
| `recepcionista` ativa | a si mesma, outras recepcionistas ativas e os mesmos cargos não administrativos |
| professor, vendedor, aluno, faxineira, conta inativa | ninguém (403) |

- **Contas admin** (inclusive a do próprio operador) só podem ser fotografadas por administradores da lista restrita (decisões D2 e P6). Como a lista ainda não foi definida (P1), ninguém fotografa contas admin: elas não aparecem na busca e o POST direto é recusado. As fotos de admin já existentes continuam no banco.
- Pessoa inexistente, inativa, de cargo desconhecido ou admin recebe a mesma resposta (404), sem revelar o motivo.
- A recepcionista já tem a permissão na política, mas **ainda não entra no sistema**, porque o painel da recepção não existe. As regras dela estão implementadas e testadas no serviço; pela tela, hoje só o admin opera.
- A foto não dá login nem muda a regra da catraca: a faxineira fotografada continua sem painel.
- A busca lista só quem o operador pode fotografar. Nome, CPF e ID são pesquisáveis; o CPF não é enviado à interface.
- A câmera exige HTTPS ou `localhost` e permissão no navegador.
- São aceitos JPEG e PNG de até 5 MB e 16 megapixels. A imagem é validada pelo conteúdo e decodificada antes de ser gravada.

## Até três fotos ativas e histórico

- Cada pessoa tem até **3 fotos ativas** em `usuarios_fotos_faciais`.
- Com 3 ativas, a nova foto é recusada com HTTP 409 (`requer_confirmacao`). A tela pede confirmação e reenvia com `substituir_mais_antiga=1`; só o valor exato `1` vale.
- Confirmada, a foto ativa mais antiga (por `data_cadastro`, depois `id_foto_facial`) passa a `ativo = 0`. Nada é apagado: a imagem continua no banco como histórico. Quem já tinha mais de 3 ativas volta a exatamente 3.
- O operador e a pessoa ficam travados (`FOR UPDATE`) durante a gravação, então duas requisições simultâneas não passam do limite.
- Cada gravação gera um registro em `auditoria_eventos` (categoria `foto_facial`, ação `cadastrar` ou `substituir`) só com IDs e cargo, sem imagem nem dado biométrico.

A tabela tem `id_foto_facial` auto incremental, `id_usuario` com chave estrangeira para `usuarios`, `data_cadastro`, `ativo` e `imagem_blob` `MEDIUMBLOB`. Não é preciso migração. Nenhum arquivo público ou URL de imagem é criado.

O servidor precisa da extensão PHP GD para decodificar a imagem. Se o PHP do Apache não carregar GD, habilite `extension=gd` em `C:\xampp\php\php.ini` e reinicie o Apache. Sem ela, o endpoint recusa o upload em vez de gravar uma imagem não validada.

## Catraca (OneFit.Turnstile)

O reconhecimento facial só identifica a pessoa; a entrada é decidida por `AutorizacaoCatraca.cs`, com as regras de `config/papeis.php`:

- **Aluno:** conta ativa e matrícula `ativa` com a data de hoje entre `data_inicio` e `data_fim`.
- **Professor** (e `profissional`): conta ativa e `cadastro_profissional.status = 'ativo'`.
- **Admin, recepcionista, vendedor e faxineira:** conta ativa, sem matrícula.
- **Pessoa inexistente, conta inativa ou bloqueada, cargo desconhecido ou falha no banco:** negado.

A catraca está em **modo de validação**: só exibe a decisão (`[Validação] Acesso autorizado/negado`), sem acionar nenhuma liberação física. Ela só lê `usuarios`, `matricula` e `cadastro_profissional` e **não grava fotos**: o antigo botão "Cadastrar foto no banco" agora orienta a usar esta tela, que aplica as permissões e o limite de fotos.

## Testes (somente banco descartável)

```
php tests/papeis.php                                      # matriz operador x pessoa
php tests/fotos-faciais.php                               # serviço: cargos, limite, histórico, trava, auditoria
powershell -File tests/papeis-http.ps1 -Script fotos-faciais-http.php   # HTTP: adulterações, faxineira, sessões
powershell -File tests/catraca-autorizacao.ps1            # compila a catraca e testa a autorização
```

Todos exigem antes `powershell -File tests/funcionarios-preparar.ps1`.
