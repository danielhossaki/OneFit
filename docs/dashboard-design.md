# Identidade visual dos dashboards

A referência é a home atual. A paleta continua exclusivamente em `assets/css/interface.css`, com `data-site-theme` para identidade e `data-theme` para modo claro/escuro.

## Compartilhamento

- `--ui-font`, `--ui-radius-*`, `--ui-border`, `--ui-glass`, `--ui-accent-text` e `--ui-motion`: acabamento comum em `interface.css`, limitado a `.home-page` e `.dashboard-page`.
- A home consome os tokens sem alterar seus valores visuais anteriores.
- A seção “Produto” de `dashboard.css` aplica os tokens aos quatro perfis, incluindo tabelas, agenda, perfil, configurações, modais, notificações e páginas auxiliares.
- Cores de sucesso/perigo são semânticas, com contraste próprio para cada modo. Não são uma segunda paleta de marca.
- IDs, classes funcionais, atributos `data-*`, JavaScript e regras PHP permanecem preservados. O único ajuste de classe de ação é o botão de confirmação de exclusão, com estado visual de perigo.

## Reprodução da revisão

Requer PHP/MySQL locais, Chrome e as ferramentas de `tests/setup-browser.ps1`. Todos os dados abaixo são sintéticos; a configuração de conexão da aplicação não é usada.

1. `php tests/dashboard-fixture.php`
2. Execute `tests/prepare-interface-web.ps1` e `tests/setup-dashboard-visual.ps1`.
3. `php tests/notification-photo-seed.php` para mostrar notificações na revisão.
4. Crie `$env:TEMP/onefit-dashboard-sessions` e inicie o servidor:
   `php -d "session.save_path=$env:TEMP/onefit-dashboard-sessions" -S 127.0.0.1:8765 -t "$env:TEMP/onefit-interface-fixture/www"`
5. Em outro terminal:
   `& "$env:TEMP/onefit-interface-tools/node-v22.14.0-win-x64/node.exe" tests/dashboard-visual.js`

Resultados e capturas ficam em `%TEMP%/onefit-dashboard-review`. O teste usa dependências públicas em cache para não depender do CDN durante a captura.

## Verificação realizada

- 30 seções dos quatro perfis, em 320, 390, 768, 1024 e 1440 pixels: 150 verificações, sem overflow horizontal da página.
- Cinco identidades nos dois modos em cada perfil: 40 combinações. Texto principal, secundário, destaque textual e texto do botão primário: contraste mínimo 4,5:1 nas superfícies verificadas.
- Autenticação real da base sintética; navegação, sidebar mobile, menus, notificações, modais e tela de senha. Nenhum erro JavaScript nos dashboards.
- Testes PHP de interface (74 verificações) e perfil do aluno aprovados; sintaxe PHP e `git diff --check` aprovados.
- O teste legado `tests/treino-render.php` falha por esperar seis colunas; o componente existente usa cinco e não foi alterado nesta refatoração. Não representa validação aprovada.

A revisão visual não executa transações financeiras ou integrações externas. Marketplace fora do dashboard mantém seu estilo próprio.