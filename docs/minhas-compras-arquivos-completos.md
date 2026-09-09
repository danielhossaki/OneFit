# Minhas compras - arquivos completos

Correcoes aplicadas: busca SQL por produto e transacao, status combinados com busca, atualizacao via fetch, contador sincronizado, estados vazios e status com cores discretas. Consulta restrita ao usuario autenticado. Componentes compartilhados entre aluno e profissional, preservando confirmacao de recebimento.

SQL NECESSARIO: nenhum. As tabelas e colunas foram verificadas no banco real.

Status: pago, processando e demais estados em andamento aparecem como Aguardando. Os estados finais do pedido sao respeitados; pedidos ainda em andamento com todos os itens entregues ou devolvidos refletem essa conclusao. Pedidos mistos continuam em acompanhamento, com a logistica individual visivel.

O Marketplace ja persiste pedido e pedido_item com status inicial aguardando. Nenhuma compra ficticia foi criada. O ID TRX- usa o id_pedido existente. O banco guarda a data de confirmacao de recebimento, mas nao uma previsao de entrega por pedido; nenhuma data foi inventada.

Validacao: 960 verificacoes de leitura com 26 compradores reais, sintaxe PHP/JavaScript e renderizacao do endpoint autenticado. Nao foi realizada validacao visual em navegador.

Cada bloco abaixo contem o arquivo inteiro, pronto para copiar e colar no caminho indicado.

ARQUIVO: assets/css/dashboard.css

CODIGO COMPLETO:

```css
/* =========================================================================
   dashboard.css
   Estilos do painel da ONE FIT.
   
     1. Variáveis de tema (dourado escuro = padrão / claro = data-theme="light")
     2. Base (body)
     3. Header (topo fixo + busca)
     4. Sidebar (menu lateral + marca)
     5. Área principal / título de página / aviso (notice)
     6. Cards (indicador simples e "metric card" com ícone)
     7. Botões
     8. Filtros + Tabela
     9. Modais
    10. Tela "em construção" (stub) e painel vazio (empty panel)
    11. Listas simples
    12. Agenda
    13. Bloco de perfil (aluno) + IMC
    14. Toast
    15. Pagamento Pix
    16. Cartão de configurações (troca de tema)
    17. Backdrop mobile + visibilidade das seções
    18. Responsivo
   ========================================================================= */

/* =========================================================================
   1. VARIÁVEIS DE TEMA
   ========================================================================= */

/* Tema padrão: dourado sobre fundo escuro */
:root {
    --bg: #0e0c0a;
    --surface: #1e1a15;
    --surface-2: #241f18;
    --border: rgba(243, 237, 226, 0.12);
    --text: #f3ede2;
    --text-muted: #b0a896;
    --gold: #d4af37;
    --gold-bright: #f4c430;
    --gold-dim: #8a6b21;
    --danger: #e42e2e;
    --success: #4caf50;

    --sidebar-w: 268px;
    --header-h: 76px;
    --header-bg: rgba(14, 12, 10, 0.94);
    --sidebar-bg: #12100e;
}

/* Tema claro: mesma identidade visual (dourado), mas em cima de fundo
   claro/branco — por isso o dourado fica mais escuro/saturado aqui
   (senão perde contraste em cima de branco), e não é só o dark invertido. */
html[data-theme="light"] {
    --bg: #f8f5f0;
    --surface: #fffdfa;
    --surface-2: #f5f0e8;
    --border: #e8dfd3;
    --text: #29251f;
    --text-muted: #766e63;
    --gold: #b98618;
    --gold-bright: #a87308;
    --gold-dim: #e4c98d;
    --danger: #c11d1d;
    --success: #2f8132;

    --header-bg: #fffdfa;
    --sidebar-bg: #fcf8f1;
}

/* No tema claro, o conteúdo fica leve e neutro, enquanto a navegação segue
   em cinza-grafite para criar a mesma hierarquia visual da referência. */
html[data-theme="light"] .bo-header { border-color: #383c43; }
html[data-theme="light"] .bo-header-search { background: #1d2025; border-color: #40454f; color: var(--gold); }
html[data-theme="light"] .bo-header-search input { color: #f5f6f7; }
html[data-theme="light"] .bo-header-search input::placeholder,
html[data-theme="light"] .bo-header-search kbd { color: #aeb4be; }
html[data-theme="light"] .bo-header-search kbd { background: #30343b; border-color: #484d56; }
html[data-theme="light"] .bo-sidebar { border-color: #383c43; }
html[data-theme="light"] .bo-side-brand { border-color: #383c43; }
html[data-theme="light"] .bo-nav-item { color: #bac0c9; }
html[data-theme="light"] .bo-nav-item:hover { background: #30343a; color: #ffffff; }
html[data-theme="light"] .bo-nav-item.active { color: #f5c76e; }
html[data-theme="light"] .bo-sidebar .bo-nav-item i { color: #b7bdc7; }
html[data-theme="light"] .bo-sidebar .bo-nav-item.active i { color: #f5c76e; }
html[data-theme="light"] .bo-sidebar-toggle,
html[data-theme="light"] .bo-user .btn-bo-outline { color: #f5f6f7; }
html[data-theme="light"] .bo-user .btn-bo-outline { border-color: #4a4e56; }
html[data-theme="light"] .bo-header .btn-bo-icon { background: #30343a; border-color: #4a4e56; color: #c8cdd4; }
html[data-theme="light"] .bo-header .btn-bo-icon:hover { border-color: var(--gold); color: var(--gold-bright); }

/* Ajustes visuais do tema claro: superfície quente e neutra, sem glow. */
html[data-theme="light"] body { background: var(--bg); }
html[data-theme="light"] .bo-header { border-color: var(--border); box-shadow: none; }
html[data-theme="light"] .bo-sidebar { border-color: #e5d4b2; }
html[data-theme="light"] .bo-side-brand { border-color: var(--border); }
html[data-theme="light"] .bo-header-search { background: #fffdfa; border-color: var(--border); color: var(--gold); }
html[data-theme="light"] .bo-header-search input { color: var(--text); }
html[data-theme="light"] .bo-header-search input::placeholder,
html[data-theme="light"] .bo-header-search kbd { color: var(--text-muted); }
html[data-theme="light"] .bo-header-search kbd { background: var(--surface-2); border-color: var(--border); }
html[data-theme="light"] .bo-header-search:focus-within { box-shadow: none; }
html[data-theme="light"] .bo-nav-item { color: #4b453d; }
html[data-theme="light"] .bo-nav-item:hover { background: #f6efe3; color: var(--text); }
html[data-theme="light"] .bo-nav-item.active { background: #fff7e7; border-color: #edcf91; color: var(--gold-bright); }
html[data-theme="light"] .bo-sidebar .bo-nav-item i { color: #8c6d31; }
html[data-theme="light"] .bo-sidebar .bo-nav-item.active i { color: var(--gold-bright); }
html[data-theme="light"] .bo-sidebar-toggle { color: var(--text); }
html[data-theme="light"] .bo-user .btn-bo-outline { background: #fffdfa; border-color: var(--border); color: var(--text); }
html[data-theme="light"] .bo-header .btn-bo-icon { background: #fffdfa; border-color: var(--border); color: #75581d; }
html[data-theme="light"] .bo-header .btn-bo-icon:hover { border-color: var(--gold); color: var(--gold-bright); }
html[data-theme="light"] .bo-metric-card,
html[data-theme="light"] .bo-data-panel,
html[data-theme="light"] .bo-settings-card,
html[data-theme="light"] .bo-card,
html[data-theme="light"] .bo-table-wrap,
html[data-theme="light"] .bo-profile-block { background: var(--surface); box-shadow: none; }
html[data-theme="light"] .bo-notice { background: #fff9ed; border-color: #efd9aa; }
html[data-theme="light"] .bo-theme-choice { background: #fffdfa; }
html[data-theme="light"] .bo-theme-choice:hover,
html[data-theme="light"] .bo-theme-choice.active { background: #fff9ed; }
html[data-theme="light"] .bo-toast { box-shadow: none; }
html[data-theme="light"] .bo-filters .form-control:focus,
html[data-theme="light"] .bo-filters .form-select:focus,
html[data-theme="light"] .bo-modal .form-control:focus,
html[data-theme="light"] .bo-modal .form-select:focus { box-shadow: none; }

/* =========================================================================
   2. BASE
   ========================================================================= */
* {
    box-sizing: border-box;
}

body {
    background: radial-gradient(circle at 78% -20%, rgba(212, 175, 55, 0.13), transparent 35%), var(--bg);
    color: var(--text);
    font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
}

/* =========================================================================
   3. HEADER
   Barra fixa no topo, deslocada para começar depois da sidebar (a sidebar
   ocupa a altura inteira da tela, ver bloco 4). Contém: botão de menu
   mobile, busca rápida e o seletor/rótulo de perfil (ver components/header.php).
   ========================================================================= */
.bo-header {
    position: fixed;
    top: 0;
    left: var(--sidebar-w);
    right: 0;
    height: var(--header-h);
    background: var(--header-bg);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border);
    z-index: 1030;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.bo-sidebar-toggle {
    background: none;
    border: none;
    color: var(--text);
    font-size: 22px;
    cursor: pointer;
    padding: 4px 8px;
}

/* Campo de busca rápida no header (opcional — só aparece se o HTML tiver
   um .bo-header-search; não é obrigatório usar em toda página) */
.bo-header-search {
    align-items: center;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--gold);
    display: flex;
    gap: 9px;
    height: 42px;
    padding: 0 9px 0 12px;
    width: min(350px, 40vw);
}

.bo-header-search-wrap {
    position: relative;
}

.bo-header-search input {
    background: transparent;
    border: 0;
    color: var(--text);
    font: inherit;
    font-size: 13px;
    min-width: 0;
    outline: 0;
    width: 100%;
}

.bo-header-search input::placeholder {
    color: var(--text-muted);
}

.bo-header-search:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, .12);
}

.bo-header-search kbd {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 5px;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
    padding: 3px 5px;
    white-space: nowrap;
}

.bo-search-results {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 16px 34px rgba(0, 0, 0, .24);
    left: 0;
    margin-top: 8px;
    max-height: min(390px, calc(100vh - 105px));
    min-width: min(350px, 78vw);
    overflow-y: auto;
    padding: 7px;
    position: absolute;
    top: 100%;
    transform: translateY(-5px);
    transition: opacity .16s ease, transform .16s ease;
    width: min(440px, 78vw);
    z-index: 1040;
}

.bo-search-results[hidden] {
    display: none;
}

.bo-search-group-label {
    color: var(--text-muted);
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    padding: 8px 10px 5px;
    text-transform: uppercase;
}

.bo-search-result {
    align-items: center;
    background: transparent;
    border: 0;
    border-radius: 8px;
    color: var(--text);
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px;
    text-align: left;
    width: 100%;
}

.bo-search-result:hover,
.bo-search-result.is-active {
    background: rgba(212, 175, 55, .11);
    color: var(--text);
}

.bo-search-result > i {
    color: var(--gold-bright);
    font-size: 17px;
    width: 19px;
}

.bo-search-result strong,
.bo-search-result small {
    display: block;
}

.bo-search-result strong {
    font-size: 13px;
}

.bo-search-result small,
.bo-search-empty {
    color: var(--text-muted);
    font-size: 12px;
}

.bo-search-empty {
    display: block;
    padding: 18px 10px;
    text-align: center;
}

.bo-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bo-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold-bright), var(--gold-dim));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #14110e;
    border: 0;
    cursor: pointer;
    flex-shrink: 0;
}

.bo-avatar:focus-visible,
.bo-search-result:focus-visible {
    outline: 3px solid rgba(212, 175, 55, .42);
    outline-offset: 2px;
}

.bo-notifications-wrap {
    position: relative;
}

.bo-notifications-toggle {
    position: relative;
    font-size: 18px;
}

.bo-notifications-count {
    position: absolute;
    top: -5px;
    right: -6px;
    min-width: 19px;
    padding: 2px 4px;
    border-radius: 20px;
    background: #b42318;
    color: #fff;
    border: 2px solid var(--surface);
    font-size: 10px;
    line-height: 13px;
}

.bo-notifications-panel {
    position: absolute;
    top: calc(100% + 9px);
    right: -50px;
    width: min(360px, calc(100vw - 36px));
    max-height: calc(100dvh - var(--header-h) - 24px);
    overflow-y: auto;
    overscroll-behavior: contain;
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, .24);
    z-index: 1041;
    transform-origin: top right;
}

.bo-notifications-panel:not([hidden]) {
    animation: bo-notifications-enter .22s ease-out;
}

@keyframes bo-notifications-enter {
    from {
        opacity: 0;
        transform: translateY(-8px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .bo-notifications-panel:not([hidden]) {
        animation: none;
    }
}

.bo-notifications-heading {
    padding: 18px 18px 12px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.bo-notifications-heading h2 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
}

.bo-notifications-heading button {
    background: transparent;
    border: 0;
    color: var(--gold-bright);
    font: inherit;
    font-size: 12px;
    padding: 6px 8px;
    border-radius: 6px;
    cursor: pointer;
}

.bo-notifications-heading button:hover:not(:disabled) {
    background: var(--surface-2);
}

.bo-notifications-heading button:disabled {
    color: var(--text-muted);
    cursor: default;
}

.bo-notifications-heading button:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 3px;
}

.bo-notifications-feedback,
.bo-notifications-empty {
    padding: 0 18px 16px;
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
}

.bo-notifications-feedback {
    font-size: 11px;
    line-height: 1.5;
}

.bo-notifications-empty {
    padding: 28px 18px;
    text-align: center;
}

.bo-notifications-list {
    list-style: none;
    padding: 0 8px 8px;
    margin: 0;
}

.bo-notifications-item {
    position: relative;
    padding: 14px 14px 14px 28px;
    border-radius: 10px;
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.6;
}

.bo-notifications-item.is-unread {
    background: var(--surface-2);
}

.bo-notifications-item.is-unread::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 21px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--gold);
}

.bo-notifications-item p {
    margin: 0 0 6px;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.bo-notifications-item-title {
    display: block;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.bo-notifications-item-link {
    display: block;
    width: fit-content;
    margin-top: 8px;
    color: var(--gold-bright);
}

.bo-notifications-item-link:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 3px;
}

.bo-notifications-item time {
    font-size: 11px;
    color: var(--text-muted);
}

@media (max-width: 560px) {
    .bo-notifications-panel { right: -46px; }
    .bo-header-search { max-width: calc(100vw - 180px); }
}

.bo-user-menu-wrap {
    position: relative;
}

.bo-user-menu {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 14px 28px rgba(0, 0, 0, .22);
    min-width: 175px;
    opacity: 0;
    overflow: hidden;
    padding: 5px;
    pointer-events: none;
    position: absolute;
    right: 0;
    top: calc(100% + 9px);
    transform: translateY(-5px);
    transition: opacity .16s ease, transform .16s ease;
    visibility: hidden;
    z-index: 1040;
}

.bo-user-menu.is-open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
    visibility: visible;
}

.bo-user-menu a {
    align-items: center;
    border-radius: 7px;
    color: var(--text);
    display: flex;
    font-size: 13px;
    gap: 9px;
    padding: 9px 10px;
    text-decoration: none;
}

.bo-user-menu a:hover {
    background: var(--surface-2);
    color: var(--gold-bright);
}

.bo-user-menu .bo-user-menu-logout:hover {
    color: var(--danger);
}

/* Dropdown "Administrador / Profissional / Aluno" no header — só existe
   (e só funciona) para o perfil admin; ver components/header.php e
   backoffice.js > boRenderPerfilMenu() */
.bo-perfil-menu.dropdown-menu {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 6px;
    min-width: 200px;
}

.bo-perfil-menu .dropdown-item {
    color: var(--text);
    border-radius: 6px;
    font-size: 14px;
    padding: 8px 12px;
}

.bo-perfil-menu .dropdown-item:hover {
    background: var(--surface-2);
    color: var(--gold-bright);
}

.bo-perfil-menu .dropdown-item.active {
    background: rgba(212, 175, 55, 0.15);
    color: var(--gold-bright);
}

/* =========================================================================
   4. SIDEBAR
   Ocupa a altura inteira da tela (inclusive atrás do header). Os itens de
   menu (.bo-nav-item) são montados dinamicamente pelo JS conforme o perfil
   ativo — ver backoffice.js > boRenderSidebar().
   ========================================================================= */
.bo-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-w);
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border);
    overflow-y: auto;
    padding: 22px 14px;
    z-index: 1025;
    transition: transform 0.3s ease;

    /* Esconde a barra de rolagem (Firefox / IE-Edge antigo) */
    scrollbar-width: none;
    -ms-overflow-style: none;
}

/* Esconde a barra de rolagem (Chrome / Edge / Safari) */
.bo-sidebar::-webkit-scrollbar {
    display: none;
}

/* Bloco de marca/logo no topo da sidebar (opcional — usar quando o logo
   ficar dentro do menu lateral em vez do header) */
.bo-side-brand {
    align-items: center;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    display: flex;
    gap: 10px;
    margin: -2px 0 20px;
    padding: 0 10px 20px;
    text-decoration: none;
}

.bo-side-brand img {
    height: 42px;
    object-fit: contain;
    width: 42px;
}

.bo-side-brand span {
    color: var(--gold);
    font-size: 16px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.bo-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    border-radius: 11px;
    color: var(--text-muted);
    text-decoration: none;
    margin-bottom: 5px;
    font-size: 14px;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    background: none;
    width: 100%;
    text-align: left;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.bo-nav-item i {
    font-size: 16px;
    width: 20px;
    text-align: center;
    color: var(--gold-dim);
}

.bo-nav-item:hover {
    background: var(--surface-2);
    color: var(--text);
}

.bo-nav-item.active {
    background: linear-gradient(100deg, rgba(212, 175, 55, .20), rgba(212, 175, 55, .06));
    border-color: rgba(212, 175, 55, .4);
    color: var(--gold-bright);
}

.bo-nav-item.active i {
    color: var(--gold-bright);
}

/* =========================================================================
   5. ÁREA PRINCIPAL / TÍTULO DE PÁGINA / EYEBROW / NOTICE
   ========================================================================= */
.bo-main {
    margin-left: var(--sidebar-w);
    margin-top: var(--header-h);
    padding: 34px 38px 64px;
    min-height: calc(100vh - var(--header-h));
}

/* Cabeçalho de cada tela (título + descrição + botões de ação) */
.bo-page-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 26px;
    flex-wrap: wrap;
    gap: 12px;
}

.bo-page-title h1 {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.04em;
    text-transform: none;
    color: var(--text);
    margin: 0;
}

.bo-page-title p {
    color: var(--text-muted);
    font-size: 14px;
    max-width: 610px;
    margin: 4px 0 0;
}

.bo-page-title .bo-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Rótulo pequeno em destaque acima de um título (opcional) */
.bo-eyebrow {
    align-items: center;
    color: var(--gold);
    display: inline-flex;
    font-size: 12px;
    font-weight: 800;
    gap: 7px;
    letter-spacing: .08em;
    margin-bottom: 8px;
    text-transform: uppercase;
}

/* Aviso/banner de destaque (ex: "esta tela ainda usa dados de exemplo") */
.bo-notice {
    align-items: flex-start;
    background: linear-gradient(110deg, rgba(212, 175, 55, .13), rgba(212, 175, 55, .035));
    border: 1px solid rgba(212, 175, 55, .28);
    border-radius: 15px;
    display: flex;
    gap: 13px;
    margin-bottom: 18px;
    padding: 17px 19px;
    /* Some sozinho após alguns segundos: sem isso a mensagem fica presa no
       topo ao trocar de aba, já que o JS só alterna a section visível e
       nunca remove este bloco (ele fica fora das <section data-section>). */
    animation: bo-notice-fade 6s ease-in forwards;
    overflow: hidden;
}

@keyframes bo-notice-fade {
    0% { opacity: 1; max-height: 200px; margin-bottom: 18px; padding-top: 17px; padding-bottom: 17px; }
    80% { opacity: 1; max-height: 200px; margin-bottom: 18px; padding-top: 17px; padding-bottom: 17px; }
    100% { opacity: 0; max-height: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0; border-width: 0; pointer-events: none; }
}

.bo-notice>i {
    color: var(--gold-bright);
    font-size: 20px;
}

.bo-notice strong,
.bo-notice span {
    display: block;
}

.bo-notice strong {
    color: var(--text);
    font-size: 14px;
    margin-bottom: 3px;
}

.bo-notice span {
    color: var(--text-muted);
    font-size: 13px;
}

/* =========================================================================
   6. CARDS
   ========================================================================= */

/* Card simples de indicador (usado nos dashboards originais) */
.bo-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    height: 100%;
}

.bo-card-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.bo-card-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 4px;
}

.bo-card-sub {
    font-size: 13px;
    color: var(--gold);
    font-weight: 600;
}

/* Grade de "metric cards" (versão com ícone, usada nos dashboards novos) */
.bo-metric-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 18px;
}

.bo-metric-card,
.bo-data-panel,
.bo-settings-card {
    background: color-mix(in srgb, var(--surface) 96%, transparent);
    border: 1px solid var(--border);
    border-radius: 16px;
}

.bo-metric-card {
    align-items: flex-start;
    display: flex;
    gap: 13px;
    min-height: 142px;
    padding: 20px;
}

.bo-metric-icon {
    align-items: center;
    background: rgba(212, 175, 55, .13);
    border: 1px solid rgba(212, 175, 55, .16);
    border-radius: 11px;
    color: var(--gold-bright);
    display: inline-flex;
    flex: 0 0 42px;
    font-size: 18px;
    height: 42px;
    justify-content: center;
    width: 42px;
}

.bo-metric-card .bo-card-label {
    display: block;
    margin: 2px 0 8px;
}

.bo-metric-card strong {
    color: var(--text);
    display: block;
    font-size: 27px;
    line-height: 1;
}

.bo-metric-card small {
    color: var(--text-muted);
    display: block;
    font-size: 11px;
    margin-top: 10px;
}

/* =========================================================================
   7. BOTÕES
   ========================================================================= */
.btn-bo-gold {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-radius: 8px;
    border: none;
    background: linear-gradient(120deg, var(--gold-dim), var(--gold) 45%, var(--gold-bright) 60%, var(--gold) 75%, var(--gold-dim));
    background-size: 250% 100%;
    color: #14110e;
    cursor: pointer;
    transition: background-position 0.3s ease, transform 0.2s ease;
}

.btn-bo-gold:hover {
    background-position: 100% 0;
    color: #14110e;
    transform: translateY(-1px);
}

.logout-button{
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    font-weight: 600;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: rgba(189, 5, 5, 0.301) !important;
    color: var(--text);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
    text-decoration: none;
}

.logout-button:hover{
    background: rgba(114, 3, 3, 0.301) !important;
    
}

.btn-bo-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    font-weight: 600;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
    text-decoration: none;
}

.btn-bo-outline:hover {
    border-color: var(--gold);
    color: var(--gold-bright);
}

.btn-bo-icon {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
}

.btn-bo-icon:hover {
    border-color: var(--gold);
    color: var(--gold-bright);
}

.btn-bo-icon.danger:hover {
    border-color: var(--danger);
    color: var(--danger);
}

/* =========================================================================
   8. FILTROS + TABELA
   ========================================================================= */
.bo-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 18px;
}

.bo-filters .form-control,
.bo-filters .form-select {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
}

.bo-filters .form-control::placeholder {
    color: var(--text-muted);
}

.bo-filters .form-control:focus,
.bo-filters .form-select:focus {
    background: var(--surface-2);
    border-color: var(--gold);
    color: var(--text);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

.bo-filters .bo-daterange {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 13px;
}

.bo-table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 24px;
}

.bo-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.bo-table thead th {
    color: var(--text-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border);
    padding: 14px 16px;
    text-align: left;
    white-space: nowrap;
}

.bo-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    vertical-align: middle;
}

.bo-table tbody tr:last-child td {
    border-bottom: none;
}

.bo-table tbody tr:hover {
    background: var(--surface-2);
}

/* Miniatura de imagem (foto de produto/profissional) dentro da tabela */
.bo-thumb {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 16px;
    overflow: hidden;
}

.bo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Badge de status (ativo/inativo, disponível/indisponível) — ver helpers.php > bo_badge() */
.bo-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.bo-badge-active {
    background: rgba(76, 175, 80, 0.15);
    color: var(--success);
}

.bo-badge-inactive {
    background: rgba(228, 87, 46, 0.15);
    color: var(--danger);
}

.bo-table-actions {
    display: flex;
    gap: 8px;
}

.bo-inline-form {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 160px;
}

.bo-nav-tabs {
    border-bottom-color: var(--border);
    margin-bottom: 18px;
}

.bo-nav-tabs .nav-link {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    font-weight: 700;
}

.bo-nav-tabs .nav-link:hover {
    border-color: var(--border);
    color: var(--text);
}

.bo-nav-tabs .nav-link.active {
    background: var(--surface);
    border-color: var(--border) var(--border) var(--surface);
    color: var(--gold-bright);
}

/* Linha "nenhum resultado encontrado", mostrada pelo JS quando o filtro zera a lista */
.bo-empty-row td {
    text-align: center;
    padding: 40px 16px;
    color: var(--text-muted);
}

/* =========================================================================
   9. MODAIS
   ========================================================================= */
.bo-modal .modal-content {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 14px;
}

.bo-modal .modal-header,
.bo-modal .modal-footer {
    border-color: var(--border);
}

.bo-modal .form-label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
}

.bo-modal .form-control,
.bo-modal .form-select {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
}

.bo-modal .form-control:focus,
.bo-modal .form-select:focus {
    background: var(--surface-2);
    border-color: var(--gold);
    color: var(--text);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

.bo-modal .btn-close {
    filter: invert(1) grayscale(1) brightness(1.6);
}

/* No tema claro o botão de fechar do Bootstrap já nasce escuro — não
   precisa do filtro de inverter cor (que foi pensado pro tema escuro) */
html[data-theme="light"] .bo-modal .btn-close {
    filter: none;
}

/* Modal do perfil profissional: permanece escuro mesmo quando o painel usa
   o tema claro. O :has() limita estas variáveis às páginas que realmente
   carregaram section-profissional.php; outros perfis não são afetados. */
body:has(.bo-content-section[data-perfil="profissional"]) #boFormModal {
    --surface: #1e1a15;
    --surface-2: #241f18;
    --border: rgba(243, 237, 226, 0.12);
    --text: #f3ede2;
    --text-muted: #b0a896;
    --gold: #d4af37;
    --gold-bright: #f4c430;
    --gold-dim: #8a6b21;
}

body:has(.bo-content-section[data-perfil="profissional"]) #boFormModal .btn-close {
    filter: invert(1) grayscale(1) brightness(1.6);
}

/* Pré-visualização de imagem nos campos do tipo "image" (upload/URL) */
.bo-modal img[data-bo-preview] {
    max-height: 140px;
    border-radius: 8px;
    border: 1px solid var(--border);
    display: none;
    object-fit: cover;
}

/* =========================================================================
   10. TELA "EM CONSTRUÇÃO" (stub) + PAINEL VAZIO (empty panel)
   ========================================================================= */
.bo-stub {
    background: var(--surface);
    border: 1px dashed var(--border);
    border-radius: 14px;
    padding: 60px 32px;
    text-align: center;
}

.bo-stub i {
    font-size: 40px;
    color: var(--gold);
    margin-bottom: 16px;
}

.bo-stub h2 {
    font-size: 18px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}

.bo-stub p {
    color: var(--text-muted);
    max-width: 420px;
    margin: 0 auto;
}

/* Painel vazio "genérico" (mesma ideia do stub, mas dentro de um
   .bo-data-panel — usado quando uma tabela/lista de dados reais ainda
   não tem nenhum registro) */
.bo-data-panel {
    min-height: 270px;
    padding: 28px;
}

.bo-empty-panel {
    align-items: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
    height: 100%;
}

.bo-empty-panel>i {
    color: var(--gold);
    font-size: 34px;
    margin-bottom: 13px;
}

.bo-empty-panel h2 {
    color: var(--text);
    font-size: 17px;
    font-weight: 800;
    margin: 0 0 7px;
}

.bo-empty-panel p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
    max-width: 440px;
}

/* =========================================================================
   11. LISTAS SIMPLES (usadas em "Funções" e "Categorias" — sem tabela, só linhas)
   ========================================================================= */
.bo-list {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}

.bo-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    gap: 12px;
}

.bo-list-item:last-child {
    border-bottom: none;
}

.bo-list-item .bo-list-title {
    font-weight: 700;
    font-size: 14px;
}

.bo-list-item .bo-list-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* =========================================================================
   12. AGENDA (cards de horário agendado/disponível — profissional e aluno)
   ========================================================================= */
.bo-agenda-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

/* Borda tracejada para diferenciar horários "disponíveis" dos já agendados */
.bo-agenda-card.disponivel {
    border-style: dashed;
}

.bo-agenda-card .bo-agenda-title {
    font-weight: 700;
    font-size: 14px;
}

.bo-agenda-card .bo-agenda-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Título pequeno usado para separar grupos dentro de uma mesma seção
   (ex: "Agendados" / "Disponíveis" na tela de Agenda) */
.bo-section-heading {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin: 24px 0 12px;
    font-weight: 700;
}

.bo-section-heading:first-child {
    margin-top: 0;
}

/* =========================================================================
   13. PERFIL DO ALUNO (bloco de dados cadastrais + bloco de avaliação física/IMC)
   ========================================================================= */
.bo-profile-block {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}

.bo-profile-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    gap: 12px;
}

.bo-profile-row:last-child {
    border-bottom: none;
}

.bo-profile-row span:first-child {
    color: var(--text-muted);
}

.bo-imc-box {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

/* =========================================================================
   14. TOAST (aviso flutuante no canto inferior direito)
   ========================================================================= */
.bo-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--surface);
    border: 1px solid var(--gold);
    color: var(--text);
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    z-index: 2000;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.25s ease, transform 0.25s ease;
    pointer-events: none;
}

.bo-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* =========================================================================
   15. PAGAMENTO PIX (QR code simulado + campo "copia e cola")
   ========================================================================= */
.bo-pix-box {
    background: var(--surface-2);
    border: 1px dashed var(--border);
    border-radius: 10px;
    padding: 16px;
    text-align: center;
}

.bo-qr-placeholder {
    width: 140px;
    height: 140px;
    margin: 0 auto 12px;
    border-radius: 8px;
    background:
        repeating-linear-gradient(45deg, var(--gold-dim) 0 8px, transparent 8px 16px),
        var(--surface);
    border: 1px solid var(--border);
}

/* =========================================================================
   16. CARTÃO DE CONFIGURAÇÕES (troca de tema claro/escuro)
   ========================================================================= */
.bo-settings-card {
    max-width: 760px;
    padding: 26px;
}

.bo-settings-stack {
    display: grid;
    gap: 18px;
    max-width: 760px;
    width: 100%;
}

.bo-settings-stack .bo-settings-card {
    max-width: none;
}

@media (min-width: 992px) and (max-width: 1199px) {
    .bo-settings-stack {
        max-width: 900px;
    }
}

@media (min-width: 1200px) {
    .bo-settings-stack {
        max-width: 1080px;
    }
}

.bo-settings-heading {
    align-items: center;
    display: flex;
    gap: 14px;
    margin-bottom: 22px;
}

.bo-settings-heading h2 {
    color: var(--text);
    font-size: 16px;
    font-weight: 800;
    margin: 0 0 4px;
}

.bo-settings-heading p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
}

.bo-theme-choices {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.bo-theme-choice {
    align-items: center;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    display: flex;
    gap: 12px;
    padding: 16px;
    text-align: left;
    transition: border-color .2s, background .2s;
}

.bo-theme-choice:hover,
.bo-theme-choice.active {
    background: rgba(212, 175, 55, .09);
    border-color: var(--gold);
}

.bo-theme-choice:focus-visible,
.bo-toggle-input:focus-visible {
    outline: 3px solid rgba(212, 175, 55, .35);
    outline-offset: 3px;
}

.bo-theme-choice:disabled,
.bo-toggle-input:disabled {
    cursor: wait;
    opacity: .65;
}

.bo-theme-choice>i:first-child {
    color: var(--gold-bright);
    font-size: 20px;
}

.bo-theme-choice span {
    flex: 1;
}

.bo-theme-choice strong,
.bo-theme-choice small {
    display: block;
}

.bo-theme-choice strong {
    font-size: 14px;
}

.bo-theme-choice small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 2px;
}

.bo-settings-logout,
.bo-settings-action-row {
    align-items: center;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 16px;
    justify-content: space-between;
    margin-top: 23px;
    padding-top: 20px;
}

.bo-settings-logout strong,
.bo-settings-logout span,
.bo-settings-action-row strong,
.bo-settings-action-row span {
    display: block;
}

.bo-settings-logout strong,
.bo-settings-action-row strong {
    color: var(--text);
    font-size: 14px;
}

.bo-settings-logout span,
.bo-settings-action-row span {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 3px;
}

.bo-preference-list {
    display: grid;
}

.bo-preference-row {
    align-items: center;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    min-height: 58px;
    transition: opacity .2s;
}

.bo-preference-row:first-child {
    border-top: 0;
}

.bo-preference-row label {
    align-items: center;
    color: var(--text);
    cursor: pointer;
    display: flex;
    font-size: 14px;
    gap: 11px;
}

.bo-preference-row label i {
    color: var(--gold-bright);
    font-size: 17px;
}

.bo-preference-row.is-loading {
    opacity: .6;
}

.bo-toggle-input {
    appearance: none;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 999px;
    cursor: pointer;
    flex: 0 0 auto;
    height: 26px;
    margin: 0;
    position: relative;
    transition: background .2s, border-color .2s;
    width: 46px;
}

.bo-toggle-input::after {
    background: var(--text-muted);
    border-radius: 50%;
    content: '';
    height: 18px;
    left: 3px;
    position: absolute;
    top: 3px;
    transition: transform .2s, background .2s;
    width: 18px;
}

.bo-toggle-input:checked {
    background: rgba(212, 175, 55, .24);
    border-color: var(--gold);
}

.bo-toggle-input:checked::after {
    background: var(--gold-bright);
    transform: translateX(20px);
}

.bo-settings-hint {
    color: var(--text-muted);
    font-size: 12px;
    margin: 16px 0 0;
}

.bo-logout-button:hover {
    border-color: var(--danger);
    color: var(--danger);
}

/* =========================================================================
   17. BACKDROP MOBILE + VISIBILIDADE DAS SEÇÕES
   ========================================================================= */

/* Camada escura atrás da sidebar quando aberta no mobile (fecha ao clicar fora) */
.bo-sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1024;
}

/* Cada "página" do backoffice é uma <section data-perfil="..." data-section="...">
   escondida por padrão; o JS mostra só a seção ativa trocando essa classe */
.bo-content-section {
    display: none;
}

.bo-content-section.active {
    display: block;
}

/* =========================================================================
   18. RESPONSIVO
   ========================================================================= */
@media (max-width: 1199px) {
    .bo-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 991px) {

    .bo-theme-choices {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    /* Sidebar vira um painel deslizante (off-canvas) em telas menores */
    .bo-sidebar {
        transform: translateX(-100%);
    }

    .bo-sidebar.active {
        transform: translateX(0);
    }

    .bo-sidebar-backdrop.active {
        display: block;
    }

    .bo-header {
        left: 0;
        padding: 0 18px;
    }

    .bo-main {
        margin-left: 0;
        padding: 26px 18px 48px;
    }
}

@media (max-width: 560px) {

    /* Esconde o texto "One Fit · Backoffice" no header, mantém só a logo */
    .bo-logo span {
        display: none;
    }

    .bo-header-search {
        width: min(260px, 50vw);
    }

    .bo-metric-grid,
    .bo-theme-choices {
        grid-template-columns: 1fr;
    }

    .bo-metric-card {
        min-height: 112px;
    }

    .bo-header-settings {
        display: none;
    }

    .bo-user {
        gap: 8px;
    }

    .bo-search-results {
        left: min(-52px, -18vw);
        width: min(340px, calc(100vw - 28px));
    }

    .bo-settings-logout {
        align-items: flex-start;
        flex-direction: column;
    }

    .bo-settings-action-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .bo-settings-action-row .btn-bo-outline,
    .bo-settings-action-row .logout-button {
        justify-content: center;
        width: 100%;
    }

    .btn-bo-outline {
        padding: 8px 11px;
    }
}

/* Checklist de modalidades no cadastro de profissional */
.bo-checklist {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface-2);
}

.bo-checklist-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    cursor: pointer;
}

.bo-checklist-item input {
    accent-color: var(--gold);
    cursor: pointer;
}

[data-bo-compras] .bo-compra-aguardando { color: #d8b45a; background: rgba(216, 180, 90, .12); }
[data-bo-compras] .bo-compra-entregue { color: #55ba88; background: rgba(85, 186, 136, .12); }
[data-bo-compras] .bo-compra-cancelado { color: #df8585; background: rgba(223, 133, 133, .12); }
[data-bo-compras] .bo-compra-devolvido { color: #b19bd9; background: rgba(177, 155, 217, .12); }

```

ARQUIVO: assets/js/dashboard.js

CODIGO COMPLETO:

```js
/* =========================================================================
   backoffice.js
   Toda a interatividade do painel: troca de perfil (admin/profissional/
   aluno), montagem dinâmica do menu lateral, abertura do modal de
   formulário genérico (cadastro/edição), filtros de tabela, cálculo de
   IMC, simulação de pagamento (Pix/cartão) e exportação de tabela em CSV.

   Depende de duas variáveis globais definidas ANTES deste arquivo, no
   próprio dashboard.php (porque vêm de dados do PHP):
     - BO_CATEGORIAS_OPTIONS  (nomes das categorias de produto)
     - BO_PLANOS_OPTIONS      (nomes dos planos cadastrados)
   ========================================================================= */

/* ---------- Notificações reais do usuário autenticado ---------- */
document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('boNotificationsWrap');
    if (!wrap) return;
    const toggle = document.getElementById('boNotificationsToggle');
    const panel = document.getElementById('boNotificationsPanel');
    const count = document.getElementById('boNotificationsCount');
    const list = document.getElementById('boNotificationsList');
    const empty = document.getElementById('boNotificationsEmpty');
    const readAll = document.getElementById('boNotificationsReadAll');
    const status = document.getElementById('boNotificationsStatus');
    const feedback = document.getElementById('boNotificationsFeedback');
    let busy = false;
    let unread = 0;
    const render = (data) => {
        unread = data.nao_lidas;
        count.textContent = unread > 99 ? '99+' : String(unread);
        count.hidden = unread === 0;
        toggle.setAttribute('aria-label', `Notificações: ${unread} não lidas`);
        readAll.disabled = unread === 0;
        empty.hidden = data.notificacoes.length > 0;
        list.replaceChildren();
        data.notificacoes.forEach(item => {
            const row = document.createElement('li');
            row.className = `bo-notifications-item${item.lida_em ? '' : ' is-unread'}`;
            const title = document.createElement('strong');
            title.className = 'bo-notifications-item-title';
            title.textContent = item.titulo;
            const text = document.createElement('p');
            text.textContent = item.mensagem;
            if (!item.lida_em) {
                const label = document.createElement('span');
                label.className = 'visually-hidden';
                label.textContent = 'Não lida. ';
                text.prepend(label);
            }
            const time = document.createElement('time');
            const date = new Date(item.criada_em.replace(' ', 'T') + 'Z');
            time.dateTime = date.toISOString();
            time.textContent = date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
            row.append(title, text, time);
            // Defesa adicional para links eventualmente inseridos por outros serviços.
            if (typeof item.link === 'string' && /^\/(?!\/)/.test(item.link) && !/[\\\s\u0000-\u001f]/.test(item.link)) {
                const url = new URL(item.link, location.origin);
                if (url.origin === location.origin) {
                    const link = document.createElement('a');
                    link.href = url.href;
                    link.className = 'bo-notifications-item-link';
                    link.textContent = 'Ver detalhes';
                    row.append(link);
                }
            }
            list.append(row);
        });
    };
    const refresh = async (markRead = false) => {
        if (busy) return;
        busy = true;
        readAll.disabled = true;
        panel.setAttribute('aria-busy', 'true');
        try {
            const options = { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } };
            if (markRead) {
                options.method = 'POST';
                options.body = new URLSearchParams({ csrf_token: BO_CSRF_TOKEN });
            }
            const response = await fetch(wrap.dataset.url, options);
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Não foi possível carregar as notificações.');
            render(data);
            feedback.hidden = true;
            if (markRead) {
                panel.focus();
                status.textContent = 'Todas as notificações foram marcadas como lidas.';
            }
        } catch (error) {
            feedback.textContent = error.message || 'Não foi possível atualizar. Reabra as notificações para tentar novamente.';
            feedback.hidden = false;
        } finally {
            busy = false;
            readAll.disabled = unread === 0;
            panel.setAttribute('aria-busy', 'false');
        }
    };
    const close = () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };
    toggle.addEventListener('click', () => {
        if (!panel.hidden) { close(); return; }
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        panel.focus();
        refresh();
    });
    readAll.addEventListener('click', () => refresh(true));
    document.addEventListener('click', event => {
        if (!wrap.contains(event.target)) close();
    });
    document.addEventListener('focusin', event => {
        if (!wrap.contains(event.target)) close();
    });
    wrap.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            close();
            toggle.focus();
        }
    });
    refresh();
    // Atualiza ao retornar à aba e periodicamente, sem requisições sobrepostas.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    window.setInterval(() => { if (!document.hidden) refresh(); }, 60000);
});

/* ---------- Preferências de notificações: salvar cada switch na conta ---------- */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bo-preference]').forEach((input) => {
        let savedValue = input.checked;
        let saving = false;
        input.addEventListener('change', async () => {
            if (saving) return;
            const value = input.checked ? 1 : 0;
            saving = true;
            input.disabled = true;
            input.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(BO_PREFERENCES_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    body: new URLSearchParams({
                        csrf_token: BO_CSRF_TOKEN,
                        key: input.dataset.boPreference,
                        value: String(value),
                    }),
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true) {
                    throw new Error(result.message || 'Não foi possível salvar a preferência.');
                }
                savedValue = value === 1;
                input.checked = savedValue;
            } catch (error) {
                input.checked = savedValue;
                boToast(error instanceof SyntaxError || error instanceof TypeError
                    ? 'Não foi possível confirmar a gravação. Atualize a página e tente novamente.'
                    : error.message);
            } finally {
                saving = false;
                input.disabled = false;
                input.removeAttribute('aria-busy');
            }
        });
    });
});

/* ---------- Perfis de acesso ----------
   Cada perfil define o rótulo mostrado no header e os itens do menu
   lateral (chave da seção + label + ícone Bootstrap Icons). A seção
   correspondente já existe como <section data-perfil="..." data-section="...">
   no HTML (ver components/section-*.php); se não existir, cai no
   fallback "Em construção" (ver boGoToSection). */
const BO_PERFIS = {
    admin: {
        label: 'Administrador',
        menus: [
            { key: 'dashboard', label: 'Visão Geral', icon: 'bi-speedometer2' },
            { key: 'usuarios', label: 'Usuários', icon: 'bi-people' },
            { key: 'permissoes', label: 'Permissões', icon: 'bi-shield-lock' },
            { key: 'funcoes', label: 'Funções', icon: 'bi-diagram-3' },
            { key: 'pagamentos', label: 'Pagamentos', icon: 'bi-credit-card' },
            { key: 'cashbacks', label: 'Cashbacks', icon: 'bi-wallet2' },
            { key: 'categorias', label: 'Categorias', icon: 'bi-tags' },
            { key: 'produtos', label: 'Produtos', icon: 'bi-box-seam' },
            { key: 'vendas', label: 'Vendas Marketplace', icon: 'bi-truck' },
            { key: 'planos', label: 'Cadastro de Planos', icon: 'bi-clipboard-check' },
            { key: 'profissionais', label: 'Profissionais', icon: 'bi-person-badge' },
            { key: 'modalidades', label: 'Modalidades', icon: 'bi-activity' },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    vendedor: {
        label: 'Vendedor',
        menus: [
            { key: 'vendas', label: 'Vendas Marketplace', icon: 'bi-truck' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    profissional: {
        label: 'Profissional',
        menus: [
            { key: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
            { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
            { key: 'alunos', label: 'Alunos', icon: 'bi-people' },
            { key: 'agenda', label: 'Agenda', icon: 'bi-calendar3' },
            { key: 'cashback', label: 'Meu cashback', icon: 'bi-wallet2' },
            { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    aluno: {
        label: 'Aluno',
        menus: [
            { key: 'perfil', label: 'Perfil', icon: 'bi-person-circle' },
            { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
            { key: 'cashback', label: 'Cashback', icon: 'bi-wallet2' },
            { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
            { key: 'treino', label: 'Treino', icon: 'bi-lightning-charge' },
            { key: 'agenda', label: 'Minha agenda', icon: 'bi-calendar3' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
};

// Estado atual da tela: qual perfil está sendo visualizado e qual seção do menu.
// boPerfilAtual começa no perfil REAL do usuário logado (BO_PERFIL_LOGADO,
// definido no <script> inline do dashboard.php a partir da sessão/tipo_usuario)
// — só o admin pode trocar isso depois, pelo dropdown do header.
let boPerfilAtual = (typeof BO_PERFIL_LOGADO !== 'undefined') ? BO_PERFIL_LOGADO : 'aluno';
let boSectionAtual = null; // definida no DOMContentLoaded, com base no 1º item do menu do perfil
let boFormModalInstance = null; // instância do Modal do Bootstrap (definida no DOMContentLoaded)

// Converte todos os códigos ISO 3166-1 em nomes de países no idioma do painel.
// O nome selecionado é armazenado como nacionalidade no perfil do usuário.
const BO_COUNTRY_CODES = `AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW`.split(' ');
const boRegionNames = typeof Intl.DisplayNames === 'function'
    ? new Intl.DisplayNames(['pt-BR'], { type: 'region' })
    : null;
const BO_NATIONALITY_OPTIONS = BO_COUNTRY_CODES
    .map((code) => boRegionNames ? boRegionNames.of(code) : code)
    .sort((a, b) => a.localeCompare(b, 'pt-BR'));

/* ---------- Esquemas do modal de formulário genérico ----------
   Cada chave corresponde ao 1º argumento passado em boOpenForm(schemaKey, ...)
   nos botões "onclick" do HTML. A lista de campos aqui é usada por
   boBuildField() para montar o formulário dinamicamente dentro do modal
   #boFormModal, sem precisar de um modal HTML diferente para cada tela. */
const BO_FORM_SCHEMAS = {
    alunoDoProfissionalForm: [
        { key: 'nome', label: 'Nome', type: 'text', col: 12 },
        { key: 'contato', label: 'Contato', type: 'text', col: 6 },
        { key: 'plano', label: 'Plano', type: 'text', col: 6 },
        { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
        { key: 'valor', label: 'Valor', type: 'number', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    agendaDisponivel: [
        { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
        { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
    ],
    agendaAgendar: [
        { key: 'aluno', label: 'Aluno', type: 'text', col: 12 },
        { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
        { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    utilizarCashback: [
        { key: 'valor', label: 'Valor a utilizar', type: 'number', col: 12 },
    ],
    planoAlterar: [
        { key: 'plano', label: 'Novo plano', type: 'select', options: BO_PLANOS_OPTIONS, col: 12 },
    ],
    perfilEdit: [
        { key: 'nome', label: 'Nome', type: 'text', col: 6, required: true },
        { key: 'documento', label: 'Documento', type: 'text', col: 6, required: true },
        { key: 'email', label: 'E-mail', type: 'email', col: 6, required: true },
        { key: 'telefone', label: 'Telefone', type: 'text', col: 6, required: true },
        { key: 'nacionalidade', label: 'Nacionalidade', type: 'select', options: BO_NATIONALITY_OPTIONS, col: 6, required: true },
        { key: 'nascimento', label: 'Data de nascimento', type: 'date', col: 6, required: true },
        { key: 'genero', label: 'Gênero', type: 'select', options: ['masculino', 'feminino', 'outro'], optionLabels: ['Masculino', 'Feminino', 'Outro'], col: 6, required: true },
        { key: 'endereco', label: 'Endereço', type: 'text', col: 12, required: true },
        { key: 'cidade', label: 'Cidade', type: 'text', col: 6, required: true },
        { key: 'estado', label: 'Estado (UF)', type: 'text', col: 6, required: true },
        { key: 'altura', label: 'Altura (m)', type: 'number', col: 6, min: 0.5, max: 3, step: 0.01 },
        { key: 'peso', label: 'Peso (kg)', type: 'number', col: 6, min: 1, max: 500, step: 0.1 },
        { key: 'foto', label: 'URL da foto', type: 'url', col: 12 },
    ],
    treinoExercicio: [
        { key: 'nome', label: 'Exercício', type: 'text', col: 12 },
        { key: 'series', label: 'Séries', type: 'number', col: 4 },
        { key: 'repeticoes', label: 'Repetições', type: 'number', col: 4 },
        { key: 'carga', label: 'Carga (kg)', type: 'number', col: 4 },
    ],
};

/**
 * Cria o elemento de UM campo do formulário (label + input/select/textarea/
 * checklist/upload de imagem), de acordo com o "type" definido no schema
 * acima. É chamada uma vez por campo dentro de boOpenForm().
 */
function boBuildField(field) {
    const wrap = document.createElement('div');
    wrap.className = 'col-' + (field.col || 12);

    const label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = field.label;
    wrap.appendChild(label);

    if (field.type === 'select') {
        const select = document.createElement('select');
        select.className = 'form-select';
        select.setAttribute('data-bo-field', field.key);
        field.options.forEach((opt, i) => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = (field.optionLabels && field.optionLabels[i]) || opt;
            select.appendChild(o);
        });
        wrap.appendChild(select);
    } else if (field.type === 'textarea') {
        const ta = document.createElement('textarea');
        ta.className = 'form-control';
        ta.rows = 3;
        ta.setAttribute('data-bo-field', field.key);
        wrap.appendChild(ta);
    } else if (field.type === 'checklist') {
        // Grupo de checkboxes (ex: permissões de uma função)
        const box = document.createElement('div');
        box.className = 'd-flex flex-wrap gap-3';
        field.options.forEach((opt) => {
            const id = 'chk_' + field.key + '_' + opt.replace(/\s+/g, '');
            const chkWrap = document.createElement('div');
            chkWrap.className = 'form-check';
            chkWrap.innerHTML = `<input class="form-check-input" type="checkbox" id="${id}" value="${opt}" data-bo-checklist="${field.key}"><label class="form-check-label" for="${id}">${opt}</label>`;
            box.appendChild(chkWrap);
        });
        wrap.appendChild(box);
    } else if (field.type === 'image') {
        // Campo de imagem: aceita tanto uma URL digitada quanto upload de
        // arquivo local (convertido para base64 e mostrado na pré-visualização)
        const url = document.createElement('input');
        url.type = 'text';
        url.className = 'form-control mb-2';
        url.placeholder = 'URL da imagem';
        url.setAttribute('data-bo-field', field.key);
        wrap.appendChild(url);

        const file = document.createElement('input');
        file.type = 'file';
        file.accept = 'image/*';
        file.className = 'form-control mb-2';
        wrap.appendChild(file);

        const preview = document.createElement('img');
        preview.setAttribute('data-bo-preview', field.key);
        wrap.appendChild(preview);

        url.addEventListener('input', () => {
            if (url.value) {
                preview.src = url.value;
                preview.style.display = 'block';
            }
        });
        file.addEventListener('change', () => {
            const f = file.files[0];
            if (f) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    url.value = ''; // upload tem prioridade sobre a URL digitada
                };
                reader.readAsDataURL(f);
            }
        });
    } else {
        // text / email / date / number (padrão)
        const input = document.createElement('input');
        input.type = field.type;
        input.className = 'form-control';
        input.setAttribute('data-bo-field', field.key);
        if (field.placeholder) input.placeholder = field.placeholder;
        if (field.readonly) input.readOnly = true;
        wrap.appendChild(input);
    }

    const control = wrap.querySelector(`[data-bo-field="${field.key}"]`);
    if (control) {
        if (field.required) control.required = true;
        if (field.min !== undefined) control.min = field.min;
        if (field.max !== undefined) control.max = field.max;
        if (field.step !== undefined) control.step = field.step;
    }

    return wrap;
}

/**
 * Abre o modal genérico de formulário (#boFormModal), monta os campos
 * do schema indicado, preenche com "values" (quando for edição) e
 * prepara o botão "Salvar". Chamada pelos botões "Novo X" / "Editar" no HTML.
 *
 * @param {string} schemaKey  chave em BO_FORM_SCHEMAS (ex: 'produtoForm')
 * @param {string} title      título mostrado no cabeçalho do modal
 * @param {object} values     valores já existentes (edição) ou {} (novo)
 * @param {object} options    { doubleConfirm: true } exige clicar 2x em
 *                             "Salvar" antes de confirmar (usado em ações
 *                             sensíveis, ex: permissões)
 */
function boOpenForm(schemaKey, title, values, options) {
    values = values || {};
    options = options || {};

    const form = document.getElementById('boFormModalForm');
    form.innerHTML = '';
    document.getElementById('boFormModalTitle').textContent = title;

    const fields = BO_FORM_SCHEMAS[schemaKey] || [];
    fields.forEach((field) => form.appendChild(boBuildField(field)));

    // Preenche os campos recém-criados com os valores atuais do registro
    fields.forEach((field) => {
        if (field.type === 'checklist') {
            const selected = (values[field.key] || '').split(',').map((s) => s.trim());
            form.querySelectorAll(`[data-bo-checklist="${field.key}"]`).forEach((chk) => {
                chk.checked = selected.includes(chk.value);
            });
            return;
        }
        const el = form.querySelector(`[data-bo-field="${field.key}"]`);
        if (el && values[field.key] !== undefined) {
            // Preserva valores antigos que ainda não façam parte da lista atual.
            if (field.type === 'select' && values[field.key] && !Array.from(el.options).some((option) => option.value === values[field.key])) {
                el.add(new Option(values[field.key], values[field.key]));
            }
            el.value = values[field.key];
        }
        if (field.type === 'image' && values[field.key]) {
            const preview = form.querySelector(`[data-bo-preview="${field.key}"]`);
            if (preview) {
                preview.src = values[field.key];
                preview.style.display = 'block';
            }
        }
    });

    // Recria o botão "Salvar" a cada abertura para não acumular listeners antigos
    const oldSaveBtn = document.getElementById('boFormModalSave');
    const saveBtn = oldSaveBtn.cloneNode(true);
    oldSaveBtn.parentNode.replaceChild(saveBtn, oldSaveBtn);
    saveBtn.textContent = 'Salvar';

    let confirmStep = 0;
    saveBtn.addEventListener('click', async () => {
        if (options.doubleConfirm && confirmStep === 0) {
            confirmStep = 1;
            saveBtn.textContent = 'Clique novamente para confirmar';
            return;
        }

        if (!form.reportValidity()) return;

        if (schemaKey === 'perfilEdit') {
            const profileValues = {};
            fields.forEach((field) => {
                const input = form.querySelector(`[data-bo-field="${field.key}"]`);
                profileValues[field.key] = input ? input.value.trim() : '';
            });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';

            try {
                const response = await fetch(BO_PROFILE_UPDATE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...profileValues, csrf_token: BO_CSRF_TOKEN }),
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Não foi possível atualizar o perfil.');

                Object.assign(BO_CURRENT_USER, profileValues);
                document.getElementById('boProfileName').textContent = profileValues.nome;
                document.getElementById('boProfileEmail').textContent = profileValues.email;
                const gender = document.getElementById('boProfileGender');
                if (gender) gender.textContent = `Gênero: ${profileValues.genero.charAt(0).toUpperCase()}${profileValues.genero.slice(1)}`;
                document.getElementById('boAvatar').textContent = profileValues.nome.charAt(0).toUpperCase();

                boFormModalInstance.hide();
                boToast(result.message);
            } catch (error) {
                boToast(error.message);
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar';
            }
            return;
        }

        boFormModalInstance.hide();
        boToast('Alterações salvas.');
    });

    boFormModalInstance.show();
}

/**
 * Mostra um aviso flutuante (toast) no canto inferior direito por ~2,5s.
 */
function boToast(msg) {
    const toast = document.getElementById('boToast');
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(window._boToastTimer);
    window._boToastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
}

/**
 * Reconstrói os itens do menu lateral (#boNav) de acordo com o perfil
 * ativo (boPerfilAtual), marcando o item da seção atual como "active".
 */
function boRenderSidebar() {
    const nav = document.getElementById('boNav');
    nav.innerHTML = '';
    BO_PERFIS[boPerfilAtual].menus.forEach((item) => {
        const itemElement = document.createElement(item.href ? 'a' : 'button');
        if (item.href) {
            itemElement.href = item.href;
        } else {
            itemElement.type = 'button';
            itemElement.addEventListener('click', () => boGoToSection(item.key));
        }
        itemElement.className = 'bo-nav-item' + (item.key === boSectionAtual ? ' active' : '');
        itemElement.setAttribute('data-section', item.key);
        itemElement.innerHTML = `<i class="bi ${item.icon}"></i><span>${item.label}</span>`;
        nav.appendChild(itemElement);
    });
}

/**
 * Reconstrói o dropdown "Administrador / Profissional / Aluno" do header,
 * marcando o perfil ativo. (Esse seletor existe só para navegar entre as
 * 3 visões durante o desenvolvimento/testes do backoffice.)
 */
function boRenderPerfilMenu() {
    // Só o admin vê/usa o seletor de perfil (ver header.php) — pra qualquer
    // outro perfil, #boPerfilMenu existe só como placeholder vazio (.d-none).
    if (!BO_IS_ADMIN) return;

    const menu = document.getElementById('boPerfilMenu');
    menu.innerHTML = '';
    Object.keys(BO_PERFIS).forEach((key) => {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item' + (key === boPerfilAtual ? ' active' : '');
        link.setAttribute('data-perfil', key);
        link.textContent = BO_PERFIS[key].label;
        li.appendChild(link);
        menu.appendChild(li);
    });
}

/* ---------- Perfil e busca global ---------- */
const BO_SEARCH_ALIASES = {
    dashboard: ['início', 'inicio', 'visão geral', 'resumo'],
    perfil: ['meu perfil', 'conta', 'dados cadastrais', 'editar perfil'],
    historico: ['histórico', 'historico', 'pagamentos', 'movimentações', 'movimentacoes'],
    cashback: ['saldo', 'benefícios', 'beneficios'],
    compras: ['minhas compras', 'pedidos', 'compras', 'histórico de compras'],
    treino: ['treinos', 'exercícios', 'exercicios', 'ficha'],
    agenda: ['agenda', 'agendamentos', 'horários', 'horarios'],
    configuracoes: ['configurações', 'configuracoes', 'ajustes', 'tema', 'conta'],
    profissionais: ['profissionais', 'equipe', 'personal trainer', 'nutricionista'],
    usuarios: ['usuários', 'usuarios', 'alunos'],
    planos: ['planos', 'assinaturas'],
    pagamentos: ['pagamentos', 'financeiro'],
};

let boSearchItems = [];
let boSearchActiveIndex = -1;
let boSearchDebounceTimer = null;

function boNormalizeSearch(value) {
    return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function boCloseSearch() {
    const results = document.getElementById('boSearchResults');
    const input = document.getElementById('boHeaderSearch');
    if (!results || !input) return;
    results.hidden = true;
    results.innerHTML = '';
    input.setAttribute('aria-expanded', 'false');
    clearTimeout(boSearchDebounceTimer);
    boSearchItems = [];
    boSearchActiveIndex = -1;
}

function boGetSearchPages() {
    const pages = BO_PERFIS[boPerfilAtual].menus.map((item) => ({
        type: 'page',
        key: item.key,
        href: item.href,
        title: item.label,
        subtitle: item.href ? 'Abrir Marketplace' : 'Página do painel',
        icon: item.icon,
        terms: [item.label, ...(BO_SEARCH_ALIASES[item.key] || [])],
    }));

    if (!pages.some((page) => page.key === 'perfil')) {
        pages.unshift({
            type: 'page', key: 'perfil', title: 'Meu perfil', subtitle: 'Dados da sua conta', icon: 'bi-person-circle',
            terms: ['perfil', ...(BO_SEARCH_ALIASES.perfil || [])],
        });
    }

    return pages;
}

function boBuildSearchResult(result, index) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'bo-search-result' + (index === boSearchActiveIndex ? ' is-active' : '');
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', index === boSearchActiveIndex ? 'true' : 'false');
    button.innerHTML = `<i class="bi ${result.icon}"></i><span><strong></strong><small></small></span>`;
    button.querySelector('strong').textContent = result.title;
    button.querySelector('small').textContent = result.subtitle;
    button.addEventListener('click', () => boOpenSearchResult(result));
    return button;
}

function boRenderSearch(query) {
    const resultsBox = document.getElementById('boSearchResults');
    const input = document.getElementById('boHeaderSearch');
    if (!resultsBox || !input) return;

    const term = boNormalizeSearch(query);
    if (!term) {
        boCloseSearch();
        return;
    }

    const pages = boGetSearchPages().filter((page) => boNormalizeSearch(page.terms.join(' ')).includes(term));
    const professionals = (typeof BO_PROFISSIONAIS_SEARCH !== 'undefined' ? BO_PROFISSIONAIS_SEARCH : [])
        .filter((professional) => boNormalizeSearch(`${professional.nome} ${professional.funcao} ${professional.especialidade}`).includes(term))
        .map((professional) => ({
            type: 'professional', id: professional.id, title: professional.nome,
            subtitle: professional.especialidade || professional.funcao, icon: 'bi-person-badge',
        }));

    boSearchItems = [...professionals, ...pages];
    boSearchActiveIndex = boSearchItems.length ? 0 : -1;
    resultsBox.innerHTML = '';

    const appendGroup = (label, group, offset) => {
        if (!group.length) return;
        const heading = document.createElement('span');
        heading.className = 'bo-search-group-label';
        heading.textContent = label;
        resultsBox.appendChild(heading);
        group.forEach((item, index) => resultsBox.appendChild(boBuildSearchResult(item, offset + index)));
    };

    if (boSearchItems.length) {
        appendGroup('Profissionais', professionals, 0);
        appendGroup('Páginas', pages, professionals.length);
    } else {
        const empty = document.createElement('span');
        empty.className = 'bo-search-empty';
        empty.textContent = 'Nenhum resultado encontrado';
        resultsBox.appendChild(empty);
    }

    resultsBox.hidden = false;
    input.setAttribute('aria-expanded', 'true');
}

function boOpenSearchResult(result) {
    if (result.href) {
        window.location.assign(result.href);
    } else if (result.type === 'professional') {
        boShowProfessional(result.id);
    } else {
        boGoToSection(result.key);
    }
    document.getElementById('boHeaderSearch').value = '';
    boCloseSearch();
}

function boOpenProfileEdit() {
    boOpenForm('perfilEdit', 'Editar perfil', typeof BO_CURRENT_USER !== 'undefined' ? BO_CURRENT_USER : {});
}

function boShowProfessional(id, updateRoute = true) {
    const professional = (typeof BO_PROFISSIONAIS_SEARCH !== 'undefined' ? BO_PROFISSIONAIS_SEARCH : [])
        .find((item) => Number(item.id) === Number(id));
    if (!professional) {
        boGoToSection(BO_PERFIS[boPerfilAtual].menus[0].key, false);
        return false;
    }

    let section = document.getElementById('boProfessionalProfileSection');
    if (!section) {
        section = document.createElement('section');
        section.id = 'boProfessionalProfileSection';
        section.className = 'bo-content-section';
        document.querySelector('.bo-main').appendChild(section);
    }
    section.innerHTML = '<div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-badge"></i> Profissional</span><h1></h1><p></p></div></div><div class="bo-settings-card bo-profile-settings"><div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person-workspace"></i></span><div><h2></h2><p></p></div></div></div>';
    section.querySelector('.bo-page-title h1').textContent = professional.nome;
    section.querySelector('.bo-page-title p').textContent = 'Perfil profissional disponível no painel ONE FIT.';
    section.querySelector('.bo-settings-heading h2').textContent = professional.funcao;
    section.querySelector('.bo-settings-heading p').textContent = professional.especialidade || professional.funcao;

    document.querySelectorAll('.bo-content-section').forEach((item) => item.classList.remove('active'));
    section.classList.add('active');
    boSectionAtual = 'profissional';
    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => btn.classList.remove('active'));
    document.getElementById('boSidebar').classList.remove('active');
    document.getElementById('boSidebarBackdrop').classList.remove('active');

    if (updateRoute) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', 'profissional');
        url.searchParams.set('profissional', professional.id);
        window.history.pushState({}, '', url);
    }
    return true;
}

/**
 * Troca a seção visível dentro do perfil atual. Procura uma
 * <section data-perfil="X" data-section="Y"> já pronta no HTML; se não
 * existir, mostra a seção de fallback "Em construção" com o título certo.
 */
function boGoToSection(sectionKey, updateRoute = true) {
    const isSpecialSection = sectionKey === 'configuracoes' || sectionKey === 'perfil';
    if (!isSpecialSection && !BO_PERFIS[boPerfilAtual].menus.some((item) => item.key === sectionKey)) return;
    boSectionAtual = sectionKey;

    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => {
        btn.classList.toggle('active', btn.getAttribute('data-section') === sectionKey);
    });

    const prebuilt = sectionKey === 'configuracoes'
        ? document.getElementById('boSettingsSection')
        : sectionKey === 'perfil'
            ? document.getElementById('boProfileSection')
            : document.querySelector(`.bo-content-section[data-perfil="${boPerfilAtual}"][data-section="${sectionKey}"]`);

    document.querySelectorAll('.bo-content-section').forEach((section) => section.classList.remove('active'));

    if (prebuilt) {
        prebuilt.classList.add('active');
    } else {
        const item = BO_PERFIS[boPerfilAtual].menus.find((m) => m.key === sectionKey);
        document.getElementById('boStubTitle').textContent = item ? item.label : '';
        document.getElementById('boStubDesc').textContent = 'Esta tela ainda será detalhada para o perfil ' + BO_PERFIS[boPerfilAtual].label + '.';
        document.getElementById('boStubIcon').className = 'bi ' + (item ? item.icon : 'bi-hourglass-split');
        document.getElementById('boStubSection').classList.add('active');
    }

    // Fecha a sidebar mobile ao navegar (não faz nada se já estiver fechada/desktop)
    document.getElementById('boSidebar').classList.remove('active');
    document.getElementById('boSidebarBackdrop').classList.remove('active');

    if (updateRoute) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', sectionKey);
        url.searchParams.delete('profissional');
        window.history.pushState({}, '', url);
    }
}

/**
 * Troca o perfil ativo (admin/profissional/aluno), atualiza header,
 * remonta o menu lateral e abre a primeira seção do novo perfil.
 */
function boTrocarPerfil(perfilKey) {
    // Segunda camada de proteção: mesmo que alguém force a chamada dessa
    // função pelo console do navegador, só o admin consegue trocar de perfil.
    // A proteção "de verdade" é o servidor só mandar o HTML das seções que
    // o tipo_usuario da sessão tem direito a ver (ver dashboard.php).
    if (!BO_IS_ADMIN) return;
    if (!BO_PERFIS[perfilKey] || perfilKey === boPerfilAtual) return;

    boPerfilAtual = perfilKey;
    document.getElementById('boPerfilLabel').textContent = BO_PERFIS[perfilKey].label;
    document.getElementById('boAvatar').textContent = BO_PERFIS[perfilKey].label.charAt(0);

    boRenderSidebar();
    boRenderPerfilMenu();
    boGoToSection(BO_PERFIS[perfilKey].menus[0].key);
}

/* ---------- Inicialização geral (menu, sidebar mobile, filtros, ações de tabela) ---------- */
document.addEventListener('DOMContentLoaded', () => {
    boFormModalInstance = new bootstrap.Modal(document.getElementById('boFormModal'));

    boRenderSidebar();
    boRenderPerfilMenu();
    // A primeira seção depende do perfil: admin/profissional começam em
    // "dashboard", mas o Aluno não tem essa chave — o dele é "perfil".
    // Por isso pegamos sempre o primeiro item do MENU DO PERFIL ATUAL,
    // em vez de um valor fixo.
    const routeParams = new URLSearchParams(window.location.search);
    const routeSection = routeParams.get('section');
    const routeProfessional = routeParams.get('profissional');
    if (routeSection === 'profissional' && routeProfessional) {
        boShowProfessional(routeProfessional, false);
    } else {
        const initialSection = (routeSection === 'perfil' || routeSection === 'configuracoes' || BO_PERFIS[boPerfilAtual].menus.some((item) => item.key === routeSection))
            ? routeSection
            : BO_PERFIS[boPerfilAtual].menus[0].key;
        boGoToSection(initialSection, false);
    }

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('onefit-theme', theme); } catch (e) { /* armazenamento indisponível */ }
        try { document.cookie = 'onefit_theme=' + theme + '; path=/; max-age=31536000'; } catch (e) { /* cookie indisponível */ }
        document.querySelectorAll('[data-bo-theme]').forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-bo-theme') === theme);
        });
    };

    let savedTheme = 'dark';
    try { savedTheme = localStorage.getItem('onefit-theme') || 'dark'; } catch (e) { /* usa o padrão escuro */ }
    applyTheme(savedTheme === 'light' ? 'light' : 'dark');
    document.querySelectorAll('[data-bo-theme]').forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.getAttribute('data-bo-theme')));
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.getElementById('boHeaderSearch')?.focus();
        }
    });

    const searchInput = document.getElementById('boHeaderSearch');
    const searchWrap = document.getElementById('boHeaderSearchWrap');
    searchInput.addEventListener('input', () => {
        clearTimeout(boSearchDebounceTimer);
        boSearchDebounceTimer = setTimeout(() => boRenderSearch(searchInput.value), 180);
    });
    searchInput.addEventListener('search', () => {
        if (!searchInput.value) boCloseSearch();
    });
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            boCloseSearch();
            searchInput.blur();
            return;
        }
        if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key) || !boSearchItems.length) return;
        event.preventDefault();
        if (event.key === 'Enter') {
            boOpenSearchResult(boSearchItems[boSearchActiveIndex < 0 ? 0 : boSearchActiveIndex]);
            return;
        }
        const nextIndex = event.key === 'ArrowDown'
            ? (boSearchActiveIndex + 1) % boSearchItems.length
            : (boSearchActiveIndex - 1 + boSearchItems.length) % boSearchItems.length;
        boRenderSearch(searchInput.value);
        // boRenderSearch seleciona o primeiro resultado por padrão; restaura
        // a seleção escolhida pelo teclado para manter a navegação previsível.
        boSearchActiveIndex = nextIndex;
        document.querySelectorAll('.bo-search-result').forEach((button, index) => {
            const active = index === boSearchActiveIndex;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    });

    const avatar = document.getElementById('boAvatar');
    const userMenu = document.getElementById('boUserMenu');
    const userMenuWrap = document.getElementById('boUserMenuWrap');
    const closeUserMenu = () => {
        userMenu.classList.remove('is-open');
        userMenu.setAttribute('aria-hidden', 'true');
        avatar.setAttribute('aria-expanded', 'false');
    };
    avatar.addEventListener('click', () => {
        const willOpen = !userMenu.classList.contains('is-open');
        userMenu.classList.toggle('is-open', willOpen);
        userMenu.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
        avatar.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!searchWrap.contains(event.target)) boCloseSearch();
        if (!userMenuWrap.contains(event.target)) closeUserMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeUserMenu();
    });

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('section') === 'profissional' && params.get('profissional')) {
            boShowProfessional(params.get('profissional'), false);
        } else {
            boGoToSection(params.get('section') || BO_PERFIS[boPerfilAtual].menus[0].key, false);
        }
    });

    // Clique num item do dropdown de perfil (header) -> troca de perfil.
    // #boPerfilMenu só existe quando o seletor de perfil está no header
    // (hoje BO_IS_ADMIN é sempre false, então o elemento nem é renderizado).
    const perfilMenuEl = document.getElementById('boPerfilMenu');
    if (perfilMenuEl) {
        perfilMenuEl.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-perfil]');
            if (!link) return;
            event.preventDefault();
            boTrocarPerfil(link.getAttribute('data-perfil'));
        });
    }

    // Botão hambúrguer (mobile) abre/fecha a sidebar; clicar fora também fecha
    const sidebar = document.getElementById('boSidebar');
    const backdrop = document.getElementById('boSidebarBackdrop');
    const toggle = document.getElementById('boSidebarToggle');

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    });
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('active');
        backdrop.classList.remove('active');
    });

    /* ---- Filtros de tabela ----
       Para cada <table data-bo-table="X">, procura os inputs/selects com
       data-bo-target="X" (busca, status, tipo, data-de, data-até) e
       esconde/mostra as linhas conforme os atributos data-* de cada <tr>
       (data-search, data-status, data-type, data-date) definidos no PHP. */
    document.querySelectorAll('[data-bo-table]').forEach((table) => {
        const filterId = table.getAttribute('data-bo-table');
        const searchInput = document.querySelector(`[data-bo-filter="search"][data-bo-target="${filterId}"]`);
        const statusSelect = document.querySelector(`[data-bo-filter="status"][data-bo-target="${filterId}"]`);
        const typeSelect = document.querySelector(`[data-bo-filter="type"][data-bo-target="${filterId}"]`);
        const dateFrom = document.querySelector(`[data-bo-filter="date-from"][data-bo-target="${filterId}"]`);
        const dateTo = document.querySelector(`[data-bo-filter="date-to"][data-bo-target="${filterId}"]`);
        const emptyRow = table.querySelector('.bo-empty-row');

        const applyFilters = () => {
            const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const status = statusSelect ? statusSelect.value : '';
            const type = typeSelect ? typeSelect.value : '';
            const from = dateFrom ? dateFrom.value : '';
            const to = dateTo ? dateTo.value : '';
            let visibleCount = 0;

            table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => {
                const haystack = row.getAttribute('data-search') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const rowType = row.getAttribute('data-type') || '';
                const rowDate = row.getAttribute('data-date') || '';

                const matchesTerm = term === '' || haystack.toLowerCase().includes(term);
                const matchesStatus = status === '' || rowStatus === status;
                const matchesType = type === '' || rowType === type;
                const matchesFrom = from === '' || rowDate === '' || rowDate >= from;
                const matchesTo = to === '' || rowDate === '' || rowDate <= to;

                const visible = matchesTerm && matchesStatus && matchesType && matchesFrom && matchesTo;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount += 1;
            });

            if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        };

        [searchInput, statusSelect, typeSelect, dateFrom, dateTo].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });
    });

    /* ---- Ações de tabela (delegadas no <body> pois as linhas são dinâmicas) ---- */
    document.body.addEventListener('click', (event) => {

        // Pausar/Ativar — usado só pelas telas sem persistência real (ex:
        // agenda do profissional). No admin isso agora é um <form> em PHP
        // (ver includes/admin-forms.php), sem passar por aqui.
        const toggleBtn = event.target.closest('[data-bo-action="toggle-status"]');
        if (toggleBtn) {
            const row = toggleBtn.closest('tr');
            const badge = row.querySelector('.bo-badge');
            const active = badge.classList.contains('bo-badge-active');
            const onLabel = toggleBtn.getAttribute('data-on') || 'Ativo';
            const offLabel = toggleBtn.getAttribute('data-off') || 'Inativo';

            badge.classList.toggle('bo-badge-active', !active);
            badge.classList.toggle('bo-badge-inactive', active);
            badge.textContent = active ? offLabel : onLabel;
            row.setAttribute('data-status', active ? 'inativo' : 'ativo');
            toggleBtn.innerHTML = `<i class="bi ${active ? 'bi-play-circle' : 'bi-pause-circle'}"></i>`;
            toggleBtn.title = active ? 'Ativar' : 'Pausar/Inativar';
        }

        // Excluir — usado só pelas telas sem persistência real (ex: ficha de
        // treino do aluno). No admin isso agora é um modal Bootstrap centralizado
        // (ver bo_botao_excluir/bo_modal_confirmar_exclusao em includes/admin-forms.php).
        const deleteBtn = event.target.closest('[data-bo-action="delete"]');
        if (deleteBtn) {
            const label = deleteBtn.getAttribute('data-bo-name') || 'este registro';
            if (window.confirm(`Tem certeza que deseja excluir ${label}?`)) {
                deleteBtn.closest('tr').remove();
                boToast('Registro excluído.');
            }
        }

        // Limpar tabela inteira (ex: "Limpar Treino")
        const clearBtn = event.target.closest('[data-bo-action="clear-table"]');
        if (clearBtn) {
            const tableSel = clearBtn.getAttribute('data-bo-target-table');
            const table = document.querySelector(`[data-bo-table="${tableSel}"]`);
            if (table && window.confirm('Tem certeza que deseja limpar todos os itens?')) {
                table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => row.remove());
                const emptyRow = table.querySelector('.bo-empty-row');
                if (emptyRow) emptyRow.style.display = '';
                boToast('Lista limpa.');
            }
        }

        // Remover card de agenda (horário agendado ou disponível)
        const removeCardBtn = event.target.closest('[data-bo-remove-card]');
        if (removeCardBtn) {
            if (window.confirm('Remover este horário?')) {
                removeCardBtn.closest('.bo-agenda-card').remove();
            }
        }

        // Exportar tabela visível para CSV
        const exportBtn = event.target.closest('[data-bo-export]');
        if (exportBtn) {
            boExportTableCsv(exportBtn.getAttribute('data-bo-export'));
        }
    });
});

/* ---------- Cálculo de IMC (tela "Perfil" do aluno) ---------- */
function boCalcularIMC() {
    const altura = parseFloat(document.getElementById('imcAltura').value);
    const peso = parseFloat(document.getElementById('imcPeso').value);
    const resultado = document.getElementById('imcResultado');

    if (!altura || !peso) {
        resultado.textContent = 'Informe altura e peso.';
        return;
    }

    const imc = peso / (altura * altura);
    let status = 'Normal';
    if (imc < 18.5) status = 'Abaixo do peso';
    else if (imc >= 25 && imc < 30) status = 'Sobrepeso';
    else if (imc >= 30) status = 'Obesidade';

    resultado.textContent = imc.toFixed(1) + ' · ' + status;
}

/* ---------- Modal "Pagar plano" (Pix simulado / cartão) ---------- */
document.addEventListener('DOMContentLoaded', () => {
    const painelPix = document.getElementById('painelPix');
    const painelCartao = document.getElementById('painelCartao');
    const metodoPix = document.getElementById('metodoPix');

    // Alterna entre o painel de Pix e o painel de cartão conforme o método escolhido
    document.querySelectorAll('input[name="metodoPagamento"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const isPix = metodoPix.checked;
            painelPix.style.display = isPix ? 'block' : 'none';
            painelCartao.style.display = isPix ? 'none' : 'block';
        });
    });

    // "Gerar QR Code" apenas revela o bloco simulado (não gera Pix real)
    const btnGerarQr = document.getElementById('btnGerarQr');
    if (btnGerarQr) {
        btnGerarQr.addEventListener('click', () => {
            document.getElementById('pixResultado').style.display = 'block';
        });
    }

    // Copia o código "copia e cola" do Pix para a área de transferência
    const btnCopiarPix = document.getElementById('btnCopiarPix');
    if (btnCopiarPix) {
        btnCopiarPix.addEventListener('click', () => {
            const campo = document.getElementById('pixCopiaCola');
            campo.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(campo.value).then(() => boToast('Código Pix copiado.'));
            } else {
                document.execCommand('copy');
                boToast('Código Pix copiado.');
            }
        });
    }

    // "Pagar" apenas fecha o modal e mostra o toast (pagamento simulado)
    const btnPagar = document.getElementById('btnPagar');
    if (btnPagar) {
        btnPagar.addEventListener('click', () => {
            const modalEl = document.getElementById('modalPagarPlano');
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            boToast('Pagamento simulado com sucesso!');
        });
    }
});

/**
 * Exporta as linhas visíveis (não filtradas) de uma tabela para um
 * arquivo .csv baixado pelo navegador.
 */
function boExportTableCsv(tableId) {
    const table = document.querySelector(`[data-bo-table="${tableId}"]`);
    if (!table) return;

    const rows = [];
    table.querySelectorAll('thead tr').forEach((tr) => {
        const cols = Array.from(tr.querySelectorAll('th')).map((th) => `"${th.textContent.trim()}"`);
        rows.push(cols.join(';'));
    });
    table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((tr) => {
        if (tr.style.display === 'none') return; // não exporta linhas escondidas pelo filtro
        const cols = Array.from(tr.querySelectorAll('td')).map((td) => `"${td.textContent.trim().replace(/\s+/g, ' ')}"`);
        rows.push(cols.join(';'));
    });

    const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = tableId + '.csv';
    link.click();
    boToast('Exportação gerada.');
}
// As duas tabelas e o total são renderizados a partir da mesma consulta autenticada.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bo-compras]').forEach((section) => {
        const search = section.querySelector('[data-compras-busca]');
        const status = section.querySelector('[data-compras-status]');
        const results = section.querySelector('[data-compras-resultados]');
        const feedback = section.querySelector('[data-compras-feedback]');
        let timer;
        let controller;
        let version = 0;
        const schedule = (delay) => {
            clearTimeout(timer);
            if (controller) controller.abort();
            const current = ++version;
            results.hidden = true;
            results.setAttribute('aria-busy', 'true');
            feedback.hidden = false;
            feedback.textContent = 'Carregando compras…';
            timer = setTimeout(async () => {
                controller = new AbortController();
                const url = new URL(section.dataset.endpoint, window.location.href);
                url.searchParams.set('busca', search.value.trim());
                url.searchParams.set('status', status.value);
                try {
                    const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.error || 'Não foi possível carregar as compras.');
                    if (current !== version) return;
                    results.innerHTML = data.html;
                    results.hidden = false;
                    feedback.textContent = `${data.total} pedido(s) encontrado(s).`;
                } catch (error) {
                    if (current !== version || error.name === 'AbortError') return;
                    feedback.textContent = `${error.message} Altere a busca ou o status para tentar novamente.`;
                } finally {
                    if (current === version) results.setAttribute('aria-busy', 'false');
                }
            }, delay);
        };
        search.addEventListener('input', () => schedule(250));
        search.addEventListener('search', () => schedule(0));
        status.addEventListener('change', () => schedule(0));
    });
});

```

ARQUIVO: pages/dashboard/includes/db-data.php

CODIGO COMPLETO:

```php
<?php
require_once __DIR__ . '/compras.php';
/**
 * includes/db-data.php
 * Substitui includes/mock-data.php: monta as mesmas variáveis que os
 * components/section-admin.php, section-profissional.php e
 * section-aluno.php esperam, só que lendo do banco de verdade em vez de
 * usar dados fictícios. Cada bloco só roda para o perfil correspondente
 * ($perfilLogado), evitando consultas (e exposição de dados) desnecessárias.
 *
 * Depende de $conn (config/conn.php) e $perfilLogado/$_SESSION['id_usuario']
 * (já carregados em dashboard.php antes deste require).
 */

/**
 * Mapeia o status de pagamento/matrícula do banco para um rótulo em pt-BR.
 */
function bo_label_status_pagamento(string $status): string
{
    $labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Pago',
        'recusado' => 'Recusado',
        'cancelado' => 'Cancelado',
        'atrasado' => 'Atrasado',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function bo_label_forma_pagamento(string $forma): string
{
    $labels = ['pix' => 'PIX', 'cartao' => 'Cartão', 'cashback' => 'Cashback'];
    return $labels[$forma] ?? ucfirst($forma);
}

function bo_ciclo_por_duracao(?int $dias): string
{
    if ($dias === null) return '—';
    if ($dias <= 31) return 'Mensal';
    if ($dias <= 93) return 'Trimestral';
    if ($dias <= 186) return 'Semestral';
    return 'Anual';
}

/* =======================================================================
 * PERFIL: ADMINISTRADOR
 * ===================================================================== */
if ($perfilLogado === 'admin') {

    // Cards da tela "Dashboard"
    $admDashboard = [
        'usuariosAtivos' => 0, 'usuariosNovosMes' => 0,
        'saldoDia' => 0, 'saldoSemana' => 0, 'saldoMes' => 0, 'saldoAno' => 0,
        'cashbackDia' => 0, 'cashbackSemana' => 0, 'cashbackMes' => 0, 'cashbackAno' => 0,
        'acessosLiberados' => 0, 'acessosBloqueados' => 0, 'totalUsuarios' => 0,
        'profissionaisAtivos' => 0, 'profissionaisPendentes' => 0,
    ];

    if ($r = $conn->query("SELECT
            SUM(status = 'ativo') AS usuarios_ativos,
            SUM(status != 'bloqueado') AS acessos_liberados,
            SUM(status = 'bloqueado') AS acessos_bloqueados,
            SUM(data_cadastro >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS usuarios_novos_mes,
            COUNT(*) AS total
        FROM usuarios")) {
        $row = $r->fetch_assoc();
        $admDashboard['usuariosAtivos'] = (int) $row['usuarios_ativos'];
        $admDashboard['acessosLiberados'] = (int) $row['acessos_liberados'];
        $admDashboard['acessosBloqueados'] = (int) $row['acessos_bloqueados'];
        $admDashboard['usuariosNovosMes'] = (int) $row['usuarios_novos_mes'];
        $admDashboard['totalUsuarios'] = (int) $row['total'];
    }

    if ($r = $conn->query("SELECT
            SUM(CASE WHEN DATE(data_pagamento) = CURDATE() THEN valor ELSE 0 END) AS dia,
            SUM(CASE WHEN YEARWEEK(data_pagamento, 1) = YEARWEEK(CURDATE(), 1) THEN valor ELSE 0 END) AS semana,
            SUM(CASE WHEN YEAR(data_pagamento) = YEAR(CURDATE()) AND MONTH(data_pagamento) = MONTH(CURDATE()) THEN valor ELSE 0 END) AS mes,
            SUM(CASE WHEN YEAR(data_pagamento) = YEAR(CURDATE()) THEN valor ELSE 0 END) AS ano
        FROM pagamento WHERE status = 'aprovado'")) {
        $row = $r->fetch_assoc();
        $admDashboard['saldoDia'] = (float) $row['dia'];
        $admDashboard['saldoSemana'] = (float) $row['semana'];
        $admDashboard['saldoMes'] = (float) $row['mes'];
        $admDashboard['saldoAno'] = (float) $row['ano'];
    }

    if ($r = $conn->query("SELECT
            SUM(CASE WHEN DATE(data_criacao) = CURDATE() THEN valor ELSE 0 END) AS dia,
            SUM(CASE WHEN YEARWEEK(data_criacao, 1) = YEARWEEK(CURDATE(), 1) THEN valor ELSE 0 END) AS semana,
            SUM(CASE WHEN YEAR(data_criacao) = YEAR(CURDATE()) AND MONTH(data_criacao) = MONTH(CURDATE()) THEN valor ELSE 0 END) AS mes,
            SUM(CASE WHEN YEAR(data_criacao) = YEAR(CURDATE()) THEN valor ELSE 0 END) AS ano
        FROM cashback WHERE tipo = 'credito'")) {
        $row = $r->fetch_assoc();
        $admDashboard['cashbackDia'] = (float) $row['dia'];
        $admDashboard['cashbackSemana'] = (float) $row['semana'];
        $admDashboard['cashbackMes'] = (float) $row['mes'];
        $admDashboard['cashbackAno'] = (float) $row['ano'];
    }

    if ($r = $conn->query("SELECT
            SUM(status = 'ativo') AS ativos,
            SUM(status = 'inativo') AS pendentes
        FROM cadastro_profissional")) {
        $row = $r->fetch_assoc();
        $admDashboard['profissionaisAtivos'] = (int) $row['ativos'];
        $admDashboard['profissionaisPendentes'] = (int) $row['pendentes'];
    }

    // Tela "Usuários"
    $usuarios = [];
    $sql = "SELECT u.id_usuario, u.nome, u.email, u.cpf, u.status,
                   u.celular, u.genero, u.data_nascimento, u.nacionalidade, u.endereco, u.cidade_estado,
                   m.id_matricula, m.data_inicio, m.data_fim, m.id_plano, pl.nome AS plano_nome
            FROM usuarios u
            LEFT JOIN matricula m ON m.id_matricula = (
                SELECT id_matricula FROM matricula
                WHERE id_usuario = u.id_usuario
                ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1
            )
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
            ORDER BY u.nome";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $cidadeEstadoUsr = explode('/', $row['cidade_estado'] ?? '', 2);
            $usuarios[] = [
                'id' => (int) $row['id_usuario'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'cpf' => $row['cpf'],
                'status' => $row['status'] === 'ativo' ? 'ativo' : 'inativo',
                'matricula' => $row['id_matricula'] ? 'MAT-' . str_pad($row['id_matricula'], 4, '0', STR_PAD_LEFT) : '—',
                'dataInicial' => $row['data_inicio'] ?: '',
                'dataFinal' => $row['data_fim'] ?: '',
                'idPlano' => $row['id_plano'] ? (int) $row['id_plano'] : null,
                'plano' => $row['plano_nome'] ?: '—',
                'acesso' => $row['status'] === 'bloqueado' ? 'Bloqueado' : 'Liberado',
                'observacao' => '',
                'celular' => $row['celular'],
                'genero' => $row['genero'],
                'nascimento' => $row['data_nascimento'],
                'nacionalidade' => $row['nacionalidade'],
                'endereco' => $row['endereco'],
                'cidade' => trim($cidadeEstadoUsr[0] ?? ''),
                'estado' => trim($cidadeEstadoUsr[1] ?? ''),
            ];
        }
    }

    // Tela "Funções" — tabela `funcao`
    $funcoes = [];
    if ($r = $conn->query('SELECT id_funcao, nome FROM funcao ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $funcoes[] = ['id' => (int) $row['id_funcao'], 'nome' => $row['nome']];
        }
    }

    // Tela "Permissões" — tabela `permissoes` (email + função concedida)
    $permissoes = [];
    $sql = "SELECT pe.id_permissao, pe.nome, pe.email, pe.funcao AS id_funcao, f.nome AS funcaoLabel
            FROM permissoes pe
            LEFT JOIN funcao f ON f.id_funcao = pe.funcao
            ORDER BY pe.nome";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $permissoes[] = [
                'id' => (int) $row['id_permissao'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'id_funcao' => (int) $row['id_funcao'],
                'funcaoLabel' => $row['funcaoLabel'] ?? '—',
            ];
        }
    }

    // Tela "Pagamentos"
    $pagamentos = [];
    $sql = "SELECT p.id_pagamento, COALESCE(p.data_pagamento, p.data_vencimento) AS data, p.forma_pagamento, p.valor, p.status, m.id_usuario
            FROM pagamento p
            JOIN matricula m ON m.id_matricula = p.id_matricula
            ORDER BY data DESC";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $pagamentos[] = [
                'id' => (int) $row['id_pagamento'],
                'data' => $row['data'],
                'tipo' => bo_label_forma_pagamento($row['forma_pagamento']),
                'valor' => (float) $row['valor'],
                'usuarioId' => (int) $row['id_usuario'],
                'observacao' => bo_label_status_pagamento($row['status']),
            ];
        }
    }

    // Tela "Cashbacks"
    $cashbackResumo = ['saldoTotal' => 0, 'distribuidos' => 0, 'debitado' => 0, 'creditado' => 0];
    if ($r = $conn->query("SELECT
            SUM(CASE WHEN tipo = 'credito' THEN valor ELSE 0 END) AS creditado,
            SUM(CASE WHEN tipo = 'debito' THEN valor ELSE 0 END) AS debitado
        FROM cashback WHERE status != 'cancelado'")) {
        $row = $r->fetch_assoc();
        $cashbackResumo['creditado'] = (float) $row['creditado'];
        $cashbackResumo['distribuidos'] = (float) $row['creditado'];
        $cashbackResumo['debitado'] = (float) $row['debitado'];
        $cashbackResumo['saldoTotal'] = $cashbackResumo['creditado'] - $cashbackResumo['debitado'];
    }

    $cashbackTransacoes = [];
    $sql = "SELECT id_cashback, data_criacao, tipo, valor, id_usuario, descricao FROM cashback ORDER BY data_criacao DESC";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $cashbackTransacoes[] = [
                'id' => (int) $row['id_cashback'],
                'data' => $row['data_criacao'],
                'tipo' => $row['tipo'],
                'valor' => (float) $row['valor'],
                'usuarioId' => (int) $row['id_usuario'],
                'motivo' => $row['descricao'],
            ];
        }
    }

    // Tela "Categorias"
    $categorias = [];
    if ($r = $conn->query('SELECT id_categoria, nome, status FROM categorias ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $categorias[] = ['id' => (int) $row['id_categoria'], 'nome' => $row['nome'], 'status' => $row['status']];
        }
    }
    $categoriasAtivasOptions = array_values(array_map(
        static fn(array $c): string => $c['nome'],
        array_filter($categorias, static fn(array $c): bool => $c['status'] === 'ativo')
    ));

    // Tela "Produtos"
    $produtosResumo = ['total' => 0, 'disponiveis' => 0, 'indisponiveis' => 0];
    if ($r = $conn->query("SELECT COUNT(*) total, SUM(status='ativo') disponiveis, SUM(status='inativo') indisponiveis FROM produtos")) {
        $row = $r->fetch_assoc();
        $produtosResumo['total'] = (int) $row['total'];
        $produtosResumo['disponiveis'] = (int) $row['disponiveis'];
        $produtosResumo['indisponiveis'] = (int) $row['indisponiveis'];
    }

    $produtos = [];
    $sql = "SELECT id_produto, nome, categoria, preco, desconto, cashback_valor, estoque, status, imagem, descricao FROM produtos ORDER BY nome";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $desconto = (float) $row['desconto'];
            $preco = (float) $row['preco'];
            $produtos[] = [
                'id' => (int) $row['id_produto'],
                'nome' => $row['nome'],
                'categoria' => $row['categoria'],
                'preco' => $preco,
                'desconto' => $desconto,
                'valorFinal' => round($preco - ($preco * $desconto / 100), 2),
                'cashback' => (float) $row['cashback_valor'],
                'estoque' => (int) $row['estoque'],
                'status' => $row['status'] === 'ativo' ? 'disponivel' : 'indisponivel',
                'imagem' => $row['imagem'],
                'descricao' => $row['descricao'],
            ];
        }
    }

    // Tela "Vendas Marketplace" (visão agregada de todos os vendedores)
    $admVendasProdutos = bo_carregar_produtos_vendedor($conn, null);
    $admVendas = bo_carregar_vendas_vendedor($conn, null);
    $transportadoras = bo_carregar_transportadoras($conn);

    // Tela "Cadastro de Planos"
    $planos = [];
    $sql = "SELECT id_plano, nome, valor, duracao_dias, descricao, beneficios, status FROM cadastro_planos ORDER BY valor";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $planos[] = [
                'id' => (int) $row['id_plano'],
                'nome' => $row['nome'],
                'valor' => (float) $row['valor'],
                'ciclo' => bo_ciclo_por_duracao((int) $row['duracao_dias']),
                'descricao' => $row['descricao'],
                'beneficios' => $row['beneficios'],
                'status' => $row['status'],
                'textoBotao' => 'Assinar agora',
            ];
        }
    }
    $planosAtivosOptions = array_values(array_map(
        static fn(array $p): string => $p['nome'],
        array_filter($planos, static fn(array $p): bool => $p['status'] === 'ativo')
    ));

    // Tela "Modalidades"
    $modalidadesAdm = [];
    if ($r = $conn->query('SELECT id_modalidade, nome FROM modalidades ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $modalidadesAdm[] = ['id' => (int) $row['id_modalidade'], 'nome' => $row['nome']];
        }
    }
    $modalidadesOptions = array_map(static fn(array $m): string => $m['nome'], $modalidadesAdm);

    // Tela "Profissionais"
    $profissionaisAdm = [];
    try {
        $sql = "SELECT id_profissional, nome, especialidade, modalidades, registro_profissional, status, email, celular, descricao, foto
                FROM cadastro_profissional ORDER BY nome";
        $r = $conn->query($sql);
    } catch (\mysqli_sql_exception $e) {
        // Coluna "modalidades" ainda não existe neste banco (migração
        // modalidades-profissional-migration.sql pendente): consulta sem ela.
        $sql = "SELECT id_profissional, nome, especialidade, registro_profissional, status, email, celular, descricao, foto
                FROM cadastro_profissional ORDER BY nome";
        $r = $conn->query($sql);
    }
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $profissionaisAdm[] = [
                'id' => (int) $row['id_profissional'],
                'nome' => $row['nome'],
                'funcao' => $row['especialidade'],
                'tituloCard' => $row['especialidade'],
                'modalidades' => $row['modalidades'] ?? '',
                'documento' => $row['registro_profissional'],
                'status' => $row['status'],
                'email' => $row['email'],
                'telefone' => $row['celular'],
                'celular' => $row['celular'],
                'descricao' => $row['descricao'],
                'experiencia' => '',
                'foto' => $row['foto'],
                'observacaoInterna' => '',
            ];
        }
    }
}

/* =======================================================================
 * PERFIL: PROFISSIONAL
 * ===================================================================== */
if ($perfilLogado === 'profissional') {

    $idUsuarioLogado = (int) $_SESSION['id_usuario'];
    $idProfissional = null;
    $stmt = $conn->prepare('SELECT id_profissional, status FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $cadProf = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $idProfissional = $cadProf['id_profissional'] ?? null;

    $profContrato = ['status' => $cadProf ? ucfirst($cadProf['status']) : '—', 'validade' => '—', 'saldoCashback' => 0];

    $stmt = $conn->prepare("SELECT
            SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo
        FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $profContrato['saldoCashback'] = (float) ($row['saldo'] ?? 0);

    // Tela "Histórico" — não há tabela de repasses/comissão por competência no banco
    $profHistorico = [];

    // Tela "Alunos" — vinculados por agendamentos já realizados com este profissional
    $profAlunos = [];
    if ($idProfissional) {
        $stmt = $conn->prepare("SELECT DISTINCT u.id_usuario, u.nome, u.celular
                FROM agendamento a
                JOIN usuarios u ON u.id_usuario = a.id_usuario
                WHERE a.id_profissional = ?
                ORDER BY u.nome");
        $stmt->bind_param('i', $idProfissional);
        $stmt->execute();
        $alunosRes = $stmt->get_result();
        while ($aluno = $alunosRes->fetch_assoc()) {
            $stmt2 = $conn->prepare("SELECT m.status, m.valor_contratado, pl.nome AS plano
                    FROM matricula m
                    LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
                    WHERE m.id_usuario = ?
                    ORDER BY m.data_matricula DESC LIMIT 1");
            $stmt2->bind_param('i', $aluno['id_usuario']);
            $stmt2->execute();
            $mat = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            $profAlunos[] = [
                'id' => (int) $aluno['id_usuario'],
                'nome' => $aluno['nome'],
                'contato' => $aluno['celular'],
                'plano' => $mat['plano'] ?? '—',
                'status' => ($mat['status'] ?? '') === 'ativa' ? 'ativo' : 'inativo',
                'valor' => (float) ($mat['valor_contratado'] ?? 0),
                'observacao' => '',
            ];
        }
        $stmt->close();
    }

    // Tela "Agenda"
    $profAgendados = [];
    $profDisponiveis = []; // não há tabela de horários "livres" oferecidos pelo profissional no banco
    if ($idProfissional) {
        $stmt = $conn->prepare("SELECT a.titulo, a.tipo, a.data_evento, a.hora_inicio, u.nome, u.celular
                FROM agendamento a
                JOIN usuarios u ON u.id_usuario = a.id_usuario
                WHERE a.id_profissional = ? AND a.status IN ('agendado','confirmado')
                ORDER BY a.data_evento, a.hora_inicio");
        $stmt->bind_param('i', $idProfissional);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $profAgendados[] = [
                'aluno' => $row['nome'],
                'contato' => $row['celular'],
                'data' => date('d/m/Y', strtotime($row['data_evento'])) . ' ' . substr($row['hora_inicio'], 0, 5),
                'modalidade' => $row['titulo'] ?: ucfirst($row['tipo']),
            ];
        }
        $stmt->close();
    }

    // Tela "Meu cashback"
    $profCashbackHistorico = [];
    $stmt = $conn->prepare('SELECT data_criacao, descricao, valor FROM cashback WHERE id_usuario = ? ORDER BY data_criacao DESC');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $profCashbackHistorico[] = [
            'data' => date('d/m/Y', strtotime($row['data_criacao'])),
            'descricao' => $row['descricao'],
            'valor' => (float) $row['valor'],
        ];
    }
    $stmt->close();

    // Tela "Minhas compras"
    [$profPedidos, $profPedidosHistorico] = bo_carregar_pedidos($conn, $idUsuarioLogado);
}

/* =======================================================================
 * PERFIL: ALUNO
 * ===================================================================== */
if ($perfilLogado === 'aluno') {

    $idUsuarioLogado = (int) $_SESSION['id_usuario'];

    $stmt = $conn->prepare("SELECT m.status, m.valor_contratado, pl.nome AS plano
            FROM matricula m
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
            WHERE m.id_usuario = ?
            ORDER BY m.data_matricula DESC LIMIT 1");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $matriculaAtual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $statusLabel = ['ativa' => 'Ativo', 'pendente' => 'Pendente', 'vencida' => 'Vencido', 'cancelada' => 'Cancelado'];

    $alunoPerfil = [
        'nome' => $usuarioBanco['nome'],
        'email' => $usuarioBanco['email'],
        'plano' => $matriculaAtual['plano'] ?? '—',
        'status' => $statusLabel[$matriculaAtual['status'] ?? ''] ?? '—',
        'documento' => $usuarioBanco['cpf'],
        'telefone' => $usuarioBanco['celular'],
        'dataCadastro' => $usuarioBanco['data_cadastro'] ? date('d/m/Y', strtotime($usuarioBanco['data_cadastro'] ?? '')) : '—',
        'nascimento' => $usuarioBanco['data_nascimento'] ? date('d/m/Y', strtotime($usuarioBanco['data_nascimento'])) : '—',
        'altura' => (float) $usuarioBanco['altura'],
        'peso' => (float) $usuarioBanco['peso'],
        'objetivo' => $usuarioBanco['objetivo'] ?: '',
        'valorContratado' => (float) ($matriculaAtual['valor_contratado'] ?? 0),
    ];

    // Tela "Histórico"
    $alunoHistorico = [];
    $stmt = $conn->prepare("SELECT p.data_pagamento, p.data_vencimento, p.forma_pagamento, p.status, p.valor, p.id_pagamento, pl.nome AS plano
            FROM pagamento p
            JOIN matricula m ON m.id_matricula = p.id_matricula
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
            WHERE m.id_usuario = ?
            ORDER BY COALESCE(p.data_pagamento, p.data_vencimento) DESC");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dataRef = $row['data_pagamento'] ?: $row['data_vencimento'];
        $stmt2 = $conn->prepare('SELECT SUM(valor) total FROM cashback WHERE id_pagamento = ? AND tipo = "credito"');
        $stmt2->bind_param('i', $row['id_pagamento']);
        $stmt2->execute();
        $cb = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $alunoHistorico[] = [
            'data' => date('d/m/Y H:i', strtotime($dataRef)),
            'descricao' => 'Mensalidade ' . ($row['plano'] ?? ''),
            'tipo' => bo_label_forma_pagamento($row['forma_pagamento']),
            'status' => bo_label_status_pagamento($row['status']),
            'valor' => (float) $row['valor'],
            'cashback' => (float) ($cb['total'] ?? 0),
        ];
    }
    $stmt->close();

    // Tela "Cashback"
    $alunoCashbackSaldo = 0;
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo
            FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $alunoCashbackSaldo = (float) ($row['saldo'] ?? 0);

    $alunoCashbackHistorico = [];
    $stmt = $conn->prepare('SELECT data_criacao, tipo, descricao, valor FROM cashback WHERE id_usuario = ? ORDER BY data_criacao DESC');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $alunoCashbackHistorico[] = [
            'data' => date('d/m/Y', strtotime($row['data_criacao'])),
            'tipo' => $row['tipo'],
            'descricao' => $row['descricao'],
            'valor' => (float) $row['valor'],
        ];
    }
    $stmt->close();

    // Tela "Minhas compras"
    [$alunoPedidos, $alunoPedidosHistorico] = bo_carregar_pedidos($conn, $idUsuarioLogado);

    // Tela "Treino" — não há tabela de ficha de treino no banco
    $alunoTreino = [];

    // Tela "Minha agenda" — não há tabela de horários "disponíveis" distinta dos agendamentos
    $alunoAgendaDisponiveis = [];
}

/* =======================================================================
 * PERFIL: VENDEDOR
 * ===================================================================== */
if ($perfilLogado === 'vendedor') {
    $idUsuarioLogado = (int) $_SESSION['id_usuario'];

    $categoriasAtivasOptions = [];
    if ($r = $conn->query("SELECT nome FROM categorias WHERE status = 'ativo' ORDER BY nome")) {
        while ($row = $r->fetch_assoc()) {
            $categoriasAtivasOptions[] = $row['nome'];
        }
    }

    $vendedorProdutos = bo_carregar_produtos_vendedor($conn, $idUsuarioLogado);
    $vendedorProdutosResumo = [
        'total' => count($vendedorProdutos),
        'disponiveis' => count(array_filter($vendedorProdutos, static fn(array $p): bool => $p['status'] === 'disponivel')),
        'indisponiveis' => count(array_filter($vendedorProdutos, static fn(array $p): bool => $p['status'] === 'indisponivel')),
    ];
    $vendedorVendas = bo_carregar_vendas_vendedor($conn, $idUsuarioLogado);
    // Só para exibir o nome da transportadora escolhida em cada venda — o
    // cadastro de transportadoras em si é exclusivo do admin (globais).
    $transportadorasAtivas = array_values(array_filter(
        bo_carregar_transportadoras($conn),
        static fn(array $t): bool => $t['status'] === 'ativo'
    ));
}

// Nomes de planos ativos: usados no <select> do modal "Alterar plano" do
// aluno. Para o admin, já foi calculado no bloco acima (evita repetir a query).
if (!isset($planosAtivosOptions)) {
    $planosAtivosOptions = [];
    if ($r = $conn->query("SELECT nome FROM cadastro_planos WHERE status = 'ativo' ORDER BY nome")) {
        while ($row = $r->fetch_assoc()) {
            $planosAtivosOptions[] = $row['nome'];
        }
    }
}

/**
 * Catálogo de produtos filtrado por vendedor (id_vendedor = usuarios.id_usuario
 * do dono). Passe null para trazer todos os vendedores (visão agregada do
 * admin na tela "Vendas Marketplace"). Mesmo shape de $produtos em
 * "Produtos" do admin, com o nome do vendedor a mais.
 */
function bo_carregar_produtos_vendedor(mysqli $conn, ?int $idVendedor): array
{
    $sql = "SELECT p.id_produto, p.nome, p.categoria, p.preco, p.desconto, p.cashback_valor, p.estoque, p.status, p.imagem, p.descricao,
                   COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
            FROM produtos p
            LEFT JOIN usuarios v ON v.id_usuario = p.id_vendedor"
        . ($idVendedor !== null ? ' WHERE p.id_vendedor = ?' : '')
        . ' ORDER BY p.nome';
    $stmt = $conn->prepare($sql);
    if ($idVendedor !== null) {
        $stmt->bind_param('i', $idVendedor);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $produtos = [];
    while ($row = $res->fetch_assoc()) {
        $desconto = (float) $row['desconto'];
        $preco = (float) $row['preco'];
        $produtos[] = [
            'id' => (int) $row['id_produto'],
            'nome' => $row['nome'],
            'categoria' => $row['categoria'],
            'preco' => $preco,
            'desconto' => $desconto,
            'valorFinal' => round($preco - ($preco * $desconto / 100), 2),
            'cashback' => (float) $row['cashback_valor'],
            'estoque' => (int) $row['estoque'],
            'status' => $row['status'] === 'ativo' ? 'disponivel' : 'indisponivel',
            'imagem' => $row['imagem'],
            'descricao' => $row['descricao'],
            'vendedor' => $row['vendedor_nome'],
        ];
    }
    $stmt->close();

    return $produtos;
}

/**
 * Vendas (itens de pedido) de um vendedor, com produto, comprador,
 * transportadora e status de logística. Passe null para trazer as vendas
 * de todos os vendedores (visão agregada do admin).
 */
function bo_carregar_vendas_vendedor(mysqli $conn, ?int $idVendedor): array
{
    $sql = "SELECT pi.id_item, pi.id_pedido, pi.quantidade, pi.subtotal, pi.valor_frete, pi.status_logistica, pi.codigo_rastreio,
                   pr.nome AS produto_nome, u.nome AS comprador_nome, pe.data_pedido,
                   t.nome AS transportadora_nome, COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
            FROM pedido_item pi
            JOIN pedido pe ON pe.id_pedido = pi.id_pedido
            JOIN produtos pr ON pr.id_produto = pi.id_produto
            JOIN usuarios u ON u.id_usuario = pe.id_usuario
            LEFT JOIN transportadoras t ON t.id_transportadora = pi.id_transportadora
            LEFT JOIN usuarios v ON v.id_usuario = pi.id_vendedor"
        . ($idVendedor !== null ? ' WHERE pi.id_vendedor = ?' : '')
        . ' ORDER BY pe.data_pedido DESC';
    $stmt = $conn->prepare($sql);
    if ($idVendedor !== null) {
        $stmt->bind_param('i', $idVendedor);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $statusLogisticaLabel = ['aguardando' => 'Aguardando', 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => 'Devolvido', 'extraviado' => 'Extraviado'];

    $vendas = [];
    while ($row = $res->fetch_assoc()) {
        $vendas[] = [
            'id' => (int) $row['id_item'],
            'idPedido' => (int) $row['id_pedido'],
            'produto' => $row['produto_nome'],
            'vendedor' => $row['vendedor_nome'],
            'comprador' => $row['comprador_nome'],
            'quantidade' => (int) $row['quantidade'],
            'valor' => (float) $row['subtotal'],
            'valorFrete' => (float) $row['valor_frete'],
            'transportadora' => $row['transportadora_nome'] ?? '—',
            'statusLogistica' => $row['status_logistica'],
            'statusLogisticaLabel' => $statusLogisticaLabel[$row['status_logistica']] ?? ucfirst($row['status_logistica']),
            'codigoRastreio' => $row['codigo_rastreio'],
            'data' => date('d/m/Y H:i', strtotime($row['data_pedido'])),
        ];
    }
    $stmt->close();

    return $vendas;
}

/**
 * Transportadoras globais (cadastradas pelo admin) com suas faixas de CEP.
 */
function bo_carregar_transportadoras(mysqli $conn): array
{
    $transportadoras = [];
    $res = $conn->query('SELECT id_transportadora, nome, tipo, status FROM transportadoras ORDER BY nome');
    while ($row = $res->fetch_assoc()) {
        $transportadoras[(int) $row['id_transportadora']] = [
            'id' => (int) $row['id_transportadora'],
            'nome' => $row['nome'],
            'tipo' => $row['tipo'],
            'status' => $row['status'],
            'faixas' => [],
        ];
    }

    $resFaixas = $conn->query('SELECT id_faixa, id_transportadora, cep_inicial, cep_final, valor_frete, prazo_dias FROM faixas_cep_frete ORDER BY cep_inicial');
    while ($row = $resFaixas->fetch_assoc()) {
        $idTransportadora = (int) $row['id_transportadora'];
        if (isset($transportadoras[$idTransportadora])) {
            $transportadoras[$idTransportadora]['faixas'][] = [
                'id' => (int) $row['id_faixa'],
                'cepInicial' => $row['cep_inicial'],
                'cepFinal' => $row['cep_final'],
                'valorFrete' => (float) $row['valor_frete'],
                'prazoDias' => (int) $row['prazo_dias'],
            ];
        }
    }

    return array_values($transportadoras);
}

```

ARQUIVO: pages/dashboard/includes/compras.php

CODIGO COMPLETO:

```php
<?php
/**
 * Carrega os pedidos (marketplace) de um usuário, já separados em
 * "em andamento" e "histórico" — usado pelas telas "Minhas compras"
 * do profissional e do aluno. Cada pedido traz sua lista de itens
 * ('itens'), com o nome do vendedor de cada um ("ONE FIT" para produtos
 * legados sem vendedor, id_vendedor NULL) e o status de logística.
 *
 * @return array{0: array, 1: array}
 */
function bo_carregar_pedidos(mysqli $conn, int $idUsuario, string $busca = '', string $status = ''): array
{
    $statusSql = "CASE WHEN pe.status IN ('cancelado','devolvido','entregue') THEN pe.status
        WHEN NOT EXISTS (SELECT 1 FROM pedido_item x WHERE x.id_pedido = pe.id_pedido AND x.status_logistica <> 'entregue') THEN 'entregue'
        WHEN NOT EXISTS (SELECT 1 FROM pedido_item x WHERE x.id_pedido = pe.id_pedido AND x.status_logistica <> 'devolvido') THEN 'devolvido'
        ELSE 'aguardando' END";
    $busca = trim($busca);
    $like = '%' . strtr($busca, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    // O identificador exibido deriva da chave real do pedido, sem coluna duplicada.
    $idBusca = preg_match('/^TRX-?0*([0-9]+)$/i', $busca, $match) ? (int) $match[1] : 0;
    $stmt = $conn->prepare("SELECT pe.id_pedido, pe.status, ($statusSql) AS status_compra, pe.data_pedido, pe.valor_total,
            pi.id_item, pi.quantidade, pi.status_logistica, pi.confirmado_recebimento,
            pi.confirmado_recebimento_em, pr.nome AS produto_nome,
            COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
        FROM pedido pe
        JOIN pedido_item pi ON pi.id_pedido = pe.id_pedido
        JOIN produtos pr ON pr.id_produto = pi.id_produto
        LEFT JOIN usuarios v ON v.id_usuario = pi.id_vendedor
        WHERE pe.id_usuario = ?
          AND (? = '' OR ($statusSql) = ?)
          AND (? = '' OR LOWER(CONCAT('TRX-', LPAD(pe.id_pedido, GREATEST(4, CHAR_LENGTH(pe.id_pedido)), '0'))) LIKE LOWER(?) ESCAPE '!'
               OR pe.id_pedido = ? OR CAST(pe.id_pedido AS CHAR) LIKE ? ESCAPE '!'
               OR EXISTS (SELECT 1 FROM pedido_item busca_item
                   JOIN produtos busca_produto ON busca_produto.id_produto = busca_item.id_produto
                   WHERE busca_item.id_pedido = pe.id_pedido
                     AND LOWER(busca_produto.nome) LIKE LOWER(?) ESCAPE '!'))
        ORDER BY pe.data_pedido DESC, pe.id_pedido DESC, pi.id_item ASC");
    $stmt->bind_param('issssiss', $idUsuario, $status, $status, $busca, $like, $idBusca, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $statusLabel = ['aguardando' => 'Aguardando', 'pago' => 'Pago', 'processando' => 'Processando', 'entregue' => 'Entregue', 'cancelado' => 'Cancelado', 'devolvido' => 'Devolvido'];
    $statusLogisticaLabel = ['aguardando' => 'Aguardando', 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => 'Devolvido', 'extraviado' => 'Extraviado'];
    $finalizados = ['entregue', 'cancelado', 'devolvido'];

    $pedidos = [];
    while ($row = $res->fetch_assoc()) {
        $idPedido = (int) $row['id_pedido'];
        if (!isset($pedidos[$idPedido])) {
            $pedidos[$idPedido] = [
                'transacao' => 'TRX-' . str_pad((string) $idPedido, 4, '0', STR_PAD_LEFT),
                'valor' => (float) $row['valor_total'],
                'status' => $statusLabel[$row['status_compra']],
                'data' => date('d/m/Y H:i', strtotime($row['data_pedido'])),
                'statusBanco' => $row['status_compra'],
                'itens' => [],
                // Mantido por compatibilidade com quem ainda espera um resumo em texto.
                'produto' => '',
            ];
        }
        $pedidos[$idPedido]['itens'][] = [
            'idItem' => (int) $row['id_item'],
            'produto' => $row['produto_nome'],
            'quantidade' => (int) $row['quantidade'],
            'vendedor' => $row['vendedor_nome'],
            'statusLogisticaBanco' => $row['status_logistica'],
            'statusLogistica' => $statusLogisticaLabel[$row['status_logistica']] ?? ucfirst($row['status_logistica']),
            'confirmadoRecebimento' => (bool) $row['confirmado_recebimento'],
            'confirmadoRecebimentoEm' => $row['confirmado_recebimento_em'] ? date('d/m/Y H:i', strtotime($row['confirmado_recebimento_em'])) : null,
        ];
    }
    $stmt->close();

    $emAndamento = [];
    $historico = [];
    foreach ($pedidos as $pedido) {
        $pedido['produto'] = implode(', ', array_column($pedido['itens'], 'produto'));
        if (in_array($pedido['statusBanco'], $finalizados, true)) {
            $historico[] = $pedido;
        } else {
            $emAndamento[] = $pedido;
        }
    }

    return [$emAndamento, $historico];
}


```

ARQUIVO: pages/dashboard/components/section-aluno.php

CODIGO COMPLETO:

```php
<?php
/**
 * components/section-aluno.php
 * Telas do perfil Aluno. Depende de includes/mock-data.php:
 *   $alunoPerfil, $alunoHistorico, $alunoCashbackHistorico, $alunoPedidos,
 *   $alunoPedidosHistorico, $alunoTreino, $alunoAgendaDisponiveis
 */
?>

<!-- ===== ALUNO · Perfil (dados cadastrais + avaliação física/IMC) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="perfil">
    <div class="bo-page-title">
        <div>
            <h1>Perfil</h1>
            <p>Seus dados cadastrais na ONE FIT.</p>
        </div>
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("perfilEdit","Editar perfil", <?php echo bo_json($alunoPerfil); ?>)'>
            <i class="bi bi-pencil"></i> Editar
        </button>
    </div>

    <!-- Bloco 1: dados cadastrais -->
    <div class="bo-profile-block">
        <div class="bo-thumb mb-3" style="width:72px;height:72px;font-size:28px;">
            <i class="bi bi-person"></i>
        </div>
        <div class="bo-profile-row"><span>E-mail</span><span><?php echo $alunoPerfil['email']; ?></span></div>
        <div class="bo-profile-row">
            <span>Plano</span>
            <span>
                <?php echo $alunoPerfil['plano']; ?>
                <button type="button" class="btn-bo-outline ms-2" style="padding:4px 10px;font-size:12px;"
                    onclick='boOpenForm("planoAlterar","Alterar plano", {plano: "<?php echo $alunoPerfil['plano']; ?>"})'>Alterar</button>
            </span>
        </div>
        <div class="bo-profile-row"><span>Status</span><span><?php echo bo_badge($alunoPerfil['status'] === 'Ativo'); ?></span></div>
        <div class="bo-profile-row"><span>Documento</span><span><?php echo $alunoPerfil['documento']; ?></span></div>
        <div class="bo-profile-row"><span>Telefone</span><span><?php echo $alunoPerfil['telefone']; ?></span></div>
        <div class="bo-profile-row"><span>Data de cadastro</span><span><?php echo $alunoPerfil['dataCadastro']; ?></span></div>
        <div class="bo-profile-row"><span>Data de nascimento</span><span><?php echo $alunoPerfil['nascimento']; ?></span></div>
    </div>

    <!-- Bloco 2: avaliação física + cálculo de IMC (ver backoffice.js > boCalcularIMC) -->
    <div class="bo-profile-block">
        <div class="bo-section-heading">Avaliação física</div>
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label">Altura (m)</label>
                <input type="number" step="0.01" class="form-control" id="imcAltura" value="<?php echo $alunoPerfil['altura']; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Peso (kg)</label>
                <input type="number" step="0.1" class="form-control" id="imcPeso" value="<?php echo $alunoPerfil['peso']; ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Objetivo</label>
                <input type="text" class="form-control" id="imcObjetivo" value="<?php echo $alunoPerfil['objetivo']; ?>">
            </div>
        </div>
        <div class="bo-imc-box mb-3">
            <button type="button" class="btn-bo-outline" onclick="boCalcularIMC()">
                <i class="bi bi-calculator"></i> Calcular IMC
            </button>
            <div>
                <div class="bo-card-label" style="margin-bottom:2px;">Status de IMC</div>
                <div class="bo-card-value" id="imcResultado" style="font-size:18px;">—</div>
            </div>
        </div>
        <div class="bo-table-actions">
            <button type="button" class="btn-bo-outline">Cancelar</button>
            <button type="button" class="btn-bo-gold" onclick="boToast('Alterações salvas.')">Salvar</button>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Histórico (pagamentos de mensalidade) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="historico">
    <div class="bo-page-title">
        <div>
            <h1>Histórico</h1>
            <p>Histórico de pagamentos e movimentações.</p>
        </div>
        <!-- Abre o modal fixo de pagamento (components/modal-pagar-plano.php) -->
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPagarPlano">
            <i class="bi bi-credit-card"></i> Pagar Plano
        </button>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>Data/hora</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Cashback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['data']; ?></td>
                            <td><?php echo $h['descricao']; ?></td>
                            <td><?php echo $h['tipo']; ?></td>
                            <td><?php echo $h['status']; ?></td>
                            <td><?php echo bo_money($h['valor']); ?></td>
                            <td><?php echo bo_money($h['cashback']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Cashback (saldo e extrato de crédito/débito) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="cashback">
    <div class="bo-page-title">
        <div>
            <h1>Cashback</h1>
            <p>Saldo disponível e histórico de movimentações.</p>
        </div>
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Usar cashback", {})'>
            <i class="bi bi-wallet2"></i> Usar Cashback
        </button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo de cashback</div>
                <div class="bo-card-value"><?php echo bo_money($alunoCashbackSaldo); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoCashbackHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['data']; ?></td>
                            <td><?php echo $h['tipo'] === 'credito' ? 'Crédito' : 'Débito'; ?></td>
                            <td><?php echo $h['descricao']; ?></td>
                            <td><?php echo bo_money($h['valor']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Minhas compras (pedidos em andamento e histórico) ===== -->
<?php $perfilCompras = 'aluno'; $comprasPedidos = $alunoPedidos ?? []; $comprasHistorico = $alunoPedidosHistorico ?? []; require __DIR__ . '/section-compras.php'; ?>

<!-- ===== ALUNO · Treino (ficha de exercícios) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="treino">
    <div class="bo-page-title">
        <div>
            <h1>Treino</h1>
            <p>Monte e acompanhe sua ficha de treino.</p>
        </div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bo-action="clear-table" data-bo-target-table="alunoTreino">
                <i class="bi bi-eraser"></i> Limpar Treino
            </button>
            <button type="button" class="btn-bo-gold" onclick='boOpenForm("treinoExercicio","Adicionar exercício", {})'>
                <i class="bi bi-plus-lg"></i> Adicionar Treino
            </button>
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="alunoTreino">
                <thead>
                    <tr>
                        <th>Exercício</th>
                        <th>Séries</th>
                        <th>Repetições</th>
                        <th>Carga</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoTreino as $t): ?>
                        <tr>
                            <td><?php echo $t['nome']; ?></td>
                            <td><?php echo $t['series']; ?></td>
                            <td><?php echo $t['repeticoes']; ?></td>
                            <td><?php echo $t['carga']; ?> kg</td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar"
                                        onclick='boOpenForm("treinoExercicio","Editar exercício", <?php echo bo_json($t); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn-bo-icon danger" title="Remover"
                                        data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($t['nome']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="5">Nenhum exercício cadastrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Minha agenda (horários de avaliação física disponíveis) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1>Minha agenda</h1>
            <p>Datas disponíveis e agendadas com seus profissionais.</p>
        </div>
    </div>

    <div class="bo-section-heading">Agenda de avaliação física</div>
    <?php foreach ($alunoAgendaDisponiveis as $d): ?>
        <div class="bo-agenda-card disponivel">
            <div>
                <div class="bo-agenda-title"><?php echo $d['tipo']; ?></div>
                <div class="bo-agenda-sub"><?php echo $d['data']; ?></div>
            </div>
            <button type="button" class="btn-bo-gold" style="padding:8px 16px;"
                onclick='boOpenForm("agendaAgendar","Confirmar agendamento", {data: "<?php echo $d['data']; ?>", modalidade: "<?php echo $d['tipo']; ?>"})'>
                Agendar
            </button>
        </div>
    <?php endforeach; ?>
</section>

```

ARQUIVO: pages/dashboard/components/section-profissional.php

CODIGO COMPLETO:

```php
<?php
/**
 * Área do profissional ligada ao banco.
 *
 * A regra desta área permanece neste arquivo para não exigir alterações nas
 * actions, no JavaScript global ou nos demais componentes do dashboard.
 */

$ofH = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$ofFeedback = null;
$ofIdUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$ofIdProfissional = 0;
$ofAlunosDisponiveis = [];
$ofAlunosVinculados = [];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Conexão com o banco indisponível.');
    }

    $stmt = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $ofCadastroProfissional = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ofCadastroProfissional) {
        throw new RuntimeException('O usuário conectado não possui cadastro de profissional.');
    }
    $ofIdProfissional = (int) $ofCadastroProfissional['id_profissional'];

    // Relações ausentes no dump original. IF NOT EXISTS torna a preparação
    // segura para ser executada novamente sem apagar ou duplicar dados.
    if (!$conn->query(
        "CREATE TABLE IF NOT EXISTS profissional_aluno (
            id_vinculo INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_profissional INT NOT NULL,
            id_aluno INT NOT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            observacao VARCHAR(255) DEFAULT NULL,
            data_vinculo TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_vinculo),
            UNIQUE KEY uk_profissional_aluno (id_profissional, id_aluno),
            KEY idx_profissional_aluno_aluno (id_aluno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    )) {
        throw new RuntimeException('Não foi possível preparar os vínculos de alunos.');
    }

    if (!$conn->query(
        "CREATE TABLE IF NOT EXISTS disponibilidade_profissional (
            id_disponibilidade INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_profissional INT NOT NULL,
            modalidade VARCHAR(100) NOT NULL,
            data_evento DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fim TIME NOT NULL,
            local VARCHAR(120) DEFAULT NULL,
            status ENUM('disponivel','ocupado','cancelado') NOT NULL DEFAULT 'disponivel',
            data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_disponibilidade),
            KEY idx_disponibilidade_profissional (id_profissional, data_evento, hora_inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    )) {
        throw new RuntimeException('Não foi possível preparar os horários disponíveis.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prof_action'])) {
        $ofTokenRecebido = (string) ($_POST['csrf_token'] ?? '');
        $ofTokenSessao = (string) ($_SESSION['csrf_token'] ?? '');
        if ($ofTokenSessao === '' || !hash_equals($ofTokenSessao, $ofTokenRecebido)) {
            throw new RuntimeException('A sessão expirou. Atualize a página e tente novamente.');
        }

        $ofAction = (string) $_POST['prof_action'];

        if ($ofAction === 'vincular_aluno') {
            $idAluno = filter_input(INPUT_POST, 'id_aluno', FILTER_VALIDATE_INT);
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            if (!$idAluno) {
                throw new RuntimeException('Selecione um aluno válido.');
            }

            $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'aluno' LIMIT 1");
            $stmt->bind_param('i', $idAluno);
            $stmt->execute();
            $alunoExiste = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$alunoExiste) {
                throw new RuntimeException('Aluno não encontrado.');
            }

            $stmt = $conn->prepare(
                "INSERT INTO profissional_aluno (id_profissional, id_aluno, status, observacao)
                 VALUES (?, ?, 'ativo', ?)
                 ON DUPLICATE KEY UPDATE status = 'ativo', observacao = VALUES(observacao)"
            );
            $stmt->bind_param('iis', $ofIdProfissional, $idAluno, $observacao);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível vincular o aluno.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Aluno vinculado com sucesso.'];
        } elseif ($ofAction === 'atualizar_vinculo') {
            $idVinculo = filter_input(INPUT_POST, 'id_vinculo', FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            if (!$idVinculo || !in_array($status, ['ativo', 'inativo'], true)) {
                throw new RuntimeException('Dados do vínculo inválidos.');
            }

            $stmt = $conn->prepare(
                'UPDATE profissional_aluno SET status = ?, observacao = ? WHERE id_vinculo = ? AND id_profissional = ?'
            );
            $stmt->bind_param('ssii', $status, $observacao, $idVinculo, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Vínculo não encontrado ou sem alterações.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Vínculo atualizado com sucesso.'];
        } elseif ($ofAction === 'excluir_vinculo') {
            $idVinculo = filter_input(INPUT_POST, 'id_vinculo', FILTER_VALIDATE_INT);
            if (!$idVinculo) {
                throw new RuntimeException('Vínculo inválido.');
            }

            $stmt = $conn->prepare('DELETE FROM profissional_aluno WHERE id_vinculo = ? AND id_profissional = ?');
            $stmt->bind_param('ii', $idVinculo, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Vínculo não encontrado.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Aluno removido da lista. O cadastro dele foi preservado.'];
        } elseif ($ofAction === 'criar_disponibilidade') {
            $modalidade = trim((string) ($_POST['modalidade'] ?? ''));
            $dataEvento = (string) ($_POST['data_evento'] ?? '');
            $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
            $horaFim = (string) ($_POST['hora_fim'] ?? '');
            $local = trim((string) ($_POST['local'] ?? ''));
            $dataValida = DateTime::createFromFormat('Y-m-d', $dataEvento);

            if ($modalidade === '' || !$dataValida || $dataValida->format('Y-m-d') !== $dataEvento
                || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
                || !preg_match('/^\d{2}:\d{2}$/', $horaFim)
                || $horaFim <= $horaInicio) {
                throw new RuntimeException('Preencha corretamente a modalidade, a data e os horários.');
            }

            $stmt = $conn->prepare(
                "SELECT id_disponibilidade FROM disponibilidade_profissional
                 WHERE id_profissional = ? AND data_evento = ? AND status = 'disponivel'
                   AND hora_inicio < ? AND hora_fim > ? LIMIT 1"
            );
            $stmt->bind_param('isss', $ofIdProfissional, $dataEvento, $horaFim, $horaInicio);
            $stmt->execute();
            $conflito = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($conflito) {
                throw new RuntimeException('Já existe um horário disponível nesse período.');
            }

            $stmt = $conn->prepare(
                "INSERT INTO disponibilidade_profissional
                    (id_profissional, modalidade, data_evento, hora_inicio, hora_fim, local, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'disponivel')"
            );
            $stmt->bind_param('isssss', $ofIdProfissional, $modalidade, $dataEvento, $horaInicio, $horaFim, $local);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível cadastrar o horário.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Horário disponível cadastrado.'];
        } elseif ($ofAction === 'remover_disponibilidade') {
            $idDisponibilidade = filter_input(INPUT_POST, 'id_disponibilidade', FILTER_VALIDATE_INT);
            if (!$idDisponibilidade) {
                throw new RuntimeException('Horário inválido.');
            }

            $stmt = $conn->prepare(
                "DELETE FROM disponibilidade_profissional
                 WHERE id_disponibilidade = ? AND id_profissional = ? AND status = 'disponivel'"
            );
            $stmt->bind_param('ii', $idDisponibilidade, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Horário não encontrado.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Horário disponível removido.'];
        } elseif ($ofAction === 'agendar') {
            $operacaoAgendamento = (string) ($_POST['operacao_agendamento'] ?? '');
            if ($operacaoAgendamento === '' || !isset($_SESSION['operacoes_agendamento'][$operacaoAgendamento])) {
                throw new RuntimeException('Formulário já utilizado ou expirado. Abra um novo agendamento.');
            }
            $idAluno = filter_input(INPUT_POST, 'id_aluno', FILTER_VALIDATE_INT);
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $tipo = (string) ($_POST['tipo'] ?? 'aula');
            $dataEvento = (string) ($_POST['data_evento'] ?? '');
            $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
            $horaFim = trim((string) ($_POST['hora_fim'] ?? ''));
            $local = trim((string) ($_POST['local'] ?? ''));
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            $tiposPermitidos = ['aula', 'personal', 'avaliacao', 'consulta', 'reuniao', 'outro'];
            $dataValida = DateTime::createFromFormat('Y-m-d', $dataEvento);

            if (!$idAluno || $titulo === '' || !in_array($tipo, $tiposPermitidos, true)
                || !$dataValida || $dataValida->format('Y-m-d') !== $dataEvento
                || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
                || ($horaFim !== '' && (!preg_match('/^\d{2}:\d{2}$/', $horaFim) || $horaFim <= $horaInicio))) {
                throw new RuntimeException('Preencha corretamente os dados do agendamento.');
            }

            $stmt = $conn->prepare(
                "SELECT id_vinculo FROM profissional_aluno
                 WHERE id_profissional = ? AND id_aluno = ? AND status = 'ativo' LIMIT 1"
            );
            $stmt->bind_param('ii', $ofIdProfissional, $idAluno);
            $stmt->execute();
            $vinculoAtivo = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$vinculoAtivo) {
                throw new RuntimeException('Selecione um aluno vinculado e ativo.');
            }

            $stmt = $conn->prepare(
                "SELECT id_agendamento FROM agendamento
                 WHERE id_profissional = ? AND data_evento = ? AND hora_inicio = ?
                   AND status NOT IN ('cancelado','concluido') LIMIT 1"
            );
            $stmt->bind_param('iss', $ofIdProfissional, $dataEvento, $horaInicio);
            $stmt->execute();
            $conflito = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($conflito) {
                throw new RuntimeException('Já existe um agendamento nesse horário.');
            }

            // Compatível com o dump original, em que id_agendamento não foi
            // marcado como AUTO_INCREMENT.
            $proximoId = 1;
            $resultadoId = $conn->query('SELECT COALESCE(MAX(id_agendamento), 0) + 1 AS proximo FROM agendamento');
            if ($resultadoId) {
                $proximoId = (int) ($resultadoId->fetch_assoc()['proximo'] ?? 1);
            }

            $stmt = $conn->prepare(
                "INSERT INTO agendamento
                    (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento,
                     hora_inicio, hora_fim, local, observacao, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), 'agendado')"
            );
            $stmt->bind_param(
                'iiisssssss',
                $proximoId,
                $idAluno,
                $ofIdProfissional,
                $titulo,
                $tipo,
                $dataEvento,
                $horaInicio,
                $horaFim,
                $local,
                $observacao
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível criar o agendamento.');
            }
            $stmt->close();
            unset($_SESSION['operacoes_agendamento'][$operacaoAgendamento]);
            // INSERT concluído em autocommit. A falha secundária não desfaz a aula.
            try {
                require_once __DIR__ . '/../../../config/notificacoes.php';
                criarNotificacao($idAluno, 'Agendamento criado',
                    'Seu agendamento foi marcado para ' . $dataValida->format('d/m/Y') . ' às ' . $horaInicio . '.', 'agendamento');
            } catch (Throwable $erroNotificacao) {
                error_log('ONE FIT: falha ao notificar agendamento criado #' . $proximoId . '; código ' . $erroNotificacao->getCode());
            }
            $ofFeedback = ['type' => 'success', 'message' => 'Agendamento criado com sucesso.'];
        } elseif ($ofAction === 'cancelar_agendamento') {
            $idAgendamento = filter_input(INPUT_POST, 'id_agendamento', FILTER_VALIDATE_INT);
            if (!$idAgendamento) {
                throw new RuntimeException('Agendamento inválido.');
            }

            // Destinatário e data vêm do agendamento pertencente ao profissional.
            $stmt = $conn->prepare('SELECT id_usuario, data_evento, hora_inicio FROM agendamento WHERE id_agendamento = ? AND id_profissional = ?');
            $stmt->bind_param('ii', $idAgendamento, $ofIdProfissional);
            $stmt->execute();
            $agendamentoCancelado = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$agendamentoCancelado) throw new RuntimeException('Agendamento não encontrado.');

            $stmt = $conn->prepare(
                "UPDATE agendamento SET status = 'cancelado'
                 WHERE id_agendamento = ? AND id_profissional = ? AND status IN ('agendado','confirmado')"
            );
            $stmt->bind_param('ii', $idAgendamento, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Agendamento não encontrado ou já encerrado.');
            }
            $stmt->close();
            // affected_rows acima impede aviso em cancelamentos repetidos.
            try {
                require_once __DIR__ . '/../../../config/notificacoes.php';
                criarNotificacao((int) $agendamentoCancelado['id_usuario'], 'Agendamento cancelado',
                    'Seu agendamento de ' . date('d/m/Y', strtotime($agendamentoCancelado['data_evento']))
                    . ' às ' . substr($agendamentoCancelado['hora_inicio'], 0, 5) . ' foi cancelado.', 'agendamento');
            } catch (Throwable $erroNotificacao) {
                error_log('ONE FIT: falha ao notificar agendamento cancelado #' . $idAgendamento . '; código ' . $erroNotificacao->getCode());
            }
            $ofFeedback = ['type' => 'success', 'message' => 'Agendamento cancelado.'];
        } elseif ($ofAction === 'usar_cashback') {
            $valorTexto = str_replace(',', '.', trim((string) ($_POST['valor'] ?? '')));
            $valor = filter_var($valorTexto, FILTER_VALIDATE_FLOAT);
            if ($valor === false || $valor <= 0) {
                throw new RuntimeException('Informe um valor de cashback válido.');
            }

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END), 0) AS saldo
                 FROM cashback WHERE id_usuario = ? AND status != 'cancelado'"
            );
            $stmt->bind_param('i', $ofIdUsuario);
            $stmt->execute();
            $saldo = (float) ($stmt->get_result()->fetch_assoc()['saldo'] ?? 0);
            $stmt->close();
            if ($valor > $saldo) {
                throw new RuntimeException('Saldo de cashback insuficiente.');
            }

            $descricao = 'Uso de cashback pelo profissional';
            $stmt = $conn->prepare(
                "INSERT INTO cashback (id_usuario, id_pagamento, valor, tipo, origem, descricao, status)
                 VALUES (?, NULL, ?, 'debito', 'uso', ?, 'utilizado')"
            );
            $stmt->bind_param('ids', $ofIdUsuario, $valor, $descricao);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível utilizar o cashback.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Cashback utilizado com sucesso.'];
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    }

    // Recarrega os dados alteráveis para mostrar o resultado na mesma resposta.
    $stmt = $conn->prepare(
        "SELECT pa.id_vinculo, pa.id_aluno, pa.status, pa.observacao,
                u.nome, u.email, u.celular,
                COALESCE(cp.nome, 'Sem plano') AS plano,
                COALESCE(m.valor_contratado, 0) AS valor
         FROM profissional_aluno pa
         JOIN usuarios u ON u.id_usuario = pa.id_aluno
         LEFT JOIN matricula m ON m.id_matricula = (
            SELECT MAX(m2.id_matricula) FROM matricula m2 WHERE m2.id_usuario = u.id_usuario
         )
         LEFT JOIN cadastro_planos cp ON cp.id_plano = m.id_plano
         WHERE pa.id_profissional = ? ORDER BY u.nome"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $ofAlunosVinculados[] = $row;
    }
    $stmt->close();
    $profAlunos = $ofAlunosVinculados;

    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.nome, u.email
         FROM usuarios u
         WHERE u.tipo_usuario = 'aluno' AND u.status = 'ativo'
           AND NOT EXISTS (
             SELECT 1 FROM profissional_aluno pa
             WHERE pa.id_profissional = ? AND pa.id_aluno = u.id_usuario AND pa.status = 'ativo'
           )
         ORDER BY u.nome"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $ofAlunosDisponiveis[] = $row;
    }
    $stmt->close();

    $profAgendados = [];
    $stmt = $conn->prepare(
        "SELECT a.id_agendamento, a.titulo, a.tipo, a.data_evento, a.hora_inicio,
                a.hora_fim, a.local, a.observacao, a.status,
                u.id_usuario AS id_aluno, u.nome AS aluno, u.celular AS contato
         FROM agendamento a
         JOIN usuarios u ON u.id_usuario = a.id_usuario
         WHERE a.id_profissional = ? AND a.status IN ('agendado','confirmado')
         ORDER BY a.data_evento, a.hora_inicio"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $row['modalidade'] = $row['titulo'] ?: ucfirst($row['tipo']);
        $row['data'] = date('d/m/Y', strtotime($row['data_evento'])) . ' ' . substr($row['hora_inicio'], 0, 5);
        $profAgendados[] = $row;
    }
    $stmt->close();

    $profDisponiveis = [];
    $stmt = $conn->prepare(
        "SELECT id_disponibilidade, modalidade, data_evento, hora_inicio, hora_fim, local
         FROM disponibilidade_profissional
         WHERE id_profissional = ? AND status = 'disponivel'
         ORDER BY data_evento, hora_inicio"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $row['data'] = date('d/m/Y', strtotime($row['data_evento']))
            . ' ' . substr($row['hora_inicio'], 0, 5)
            . ' às ' . substr($row['hora_fim'], 0, 5);
        $profDisponiveis[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END), 0) AS saldo
         FROM cashback WHERE id_usuario = ? AND status != 'cancelado'"
    );
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $profContrato['saldoCashback'] = (float) ($stmt->get_result()->fetch_assoc()['saldo'] ?? 0);
    $stmt->close();

    $profCashbackHistorico = [];
    $stmt = $conn->prepare(
        "SELECT data_criacao, descricao, valor, tipo FROM cashback
         WHERE id_usuario = ? AND status != 'cancelado' ORDER BY data_criacao DESC, id_cashback DESC"
    );
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $profCashbackHistorico[] = [
            'data' => date('d/m/Y', strtotime($row['data_criacao'])),
            'descricao' => $row['descricao'],
            'valor' => $row['tipo'] === 'debito' ? -(float) $row['valor'] : (float) $row['valor'],
        ];
    }
    $stmt->close();
} catch (Throwable $e) {
    $ofFeedback = ['type' => 'danger', 'message' => $e->getMessage()];
}

$ofCsrf = (string) ($_SESSION['csrf_token'] ?? '');
// Tokens por formulário permitem abas simultâneas e bloqueiam reenvios já concluídos.
$_SESSION['operacoes_agendamento'] = array_slice($_SESSION['operacoes_agendamento'] ?? [], -49, null, true);
$ofOperacaoAgendamento = bin2hex(random_bytes(16));
$_SESSION['operacoes_agendamento'][$ofOperacaoAgendamento] = true;
?>

<?php if ($ofFeedback): ?>
    <div class="alert alert-<?php echo $ofH($ofFeedback['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo $ofH($ofFeedback['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<!-- ===== PROFISSIONAL · Dashboard ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="dashboard">
    <div class="bo-page-title">
        <div><h1>Dashboard</h1><p>Resumo do seu contrato e saldo com a ONE FIT.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-4"><div class="bo-card">
            <div class="bo-card-label">Status de contrato</div>
            <div class="bo-card-value"><?php echo $ofH($profContrato['status'] ?? '—'); ?></div>
        </div></div>
        <div class="col-12 col-md-4"><div class="bo-card">
            <div class="bo-card-label">Validade de contrato</div>
            <div class="bo-card-value"><?php echo $ofH($profContrato['validade'] ?? '—'); ?></div>
        </div></div>
        <div class="col-12 col-md-4"><div class="bo-card">
            <div class="bo-card-label">Saldo de cashback</div>
            <div class="bo-card-value"><?php echo bo_money((float) ($profContrato['saldoCashback'] ?? 0)); ?></div>
        </div></div>
    </div>
</section>

<!-- ===== PROFISSIONAL · Histórico ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="historico">
    <div class="bo-page-title">
        <div><h1>Histórico</h1><p>Histórico de competências e valores recebidos.</p></div>
        <button type="button" class="btn-bo-outline" data-bo-export="profHistorico"><i class="bi bi-download"></i> Exportar</button>
    </div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profHistorico">
            <thead><tr><th>Competência</th><th>Valor</th><th>Tipo</th><th>Cashback</th></tr></thead>
            <tbody>
                <?php foreach (($profHistorico ?? []) as $h): ?>
                    <tr>
                        <td><?php echo $ofH($h['competencia'] ?? '—'); ?></td>
                        <td><?php echo bo_money((float) ($h['valor'] ?? 0)); ?></td>
                        <td><?php echo $ofH(ucfirst((string) ($h['tipo'] ?? '—'))); ?></td>
                        <td><?php echo bo_money((float) ($h['cashback'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profHistorico) ? '' : 'style="display:none"'; ?>><td colspan="4">Nenhum registro encontrado.</td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFISSIONAL · Alunos ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="alunos">
    <div class="bo-page-title">
        <div><h1>Alunos</h1><p>Cadastre, edite ou remova vínculos com alunos já registrados.</p></div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofAlunoModal" data-of-new-student>
            <i class="bi bi-plus-lg"></i> Adicionar aluno
        </button>
    </div>
    <div class="bo-filters">
        <input type="search" class="form-control" style="max-width:300px" placeholder="Buscar aluno" data-bo-filter="search" data-bo-target="profAlunos">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="profAlunos">
            <option value="">Todos os status</option><option value="ativo">Ativo</option><option value="inativo">Inativo</option>
        </select>
    </div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profAlunos">
            <thead><tr><th>Nome</th><th>Plano</th><th>Status</th><th>Valor</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($profAlunos as $a): ?>
                    <tr data-search="<?php echo $ofH(strtolower(($a['nome'] ?? '') . ' ' . ($a['email'] ?? '') . ' ' . ($a['plano'] ?? ''))); ?>"
                        data-status="<?php echo $ofH($a['status']); ?>">
                        <td>
                            <?php echo $ofH($a['nome']); ?>
                            <small class="d-block text-secondary"><?php echo $ofH($a['email'] ?? ''); ?></small>
                        </td>
                        <td><?php echo $ofH($a['plano'] ?? 'Sem plano'); ?></td>
                        <td><?php echo bo_badge($a['status'] === 'ativo'); ?></td>
                        <td><?php echo bo_money((float) ($a['valor'] ?? 0)); ?></td>
                        <td><div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar vínculo"
                                data-bs-toggle="modal" data-bs-target="#ofAlunoModal" data-of-edit-student
                                data-id="<?php echo (int) $a['id_vinculo']; ?>"
                                data-name="<?php echo $ofH($a['nome']); ?>"
                                data-status="<?php echo $ofH($a['status']); ?>"
                                data-observation="<?php echo $ofH($a['observacao'] ?? ''); ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" action="?section=alunos" class="d-inline" data-of-confirm="Remover este aluno da sua lista?">
                                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                                <input type="hidden" name="prof_action" value="excluir_vinculo">
                                <input type="hidden" name="id_vinculo" value="<?php echo (int) $a['id_vinculo']; ?>">
                                <button type="submit" class="btn-bo-icon danger" title="Excluir vínculo"><i class="bi bi-trash"></i></button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profAlunos) ? '' : 'style="display:none"'; ?>><td colspan="5">Nenhum aluno vinculado.</td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFISSIONAL · Agenda ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="agenda">
    <div class="bo-page-title">
        <div><h1>Agenda</h1><p>Cadastre horários disponíveis e gerencie os agendamentos.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bs-toggle="modal" data-bs-target="#ofDisponibilidadeModal"><i class="bi bi-calendar-plus"></i> Horário disponível</button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofAgendamentoModal"><i class="bi bi-plus-lg"></i> Agendar</button>
        </div>
    </div>
    <div class="bo-filters"><div class="bo-daterange">
        De <input type="date" class="form-control" data-of-date-from>
        até <input type="date" class="form-control" data-of-date-to>
    </div></div>

    <div class="bo-section-heading">Agendados</div>
    <div data-of-agenda-list>
        <?php foreach ($profAgendados as $ag): ?>
            <div class="bo-agenda-card" data-of-agenda-card data-date="<?php echo $ofH($ag['data_evento']); ?>">
                <div>
                    <div class="bo-agenda-title"><?php echo $ofH($ag['aluno']); ?> · <?php echo $ofH($ag['modalidade']); ?></div>
                    <div class="bo-agenda-sub">
                        <?php echo $ofH($ag['contato']); ?> · <?php echo $ofH($ag['data']); ?>
                        <?php if (!empty($ag['local'])): ?> · <?php echo $ofH($ag['local']); ?><?php endif; ?>
                    </div>
                </div>
                <form method="post" action="?section=agenda" data-of-confirm="Cancelar este agendamento?">
                    <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                    <input type="hidden" name="prof_action" value="cancelar_agendamento">
                    <input type="hidden" name="id_agendamento" value="<?php echo (int) $ag['id_agendamento']; ?>">
                    <button type="submit" class="btn-bo-icon danger" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (empty($profAgendados)): ?><p class="text-secondary">Nenhum agendamento ativo.</p><?php endif; ?>
    </div>

    <div class="bo-section-heading">Disponíveis</div>
    <div data-of-agenda-list>
        <?php foreach ($profDisponiveis as $d): ?>
            <div class="bo-agenda-card disponivel" data-of-agenda-card data-date="<?php echo $ofH($d['data_evento']); ?>">
                <div>
                    <div class="bo-agenda-title"><?php echo $ofH($d['modalidade']); ?></div>
                    <div class="bo-agenda-sub">
                        <?php echo $ofH($d['data']); ?><?php if (!empty($d['local'])): ?> · <?php echo $ofH($d['local']); ?><?php endif; ?>
                    </div>
                </div>
                <form method="post" action="?section=agenda" data-of-confirm="Remover este horário disponível?">
                    <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                    <input type="hidden" name="prof_action" value="remover_disponibilidade">
                    <input type="hidden" name="id_disponibilidade" value="<?php echo (int) $d['id_disponibilidade']; ?>">
                    <button type="submit" class="btn-bo-icon danger" title="Remover"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (empty($profDisponiveis)): ?><p class="text-secondary">Nenhum horário disponível cadastrado.</p><?php endif; ?>
    </div>
</section>

<!-- ===== PROFISSIONAL · Cashback ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="cashback">
    <div class="bo-page-title">
        <div><h1>Meu cashback</h1><p>Saldo disponível e histórico de créditos e débitos.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bo-export="profCashback"><i class="bi bi-download"></i> Exportar</button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofCashbackModal"><i class="bi bi-wallet2"></i> Utilizar cashback</button>
        </div>
    </div>
    <div class="row g-3 mb-3"><div class="col-12 col-md-4"><div class="bo-card">
        <div class="bo-card-label">Saldo total</div>
        <div class="bo-card-value"><?php echo bo_money((float) ($profContrato['saldoCashback'] ?? 0)); ?></div>
    </div></div></div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profCashback">
            <thead><tr><th>Data</th><th>Descrição</th><th>Valor</th></tr></thead>
            <tbody>
                <?php foreach ($profCashbackHistorico as $h): ?>
                    <tr><td><?php echo $ofH($h['data']); ?></td><td><?php echo $ofH($h['descricao']); ?></td><td><?php echo bo_money((float) $h['valor']); ?></td></tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profCashbackHistorico) ? '' : 'style="display:none"'; ?>><td colspan="3">Nenhum registro encontrado.</td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFISSIONAL · Compras ===== -->
<?php $perfilCompras = 'profissional'; $comprasPedidos = $profPedidos ?? []; $comprasHistorico = $profPedidosHistorico ?? []; require __DIR__ . '/section-compras.php'; ?>

<!-- Modal: vínculo de aluno -->
<div class="modal fade" id="ofAlunoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=alunos" id="ofAlunoForm">
            <div class="modal-header">
                <h5 class="modal-title" id="ofAlunoModalTitle">Adicionar aluno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="vincular_aluno" id="ofAlunoAction">
                <input type="hidden" name="id_vinculo" value="" id="ofAlunoVinculo">
                <div class="mb-3" id="ofAlunoSelectWrap">
                    <label class="form-label" for="ofAlunoSelect">Aluno</label>
                    <select class="form-select" name="id_aluno" id="ofAlunoSelect">
                        <option value="">Selecione</option>
                        <?php foreach ($ofAlunosDisponiveis as $aluno): ?>
                            <option value="<?php echo (int) $aluno['id_usuario']; ?>"><?php echo $ofH($aluno['nome'] . ' · ' . $aluno['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($ofAlunosDisponiveis)): ?><small class="text-secondary">Todos os alunos ativos já estão vinculados.</small><?php endif; ?>
                </div>
                <div class="mb-3 d-none" id="ofAlunoNameWrap">
                    <label class="form-label">Aluno</label><input type="text" class="form-control" id="ofAlunoName" readonly>
                </div>
                <div class="mb-3 d-none" id="ofAlunoStatusWrap">
                    <label class="form-label" for="ofAlunoStatus">Status do vínculo</label>
                    <select class="form-select" name="status" id="ofAlunoStatus"><option value="ativo">Ativo</option><option value="inativo">Inativo</option></select>
                </div>
                <div>
                    <label class="form-label" for="ofAlunoObservacao">Observação</label>
                    <textarea class="form-control" name="observacao" id="ofAlunoObservacao" rows="3" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">Salvar</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal: disponibilidade -->
<div class="modal fade" id="ofDisponibilidadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=agenda">
            <div class="modal-header"><h5 class="modal-title">Novo horário disponível</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="criar_disponibilidade">
                <div class="col-12"><label class="form-label">Modalidade</label><input class="form-control" name="modalidade" maxlength="100" required></div>
                <div class="col-12 col-md-6"><label class="form-label">Data</label><input type="date" class="form-control" name="data_evento" min="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Início</label><input type="time" class="form-control" name="hora_inicio" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Fim</label><input type="time" class="form-control" name="hora_fim" required></div>
                <div class="col-12"><label class="form-label">Local</label><input class="form-control" name="local" maxlength="120"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Cadastrar</button></div>
        </form>
    </div></div>
</div>

<!-- Modal: agendamento -->
<div class="modal fade" id="ofAgendamentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=agenda">
            <div class="modal-header"><h5 class="modal-title">Novo agendamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="agendar">
                <input type="hidden" name="operacao_agendamento" value="<?php echo $ofH($ofOperacaoAgendamento); ?>">
                <div class="col-12">
                    <label class="form-label">Aluno</label>
                    <select class="form-select" name="id_aluno" required>
                        <option value="">Selecione</option>
                        <?php foreach ($profAlunos as $a): ?><?php if ($a['status'] === 'ativo'): ?>
                            <option value="<?php echo (int) $a['id_aluno']; ?>"><?php echo $ofH($a['nome']); ?></option>
                        <?php endif; ?><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-8"><label class="form-label">Título</label><input class="form-control" name="titulo" maxlength="150" required></div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo"><option value="aula">Aula</option><option value="personal">Personal</option><option value="avaliacao">Avaliação</option><option value="consulta">Consulta</option><option value="reuniao">Reunião</option><option value="outro">Outro</option></select>
                </div>
                <div class="col-12 col-md-6"><label class="form-label">Data</label><input type="date" class="form-control" name="data_evento" min="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Início</label><input type="time" class="form-control" name="hora_inicio" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Fim</label><input type="time" class="form-control" name="hora_fim"></div>
                <div class="col-12"><label class="form-label">Local</label><input class="form-control" name="local" maxlength="120"></div>
                <div class="col-12"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="2" maxlength="255"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Agendar</button></div>
        </form>
    </div></div>
</div>

<!-- Modal: cashback -->
<div class="modal fade" id="ofCashbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=cashback">
            <div class="modal-header"><h5 class="modal-title">Utilizar cashback</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="usar_cashback">
                <label class="form-label">Valor</label>
                <input type="number" class="form-control" name="valor" min="0.01" step="0.01"
                    max="<?php echo $ofH(number_format((float) ($profContrato['saldoCashback'] ?? 0), 2, '.', '')); ?>" required>
                <small class="text-secondary">Saldo disponível: <?php echo bo_money((float) ($profContrato['saldoCashback'] ?? 0)); ?></small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Confirmar uso</button></div>
        </form>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-of-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.getAttribute('data-of-confirm'))) event.preventDefault();
        });
    });

    const alunoModal = document.getElementById('ofAlunoModal');
    const alunoAction = document.getElementById('ofAlunoAction');
    const alunoVinculo = document.getElementById('ofAlunoVinculo');
    const alunoSelect = document.getElementById('ofAlunoSelect');
    const alunoSelectWrap = document.getElementById('ofAlunoSelectWrap');
    const alunoNameWrap = document.getElementById('ofAlunoNameWrap');
    const alunoStatusWrap = document.getElementById('ofAlunoStatusWrap');
    const alunoName = document.getElementById('ofAlunoName');
    const alunoStatus = document.getElementById('ofAlunoStatus');
    const alunoObservacao = document.getElementById('ofAlunoObservacao');

    alunoModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const editing = button?.hasAttribute('data-of-edit-student');
        document.getElementById('ofAlunoModalTitle').textContent = editing ? 'Editar vínculo' : 'Adicionar aluno';
        alunoAction.value = editing ? 'atualizar_vinculo' : 'vincular_aluno';
        alunoVinculo.value = editing ? button.dataset.id : '';
        alunoName.value = editing ? button.dataset.name : '';
        alunoStatus.value = editing ? button.dataset.status : 'ativo';
        alunoObservacao.value = editing ? button.dataset.observation : '';
        alunoSelect.value = '';
        alunoSelect.required = !editing;
        alunoSelectWrap.classList.toggle('d-none', editing);
        alunoNameWrap.classList.toggle('d-none', !editing);
        alunoStatusWrap.classList.toggle('d-none', !editing);
    });

    const from = document.querySelector('[data-of-date-from]');
    const to = document.querySelector('[data-of-date-to]');
    const filterAgenda = () => {
        document.querySelectorAll('[data-of-agenda-card]').forEach((card) => {
            const date = card.dataset.date || '';
            card.style.display = (!from.value || date >= from.value) && (!to.value || date <= to.value) ? '' : 'none';
        });
    };
    from?.addEventListener('change', filterAgenda);
    to?.addEventListener('change', filterAgenda);
});
</script>

```

ARQUIVO: pages/dashboard/components/section-compras.php

CODIGO COMPLETO:

```php
<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($perfilCompras); ?>" data-bo-compras data-endpoint="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/buscar-compras.php'); ?>" data-section="compras">
    <div class="bo-page-title">
        <div>
            <h1>Minhas compras</h1>
            <p>Acompanhamento e histórico dos seus pedidos.</p>
        </div>
    </div>

    <div class="bo-filters">
        <input data-compras-busca aria-label="Buscar por produto ou ID da transação" type="search" maxlength="150" class="form-control" style="max-width:280px" placeholder="Buscar por produto, ID da transação ou pedido">
        <select data-compras-status aria-label="Status da compra" class="form-select" style="max-width:200px">
            <option value="">Todos</option>
            <option value="aguardando">Aguardando</option>
            <option value="entregue">Entregue</option>
            <option value="cancelado">Cancelado</option>
            <option value="devolvido">Devolvido</option>
        </select>
    </div>

    <p data-compras-feedback role="status" aria-live="polite" hidden></p>
    <div data-compras-resultados>
    <?php require __DIR__ . '/compras-resultados.php'; ?>
    </div>
</section>

```

ARQUIVO: pages/dashboard/components/compras-resultados.php

CODIGO COMPLETO:

```php
<?php if (!$comprasPedidos && !$comprasHistorico): ?><p class="bo-card">Nenhuma compra encontrada.</p><?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Total de pedidos</div>
                <div class="bo-card-value"><?php echo count($comprasPedidos) + count($comprasHistorico); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-section-heading">Acompanhamento de pedido</div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Recebimento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasPedidos)): ?><tr><td colspan="6">Nenhum pedido em andamento corresponde aos filtros selecionados.</td></tr><?php endif; ?>
                    <?php foreach ($comprasPedidos as $ped): ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <div><?php echo (int) $it['quantidade']; ?>x <?php echo htmlspecialchars($it['produto']); ?> — <small>Vendido por: <?php echo htmlspecialchars($it['vendedor']); ?> · <?php echo htmlspecialchars($it['statusLogistica']); ?></small></div>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo array_sum(array_column($ped['itens'], 'quantidade')); ?></td>
                            <td><?php echo bo_money($ped['valor']); ?></td>
                            <td><span class="bo-badge bo-compra-<?php echo $ped['statusBanco']; ?>"><?php echo $ped['status']; ?></span></td>
                            <td>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <?php if ($ped['statusBanco'] === 'aguardando' && $it['statusLogisticaBanco'] === 'despachado'): ?>
                                        <form method="POST" action="<?php echo bo_form_action('meus-pedidos.php'); ?>" class="bo-inline-form">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'compras'); ?>
                                            <?php echo bo_hidden('acao', 'confirmar-recebimento'); ?>
                                            <?php echo bo_hidden('id_item', $it['idItem']); ?>
                                            <button type="submit" class="btn-bo-outline btn-sm">Confirmar recebimento</button>
                                        </form>
                                    <?php elseif ($it['confirmadoRecebimento']): ?>
                                        <small>Recebido em <?php echo $it['confirmadoRecebimentoEm']; ?></small>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bo-section-heading">Histórico de compra</div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Data/hora</th>
                        <th>Produto</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasHistorico)): ?><tr><td colspan="4">Nenhuma compra no histórico corresponde aos filtros selecionados.</td></tr><?php endif; ?>
                    <?php foreach ($comprasHistorico as $ped): ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td><?php echo $ped['data']; ?></td>
                            <td>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <div><?php echo (int) $it['quantidade']; ?>x <?php echo htmlspecialchars($it['produto']); ?> — <small>Vendido por: <?php echo htmlspecialchars($it['vendedor']); ?></small></div>
                                <?php endforeach; ?>
                            </td>
                            <td><span class="bo-badge bo-compra-<?php echo $ped['statusBanco']; ?>"><?php echo $ped['status']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

```

ARQUIVO: pages/dashboard/funcionalidades/buscar-compras.php

CODIGO COMPLETO:

```php
<?php
require __DIR__ . '/../../../config/parametros.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sua sessão expirou. Entre novamente.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}
$busca = $_GET['busca'] ?? '';
$status = $_GET['status'] ?? '';
if (!is_string($busca) || !is_string($status) || strlen($busca) > 600
    || !in_array($status, ['', 'aguardando', 'entregue', 'cancelado', 'devolvido'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Filtros inválidos.']);
    exit;
}
$bufferLevel = ob_get_level();
try {
    require __DIR__ . '/../../../config/conn.php';
    require __DIR__ . '/../includes/helpers.php';
    require __DIR__ . '/../includes/admin-forms.php';
    require __DIR__ . '/../includes/compras.php';
    [$comprasPedidos, $comprasHistorico] = bo_carregar_pedidos($conn, (int) $_SESSION['id_usuario'], $busca, $status);
    ob_start();
    require __DIR__ . '/../components/compras-resultados.php';
    $html = ob_get_clean();
    echo json_encode(['html' => $html, 'total' => count($comprasPedidos) + count($comprasHistorico)], JSON_THROW_ON_ERROR);
} catch (Throwable $erro) {
    if (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }
    error_log('ONE FIT: falha na busca de compras; código ' . $erro->getCode());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar as compras. Tente novamente.']);
}

```

ARQUIVO: tests/compras.php

CODIGO COMPLETO:

```php
<?php
// Verificação somente de leitura sobre os pedidos existentes. Não cria compras.
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/dashboard/includes/compras.php';
function checkCompras(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function comprasIds(array $groups): array {
    $ids = array_column(array_merge(...$groups), 'transacao');
    sort($ids);
    return $ids;
}
$users = $conn->query('SELECT DISTINCT id_usuario FROM pedido')->fetch_all(MYSQLI_ASSOC);
$checks = 0;
foreach ($users as $user) {
    $id = (int) $user['id_usuario'];
    $all = bo_carregar_pedidos($conn, $id);
    $orders = array_merge(...$all);
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT pe.id_pedido) n FROM pedido pe JOIN pedido_item pi ON pi.id_pedido = pe.id_pedido JOIN produtos pr ON pr.id_produto = pi.id_produto WHERE pe.id_usuario = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    checkCompras(count($orders) === (int) $stmt->get_result()->fetch_assoc()['n'], 'Total divergente');
    $stmt->close();
    foreach (['', 'aguardando', 'entregue', 'cancelado', 'devolvido'] as $status) {
        foreach (['', '1', "' OR 1=1 --", '%', '_', 'produto-inexistente-7ad3'] as $term) {
            $expected = array_values(array_filter($orders, static function ($order) use ($status, $term) {
                if ($status !== '' && $order['statusBanco'] !== $status) return false;
                if ($term === '' || stripos($order['transacao'], $term) !== false) return true;
                foreach ($order['itens'] as $item) if (stripos($item['produto'], $term) !== false) return true;
                return false;
            }));
            checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, $term, $status)) === comprasIds([$expected]), 'Busca/status divergentes');
            $checks++;
        }
    }
    foreach ($orders as $order) {
        $trx = $order['transacao'];
        checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, $trx)) === [$trx], 'Busca por transação');
        checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, str_replace('TRX-', 'trx00', $trx))) === [$trx], 'Transação sem hífen');
        $name = $order['itens'][0]['produto'];
        checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, strtolower($name))) === comprasIds(bo_carregar_pedidos($conn, $id, strtoupper($name))), 'Maiúsculas/minúsculas');
        $found = array_merge(...bo_carregar_pedidos($conn, $id, $name));
        $matching = array_values(array_filter($found, static fn($p) => $p['transacao'] === $trx));
        checkCompras(count($matching) === 1 && $matching[0]['itens'] === $order['itens'], 'Itens incompletos');
        checkCompras(bo_carregar_pedidos($conn, 0, $trx) === [[], []], 'Vazamento de compras');
        $checks += 5;
    }
}
echo "OK: $checks verificações, " . count($users) . " compradores reais; nenhuma escrita no banco.\n";

```

