<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Migração da disponibilidade dos professores (database/migrate-professores.php) num banco
// descartável com a MESMA estrutura de cadastro_profissional, usuarios e agendamento do banco
// remoto (tests/fixtures/interface-schema.json, ENUM de cargos depois de 20261020_diretor_v1).
// Roda o script real numa cópia mínima do app cujo .env aponta para esse banco local.
// Banco e cópia são apagados no final. Orquestrado por tests/funcionarios-preparar.ps1.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$banco = 'onefit_interface_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$pasta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'onefit-migracao-professores-' . bin2hex(random_bytes(4));
$checks = 0;
function pmCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function pmCopiar(string $origem, string $destino): void
{
    mkdir($destino, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $alvo = $destino . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($origem) + 1);
        $item->isDir() ? mkdir($alvo, 0700, true) : copy($item->getPathname(), $alvo);
    }
}
function pmApagar(string $caminho): void
{
    if (!is_dir($caminho)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($caminho);
}
function pmRodar(array $argumentos, ?int &$codigo): string
{
    $processo = proc_open(array_merge([PHP_BINARY], $argumentos), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
    $saida = stream_get_contents($tubos[1]) . stream_get_contents($tubos[2]);
    fclose($tubos[1]);
    fclose($tubos[2]);
    $codigo = proc_close($processo);
    return $saida;
}
/** Hash das linhas nas colunas originais (sem a coluna nova). */
function pmLinhas(mysqli $db, string $tabela, array $colunas): array
{
    return array_map(static fn(array $l): string => hash('sha256', json_encode($l, JSON_THROW_ON_ERROR)),
        $db->query('SELECT ' . implode(', ', $colunas) . " FROM $tabela ORDER BY 1")->fetch_all(MYSQLI_ASSOC));
}
function pmColunas(mysqli $db): array
{
    return array_column($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' ORDER BY ORDINAL_POSITION")->fetch_all(), 0);
}
function pmVersoes(mysqli $db): array
{
    return array_column($db->query('SELECT versao FROM onefit_migrations ORDER BY versao')->fetch_all(), 0);
}

$esquema = json_decode(file_get_contents(__DIR__ . '/fixtures/interface-schema.json'), true, 512, JSON_THROW_ON_ERROR);
$servidor = new mysqli('127.0.0.1', 'root', '');
$servidor->query("CREATE DATABASE `$banco` CHARACTER SET utf8mb4");
try {
    $db = new mysqli('127.0.0.1', 'root', '', $banco);
    $db->set_charset('utf8mb4');
    foreach (['usuarios', 'cadastro_profissional', 'agendamento'] as $tabela) {
        $db->query(preg_replace('/ AUTO_INCREMENT=\d+/', '', $esquema[$tabela]));
    }
    $db->query("ALTER TABLE usuarios MODIFY tipo_usuario ENUM('aluno','profissional','admin','vendedor','professor','recepcionista','faxineira','diretor') DEFAULT 'aluno'");
    $db->query('CREATE TABLE onefit_migrations (versao VARCHAR(96) PRIMARY KEY, aplicada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->query("INSERT INTO onefit_migrations (versao) VALUES ('20260910_interface_v1'), ('20261020_diretor_v1'), ('20261020_funcionarios_v1')");
    // Mesma distribuição do remoto: 2 professores ativos e 1 inativo, todos com cadastro; mais 1 aluno.
    foreach ([[1, 'aluno', 'ativo'], [2, 'professor', 'ativo'], [3, 'professor', 'ativo'], [4, 'professor', 'inativo']] as [$n, $cargo, $status]) {
        $db->query("INSERT INTO usuarios (id_usuario, nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
            VALUES ($n, 'Pessoa Sintética $n', 'x', 'BR', '1990-01-0$n', 'outro', '0000000000$n', 'Rua Teste, $n', 'Cidade/SP', 'pessoa$n@example.test', 1, '1190000000$n', '$cargo', '$status')");
    }
    $db->query("INSERT INTO cadastro_profissional (id_profissional, id_usuario, nome, email, especialidade, status, modalidades, data_cadastro) VALUES
        (7, 2, 'Pessoa Sintética 2', 'pessoa2@example.test', 'Musculação', 'ativo', 'Yoga', '2026-01-01 10:00:00'),
        (8, 3, 'Pessoa Sintética 3', 'pessoa3@example.test', NULL, 'ativo', NULL, '2026-01-02 10:00:00'),
        (9, 4, 'Pessoa Sintética 4', 'pessoa4@example.test', 'Pilates', 'inativo', NULL, '2026-01-03 10:00:00')");
    $db->query("INSERT INTO agendamento (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento, hora_inicio, status) VALUES
        (1, 1, 7, 'Avaliação física', 'avaliacao', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', 'confirmado'),
        (2, 1, 8, 'Personal trainer', 'personal', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10:00:00', 'concluido')");
    $colunasAntes = pmColunas($db);
    $cadastrosAntes = pmLinhas($db, 'cadastro_profissional', $colunasAntes);
    $agendamentosAntes = pmLinhas($db, 'agendamento', ['id_agendamento', 'id_usuario', 'id_profissional', 'titulo', 'tipo', 'data_evento', 'hora_inicio', 'status']);

    $copia = $pasta . '/www/AN25/OneFit';
    pmCopiar($raiz . DIRECTORY_SEPARATOR . 'config', $copia . DIRECTORY_SEPARATOR . 'config');
    pmCopiar($raiz . DIRECTORY_SEPARATOR . 'database', $copia . DIRECTORY_SEPARATOR . 'database');
    file_put_contents($copia . '/.env', "APP_URL=http://localhost/AN25/OneFit/\nDB_HOST=127.0.0.1\nDB_NAME=$banco\nDB_USER=root\nDB_PASSWORD=\n");
    mkdir($pasta . '/backups', 0700);
    $runner = $copia . '/database/migrate-professores.php';
    $alvo = ['--host=127.0.0.1', "--database=$banco"];
    $backup = '--backup-dir=' . $pasta . '/backups';
    $confirmar = "--confirmar=$banco";

    // --- Inspeção e recusas ------------------------------------------------------------------
    $inspecao = json_decode(pmRodar([$runner, '--inspecionar', ...$alvo], $codigo), true);
    pmCheck($codigo === 0 && $inspecao['coluna_disponivel_agendamento'] === false && $inspecao['cadastro_profissional']['total'] === 3
        && $inspecao['cadastro_profissional']['contas_com_mais_de_um_cadastro'] === 0 && $inspecao['agendamentos_futuros_ativos'] === 1, 'Inspeção somente leitura com contagens');
    pmCheck(pmLinhas($db, 'cadastro_profissional', $colunasAntes) === $cadastrosAntes, 'Inspeção não altera nada');
    pmRodar([$runner, '--aplicar', ...$alvo], $codigo);
    pmCheck($codigo === 2, 'Sem diretório de backup: recusado');
    pmRodar([$runner, '--aplicar', '--host=127.0.0.1', '--database=outro_banco', '--confirmar=outro_banco', $backup], $codigo);
    pmCheck($codigo === 2, 'Destino diferente do .env: recusado');
    pmRodar([$runner, '--aplicar', ...$alvo, $confirmar, '--backup-dir=' . $raiz], $codigo);
    pmCheck($codigo === 2, 'Backup dentro do repositório: recusado');
    pmRodar([$runner, '--aplicar', ...$alvo, $backup], $codigo);
    pmCheck($codigo === 2, 'Sem --confirmar: recusado');
    pmRodar([$runner, '--aplicar', ...$alvo, '--confirmar=onefit', $backup], $codigo);
    pmCheck($codigo === 2, '--confirmar com outro banco: recusado');
    pmRodar([$runner, '--reverter', ...$alvo, '--confirmar=onefit', $backup], $codigo);
    pmCheck($codigo === 2, 'Reversão com --confirmar de outro banco: recusada');
    $db->query("DELETE FROM onefit_migrations WHERE versao = '20261020_funcionarios_v1'");
    $saida = pmRodar([$runner, '--aplicar', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 1 && str_contains($saida, 'Aplique antes'), 'Sem a migração de funcionários: recusado');
    $db->query("INSERT INTO onefit_migrations (versao) VALUES ('20261020_funcionarios_v1')");
    // Duas contas no mesmo cadastro? Não: um professor com dois cadastros impede a chave única.
    $db->query("INSERT INTO cadastro_profissional (id_profissional, id_usuario, nome, email, status) VALUES (10, 2, 'Duplicado', 'duplicado@example.test', 'ativo')");
    $saida = pmRodar([$runner, '--aplicar', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 1 && str_contains($saida, 'mais de um cadastro profissional') && pmColunas($db) === $colunasAntes, 'Conta com dois cadastros: recusado, nada alterado');
    $db->query('DELETE FROM cadastro_profissional WHERE id_profissional = 10');
    pmCheck(glob($pasta . '/backups/*.json') === [] && pmLinhas($db, 'cadastro_profissional', $colunasAntes) === $cadastrosAntes, 'Recusas não gravam backup nem dados');

    // --- Aplicação ---------------------------------------------------------------------------
    $saida = pmRodar([$runner, '--aplicar', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 0 && str_contains($saida, 'Migração verificada'), 'Migração aplicada: ' . trim($saida));
    $posStatus = array_search('status', $colunasAntes, true) + 1;
    pmCheck(pmColunas($db) === array_merge(array_slice($colunasAntes, 0, $posStatus), ['disponivel_agendamento'], array_slice($colunasAntes, $posStatus)), 'Coluna nova depois de status; demais colunas intactas');
    $definicao = $db->query("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' AND COLUMN_NAME = 'disponivel_agendamento'")->fetch_assoc();
    pmCheck(str_starts_with($definicao['COLUMN_TYPE'], 'tinyint') && $definicao['IS_NULLABLE'] === 'NO' && trim((string) $definicao['COLUMN_DEFAULT'], "'") === '1', 'TINYINT NOT NULL DEFAULT 1');
    pmCheck(pmLinhas($db, 'cadastro_profissional', $colunasAntes) === $cadastrosAntes, 'Cadastros preservados (IDs, vínculos, status e dados)');
    pmCheck((int) $db->query('SELECT COUNT(*) FROM cadastro_profissional WHERE disponivel_agendamento = 1')->fetch_row()[0] === 3, 'Todos continuam disponíveis (regra anterior)');
    pmCheck(pmLinhas($db, 'agendamento', ['id_agendamento', 'id_usuario', 'id_profissional', 'titulo', 'tipo', 'data_evento', 'hora_inicio', 'status']) === $agendamentosAntes, 'Agendamentos intactos');
    pmCheck(pmVersoes($db) === ['20260910_interface_v1', '20261020_diretor_v1', '20261020_funcionarios_v1', '20261021_professores_v1'], 'Versão registrada');
    $arquivos = glob($pasta . '/backups/onefit-before-professores-aplicar-*.json');
    $verificacao = json_decode(pmRodar([$runner, '--verify=' . ($arquivos[0] ?? 'x')], $codigo), true);
    pmCheck(count($arquivos) === 1 && $codigo === 0 && $verificacao['cadastro_profissional'] === 3, 'Backup verificável com os 3 cadastros');
    $segunda = pmRodar([$runner, '--aplicar', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 0 && str_contains($segunda, 'já aplicada; nada foi alterado') && count(glob($pasta . '/backups/*.json')) === 1, 'Segunda execução não altera nada');
    // Chave única: um segundo cadastro para a mesma conta é recusado pelo banco; vários sem conta, não.
    try {
        $db->query("INSERT INTO cadastro_profissional (id_usuario, nome, email) VALUES (2, 'Duplicado', 'duplicado@example.test')");
        pmCheck(false, 'Chave única deveria recusar segundo cadastro da conta');
    } catch (mysqli_sql_exception $erro) {
        pmCheck($erro->getCode() === 1062, 'Banco recusa segundo cadastro da mesma conta (1062)');
    }
    $db->query("INSERT INTO cadastro_profissional (id_usuario, nome, email) VALUES (NULL, 'Sem conta A', 'semcontaa@example.test'), (NULL, 'Sem conta B', 'semcontab@example.test')");
    pmCheck((int) $db->query('SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IS NULL AND disponivel_agendamento = 1')->fetch_row()[0] === 2, 'Cadastros sem conta continuam permitidos; novos nascem disponíveis');
    $db->query('DELETE FROM cadastro_profissional WHERE id_usuario IS NULL');

    // --- Reversão ----------------------------------------------------------------------------
    $db->query('UPDATE cadastro_profissional SET disponivel_agendamento = 0 WHERE id_profissional = 8');
    $saida = pmRodar([$runner, '--reverter', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 1 && str_contains($saida, 'indisponível') && in_array('disponivel_agendamento', pmColunas($db), true), 'Reversão recusada com professor indisponível');
    $db->query('UPDATE cadastro_profissional SET disponivel_agendamento = 1 WHERE id_profissional = 8');
    $saida = pmRodar([$runner, '--reverter', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 0 && str_contains($saida, 'Reversão verificada'), 'Reversão aplicada: ' . trim($saida));
    pmCheck(pmColunas($db) === $colunasAntes && (int) $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' AND INDEX_NAME = 'uk_cadastro_profissional_usuario'")->fetch_row()[0] === 0, 'Estrutura idêntica à original');
    pmCheck(pmLinhas($db, 'cadastro_profissional', $colunasAntes) === $cadastrosAntes, 'Reversão não altera cadastros');
    pmCheck(pmVersoes($db) === ['20260910_interface_v1', '20261020_diretor_v1', '20261020_funcionarios_v1'], 'Versão removida');
    $saida = pmRodar([$runner, '--reverter', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 0 && str_contains($saida, 'não está aplicada'), 'Reverter de novo não altera nada');
    $saida = pmRodar([$runner, '--aplicar', ...$alvo, $confirmar, $backup], $codigo);
    pmCheck($codigo === 0 && in_array('disponivel_agendamento', pmColunas($db), true) && pmLinhas($db, 'cadastro_profissional', $colunasAntes) === $cadastrosAntes, 'Reaplicar depois da reversão funciona');
} finally {
    $servidor->query("DROP DATABASE IF EXISTS `$banco`");
    pmApagar($pasta);
}

echo "Migração da disponibilidade dos professores (esquema remoto): $checks verificações passaram.\n";
