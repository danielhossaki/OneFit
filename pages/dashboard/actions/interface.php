<?php
require_once __DIR__ . '/../../../config/parametros.php';
require_once __DIR__ . '/../../../config/conn.php';
header('Cache-Control: no-store');
function interfaceFalha(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo onefitTraduzir($message); exit;
}
$id = (int) ($_SESSION['id_usuario'] ?? 0);
if (!$id) interfaceFalha(401, onefitTraduzir('Sua sessão expirou. Entre novamente.'));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); interfaceFalha(405, onefitTraduzir('Método não permitido.')); }
$csrf = $_POST['csrf_token'] ?? null;
if (!is_string($csrf) || !$csrf || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) interfaceFalha(403, onefitTraduzir('Sua sessão expirou. Atualize a página e tente novamente.'));
$key = $_POST['configuracao'] ?? null;
$value = $_POST['valor'] ?? null;
if (!is_string($value)) interfaceFalha(422, onefitTraduzir('Preferência inválida.'));
if ($key === 'idioma' && $value === 'pt') $value = 'pt-BR';
try {
    if ($key === 'idioma' && in_array($value, onefitIdiomas(), true)) {
        $stmt = $conn->prepare('INSERT INTO preferencias_usuario (id_usuario, idioma) VALUES (?, ?) ON DUPLICATE KEY UPDATE idioma = VALUES(idioma)');
        $stmt->bind_param('is', $id, $value); $stmt->execute(); $stmt->close();
        $_SESSION['idioma'] = $value;
    } elseif ($key === 'tema_cores' && in_array($value, onefitTemas(), true)) {
        $conn->begin_transaction();
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'admin' AND status = 'ativo' FOR UPDATE");
        $stmt->bind_param('i', $id); $stmt->execute();
        $allowed = (bool) $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$allowed) { $conn->rollback(); interfaceFalha(403, onefitTraduzir('Acesso não autorizado.')); }
        $stmt = $conn->prepare("INSERT INTO configuracoes_site (chave,valor) VALUES ('tema_cores',?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
        $stmt->bind_param('s', $value); $stmt->execute(); $stmt->close();
        $conn->commit();
    } else interfaceFalha(422, onefitTraduzir('Preferência inválida.'));
    $_SESSION['bo_flash'] = ['type' => 'success', 'text' => onefitTraduzir('Preferência salva.')];
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php?section=configuracoes', true, 303);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('ONE FIT: interface save failed; code=' . $e->getCode());
    interfaceFalha(503, onefitTraduzir('Não foi possível salvar. Tente novamente.'));
}
