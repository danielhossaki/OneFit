<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Hierarquia diretor > admin via HTTP real, na cópia sintética ligada ao banco descartável
// (tests/papeis-http.ps1 -Script diretor-http.php, depois de tests/funcionarios-preparar.ps1):
// tela Funcionários de cada nível, requisições forjadas do admin (com CSRF válido) contra contas
// de diretor e promoções, operações do diretor e o efeito imediato na sessão de quem muda de cargo.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/http-navegador.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
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
function dhCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function dhCpf(int $n): string
{
    $base = sprintf('%09d', $n);
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $base[$i] * (($posicao + 1) - $i);
        }
        $base .= ((10 * $soma) % 11) % 10;
    }
    return $base;
}
$cargoDe = static fn(int $id): ?string => $db->query("SELECT CONCAT(tipo_usuario, '/', status) FROM usuarios WHERE id_usuario = $id")->fetch_row()[0] ?? null;
$retrato = static fn(): array => $db->query('CHECKSUM TABLE usuarios, recuperacao_senha_tokens, verificacao_email_tokens, cadastro_profissional')->fetch_all();

$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$criar = static function (string $cargo, string $status = 'ativo') use ($db, $run, &$seq, $senha): array {
    $seq++;
    $email = "dh{$run}x{$seq}@example.test";
    $nome = "Diretor Http $run $seq";
    $cpf = dhCpf($run * 100 + $seq);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-05-10', 'outro', ?, 'Rua de Teste, 10', 'São Paulo/SP', ?, 1, '11987654321', ?, ?)");
    $stmt->bind_param('ssssss', $nome, $senha, $cpf, $email, $cargo, $status);
    $stmt->execute();
    return ['id' => (int) $stmt->insert_id, 'email' => $email, 'nome' => $nome, 'cpf' => $cpf];
};
$formulario = static fn(array $conta, string $cargo, array $extra = []): array => $extra + [
    'nome' => $conta['nome'], 'email' => $conta['email'], 'cpf' => $conta['cpf'], 'celular' => '11987654321',
    'nascimento' => '1990-05-10', 'genero' => 'outro', 'nacionalidade' => 'BR', 'endereco' => 'Rua de Teste, 10',
    'cidade' => 'São Paulo', 'estado' => 'SP', 'cargo' => $cargo, 'secao' => 'funcionarios',
];
$diretor = $criar('diretor');
$admin = $criar('admin');
$outroAdmin = $criar('admin');
$vendedor = $criar('vendedor');
$rota = 'pages/dashboard/funcionalidades/funcionarios.php';
$api = 'pages/dashboard/actions/fotos-faciais.php';

$navDiretor = new Navegador($base);
$navAdmin = new Navegador($base);
dhCheck(str_contains($navDiretor->entrar($diretor['email'])['location'], 'pages/dashboard/dashboard.php'), 'Diretor entra');
dhCheck(str_contains($navAdmin->entrar($admin['email'])['location'], 'pages/dashboard/dashboard.php'), 'Admin entra');
$painelDiretor = $navDiretor->req('GET', 'pages/dashboard/dashboard.php?section=funcionarios')['body'];
$painelAdmin = $navAdmin->req('GET', 'pages/dashboard/dashboard.php?section=funcionarios')['body'];
$cargosDoFormulario = static fn(string $html): array => preg_match('#id="funcCargoNovo"[^>]*>(.*?)</select>#s', $html, $m) && preg_match_all('/value="([a-z]+)"/', $m[1], $v) ? $v[1] : [];

// --- Tela Funcionários de cada nível -------------------------------------------------------
dhCheck(str_contains($painelDiretor, 'const BO_PERFIL_LOGADO = "admin"') && str_contains($painelDiretor, 'data-section="funcionarios"'), 'Diretor usa o painel administrativo');
dhCheck(str_contains($painelDiretor, 'Como Diretor, você também cria e altera contas de administrador e de diretor.'), 'Diretor vê o aviso do seu nível');
dhCheck($cargosDoFormulario($painelDiretor) === ['diretor', 'admin', 'vendedor', 'professor', 'recepcionista', 'faxineira'], 'Diretor pode escolher admin e diretor no cadastro');
dhCheck(str_contains($painelDiretor, 'id="modalFuncionarioEditar' . $outroAdmin['id'] . '"') && !str_contains($painelDiretor, 'id="modalFuncionarioEditar' . $diretor['id'] . '"'), 'Diretor edita administradores, mas não a própria conta');
dhCheck(str_contains($painelAdmin, 'Criar, promover ou alterar administradores e diretores é exclusivo de um Diretor.'), 'Admin vê contas administrativas só para consulta');
dhCheck($cargosDoFormulario($painelAdmin) === ['vendedor', 'professor', 'recepcionista', 'faxineira'], 'Admin só escolhe cargos comuns');
dhCheck(!str_contains($painelAdmin, 'id="modalFuncionarioEditar' . $outroAdmin['id'] . '"') && !str_contains($painelAdmin, 'id="modalFuncionarioEditar' . $diretor['id'] . '"') && str_contains($painelAdmin, 'id="modalFuncionarioEditar' . $vendedor['id'] . '"'), 'Admin não tem formulários para contas administrativas');

// --- Requisições forjadas do admin (CSRF válido): nada muda --------------------------------------
$antes = $retrato();
$token = $navAdmin->csrf();
$tentativas = [
    'rebaixar o diretor' => ['acao' => 'update', 'id' => (string) $diretor['id']] + $formulario($diretor, 'vendedor'),
    'trocar o e-mail do diretor' => ['acao' => 'update', 'id' => (string) $diretor['id']] + $formulario($diretor, 'diretor', ['email' => "invasor{$run}@example.test"]),
    'desativar o diretor' => ['acao' => 'status', 'id' => (string) $diretor['id'], 'status' => 'inativo', 'secao' => 'funcionarios'],
    'redefinir a senha do diretor' => ['acao' => 'reenviar-acesso', 'id' => (string) $diretor['id'], 'secao' => 'funcionarios'],
    'alterar outro admin' => ['acao' => 'update', 'id' => (string) $outroAdmin['id']] + $formulario($outroAdmin, 'admin', ['nome' => 'Trocado']),
    'desativar outro admin' => ['acao' => 'status', 'id' => (string) $outroAdmin['id'], 'status' => 'inativo', 'secao' => 'funcionarios'],
    'se promover a diretor' => ['acao' => 'update', 'id' => (string) $admin['id']] + $formulario($admin, 'diretor'),
    'promover vendedor a diretor' => ['acao' => 'update', 'id' => (string) $vendedor['id']] + $formulario($vendedor, 'diretor'),
    'promover vendedor a admin' => ['acao' => 'update', 'id' => (string) $vendedor['id']] + $formulario($vendedor, 'admin'),
    'criar um diretor' => ['acao' => 'create'] + $formulario(['nome' => "Falso Diretor $run", 'email' => "falso{$run}@example.test", 'cpf' => dhCpf($run * 100 + 90)], 'diretor'),
    'criar um admin' => ['acao' => 'create'] + $formulario(['nome' => "Falso Admin $run", 'email' => "falsoadm{$run}@example.test", 'cpf' => dhCpf($run * 100 + 91)], 'admin'),
    'forjar o operador' => ['acao' => 'status', 'id' => (string) $diretor['id'], 'status' => 'inativo', 'secao' => 'funcionarios', 'id_operador' => (string) $diretor['id'], 'tipo_usuario' => 'diretor'],
];
foreach ($tentativas as $rotulo => $campos) {
    $r = $navAdmin->req('POST', $rota, ['csrf_token' => $token] + $campos);
    dhCheck($r['status'] === 302 && str_contains($r['location'], 'section=funcionarios'), "Admin tenta $rotulo: redirecionado");
}
$r = $navAdmin->req('POST', 'pages/dashboard/funcionalidades/permissoes.php', ['csrf_token' => $token, 'acao' => 'update', 'id_usuario' => (string) $admin['id'], 'tipo_usuario' => 'diretor', 'funcao' => 'diretor']);
dhCheck($r['status'] === 302, 'Rota antiga de permissões recusa');
dhCheck($retrato() === $antes, 'Nenhuma tentativa do admin alterou contas, tokens ou cadastros');
dhCheck($cargoDe($diretor['id']) === 'diretor/ativo' && $cargoDe($admin['id']) === 'admin/ativo' && $cargoDe($vendedor['id']) === 'vendedor/ativo', 'Cargos intactos');
dhCheck(str_contains($navAdmin->req('GET', 'pages/dashboard/dashboard.php?section=funcionarios')['body'], 'só podem ser alteradas por um Diretor'), 'Admin vê a recusa');
// Sem CSRF, nem o diretor altera.
$r = $navDiretor->req('POST', $rota, ['acao' => 'status', 'id' => (string) $outroAdmin['id'], 'status' => 'inativo', 'secao' => 'funcionarios']);
dhCheck($cargoDe($outroAdmin['id']) === 'admin/ativo', 'Diretor sem CSRF: nada muda');

// --- Operações do diretor ------------------------------------------------------------------------
$tokenDiretor = $navDiretor->csrf();
$novo = ['nome' => "Admin Novo $run", 'email' => "admnovo{$run}@example.test", 'cpf' => dhCpf($run * 100 + 92)];
$navDiretor->req('POST', $rota, ['csrf_token' => $tokenDiretor, 'acao' => 'create'] + $formulario($novo, 'admin'));
dhCheck($db->query("SELECT tipo_usuario FROM usuarios WHERE email = '{$novo['email']}'")->fetch_row()[0] === 'admin', 'Diretor cadastra administrador');
$navDiretor->req('POST', $rota, ['csrf_token' => $tokenDiretor, 'acao' => 'update', 'id' => (string) $admin['id']] + $formulario($admin, 'diretor'));
dhCheck($cargoDe($admin['id']) === 'diretor/ativo', 'Diretor promove administrador a diretor');
// A sessão do promovido passa a ter o nível novo na próxima requisição.
dhCheck(str_contains($navAdmin->req('GET', 'pages/dashboard/dashboard.php?section=funcionarios')['body'], 'Como Diretor, você também cria'), 'Promovido ganha o nível de diretor na hora');
$navDiretor->req('POST', $rota, ['csrf_token' => $tokenDiretor, 'acao' => 'update', 'id' => (string) $admin['id']] + $formulario($admin, 'admin'));
dhCheck($cargoDe($admin['id']) === 'admin/ativo', 'Diretor rebaixa diretor (outro diretor continua ativo)');
dhCheck(str_contains($navAdmin->req('GET', 'pages/dashboard/dashboard.php?section=funcionarios')['body'], 'exclusivo de um Diretor'), 'Rebaixado perde o nível de diretor na hora');
$r = $navAdmin->req('POST', $rota, ['csrf_token' => $navAdmin->csrf(), 'acao' => 'status', 'id' => (string) $diretor['id'], 'status' => 'inativo', 'secao' => 'funcionarios']);
dhCheck($cargoDe($diretor['id']) === 'diretor/ativo', 'Rebaixado não usa mais o nível antigo');
$tokens = (int) $db->query("SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$outroAdmin['id']}")->fetch_row()[0];
$navDiretor->req('POST', $rota, ['csrf_token' => $tokenDiretor, 'acao' => 'reenviar-acesso', 'id' => (string) $outroAdmin['id'], 'secao' => 'funcionarios']);
dhCheck((int) $db->query("SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$outroAdmin['id']}")->fetch_row()[0] === $tokens + 1, 'Diretor inicia a redefinição de senha de administrador');
$navDiretor->req('POST', $rota, ['csrf_token' => $tokenDiretor, 'acao' => 'status', 'id' => (string) $diretor['id'], 'status' => 'inativo', 'secao' => 'funcionarios']);
dhCheck($cargoDe($diretor['id']) === 'diretor/ativo', 'Diretor não desativa a própria conta');
$navDiretor->req('POST', $rota, ['csrf_token' => $tokenDiretor, 'acao' => 'status', 'id' => (string) $admin['id'], 'status' => 'inativo', 'secao' => 'funcionarios']);
$r = $navAdmin->req('GET', 'pages/dashboard/dashboard.php');
dhCheck($r['status'] === 302 && str_contains($r['location'], 'pages/login/login.php'), 'Administrador desativado pelo diretor sai do painel na hora');

// --- Cadastro facial: contas administrativas só pelo diretor ----------------------------------
$ids = static fn(Navegador $nav): array => array_column(json_decode($nav->req('GET', $api)['body'], true)['pessoas'] ?? [], 'id');
$navOutroAdmin = new Navegador($base);
$navOutroAdmin->entrar($outroAdmin['email']);
dhCheck(in_array($outroAdmin['id'], $ids($navDiretor), true) && in_array($diretor['id'], $ids($navDiretor), true), 'Diretor lista contas administrativas no cadastro facial');
dhCheck(!in_array($diretor['id'], $ids($navOutroAdmin), true) && !in_array($outroAdmin['id'], $ids($navOutroAdmin), true), 'Admin não lista contas administrativas no cadastro facial');

echo "Hierarquia diretor > admin via HTTP: $checks verificações passaram.\n";
