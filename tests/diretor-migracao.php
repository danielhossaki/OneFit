<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Migração do cargo diretor (database/migrate-diretor.php) e designação/recuperação do
// primeiro diretor (database/diretor-recuperar.php) num banco descartável com a MESMA
// estrutura de usuarios do banco remoto depois de 20261020_funcionarios_v1 (7 cargos no ENUM,
// 2 alunos, 2 profissionais e 1 admin). Roda os scripts reais numa cópia mínima do app cujo
// .env aponta para esse banco local. Banco e cópia são apagados no final.
// Orquestrado por tests/funcionarios-preparar.ps1.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$banco = 'onefit_interface_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$pasta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'onefit-migracao-diretor-' . bin2hex(random_bytes(4));
$checks = 0;
function dmCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function dmCopiar(string $origem, string $destino): void
{
    mkdir($destino, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $alvo = $destino . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($origem) + 1);
        $item->isDir() ? mkdir($alvo, 0700, true) : copy($item->getPathname(), $alvo);
    }
}
function dmApagar(string $caminho): void
{
    if (!is_dir($caminho)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($caminho);
}
function dmRodar(array $argumentos, ?int &$codigo): string
{
    $processo = proc_open(array_merge([PHP_BINARY], $argumentos), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
    $saida = stream_get_contents($tubos[1]) . stream_get_contents($tubos[2]);
    fclose($tubos[1]);
    fclose($tubos[2]);
    $codigo = proc_close($processo);
    return $saida;
}
function dmColuna(mysqli $db): array
{
    return $db->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_assoc();
}
function dmValores(mysqli $db): array
{
    preg_match_all("/'([^']*)'/", dmColuna($db)['COLUMN_TYPE'], $valores);
    return $valores[1];
}
function dmLinhas(mysqli $db): array
{
    return array_map(static fn(array $l): string => hash('sha256', json_encode($l, JSON_THROW_ON_ERROR)),
        $db->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC));
}
function dmVersoes(mysqli $db): array
{
    return array_column($db->query('SELECT versao FROM onefit_migrations ORDER BY versao')->fetch_all(), 0);
}

$cargosAntes = ['aluno', 'profissional', 'admin', 'vendedor', 'professor', 'recepcionista', 'faxineira'];
$servidor = new mysqli('127.0.0.1', 'root', '');
$servidor->query("CREATE DATABASE `$banco` CHARACTER SET utf8mb4");
try {
    $db = new mysqli('127.0.0.1', 'root', '', $banco);
    $db->set_charset('utf8mb4');
    $db->query("CREATE TABLE `usuarios` (
      `id_usuario` int(11) NOT NULL AUTO_INCREMENT, `nome` varchar(150) NOT NULL, `senha` varchar(255) NOT NULL,
      `nacionalidade` varchar(60) NOT NULL, `data_nascimento` date NOT NULL, `genero` enum('masculino','feminino','outro') NOT NULL,
      `cpf` char(14) NOT NULL, `endereco` varchar(255) NOT NULL, `cidade_estado` varchar(120) NOT NULL, `email` varchar(150) NOT NULL,
      `email_verificado` tinyint(1) NOT NULL DEFAULT '0', `celular` varchar(20) NOT NULL, `objetivo` varchar(100) DEFAULT NULL,
      `altura` decimal(5,2) DEFAULT NULL, `peso` decimal(5,2) DEFAULT NULL, `foto` varchar(255) DEFAULT NULL,
      `tipo_usuario` enum('" . implode("','", $cargosAntes) . "') DEFAULT 'aluno', `status` enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
      `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, `remember_token` varchar(150) DEFAULT NULL,
      PRIMARY KEY (`id_usuario`), UNIQUE KEY `cpf` (`cpf`), UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query('CREATE TABLE onefit_migrations (versao VARCHAR(96) PRIMARY KEY, aplicada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->query("INSERT INTO onefit_migrations (versao) VALUES ('20260910_interface_v1'), ('20261020_funcionarios_v1')");
    // auditoria_eventos, como criada pela migração de funcionários.
    $db->query(rtrim(trim(preg_replace('/^--.*$/m', '', file_get_contents($raiz . '/database/migrations/funcionarios-20261020.sql'))), ';'));
    foreach ([['aluno', 1], ['aluno', 2], ['profissional', 3], ['profissional', 4], ['admin', 5]] as [$cargo, $n]) {
        $db->query("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status, data_cadastro, data_atualizacao)
            VALUES ('Pessoa Sintética $n', '" . password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT) . "', 'BR', '1990-01-0$n', 'outro', '0000000000$n',
                    'Rua Teste, $n', 'Cidade/SP', 'pessoa$n@example.test', 1, '1190000000$n', '$cargo', 'ativo', '2026-01-0$n 10:00:00', '2026-01-0$n 10:00:00')");
    }
    $colunaAntes = dmColuna($db);
    $linhasAntes = dmLinhas($db);

    $copia = $pasta . '/www/AN25/OneFit';
    dmCopiar($raiz . DIRECTORY_SEPARATOR . 'config', $copia . DIRECTORY_SEPARATOR . 'config');
    dmCopiar($raiz . DIRECTORY_SEPARATOR . 'database', $copia . DIRECTORY_SEPARATOR . 'database');
    dmCopiar($raiz . '/pages/dashboard/includes', $copia . '/pages/dashboard/includes');
    file_put_contents($copia . '/.env', "APP_URL=http://localhost/AN25/OneFit/\nDB_HOST=127.0.0.1\nDB_NAME=$banco\nDB_USER=root\nDB_PASSWORD=\n");
    mkdir($pasta . '/backups', 0700);
    $runner = $copia . '/database/migrate-diretor.php';
    $recuperar = $copia . '/database/diretor-recuperar.php';
    $alvo = ['--host=127.0.0.1', "--database=$banco"];
    $backup = '--backup-dir=' . $pasta . '/backups';

    // --- Inspeção e recusas de destino ------------------------------------------------------
    $inspecao = json_decode(dmRodar([$runner, '--inspecionar', ...$alvo], $codigo), true);
    dmCheck($codigo === 0 && $inspecao['diretor_no_enum'] === false && $inspecao['diretores_ativos'] === 0 && $inspecao['pre_requisito_20261020_funcionarios_v1'] === true, 'Inspeção: sem diretor no ENUM, pré-requisito aplicado');
    dmRodar([$runner, '--aplicar', ...$alvo], $codigo);
    dmCheck($codigo === 2, 'Sem diretório de backup: recusado');
    dmRodar([$runner, '--aplicar', '--host=127.0.0.1', '--database=outro_banco', $backup], $codigo);
    dmCheck($codigo === 2, 'Destino diferente do .env: recusado');
    $db->query("DELETE FROM onefit_migrations WHERE versao = '20261020_funcionarios_v1'");
    $saida = dmRodar([$runner, '--aplicar', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 1 && str_contains($saida, 'Aplique antes'), 'Sem a migração de funcionários: recusado');
    $db->query("INSERT INTO onefit_migrations (versao) VALUES ('20261020_funcionarios_v1')");
    dmCheck(dmValores($db) === $cargosAntes && dmLinhas($db) === $linhasAntes, 'Recusas não alteram nada');

    // --- Aplicação ---------------------------------------------------------------------------
    $saida = dmRodar([$runner, '--aplicar', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 0 && str_contains($saida, 'Nenhuma conta mudou de cargo'), 'Migração aplicada: ' . trim($saida));
    dmCheck(dmValores($db) === array_merge($cargosAntes, ['diretor']), 'ENUM ganhou diretor no final, sem reordenar');
    $colunaDepois = dmColuna($db);
    foreach (['IS_NULLABLE', 'COLUMN_DEFAULT', 'CHARACTER_SET_NAME', 'COLLATION_NAME'] as $atributo) {
        dmCheck($colunaDepois[$atributo] === $colunaAntes[$atributo], "Atributo preservado: $atributo");
    }
    dmCheck(dmLinhas($db) === $linhasAntes, 'Nenhuma conta mudou (nem o admin virou diretor)');
    dmCheck(dmVersoes($db) === ['20260910_interface_v1', '20261020_diretor_v1', '20261020_funcionarios_v1'], 'Versão registrada');
    $arquivos = glob($pasta . '/backups/onefit-before-diretor-aplicar-*.json');
    $verificacao = json_decode(dmRodar([$runner, '--verify=' . ($arquivos[0] ?? 'x')], $codigo), true);
    dmCheck(count($arquivos) === 1 && $codigo === 0 && $verificacao['usuarios'] === 5, 'Backup verificável com as 5 contas');
    $segunda = dmRodar([$runner, '--aplicar', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 0 && str_contains($segunda, 'já aplicada; nada foi alterado') && count(glob($pasta . '/backups/*.json')) === 1, 'Segunda execução não altera nada');
    require $copia . '/pages/dashboard/includes/funcionarios.php';
    dmCheck(bo_funcionarios_enum_aceita($db, 'diretor') && bo_funcionarios_migracao($db)['ok'], 'Tela de funcionários aceita o cargo diretor');

    // --- Designação do primeiro diretor (procedimento de recuperação) ----------------------
    dmRodar([$recuperar, ...$alvo, '--id=5', '--confirmar=PROMOVER-4'], $codigo);
    dmCheck($codigo === 2, 'Confirmação de outra conta: recusada');
    $saida = dmRodar([$recuperar, ...$alvo, '--id=1', '--confirmar=PROMOVER-1'], $codigo);
    dmCheck($codigo === 1 && str_contains($saida, 'ser de administrador') && dmLinhas($db) === $linhasAntes, 'Aluno nunca é promovido');
    $saida = dmRodar([$recuperar, '--host=127.0.0.1', '--database=outro_banco', '--id=5', '--confirmar=PROMOVER-5'], $codigo);
    dmCheck($codigo === 2, 'Recuperação em destino diferente do .env: recusada');
    $saida = dmRodar([$recuperar, ...$alvo, '--id=5', '--confirmar=PROMOVER-5'], $codigo);
    dmCheck($codigo === 0 && $db->query('SELECT tipo_usuario FROM usuarios WHERE id_usuario = 5')->fetch_row()[0] === 'diretor', 'Admin ativo promovido a diretor: ' . trim($saida));
    dmCheck((int) $db->query("SELECT COUNT(*) FROM auditoria_eventos WHERE acao = 'recuperacao_diretor' AND id_alvo = 5 AND id_operador IS NULL")->fetch_row()[0] === 1, 'Promoção registrada na auditoria');
    dmCheck((int) $db->query("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario <> 'diretor' AND id_usuario <> 5")->fetch_row()[0] === 4, 'Nenhuma outra conta mudou');
    $db->query("UPDATE usuarios SET tipo_usuario = 'admin' WHERE id_usuario = 4");
    $saida = dmRodar([$recuperar, ...$alvo, '--id=4', '--confirmar=PROMOVER-4'], $codigo);
    dmCheck($codigo === 1 && str_contains($saida, 'Já existe Diretor ativo') && $db->query('SELECT tipo_usuario FROM usuarios WHERE id_usuario = 4')->fetch_row()[0] === 'admin', 'Com diretor ativo, a recuperação não promove mais ninguém');
    $db->query("UPDATE usuarios SET tipo_usuario = 'profissional' WHERE id_usuario = 4");

    // --- Reversão -------------------------------------------------------------------------
    $saida = dmRodar([$runner, '--reverter', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 1 && str_contains($saida, 'conta(s) com o cargo diretor') && in_array('diretor', dmValores($db), true), 'Reversão recusada enquanto houver diretor');
    $db->query("UPDATE usuarios SET tipo_usuario = 'admin' WHERE id_usuario = 5");
    $linhasSemDiretor = dmLinhas($db);
    $saida = dmRodar([$runner, '--reverter', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 0 && str_contains($saida, 'Reversão verificada'), 'Reversão aplicada: ' . trim($saida));
    dmCheck(dmValores($db) === $cargosAntes, 'ENUM volta exatamente ao anterior');
    dmCheck(dmColuna($db) === $colunaAntes, 'Coluna idêntica à original');
    dmCheck(dmLinhas($db) === $linhasSemDiretor, 'Reversão não altera contas');
    dmCheck(dmVersoes($db) === ['20260910_interface_v1', '20261020_funcionarios_v1'], 'Versão removida');
    $saida = dmRodar([$runner, '--reverter', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 0 && str_contains($saida, 'não está aplicada'), 'Reverter de novo não altera nada');
    $saida = dmRodar([$runner, '--aplicar', ...$alvo, $backup], $codigo);
    dmCheck($codigo === 0 && dmValores($db) === array_merge($cargosAntes, ['diretor']), 'Reaplicar depois da reversão funciona');
} finally {
    $servidor->query("DROP DATABASE IF EXISTS `$banco`");
    dmApagar($pasta);
}

echo "Migração do cargo diretor (esquema remoto): $checks verificações passaram.\n";
