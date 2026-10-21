<?php
/**
 * database/migrate-professores.php — disponibilidade dos professores, versão 20261021_professores_v1.
 *
 * Somente pela linha de comando. O alvo é o banco do .env da cópia onde o
 * script roda; --host e --database precisam coincidir com ele, e quem grava
 * (--aplicar, --reverter) ainda repete o nome do banco em --confirmar.
 * Nenhum cadastro é apagado nem muda de dono; nenhuma outra tabela é escrita.
 *
 *   Inspeção (somente leitura, relatório só com contagens e booleanos):
 *     php database/migrate-professores.php --inspecionar --host=H --database=D
 *   Aplicação (backup verificado de cadastro_profissional antes do DDL):
 *     php database/migrate-professores.php --aplicar --host=H --database=D --confirmar=D --backup-dir=DIRETORIO_PRIVADO
 *   Reversão (só se nenhum professor estiver indisponível; também com backup):
 *     php database/migrate-professores.php --reverter --host=H --database=D --confirmar=D --backup-dir=DIRETORIO_PRIVADO
 *   Conferência de um backup desta migração:
 *     php database/migrate-professores.php --verify=ARQUIVO
 *
 * Aplicação: exige 20261020_funcionarios_v1, tabela InnoDB com as colunas
 * esperadas e nenhuma conta com dois cadastros profissionais; roda
 * database/migrations/professores-20261021.sql (coluna disponivel_agendamento,
 * padrão 1, e chave única em id_usuario). Depois confere a definição da coluna
 * e do índice, que os dados de cadastro_profissional não mudaram, que todo
 * cadastro ficou disponível e que recebem agendamento exatamente os mesmos
 * professores de antes; compara usuarios, agendamento, disponibilidade_profissional
 * e profissional_aluno antes e depois; registra a versão em onefit_migrations.
 * Rodar de novo não altera nada. A reversão desfaz exatamente isso.
 *
 * O backup completo do banco (database/backup-verificar.php) vem antes, à parte.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const PROFESSORES_VERSAO = '20261021_professores_v1';
const PROFESSORES_PRE_REQUISITO = '20261020_funcionarios_v1';
const PROFESSORES_FORMATO_BACKUP = 'onefit-professores-backup-v1';
const PROFESSORES_COLUNA = 'disponivel_agendamento';
const PROFESSORES_INDICE = 'uk_cadastro_profissional_usuario';
const PROFESSORES_COLUNAS_EXIGIDAS = ['id_profissional', 'id_usuario', 'status'];
const PROFESSORES_TABELAS_RELACIONADAS = ['usuarios', 'agendamento', 'disponibilidade_profissional', 'profissional_aluno'];
const PROFESSORES_CARGOS = ['professor', 'profissional'];

function professoresVerificarBackup(string $caminho): int
{
    $dados = json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
    $tabela = $dados['tables']['cadastro_profissional'] ?? null;
    if (($dados['format'] ?? '') !== PROFESSORES_FORMATO_BACKUP || !$tabela) {
        throw new RuntimeException('Formato de backup inválido');
    }
    if (count($tabela['rows']) !== $tabela['count'] || !hash_equals($tabela['sha256'], hash('sha256', json_encode($tabela['rows'], JSON_THROW_ON_ERROR)))) {
        throw new RuntimeException('Falha na verificação do backup');
    }
    foreach ($tabela['rows'] as $linha) {
        foreach ($linha as $valor) {
            if ($valor !== null && base64_decode($valor, true) === false) {
                throw new RuntimeException('Célula inválida no backup');
            }
        }
    }
    return $tabela['count'];
}

function professoresVersaoRegistrada(mysqli $conn, string $versao): bool
{
    $temJournal = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'onefit_migrations'")->fetch_row()[0];
    if (!$temJournal) {
        return false;
    }
    $stmt = $conn->prepare('SELECT versao FROM onefit_migrations WHERE versao = ?');
    $stmt->bind_param('s', $versao);
    $stmt->execute();
    $registrada = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $registrada;
}

/** Colunas de cadastro_profissional na ordem da tabela. */
function professoresColunas(mysqli $conn): array
{
    return array_column($conn->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' ORDER BY ORDINAL_POSITION"
    )->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
}

/** A coluna nova está como a migração cria: TINYINT(1) NOT NULL DEFAULT 1. */
function professoresColunaCorreta(mysqli $conn): bool
{
    $coluna = $conn->query(
        "SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' AND COLUMN_NAME = '" . PROFESSORES_COLUNA . "'"
    )->fetch_assoc();
    return $coluna && $coluna['DATA_TYPE'] === 'tinyint' && $coluna['IS_NULLABLE'] === 'NO' && trim((string) $coluna['COLUMN_DEFAULT'], "'") === '1';
}

/** O índice existe e é único sobre id_usuario (só essa coluna). */
function professoresTemIndice(mysqli $conn, bool $exigirDefinicao = false): bool
{
    $stmt = $conn->prepare("SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS colunas FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' AND INDEX_NAME = ? GROUP BY NON_UNIQUE");
    $indice = PROFESSORES_INDICE;
    $stmt->bind_param('s', $indice);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$linha) {
        return false;
    }
    return !$exigirDefinicao || ((int) $linha['NON_UNIQUE'] === 0 && $linha['colunas'] === 'id_usuario');
}

/** Linhas de cadastro_profissional nas colunas dadas (células em base64). */
function professoresLinhas(mysqli $conn, array $colunas): array
{
    $lista = implode(', ', array_map(static fn(string $c): string => '`' . str_replace('`', '``', $c) . '`', $colunas));
    $linhas = $conn->query("SELECT $lista FROM cadastro_profissional ORDER BY id_profissional")->fetch_all(MYSQLI_ASSOC);
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

/** Contagem e hash (independente da ordem) de cada tabela relacionada que existir. */
function professoresRetratoRelacionadas(mysqli $conn): array
{
    $retrato = [];
    foreach (PROFESSORES_TABELAS_RELACIONADAS as $tabela) {
        $existe = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tabela'")->fetch_row()[0];
        if (!$existe) {
            continue;
        }
        $hashes = array_map(static fn(array $l): string => hash('sha256', json_encode($l, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)),
            $conn->query("SELECT * FROM `$tabela`")->fetch_all(MYSQLI_NUM));
        sort($hashes);
        $retrato[$tabela] = ['linhas' => count($hashes), 'sha256' => hash('sha256', implode("\n", $hashes))];
    }
    return $retrato;
}

/** IDs de cadastro que recebem agendamento: cargo professor, conta ativa, cadastro ativo e, com a coluna, disponível. */
function professoresAgendaveis(mysqli $conn): array
{
    $disponivel = in_array(PROFESSORES_COLUNA, professoresColunas($conn), true) ? ' AND cp.' . PROFESSORES_COLUNA . ' = 1' : '';
    $cargos = "'" . implode("', '", PROFESSORES_CARGOS) . "'";
    return array_map('intval', array_column($conn->query(
        "SELECT cp.id_profissional FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario
         WHERE u.tipo_usuario IN ($cargos) AND u.status = 'ativo' AND cp.status = 'ativo'$disponivel ORDER BY cp.id_profissional"
    )->fetch_all(), 0));
}

/** Privilégios do usuário conectado no banco atual (só booleanos; o nome do usuário não sai). */
function professoresPrivilegios(mysqli $conn): array
{
    $banco = (string) $conn->query('SELECT DATABASE()')->fetch_row()[0];
    $tem = [];
    foreach ($conn->query('SHOW GRANTS')->fetch_all() as [$grant]) {
        if (!preg_match('/^GRANT (.+?) ON (\S+) TO/i', $grant, $m)) {
            continue;
        }
        $escopo = str_replace('`', '', $m[2]);
        if ($escopo !== '*.*' && strcasecmp($escopo, $banco . '.*') !== 0 && strcasecmp($escopo, $banco . '.cadastro_profissional') !== 0) {
            continue;
        }
        foreach (array_map('trim', explode(',', strtoupper($m[1]))) as $privilegio) {
            $tem[$privilegio] = true;
        }
    }
    $todos = isset($tem['ALL PRIVILEGES']);
    return ['ALTER' => $todos || isset($tem['ALTER']), 'INDEX' => $todos || isset($tem['INDEX'])];
}

/** Relatório somente leitura: só contagens e booleanos, nunca dados pessoais. */
function professoresInspecionar(mysqli $conn): array
{
    $contar = static fn(string $sql): int => (int) $conn->query($sql)->fetch_row()[0];
    $colunas = professoresColunas($conn);
    $temColuna = in_array(PROFESSORES_COLUNA, $colunas, true);
    return [
        'banco' => $conn->query('SELECT DATABASE()')->fetch_row()[0],
        'versao_servidor' => $conn->server_info,
        'cadastro_profissional_engine' => $conn->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional'")->fetch_row()[0] ?? null,
        'colunas_exigidas_presentes' => !array_diff(PROFESSORES_COLUNAS_EXIGIDAS, $colunas),
        'privilegios' => professoresPrivilegios($conn),
        'cadastro_profissional' => [
            'total' => $contar('SELECT COUNT(*) FROM cadastro_profissional'),
            'ativos' => $contar("SELECT COUNT(*) FROM cadastro_profissional WHERE status = 'ativo'"),
            'sem_conta' => $contar('SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IS NULL'),
            'contas_com_mais_de_um_cadastro' => $contar('SELECT COUNT(*) FROM (SELECT id_usuario FROM cadastro_profissional WHERE id_usuario IS NOT NULL GROUP BY id_usuario HAVING COUNT(*) > 1) d'),
            'vinculados_a_conta_inexistente' => $contar('SELECT COUNT(*) FROM cadastro_profissional cp LEFT JOIN usuarios u ON u.id_usuario = cp.id_usuario WHERE cp.id_usuario IS NOT NULL AND u.id_usuario IS NULL'),
            'vinculados_a_conta_que_nao_e_professor' => $contar("SELECT COUNT(*) FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario WHERE u.tipo_usuario NOT IN ('profissional', 'professor')"),
            'indisponiveis' => $temColuna ? $contar('SELECT COUNT(*) FROM cadastro_profissional WHERE ' . PROFESSORES_COLUNA . ' = 0') : null,
        ],
        'recebem_agendamento' => count(professoresAgendaveis($conn)),
        'agendamentos_futuros_ativos' => $contar("SELECT COUNT(*) FROM agendamento WHERE status IN ('agendado', 'confirmado') AND data_evento >= CURDATE()"),
        'coluna_' . PROFESSORES_COLUNA => $temColuna,
        'coluna_' . PROFESSORES_COLUNA . '_correta' => $temColuna && professoresColunaCorreta($conn),
        'indice_' . PROFESSORES_INDICE => professoresTemIndice($conn),
        'pre_requisito_' . PROFESSORES_PRE_REQUISITO => professoresVersaoRegistrada($conn, PROFESSORES_PRE_REQUISITO),
        'migracao_registrada' => professoresVersaoRegistrada($conn, PROFESSORES_VERSAO),
    ];
}

/** Backup verificado de cadastro_profissional (snapshot consistente, somente leitura). */
function professoresBackup(mysqli $conn, string $diretorio, string $banco, string $operacao): string
{
    $conn->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $conn->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $esquema = $conn->query('SHOW CREATE TABLE cadastro_profissional')->fetch_row()[1];
    $linhas = professoresLinhas($conn, professoresColunas($conn));
    $conn->commit();
    $backup = [
        'format' => PROFESSORES_FORMATO_BACKUP,
        'version' => PROFESSORES_VERSAO,
        'operation' => $operacao,
        'database' => $banco,
        'created_utc' => gmdate('c'),
        // TIMESTAMP lidos na sessão em UTC: restaurar com time_zone = '+00:00'.
        'time_zone' => (string) $conn->query('SELECT @@session.time_zone')->fetch_row()[0],
        'tables' => ['cadastro_profissional' => [
            'schema' => $esquema,
            'count' => count($linhas),
            'sha256' => hash('sha256', json_encode($linhas, JSON_THROW_ON_ERROR)),
            'rows' => $linhas,
        ]],
    ];
    $caminho = $diretorio . DIRECTORY_SEPARATOR . 'onefit-before-professores-' . $operacao . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $bytes = json_encode($backup, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $arquivo = fopen($caminho, 'xb');
    if (!$arquivo || fwrite($arquivo, $bytes) !== strlen($bytes) || !fflush($arquivo)) {
        throw new RuntimeException('Backup incompleto');
    }
    fclose($arquivo);
    professoresVerificarBackup($caminho);
    if (!hash_equals(hash('sha256', $bytes), hash_file('sha256', $caminho))) {
        throw new RuntimeException('Hash do backup não confere');
    }
    return $caminho;
}

/** Compara os retratos das tabelas relacionadas; a migração não as escreve, então diferença = atividade concorrente. */
function professoresCompararRelacionadas(array $antes, array $depois): array
{
    $iguais = [];
    foreach ($antes as $tabela => $retrato) {
        $iguais[$tabela] = ($depois[$tabela] ?? null) === $retrato;
    }
    return $iguais;
}

$args = getopt('', ['aplicar', 'reverter', 'inspecionar', 'host:', 'database:', 'confirmar:', 'backup-dir:', 'verify:']);

if (isset($args['verify'])) {
    echo json_encode(['cadastro_profissional' => professoresVerificarBackup($args['verify']), 'sha256' => hash_file('sha256', $args['verify'])], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
$operacao = isset($args['aplicar']) ? 'aplicar' : (isset($args['reverter']) ? 'reverter' : (isset($args['inspecionar']) ? 'inspecionar' : null));
if ($operacao === null || (isset($args['aplicar']) && isset($args['reverter'])) || !isset($args['host'], $args['database'])
    || ($operacao !== 'inspecionar' && !isset($args['backup-dir'], $args['confirmar']))) {
    fwrite(STDERR, "Uso: --inspecionar --host=... --database=...\n     --aplicar|--reverter --host=... --database=... --confirmar=NOME_DO_BANCO --backup-dir=DIRETORIO_PRIVADO\n     --verify=ARQUIVO\n");
    exit(2);
}

require_once __DIR__ . '/../config/env.php';
if ($args['host'] !== onefitEnv('DB_HOST') || $args['database'] !== onefitEnv('DB_NAME')) {
    fwrite(STDERR, "Destino informado difere do banco configurado no .env desta cópia. Nada foi feito.\n");
    exit(2);
}
if ($operacao !== 'inspecionar') {
    if ($args['confirmar'] !== $args['database']) {
        fwrite(STDERR, "--confirmar precisa repetir exatamente o nome do banco de destino. Nada foi feito.\n");
        exit(2);
    }
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
if ((string) $conn->query('SELECT DATABASE()')->fetch_row()[0] !== $args['database']) {
    fwrite(STDERR, "A conexão abriu outro banco que não o informado. Nada foi feito.\n");
    exit(2);
}

if ($operacao === 'inspecionar') {
    echo json_encode(professoresInspecionar($conn), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

$trava = 'onefit.' . PROFESSORES_VERSAO;
$stmt = $conn->prepare('SELECT GET_LOCK(?, 10)');
$stmt->bind_param('s', $trava);
$stmt->execute();
if ((int) $stmt->get_result()->fetch_row()[0] !== 1) {
    fwrite(STDERR, "Outra execução desta migração está em andamento.\n");
    exit(1);
}
$stmt->close();

try {
    $registrada = professoresVersaoRegistrada($conn, PROFESSORES_VERSAO);
    $colunasAntes = professoresColunas($conn);
    $temColuna = in_array(PROFESSORES_COLUNA, $colunasAntes, true);
    $temIndice = professoresTemIndice($conn);
    $conn->query("SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES')");
    $conn->query("SET SESSION time_zone = '+00:00'");

    if ($operacao === 'aplicar') {
        if ($registrada && $temColuna && $temIndice) {
            echo 'Migração ' . PROFESSORES_VERSAO . " já aplicada; nada foi alterado.\n";
            exit;
        }
        if ($registrada || $temColuna || $temIndice) {
            throw new RuntimeException('Estado parcial inesperado (versão, coluna ou índice já existe sem os demais). Nada foi feito; investigue antes de rodar de novo.');
        }
        if (!professoresVersaoRegistrada($conn, PROFESSORES_PRE_REQUISITO)) {
            throw new RuntimeException('Aplique antes a migração ' . PROFESSORES_PRE_REQUISITO . ' (database/migrate-funcionarios.php). Nada foi feito.');
        }
        $inspecao = professoresInspecionar($conn);
        if ($inspecao['cadastro_profissional_engine'] !== 'InnoDB' || !$inspecao['colunas_exigidas_presentes']) {
            throw new RuntimeException('cadastro_profissional não tem a estrutura esperada (InnoDB com id_profissional, id_usuario e status). Nada foi feito.');
        }
        if (!$inspecao['privilegios']['ALTER'] || !$inspecao['privilegios']['INDEX']) {
            throw new RuntimeException('O usuário do banco não tem ALTER e INDEX neste banco. Nada foi feito.');
        }
        $duplicados = $inspecao['cadastro_profissional']['contas_com_mais_de_um_cadastro'];
        if ($duplicados > 0) {
            throw new RuntimeException("Há $duplicados conta(s) com mais de um cadastro profissional. Resolva antes, explicitamente; nada foi feito.");
        }
        $backup = professoresBackup($conn, $backupDir, $args['database'], 'aplicar');
        $relacionadasAntes = professoresRetratoRelacionadas($conn);
        $agendaveisAntes = professoresAgendaveis($conn);
        $linhasAntes = professoresLinhas($conn, $colunasAntes);
        foreach (explode(';', preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/migrations/professores-20261021.sql'))) as $comando) {
            if (trim($comando) !== '') {
                $conn->query(trim($comando));
            }
        }
        // DDL faz commit implícito: em vez de prometer rollback, confere tudo.
        $posStatus = array_search('status', $colunasAntes, true) + 1;
        if (professoresColunas($conn) !== array_merge(array_slice($colunasAntes, 0, $posStatus), [PROFESSORES_COLUNA], array_slice($colunasAntes, $posStatus))
            || !professoresColunaCorreta($conn) || !professoresTemIndice($conn, true)) {
            throw new RuntimeException('Estrutura resultante difere do esperado. Backup intacto em: ' . $backup);
        }
        if (professoresLinhas($conn, $colunasAntes) !== $linhasAntes) {
            throw new RuntimeException('Linhas de cadastro_profissional mudaram durante a migração; verifique atividade concorrente. Backup intacto em: ' . $backup);
        }
        if ((int) $conn->query('SELECT COUNT(*) FROM cadastro_profissional WHERE ' . PROFESSORES_COLUNA . ' <> 1')->fetch_row()[0] !== 0) {
            throw new RuntimeException('Algum cadastro não ficou disponível. Backup intacto em: ' . $backup);
        }
        $agendaveisDepois = professoresAgendaveis($conn);
        $relacionadasIguais = professoresCompararRelacionadas($relacionadasAntes, professoresRetratoRelacionadas($conn));
        if ($agendaveisDepois !== $agendaveisAntes && $relacionadasIguais['usuarios']) {
            throw new RuntimeException('Os professores que recebem agendamento mudaram sem mudança de contas. Backup intacto em: ' . $backup);
        }
        $versao = PROFESSORES_VERSAO;
        $stmt = $conn->prepare('INSERT INTO onefit_migrations (versao) VALUES (?)');
        $stmt->bind_param('s', $versao);
        $stmt->execute();
        $stmt->close();
    } else {
        if (!$registrada && !$temColuna && !$temIndice) {
            echo 'Migração ' . PROFESSORES_VERSAO . " não está aplicada; nada foi alterado.\n";
            exit;
        }
        if (!$temColuna || !$temIndice) {
            throw new RuntimeException('Estado parcial inesperado; nada foi alterado.');
        }
        // Reverter tornaria disponíveis os professores marcados como indisponíveis.
        $indisponiveis = (int) $conn->query('SELECT COUNT(*) FROM cadastro_profissional WHERE ' . PROFESSORES_COLUNA . ' = 0')->fetch_row()[0];
        if ($indisponiveis > 0) {
            throw new RuntimeException("Há $indisponiveis professor(es) indisponível(is); a reversão os devolveria à agenda. Torne-os disponíveis antes, na tela Professores; nada foi alterado.");
        }
        $backup = professoresBackup($conn, $backupDir, $args['database'], 'reverter');
        $relacionadasAntes = professoresRetratoRelacionadas($conn);
        $agendaveisAntes = professoresAgendaveis($conn);
        $colunasDepois = array_values(array_diff($colunasAntes, [PROFESSORES_COLUNA]));
        $linhasAntes = professoresLinhas($conn, $colunasDepois);
        $conn->query('ALTER TABLE cadastro_profissional DROP INDEX ' . PROFESSORES_INDICE . ', DROP COLUMN ' . PROFESSORES_COLUNA);
        if (professoresColunas($conn) !== $colunasDepois || professoresTemIndice($conn)) {
            throw new RuntimeException('Estrutura resultante difere do esperado. Backup intacto em: ' . $backup);
        }
        if (professoresLinhas($conn, $colunasDepois) !== $linhasAntes) {
            throw new RuntimeException('Linhas de cadastro_profissional mudaram durante a reversão. Backup intacto em: ' . $backup);
        }
        $agendaveisDepois = professoresAgendaveis($conn);
        $relacionadasIguais = professoresCompararRelacionadas($relacionadasAntes, professoresRetratoRelacionadas($conn));
        $versao = PROFESSORES_VERSAO;
        $stmt = $conn->prepare('DELETE FROM onefit_migrations WHERE versao = ?');
        $stmt->bind_param('s', $versao);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'versao' => PROFESSORES_VERSAO,
        'operacao' => $operacao,
        'banco' => $args['database'],
        'backup' => $backup,
        'backup_sha256' => hash_file('sha256', $backup),
        'backup_linhas_cadastro_profissional' => count($linhasAntes),
        'recebem_agendamento_antes' => count($agendaveisAntes),
        'recebem_agendamento_depois' => count($agendaveisDepois),
        'mesmos_professores_na_agenda' => $agendaveisAntes === $agendaveisDepois,
        'tabelas_relacionadas_inalteradas' => $relacionadasIguais,
        'depois' => professoresInspecionar($conn),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if (in_array(false, $relacionadasIguais, true) || $agendaveisAntes !== $agendaveisDepois) {
        echo "ATENÇÃO: houve mudança em tabelas que a migração não escreve (atividade concorrente). Confira antes de seguir.\n";
    }
    echo $operacao === 'aplicar'
        ? "Migração verificada. Dados preservados; todos os cadastros continuam disponíveis.\n"
        : "Reversão verificada. Dados preservados.\n";
} catch (Throwable $erro) {
    fwrite(STDERR, 'Migração interrompida; código=' . $erro->getCode() . '; ' . $erro->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->bind_param('s', $trava);
    $stmt->execute();
    $stmt->close();
}
