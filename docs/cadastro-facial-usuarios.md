# Cadastro facial de usuários

A seção **Cadastro facial** fica disponível no painel de administradores e profissionais. O operador pesquisa e escolhe explicitamente uma conta ativa de aluno, profissional ou administrador, confere o tipo e o ID e captura a imagem da pessoa diante da webcam. O ID selecionado é enviado ao endpoint e validado novamente no servidor; o ID da conta operadora não é usado automaticamente como titular.

## Acesso e seleção

- O endpoint autoriza operadores com conta `admin` ativa ou conta `profissional` ativa, segundo as regras de autenticação existentes. O esquema atual não define um papel `recepcionista` separado.
- A busca lista contas ativas de `usuarios` com `tipo_usuario` igual a `aluno`, `profissional` ou `admin`. Nome, CPF e ID são pesquisáveis; o CPF não é enviado à interface.
- Contas de outros tipos, como vendedor, não são elegíveis para cadastro facial por este fluxo.
- A câmera exige HTTPS ou `localhost` e permissão concedida no navegador.
- São aceitos JPEG e PNG até 5 MB e 16 megapixels. A imagem é validada pelo conteúdo e decodificada antes de ser armazenada.

## Histórico e armazenamento

As imagens são gravadas em `usuarios_fotos_faciais.imagem_blob` como BLOB e vinculadas a `usuarios.id_usuario`. Cada confirmação desativa, em transação, as fotos ativas anteriores do mesmo usuário e insere a nova; fotos antigas permanecem no banco como histórico. Nenhum arquivo público ou URL de imagem é criado.

A tabela existente tem `id_foto_facial` auto incremental, `id_usuario` obrigatório com chave estrangeira para `usuarios`, `data_cadastro` com valor padrão, `ativo` com valor padrão e `imagem_blob` `MEDIUMBLOB`. Não é necessária migração.

O servidor precisa ter a extensão PHP GD habilitada para decodificar a imagem antes do armazenamento. Se o PHP usado pelo Apache não carregar GD, habilite `extension=gd` em `C:\xampp\php\php.ini` e reinicie o Apache. Sem ela, o endpoint recusa o upload com uma mensagem de configuração, em vez de armazenar uma imagem não validada.
