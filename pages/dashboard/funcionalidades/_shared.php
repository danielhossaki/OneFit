<?php
/**
 * funcionalidades/_shared.php
 * Bootstrap comum a todos os handlers de CRUD do admin nesta pasta:
 * conexão, autenticação, checagem de admin/CSRF e helpers de
 * redirecionamento com mensagem (flash). Cada arquivo desta pasta
 * (usuarios.php, produtos.php, ...) faz `require __DIR__ . '/_shared.php';`
 * antes de processar o POST.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require __DIR__ . '/../includes/helpers.php';

if (($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php');
    exit;
}

/**
 * Guarda uma mensagem para ser exibida (uma única vez) na próxima
 * renderização do dashboard.php, logo após o redirecionamento.
 */
function bo_flash(string $type, string $text): void
{
    $_SESSION['bo_flash'] = ['type' => $type, 'text' => $text];
}

/**
 * Volta para a seção do backoffice de onde a ação partiu.
 */
function bo_redirect(string $section): never
{
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php?section=' . urlencode($section ?: 'dashboard'));
    exit;
}

/**
 * Confere o token CSRF do formulário. Em caso de falha, já redireciona
 * de volta (com a mensagem de erro) e encerra o script.
 */
function bo_check_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        bo_flash('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
        bo_redirect((string) ($_POST['secao'] ?? 'dashboard'));
    }
}

function bo_str(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function bo_num(string $key): float
{
    $v = str_replace(',', '.', (string) ($_POST[$key] ?? 0));
    return is_numeric($v) ? (float) $v : 0.0;
}

/**
 * Seção para onde redirecionar ao final (vem de um <input type="hidden"
 * name="secao"> em todo formulário, já que não há JS guardando a aba atual).
 */
function bo_secao_atual(): string
{
    return bo_str('secao') ?: 'dashboard';
}
