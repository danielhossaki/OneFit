<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Migração de funcionários num banco descartável com a MESMA estrutura de usuarios e
// cadastro_profissional do banco remoto (SHOW CREATE TABLE de 20/10/2026): ENUM só com
// aluno/profissional/admin, sem vendedor. Roda o script real numa cópia mínima do app
// (config/, database/ e os includes do painel) cujo .env aponta para esse banco local.
// Dados sintéticos; banco e cópia são apagados no final. Orquestrado por
// tests/funcionarios-preparar.ps1.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$raiz = dirname(__DIR__);
$banco = 'onefit_interface_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$pasta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'onefit-migracao-remota-' . bin2hex(random_bytes(4));

$checks = 0;
function remCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function remCopiar(string $origem, string $destino): void
{
    mkdir($destino, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $alvo = $destino . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($origem) + 1);
        $item->isDir() ? mkdir($alvo, 0700, true) : copy($item->getPathname(), $alvo);
    }
}
function remApagar(string $caminho): void
{
    if (!is_dir($caminho)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($caminho, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($caminho);
}
function remRodar(array $argumentos, ?int &$codigo): string
{
    $processo = proc_open(array_merge([PHP_BINARY], $argumentos), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
    $saida = stream_get_contents($tubos[1]) . stream_get_contents($tubos[2]);
    fclose($tubos[1]);
    fclose($tubos[2]);
    $codigo = proc_close($processo);
    return $saida;
}
function remColuna(mysqli $db): array
{
    return $db->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_assoc();
}
function remLinhas(mysqli $db): array
{
    return array_map(static fn(array $l): string => hash('sha256', json_encode($l, JSON_THROW_ON_ERROR)),
        $db->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC));
}

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
      `tipo_usuario` enum('aluno','profissional','admin') DEFAULT 'aluno', `status` enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
      `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, `remember_token` varchar(150) DEFAULT NULL,
      PRIMARY KEY (`id_usuario`), UNIQUE KEY `cpf` (`cpf`), UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE `cadastro_profissional` (
      `id_profissional` int(11) NOT NULL AUTO_INCREMENT, `id_usuario` int(11) DEFAULT NULL, `nome` varchar(150) NOT NULL,
      `email` varchar(150) NOT NULL, `celular` varchar(20) DEFAULT NULL, `especialidade` varchar(100) DEFAULT NULL,
      `registro_profissional` varchar(100) DEFAULT NULL, `descricao` text, `foto` varchar(255) DEFAULT NULL,
      `status` enum('ativo','inativo') DEFAULT 'ativo', `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `modalidades` varchar(255) DEFAULT NULL, PRIMARY KEY (`id_profissional`), UNIQUE KEY `email` (`email`), KEY `id_usuario` (`id_usuario`),
      CONSTRAINT `cadastro_profissional_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query('CREATE TABLE onefit_migrations (versao VARCHAR(96) PRIMARY KEY, aplicada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->query("INSERT INTO onefit_migrations (versao) VALUES ('20260910_interface_v1')");
    // Mesma distribuição do remoto: 2 alunos, 2 profissionais (com cadastro vinculado) e 1 admin.
    foreach ([['aluno', 1], ['aluno', 2], ['profissional', 3], ['profissional', 4], ['admin', 5]] as [$cargo, $n]) {
        $db->query("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status, data_cadastro, data_atualizacao)
            VALUES ('Pessoa Sintética $n', '" . password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT) . "', 'BR', '1990-01-0$n', 'outro', '000.000.000-0$n',
                    'Rua Teste, $n', 'Cidade/SP', 'pessoa$n@example.test', 1, '(11) 90000-000$n', '$cargo', 'ativo', '2026-01-0$n 10:00:00', '2026-01-0$n 10:00:00')");
        if ($cargo === 'profissional') {
            $db->query("INSERT INTO cadastro_profissional (id_usuario, nome, email, status) VALUES ($db->insert_id, 'Pessoa Sintética $n', 'pessoa$n@example.test', 'ativo')");
        }
    }
    $colunaAntes = remColuna($db);
    $linhasAntes = remLinhas($db);

    // Mesmo formato da fixture: o backup fica fora da cópia e da raiz pública (www/).
    $copia = $pasta . '/www/AN25/OneFit';
    remCopiar($raiz . DIRECTORY_SEPARATOR . 'config', $copia . DIRECTORY_SEPARATOR . 'config');
    remCopiar($raiz . DIRECTORY_SEPARATOR . 'database', $copia . DIRECTORY_SEPARATOR . 'database');
    remCopiar($raiz . '/pages/dashboard/includes', $copia . '/pages/dashboard/includes');
    file_put_contents($copia . '/.env', "APP_URL=http://localhost/AN25/OneFit/\nDB_HOST=127.0.0.1\nDB_NAME=$banco\nDB_USER=root\nDB_PASSWORD=\n");
    mkdir($pasta . '/backups', 0700);
    $runner = $copia . '/database/migrate-funcionarios.php';
    $alvo = ['--host=127.0.0.1', "--database=$banco"];

    $inspecao = json_decode(remRodar([$runner, '--inspecionar', ...$alvo], $codigo), true);
    remCheck($codigo === 0 && is_array($inspecao), 'Inspeção roda no esquema remoto');
    remCheck($inspecao['tipo_usuario']['cargos_novos_faltando'] === ['vendedor', 'professor', 'recepcionista', 'faxineira'], 'Inspeção aponta vendedor e os 3 cargos novos');
    remCheck($inspecao['cadastro_profissional']['vinculados'] === 2 && $inspecao['cadastro_profissional']['vinculados_a_conta_que_nao_e_professor'] === 0, 'Inspeção dos cadastros profissionais');
    remCheck(remLinhas($db) === $linhasAntes && remColuna($db) === $colunaAntes, 'Inspeção não altera nada');

    $saida = remRodar([$runner, '--aplicar', ...$alvo, '--backup-dir=' . $pasta . '/backups'], $codigo);
    remCheck($codigo === 0 && str_contains($saida, 'Migração verificada'), 'Migração aplicada no esquema remoto: ' . trim($saida));
    $colunaDepois = remColuna($db);
    preg_match_all("/'([^']*)'/", $colunaDepois['COLUMN_TYPE'], $valores);
    remCheck($valores[1] === ['aluno', 'profissional', 'admin', 'vendedor', 'professor', 'recepcionista', 'faxineira'], 'ENUM ganhou vendedor e os 3 cargos, no final e na ordem');
    foreach (['IS_NULLABLE', 'COLUMN_DEFAULT', 'CHARACTER_SET_NAME', 'COLLATION_NAME'] as $atributo) {
        remCheck($colunaDepois[$atributo] === $colunaAntes[$atributo], "Atributo preservado: $atributo");
    }
    remCheck(remLinhas($db) === $linhasAntes, 'Nenhuma linha de usuarios mudou (nem data_atualizacao)');
    remCheck((int) $db->query('SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IS NOT NULL')->fetch_row()[0] === 2, 'Vínculos profissionais preservados');
    $versoes = array_column($db->query('SELECT versao FROM onefit_migrations ORDER BY versao')->fetch_all(), 0);
    remCheck($versoes === ['20260910_interface_v1', '20261020_funcionarios_v1'], 'Versão registrada sem apagar a anterior');

    $backups = glob($pasta . '/backups/onefit-before-funcionarios-*.json');
    remCheck(count($backups) === 1, 'Um backup gravado');
    $verificacao = json_decode(remRodar([$runner, '--verify=' . $backups[0]], $codigo), true);
    remCheck($codigo === 0 && $verificacao['tabelas'] === ['usuarios' => 5], 'Backup verificável com as 5 linhas');

    $segunda = remRodar([$runner, '--aplicar', ...$alvo, '--backup-dir=' . $pasta . '/backups'], $codigo);
    remCheck($codigo === 0 && str_contains($segunda, 'aplicada; nada foi alterado') && count(glob($pasta . '/backups/*.json')) === 1, 'Segunda execução não altera nada');

    // A trava da tela (mesma função do painel, lendo o .env da cópia) libera só agora.
    require $copia . '/pages/dashboard/includes/funcionarios.php';
    remCheck(bo_funcionarios_migracao($db) === ['ok' => true, 'faltando' => []], 'Tela de funcionários liberada após a migração');
    foreach (onefitCargosAtribuiveisPorAdmin() as $cargo) {
        remCheck(in_array($cargo, $valores[1], true), "ENUM aceita o cargo atribuível $cargo");
    }
} finally {
    $servidor->query("DROP DATABASE IF EXISTS `$banco`");
    remApagar($pasta);
}

echo "Migração (esquema remoto): $checks verificações passaram.\n";
