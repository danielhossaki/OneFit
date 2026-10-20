<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Regras do cadastro facial (pages/dashboard/includes/fotos-faciais.php) contra o
// banco descartável onefit_interface_test_*, já migrado por
// tests/funcionarios-preparar.ps1. Imagens sintéticas geradas aqui; nenhuma foto
// real é lida ou gravada fora desse banco.
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/../pages/dashboard/includes/fotos-faciais.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
if (!bo_funcionarios_migracao($db)['ok']) {
    throw new RuntimeException('Rode antes tests/funcionarios-preparar.ps1 (migração no banco de teste).');
}
// Mesma estrutura de produção (SHOW CREATE TABLE de 20/10/2026).
$db->query("CREATE TABLE IF NOT EXISTS usuarios_fotos_faciais (
    id_foto_facial INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    imagem_blob MEDIUMBLOB NULL,
    KEY idx_usuario (id_usuario),
    CONSTRAINT fk_fotos_faciais_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$checks = 0;
function ffCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function ffJpeg(int $tom): string
{
    $img = imagecreatetruecolor(48, 48);
    imagefill($img, 0, 0, imagecolorallocate($img, $tom % 256, 120, 200));
    ob_start();
    imagejpeg($img, null, 80);
    imagedestroy($img);
    return ob_get_clean();
}
/** Retrato das fotos: id => [usuario, ativo, sha1 do blob]. */
function ffFotos(mysqli $db, ?int $idUsuario = null): array
{
    $sql = 'SELECT id_foto_facial, id_usuario, ativo, SHA1(imagem_blob) h FROM usuarios_fotos_faciais' . ($idUsuario ? ' WHERE id_usuario = ' . $idUsuario : '') . ' ORDER BY id_foto_facial';
    $fotos = [];
    foreach ($db->query($sql) as $l) {
        $fotos[(int) $l['id_foto_facial']] = [(int) $l['id_usuario'], (int) $l['ativo'], $l['h']];
    }
    return $fotos;
}
function ffAtivas(mysqli $db, int $id): array
{
    return array_keys(array_filter(ffFotos($db, $id), static fn(array $f): bool => $f[1] === 1));
}
function ffContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
/** A operação precisa ser recusada com o status esperado e sem gravar nada. */
function ffRecusa(mysqli $db, callable $acao, int $status, string $rotulo): FotoFacialRecusada
{
    $fotos = ffFotos($db);
    $auditoria = ffContar($db, 'SELECT COUNT(*) FROM auditoria_eventos');
    try {
        $acao();
    } catch (FotoFacialRecusada $recusa) {
        ffCheck($recusa->status === $status, "$rotulo: status {$recusa->status}, esperado $status");
        ffCheck(ffFotos($db) === $fotos && ffContar($db, 'SELECT COUNT(*) FROM auditoria_eventos') === $auditoria, "$rotulo: nada gravado");
        return $recusa;
    }
    throw new RuntimeException("$rotulo: deveria ter sido recusado");
}

$run = random_int(100000, 899999);
$cpf = 0;
$criar = static function (string $cargo, string $status = 'ativo') use ($db, $run, &$cpf): int {
    $cpf++;
    $nome = "Facial $run $cargo $cpf";
    $email = "facial{$run}x{$cpf}@example.test";
    $documento = sprintf('%011d', $run * 100 + $cpf);
    $senha = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 1', 'Santos/SP', ?, 1, '(11) 90000-0000', ?, ?)");
    $stmt->bind_param('ssssss', $nome, $senha, $documento, $email, $cargo, $status);
    $stmt->execute();
    return (int) $stmt->insert_id;
};
$admin = $criar('admin');
$recep1 = $criar('recepcionista');
$recep2 = $criar('recepcionista');
$recepInativa = $criar('recepcionista', 'inativo');
$faxineira = $criar('faxineira');
$professor = $criar('professor');
$db->query("INSERT INTO cadastro_profissional (id_usuario, nome, email, status) VALUES ($professor, 'Facial prof $run', 'facialprof$run@example.test', 'ativo')");
$aluno = $criar('aluno');
$vendedor = $criar('vendedor');
// Cargo fora do ENUM em servidor sem modo estrito (como o remoto) vira '': deve ser recusado.
$desconhecido = $criar('aluno');
$db->query("SET SESSION sql_mode = ''");
$db->query("UPDATE usuarios SET tipo_usuario = 'gerente' WHERE id_usuario = $desconhecido");
$db->query("SET SESSION sql_mode = DEFAULT");
ffCheck($db->query("SELECT tipo_usuario FROM usuarios WHERE id_usuario = $desconhecido")->fetch_row()[0] === '', 'Cargo inválido gravado como vazio (cenário do banco remoto)');

// Histórico pré-existente: fotos inativas do aluno e o retrato de todas as fotos de fora deste teste.
$stmt = $db->prepare('INSERT INTO usuarios_fotos_faciais (id_usuario, imagem_blob, ativo, data_cadastro) VALUES (?, ?, 0, ?)');
foreach (['2026-01-01 10:00:00', '2026-02-01 10:00:00'] as $i => $quando) {
    $blob = ffJpeg(10 + $i);
    $stmt->bind_param('iss', $aluno, $blob, $quando);
    $stmt->execute();
}
$idsDoTeste = [$admin, $recep1, $recep2, $recepInativa, $faxineira, $professor, $aluno, $vendedor, $desconhecido];
$deFora = array_filter(ffFotos($db), static fn(array $f): bool => !in_array($f[0], $idsDoTeste, true));
$historicoAluno = ffFotos($db, $aluno);

// --- Recepcionista -------------------------------------------------------------
$r = bo_foto_facial_cadastrar($db, $recep1, $recep1, ffJpeg(1), false);
ffCheck($r['fotos_ativas'] === 1 && $r['desativadas'] === [] && ffAtivas($db, $recep1) === [$r['id_foto_facial']], 'Recepcionista cadastra a própria face');
$r = bo_foto_facial_cadastrar($db, $recep1, $recep2, ffJpeg(2), false);
ffCheck(ffAtivas($db, $recep2) === [$r['id_foto_facial']], 'Recepcionista cadastra outra recepcionista ativa');
foreach ([$aluno, $professor, $vendedor, $faxineira] as $pessoa) {
    $r = bo_foto_facial_cadastrar($db, $recep1, $pessoa, ffJpeg(3), false);
    ffCheck(in_array($r['id_foto_facial'], ffAtivas($db, $pessoa), true), "Recepcionista cadastra cargo não administrativo ($pessoa)");
}
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $recep1, $recepInativa, ffJpeg(4), false), 404, 'Recepcionista inativa não é fotografada');
$recusa = ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $recep1, $admin, ffJpeg(5), false), 404, 'Recepcionista não cadastra admin');
ffCheck(!str_contains(mb_strtolower($recusa->getMessage()), 'admin'), 'Recusa não revela que a pessoa é admin');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $recep1, $admin, ffJpeg(5), true), 404, 'Recepcionista não substitui foto de admin');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $recepInativa, $recepInativa, ffJpeg(6), false), 403, 'Recepcionista inativa não opera');

// --- Admin comum, faxineira, professor ------------------------------------------
$r = bo_foto_facial_cadastrar($db, $admin, $faxineira, ffJpeg(7), false);
ffCheck(count(ffAtivas($db, $faxineira)) === 2, 'Admin cadastra faxineira');
$linhaFaxineira = onefitBuscarSituacaoUsuario($db, $faxineira);
ffCheck(onefitPainelDoPapel($linhaFaxineira['tipo_usuario']) === null, 'Faxineira fotografada continua sem painel');
ffCheck(onefitAvaliarSessao($linhaFaxineira, 'faxineira')['valida'] === false && onefitAvaliarSessao($linhaFaxineira, 'aluno')['valida'] === false, 'Faxineira não tem sessão válida (nem como aluna)');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $admin, ffJpeg(8), false), 404, 'Admin comum não cadastra a própria face (P6)');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $professor, $aluno, ffJpeg(9), false), 403, 'Professor não opera o cadastro facial');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $faxineira, $faxineira, ffJpeg(9), false), 403, 'Faxineira não opera o cadastro facial');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $aluno, $aluno, ffJpeg(9), true), 403, 'Aluno não opera o cadastro facial');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, 999999999, $aluno, ffJpeg(9), false), 403, 'Operador inexistente');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, 999999999, ffJpeg(9), false), 404, 'Pessoa inexistente');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $desconhecido, ffJpeg(9), false), 404, 'Pessoa de cargo desconhecido');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $aluno, 'não é imagem', false), 422, 'Conteúdo que não é imagem');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $aluno, "\x89PNG\r\n\x1a\n" . str_repeat('x', 64), false), 422, 'PNG adulterado');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $aluno, str_repeat('a', BO_FOTOS_FACIAIS_MAX_BYTES + 1), false), 422, 'Imagem acima de 5 MB');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $aluno, '', false), 422, 'Imagem vazia');

// --- Limite de três fotos ativas -------------------------------------------------
// O aluno já tem 1 ativa (da recepcionista) e 2 históricas inativas.
$primeira = ffAtivas($db, $aluno)[0];
$r2 = bo_foto_facial_cadastrar($db, $admin, $aluno, ffJpeg(20), false);
$r3 = bo_foto_facial_cadastrar($db, $admin, $aluno, ffJpeg(21), false);
ffCheck($r2['fotos_ativas'] === 2 && $r3['fotos_ativas'] === 3, 'Até três fotos ativas sem confirmação');
$recusa = ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $aluno, ffJpeg(22), false), 409, 'Quarta foto sem confirmação');
ffCheck($recusa->dados === ['requer_confirmacao' => true, 'fotos_ativas' => 3], 'Quarta foto pede confirmação explícita');
$antes = ffFotos($db, $aluno);
$r4 = bo_foto_facial_cadastrar($db, $admin, $aluno, ffJpeg(23), true);
$depois = ffFotos($db, $aluno);
ffCheck($r4['desativadas'] === [$primeira] && $r4['fotos_ativas'] === 3, 'Confirmação desativa só a foto ativa mais antiga');
ffCheck(ffAtivas($db, $aluno) === [$r2['id_foto_facial'], $r3['id_foto_facial'], $r4['id_foto_facial']], 'Restam as três mais recentes');
ffCheck($depois[$primeira] === [$aluno, 0, $antes[$primeira][2]], 'Foto substituída continua no histórico, com a imagem intacta');
foreach ($historicoAluno as $id => $foto) {
    ffCheck($depois[$id] === $foto, 'Histórico anterior preservado');
}
ffCheck(count($depois) === count($antes) + 1, 'Nenhuma foto apagada');

// Pessoa com mais de três ativas (gravadas antes da regra): a confirmação deixa exatamente três.
$stmt = $db->prepare('INSERT INTO usuarios_fotos_faciais (id_usuario, imagem_blob, ativo, data_cadastro) VALUES (?, ?, 1, ?)');
foreach (['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-04'] as $i => $dia) {
    $blob = ffJpeg(30 + $i);
    $quando = "$dia 10:00:00";
    $stmt->bind_param('iss', $vendedor, $blob, $quando);
    $stmt->execute();
}
$legado = ffAtivas($db, $vendedor);
ffCheck(count($legado) === 5, 'Cenário legado com cinco ativas');
ffRecusa($db, static fn() => bo_foto_facial_cadastrar($db, $admin, $vendedor, ffJpeg(40), false), 409, 'Legado acima do limite pede confirmação');
$r = bo_foto_facial_cadastrar($db, $admin, $vendedor, ffJpeg(41), true);
ffCheck(count($r['desativadas']) === 3 && $r['fotos_ativas'] === 3 && count(ffAtivas($db, $vendedor)) === 3, 'Legado volta a três ativas, desativando as mais antigas');
$cronologia = array_column($db->query("SELECT id_foto_facial FROM usuarios_fotos_faciais WHERE id_usuario = $vendedor ORDER BY data_cadastro, id_foto_facial")->fetch_all(), 0);
ffCheck(array_map('intval', array_slice($cronologia, 0, 3)) === $r['desativadas'], 'Desativadas são as três mais antigas por data de cadastro');

// --- Requisições simultâneas: a pessoa fica travada durante a gravação ----------
$outra = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$outra->begin_transaction();
$outra->query("SELECT id_usuario FROM usuarios WHERE id_usuario = $aluno FOR UPDATE");
$db->query('SET SESSION innodb_lock_wait_timeout = 1');
$fotosAntes = ffFotos($db);
try {
    bo_foto_facial_cadastrar($db, $admin, $aluno, ffJpeg(50), true);
    ffCheck(false, 'Gravação concorrente deveria esperar a trava');
} catch (mysqli_sql_exception $erro) {
    ffCheck($erro->getCode() === 1205, 'Segunda gravação espera a trava da pessoa (lock wait)');
}
ffCheck(ffFotos($db) === $fotosAntes, 'Gravação concorrente não deixa nada pela metade');
$outra->rollback();
$outra->close();
$db->query('SET SESSION innodb_lock_wait_timeout = DEFAULT');
ffCheck(count(ffAtivas($db, $aluno)) === 3, 'Limite mantido depois da disputa');

// --- Auditoria sem dado biométrico -----------------------------------------------
$eventos = $db->query("SELECT acao, id_operador, id_alvo, detalhes FROM auditoria_eventos WHERE categoria = 'foto_facial' AND id_alvo IN (" . implode(',', $idsDoTeste) . ') ORDER BY id_evento')->fetch_all(MYSQLI_ASSOC);
ffCheck(count($eventos) === 11, 'Uma auditoria por foto gravada (' . count($eventos) . ')');
foreach ($eventos as $evento) {
    $detalhes = json_decode($evento['detalhes'], true, 4, JSON_THROW_ON_ERROR);
    ffCheck(array_keys($detalhes) === ['id_foto_facial', 'desativadas', 'cargo'] && strlen($evento['detalhes']) < 200, 'Auditoria só com ids e cargo');
}
ffCheck(array_count_values(array_column($eventos, 'acao')) === ['cadastrar' => 9, 'substituir' => 2], 'Ações auditadas: cadastrar e substituir');

// --- Listagem -------------------------------------------------------------------
$ids = static fn(array $pessoas): array => array_column($pessoas, 'id');
$listaRecep = $ids(bo_fotos_faciais_listar($db, $recep1, "Facial $run"));
foreach ([$recep1, $recep2, $aluno, $professor, $vendedor, $faxineira] as $pessoa) {
    ffCheck(in_array($pessoa, $listaRecep, true), "Recepcionista vê pessoa permitida ($pessoa)");
}
foreach ([$admin, $recepInativa, $desconhecido] as $pessoa) {
    ffCheck(!in_array($pessoa, $listaRecep, true), "Recepcionista não vê pessoa proibida ($pessoa)");
}
$listaAdmin = bo_fotos_faciais_listar($db, $admin, "Facial $run");
ffCheck(!in_array($admin, $ids($listaAdmin), true) && in_array($faxineira, $ids($listaAdmin), true), 'Admin comum não vê contas admin, vê a faxineira');
$doAluno = array_values(array_filter($listaAdmin, static fn(array $p): bool => $p['id'] === $aluno))[0];
ffCheck($doAluno['fotos_ativas'] === 3 && $doAluno['foto_cadastrada'] === true && !isset($doAluno['cpf']), 'Listagem com nº de fotos ativas e sem CPF');
ffCheck(bo_fotos_faciais_listar($db, $professor, '') === [] && bo_fotos_faciais_listar($db, $faxineira, '') === [], 'Quem não opera não lista ninguém');

// --- Fotos de fora deste teste continuam iguais --------------------------------
ffCheck(array_intersect_key(ffFotos($db), $deFora) === $deFora, 'Fotos de outras pessoas intactas');

echo "Cadastro facial: $checks verificações passaram.\n";
