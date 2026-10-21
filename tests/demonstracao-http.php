<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Login e permissões das cinco contas principais de uma base de demonstração, via HTTP real,
// na cópia sintética apontada para um banco LOCAL (tests/papeis-http.ps1 -Script demonstracao-http.php).
// As senhas vêm do arquivo privado indicado em ONEFIT_DEMO_LOGIN (fora do repositório):
//   {"admin": {"id": 1, "email": "...", "senha": "..."}, "rodrigo": {...}, "daniel": {...}, "fabricio": {...}, "arthur": {...}, "marcos": {...}}
// (admin = conta principal, cargo diretor; marcos = administrador comum).
// Nenhuma senha é impressa. O único POST que alcançaria uma gravação é recusado, e o teste confere que nada mudou.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$arquivoLogin = (string) getenv('ONEFIT_DEMO_LOGIN');
if ($arquivoLogin === '' || !is_file($arquivoLogin)) {
    throw new RuntimeException('Defina ONEFIT_DEMO_LOGIN com o arquivo privado de logins da demonstração.');
}
$contas = json_decode(file_get_contents($arquivoLogin), true, 512, JSON_THROW_ON_ERROR);
$env = parse_ini_file(sys_get_temp_dir() . '/onefit-interface-fixture/www/AN25/OneFit/.env', false, INI_SCANNER_RAW);
if (($env['DB_HOST'] ?? '') !== '127.0.0.1' || !preg_match('/^onefit_(ensaio_reset_\d{8}_\d{6}|interface_test_\d{8}_\d{6}_[a-f0-9]{4})$/D', $env['DB_NAME'] ?? '')) {
    throw new RuntimeException('A cópia sintética precisa apontar para um banco local de ensaio ou de teste.');
}
require __DIR__ . '/http-navegador.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $env['DB_NAME']);
$db->set_charset('utf8mb4');

$checks = 0;
function dhCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
$retrato = static fn(): array => $db->query('CHECKSUM TABLE usuarios, matricula, pagamento, cadastro_profissional, usuarios_fotos_faciais')->fetch_all();
$antes = $retrato();
$painel = static fn(Navegador $nav): string => $nav->req('GET', 'pages/dashboard/dashboard.php')['body'];
$perfil = static fn(string $html): ?string => preg_match('/const BO_PERFIL_LOGADO = "([a-z]+)"/', $html, $m) ? $m[1] : null;

// Senha errada: mensagem comum, sem dizer se a conta existe.
$r = (new Navegador($base))->entrar($contas['admin']['email'], 'senha-errada-' . bin2hex(random_bytes(4)));
dhCheck(str_ends_with($r['location'], 'login.php?msg=1'), 'Senha errada é recusada');

// Cada conta entra no painel do próprio cargo, lido do banco.
$esperado = ['admin' => ['diretor', 'admin'], 'marcos' => ['admin', 'admin'], 'rodrigo' => ['professor', 'profissional'], 'daniel' => ['recepcionista', 'recepcionista'], 'fabricio' => ['aluno', 'aluno'], 'arthur' => ['vendedor', 'vendedor']];
$nav = [];
foreach ($esperado as $chave => [$cargo, $painelEsperado]) {
    $nav[$chave] = new Navegador($base);
    $r = $nav[$chave]->entrar($contas[$chave]['email'], $contas[$chave]['senha']);
    dhCheck(str_contains($r['location'], 'pages/dashboard/dashboard.php'), "$chave entra");
    $cargoBanco = $db->query('SELECT tipo_usuario FROM usuarios WHERE id_usuario = ' . (int) $contas[$chave]['id'])->fetch_row()[0];
    dhCheck($cargoBanco === $cargo, "$chave é $cargo no banco");
    dhCheck($perfil($painel($nav[$chave])) === $painelEsperado, "$chave usa o painel $painelEsperado");
}
// Seções e rotas por cargo.
$admin = $painel($nav['admin']);
dhCheck(str_contains($admin, 'data-section="usuarios"') && str_contains($admin, 'data-section="funcionarios"') && str_contains($admin, 'data-section="cadastro-facial"'), 'Diretor vê Usuários, Funcionários e Cadastro facial');
dhCheck(str_contains($admin, 'Como Diretor, você também cria e altera contas de administrador e de diretor.'), 'Conta principal tem o nível de diretor na tela Funcionários');
$adminComum = $nav['marcos']->req('GET', 'pages/dashboard/dashboard.php?section=funcionarios')['body'];
dhCheck(str_contains($adminComum, 'exclusivo de um Diretor') && !str_contains($adminComum, 'id="modalFuncionarioEditar' . (int) $contas['admin']['id'] . '"'), 'Administrador comum vê o diretor só para consulta');
$r = $nav['marcos']->req('POST', 'pages/dashboard/funcionalidades/funcionarios.php', ['csrf_token' => $nav['marcos']->csrf(), 'acao' => 'status', 'id' => (string) $contas['admin']['id'], 'status' => 'inativo', 'secao' => 'funcionarios']);
dhCheck($db->query('SELECT status FROM usuarios WHERE id_usuario = ' . (int) $contas['admin']['id'])->fetch_row()[0] === 'ativo', 'Administrador comum não desativa o diretor');
$recepcao = $painel($nav['daniel']);
dhCheck(str_contains($recepcao, 'data-section="cadastro-facial"') && str_contains($recepcao, 'data-perfil="recepcionista" data-section="usuarios"')
    && !str_contains($recepcao, 'data-section="funcionarios"') && !str_contains($recepcao, 'pages/dashboard/funcionalidades/'), 'daniel (recepção): cadastro facial e Alunos só para consulta, sem Funcionários nem formulários do admin');
foreach (['rodrigo', 'fabricio', 'arthur'] as $chave) {
    $html = $painel($nav[$chave]);
    dhCheck(!str_contains($html, 'data-section="usuarios"') && !str_contains($html, 'data-section="funcionarios"') && !str_contains($html, 'data-section="cadastro-facial"'), "$chave sem seções de administração");
    dhCheck($nav[$chave]->req('GET', 'pages/dashboard/actions/fotos-faciais.php')['status'] === 403, "$chave não gerencia fotos faciais");
}
$idsAdmin = array_map('intval', array_column($db->query("SELECT id_usuario FROM usuarios WHERE tipo_usuario IN ('admin', 'diretor')")->fetch_all(), 0));
$listaDiretor = array_column(json_decode($nav['admin']->req('GET', 'pages/dashboard/actions/fotos-faciais.php')['body'], true)['pessoas'] ?? [], 'id');
dhCheck(in_array(125, $listaDiretor, true) && in_array(129, $listaDiretor, true) && in_array((int) $contas['marcos']['id'], $listaDiretor, true), 'Diretor lista Daniel (125), Rodrigo (129) e contas administrativas para o cadastro facial');
foreach (['marcos', 'daniel'] as $chave) {
    $lista = json_decode($nav[$chave]->req('GET', 'pages/dashboard/actions/fotos-faciais.php')['body'], true);
    $ids = array_column($lista['pessoas'] ?? [], 'id');
    dhCheck(($lista['ok'] ?? false) === true && in_array(125, $ids, true) && in_array(129, $ids, true) && !array_intersect($idsAdmin, $ids), "$chave lista Daniel (125) e Rodrigo (129) para o cadastro facial, sem contas admin");
}
// Vendedor: os produtos da demonstração aparecem no painel dele.
$vendedor = $painel($nav['arthur']);
$produtos = array_column($db->query('SELECT nome FROM produtos WHERE id_vendedor = ' . (int) $contas['arthur']['id'])->fetch_all(), 0);
dhCheck(count($produtos) === 5, 'Arthur é o vendedor dos 5 produtos');
foreach ($produtos as $nomeProduto) {
    dhCheck(str_contains($vendedor, htmlspecialchars($nomeProduto, ENT_QUOTES, 'UTF-8')), 'Arthur vê o produto no painel');
}
$r = $nav['arthur']->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['csrf_token' => $nav['arthur']->csrf(), 'acao' => 'toggle-status', 'id' => (string) $contas['fabricio']['id'], 'secao' => 'usuarios']);
dhCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), 'Vendedor não usa rotas do admin');

// Contas administrativas: só o diretor fotografa. Administrador comum e recepção são recusados
// (e o teste não grava foto nenhuma de conta administrativa na base de demonstração).
$imagem = tempnam(sys_get_temp_dir(), 'demo-foto');
$gd = imagecreatetruecolor(320, 240);
imagefilledrectangle($gd, 0, 0, 319, 239, imagecolorallocate($gd, 90, 120, 160));
imagejpeg($gd, $imagem);
imagedestroy($gd);
foreach (['marcos', 'daniel'] as $chave) {
    $r = $nav[$chave]->req('POST', 'pages/dashboard/actions/fotos-faciais.php', [
        'csrf_token' => $nav[$chave]->csrf(), 'id_usuario' => (string) $contas['admin']['id'], 'imagem' => new CURLFile($imagem, 'image/jpeg', 'rosto.jpg'),
    ]);
    $resposta = json_decode($r['body'], true);
    dhCheck($r['status'] === 404 && ($resposta['ok'] ?? true) === false, "$chave não cadastra foto do diretor (recusa sem revelar a conta)");
}
unlink($imagem);
// A recepção não usa rotas do admin.
$r = $nav['daniel']->req('POST', 'pages/dashboard/funcionalidades/funcionarios.php', ['csrf_token' => $nav['daniel']->csrf(), 'acao' => 'status', 'id' => (string) $contas['admin']['id'], 'status' => 'inativo', 'secao' => 'funcionarios']);
dhCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), 'Recepção não altera contas pela tela Funcionários');

dhCheck($retrato() === $antes, 'Nenhuma gravação em usuários, matrículas, cobranças, cadastros profissionais ou fotos');
echo "Contas principais via HTTP: $checks verificações passaram.\n";
