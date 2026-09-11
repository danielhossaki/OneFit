<?php
/** Explicit CLI-only deployment, with a complete private backup before any DDL. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$args = getopt('', ['development', 'host:', 'database:', 'backup-dir:', 'verify:']);
function verifyInterfaceBackup(string $path): array {
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($data['format'] ?? '') !== 'onefit-interface-backup-v1') throw new RuntimeException('Invalid backup format');
    $counts = [];
    foreach ($data['tables'] as $table => $entry) {
        $rows = $entry['rows'];
        if (count($rows) !== $entry['count'] || !hash_equals($entry['sha256'], hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) throw new RuntimeException('Backup verification failed');
        foreach ($rows as $row) foreach ($row as $value) if ($value !== null && base64_decode($value, true) === false) throw new RuntimeException('Invalid backup cell');
        $counts[$table] = count($rows);
    }
    return $counts;
}
if (isset($args['verify'])) {
    echo json_encode(['tables' => verifyInterfaceBackup($args['verify']), 'sha256' => hash_file('sha256', $args['verify'])], JSON_PRETTY_PRINT) . PHP_EOL; exit;
}
if (!isset($args['development'], $args['host'], $args['database'], $args['backup-dir'])) {
    fwrite(STDERR, "Required: --development --host=... --database=... --backup-dir=PRIVATE_DIRECTORY\n"); exit(2);
}
require_once __DIR__ . '/../config/env.php';
if ($args['host'] !== onefitEnv('DB_HOST') || $args['database'] !== onefitEnv('DB_NAME')) throw new RuntimeException('Configured target differs from approved target');
$backupDir = realpath($args['backup-dir']);
if (!$backupDir || !is_dir($backupDir) || !is_writable($backupDir)) throw new RuntimeException('Create a private writable backup directory first');
$project = realpath(__DIR__ . '/..');
// This XAMPP project is below htdocs. Backup must be outside the entire public root.
$public = realpath(__DIR__ . '/../../..');
foreach ([$project, $public] as $forbidden) {
    $normalized = strtolower(str_replace('\\', '/', $backupDir)) . '/';
    if ($forbidden && str_starts_with($normalized, strtolower(str_replace('\\', '/', $forbidden)) . '/')) throw new RuntimeException('Backup directory is public or inside the repository');
}
require __DIR__ . '/../config/conn.php';
$version = '20260910_interface_v1';
$lock = 'onefit.' . $version;
$stmt = $conn->prepare('SELECT GET_LOCK(?, 10)'); $stmt->bind_param('s', $lock); $stmt->execute();
if ((int) $stmt->get_result()->fetch_row()[0] !== 1) throw new RuntimeException('Migration already running');
$stmt->close();
try {
    $hasJournal = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='onefit_migrations'")->fetch_row()[0];
    if ($hasJournal) {
        $stmt = $conn->prepare('SELECT versao FROM onefit_migrations WHERE versao=?'); $stmt->bind_param('s', $version); $stmt->execute();
        $done = (bool) $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($done) { echo "Migration already applied; no changes.\n"; exit; }
    }
    $tables = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetch_all(MYSQLI_ASSOC);
    $backup = ['format'=>'onefit-interface-backup-v1', 'database'=>$args['database'], 'created_utc'=>gmdate('c'), 'tables'=>[]];
    $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $conn->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    foreach ($tables as $table) {
        $name = $table['TABLE_NAME']; $quoted = '`' . str_replace('`', '``', $name) . '`';
        $schema = $conn->query('SHOW CREATE TABLE ' . $quoted)->fetch_row()[1];
        $rows = $conn->query('SELECT * FROM ' . $quoted)->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) foreach ($row as &$value) if ($value !== null) $value = base64_encode((string) $value);
        unset($row, $value);
        $backup['tables'][$name] = ['schema'=>$schema, 'count'=>count($rows), 'sha256'=>hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)), 'rows'=>$rows];
    }
    $conn->commit();
    $path = $backupDir . DIRECTORY_SEPARATOR . 'onefit-before-interface-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $bytes = json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $stream = fopen($path, 'xb');
    if (!$stream || fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream)) throw new RuntimeException('Incomplete backup');
    if (function_exists('fsync')) fsync($stream);
    fclose($stream);
    $counts = verifyInterfaceBackup($path);
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $path))) throw new RuntimeException('Backup hash mismatch');
    echo json_encode(['backup'=>$path, 'sha256'=>hash_file('sha256', $path), 'counts'=>$counts], JSON_PRETTY_PRINT) . PHP_EOL;
    $sql = file_get_contents(__DIR__ . '/migrations/interface-20260910.sql');
    $hasLanguage = (int) $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='preferencias_usuario' AND COLUMN_NAME='idioma'")->fetch_row()[0];
    $statements = explode(';', preg_replace('/^--.*$/m', '', $sql));
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!$statement || ($hasLanguage && str_starts_with($statement, 'ALTER TABLE'))) continue;
        $conn->query($statement);
    }
    // DDL auto-commits in MySQL 5.7; explicitly verify instead of promising rollback.
    $column = $conn->query("SHOW COLUMNS FROM preferencias_usuario LIKE 'idioma'")->fetch_assoc();
    if ($column['Type'] !== 'varchar(5)' || $column['Default'] !== 'pt-BR') throw new RuntimeException('Language column mismatch');
    foreach ($backup['tables'] as $name => $entry) {
        $quoted = '`' . str_replace('`', '``', $name) . '`';
        $current = $conn->query('SELECT * FROM ' . $quoted)->fetch_all(MYSQLI_ASSOC);
        $before = $entry['rows']; $after = [];
        foreach ($current as $row) {
            if ($name === 'preferencias_usuario' && !$hasLanguage) unset($row['idioma']);
            foreach ($row as &$value) if ($value !== null) $value = base64_encode((string) $value);
            unset($value); $after[] = $row;
        }
        $hashRows = static function ($rows) { $hashes = array_map(static fn($row) => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)), $rows); sort($hashes); return $hashes; };
        if ($hashRows($before) !== $hashRows($after)) throw new RuntimeException('Existing rows changed during migration; inspect concurrent activity. Backup is intact.');
    }
    $conn->query('CREATE TABLE IF NOT EXISTS onefit_migrations (versao VARCHAR(96) PRIMARY KEY, aplicada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $stmt = $conn->prepare('INSERT INTO onefit_migrations (versao) VALUES (?)'); $stmt->bind_param('s', $version); $stmt->execute(); $stmt->close();
    echo "Migration verified. Existing data preserved.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration stopped; code=' . $e->getCode() . '; ' . $e->getMessage() . PHP_EOL); exit(1);
} finally {
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)'); $stmt->bind_param('s', $lock); $stmt->execute(); $stmt->close();
}
