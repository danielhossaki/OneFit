<?php
// Endpoint JSON próprio: _shared.php aceita apenas POST e redireciona erros.
require_once __DIR__ . '/../../../config/parametros.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function notificacoesResponder(array $dados, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

$usuarioId = (int) ($_SESSION['id_usuario'] ?? 0);
if ($usuarioId <= 0) notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Sua sessão expirou. Entre novamente.')], 401);
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Método não permitido.')], 405);
}
if ($metodo === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Atualize a página e tente novamente.')], 403);
    }
}
session_write_close();

try {
    require_once __DIR__ . '/../../../config/conn.php';
    require_once __DIR__ . '/../../../config/notificacoes.php';
    if ($metodo === 'POST') {
        $acao = $_POST['acao'] ?? 'marcar_lidas';
        if (!is_string($acao) || !in_array($acao, ['marcar_lidas', 'apagar', 'apagar_todas'], true)) {
            notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Ação inválida.')], 400);
        }
        if ($acao === 'marcar_lidas') {
            marcarNotificacoesComoLidas($usuarioId);
        } else {
            $id = $_POST['id'] ?? null;
            if ($acao === 'apagar' && (!is_string($id) || !ctype_digit($id) || !filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) {
                notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Ação inválida.')], 400);
            }
            // A propriedade é exigida também para administradores. Preserve o ledger de eventos.
            $stmt = $conn->prepare($acao === 'apagar'
                ? 'DELETE FROM notificacoes WHERE usuario_id = ? AND id = ?'
                : 'DELETE FROM notificacoes WHERE usuario_id = ?');
            if ($acao === 'apagar') $stmt->bind_param('ii', $usuarioId, $id);
            else $stmt->bind_param('i', $usuarioId);
            $stmt->execute();
            $removidas = $stmt->affected_rows;
            $stmt->close();
            if ($acao === 'apagar' && $removidas === 0) {
                notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Notificação não encontrada.')], 404);
            }
        }
    }
    notificacoesResponder(['ok' => true] + buscarNotificacoes($usuarioId));
} catch (Throwable $erro) {
    error_log('Falha no endpoint de notificações ONE FIT. Código: ' . $erro->getCode());
    notificacoesResponder(['ok' => false, 'message' => onefitTraduzir('Não foi possível atualizar as notificações. Tente novamente.')], 500);
}
