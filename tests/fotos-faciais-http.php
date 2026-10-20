<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Cadastro facial e acesso de cargos sem painel via HTTP real, na cópia sintética
// ligada ao banco descartável (tests/papeis-http.ps1 -Script fotos-faciais-http.php,
// depois de tests/funcionarios-preparar.ps1). A cópia não tem SMTP: nenhum e-mail sai.
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
function fhCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function fhContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function fhJson(array $r): array
{
    return json_decode($r['body'], true) ?? [];
}
$arquivos = [];
function fhImagem(string $conteudo): CURLFile
{
    global $arquivos;
    $caminho = tempnam(sys_get_temp_dir(), 'onefit-facial-');
    file_put_contents($caminho, $conteudo);
    $arquivos[] = $caminho;
    return new CURLFile($caminho, 'image/jpeg', 'captura-facial.jpg');
}
function fhJpeg(int $tom): string
{
    $img = imagecreatetruecolor(48, 48);
    imagefill($img, 0, 0, imagecolorallocate($img, $tom % 256, 90, 160));
    ob_start();
    imagejpeg($img, null, 80);
    imagedestroy($img);
    return ob_get_clean();
}

$run = random_int(100000, 899999);
$seq = 0;
$senhaConhecida = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$criar = static function (string $cargo) use ($db, $run, &$seq, $senhaConhecida): array {
    $seq++;
    $email = "fh{$run}x{$seq}@example.test";
    $nome = "Facial Http $run $seq";
    $cpf = sprintf('%011d', $run * 100 + $seq + 50);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 2', 'Santos/SP', ?, 1, '(11) 90000-0001', ?, 'ativo')");
    $stmt->bind_param('sssss', $nome, $senhaConhecida, $cpf, $email, $cargo);
    $stmt->execute();
    return ['id' => (int) $stmt->insert_id, 'email' => $email];
};
$faxineira = $criar('faxineira');
$recepcionista = $criar('recepcionista');
$aluno = $criar('aluno');
$api = 'pages/dashboard/actions/fotos-faciais.php';

try {
    // --- Admin comum ---------------------------------------------------------
    $admin = new Navegador($base);
    fhCheck(str_contains($admin->entrar('fixture1@example.test')['location'], 'pages/dashboard/dashboard.php'), 'Admin entra');
    $token = $admin->csrf();
    $lista = fhJson($admin->req('GET', $api . '?q=' . rawurlencode("Facial Http $run")));
    $idsLista = array_column($lista['pessoas'] ?? [], 'id');
    fhCheck(($lista['ok'] ?? false) && in_array($faxineira['id'], $idsLista, true) && in_array($recepcionista['id'], $idsLista, true) && in_array($aluno['id'], $idsLista, true), 'Lista traz faxineira, recepcionista e aluno');
    $todos = array_column(fhJson($admin->req('GET', $api))['pessoas'] ?? [], 'id');
    fhCheck(!in_array(1, $todos, true) && !in_array(2, $todos, true) && !in_array(6, $todos, true), 'Lista não traz contas admin nem inativas');

    $r = $admin->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $faxineira['id'], 'imagem' => fhImagem(fhJpeg(1))]);
    fhCheck($r['status'] === 200 && (fhJson($r)['fotos_ativas'] ?? 0) === 1, 'Admin cadastra a faxineira por HTTP');
    fhCheck(fhContar($db, "SELECT COUNT(*) FROM usuarios_fotos_faciais WHERE id_usuario = {$faxineira['id']} AND ativo = 1") === 1, 'Foto da faxineira gravada');

    // --- Requisições diretas e parâmetros adulterados ---------------------------
    $total = fhContar($db, 'SELECT COUNT(*) FROM usuarios_fotos_faciais');
    $adulteradas = [
        'Admin (outro) por id direto' => [['csrf_token' => $token, 'id_usuario' => '2', 'imagem' => fhImagem(fhJpeg(2))], 404],
        'Próprio admin (P6)' => [['csrf_token' => $token, 'id_usuario' => '1', 'imagem' => fhImagem(fhJpeg(3))], 404],
        'Cargo e operador forjados' => [['csrf_token' => $token, 'id_usuario' => '2', 'tipo_usuario' => 'aluno', 'id_operador' => '2', 'operador_restrito' => '1', 'imagem' => fhImagem(fhJpeg(4))], 404],
        'Conta inativa' => [['csrf_token' => $token, 'id_usuario' => '6', 'imagem' => fhImagem(fhJpeg(5))], 404],
        'Id inexistente' => [['csrf_token' => $token, 'id_usuario' => '999999999', 'imagem' => fhImagem(fhJpeg(6))], 404],
        'Id não numérico' => [['csrf_token' => $token, 'id_usuario' => '3 OR 1=1', 'imagem' => fhImagem(fhJpeg(7))], 422],
        'Id negativo' => [['csrf_token' => $token, 'id_usuario' => '-3', 'imagem' => fhImagem(fhJpeg(8))], 422],
        'Sem CSRF' => [['id_usuario' => (string) $aluno['id'], 'imagem' => fhImagem(fhJpeg(9))], 403],
        'CSRF errado' => [['csrf_token' => str_repeat('a', 64), 'id_usuario' => (string) $aluno['id'], 'imagem' => fhImagem(fhJpeg(10))], 403],
        'Arquivo que não é imagem' => [['csrf_token' => $token, 'id_usuario' => (string) $aluno['id'], 'imagem' => fhImagem('<?php echo 1;')], 422],
        'Sem arquivo' => [['csrf_token' => $token, 'id_usuario' => (string) $aluno['id']], 422],
    ];
    foreach ($adulteradas as $rotulo => [$campos, $status]) {
        $r = $admin->req('POST', $api, $campos);
        fhCheck($r['status'] === $status && (fhJson($r)['ok'] ?? null) === false, "$rotulo: HTTP {$r['status']} (esperado $status)");
    }
    fhCheck($admin->req('PUT', $api)['status'] === 405, 'Método não permitido');
    fhCheck(fhContar($db, 'SELECT COUNT(*) FROM usuarios_fotos_faciais') === $total, 'Nenhuma requisição adulterada gravou foto');

    // --- Limite de três fotos ativas -------------------------------------------
    foreach ([1, 2, 3] as $n) {
        $r = $admin->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $aluno['id'], 'imagem' => fhImagem(fhJpeg(20 + $n))]);
        fhCheck($r['status'] === 200 && fhJson($r)['fotos_ativas'] === $n, "Foto $n do aluno");
    }
    $maisAntiga = fhContar($db, "SELECT MIN(id_foto_facial) FROM usuarios_fotos_faciais WHERE id_usuario = {$aluno['id']} AND ativo = 1");
    $r = $admin->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $aluno['id'], 'imagem' => fhImagem(fhJpeg(30))]);
    fhCheck($r['status'] === 409 && fhJson($r)['requer_confirmacao'] === true && fhJson($r)['fotos_ativas'] === 3, 'Quarta foto: 409 pedindo confirmação');
    foreach (['true', 'sim', '01', ' 1'] as $quase) {
        $r = $admin->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $aluno['id'], 'substituir_mais_antiga' => $quase, 'imagem' => fhImagem(fhJpeg(31))]);
        fhCheck($r['status'] === 409, "Confirmação só com '1' (recusado: '$quase')");
    }
    fhCheck(fhContar($db, "SELECT COUNT(*) FROM usuarios_fotos_faciais WHERE id_usuario = {$aluno['id']}") === 3, 'Sem confirmação nada muda');
    $r = $admin->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $aluno['id'], 'substituir_mais_antiga' => '1', 'imagem' => fhImagem(fhJpeg(32))]);
    fhCheck($r['status'] === 200 && fhJson($r)['fotos_ativas'] === 3, 'Quarta foto confirmada');
    fhCheck(fhContar($db, "SELECT ativo FROM usuarios_fotos_faciais WHERE id_foto_facial = $maisAntiga") === 0
        && fhContar($db, "SELECT COUNT(*) FROM usuarios_fotos_faciais WHERE id_usuario = {$aluno['id']}") === 4
        && fhContar($db, "SELECT COUNT(*) FROM usuarios_fotos_faciais WHERE id_usuario = {$aluno['id']} AND ativo = 1") === 3, 'Mais antiga desativada e mantida no histórico');

    // --- Faxineira: sem login, sem recuperação, sem link antigo ---------------------
    $nav = new Navegador($base);
    $r = $nav->entrar($faxineira['email']);
    fhCheck(!str_contains($r['location'], 'dashboard.php'), 'Faxineira com senha correta não entra');
    $r = $nav->req('GET', 'pages/dashboard/dashboard.php');
    fhCheck($r['status'] === 302 && !str_contains($r['body'], 'data-perfil="aluno"'), 'Faxineira não abre o painel (nem o do aluno) por URL direta');
    fhCheck($nav->req('GET', $api)['status'] === 401, 'Faxineira não chega ao cadastro facial');
    // Controle: a mesma rota gera link para quem tem painel (sem SMTP, o link fica só no banco).
    $controle = new Navegador($base);
    $tokensAluno = fhContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$aluno['id']}");
    $controle->req('POST', 'pages/auth/senha/processar-recuperacao.php', ['email' => $aluno['email']]);
    fhCheck(fhContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$aluno['id']}") === $tokensAluno + 1, 'Controle: recuperação gera link para aluno');
    $tokens = fhContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$faxineira['id']}");
    $nav->req('GET', 'pages/auth/senha/esqueci-senha.php');
    $nav->req('POST', 'pages/auth/senha/processar-recuperacao.php', ['email' => $faxineira['email']]);
    fhCheck(fhContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$faxineira['id']}") === $tokens, 'Recuperação de senha não gera link para faxineira');
    // Link emitido quando a conta ainda tinha painel: não pode mais definir senha.
    $linkAntigo = bin2hex(random_bytes(32));
    $hash = hash('sha256', $linkAntigo);
    $db->query("INSERT INTO recuperacao_senha_tokens (usuario_id, token_hash, expira_em) VALUES ({$faxineira['id']}, '$hash', DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $senhaAntes = $db->query("SELECT senha FROM usuarios WHERE id_usuario = {$faxineira['id']}")->fetch_row()[0];
    $r = $nav->req('POST', 'pages/auth/senha/redefinir-senha.php', ['token' => $linkAntigo, 'nova_senha' => 'NovaSenha-123!', 'confirmar_senha' => 'NovaSenha-123!']);
    fhCheck($db->query("SELECT senha FROM usuarios WHERE id_usuario = {$faxineira['id']}")->fetch_row()[0] === $senhaAntes, 'Link antigo não troca a senha da faxineira');
    fhCheck(str_contains($r['body'], 'Este link é inválido, expirou ou já foi utilizado.'), 'Link antigo recebe a mensagem genérica');

    // --- Recepcionista: política dá cadastro facial, mas ainda não há painel ---------
    $recep = new Navegador($base);
    fhCheck(!str_contains($recep->entrar($recepcionista['email'])['location'], 'dashboard.php'), 'Recepcionista sem painel não entra (painel da recepção pendente)');
    fhCheck($recep->req('GET', $api)['status'] === 401, 'Recepcionista não chega ao endpoint sem sessão');

    // --- Sessão antiga: aluno que vira faxineira perde o acesso na hora ------------
    $alunoNav = new Navegador($base);
    fhCheck(str_contains($alunoNav->entrar($aluno['email'])['location'], 'dashboard.php'), 'Aluno entra normalmente');
    fhCheck($alunoNav->req('GET', 'pages/dashboard/dashboard.php')['status'] === 200, 'Aluno abre o painel');
    fhCheck($alunoNav->req('GET', $api)['status'] === 403, 'Aluno não opera o cadastro facial');
    $db->query("UPDATE usuarios SET tipo_usuario = 'faxineira' WHERE id_usuario = {$aluno['id']}");
    $r = $alunoNav->req('GET', 'pages/dashboard/dashboard.php');
    fhCheck($r['status'] === 302 && !str_contains($r['body'], 'data-perfil="aluno"'), 'Sessão antiga de quem virou faxineira é encerrada');
    fhCheck($alunoNav->req('GET', $api)['status'] === 401, 'Sessão encerrada também no endpoint');
    $db->query("UPDATE usuarios SET tipo_usuario = 'aluno' WHERE id_usuario = {$aluno['id']}");

    // --- Professor (fixture 4, valor legado 'profissional') -----------------------
    $prof = new Navegador($base);
    fhCheck(str_contains($prof->entrar('fixture4@example.test')['location'], 'dashboard.php'), 'Professor entra');
    $tokenProf = $prof->csrf();
    fhCheck($prof->req('GET', $api)['status'] === 403, 'Professor não lista pessoas');
    $r = $prof->req('POST', $api, ['csrf_token' => $tokenProf, 'id_usuario' => (string) $faxineira['id'], 'imagem' => fhImagem(fhJpeg(40))]);
    fhCheck($r['status'] === 403, 'Professor não grava foto por POST direto');
} finally {
    foreach ($arquivos as $caminho) {
        @unlink($caminho);
    }
}

echo "Cadastro facial via HTTP: $checks verificações passaram.\n";
