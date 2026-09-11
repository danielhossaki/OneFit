<?php
require __DIR__ . '/../../../config/parametros.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => onefitTraduzir('Sua sessão expirou. Entre novamente.')]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => onefitTraduzir('Método não permitido.')]);
    exit;
}
$busca = $_GET['busca'] ?? '';
$status = $_GET['status'] ?? '';
if (!is_string($busca) || !is_string($status) || strlen($busca) > 600
    || !in_array($status, ['', 'aguardando', 'preparando', 'despachado', 'entregue', 'cancelado', 'devolvido', 'extraviado'], true)) {
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
