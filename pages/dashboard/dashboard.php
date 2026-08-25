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
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require __DIR__ . '/includes/helpers.php';

// Sem sessão -> manda pro login. O acesso ao dashboard inteiro depende de
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Carrega o perfil real do usuário autenticado para preencher a tela e o modal.
$stmtUsuario = $conn->prepare(
    'SELECT nome, nacionalidade, data_nascimento, genero, cpf, endereco,
            cidade_estado, email, celular, altura, peso, foto, objetivo, data_cadastro
     FROM usuarios WHERE id_usuario = ? LIMIT 1'
);
$stmtUsuario->bind_param('i', $_SESSION['id_usuario']);
$stmtUsuario->execute();
$usuarioBanco = $stmtUsuario->get_result()->fetch_assoc();
$stmtUsuario->close();

if (!$usuarioBanco) {
    header('Location: ' . BASE_URL . 'config/logout.php');
    exit;
}

$cidadeEstado = explode('/', $usuarioBanco['cidade_estado'] ?? '', 2);
$usuarioDashboard = [
    'nome' => $usuarioBanco['nome'],
    'documento' => $usuarioBanco['cpf'],
    'email' => $usuarioBanco['email'],
    'telefone' => $usuarioBanco['celular'],
    'nacionalidade' => $usuarioBanco['nacionalidade'],
    'nascimento' => $usuarioBanco['data_nascimento'],
    'genero' => $usuarioBanco['genero'],
    'endereco' => $usuarioBanco['endereco'],
    'cidade' => trim($cidadeEstado[0] ?? ''),
    'estado' => trim($cidadeEstado[1] ?? ''),
    'altura' => $usuarioBanco['altura'],
    'peso' => $usuarioBanco['peso'],
    'foto' => $usuarioBanco['foto'],
];

require __DIR__ . '/includes/db-data.php';

// Busca de profissionais no header: usa a equipe cadastrada no banco
// (populada em includes/db-data.php só para o perfil admin) ou consulta
// direta para os demais perfis, restrita aos ativos.
if ($perfilLogado === 'admin') {
    $profissionaisPesquisa = array_values(array_map(
        static fn(array $profissional): array => [
            'id' => (int) $profissional['id'],
            'nome' => $profissional['nome'],
            'funcao' => $profissional['funcao'],
            'especialidade' => $profissional['tituloCard'],
        ],
        array_filter($profissionaisAdm, static fn(array $profissional): bool => $profissional['status'] === 'ativo')
    ));
} else {
    $profissionaisPesquisa = [];
    $rBusca = $conn->query("SELECT id_profissional, nome, especialidade FROM cadastro_profissional WHERE status = 'ativo' ORDER BY nome");
    while ($rowBusca = $rBusca->fetch_assoc()) {
        $profissionaisPesquisa[] = [
            'id' => (int) $rowBusca['id_profissional'],
            'nome' => $rowBusca['nome'],
            'funcao' => $rowBusca['especialidade'],
            'especialidade' => $rowBusca['especialidade'],
        ];
    }
}
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel · ONE FIT</title>
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
        // Cada section-*.php busca os dados reais (includes/db-data.php)
        // já filtrados por $_SESSION['id_usuario'] quando aplicável.
        if ($perfilLogado === 'admin') {
            require __DIR__ . '/components/section-admin.php';
        } elseif ($perfilLogado === 'profissional') {
            require __DIR__ . '/components/section-profissional.php';
        } else {
            require __DIR__ . '/components/section-aluno.php';
        }
        require __DIR__ . '/components/section-configuracoes.php';
        require __DIR__ . '/components/section-stub.php';
        ?>
    </main>

    <?php require __DIR__ . '/components/modal-form.php'; ?>
    <?php if ($perfilLogado === 'aluno'): ?>
        <?php require __DIR__ . '/components/modal-pagar-plano.php'; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Essas duas listas vêm do PHP (categorias e planos cadastrados) e
        // precisam existir ANTES de dashboard.js carregar, porque os
        // schemas de formulário (BO_FORM_SCHEMAS.produtoForm e .planoAlterar)
        // usam elas direto ao montar os <select> de categoria/plano.
        const BO_CATEGORIAS_OPTIONS = [];
        const BO_PLANOS_OPTIONS = [];

        // Perfil real do usuário logado (vindo da sessão, não escolhido por ele).
        // dashboard.js usa isso pra: (1) abrir direto na seção certa,
        // (2) só o admin conseguir usar o seletor de perfil no header.
        const BO_PERFIL_LOGADO = "<?php echo $perfilLogado; ?>";
        // A visualização passa a respeitar apenas o perfil real da sessão.
        // Não há mais alternância de perfis de demonstração no painel.
        const BO_IS_ADMIN = false;
        const BO_MARKETPLACE_URL = <?php echo json_encode(BASE_URL . 'pages/marketplace/marketplace.php'); ?>;
        const BO_CURRENT_USER = <?php echo json_encode($usuarioDashboard, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const BO_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        const BO_PROFILE_UPDATE_URL = <?php echo json_encode(BASE_URL . 'pages/dashboard/actions/update-profile.php'); ?>;
        const BO_PROFISSIONAIS_SEARCH = <?php echo json_encode($profissionaisPesquisa, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>

    <script src="<?php echo BASE_URL; ?>assets/js/dashboard.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/js/dashboard.js'); ?>"></script>

</body>

</html>
