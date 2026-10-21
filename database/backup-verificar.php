<?php
/**
 * database/backup-verificar.php — backup completo do banco, verificação e restauração isolada.
 *
 * Somente pela linha de comando. Nunca escreve no banco de origem: o dump usa
 * mysqldump --single-transaction (só leitura) e o retrato de conferência é
 * lido numa transação READ ONLY. Restauração e ensaio acontecem só num banco
 * local descartável (127.0.0.1, nome onefit_restauracao_teste_*), apagado no fim.
 *
 *   Backup (o destino tem de coincidir com o .env desta cópia):
 *     php database/backup-verificar.php --criar --host=H --database=D --backup-dir=DIRETORIO_PRIVADO
 *   Conferência do arquivo (hash, tamanho e estrutura, sem banco):
 *     php database/backup-verificar.php --verificar=ARQUIVO.sql
 *   Restauração isolada + comparação tabela a tabela (opcionalmente com ensaio da
 *   migração 20261021_professores_v1 sobre os dados restaurados, ida e volta):
 *     php database/backup-verificar.php --restaurar-isolado=ARQUIVO.sql [--ensaiar-migracao] [--manter]
 *
 * O manifesto (ARQUIVO.sql.manifest.json) guarda SHA-256 e tamanho do dump e,
 * por tabela, o número de linhas e um hash das linhas (TIMESTAMP em UTC, ordem
 * indiferente), lidos antes e depois do dump: se não mudaram, o dump tem de
 * reproduzir exatamente esse retrato. Nenhuma senha ou credencial é impressa.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const BV_FORMATO = 'onefit-backup-completo-v1';
const BV_PREFIXO_RESTAURACAO = 'onefit_restauracao_teste_';
const BV_TENTATIVAS = 3;

function bvSair(string $mensagem, int $codigo = 2): never
{
    fwrite(STDERR, $mensagem . PHP_EOL);
    exit($codigo);
}

/** Executável do cliente MySQL/MariaDB (variável de ambiente ou XAMPP). */
function bvBinario(string $nome): string
{
    $variavel = getenv('ONEFIT_' . strtoupper($nome));
    foreach (array_filter([$variavel ?: null, 'C:/xampp/mysql/bin/' . $nome . '.exe', '/usr/bin/' . $nome]) as $caminho) {
        if (is_file($caminho)) {
            return $caminho;
        }
    }
    bvSair("$nome não encontrado (defina ONEFIT_" . strtoupper($nome) . ').');
}

function bvRodar(array $comando, ?string $entrada = null, ?string &$saida = null): int
{
    $descritores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    if ($entrada !== null) {
        $descritores[0] = ['file', $entrada, 'r'];
    }
    $processo = proc_open($comando, $descritores, $tubos);
    if (!is_resource($processo)) {
        return 255;
    }
    $saida = stream_get_contents($tubos[1]) . stream_get_contents($tubos[2]);
    fclose($tubos[1]);
    fclose($tubos[2]);
    return proc_close($processo);
}

/** Pasta privada: existe, é gravável e fica fora do repositório e do htdocs. */
function bvPastaPrivada(string $pasta): string
{
    $real = realpath($pasta);
    if (!$real || !is_dir($real) || !is_writable($real)) {
        bvSair('Crie antes um diretório privado e gravável para o backup.');
    }
    $normalizado = strtolower(str_replace('\\', '/', $real)) . '/';
    foreach ([realpath(__DIR__ . '/..'), realpath(__DIR__ . '/../../..'), 'C:/xampp/htdocs'] as $proibido) {
        if ($proibido && str_starts_with($normalizado, strtolower(str_replace('\\', '/', (string) realpath($proibido) ?: $proibido)) . '/')) {
            bvSair('O diretório de backup está na raiz pública ou dentro do repositório.');
        }
    }
    return $real;
}

/**
 * Retrato de conferência: por tabela, linhas e hash das linhas (cada linha em
 * JSON, hashes ordenados) e hash da lista de colunas. $colunas limita as
 * colunas de uma tabela (ensaio da migração, que acrescenta uma coluna).
 */
function bvRetrato(mysqli $db, array $colunas = []): array
{
    $db->query("SET SESSION time_zone = '+00:00'");
    $tabelas = array_column($db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetch_all(), 0);
    $retrato = [];
    foreach ($tabelas as $tabela) {
        $todas = array_column($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $db->real_escape_string($tabela) . "' ORDER BY ORDINAL_POSITION")->fetch_all(), 0);
        $usar = $colunas[$tabela] ?? $todas;
        $lista = implode(', ', array_map(static fn(string $c): string => '`' . str_replace('`', '``', $c) . '`', $usar));
        $hashes = [];
        $resultado = $db->query("SELECT $lista FROM `" . str_replace('`', '``', $tabela) . '`', MYSQLI_USE_RESULT);
        while ($linha = $resultado->fetch_row()) {
            $hashes[] = hash('sha256', serialize($linha));
        }
        $resultado->free();
        sort($hashes);
        $retrato[$tabela] = ['linhas' => count($hashes), 'sha256' => hash('sha256', implode("\n", $hashes)), 'colunas' => $todas];
    }
    return $retrato;
}

function bvVisoes(mysqli $db): array
{
    return array_column($db->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetch_all(), 0);
}

/** Confere hash, tamanho e estrutura do arquivo contra o manifesto. Retorna o manifesto. */
function bvVerificarArquivo(string $arquivo): array
{
    $manifestoArquivo = $arquivo . '.manifest.json';
    if (!is_file($arquivo) || !is_file($manifestoArquivo)) {
        bvSair('Arquivo de backup ou manifesto ausente.');
    }
    $manifesto = json_decode(file_get_contents($manifestoArquivo), true, 512, JSON_THROW_ON_ERROR);
    if (($manifesto['format'] ?? '') !== BV_FORMATO) {
        bvSair('Manifesto em formato inesperado.');
    }
    $problemas = [];
    if (filesize($arquivo) !== $manifesto['size_bytes']) {
        $problemas[] = 'tamanho difere do manifesto';
    }
    if (!hash_equals($manifesto['sha256'], hash_file('sha256', $arquivo))) {
        $problemas[] = 'SHA-256 difere do manifesto';
    }
    $estrutura = bvEstruturaDump($arquivo);
    foreach (array_keys($manifesto['tables']) as $tabela) {
        if (!isset($estrutura['tabelas'][$tabela])) {
            $problemas[] = "sem CREATE TABLE de $tabela";
        } elseif ($manifesto['tables'][$tabela]['linhas'] > 0 && empty($estrutura['insercoes'][$tabela])) {
            $problemas[] = "sem dados de $tabela";
        }
    }
    foreach ($manifesto['views'] as $visao) {
        if (!isset($estrutura['visoes'][$visao])) {
            $problemas[] = "sem a visão $visao";
        }
    }
    if (!$estrutura['completo']) {
        $problemas[] = 'dump sem a linha final "Dump completed" (incompleto)';
    }
    $manifesto['problemas'] = $problemas;
    return $manifesto;
}

/** Varre o dump (linha a linha, sem carregar tudo): tabelas, visões, inserções, maior linha e fim. */
function bvEstruturaDump(string $arquivo): array
{
    $estrutura = ['tabelas' => [], 'visoes' => [], 'insercoes' => [], 'maior_linha' => 0, 'completo' => false, 'sandbox' => false];
    $f = fopen($arquivo, 'rb');
    $ultima = '';
    while (($linha = fgets($f)) !== false) {
        $estrutura['maior_linha'] = max($estrutura['maior_linha'], strlen($linha));
        if (str_starts_with($linha, 'CREATE TABLE `') && preg_match('/^CREATE TABLE `([^`]+)`/', $linha, $m)) {
            $estrutura['tabelas'][$m[1]] = true;
        } elseif (str_starts_with($linha, 'INSERT INTO `') && preg_match('/^INSERT INTO `([^`]+)`/', $linha, $m)) {
            $estrutura['insercoes'][$m[1]] = true;
        } elseif (preg_match('/VIEW `([^`]+)` AS/', $linha, $m)) {
            $estrutura['visoes'][$m[1]] = true;
        } elseif (str_starts_with($linha, '/*M!999999')) {
            $estrutura['sandbox'] = true;
        }
        if (trim($linha) !== '') {
            $ultima = $linha;
        }
    }
    fclose($f);
    $estrutura['completo'] = str_starts_with($ultima, '-- Dump completed');
    return $estrutura;
}

$args = getopt('', ['criar', 'host:', 'database:', 'backup-dir:', 'verificar:', 'restaurar-isolado:', 'ensaiar-migracao', 'manter']);

// ---------------------------------------------------------------------------------------------
if (isset($args['verificar'])) {
    $manifesto = bvVerificarArquivo($args['verificar']);
    echo json_encode(['arquivo' => $args['verificar'], 'sha256' => $manifesto['sha256'], 'tamanho' => $manifesto['size_bytes'],
        'tabelas' => count($manifesto['tables']), 'problemas' => $manifesto['problemas']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($manifesto['problemas'] ? 1 : 0);
}

// ---------------------------------------------------------------------------------------------
if (isset($args['criar'])) {
    if (!isset($args['host'], $args['database'], $args['backup-dir'])) {
        bvSair("Uso: --criar --host=... --database=... --backup-dir=DIRETORIO_PRIVADO");
    }
    require_once __DIR__ . '/../config/env.php';
    if ($args['host'] !== onefitEnv('DB_HOST') || $args['database'] !== onefitEnv('DB_NAME')) {
        bvSair('Destino informado difere do banco configurado no .env desta cópia. Nada foi feito.');
    }
    $pasta = bvPastaPrivada($args['backup-dir']);
    $porta = (int) (onefitEnv('DB_PORT') ?: 3306);
    // Uma conexão por retrato: o servidor remoto fecha conexões ociosas durante o dump.
    $conectar = static function () use ($porta, $args): mysqli {
        $conexao = new mysqli(onefitEnv('DB_HOST'), onefitEnv('DB_USER'), onefitEnv('DB_PASSWORD'), onefitEnv('DB_NAME'), $porta);
        $conexao->set_charset('utf8mb4');
        if ((string) $conexao->query('SELECT DATABASE()')->fetch_row()[0] !== $args['database']) {
            bvSair('A conexão abriu outro banco. Nada foi feito.');
        }
        return $conexao;
    };
    $origem = $conectar();
    $versaoServidor = $origem->server_info;
    $origem->close();
    $retratoLido = static function () use ($conectar): array {
        $origem = $conectar();
        $origem->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $origem->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        try {
            return ['tabelas' => bvRetrato($origem), 'visoes' => bvVisoes($origem),
                'gatilhos' => (int) $origem->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->fetch_row()[0],
                'rotinas' => (int) $origem->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')->fetch_row()[0],
                'eventos' => (int) $origem->query('SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE()')->fetch_row()[0]];
        } finally {
            $origem->rollback();
            $origem->close();
        }
    };

    // Com atividade no banco, um dump cujo retrato mudou não pode ser conferido:
    // é descartado e refeito (até BV_TENTATIVAS vezes).
    $dump = bvBinario('mysqldump');
    bvRodar([$dump, '--version'], null, $versaoCliente);
    $antes = $retratoLido();
    $tentativas = [];
    for ($tentativa = 1; ; $tentativa++) {
        $arquivo = $pasta . DIRECTORY_SEPARATOR . 'onefit-backup-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $args['database']) . '-' . gmdate('Ymd-His') . 'Z.sql';
        // Credenciais só num arquivo de opções temporário e privado, apagado logo depois.
        $temporaria = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'onefit-bv-' . bin2hex(random_bytes(8));
        mkdir($temporaria, 0700);
        $opcoes = $temporaria . DIRECTORY_SEPARATOR . 'cliente.cnf';
        $aspas = static fn(?string $v): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v) . '"';
        file_put_contents($opcoes, "[client]\nhost=" . $aspas(onefitEnv('DB_HOST')) . "\nport=$porta\nuser=" . $aspas(onefitEnv('DB_USER')) . "\npassword=" . $aspas(onefitEnv('DB_PASSWORD')) . "\n");
        chmod($opcoes, 0600);
        try {
            // --single-transaction: snapshot consistente sem LOCK TABLES nem FLUSH (só leitura).
            $codigo = bvRodar([$dump, '--defaults-extra-file=' . $opcoes, '--single-transaction', '--skip-lock-tables', '--no-tablespaces',
                '--triggers', '--hex-blob', '--default-character-set=utf8mb4', '--max-allowed-packet=64M',
                '--result-file=' . $arquivo, $args['database']], null, $saidaDump);
        } finally {
            unlink($opcoes);
            rmdir($temporaria);
        }
        if ($codigo !== 0 || !is_file($arquivo) || filesize($arquivo) === 0) {
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
            bvSair('mysqldump falhou (código ' . $codigo . '): ' . trim(preg_replace('/password\S*/i', '***', (string) $saidaDump)), 1);
        }
        $depois = $retratoLido();
        $mudaram = [];
        foreach ($antes['tabelas'] as $tabela => $r) {
            if (($depois['tabelas'][$tabela] ?? null) !== $r) {
                $mudaram[] = $tabela;
            }
        }
        $estavel = $mudaram === [] && $antes['visoes'] === $depois['visoes'] && array_keys($antes['tabelas']) === array_keys($depois['tabelas']);
        $tentativas[] = ['tentativa' => $tentativa, 'estavel' => $estavel, 'mudaram' => $mudaram];
        if ($estavel || $tentativa >= BV_TENTATIVAS) {
            break;
        }
        unlink($arquivo);
        $antes = $depois;
        sleep(5);
    }
    $manifesto = [
        'format' => BV_FORMATO,
        'database' => $args['database'],
        'server_version' => $versaoServidor,
        'client' => trim((string) $versaoCliente),
        'created_utc' => gmdate('c'),
        'file' => basename($arquivo),
        'size_bytes' => filesize($arquivo),
        'sha256' => hash_file('sha256', $arquivo),
        'hash_method' => 'por tabela: sha256 das linhas (serialize, TIMESTAMP em UTC), hashes ordenados',
        'stable_during_dump' => $estavel,
        'attempts' => $tentativas,
        'changed_during_dump' => $mudaram,
        'tables' => $depois['tabelas'],
        'views' => $depois['visoes'],
        'triggers' => $depois['gatilhos'],
        'routines' => $depois['rotinas'],
        'events' => $depois['eventos'],
    ];
    file_put_contents($arquivo . '.manifest.json', json_encode($manifesto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $conferencia = bvVerificarArquivo($arquivo);
    echo json_encode([
        'arquivo' => $arquivo,
        'manifesto' => $arquivo . '.manifest.json',
        'sha256' => $manifesto['sha256'],
        'tamanho_bytes' => $manifesto['size_bytes'],
        'tabelas' => count($manifesto['tables']),
        'linhas' => array_sum(array_column($manifesto['tables'], 'linhas')),
        'visoes' => count($manifesto['views']),
        'estavel_durante_o_dump' => $manifesto['stable_during_dump'],
        'mudaram_durante_o_dump' => $mudaram,
        'tentativas' => $tentativas,
        'gatilhos_rotinas_eventos' => [$manifesto['triggers'], $manifesto['routines'], $manifesto['events']],
        'problemas' => $conferencia['problemas'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if ($manifesto['routines'] > 0 || $manifesto['events'] > 0) {
        echo "ATENÇÃO: o banco tem rotinas ou eventos, que este dump não inclui.\n";
    }
    exit($conferencia['problemas'] || !$manifesto['stable_during_dump'] ? 1 : 0);
}

// ---------------------------------------------------------------------------------------------
if (isset($args['restaurar-isolado'])) {
    $arquivo = $args['restaurar-isolado'];
    $manifesto = bvVerificarArquivo($arquivo);
    if ($manifesto['problemas']) {
        bvSair('Backup não confere: ' . implode('; ', $manifesto['problemas']), 1);
    }
    $estrutura = bvEstruturaDump($arquivo);
    $local = new mysqli('127.0.0.1', 'root', '');
    $local->set_charset('utf8mb4');
    $banco = BV_PREFIXO_RESTAURACAO . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
    if (!preg_match('/^' . BV_PREFIXO_RESTAURACAO . '\d{8}_\d{6}_[a-f0-9]{4}$/D', $banco)) {
        bvSair('Nome do banco de restauração inesperado.');
    }
    $temporaria = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'onefit-bv-' . bin2hex(random_bytes(8));
    mkdir($temporaria, 0700);
    $pacoteOriginal = (int) $local->query('SELECT @@GLOBAL.max_allowed_packet')->fetch_row()[0];
    $relatorio = ['arquivo' => $arquivo, 'sha256' => $manifesto['sha256'], 'banco_isolado' => $banco, 'servidor_isolado' => $local->server_info];
    $validado = false;
    try {
        // Restaurar com outro nome: tira o prefixo do banco de origem e o DEFINER das visões
        // (o usuário de origem não existe aqui). Só nas linhas de estrutura, nunca nos dados.
        $transformado = $temporaria . DIRECTORY_SEPARATOR . 'restaurar.sql';
        $entrada = fopen($arquivo, 'rb');
        $saida = fopen($transformado, 'xb');
        $prefixo = '`' . str_replace('`', '``', $manifesto['database']) . '`.';
        while (($linha = fgets($entrada)) !== false) {
            if (!str_starts_with($linha, 'INSERT INTO `')) {
                $linha = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', str_replace($prefixo, '', $linha));
            }
            fwrite($saida, $linha);
        }
        fclose($entrada);
        fclose($saida);
        // Pacote do servidor local à altura da maior linha do dump (só em tempo de execução).
        $necessario = max(64 * 1024 * 1024, (int) ceil($estrutura['maior_linha'] * 1.1));
        if ($pacoteOriginal < $necessario) {
            $local->query('SET GLOBAL max_allowed_packet = ' . $necessario);
        }
        $local->query("CREATE DATABASE `$banco` CHARACTER SET utf8mb4");
        $codigo = bvRodar([bvBinario('mysql'), '--host=127.0.0.1', '--user=root', '--default-character-set=utf8mb4', '--max-allowed-packet=' . $necessario, $banco], $transformado, $saidaImportacao);
        $relatorio['importacao'] = $codigo === 0 ? 'ok' : 'falhou: ' . trim((string) $saidaImportacao);
        if ($codigo === 0) {
            $restaurado = new mysqli('127.0.0.1', 'root', '', $banco);
            $restaurado->set_charset('utf8mb4');
            $retrato = bvRetrato($restaurado);
            $diferencas = [];
            foreach ($manifesto['tables'] as $tabela => $esperado) {
                if (!isset($retrato[$tabela])) {
                    $diferencas[$tabela] = 'ausente';
                } elseif ($retrato[$tabela]['linhas'] !== $esperado['linhas']) {
                    $diferencas[$tabela] = "linhas {$retrato[$tabela]['linhas']} != {$esperado['linhas']}";
                } elseif ($retrato[$tabela]['sha256'] !== $esperado['sha256']) {
                    $diferencas[$tabela] = 'conteúdo difere';
                } elseif ($retrato[$tabela]['colunas'] !== $esperado['colunas']) {
                    $diferencas[$tabela] = 'colunas diferem';
                }
            }
            foreach (array_diff(array_keys($retrato), array_keys($manifesto['tables'])) as $sobra) {
                $diferencas[$sobra] = 'tabela a mais';
            }
            $visoesOk = [];
            foreach ($manifesto['views'] as $visao) {
                try {
                    $restaurado->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $visao) . '`');
                    $visoesOk[$visao] = true;
                } catch (mysqli_sql_exception $erro) {
                    $visoesOk[$visao] = false;
                }
            }
            $relatorio['tabelas_conferidas'] = count($manifesto['tables']);
            $relatorio['linhas_conferidas'] = array_sum(array_column($retrato, 'linhas'));
            $relatorio['diferencas'] = $diferencas;
            $relatorio['visoes_consultaveis'] = $visoesOk;
            $validado = $diferencas === [] && !in_array(false, $visoesOk, true);
            $relatorio['restauracao_validada'] = $validado;

            if ($validado && isset($args['ensaiar-migracao'])) {
                $relatorio['ensaio_migracao'] = bvEnsaiarMigracao($restaurado, $banco, $manifesto, $temporaria);
            }
            $restaurado->close();
        }
    } finally {
        if (!isset($args['manter'])) {
            $local->query("DROP DATABASE IF EXISTS `$banco`");
            $relatorio['banco_isolado_apagado'] = true;
        }
        if ($pacoteOriginal < (int) $local->query('SELECT @@GLOBAL.max_allowed_packet')->fetch_row()[0]) {
            $local->query('SET GLOBAL max_allowed_packet = ' . $pacoteOriginal);
        }
        bvApagar($temporaria);
    }
    echo json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $ensaioOk = !isset($relatorio['ensaio_migracao']) || $relatorio['ensaio_migracao']['ok'];
    exit($validado && $ensaioOk ? 0 : 1);
}

bvSair("Uso: --criar --host=... --database=... --backup-dir=DIR | --verificar=ARQUIVO.sql | --restaurar-isolado=ARQUIVO.sql [--ensaiar-migracao] [--manter]");

function bvApagar(string $caminho): void
{
    if (!is_dir($caminho)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($caminho);
}

/**
 * Ensaio da migração dos professores sobre a cópia restaurada (dados reais, banco
 * local): inspeciona, aplica, reaplica (idempotente), confere que só a coluna nova
 * mudou e que os mesmos professores recebem agendamento, reverte e confere que o
 * banco voltou exatamente ao retrato do backup.
 */
function bvEnsaiarMigracao(mysqli $db, string $banco, array $manifesto, string $temporaria): array
{
    // Mesmo layout do htdocs (www/AN25/OneFit): o executor exige o backup fora da raiz pública.
    $copia = $temporaria . '/www/AN25/OneFit';
    foreach (['config', 'database'] as $pasta) {
        $origem = realpath(__DIR__ . '/../' . $pasta);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $alvo = $copia . '/' . $pasta . '/' . substr($item->getPathname(), strlen($origem) + 1);
            $item->isDir() ? @mkdir($alvo, 0700, true) : (@mkdir(dirname($alvo), 0700, true) && false ?: copy($item->getPathname(), $alvo));
        }
    }
    file_put_contents($copia . '/.env', "APP_URL=http://localhost/AN25/OneFit/\nDB_HOST=127.0.0.1\nDB_NAME=$banco\nDB_USER=root\nDB_PASSWORD=\n");
    mkdir($temporaria . '/backups-ensaio', 0700);
    $runner = $copia . '/database/migrate-professores.php';
    $alvo = ['--host=127.0.0.1', "--database=$banco"];
    $gravar = array_merge($alvo, ["--confirmar=$banco", '--backup-dir=' . $temporaria . '/backups-ensaio']);
    $agendaveis = static fn(): array => array_map('intval', array_column($db->query(
        "SELECT cp.id_profissional FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario
         WHERE u.tipo_usuario IN ('professor', 'profissional') AND u.status = 'ativo' AND cp.status = 'ativo'"
        . (in_array('disponivel_agendamento', array_column($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional'")->fetch_all(), 0), true) ? ' AND cp.disponivel_agendamento = 1' : '')
        . ' ORDER BY cp.id_profissional')->fetch_all(), 0));
    $r = ['ok' => false];
    $antesAgenda = $agendaveis();
    $inspecao = json_decode((string) bvRodarPhp([$runner, '--inspecionar', ...$alvo], $codigo), true);
    $r['inspecao'] = $codigo === 0 ? ['coluna' => $inspecao['coluna_disponivel_agendamento'], 'indice' => $inspecao['indice_uk_cadastro_profissional_usuario'],
        'duplicados' => $inspecao['cadastro_profissional']['contas_com_mais_de_um_cadastro'], 'sem_conta' => $inspecao['cadastro_profissional']['sem_conta']] : 'falhou';
    $saida = bvRodarPhp([$runner, '--aplicar', ...$gravar], $codigo);
    $r['aplicar'] = $codigo === 0 && str_contains($saida, 'Migração verificada') ? 'ok' : 'falhou: ' . trim($saida);
    if ($r['aplicar'] !== 'ok') {
        return $r;
    }
    $saida = bvRodarPhp([$runner, '--aplicar', ...$gravar], $codigo);
    $r['reaplicar'] = $codigo === 0 && str_contains($saida, 'já aplicada; nada foi alterado') ? 'ok (nada alterado)' : 'falhou: ' . trim($saida);
    $colunasOriginais = ['cadastro_profissional' => $manifesto['tables']['cadastro_profissional']['colunas']];
    $depois = bvRetrato($db, $colunasOriginais);
    $diferencas = [];
    foreach ($manifesto['tables'] as $tabela => $esperado) {
        if ($tabela === 'onefit_migrations') {
            continue; // ganha a linha da versão
        }
        if (($depois[$tabela]['sha256'] ?? null) !== $esperado['sha256']) {
            $diferencas[] = $tabela;
        }
    }
    $r['dados_preservados_apos_aplicar'] = $diferencas === [];
    $r['tabelas_diferentes_apos_aplicar'] = $diferencas;
    $r['todos_disponiveis'] = (int) $db->query('SELECT COUNT(*) FROM cadastro_profissional WHERE disponivel_agendamento <> 1')->fetch_row()[0] === 0;
    $depoisAgenda = $agendaveis();
    $r['recebem_agendamento'] = ['antes' => $antesAgenda, 'depois' => $depoisAgenda];
    $r['mesmos_professores_na_agenda'] = $antesAgenda === $depoisAgenda;
    $saida = bvRodarPhp([$runner, '--reverter', ...$gravar], $codigo);
    $r['reverter'] = $codigo === 0 && str_contains($saida, 'Reversão verificada') ? 'ok' : 'falhou: ' . trim($saida);
    $final = bvRetrato($db);
    $r['identico_ao_backup_apos_reverter'] = array_map(static fn(array $t): string => $t['sha256'], $final)
        === array_map(static fn(array $t): string => $t['sha256'], $manifesto['tables']);
    $r['ok'] = $r['aplicar'] === 'ok' && str_starts_with($r['reaplicar'], 'ok') && $r['dados_preservados_apos_aplicar'] && $r['todos_disponiveis']
        && $r['mesmos_professores_na_agenda'] && $r['reverter'] === 'ok' && $r['identico_ao_backup_apos_reverter'];
    return $r;
}

function bvRodarPhp(array $argumentos, ?int &$codigo): string
{
    $codigo = bvRodar(array_merge([PHP_BINARY], $argumentos), null, $saida);
    return (string) $saida;
}
