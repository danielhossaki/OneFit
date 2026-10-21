<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Tela Funcionários via HTTP real, na cópia sintética ligada ao banco descartável
// (tests/papeis-http.ps1 -Script funcionarios-http.php, depois de
// tests/funcionarios-preparar.ps1). A cópia não tem SMTP: nenhum e-mail sai.
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

$checks = 0;
function hCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function hConta(mysqli $db, string $email): ?array
{
    $stmt = $db->prepare('SELECT id_usuario, tipo_usuario, status, nome, email, senha FROM usuarios WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}
function hContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function hCpf(int $n): string
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
function hSituacao(mysqli $db, int $id): array
{
    return $db->query("SELECT tipo_usuario, status, nome, email, cpf FROM usuarios WHERE id_usuario = $id")->fetch_assoc();
}

$run = random_int(100000, 899999);
$seq = 0;
$formulario = static function (string $cargo, array $extra = []) use ($run, &$seq): array {
    $seq++;
    return $extra + [
        'acao' => 'create', 'secao' => 'funcionarios', 'nome' => "Pessoa Http $run $seq", 'email' => "http{$run}x{$seq}@example.test",
        'cpf' => hCpf($run * 100 + $seq), 'celular' => '(11) 98765-4321', 'nascimento' => '1992-03-04', 'genero' => 'feminino',
        'nacionalidade' => 'BR', 'endereco' => 'Rua Http, 5', 'cidade' => 'Santos', 'estado' => 'SP', 'cargo' => $cargo,
    ];
};
$url = 'pages/dashboard/funcionalidades/funcionarios.php';

$admin = new Navegador($base);
hCheck(str_contains($admin->entrar('fixture1@example.test')['location'], 'pages/dashboard/dashboard.php'), 'Admin entra');
$painel = $admin->req('GET', 'pages/dashboard/dashboard.php');
hCheck(str_contains($painel['body'], 'data-section="funcionarios"') && str_contains($painel['body'], 'data-bs-target="#modalFuncionarioNovo"'), 'Tela Funcionários com cadastro liberado (migração aplicada)');
hCheck(str_contains($painel['body'], 'data-bo-table="funcionarios"') && str_contains($painel['body'], 'data-bo-filter="type" data-bo-target="funcionarios"'), 'Busca e filtros de cargo e situação');
preg_match('#<section class="bo-content-section" data-perfil="admin" data-section="funcionarios">(.*?)</section>#s', $painel['body'], $secaoFuncionarios);
hCheck(!str_contains($secaoFuncionarios[1] ?? '', 'Disponibilidade em Professores') && !str_contains($secaoFuncionarios[1] ?? '', 'disponivel_agendamento'), 'Disponibilidade de agenda não é duplicada na tela Funcionários');
hCheck(!str_contains($painel['body'], 'modalFuncionarioEditar2"') && !str_contains($painel['body'], 'modalFuncionarioStatus2"'), 'Conta admin 2 sem ações na tela');
$token = $admin->csrf();

// --- Cadastro pelo formulário real ------------------------------------------
$vendedor = $formulario('vendedor');
$r = $admin->req('POST', $url, $vendedor + ['csrf_token' => $token]);
$conta = hConta($db, $vendedor['email']);
hCheck($r['status'] === 302 && str_contains($r['location'], 'section=funcionarios') && $conta && $conta['tipo_usuario'] === 'vendedor', 'Vendedor cadastrado via POST');
hCheck(str_contains($admin->req('GET', 'pages/dashboard/dashboard.php')['body'], 'o e-mail com o link de acesso não pôde ser enviado'), 'Sem SMTP na cópia: falha de envio informada, cadastro mantido');
$faxineira = $formulario('faxineira');
$admin->req('POST', $url, $faxineira + ['csrf_token' => $token]);
$contaFax = hConta($db, $faxineira['email']);
hCheck($contaFax && $contaFax['tipo_usuario'] === 'faxineira' && hContar($db, 'SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = ' . $contaFax['id_usuario']) === 0, 'Faxineira cadastrada sem link de acesso');
hCheck(str_contains($admin->req('GET', 'pages/dashboard/dashboard.php')['body'], 'Este cargo não tem acesso ao painel'), 'Mensagem de cargo sem painel');

// Nome com HTML é exibido escapado.
$comHtml = $formulario('recepcionista', ['nome' => 'Ana <b>Teste</b> ' . $run]);
$admin->req('POST', $url, $comHtml + ['csrf_token' => $token]);
$painel = $admin->req('GET', 'pages/dashboard/dashboard.php')['body'];
hCheck(str_contains($painel, 'Ana &lt;b&gt;Teste&lt;/b&gt; ' . $run) && !str_contains($painel, 'Ana <b>Teste</b>'), 'Nome exibido escapado na listagem');

// --- CSRF ---------------------------------------------------------------------
$total = hContar($db, 'SELECT COUNT(*) FROM usuarios');
$admin->req('POST', $url, $formulario('vendedor'));
$admin->req('POST', $url, $formulario('vendedor') + ['csrf_token' => str_repeat('a', 64)]);
hCheck(hContar($db, 'SELECT COUNT(*) FROM usuarios') === $total, 'Sem token CSRF válido nada é criado');
hCheck($admin->req('GET', $url)['status'] === 302 && hContar($db, 'SELECT COUNT(*) FROM usuarios') === $total, 'GET no endpoint não grava');

// --- Manipulação direta dos parâmetros -----------------------------------------
foreach (['admin', 'aluno', 'profissional', 'superadmin'] as $cargo) {
    $tentativa = $formulario($cargo);
    $admin->req('POST', $url, $tentativa + ['csrf_token' => $token]);
    hCheck(hConta($db, $tentativa['email']) === null, "Cargo $cargo recusado no POST");
}
$escondido = $formulario('vendedor', ['tipo_usuario' => 'admin', 'status' => 'bloqueado']);
$admin->req('POST', $url, $escondido + ['csrf_token' => $token]);
hCheck(hConta($db, $escondido['email'])['tipo_usuario'] === 'vendedor', 'Campos tipo_usuario/status enviados à força são ignorados');
$admin2 = hSituacao($db, 2);
$admin->req('POST', $url, ['acao' => 'status', 'id' => 2, 'status' => 'inativo', 'csrf_token' => $token]);
$admin->req('POST', $url, $formulario('vendedor', ['acao' => 'update', 'id' => 2]) + ['csrf_token' => $token]);
$admin->req('POST', $url, ['acao' => 'reenviar-acesso', 'id' => 2, 'csrf_token' => $token]);
hCheck(hSituacao($db, 2) === $admin2, 'Conta admin intacta por POST direto (status, edição, acesso)');
$admin->req('POST', $url, ['acao' => 'status', 'id' => $conta['id_usuario'], 'status' => 'bloqueado', 'csrf_token' => $token]);
$admin->req('POST', $url, ['acao' => 'status', 'id' => 3, 'status' => 'inativo', 'csrf_token' => $token]);
$admin->req('POST', $url, ['acao' => 'apagar', 'id' => $conta['id_usuario'], 'csrf_token' => $token]);
hCheck(hConta($db, $vendedor['email'])['status'] === 'ativo' && hSituacao($db, 3)['status'] === 'ativo', 'Status inválido, aluno como alvo e ação desconhecida não alteram nada');
$admin->req('POST', $url, ['acao' => 'status', 'id' => $conta['id_usuario'], 'status' => 'inativo', 'csrf_token' => $token]);
hCheck(hConta($db, $vendedor['email'])['status'] === 'inativo', 'Inativação pela tela');
$admin->req('POST', $url, ['acao' => 'status', 'id' => $conta['id_usuario'], 'status' => 'ativo', 'csrf_token' => $token]);
hCheck(hConta($db, $vendedor['email'])['status'] === 'ativo', 'Reativação pela tela');

// --- Quem não é admin não usa o endpoint ----------------------------------------
foreach (['fixture3@example.test' => 'aluno', 'fixture4@example.test' => 'professor', 'fixture5@example.test' => 'vendedor'] as $email => $rotulo) {
    $nav = new Navegador($base);
    $nav->entrar($email);
    $tokenNav = $nav->csrf();
    $tentativa = $formulario('vendedor');
    $r = $nav->req('POST', $url, $tentativa + ['csrf_token' => $tokenNav]);
    $nav->req('POST', $url, ['acao' => 'status', 'id' => $conta['id_usuario'], 'status' => 'inativo', 'csrf_token' => $tokenNav]);
    hCheck($r['status'] === 302 && hConta($db, $tentativa['email']) === null && hConta($db, $vendedor['email'])['status'] === 'ativo', "$rotulo não cadastra nem altera funcionários");
    hCheck(!str_contains($nav->req('GET', 'pages/dashboard/dashboard.php')['body'], 'data-section="funcionarios"'), "$rotulo não vê a tela Funcionários");
}
hCheck((new Navegador($base))->req('POST', $url, $formulario('vendedor'))['status'] === 302, 'Sem sessão: redirecionado ao login');

// --- A tela de alunos não mexe em funcionários --------------------------------
$tokenUsuarios = $admin->csrf();
$alvos = [$conta['id_usuario'], $contaFax['id_usuario'], 4];
$antes = array_map(static fn(int $id): array => hSituacao($db, $id), $alvos);
foreach ($alvos as $id) {
    $admin->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['acao' => 'toggle-status', 'id' => $id, 'secao' => 'usuarios', 'csrf_token' => $tokenUsuarios]);
    $admin->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['acao' => 'update', 'id' => $id, 'secao' => 'usuarios', 'csrf_token' => $tokenUsuarios,
        'nome' => 'Tomado', 'email' => "tomado{$id}x{$run}@example.test", 'cpf' => hCpf($run * 100 + 77), 'status' => 'inativo']);
    $admin->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['acao' => 'delete', 'id' => $id, 'secao' => 'usuarios', 'csrf_token' => $tokenUsuarios]);
}
hCheck(array_map(static fn(int $id): array => hSituacao($db, $id), $alvos) === $antes, 'Tela de alunos não altera nem exclui funcionários');
$painel = $admin->req('GET', 'pages/dashboard/dashboard.php')['body'];
preg_match('#data-section="usuarios">(.*?)</section>#s', $painel, $telaAlunos);
hCheck(isset($telaAlunos[1]) && !str_contains($telaAlunos[1], $vendedor['email']) && !str_contains($telaAlunos[1], 'fixture1@example.test') && str_contains($telaAlunos[1], 'fixture3@example.test'), 'Lista de alunos mostra só alunos');

// --- Faxineira não entra no sistema, mesmo com senha conhecida -----------------
$senhaTeste = password_hash('Teste-faxineira-1!', PASSWORD_DEFAULT);
$stmt = $db->prepare('UPDATE usuarios SET senha = ?, email_verificado = 1 WHERE id_usuario = ?');
$stmt->bind_param('si', $senhaTeste, $contaFax['id_usuario']);
$stmt->execute();
$stmt->close();
$r = (new Navegador($base))->entrar($faxineira['email'], 'Teste-faxineira-1!');
hCheck(str_ends_with($r['location'], 'login.php?msg=6'), 'Faxineira com senha válida não entra no painel');
$recuperacao = new Navegador($base);
$recuperacao->req('POST', 'pages/auth/senha/processar-recuperacao.php', ['email' => $faxineira['email']]);
hCheck(hContar($db, 'SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = ' . $contaFax['id_usuario']) === 0, 'Recuperação de senha não gera link para faxineira');

// --- Auditoria das operações feitas por HTTP ---------------------------------
$eventos = hContar($db, 'SELECT COUNT(*) FROM auditoria_eventos WHERE id_operador = 1 AND id_alvo = ' . $conta['id_usuario']);
hCheck($eventos >= 3, 'Cadastro e mudanças de situação auditados');

echo "Funcionários via HTTP: $checks verificações passaram.\n";
