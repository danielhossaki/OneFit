Arquivos completos da atualização do perfil do aluno
==================================================

As alterações já estão aplicadas no projeto. Para copiá-las para outra instalação, substitua ou crie cada arquivo no caminho indicado, relativo à raiz `OneFit`. O documento `entrega-perfil-aluno.md` reúne o conteúdo completo de cada arquivo, incluindo CSS, JavaScript, modal e backend.

Arquivos e responsabilidades:

- `pages/dashboard/components/section-configuracoes.php`: direciona somente o aluno ao novo componente; mantém os demais perfis e preferências existentes.
- `pages/dashboard/components/student-profile.php`: resumo, foto, cards e botão de edição.
- `pages/dashboard/components/modal-student-profile.php`: formulário completo de edição com os campos pessoais existentes, altura, peso e foto.
- `assets/css/student-profile.css`: estilo escuro e dourado, layout responsivo e rolagem do modal, limitado aos componentes do aluno.
- `assets/js/student-profile.js`: cálculo instantâneo do IMC, validação, prévia e envio da foto.
- `pages/dashboard/includes/aluno-profile.php`: normalização, cálculo PHP, classificação e validação do upload.
- `pages/dashboard/actions/update-profile.php`: grava os dados ou apenas a foto do aluno autenticado usando a conexão, sessão, CSRF e UPDATE existentes.
- `pages/dashboard/actions/_shared.php`: após uma atualização do aluno, retorna à seção Meu perfil; mantém os outros redirecionamentos.

Banco de dados: não executar migração. A consulta ao banco confirmou `usuarios.altura DECIMAL(5,2)`, `usuarios.peso DECIMAL(5,2)` e `usuarios.foto VARCHAR(255)`. Altura e peso são normalizados para duas casas decimais, em conformidade com as colunas. IMC e classificação são calculados no PHP e não recebem colunas duplicadas.

Fotos: JPG/JPEG, PNG e WEBP, até 3 MB e 40 megapixels. O backend verifica o upload recebido, a extensão, o MIME e as dimensões antes de usar o gravador existente. O arquivo fica em `assets/img/uploads/perfil/`, com nome aleatório; o caminho é persistido em `usuarios.foto`. A nova foto substitui a referência anterior na conta. Os arquivos antigos permanecem no diretório. Não é necessário alterar as configurações de upload já existentes no projeto.

Salvar envia um POST e redireciona para Meu perfil, recarregando os dados do banco com o modal fechado. Campos vazios geram IMC “Não informado”; valores inválidos são recusados pelo backend. A classificação usa as faixas para adultos solicitadas e o IMC aparece com uma casa decimal.

Validação executada:

```text
php tests/student-profile.php --schema
node tests/student-profile.js
node --check assets/js/student-profile.js
php -l (em cada arquivo PHP alterado ou criado)
git diff --check
```

Os testes verificam limites de classificação, vírgula/ponto, valores vazios e inválidos, recálculo por eventos de input, rejeição de arquivo inválido, escape de HTML, componentes únicos e isolamento dos quatro perfis. A consulta de estrutura do banco é somente leitura. Não foi realizado teste de gravação/upload por uma sessão autenticada no navegador, nem inspeção visual em desktop/celular. O anexo recebido continha apenas texto, sem as imagens citadas; o layout segue o esquema textual e os estilos existentes.
