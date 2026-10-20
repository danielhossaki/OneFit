<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Prepara, no banco descartável onefit_interface_test_*, os casos da autorização
// de entrada da catraca (OneFit.Turnstile/AutorizacaoCatraca.cs) e grava o
// gabarito "id;liberado;motivo" para tests/catraca-autorizacao.cs.
// Orquestrado por tests/catraca-autorizacao.ps1. Só dados sintéticos.
$pasta = sys_get_temp_dir() . '/onefit-interface-fixture';
$fixture = json_decode(file_get_contents($pasta . '/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/../pages/dashboard/includes/matriculas.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
$plano = (int) $db->query('SELECT MIN(id_plano) FROM cadastro_planos')->fetch_row()[0];
if ($plano < 1) {
    throw new RuntimeException('Fixture sem plano.');
}

$run = random_int(100000, 899999);
$n = 0;
$casos = [];
$pessoa = static function (string $cargo, string $status = 'ativo') use ($db, $run, &$n): int {
    $n++;
    $nome = "Catraca $run $n";
    $email = "catraca{$run}x{$n}@example.test";
    $cpf = sprintf('%011d', $run * 1000 + $n);
    $senha = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 3', 'Santos/SP', ?, 1, '(11) 90000-0002', ?, ?)");
    $stmt->bind_param('ssssss', $nome, $senha, $cpf, $email, $cargo, $status);
    $stmt->execute();
    return (int) $stmt->insert_id;
};
$matricula = static function (int $id, string $status, int $inicio, int $fim) use ($db, $plano): void {
    $stmt = $db->prepare("INSERT INTO matricula (id_usuario, id_plano, data_matricula, data_inicio, data_fim, status, valor_contratado)
        VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 100)");
    $stmt->bind_param('iiiis', $id, $plano, $inicio, $fim, $status);
    $stmt->execute();
};
$cadastro = static function (int $id, string $status) use ($db, $run): void {
    $email = "catracaprof{$run}x{$id}@example.test";
    $stmt = $db->prepare("INSERT INTO cadastro_profissional (id_usuario, nome, email, status) VALUES (?, 'Catraca prof', ?, ?)");
    $stmt->bind_param('iss', $id, $email, $status);
    $stmt->execute();
};
$caso = static function (int $id, bool $liberado, string $motivo, string $rotulo) use (&$casos): void {
    $casos[] = "$id;" . ($liberado ? '1' : '0') . ";$motivo;$rotulo";
};
$semMatricula = 'sem matrícula válida hoje';

// Aluno: matrícula 'ativa' com hoje entre data_inicio e data_fim (inclusive).
$id = $pessoa('aluno'); $matricula($id, 'ativa', -10, 20); $caso($id, true, 'ok', 'aluno com matrícula vigente');
$id = $pessoa('aluno'); $matricula($id, 'ativa', 0, 30); $caso($id, true, 'ok', 'matrícula que começa hoje');
$id = $pessoa('aluno'); $matricula($id, 'ativa', -30, 0); $caso($id, true, 'ok', 'matrícula que termina hoje');
$id = $pessoa('aluno'); $matricula($id, 'ativa', -40, -1); $caso($id, false, $semMatricula, 'matrícula ativa vencida ontem');
$id = $pessoa('aluno'); $matricula($id, 'ativa', 1, 30); $caso($id, false, $semMatricula, 'matrícula que começa amanhã');
foreach (['pendente', 'vencida', 'cancelada'] as $status) {
    $id = $pessoa('aluno'); $matricula($id, $status, -10, 20); $caso($id, false, $semMatricula, "matrícula $status no período");
}
$id = $pessoa('aluno'); $caso($id, false, $semMatricula, 'aluno sem matrícula');
$id = $pessoa('aluno'); $matricula($id, 'vencida', -60, -31); $matricula($id, 'ativa', -5, 25); $caso($id, true, 'ok', 'aluno com matrícula antiga e uma vigente');
foreach (['inativo', 'bloqueado'] as $status) {
    $id = $pessoa('aluno', $status); $matricula($id, 'ativa', -10, 20); $caso($id, false, 'conta inativa ou bloqueada', "aluno $status com matrícula vigente");
}
// Ponta a ponta com as rotinas reais de inscrição e confirmação (includes/matriculas.php).
$inscrever = static function (int $id) use ($db, $plano): int {
    $db->begin_transaction();
    $m = bo_matricula_inserir_pendente($db, $id, $plano, 100.00);
    [$p] = bo_pagamento_inserir_inscricao($db, $m, 100.00, 'pix');
    $db->commit();
    return $p;
};
$id = $pessoa('aluno'); $inscrever($id); $caso($id, false, $semMatricula, 'inscrição com cobrança pendente');
$id = $pessoa('aluno'); $p = $inscrever($id);
bo_matricula_confirmar_pagamento($db, $p, 100.00, 'gateway'); $caso($id, true, 'ok', 'inscrição confirmada');
$id = $pessoa('aluno'); $p = $inscrever($id);
foreach ([1, 2, 3] as $vez) { bo_matricula_confirmar_pagamento($db, $p, 100.00, 'gateway'); }
$caso($id, true, 'ok', 'inscrição confirmada com callback repetido');
$id = $pessoa('aluno'); $p = $inscrever($id);
$db->query("UPDATE pagamento SET status = 'recusado' WHERE id_pagamento = $p");
try { bo_matricula_confirmar_pagamento($db, $p, 100.00, 'gateway'); } catch (MatriculaRecusada $recusa) { /* esperado */ }
$caso($id, false, $semMatricula, 'inscrição com cobrança recusada');
// Como o antigo "Pagar" deixava: status 'ativa' com data_fim 0000-00-00.
$id = $pessoa('aluno'); $p = $inscrever($id);
$db->query("SET SESSION sql_mode = ''");
$db->query("UPDATE matricula SET status = 'ativa', data_fim = '0000-00-00' WHERE id_usuario = $id");
$db->query('SET SESSION sql_mode = DEFAULT');
$caso($id, false, $semMatricula, "matrícula 'ativa' com data_fim zerada");
// Datas que o MySQL remoto (sem modo estrito) aceita: zeradas, com mês ou dia zero, período invertido.
$datas = static function (string $rotulo, string $inicio, string $fim, ?string $status = 'ativa') use ($db, $pessoa, $matricula, $caso, $semMatricula): void {
    $id = $pessoa('aluno'); $matricula($id, 'ativa', -10, 20);
    $db->query("SET SESSION sql_mode = ''");
    $stmt = $db->prepare("UPDATE matricula SET data_inicio = $inicio, data_fim = $fim, status = ? WHERE id_usuario = ?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $db->query('SET SESSION sql_mode = DEFAULT');
    $caso($id, false, $semMatricula, $rotulo);
};
$datas("data_inicio zerada e data_fim futura", "'0000-00-00'", 'DATE_ADD(CURDATE(), INTERVAL 20 DAY)');
$datas('data_inicio com mês zero', "CONCAT(YEAR(CURDATE()), '-00-01')", 'DATE_ADD(CURDATE(), INTERVAL 20 DAY)');
$datas('data_inicio com dia zero', "CONCAT(YEAR(CURDATE()), '-01-00')", 'DATE_ADD(CURDATE(), INTERVAL 20 DAY)');
$datas('data_fim com mês zero no ano seguinte', 'DATE_SUB(CURDATE(), INTERVAL 10 DAY)', "CONCAT(YEAR(CURDATE()) + 1, '-00-15')");
$datas('período invertido (início depois do fim)', 'DATE_ADD(CURDATE(), INTERVAL 20 DAY)', 'DATE_SUB(CURDATE(), INTERVAL 10 DAY)');
$datas('status nulo no período', 'DATE_SUB(CURDATE(), INTERVAL 10 DAY)', 'DATE_ADD(CURDATE(), INTERVAL 20 DAY)', null);
// Professor (e o legado profissional): cadastro_profissional ativo.
$id = $pessoa('professor'); $cadastro($id, 'ativo'); $caso($id, true, 'ok', 'professor com cadastro ativo');
$id = $pessoa('professor'); $cadastro($id, 'inativo'); $caso($id, false, 'cadastro profissional inativo', 'professor com cadastro inativo');
$id = $pessoa('professor'); $caso($id, false, 'cadastro profissional inativo', 'professor sem cadastro');
$id = $pessoa('profissional'); $cadastro($id, 'ativo'); $caso($id, true, 'ok', 'profissional (legado) com cadastro ativo');
$id = $pessoa('professor', 'inativo'); $cadastro($id, 'ativo'); $caso($id, false, 'conta inativa ou bloqueada', 'professor inativo');
$id = $pessoa('aluno'); $cadastro($id, 'ativo'); $caso($id, false, $semMatricula, 'aluno com cadastro profissional não vira professor');
// Demais cargos: só conta ativa, sem matrícula.
foreach (['admin', 'recepcionista', 'vendedor', 'faxineira'] as $cargo) {
    $id = $pessoa($cargo); $caso($id, true, 'ok', "$cargo ativo sem matrícula");
    $id = $pessoa($cargo, 'inativo'); $caso($id, false, 'conta inativa ou bloqueada', "$cargo inativo");
}
// Cargo fora do ENUM num servidor sem modo estrito (como o remoto) vira ''.
$id = $pessoa('aluno'); $matricula($id, 'ativa', -10, 20);
$db->query("SET SESSION sql_mode = ''");
$db->query("UPDATE usuarios SET tipo_usuario = 'gerente' WHERE id_usuario = $id");
$db->query('SET SESSION sql_mode = DEFAULT');
$caso($id, false, 'cargo desconhecido', 'cargo inválido gravado como vazio');
$caso(999999999, false, 'pessoa não encontrada', 'id inexistente');

file_put_contents($pasta . '/catraca-casos.txt', implode("\n", $casos) . "\n");
echo 'Catraca: ' . count($casos) . " casos preparados no banco descartável.\n";
