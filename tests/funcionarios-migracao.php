<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Confere a migração da Etapa 2 no banco descartável onefit_interface_test_*.
//   --antes   guarda o retrato da coluna tipo_usuario e das linhas de usuarios
//   --depois  compara com o retrato após database/migrate-funcionarios.php
// Orquestrado por tests/funcionarios-preparar.ps1.
$modo = $argv[1] ?? '';
if (!in_array($modo, ['--antes', '--depois'], true)) {
    fwrite(STDERR, "Uso: --antes | --depois\n");
    exit(2);
}
$pasta = sys_get_temp_dir() . '/onefit-interface-fixture';
$fixture = json_decode(file_get_contents($pasta . '/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');

$checks = 0;
function migCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function migEnum(string $tipo): array
{
    preg_match('/^enum\((.*)\)$/is', $tipo, $corpo);
    preg_match_all("/'((?:[^']|'')*)'/", $corpo[1] ?? '', $valores);
    return $valores[1];
}
function migRetrato(mysqli $db): array
{
    $coluna = $db->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_assoc();
    $linhas = array_map(static fn(array $l): string => hash('sha256', json_encode($l, JSON_THROW_ON_ERROR)),
        $db->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC));
    $auditoria = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_eventos'")->fetch_row()[0];
    return ['coluna' => $coluna, 'linhas' => $linhas, 'auditoria' => $auditoria === 1];
}

$arquivoRetrato = $pasta . '/funcionarios-antes.json';
if ($modo === '--antes') {
    $retrato = migRetrato($db);
    migCheck(array_intersect(['professor', 'recepcionista', 'faxineira'], migEnum($retrato['coluna']['COLUMN_TYPE'])) === [], 'Banco novo ainda sem os cargos da Etapa 2');
    migCheck(!$retrato['auditoria'], 'Banco novo ainda sem auditoria_eventos');
    file_put_contents($arquivoRetrato, json_encode($retrato, JSON_THROW_ON_ERROR));
    echo "Migração (antes): $checks verificações; " . count($retrato['linhas']) . " linhas de usuarios registradas.\n";
    exit;
}

$antes = json_decode(file_get_contents($arquivoRetrato), true, 512, JSON_THROW_ON_ERROR);
$depois = migRetrato($db);
$valoresAntes = migEnum($antes['coluna']['COLUMN_TYPE']);
migCheck(migEnum($depois['coluna']['COLUMN_TYPE']) === array_merge($valoresAntes, ['professor', 'recepcionista', 'faxineira']), 'ENUM só ganhou os 3 cargos, no final e na ordem');
foreach (['IS_NULLABLE', 'COLUMN_DEFAULT', 'CHARACTER_SET_NAME', 'COLLATION_NAME'] as $atributo) {
    migCheck($depois['coluna'][$atributo] === $antes['coluna'][$atributo], "Atributo preservado: $atributo");
}
migCheck($depois['linhas'] === $antes['linhas'], 'Nenhuma linha de usuarios mudou');
migCheck($depois['auditoria'], 'auditoria_eventos criada');
$colunas = array_column($db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_eventos' ORDER BY ORDINAL_POSITION"
)->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
migCheck($colunas === ['id_evento', 'categoria', 'acao', 'id_operador', 'id_alvo', 'detalhes', 'criado_em'], 'Colunas de auditoria_eventos');
migCheck((int) $db->query("SELECT COUNT(*) FROM onefit_migrations WHERE versao = '20261020_funcionarios_v1'")->fetch_row()[0] === 1, 'Versão registrada uma única vez');
migCheck((int) $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_eventos' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetch_row()[0] === 0, 'Auditoria sem chave estrangeira (sobrevive a exclusões)');

$backups = glob($pasta . '/backups/onefit-before-funcionarios-*.json');
usort($backups, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
migCheck(isset($backups[0]), 'Backup gerado fora da cópia pública');
$runner = $pasta . '/www/AN25/OneFit/database/migrate-funcionarios.php';
$saida = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' --verify=' . escapeshellarg($backups[0]), $saida, $codigo);
$verificacao = json_decode(implode("\n", $saida), true);
migCheck($codigo === 0 && ($verificacao['tabelas']['usuarios'] ?? -1) === count($antes['linhas']), 'Backup verificado com todas as linhas de usuarios');
migCheck(is_dir($pasta . '/backups') && !str_contains(str_replace('\\', '/', realpath($backups[0])), '/www/'), 'Backup fora da raiz pública da cópia');

echo "Migração (depois): $checks verificações passaram.\n";
