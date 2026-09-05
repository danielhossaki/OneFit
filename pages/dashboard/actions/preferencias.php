<?php

// Este endpoint retorna JSON inclusive para sessão expirada e método inválido.
// _shared.php redireciona esses casos para páginas HTML.
require_once __DIR__ . '/../../../config/parametros.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function preferencias_responder(bool $ok, string $mensagem, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
if ($idUsuario <= 0) {
    preferencias_responder(false, 'Sua sessão expirou. Entre novamente.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    preferencias_responder(false, 'Método não permitido.', 405);
}
$token = $_POST['csrf_token'] ?? null;
if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    preferencias_responder(false, 'Sua sessão expirou. Atualize a página e tente novamente.', 403);
}

$campos = [
    'lembretes_treino',
    'avisos_agendamentos',
    'atualizacoes_compras',
    'ofertas_novidades',
    'notificacoes_email',
];
$chave = $_POST['key'] ?? null;
$valor = $_POST['value'] ?? null;
if (!is_string($chave) || !is_string($valor)) {
    preferencias_responder(false, 'Preferência inválida.', 422);
}

try {
    require_once __DIR__ . '/../../../config/conn.php';
    if ($chave === 'tema') {
        if (!in_array($valor, ['light', 'dark', 'system'], true)) {
            preferencias_responder(false, 'Tema inválido.', 422);
        }
        $stmt = $conn->prepare(
            'INSERT INTO preferencias_usuario (id_usuario, tema) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE tema = VALUES(tema)'
        );
        $stmt->bind_param('is', $idUsuario, $valor);
    } elseif (in_array($chave, $campos, true) && in_array($valor, ['0', '1'], true)) {
        // A coluna vem exclusivamente da lista permitida; os valores continuam parametrizados.
        $stmt = $conn->prepare(
            "INSERT INTO preferencias_usuario (id_usuario, {$chave}) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE {$chave} = VALUES({$chave})"
        );
        $valorBooleano = (int) $valor;
        $stmt->bind_param('ii', $idUsuario, $valorBooleano);
    } else {
        preferencias_responder(false, 'Preferência inválida.', 422);
    }

    $stmt->execute();
    $stmt->close();
    preferencias_responder(true, 'Preferência salva.');
} catch (Throwable $erro) {
    error_log('Falha ao salvar preferência do painel ONE FIT. Código: ' . $erro->getCode());
    preferencias_responder(false, 'Não foi possível salvar. Tente novamente.', 500);
}
