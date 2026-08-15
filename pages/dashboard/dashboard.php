<?php
/**
 * dashboard.php
 * Página principal do backoffice. Esse arquivo só faz a "montagem":
 * carrega os dados (mock-data.php), as funções auxiliares (helpers.php)
 * e inclui, em ordem, cada pedaço de HTML (components/*.php). Toda a
 * regra de exibição/estilo/interação vive nos outros arquivos:
 
 *   assets/js/backoffice.js     -> perfis, menu, modal, filtros, IMC, pagamento
 *   includes/helpers.php        -> bo_badge(), bo_money(), bo_json()
 *   includes/mock-data.php      -> dados de exemplo (trocar por consultas ao banco)
 *   components/header.php       -> barra do topo
 *   components/sidebar.php      -> menu lateral
 *   components/section-*.php    -> telas de cada perfil (admin/profissional/aluno)
 *   components/modal-*.php      -> modais (formulário genérico e pagamento)
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/mock-data.php';

// Sem sessão -> manda pro login. O acesso ao backoffice inteiro depende de
// estar logado (id_usuario e tipo_usuario são gravados em processa_login.php).
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . 'pages/login/login.php');
    exit;
}

// Perfil de verdade (não é mais escolhido pelo usuário): vem da coluna
// usuarios.tipo_usuario, gravada na sessão no momento do login.
// Ajuste os 3 valores abaixo se os nomes do ENUM no banco forem diferentes.
$perfilLogado = $_SESSION['tipo_usuario'] ?? 'aluno';
if (!in_array($perfilLogado, ['admin', 'profissional', 'aluno'], true)) {
    $perfilLogado = 'aluno'; // valor desconhecido -> cai no perfil mais restrito
}

$usuarioDashboard = [
    'nome' => $_SESSION['nome'] ?? 'Usuário ONE FIT',
    'email' => $_SESSION['email'] ?? '',
];

// Até que exista a consulta real da equipe, a busca usa apenas os campos
// públicos dos profissionais ativos que já fazem parte do mock do painel.
$profissionaisPesquisa = array_values(array_map(
    static fn(array $profissional): array => [
        'id' => (int) $profissional['id'],
        'nome' => $profissional['nome'],
        'funcao' => $profissional['funcao'],
        'especialidade' => $profissional['tituloCard'],
    ],
    array_filter($profissionaisAdm, static fn(array $profissional): bool => $profissional['status'] === 'ativo')
));
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel — ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css">
    <script>
        try { document.documentElement.setAttribute('data-theme', localStorage.getItem('onefit-theme') || 'dark'); } catch (e) {}
    </script>
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body>

    <?php require __DIR__ . '/components/header.php'; ?>
    <?php require __DIR__ . '/components/sidebar.php'; ?>

    <main class="bo-main">
        <?php
        // IMPORTANTE: isso não é só "esconder com CSS" — as seções de perfis
        // que o usuário não tem acesso nem chegam a ser enviadas no HTML.
        // Hoje os components ainda usam dados mock (mesmo aluno pra todo
        // mundo); quando ligar ao banco de verdade, cada section-*.php deve
        // buscar os dados filtrando por $_SESSION['id_usuario'].
        require __DIR__ . '/components/section-dashboard.php';
        require __DIR__ . '/components/section-configuracoes.php';
        require __DIR__ . '/components/section-stub.php';
        ?>
    </main>

    <?php require __DIR__ . '/components/modal-form.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Essas duas listas vêm do PHP (categorias e planos cadastrados) e
        // precisam existir ANTES de backoffice.js carregar, porque os
        // schemas de formulário (BO_FORM_SCHEMAS.produtoForm e .planoAlterar)
        // usam elas direto ao montar os <select> de categoria/plano.
        const BO_CATEGORIAS_OPTIONS = [];
        const BO_PLANOS_OPTIONS = [];

        // Perfil real do usuário logado (vindo da sessão, não escolhido por ele).
        // backoffice.js usa isso pra: (1) abrir direto na seção certa,
        // (2) só o admin conseguir usar o seletor de perfil no header.
        const BO_PERFIL_LOGADO = "<?php echo $perfilLogado; ?>";
        // A visualização passa a respeitar apenas o perfil real da sessão.
        // Não há mais alternância de perfis de demonstração no painel.
        const BO_IS_ADMIN = false;
        const BO_CURRENT_USER = <?php echo json_encode($usuarioDashboard, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const BO_PROFISSIONAIS_SEARCH = <?php echo json_encode($profissionaisPesquisa, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>

    <script src="<?php echo BASE_URL; ?>assets/js/dashboard.js"></script>

</body>

</html>
