<?php
/**
 * database/migrate-funcionarios.php — Etapa 2 (funcionários), versão 20261020_funcionarios_v1.
 *
 * Somente pela linha de comando. O alvo é o banco do .env da cópia onde o
 * script roda; --host e --database precisam coincidir com ele (confirmação
 * explícita do destino). Nada é apagado nem reordenado.
 *
 *   Inspeção (somente leitura, relatório só com contagens):
 *     php database/migrate-funcionarios.php --inspecionar --host=H --database=D
 *   Aplicação (backup verificado de usuarios antes de qualquer DDL):
 *     php database/migrate-funcionarios.php --aplicar --host=H --database=D --backup-dir=DIRETORIO_PRIVADO
 *   Conferência de um backup:
 *     php database/migrate-funcionarios.php --verify=ARQUIVO
 *
 * Aplicação:
 *   1. backup da tabela usuarios (snapshot consistente) fora do repositório e
 *      da raiz pública, relido e conferido por SHA-256;
 *   2. acrescenta ao FINAL do ENUM usuarios.tipo_usuario os cargos de
 *      funcionário que faltarem (vendedor, professor, recepcionista,
 *      faxineira; o banco remoto não tem nem vendedor), preservando valores,
 *      ordem, nulidade, padrão, collation e comentário (modo estrito: nunca
 *      trunca dados);
 *   3. confere que nenhuma linha de usuarios mudou;
 *   4. cria auditoria_eventos (database/migrations/funcionarios-20261020.sql);
 *   5. registra a versão em onefit_migrations. Rodar de novo não altera nada.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const MIGRACAO_VERSAO = '20261020_funcionarios_v1';
const MIGRACAO_FORMATO_BACKUP = 'onefit-funcionarios-backup-v1';
// Todos os cargos que o admin atribui (onefitCargosAtribuiveisPorAdmin); só os ausentes são acrescentados, nesta ordem.
const MIGRACAO_CARGOS_NOVOS = ['vendedor', 'professor', 'recepcionista', 'faxineira'];
const MIGRACAO_COLUNAS_AUDITORIA = ['id_evento', 'categoria', 'acao', 'id_operador', 'id_alvo', 'detalhes', 'criado_em'];

function migracaoVerificarBackup(string $caminho): array
{
    $dados = json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
    if (($dados['format'] ?? '') !== MIGRACAO_FORMATO_BACKUP) {
        throw new RuntimeException('Formato de backup inválido');
    }
    $contagens = [];
    foreach ($dados['tables'] as $tabela => $entrada) {
        $linhas = $entrada['rows'];
        if (count($linhas) !== $entrada['count'] || !hash_equals($entrada['sha256'], hash('sha256', json_encode($linhas, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('Falha na verificação do backup');
        }
        foreach ($linhas as $linha) {
            foreach ($linha as $valor) {
                if ($valor !== null && base64_decode($valor, true) === false) {
                    throw new RuntimeException('Célula inválida no backup');
                }
            }
        }
        $contagens[$tabela] = count($linhas);
    }
    return $contagens;
}

function migracaoEnumValores(string $tipo): array
{
    if (!preg_match('/^enum\((.*)\)$/is', trim($tipo), $corpo)) {
        throw new RuntimeException('usuarios.tipo_usuario não é ENUM');
    }
    preg_match_all("/'((?:[^']|'')*)'/", $corpo[1], $valores);
    return array_map(static fn(string $v): string => str_replace("''", "'", $v), $valores[1]);
}

function migracaoColuna(mysqli $conn): array
{
    $coluna = $conn->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_assoc();
    if (!$coluna) {
        throw new RuntimeException('Coluna usuarios.tipo_usuario não encontrada');
    }
    return $coluna;
}

/** MariaDB devolve o padrão entre aspas; MySQL, sem. NULL vira null. */
function migracaoPadrao(?string $valor): ?string
{
    if ($valor === null || strtoupper($valor) === 'NULL') {
        return null;
    }
    if (strlen($valor) >= 2 && $valor[0] === "'" && str_ends_with($valor, "'")) {
        return str_replace("''", "'", substr($valor, 1, -1));
    }
    return $valor;
}

/** Hash de cada linha de usuarios (células em base64, como no backup). */
function migracaoHashesUsuarios(array $linhas): array
{
    $hashes = array_map(static fn(array $linha): string => hash('sha256', json_encode($linha, JSON_THROW_ON_ERROR)), $linhas);
    sort($hashes);
    return $hashes;
}

function migracaoLinhasUsuarios(mysqli $conn): array
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

function migracaoAplicada(mysqli $conn): bool
{
    $temJournal = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onefit_migrations'")->fetch_row()[0];
    if (!$temJournal) {
        return false;
    }
    $versao = MIGRACAO_VERSAO;
    $stmt = $conn->prepare('SELECT versao FROM onefit_migrations WHERE versao = ?');
    $stmt->bind_param('s', $versao);
    $stmt->execute();
    $aplicada = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $aplicada;
}

/** Relatório somente leitura: só contagens, nunca dados pessoais. */
function migracaoInspecionar(mysqli $conn): array
{
    $coluna = migracaoColuna($conn);
    $porCargo = [];
    foreach ($conn->query("SELECT COALESCE(tipo_usuario, '(NULL)') AS cargo, COALESCE(status, '(NULL)') AS status, COUNT(*) AS total FROM usuarios GROUP BY tipo_usuario, status ORDER BY tipo_usuario, status") as $linha) {
        $porCargo[$linha['cargo'] === '' ? '(vazio)' : $linha['cargo']][$linha['status']] = (int) $linha['total'];
    }
    $contar = static fn(string $sql): int => (int) $conn->query($sql)->fetch_row()[0];
    return [
        'banco' => $conn->query('SELECT DATABASE()')->fetch_row()[0],
        'versao_servidor' => $conn->server_info,
        'sql_mode' => $conn->query('SELECT @@SESSION.sql_mode')->fetch_row()[0],
        'usuarios_engine' => $conn->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'")->fetch_row()[0],
        'tipo_usuario' => [
            'valores' => migracaoEnumValores($coluna['COLUMN_TYPE']),
            'nulo' => $coluna['IS_NULLABLE'],
            'padrao' => migracaoPadrao($coluna['COLUMN_DEFAULT']),
            'collation' => $coluna['COLLATION_NAME'],
            'cargos_novos_faltando' => array_values(array_diff(MIGRACAO_CARGOS_NOVOS, migracaoEnumValores($coluna['COLUMN_TYPE']))),
        ],
        'usuarios_por_cargo_e_status' => $porCargo,
        'usuarios_cargo_vazio_ou_nulo' => $contar("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario IS NULL OR tipo_usuario = ''"),
        'usuarios_cpf_com_mascara' => $contar("SELECT COUNT(*) FROM usuarios WHERE cpf REGEXP '[^0-9]'"),
        'usuarios_email_duplicado_ignorando_maiusculas' => $contar('SELECT COUNT(*) FROM (SELECT LOWER(TRIM(email)) e FROM usuarios GROUP BY e HAVING COUNT(*) > 1) d'),
        'cadastro_profissional' => [
            'total' => $contar('SELECT COUNT(*) FROM cadastro_profissional'),
            'vinculados' => $contar('SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IS NOT NULL'),
            'sem_conta' => $contar('SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IS NULL'),
            'contas_com_mais_de_um_cadastro' => $contar('SELECT COUNT(*) FROM (SELECT id_usuario FROM cadastro_profissional WHERE id_usuario IS NOT NULL GROUP BY id_usuario HAVING COUNT(*) > 1) d'),
            'vinculados_a_conta_que_nao_e_professor' => $contar("SELECT COUNT(*) FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario WHERE u.tipo_usuario NOT IN ('profissional', 'professor')"),
            'vinculados_a_conta_inexistente' => $contar('SELECT COUNT(*) FROM cadastro_profissional cp LEFT JOIN usuarios u ON u.id_usuario = cp.id_usuario WHERE cp.id_usuario IS NOT NULL AND u.id_usuario IS NULL'),
            'professores_sem_cadastro_vinculado' => $contar("SELECT COUNT(*) FROM usuarios u WHERE u.tipo_usuario IN ('profissional', 'professor') AND NOT EXISTS (SELECT 1 FROM cadastro_profissional cp WHERE cp.id_usuario = u.id_usuario)"),
        ],
        'auditoria_eventos_existe' => $contar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_eventos'") === 1,
        'migracao_registrada' => migracaoAplicada($conn),
    ];
}

$args = getopt('', ['aplicar', 'inspecionar', 'host:', 'database:', 'backup-dir:', 'verify:']);

if (isset($args['verify'])) {
    echo json_encode(['tabelas' => migracaoVerificarBackup($args['verify']), 'sha256' => hash_file('sha256', $args['verify'])], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
$aplicar = isset($args['aplicar']);
if ((!$aplicar && !isset($args['inspecionar'])) || !isset($args['host'], $args['database']) || ($aplicar && !isset($args['backup-dir']))) {
    fwrite(STDERR, "Uso: --inspecionar --host=... --database=...\n     --aplicar --host=... --database=... --backup-dir=DIRETORIO_PRIVADO\n     --verify=ARQUIVO\n");
    exit(2);
}

require_once __DIR__ . '/../config/env.php';
if ($args['host'] !== onefitEnv('DB_HOST') || $args['database'] !== onefitEnv('DB_NAME')) {
    fwrite(STDERR, "Destino informado difere do banco configurado no .env desta cópia. Nada foi feito.\n");
    exit(2);
}

if ($aplicar) {
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

if (!$aplicar) {
    echo json_encode(migracaoInspecionar($conn), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

$trava = 'onefit.' . MIGRACAO_VERSAO;
$stmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
$stmt->bind_param('s', $trava);
$stmt->execute();
if ((int) $stmt->get_result()->fetch_row()[0] !== 1) {
    fwrite(STDERR, "Outra execução desta migração está em andamento.\n");
    exit(1);
}
$stmt->close();

try {
    if (migracaoAplicada($conn)) {
        echo "Migração " . MIGRACAO_VERSAO . " já aplicada; nada foi alterado.\n";
        exit;
    }
    $antes = migracaoInspecionar($conn);
    $colunaAntes = migracaoColuna($conn);
    $valoresAntes = migracaoEnumValores($colunaAntes['COLUMN_TYPE']);
    foreach (['CHARACTER_SET_NAME', 'COLLATION_NAME'] as $atributo) {
        if ($colunaAntes[$atributo] !== null && !preg_match('/^[A-Za-z0-9_]+$/', $colunaAntes[$atributo])) {
            throw new RuntimeException('Atributo inesperado na coluna: ' . $atributo);
        }
    }

    // 1. Backup verificado de usuarios (snapshot consistente, somente leitura).
    $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $conn->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $esquema = $conn->query('SHOW CREATE TABLE usuarios')->fetch_row()[1];
    $linhasAntes = migracaoLinhasUsuarios($conn);
    $conn->commit();
    $backup = [
        'format' => MIGRACAO_FORMATO_BACKUP,
        'version' => MIGRACAO_VERSAO,
        'database' => $args['database'],
        'created_utc' => gmdate('c'),
        'tables' => ['usuarios' => [
            'schema' => $esquema,
            'count' => count($linhasAntes),
            'sha256' => hash('sha256', json_encode($linhasAntes, JSON_THROW_ON_ERROR)),
            'rows' => $linhasAntes,
        ]],
    ];
    $caminho = $backupDir . DIRECTORY_SEPARATOR . 'onefit-before-funcionarios-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $bytes = json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $arquivo = fopen($caminho, 'xb');
    if (!$arquivo || fwrite($arquivo, $bytes) !== strlen($bytes) || !fflush($arquivo)) {
        throw new RuntimeException('Backup incompleto');
    }
    fclose($arquivo);
    $contagens = migracaoVerificarBackup($caminho);
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $caminho))) {
        throw new RuntimeException('Hash do backup não confere');
    }

    // 2. ENUM: só acrescenta ao final, em modo estrito.
    $faltando = array_values(array_diff(MIGRACAO_CARGOS_NOVOS, $valoresAntes));
    if ($faltando) {
        $lista = implode(',', array_map(static fn(string $v): string => "'" . $conn->real_escape_string($v) . "'", array_merge($valoresAntes, $faltando)));
        $sql = "ALTER TABLE usuarios MODIFY tipo_usuario ENUM($lista)";
        if ($colunaAntes['CHARACTER_SET_NAME'] !== null) {
            $sql .= ' CHARACTER SET ' . $colunaAntes['CHARACTER_SET_NAME'] . ' COLLATE ' . $colunaAntes['COLLATION_NAME'];
        }
        $sql .= $colunaAntes['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';
        $padrao = migracaoPadrao($colunaAntes['COLUMN_DEFAULT']);
        if ($padrao !== null) {
            $sql .= " DEFAULT '" . $conn->real_escape_string($padrao) . "'";
        } elseif ($colunaAntes['IS_NULLABLE'] === 'YES') {
            $sql .= ' DEFAULT NULL';
        }
        if ((string) $colunaAntes['COLUMN_COMMENT'] !== '') {
            $sql .= " COMMENT '" . $conn->real_escape_string($colunaAntes['COLUMN_COMMENT']) . "'";
        }
        $conn->query("SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES')");
        $conn->query($sql);
    }

    // DDL faz commit implícito: em vez de prometer rollback, confere tudo.
    $colunaDepois = migracaoColuna($conn);
    if (migracaoEnumValores($colunaDepois['COLUMN_TYPE']) !== array_merge($valoresAntes, $faltando)) {
        throw new RuntimeException('ENUM resultante difere do esperado. Backup intacto em: ' . $caminho);
    }
    foreach (['IS_NULLABLE', 'CHARACTER_SET_NAME', 'COLLATION_NAME', 'COLUMN_COMMENT'] as $atributo) {
        if ($colunaDepois[$atributo] !== $colunaAntes[$atributo]) {
            throw new RuntimeException('Atributo da coluna mudou: ' . $atributo . '. Backup intacto em: ' . $caminho);
        }
    }
    if (migracaoPadrao($colunaDepois['COLUMN_DEFAULT']) !== migracaoPadrao($colunaAntes['COLUMN_DEFAULT'])) {
        throw new RuntimeException('Valor padrão da coluna mudou. Backup intacto em: ' . $caminho);
    }

    // 3. Nenhuma linha de usuarios pode ter mudado.
    if (migracaoHashesUsuarios(migracaoLinhasUsuarios($conn)) !== migracaoHashesUsuarios($linhasAntes)) {
        throw new RuntimeException('Linhas de usuarios mudaram durante a migração; verifique atividade concorrente. Backup intacto em: ' . $caminho);
    }

    // 4. Auditoria.
    $sqlArquivo = file_get_contents(__DIR__ . '/migrations/funcionarios-20261020.sql');
    foreach (explode(';', preg_replace('/^--.*$/m', '', $sqlArquivo)) as $comando) {
        if (trim($comando) !== '') {
            $conn->query(trim($comando));
        }
    }
    $colunasAuditoria = array_column($conn->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_eventos' ORDER BY ORDINAL_POSITION"
    )->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
    if ($colunasAuditoria !== MIGRACAO_COLUNAS_AUDITORIA) {
        throw new RuntimeException('Tabela auditoria_eventos com estrutura inesperada');
    }

    // 5. Registro da versão.
    $conn->query('CREATE TABLE IF NOT EXISTS onefit_migrations (versao VARCHAR(96) PRIMARY KEY, aplicada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $versao = MIGRACAO_VERSAO;
    $stmt = $conn->prepare('INSERT INTO onefit_migrations (versao) VALUES (?)');
    $stmt->bind_param('s', $versao);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'versao' => MIGRACAO_VERSAO,
        'backup' => $caminho,
        'backup_sha256' => hash_file('sha256', $caminho),
        'backup_linhas' => $contagens,
        'enum_antes' => $valoresAntes,
        'enum_depois' => migracaoEnumValores($colunaDepois['COLUMN_TYPE']),
        'usuarios_por_cargo_e_status_antes' => $antes['usuarios_por_cargo_e_status'],
        'usuarios_por_cargo_e_status_depois' => migracaoInspecionar($conn)['usuarios_por_cargo_e_status'],
        'auditoria_eventos' => 'criada',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo "Migração verificada. Dados existentes preservados.\n";
} catch (Throwable $erro) {
    fwrite(STDERR, 'Migração interrompida; código=' . $erro->getCode() . '; ' . $erro->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->bind_param('s', $trava);
    $stmt->execute();
    $stmt->close();
}
