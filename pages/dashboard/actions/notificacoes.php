<?php
// Endpoint JSON próprio: _shared.php aceita apenas POST e redireciona erros.
require_once __DIR__ . '/../../../config/parametros.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function notificacoesResponder(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$usuarioId = (int) ($_SESSION['id_usuario'] ?? 0);
if ($usuarioId <= 0) notificacoesResponder(['ok' => false, 'message' => 'Sua sessão expirou. Entre novamente.'], 401);
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    notificacoesResponder(['ok' => false, 'message' => 'Método não permitido.'], 405);
}
if ($metodo === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        notificacoesResponder(['ok' => false, 'message' => 'Atualize a página e tente novamente.'], 403);
    }
}
session_write_close();

try {
    require_once __DIR__ . '/../../../config/conn.php';
    require_once __DIR__ . '/../../../config/notificacoes.php';
    if ($metodo === 'POST') marcarNotificacoesComoLidas($usuarioId);
    notificacoesResponder(['ok' => true] + buscarNotificacoes($usuarioId));
} catch (Throwable $erro) {
    error_log('Falha no endpoint de notificações ONE FIT. Código: ' . $erro->getCode());
    notificacoesResponder(['ok' => false, 'message' => 'Não foi possível atualizar as notificações. Tente novamente.'], 500);
}
