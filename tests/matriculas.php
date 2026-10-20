<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Ciclo de vida da matrícula (pages/dashboard/includes/matriculas.php) contra o
// banco descartável onefit_interface_test_*, já migrado por
// tests/funcionarios-preparar.ps1. Só dados sintéticos.
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/../pages/dashboard/includes/matriculas.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
if (!bo_funcionarios_migracao($db)['ok']) {
    throw new RuntimeException('Rode antes tests/funcionarios-preparar.ps1 (migração no banco de teste).');
}

$checks = 0;
function mCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function mUm(mysqli $db, string $sql): ?array
{
    return $db->query($sql)->fetch_assoc() ?: null;
}
function mRetrato(mysqli $db): array
{
    return [
        $db->query('SELECT * FROM matricula ORDER BY id_matricula')->fetch_all(MYSQLI_ASSOC),
        $db->query('SELECT * FROM pagamento ORDER BY id_pagamento')->fetch_all(MYSQLI_ASSOC),
        (int) $db->query('SELECT COUNT(*) FROM auditoria_eventos')->fetch_row()[0],
    ];
}
/** A operação precisa falhar com a exceção (e o motivo) esperados, sem gravar nada. */
function mRecusa(mysqli $db, callable $acao, string $classe, ?string $motivo, string $rotulo): void
{
    $antes = mRetrato($db);
    try {
        $acao();
    } catch (Throwable $erro) {
        mCheck($erro instanceof $classe, "$rotulo: " . get_class($erro) . ', esperado ' . $classe);
        if ($motivo !== null) {
            mCheck($erro->motivo === $motivo, "$rotulo: motivo {$erro->motivo}, esperado $motivo");
        }
        mCheck(mRetrato($db) === $antes, "$rotulo: nada gravado");
        return;
    }
    throw new RuntimeException("$rotulo: deveria ter sido recusado");
}

$run = random_int(100000, 899999);
$n = 0;
$hoje = $db->query('SELECT CURDATE()')->fetch_row()[0];
$mais30 = $db->query('SELECT DATE_ADD(CURDATE(), INTERVAL 30 DAY)')->fetch_row()[0];
$aluno = static function (string $status = 'ativo') use ($db, $run, &$n): int {
    $n++;
    $nome = "Matricula $run $n";
    $email = "matricula{$run}x{$n}@example.test";
    $cpf = sprintf('%011d', $run * 1000 + $n + 500);
    $senha = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 4', 'Santos/SP', ?, 1, '(11) 90000-0003', 'aluno', ?)");
    $stmt->bind_param('sssss', $nome, $senha, $cpf, $email, $status);
    $stmt->execute();
    return (int) $stmt->insert_id;
};
$plano = static function (int $dias) use ($db, $run): int {
    $nome = "Plano teste $run $dias";
    $stmt = $db->prepare("INSERT INTO cadastro_planos (nome, valor, duracao_dias, status) VALUES (?, 99.90, ?, 'ativo')");
    $stmt->bind_param('si', $nome, $dias);
    $stmt->execute();
    return (int) $stmt->insert_id;
};
$inscrever = static function (int $idUsuario, int $idPlano, string $forma = 'pix') use ($db): array {
    $db->begin_transaction();
    $idMatricula = bo_matricula_inserir_pendente($db, $idUsuario, $idPlano, 99.90);
    [$idPagamento, $codigo] = bo_pagamento_inserir_inscricao($db, $idMatricula, 99.90, $forma);
    $db->commit();
    return [$idMatricula, $idPagamento, $codigo];
};
$matricula = static fn(int $id): array => mUm($db, "SELECT status, data_matricula, data_inicio, data_fim FROM matricula WHERE id_matricula = $id");
$pagamento = static fn(int $id): array => mUm($db, "SELECT status, data_pagamento, valor, codigo_transacao FROM pagamento WHERE id_pagamento = $id");
$plano30 = $plano(30);

// --- Inscrição ---------------------------------------------------------------------
[$m1, $p1, $codigo1] = $inscrever($aluno(), $plano30);
$mat = $matricula($m1);
mCheck($mat === ['status' => 'pendente', 'data_matricula' => $hoje, 'data_inicio' => $hoje, 'data_fim' => $hoje], 'Inscrição: matrícula pendente com datas válidas (sem 0000-00-00)');
$pag = $pagamento($p1);
mCheck($pag['status'] === 'pendente' && $pag['data_pagamento'] === null && $pag['valor'] === '99.90' && $pag['codigo_transacao'] === $codigo1 && str_starts_with($codigo1, "MAT-$m1-"), 'Inscrição: cobrança pendente, sem data de pagamento');
mRecusa($db, static fn() => bo_pagamento_inserir_inscricao($db, $m1, 99.90, 'boleto'), InvalidArgumentException::class, null, 'Forma de pagamento fora da lista');

// Causa dos 0000-00-00 de produção: o INSERT antigo omitia data_fim (NOT NULL, sem padrão).
$idLegado = $aluno();
$insertAntigo = "INSERT INTO matricula (id_usuario, id_plano, data_matricula, data_inicio, status, valor_contratado) VALUES ($idLegado, $plano30, CURDATE(), CURDATE(), 'pendente', 99.90)";
$db->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
try {
    $db->query($insertAntigo);
    mCheck(false, 'INSERT antigo deveria falhar em modo estrito');
} catch (mysqli_sql_exception $erro) {
    mCheck($erro->getCode() === 1364, 'Em modo estrito o INSERT antigo da inscrição falha (data_fim sem valor)');
}
$db->query("SET SESSION sql_mode = ''");
$db->query($insertAntigo);
$mLegado = (int) $db->insert_id;
$db->query('SET SESSION sql_mode = DEFAULT');
mCheck($matricula($mLegado)['data_fim'] === '0000-00-00', 'Sem modo estrito (como o remoto) ele grava data_fim 0000-00-00');
$db->query("INSERT INTO pagamento (id_matricula, valor, data_vencimento, forma_pagamento, status, codigo_transacao) VALUES ($mLegado, 99.90, CURDATE(), 'pix', 'pendente', 'MAT-$mLegado-LEGADO')");
$pLegado = (int) $db->insert_id;

// --- Cobrança pendente, recusada, cancelada ou divergente não ativa ------------------
mCheck($matricula($m1)['status'] === 'pendente' && $pagamento($p1)['status'] === 'pendente', 'Sem confirmação a matrícula continua pendente');
foreach (['recusado', 'cancelado'] as $status) {
    [$m, $p] = $inscrever($aluno(), $plano30);
    $db->query("UPDATE pagamento SET status = '$status' WHERE id_pagamento = $p");
    mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $p, 99.90, 'gateway'), MatriculaRecusada::class, "pagamento_$status", "Cobrança $status não ativa");
    mCheck($matricula($m)['status'] === 'pendente', "Matrícula com cobrança $status continua pendente");
}
mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $p1, 9.99, 'gateway'), MatriculaRecusada::class, 'valor_divergente', 'Valor confirmado diferente do cobrado');
mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $p1, 99.90, 'Gateway Externo'), InvalidArgumentException::class, null, 'Origem inválida');
mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, 999999999, 99.90, 'gateway'), MatriculaRecusada::class, 'pagamento_inexistente', 'Cobrança inexistente');

// --- Confirmação válida: ativa uma única vez -----------------------------------------
$auditoriaAntes = (int) $db->query("SELECT COUNT(*) FROM auditoria_eventos WHERE categoria = 'matricula'")->fetch_row()[0];
$r = bo_matricula_confirmar_pagamento($db, $p1, 99.90, 'gateway');
mCheck($r === ['resultado' => 'ativada', 'id_matricula' => $m1, 'status' => 'ativa', 'data_inicio' => $hoje, 'data_fim' => $mais30], 'Confirmação válida ativa com início hoje e fim em +30 dias');
mCheck($matricula($m1) === ['status' => 'ativa', 'data_matricula' => $hoje, 'data_inicio' => $hoje, 'data_fim' => $mais30], 'Datas gravadas na matrícula');
mCheck($pagamento($p1)['status'] === 'aprovado' && $pagamento($p1)['data_pagamento'] !== null, 'Cobrança aprovada com data de pagamento');
$evento = mUm($db, "SELECT acao, id_operador, id_alvo, detalhes FROM auditoria_eventos WHERE categoria = 'matricula' ORDER BY id_evento DESC LIMIT 1");
$detalhes = json_decode($evento['detalhes'], true);
mCheck($evento['acao'] === 'ativar' && $evento['id_operador'] === null && (int) $evento['id_alvo'] === $m1
    && $detalhes['id_pagamento'] === $p1 && $detalhes['origem'] === 'gateway'
    && $detalhes['antes'] === ['status' => 'pendente', 'data_inicio' => $hoje, 'data_fim' => $hoje]
    && $detalhes['depois'] === ['data_inicio' => $hoje, 'data_fim' => $mais30], 'Auditoria com as datas anteriores e as novas, sem operador');

// --- Callback repetido: nada muda ------------------------------------------------------
$antes = mRetrato($db);
foreach ([1, 2, 3] as $vez) {
    $r = bo_matricula_confirmar_pagamento($db, $p1, 99.90, 'gateway');
    mCheck($r['resultado'] === 'ja_confirmado' && $r['data_fim'] === $mais30, "Confirmação repetida $vez: já confirmado, mesma data_fim");
}
mCheck(mRetrato($db) === $antes, 'Confirmações repetidas não alteram matrícula, cobrança nem auditoria');
mCheck((int) $db->query("SELECT COUNT(*) FROM auditoria_eventos WHERE categoria = 'matricula'")->fetch_row()[0] === $auditoriaAntes + 1, 'Uma única ativação auditada');

// --- Matrícula legada com data_fim 0000-00-00 (como as de produção) ------------------
$r = bo_matricula_confirmar_pagamento($db, $pLegado, 99.90, 'conciliacao');
mCheck($r['resultado'] === 'ativada' && $matricula($mLegado)['data_inicio'] === $hoje && $matricula($mLegado)['data_fim'] === $mais30, 'Legado com data zerada recebe datas válidas na ativação');

// --- Cobrança atrasada também pode ser confirmada --------------------------------------
[$mAtrasada, $pAtrasada] = $inscrever($aluno(), $plano30, 'cartao');
$db->query("UPDATE pagamento SET status = 'atrasado' WHERE id_pagamento = $pAtrasada");
mCheck(bo_matricula_confirmar_pagamento($db, $pAtrasada, 99.90, 'gateway')['resultado'] === 'ativada', 'Cobrança atrasada confirmada ativa a matrícula');

// --- Renovação/prorrogação: regra pendente, nada muda ----------------------------------
$db->query("INSERT INTO pagamento (id_matricula, valor, data_vencimento, forma_pagamento, status) VALUES ($m1, 99.90, CURDATE(), 'pix', 'pendente')");
$pRenovacao = (int) $db->insert_id;
mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $pRenovacao, 99.90, 'gateway'), MatriculaRecusada::class, 'matricula_nao_pendente', 'Nova cobrança de matrícula ativa não prorroga');
foreach (['vencida', 'cancelada'] as $status) {
    [$m, $p] = $inscrever($aluno(), $plano30);
    $db->query("UPDATE matricula SET status = '$status' WHERE id_matricula = $m");
    mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $p, 99.90, 'gateway'), MatriculaRecusada::class, 'matricula_nao_pendente', "Matrícula $status não é reativada");
}

// --- Conta inativa e plano sem duração -------------------------------------------------
[$mInativa, $pInativa] = $inscrever($aluno('inativo'), $plano30);
mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $pInativa, 99.90, 'gateway'), MatriculaRecusada::class, 'conta_inativa', 'Conta inativa não é ativada');
[$mSemDuracao, $pSemDuracao] = $inscrever($aluno(), $plano(0));
mRecusa($db, static fn() => bo_matricula_confirmar_pagamento($db, $pSemDuracao, 99.90, 'gateway'), MatriculaRecusada::class, 'plano_sem_duracao', 'Plano sem duração não inventa vigência');

// --- Duas confirmações simultâneas: a cobrança fica travada ----------------------------
[$mDisputa, $pDisputa] = $inscrever($aluno(), $plano30);
$outra = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$outra->begin_transaction();
$outra->query("SELECT id_pagamento FROM pagamento WHERE id_pagamento = $pDisputa FOR UPDATE");
$db->query('SET SESSION innodb_lock_wait_timeout = 1');
$antes = mRetrato($db);
try {
    bo_matricula_confirmar_pagamento($db, $pDisputa, 99.90, 'gateway');
    mCheck(false, 'Confirmação concorrente deveria esperar a trava');
} catch (mysqli_sql_exception $erro) {
    mCheck($erro->getCode() === 1205, 'Segunda confirmação espera a trava da cobrança (lock wait)');
}
mCheck(mRetrato($db) === $antes, 'Confirmação concorrente não deixa nada pela metade');
$outra->rollback();
$outra->close();
$db->query('SET SESSION innodb_lock_wait_timeout = DEFAULT');
mCheck(bo_matricula_confirmar_pagamento($db, $pDisputa, 99.90, 'gateway')['resultado'] === 'ativada'
    && bo_matricula_confirmar_pagamento($db, $pDisputa, 99.90, 'gateway')['resultado'] === 'ja_confirmado', 'Depois da disputa ativa uma vez só');

// --- Histórico: nenhuma matrícula ou cobrança apagada ----------------------------------
mCheck((int) $db->query("SELECT COUNT(*) FROM pagamento WHERE id_matricula = $m1")->fetch_row()[0] === 2
    && $pagamento($pRenovacao)['status'] === 'pendente', 'Cobranças da matrícula preservadas (a de renovação segue pendente)');

// --- O aluno não confirma pagamento: a rota antiga não grava mais nada -------------------
$rota = file_get_contents(__DIR__ . '/../pages/dashboard/funcionalidades/pagar-plano.php');
mCheck(!preg_match('/\b(INSERT|UPDATE|DELETE)\b/i', $rota) && !str_contains($rota, 'bo_matricula_confirmar_pagamento('), 'pagar-plano.php não grava nem confirma');
foreach (glob(__DIR__ . '/../pages/{*,*/*,*/*/*}.php', GLOB_BRACE) as $arquivo) {
    if (str_ends_with(str_replace('\\', '/', $arquivo), 'includes/matriculas.php')) {
        continue;
    }
    mCheck(!str_contains(file_get_contents($arquivo), 'bo_matricula_confirmar_pagamento('), 'Nenhuma página chama a confirmação: ' . basename($arquivo));
}

echo "Matrículas: $checks verificações passaram.\n";
