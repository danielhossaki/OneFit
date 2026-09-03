<?php

require __DIR__ . '/_shared.php';

header('Content-Type: application/json; charset=UTF-8');

function preferencias_responder(bool $ok, string $mensagem, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    preferencias_responder(false, 'Sua sessão expirou. Atualize a página e tente novamente.', 403);
}

$campos = [
    'lembretes_treino',
    'avisos_agendamentos',
    'atualizacoes_compras',
    'ofertas_novidades',
    'notificacoes_email',
];
$chave = (string) ($_POST['key'] ?? '');
$valor = (string) ($_POST['value'] ?? '');
$idUsuario = (int) $_SESSION['id_usuario'];

try {
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
    error_log('Falha segura ao salvar preferência do painel ONE FIT.');
    preferencias_responder(false, 'Não foi possível salvar. Tente novamente.', 500);
}
