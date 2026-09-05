<?php
/**
 * actions/_shared.php
 * Bootstrap comum aos handlers desta pasta (perfil e senha do próprio
 * usuário logado — qualquer perfil, não só admin). Mesmo padrão de
 * funcionalidades/_shared.php: conexão, autenticação, CSRF e helpers de
 * redirecionamento com mensagem (flash), mas sem exigir tipo_usuario=admin.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . 'pages/login/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php');
    exit;
}

function bo_flash(string $type, string $text): void
{
    $_SESSION['bo_flash'] = ['type' => $type, 'text' => $text];
}

function bo_redirect_perfil(): never
{
    $section = ($_SESSION['tipo_usuario'] ?? '') === 'aluno' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'update-profile.php' ? 'perfil' : 'configuracoes';
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php?section=' . $section);
    exit;
}

function bo_check_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        bo_flash('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
        bo_redirect_perfil();
    }
}

function bo_str(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}
