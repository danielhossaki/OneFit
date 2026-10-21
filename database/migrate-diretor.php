<?php
/**
 * database/migrate-diretor.php — hierarquia administrativa, versão 20261020_diretor_v1.
 *
 * Somente pela linha de comando. O alvo é o banco do .env da cópia onde o
 * script roda; --host e --database precisam coincidir com ele (confirmação
 * explícita do destino). Nenhuma conta muda de cargo: a migração só permite
 * gravar 'diretor'. Quem será o primeiro diretor é decidido à parte, com
 * database/diretor-recuperar.php.
 *
 *   Inspeção (somente leitura, relatório só com contagens):
 *     php database/migrate-diretor.php --inspecionar --host=H --database=D
 *   Aplicação (backup verificado de usuarios antes do DDL):
 *     php database/migrate-diretor.php --aplicar --host=H --database=D --backup-dir=DIRETORIO_PRIVADO
 *   Reversão (só se nenhuma conta for diretor; também com backup verificado):
 *     php database/migrate-diretor.php --reverter --host=H --database=D --backup-dir=DIRETORIO_PRIVADO
 *   Conferência de um backup:
 *     php database/migrate-diretor.php --verify=ARQUIVO
 *
 * Aplicação: exige 20261020_funcionarios_v1; acrescenta 'diretor' ao FINAL do
 * ENUM usuarios.tipo_usuario em modo estrito, preservando valores, ordem,
 * nulidade, padrão, collation e comentário; confere que nenhuma linha de
 * usuarios mudou; registra a versão em onefit_migrations. Rodar de novo não
 * altera nada. A reversão desfaz exatamente isso.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const DIRETOR_VERSAO = '20261020_diretor_v1';
const DIRETOR_PRE_REQUISITO = '20261020_funcionarios_v1';
const DIRETOR_FORMATO_BACKUP = 'onefit-diretor-backup-v1';
const DIRETOR_CARGO = 'diretor';

function diretorVerificarBackup(string $caminho): int
{
    $dados = json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
    $usuarios = $dados['tables']['usuarios'] ?? null;
    if (($dados['format'] ?? '') !== DIRETOR_FORMATO_BACKUP || !$usuarios) {
        throw new RuntimeException('Formato de backup inválido');
    }
    if (count($usuarios['rows']) !== $usuarios['count'] || !hash_equals($usuarios['sha256'], hash('sha256', json_encode($usuarios['rows'], JSON_THROW_ON_ERROR)))) {
        throw new RuntimeException('Falha na verificação do backup');
    }
    return $usuarios['count'];
}

function diretorEnumValores(string $tipo): array
{
    if (!preg_match('/^enum\((.*)\)$/is', trim($tipo), $corpo)) {
        throw new RuntimeException('usuarios.tipo_usuario não é ENUM');
    }
    preg_match_all("/'((?:[^']|'')*)'/", $corpo[1], $valores);
    return array_map(static fn(string $v): string => str_replace("''", "'", $v), $valores[1]);
}

function diretorColuna(mysqli $conn): array
{
    $coluna = $conn->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_assoc();
    if (!$coluna) {
        throw new RuntimeException('Coluna usuarios.tipo_usuario não encontrada');
    }
    foreach (['CHARACTER_SET_NAME', 'COLLATION_NAME'] as $atributo) {
        if ($coluna[$atributo] !== null && !preg_match('/^[A-Za-z0-9_]+$/', $coluna[$atributo])) {
            throw new RuntimeException('Atributo inesperado na coluna: ' . $atributo);
        }
    }
    return $coluna;
}

/** MariaDB devolve o padrão entre aspas; MySQL, sem. NULL vira null. */
function diretorPadrao(?string $valor): ?string
{
    if ($valor === null || strtoupper($valor) === 'NULL') {
        return null;
    }
    if (strlen($valor) >= 2 && $valor[0] === "'" && str_ends_with($valor, "'")) {
        return str_replace("''", "'", substr($valor, 1, -1));
    }
    return $valor;
}

function diretorLinhasUsuarios(mysqli $conn): array
{
    $linhas = $conn->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC);
    foreach ($linhas as &$linha) {
        foreach ($linha as &$valor) {
            if ($valor !== null) {
                $valor = base64_encode((string) $valor);
            }
        }
        unset($valor);
    }
    unset($linha);
    return $linhas;
}

function diretorVersaoRegistrada(mysqli $conn, string $versao): bool
{
    if (!(int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onefit_migrations'")->fetch_row()[0]) {
        return false;
    }
    $stmt = $conn->prepare('SELECT versao FROM onefit_migrations WHERE versao = ?');
    $stmt->bind_param('s', $versao);
    $stmt->execute();
    $registrada = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $registrada;
}

/** Relatório somente leitura: só contagens, nunca dados pessoais. */
function diretorInspecionar(mysqli $conn): array
{
    $porCargo = [];
    foreach ($conn->query("SELECT COALESCE(tipo_usuario, '(NULL)') AS cargo, COALESCE(status, '(NULL)') AS status, COUNT(*) AS total FROM usuarios GROUP BY tipo_usuario, status ORDER BY tipo_usuario, status") as $linha) {
        $porCargo[$linha['cargo'] === '' ? '(vazio)' : $linha['cargo']][$linha['status']] = (int) $linha['total'];
    }
    $valores = diretorEnumValores(diretorColuna($conn)['COLUMN_TYPE']);
    return [
        'banco' => $conn->query('SELECT DATABASE()')->fetch_row()[0],
        'versao_servidor' => $conn->server_info,
        'tipo_usuario_valores' => $valores,
        'diretor_no_enum' => in_array(DIRETOR_CARGO, $valores, true),
        'usuarios_por_cargo_e_status' => $porCargo,
        'diretores_ativos' => (int) $conn->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'diretor' AND status = 'ativo'")->fetch_row()[0],
        'pre_requisito_' . DIRETOR_PRE_REQUISITO => diretorVersaoRegistrada($conn, DIRETOR_PRE_REQUISITO),
        'migracao_registrada' => diretorVersaoRegistrada($conn, DIRETOR_VERSAO),
    ];
}

/** Backup verificado de usuarios (snapshot consistente, somente leitura). */
function diretorBackup(mysqli $conn, string $diretorio, string $banco, string $operacao): array
{
    $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $conn->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $esquema = $conn->query('SHOW CREATE TABLE usuarios')->fetch_row()[1];
    $linhas = diretorLinhasUsuarios($conn);
    $conn->commit();
    $backup = [
        'format' => DIRETOR_FORMATO_BACKUP,
        'version' => DIRETOR_VERSAO,
        'operation' => $operacao,
        'database' => $banco,
        'created_utc' => gmdate('c'),
        'tables' => ['usuarios' => [
            'schema' => $esquema,
            'count' => count($linhas),
            'sha256' => hash('sha256', json_encode($linhas, JSON_THROW_ON_ERROR)),
            'rows' => $linhas,
        ]],
    ];
    $caminho = $diretorio . DIRECTORY_SEPARATOR . 'onefit-before-diretor-' . $operacao . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $bytes = json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $arquivo = fopen($caminho, 'xb');
    if (!$arquivo || fwrite($arquivo, $bytes) !== strlen($bytes) || !fflush($arquivo)) {
        throw new RuntimeException('Backup incompleto');
    }
    fclose($arquivo);
    diretorVerificarBackup($caminho);
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $caminho))) {
        throw new RuntimeException('Hash do backup não confere');
    }
    return [$caminho, $linhas];
}

/** Redefine o ENUM com $valores, preservando os demais atributos da coluna (modo estrito). */
function diretorAlterarEnum(mysqli $conn, array $coluna, array $valores): void
{
    $lista = implode(',', array_map(static fn(string $v): string => "'" . $conn->real_escape_string($v) . "'", $valores));
    $sql = "ALTER TABLE usuarios MODIFY tipo_usuario ENUM($lista)";
    if ($coluna['CHARACTER_SET_NAME'] !== null) {
        $sql .= ' CHARACTER SET ' . $coluna['CHARACTER_SET_NAME'] . ' COLLATE ' . $coluna['COLLATION_NAME'];
    }
    $sql .= $coluna['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';
    $padrao = diretorPadrao($coluna['COLUMN_DEFAULT']);
    if ($padrao !== null) {
        $sql .= " DEFAULT '" . $conn->real_escape_string($padrao) . "'";
    } elseif ($coluna['IS_NULLABLE'] === 'YES') {
        $sql .= ' DEFAULT NULL';
    }
    if ((string) $coluna['COLUMN_COMMENT'] !== '') {
        $sql .= " COMMENT '" . $conn->real_escape_string($coluna['COLUMN_COMMENT']) . "'";
    }
    $conn->query("SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES')");
    $conn->query($sql);
}

/** DDL faz commit implícito: em vez de prometer rollback, confere coluna e linhas. */
function diretorConferir(mysqli $conn, array $colunaAntes, array $valoresEsperados, array $linhasAntes, string $backup): array
{
    $depois = diretorColuna($conn);
    if (diretorEnumValores($depois['COLUMN_TYPE']) !== $valoresEsperados) {
        throw new RuntimeException('ENUM resultante difere do esperado. Backup intacto em: ' . $backup);
    }
    foreach (['IS_NULLABLE', 'CHARACTER_SET_NAME', 'COLLATION_NAME', 'COLUMN_COMMENT'] as $atributo) {
        if ($depois[$atributo] !== $colunaAntes[$atributo]) {
            throw new RuntimeException('Atributo da coluna mudou: ' . $atributo . '. Backup intacto em: ' . $backup);
        }
    }
    if (diretorPadrao($depois['COLUMN_DEFAULT']) !== diretorPadrao($colunaAntes['COLUMN_DEFAULT'])) {
        throw new RuntimeException('Valor padrão da coluna mudou. Backup intacto em: ' . $backup);
    }
    if (diretorLinhasUsuarios($conn) !== $linhasAntes) {
        throw new RuntimeException('Linhas de usuarios mudaram durante a migração; verifique atividade concorrente. Backup intacto em: ' . $backup);
    }
    return $depois;
}

$args = getopt('', ['aplicar', 'reverter', 'inspecionar', 'host:', 'database:', 'backup-dir:', 'verify:']);

if (isset($args['verify'])) {
    echo json_encode(['usuarios' => diretorVerificarBackup($args['verify']), 'sha256' => hash_file('sha256', $args['verify'])], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
$operacao = isset($args['aplicar']) ? 'aplicar' : (isset($args['reverter']) ? 'reverter' : (isset($args['inspecionar']) ? 'inspecionar' : null));
if ($operacao === null || (isset($args['aplicar']) && isset($args['reverter'])) || !isset($args['host'], $args['database'])
    || ($operacao !== 'inspecionar' && !isset($args['backup-dir']))) {
    fwrite(STDERR, "Uso: --inspecionar --host=... --database=...\n     --aplicar|--reverter --host=... --database=... --backup-dir=DIRETORIO_PRIVADO\n     --verify=ARQUIVO\n");
    exit(2);
}

require_once __DIR__ . '/../config/env.php';
if ($args['host'] !== onefitEnv('DB_HOST') || $args['database'] !== onefitEnv('DB_NAME')) {
    fwrite(STDERR, "Destino informado difere do banco configurado no .env desta cópia. Nada foi feito.\n");
    exit(2);
}
if ($operacao !== 'inspecionar') {
    $backupDir = realpath($args['backup-dir']);
    if (!$backupDir || !is_dir($backupDir) || !is_writable($backupDir)) {
        fwrite(STDERR, "Crie antes um diretório privado e gravável para o backup.\n");
        exit(2);
    }
    // O projeto fica no htdocs: o backup tem de ficar fora do repositório e de toda a raiz pública.
    foreach ([realpath(__DIR__ . '/..'), realpath(__DIR__ . '/../../..')] as $proibido) {
        $normalizado = strtolower(str_replace('\\', '/', $backupDir)) . '/';
        if ($proibido && str_starts_with($normalizado, strtolower(str_replace('\\', '/', $proibido)) . '/')) {
            fwrite(STDERR, "O diretório de backup está na raiz pública ou dentro do repositório.\n");
            exit(2);
        }
    }
}

require __DIR__ . '/../config/conn.php';

if ($operacao === 'inspecionar') {
    echo json_encode(diretorInspecionar($conn), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

$trava = 'onefit.' . DIRETOR_VERSAO;
$stmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
$stmt->bind_param('s', $trava);
$stmt->execute();
if ((int) $stmt->get_result()->fetch_row()[0] !== 1) {
    fwrite(STDERR, "Outra execução desta migração está em andamento.\n");
    exit(1);
}
$stmt->close();

try {
    $registrada = diretorVersaoRegistrada($conn, DIRETOR_VERSAO);
    $colunaAntes = diretorColuna($conn);
    $valoresAntes = diretorEnumValores($colunaAntes['COLUMN_TYPE']);

    if ($operacao === 'aplicar') {
        if ($registrada) {
            echo 'Migração ' . DIRETOR_VERSAO . " já aplicada; nada foi alterado.\n";
            exit;
        }
        if (!diretorVersaoRegistrada($conn, DIRETOR_PRE_REQUISITO)) {
            throw new RuntimeException('Aplique antes a migração ' . DIRETOR_PRE_REQUISITO . ' (database/migrate-funcionarios.php). Nada foi feito.');
        }
        [$backup, $linhasAntes] = diretorBackup($conn, $backupDir, $args['database'], 'aplicar');
        $valoresDepois = in_array(DIRETOR_CARGO, $valoresAntes, true) ? $valoresAntes : array_merge($valoresAntes, [DIRETOR_CARGO]);
        if ($valoresDepois !== $valoresAntes) {
            diretorAlterarEnum($conn, $colunaAntes, $valoresDepois);
        }
        $colunaDepois = diretorConferir($conn, $colunaAntes, $valoresDepois, $linhasAntes, $backup);
        $versao = DIRETOR_VERSAO;
        $stmt = $conn->prepare('INSERT INTO onefit_migrations (versao) VALUES (?)');
        $stmt->bind_param('s', $versao);
        $stmt->execute();
        $stmt->close();
    } else {
        if (!$registrada && !in_array(DIRETOR_CARGO, $valoresAntes, true)) {
            echo 'Migração ' . DIRETOR_VERSAO . " não está aplicada; nada foi alterado.\n";
            exit;
        }
        // Nunca apaga um cargo em uso: contas diretor precisam ser tratadas antes, explicitamente.
        $emUso = (int) $conn->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'diretor'")->fetch_row()[0];
        if ($emUso > 0) {
            throw new RuntimeException("Há $emUso conta(s) com o cargo diretor. Defina antes o cargo delas; nada foi alterado.");
        }
        [$backup, $linhasAntes] = diretorBackup($conn, $backupDir, $args['database'], 'reverter');
        $valoresDepois = array_values(array_filter($valoresAntes, static fn(string $v): bool => $v !== DIRETOR_CARGO));
        if (diretorPadrao($colunaAntes['COLUMN_DEFAULT']) === DIRETOR_CARGO) {
            throw new RuntimeException('O valor padrão da coluna é diretor; nada foi alterado.');
        }
        diretorAlterarEnum($conn, $colunaAntes, $valoresDepois);
        $colunaDepois = diretorConferir($conn, $colunaAntes, $valoresDepois, $linhasAntes, $backup);
        $versao = DIRETOR_VERSAO;
        $stmt = $conn->prepare('DELETE FROM onefit_migrations WHERE versao = ?');
        $stmt->bind_param('s', $versao);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'versao' => DIRETOR_VERSAO,
        'operacao' => $operacao,
        'backup' => $backup,
        'backup_sha256' => hash_file('sha256', $backup),
        'backup_linhas_usuarios' => count($linhasAntes),
        'enum_antes' => $valoresAntes,
        'enum_depois' => diretorEnumValores($colunaDepois['COLUMN_TYPE']),
        'usuarios_por_cargo_e_status' => diretorInspecionar($conn)['usuarios_por_cargo_e_status'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo $operacao === 'aplicar' ? "Migração verificada. Nenhuma conta mudou de cargo.\n" : "Reversão verificada. Nenhuma conta mudou de cargo.\n";
} catch (Throwable $erro) {
    fwrite(STDERR, 'Migração interrompida; código=' . $erro->getCode() . '; ' . $erro->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->bind_param('s', $trava);
    $stmt->execute();
    $stmt->close();
}
