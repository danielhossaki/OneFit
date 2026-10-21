<?php
/**
 * database/diretor-recuperar.php — designação do primeiro diretor e recuperação
 * quando não há nenhum diretor ativo (o painel nunca deixa isso acontecer, mas
 * o banco pode chegar a esse estado por fora: migração recém-aplicada, conta
 * desativada direto no banco etc.).
 *
 * Somente pela linha de comando, por quem tem acesso ao servidor e ao .env. O
 * alvo é o banco do .env da cópia onde o script roda; --host e --database
 * precisam coincidir com ele.
 *
 *   php database/diretor-recuperar.php --host=H --database=D --id=N --confirmar=PROMOVER-N
 *
 * Regras (todas conferidas na mesma transação, com trava):
 *   - a migração 20261020_diretor_v1 precisa estar aplicada;
 *   - só roda se NÃO houver nenhum diretor ativo (havendo, use a tela Funcionários);
 *   - a conta N precisa existir, estar ativa e ser admin: nenhuma outra conta é promovida;
 *   - registra 'recuperacao_diretor' em auditoria_eventos (sem dados pessoais).
 * A senha não muda: a pessoa entra com a senha que já tem (ou pela recuperação de senha).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$args = getopt('', ['host:', 'database:', 'id:', 'confirmar:']);
$id = filter_var($args['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!isset($args['host'], $args['database']) || !$id || ($args['confirmar'] ?? '') !== 'PROMOVER-' . $id) {
    fwrite(STDERR, "Uso: --host=... --database=... --id=N --confirmar=PROMOVER-N\n");
    exit(2);
}
require_once __DIR__ . '/../config/env.php';
if ($args['host'] !== onefitEnv('DB_HOST') || $args['database'] !== onefitEnv('DB_NAME')) {
    fwrite(STDERR, "Destino informado difere do banco configurado no .env desta cópia. Nada foi feito.\n");
    exit(2);
}
require __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../config/papeis.php';

$conn->begin_transaction();
try {
    $versao = '20261020_diretor_v1';
    $stmt = $conn->prepare('SELECT versao FROM onefit_migrations WHERE versao = ?');
    $stmt->bind_param('s', $versao);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new RuntimeException('Aplique antes a migração 20261020_diretor_v1 (database/migrate-diretor.php).');
    }
    $stmt->close();
    if (onefitContarDiretoresAtivos($conn, true) > 0) {
        throw new RuntimeException('Já existe Diretor ativo. Promoções e alterações são feitas por ele, na tela Funcionários.');
    }
    $alvo = onefitBuscarSituacaoUsuario($conn, $id, true);
    if (!$alvo || $alvo['status'] !== 'ativo' || $alvo['tipo_usuario'] !== 'admin') {
        throw new RuntimeException('A conta informada precisa existir, estar ativa e ser de administrador.');
    }
    $stmt = $conn->prepare("UPDATE usuarios SET tipo_usuario = 'diretor' WHERE id_usuario = ? AND tipo_usuario = 'admin' AND status = 'ativo'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $alterados = $stmt->affected_rows;
    $stmt->close();
    $gravado = onefitBuscarSituacaoUsuario($conn, $id, true);
    if ($alterados !== 1 || ($gravado['tipo_usuario'] ?? null) !== 'diretor') {
        throw new RuntimeException('O cargo gravado difere do esperado.');
    }
    $detalhes = json_encode(['de' => 'admin', 'para' => 'diretor', 'origem' => 'linha de comando (database/diretor-recuperar.php)'], JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO auditoria_eventos (categoria, acao, id_operador, id_alvo, detalhes) VALUES ('funcionario', 'recuperacao_diretor', NULL, ?, ?)");
    $stmt->bind_param('is', $id, $detalhes);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    echo "Conta $id promovida a Diretor. Evento registrado em auditoria_eventos.\n";
} catch (Throwable $erro) {
    $conn->rollback();
    fwrite(STDERR, 'Nada foi alterado: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}
