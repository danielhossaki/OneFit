<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['id_usuario']) || ($_SESSION['tipo_usuario'] ?? '') !== 'aluno') {
    http_response_code(403);
    echo json_encode(['error' => 'Entre como aluno para acessar o treino.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sua sessão expirou. Atualize a página.']);
    exit;
}
try {
    require __DIR__ . '/../../../config/conn.php';
    require __DIR__ . '/../includes/treino.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    bo_treino_alterar($conn, (int) $_SESSION['id_usuario'], $_POST);
    echo json_encode(['ok' => true, 'exercicios' => bo_treino_carregar($conn, (int) $_SESSION['id_usuario'])], JSON_THROW_ON_ERROR);
} catch (DomainException $erro) {
    http_response_code(422);
    echo json_encode(['error' => $erro->getMessage()]);
} catch (Throwable $erro) {
    error_log('ONE FIT treino: código ' . $erro->getCode());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o treino. Tente novamente.']);
}
