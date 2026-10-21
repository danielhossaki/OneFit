<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Painel da recepção via HTTP real, na cópia sintética ligada ao banco descartável
// (tests/papeis-http.ps1 -Script recepcao-http.php, depois de tests/funcionarios-preparar.ps1):
// login, o que a recepção pode fazer (consultar alunos, pagamentos, modalidades e cashback;
// cadastro facial de não admins; a própria conta) e o que o servidor recusa (contas admin,
// pessoas inativas, rotas do admin, CSRF ausente, conta desativada).
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/http-navegador.php';
require __DIR__ . '/../config/interface.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
// A fixture não cria a tabela de fotos faciais (mesma estrutura de produção, como em fotos-faciais-http.php).
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
function rcCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function rcContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function rcJson(array $r): array
{
    return json_decode($r['body'], true) ?? [];
}
$arquivos = [];
function rcImagem(int $tom): CURLFile
{
    global $arquivos;
    $img = imagecreatetruecolor(48, 48);
    imagefill($img, 0, 0, imagecolorallocate($img, $tom % 256, 120, 90));
    $caminho = tempnam(sys_get_temp_dir(), 'onefit-recepcao-');
    imagejpeg($img, $caminho);
    imagedestroy($img);
    $arquivos[] = $caminho;
    return new CURLFile($caminho, 'image/jpeg', 'captura-facial.jpg');
}
$retrato = static fn(): array => $db->query('CHECKSUM TABLE usuarios, matricula, pagamento, cadastro_profissional, cadastro_planos, auditoria_eventos, modalidades, cashback')->fetch_all();

// Contas próprias desta execução.
$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$criar = static function (string $cargo, string $status = 'ativo') use ($db, $run, &$seq, $senha): array {
    $seq++;
    $email = "rc{$run}x{$seq}@example.test";
    $nome = "Recepcao Http $run $seq";
    $cpf = sprintf('%011d', $run * 100 + $seq + 70);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 9', 'Santos/SP', ?, 1, '(11) 90000-0009', ?, ?)");
    $stmt->bind_param('ssssss', $nome, $senha, $cpf, $email, $cargo, $status);
    $stmt->execute();
    return ['id' => (int) $stmt->insert_id, 'email' => $email];
};
$recep = $criar('recepcionista');
$outraRecep = $criar('recepcionista');
$recepInativa = $criar('recepcionista', 'inativo');
$recepBloqueada = $criar('recepcionista', 'bloqueado');
$aluno = $criar('aluno');
$professor = $criar('professor');
$api = 'pages/dashboard/actions/fotos-faciais.php';

try {
    // --- Login -----------------------------------------------------------------------------
    $nav = new Navegador($base);
    rcCheck(str_ends_with((new Navegador($base))->entrar($recep['email'], 'senha-errada')['location'], 'login.php?msg=1'), 'Senha errada recusada com a mensagem comum');
    foreach (['inativa' => $recepInativa, 'bloqueada' => $recepBloqueada] as $rotulo => $conta) {
        rcCheck(str_ends_with((new Navegador($base))->entrar($conta['email'])['location'], 'login.php?msg=2'), "Recepcionista $rotulo não entra");
    }
    rcCheck(str_contains($nav->entrar($recep['email'])['location'], 'pages/dashboard/dashboard.php'), 'Recepcionista ativa entra');
    $painel = $nav->req('GET', 'pages/dashboard/dashboard.php');
    rcCheck($painel['status'] === 200 && str_contains($painel['body'], 'const BO_PERFIL_LOGADO = "recepcionista"'), 'Painel da recepção');
    rcCheck(str_contains($painel['body'], 'data-section="cadastro-facial"') && str_contains($painel['body'], 'assets/js/fotos-faciais.js'), 'Recepção tem o cadastro facial');
    rcCheck(str_contains($painel['body'], 'data-perfil="recepcionista" data-section="agenda"'), 'Recepção tem os agendamentos');
    // Alunos, Pagamentos, Modalidades, Cashback e Marketplace (consulta de pedidos): só consulta
    // (detalhes em tests/secoes-cargos-http.php e tests/recepcao-marketplace-http.php).
    foreach (['usuarios', 'pagamentos', 'modalidades', 'cashbacks', 'marketplace'] as $secao) {
        rcCheck(str_contains($painel['body'], 'data-perfil="recepcionista" data-section="' . $secao . '"'), "Recepção consulta a seção $secao");
    }
    foreach (['funcionarios', 'planos', 'produtos', 'vendas', 'profissionais', 'categorias', 'comentarios', 'historico', 'treino'] as $secao) {
        rcCheck(!str_contains($painel['body'], 'data-section="' . $secao . '"'), "Recepção sem a seção $secao");
    }
    rcCheck(str_contains($painel['body'], $aluno['email']), 'Recepção vê o aluno na tela Alunos');
    preg_match_all('#action="[^"]*pages/dashboard/funcionalidades/([^"]+)"#', $painel['body'], $rotasRecepcao);
    $rotasRecepcao = array_values(array_unique($rotasRecepcao[1]));
    sort($rotasRecepcao);
    rcCheck($rotasRecepcao === ['agenda-recepcao.php', 'cashbacks-consulta.php'], 'Recepção só tem busca de cashback e agendamento nas rotas operacionais');
    $token = $nav->csrf();

    // --- Cadastro facial autorizado ---------------------------------------------------------
    $lista = rcJson($nav->req('GET', $api));
    $ids = array_column($lista['pessoas'] ?? [], 'id');
    rcCheck(($lista['ok'] ?? false) === true, 'Recepção lista pessoas para o cadastro facial');
    foreach (['a própria conta' => $recep, 'outra recepcionista' => $outraRecep, 'aluno' => $aluno, 'professor' => $professor] as $rotulo => $conta) {
        rcCheck(in_array($conta['id'], $ids, true), "Lista traz $rotulo");
    }
    $admins = array_map('intval', array_column($db->query("SELECT id_usuario FROM usuarios WHERE tipo_usuario IN ('admin', 'diretor')")->fetch_all(), 0));
    rcCheck(!array_intersect($admins, $ids) && !in_array($recepInativa['id'], $ids, true), 'Lista não traz contas admin nem inativas');
    foreach (['aluno' => $aluno, 'a própria conta' => $recep, 'outra recepcionista' => $outraRecep] as $rotulo => $conta) {
        $r = $nav->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $conta['id'], 'imagem' => rcImagem($conta['id'])]);
        rcCheck($r['status'] === 200 && (rcJson($r)['fotos_ativas'] ?? 0) === 1, "Recepção cadastra foto de $rotulo");
    }

    // --- Recusas no servidor -------------------------------------------------------------------
    $fotosAntes = rcContar($db, 'SELECT COUNT(*) FROM usuarios_fotos_faciais');
    $fotosAdministrativas = static fn(): int => rcContar($db, "SELECT COUNT(*) FROM usuarios_fotos_faciais f JOIN usuarios u ON u.id_usuario = f.id_usuario WHERE u.tipo_usuario IN ('admin', 'diretor')");
    $fotosAdministrativasAntes = $fotosAdministrativas();
    $antes = $retrato();
    foreach ($admins as $idAdmin) {
        $r = $nav->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $idAdmin, 'imagem' => rcImagem(7), 'tipo_usuario' => 'admin', 'id_operador' => (string) $idAdmin]);
        rcCheck($r['status'] === 404 && (rcJson($r)['ok'] ?? true) === false, "Foto de conta admin recusada ($idAdmin), mesmo com campos forjados");
    }
    $r = $nav->req('POST', $api, ['csrf_token' => $token, 'id_usuario' => (string) $recepInativa['id'], 'imagem' => rcImagem(8)]);
    rcCheck($r['status'] === 404, 'Foto de pessoa inativa recusada');
    $r = $nav->req('POST', $api, ['id_usuario' => (string) $aluno['id'], 'imagem' => rcImagem(9)]);
    rcCheck($r['status'] === 403, 'Sem CSRF: recusado');
    rcCheck(rcContar($db, 'SELECT COUNT(*) FROM usuarios_fotos_faciais') === $fotosAntes, 'Nenhuma foto gravada nas recusas');
    rcCheck($fotosAdministrativas() === $fotosAdministrativasAntes, 'Nenhuma foto de conta administrativa gravada pela recepção');
    // Rotas do admin: recusadas na entrada, mesmo com CSRF válido e campos forjados.
    $rotas = [
        'usuarios.php' => ['acao' => 'toggle-status', 'id' => (string) $aluno['id']],
        'funcionarios.php' => ['acao' => 'status', 'id' => (string) $outraRecep['id'], 'status' => 'inativo'],
        'funcionarios.php#cargo' => ['acao' => 'update', 'id' => (string) $recep['id'], 'cargo' => 'admin', 'tipo_usuario' => 'admin'],
        'pagamentos.php' => ['acao' => 'create', 'id_matricula' => '1', 'valor' => '1'],
        'modalidades.php' => ['acao' => 'create', 'nome' => 'Modalidade forjada', 'descricao' => 'x', 'icone' => 'generico'],
        'cashbacks.php' => ['acao' => 'create', 'data' => '2026-01-01', 'tipo' => 'credito', 'valor' => '50', 'usuarioId' => (string) $aluno['id']],
        'planos.php' => ['acao' => 'delete', 'id' => '1'],
        'permissoes.php' => ['acao' => 'update', 'id_usuario' => (string) $recep['id'], 'tipo_usuario' => 'admin'],
    ];
    foreach ($rotas as $rota => $campos) {
        $r = $nav->req('POST', 'pages/dashboard/funcionalidades/' . strtok($rota, '#'), ['csrf_token' => $token, 'secao' => 'dashboard'] + $campos);
        rcCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), "Rota do admin recusada: $rota");
    }
    rcCheck($nav->req('POST', 'pages/dashboard/actions/interface.php', ['csrf_token' => $token, 'configuracao' => 'tema_cores', 'valor' => onefitTemas()[0]])['status'] === 403, 'Tema do site recusado');
    rcCheck($retrato() === $antes, 'Recusas não alteram usuários, matrículas, cobranças, cadastros, planos, auditoria, modalidades ou cashback');
    rcCheck(rcContar($db, "SELECT COUNT(*) FROM usuarios WHERE id_usuario = {$recep['id']} AND tipo_usuario = 'recepcionista'") === 1, 'Recepcionista continua recepcionista');

    // --- Conta desativada perde a sessão na hora --------------------------------------------
    $db->query("UPDATE usuarios SET status = 'inativo' WHERE id_usuario = {$recep['id']}");
    $r = $nav->req('GET', 'pages/dashboard/dashboard.php');
    rcCheck($r['status'] === 302 && str_contains($r['location'], 'pages/login/login.php'), 'Recepcionista desativada sai do painel');
    rcCheck((new Navegador($base))->req('GET', $api)['status'] === 401, 'Sem sessão, o cadastro facial responde 401');
} finally {
    foreach ($arquivos as $arquivo) {
        @unlink($arquivo);
    }
}
echo "Painel da recepção via HTTP: $checks verificações passaram.\n";
