<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// "Pagar plano" do aluno via HTTP real, na cópia sintética ligada ao banco
// descartável (tests/papeis-http.ps1 -Script matriculas-http.php, depois de
// tests/funcionarios-preparar.ps1): a requisição do aluno não confirma cobrança
// nem ativa matrícula, com ou sem parâmetros forjados.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/http-navegador.php';
require __DIR__ . '/../pages/dashboard/includes/matriculas.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');

$checks = 0;
function mhCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function mhRetrato(mysqli $db, int $idMatricula): array
{
    return [
        $db->query("SELECT status, data_inicio, data_fim FROM matricula WHERE id_matricula = $idMatricula")->fetch_assoc(),
        $db->query("SELECT id_pagamento, status, data_pagamento, forma_pagamento FROM pagamento WHERE id_matricula = $idMatricula ORDER BY id_pagamento")->fetch_all(MYSQLI_ASSOC),
    ];
}

// Aluno com inscrição pendente (mesmas rotinas da página de matrícula).
$run = random_int(100000, 899999);
$email = "mhttp{$run}@example.test";
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$cpf = sprintf('%011d', $run * 10 + 7);
$stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
    VALUES ('Matricula Http $run', ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 5', 'Santos/SP', ?, 1, '(11) 90000-0004', 'aluno', 'ativo')");
$stmt->bind_param('sss', $senha, $cpf, $email);
$stmt->execute();
$idAluno = (int) $stmt->insert_id;
$idPlano = (int) $db->query('SELECT MIN(id_plano) FROM cadastro_planos')->fetch_row()[0];
$db->begin_transaction();
$idMatricula = bo_matricula_inserir_pendente($db, $idAluno, $idPlano, 100.00);
[$idPagamento] = bo_pagamento_inserir_inscricao($db, $idMatricula, 100.00, 'pix');
$db->commit();
$inicial = mhRetrato($db, $idMatricula);
$url = 'pages/dashboard/funcionalidades/pagar-plano.php';

$aluno = new Navegador($base);
mhCheck(str_contains($aluno->entrar($email)['location'], 'dashboard.php'), 'Aluno com matrícula pendente entra');
$painel = $aluno->req('GET', 'pages/dashboard/dashboard.php');
mhCheck($painel['status'] === 200 && str_contains($painel['body'], 'id="modalPagarPlano"') && str_contains($painel['body'], 'O pagamento online ainda não está disponível.'), 'Modal avisa que o pagamento online não está disponível');
$token = $aluno->csrf();

$tentativas = [
    'Pagar com Pix' => ['csrf_token' => $token, 'secao' => 'historico', 'forma_pagamento' => 'pix'],
    'Pagar com cartão' => ['csrf_token' => $token, 'secao' => 'historico', 'forma_pagamento' => 'cartao'],
    'Status e ids forjados' => ['csrf_token' => $token, 'secao' => 'historico', 'forma_pagamento' => 'pix', 'status' => 'aprovado', 'id_pagamento' => (string) $idPagamento, 'id_matricula' => (string) $idMatricula, 'confirmado' => '1'],
    'Sem CSRF' => ['secao' => 'historico', 'forma_pagamento' => 'pix'],
];
foreach ($tentativas as $rotulo => $campos) {
    $r = $aluno->req('POST', $url, $campos);
    mhCheck($r['status'] === 302, "$rotulo: redireciona");
    mhCheck(mhRetrato($db, $idMatricula) === $inicial, "$rotulo: matrícula e cobrança inalteradas");
}
mhCheck(str_contains($aluno->req('POST', $url, ['csrf_token' => $token, 'secao' => 'historico'])['location'], 'section=historico'), 'Volta para o histórico');
$depois = $aluno->req('GET', 'pages/dashboard/dashboard.php');
mhCheck(str_contains($depois['body'], 'Nenhuma cobrança foi confirmada por esta tela'), 'Aluno vê que nada foi confirmado');
mhCheck((int) $db->query("SELECT COUNT(*) FROM pagamento WHERE id_matricula = $idMatricula AND status = 'aprovado'")->fetch_row()[0] === 0, 'Nenhuma cobrança aprovada');

// Quem não é aluno nem chega à rota.
$admin = new Navegador($base);
$admin->entrar('fixture1@example.test');
$admin->req('POST', $url, ['csrf_token' => $admin->csrf(), 'secao' => 'historico', 'forma_pagamento' => 'pix']);
mhCheck(mhRetrato($db, $idMatricula) === $inicial, 'Admin pela rota do aluno: nada muda');

// A página pública de inscrição continua abrindo com o serviço novo.
$inscricao = (new Navegador($base))->req('GET', 'pages/matricula/matricula.php');
mhCheck($inscricao['status'] === 200 && str_contains($inscricao['body'], 'name="forma_pagamento"') && !str_contains($inscricao['body'], 'Fatal error'), 'Página de inscrição abre normalmente');

echo "Pagamento do plano via HTTP: $checks verificações passaram.\n";
