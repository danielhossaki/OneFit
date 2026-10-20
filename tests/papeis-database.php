<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Revalidação de sessão e checagem de cargo contra o banco descartável
// onefit_interface_test_* (tests/prepare-interface-fixture.php). Nunca usa a
// conexão configurada da aplicação.
require __DIR__ . '/../config/papeis.php';

$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');

$checks = 0;
function papeisDbCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function papeisDbSituacao(mysqli $db, int $id): array
{
    return $db->query('SELECT tipo_usuario, status FROM usuarios WHERE id_usuario = ' . $id)->fetch_assoc();
}

// Cargos novos só no banco descartável, para exercitar a política.
$db->query("ALTER TABLE usuarios MODIFY tipo_usuario ENUM('aluno','profissional','admin','vendedor','professor','recepcionista','faxineira') DEFAULT 'aluno'");
$original = [];
foreach ([1, 2, 3, 4] as $id) {
    $original[$id] = papeisDbSituacao($db, $id);
}

try {
    // --- Leitura da situação e checagem de cargo no banco --------------------
    papeisDbCheck(onefitBuscarSituacaoUsuario($db, 1)['tipo_usuario'] === 'admin', 'Situação do admin 1');
    papeisDbCheck(onefitBuscarSituacaoUsuario($db, 0) === null && onefitBuscarSituacaoUsuario($db, 999999) === null, 'Conta inexistente');
    papeisDbCheck(onefitExigirPapel($db, 1, ['admin']), 'Admin ativo autorizado');
    papeisDbCheck(!onefitExigirPapel($db, 6, ['admin']), 'Admin inativo recusado');
    papeisDbCheck(!onefitExigirPapel($db, 3, ['admin']), 'Aluno não é admin');
    papeisDbCheck(onefitExigirPapel($db, 4, ['professor']), 'profissional no banco atende professor');
    papeisDbCheck(!onefitExigirPapel($db, 4, onefitPapeisCom('opera_cadastro_facial')), 'Professor não opera cadastro facial');
    $db->begin_transaction();
    papeisDbCheck(onefitExigirPapel($db, 1, ['admin'], true), 'Checagem com trava (FOR UPDATE)');
    $db->rollback();

    // --- Revalidação da sessão ----------------------------------------------
    $_SESSION = ['id_usuario' => 3, 'tipo_usuario' => 'aluno', 'csrf_token' => 'x', 'idioma' => 'en'];
    onefitSessaoRevalidar($db);
    papeisDbCheck(($_SESSION['id_usuario'] ?? null) === 3 && $_SESSION['tipo_usuario'] === 'aluno' && $_SESSION['csrf_token'] === 'x', 'Sessão válida preservada');

    $_SESSION = ['id_usuario' => 3, 'tipo_usuario' => 'admin'];
    onefitSessaoRevalidar($db);
    papeisDbCheck($_SESSION['tipo_usuario'] === 'aluno', 'Cargo forjado na sessão é corrigido pelo banco');

    $_SESSION = ['id_usuario' => 6, 'tipo_usuario' => 'admin', 'idioma' => 'en'];
    onefitSessaoRevalidar($db);
    papeisDbCheck(!isset($_SESSION['id_usuario'], $_SESSION['tipo_usuario']), 'Conta inativa perde a sessão');
    papeisDbCheck(($_SESSION['login_tipo'] ?? '') === 'erro' && ($_SESSION['idioma'] ?? '') === 'en', 'Aviso de login preservando o idioma');
    papeisDbCheck(($_SESSION['login_msg'] ?? '') === 'Your session has ended. Sign in again to continue.', 'Aviso traduzido');

    $_SESSION = ['id_usuario' => 999999, 'tipo_usuario' => 'admin'];
    onefitSessaoRevalidar($db);
    papeisDbCheck(!isset($_SESSION['id_usuario']), 'Conta apagada perde a sessão');

    foreach (['faxineira', 'recepcionista'] as $semPainel) {
        $db->query("UPDATE usuarios SET tipo_usuario = '$semPainel' WHERE id_usuario = 2");
        $_SESSION = ['id_usuario' => 2, 'tipo_usuario' => 'admin'];
        onefitSessaoRevalidar($db);
        papeisDbCheck(!isset($_SESSION['id_usuario']), "Conta que virou $semPainel perde a sessão");
    }

    $db->query("UPDATE usuarios SET status = 'bloqueado' WHERE id_usuario = 1");
    $_SESSION = ['id_usuario' => 1, 'tipo_usuario' => 'admin'];
    onefitSessaoRevalidar($db);
    papeisDbCheck(!isset($_SESSION['id_usuario']), 'Conta bloqueada perde a sessão');

    $db->query("UPDATE usuarios SET tipo_usuario = 'professor' WHERE id_usuario = 4");
    $_SESSION = ['id_usuario' => 4, 'tipo_usuario' => 'profissional'];
    onefitSessaoRevalidar($db);
    papeisDbCheck($_SESSION['tipo_usuario'] === 'professor' && onefitPainelDoPapel($_SESSION['tipo_usuario']) === 'profissional', 'professor usa o painel atual do profissional');

    // Falha na consulta nega por padrão.
    $fechada = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
    $fechada->close();
    $_SESSION = ['id_usuario' => 3, 'tipo_usuario' => 'aluno'];
    onefitSessaoRevalidar($fechada);
    papeisDbCheck(!isset($_SESSION['id_usuario']), 'Falha no banco encerra a sessão');
} finally {
    $stmt = $db->prepare('UPDATE usuarios SET tipo_usuario = ?, status = ? WHERE id_usuario = ?');
    foreach ($original as $id => $linha) {
        $stmt->bind_param('ssi', $linha['tipo_usuario'], $linha['status'], $id);
        $stmt->execute();
    }
    $stmt->close();
}
foreach ($original as $id => $linha) {
    papeisDbCheck(papeisDbSituacao($db, $id) === $linha, "Banco descartável restaurado ($id)");
}

echo "Papéis no banco isolado: $checks verificações passaram.\n";
