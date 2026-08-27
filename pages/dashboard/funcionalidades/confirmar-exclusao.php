<?php
/**
 * funcionalidades/confirmar-exclusao.php
 * Página de confirmação (sem JS) usada por todos os botões "Excluir" do
 * admin. Só faz uma pergunta e devolve um <form method="POST"> apontando
 * pro handler do recurso (ex: produtos.php) com acao=delete — quem
 * efetivamente apaga é sempre o handler do recurso, nunca esta página.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

if (($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Recursos aceitos (evita que o link vire um redirecionador aberto para
// qualquer handler.php arbitrário) e o rótulo da mensagem/ação no rodapé.
$recursosPermitidos = [
    'usuarios' => 'usuarios.php',
    'permissoes' => 'permissoes.php',
    'pagamentos' => 'pagamentos.php',
    'cashbacks' => 'cashbacks.php',
    'categorias' => 'categorias.php',
    'produtos' => 'produtos.php',
    'profissionais' => 'profissionais.php',
];

$recurso = (string) ($_GET['recurso'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$nome = (string) ($_GET['nome'] ?? 'este registro');
$secao = (string) ($_GET['secao'] ?? 'dashboard');

if (!isset($recursosPermitidos[$recurso]) || $id <= 0) {
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php?section=' . urlencode($secao));
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar exclusão · ONE FIT</title>
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
    <main class="bo-main" style="margin-left:0;max-width:520px;padding:48px 24px;">
        <div class="bo-data-panel" style="padding:32px;">
            <div class="bo-page-title">
                <div>
                    <span class="bo-eyebrow"><i class="bi bi-exclamation-triangle"></i> Ação irreversível</span>
                    <h1>Excluir <?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>?</h1>
                    <p>Essa exclusão remove o registro definitivamente do banco de dados e não pode ser desfeita.</p>
                </div>
            </div>

            <form method="POST" action="<?php echo BASE_URL . 'pages/dashboard/funcionalidades/' . $recursosPermitidos[$recurso]; ?>" style="display:flex; gap:12px; margin-top:20px;">
                <input type="hidden" name="acao" value="delete">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="secao" value="<?php echo htmlspecialchars($secao, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <a class="btn-bo-outline" style="flex:1; text-align:center;" href="<?php echo BASE_URL . 'pages/dashboard/dashboard.php?section=' . urlencode($secao); ?>">Cancelar</a>
                <button type="submit" class="btn-bo-gold" style="flex:1; background:#dc3545; border-color:#dc3545; color:#fff;">
                    <i class="bi bi-trash"></i> Sim, excluir
                </button>
            </form>
        </div>
    </main>
</body>

</html>
