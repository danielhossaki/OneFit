<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Matriz de permissões via HTTP real, na cópia sintética ligada ao banco
// descartável (tests/papeis-http.ps1 -Script permissoes-http.php, depois de
// tests/funcionarios-preparar.ps1). Para cada rota do painel, cada cargo sem
// permissão — e quem não entrou — manda POSTs com CSRF válido, IDs de outras
// pessoas, cargo e status forjados; quem tem permissão manda sem CSRF.
// Nenhuma dessas requisições pode alterar qualquer tabela do banco.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/http-navegador.php';
require __DIR__ . '/../config/papeis.php';
require __DIR__ . '/../pages/dashboard/includes/matriculas.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');

$checks = 0;
function pmCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/** CHECKSUM de todas as tabelas do banco descartável. */
function pmRetrato(mysqli $db): array
{
    $tabelas = array_column($db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(), 0);
    return array_column($db->query('CHECKSUM TABLE `' . implode('`, `', $tabelas) . '`')->fetch_all(), 1, 0);
}

function pmAlteradas(array $antes, array $depois): string
{
    return implode(', ', array_map(static fn(string $t): string => preg_replace('/^.*\./', '', $t), array_keys(array_diff_assoc($depois, $antes))));
}

// Contas próprias desta execução, uma por cargo que entra no painel, e uma vítima.
$run = random_int(100000, 899999);
$contas = [];
foreach (['aluno', 'professor', 'profissional', 'vendedor', 'recepcionista', 'admin', 'diretor', 'vitima'] as $i => $cargo) {
    $email = "perm{$run}{$cargo}@example.test";
    $senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
    $cpf = sprintf('%011d', $run * 100 + $i);
    $tipo = $cargo === 'vitima' ? 'aluno' : $cargo;
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 7', 'Santos/SP', ?, 1, '(11) 90000-0007', ?, 'ativo')");
    $nome = "Permissao $run $cargo";
    $stmt->bind_param('sssss', $nome, $senha, $cpf, $email, $tipo);
    $stmt->execute();
    $contas[$cargo] = ['id' => (int) $stmt->insert_id, 'email' => $email];
}
$vitima = $contas['vitima'];
$idPlano = (int) $db->query('SELECT MIN(id_plano) FROM cadastro_planos')->fetch_row()[0];
$db->begin_transaction();
$idMatricula = bo_matricula_inserir_pendente($db, $vitima['id'], $idPlano, 100.00);
[$idPagamento, $codigoVitima] = bo_pagamento_inserir_inscricao($db, $idMatricula, 100.00, 'pix');
$db->commit();

$navegadores = [];
foreach (['aluno', 'professor', 'profissional', 'vendedor', 'recepcionista', 'admin', 'diretor'] as $cargo) {
    $nav = new Navegador($base);
    pmCheck(str_contains($nav->entrar($contas[$cargo]['email'])['location'], 'pages/dashboard/dashboard.php'), "$cargo entra no painel");
    $navegadores[$cargo] = ['nav' => $nav, 'csrf' => $nav->csrf()];
}

// Campos forjados: IDs da vítima (e de registros existentes), cargo e status.
$forjados = [
    'secao' => 'dashboard', 'id' => '1', 'id_usuario' => (string) $vitima['id'], 'id_alvo' => (string) $vitima['id'],
    'id_matricula' => (string) $idMatricula, 'id_pagamento' => (string) $idPagamento, 'id_plano' => (string) $idPlano,
    'id_produto' => '1', 'id_categoria' => '1', 'id_modalidade' => '1', 'id_pedido' => '1', 'id_venda' => '1',
    'id_transportadora' => '1', 'id_cashback' => '1', 'id_testemunho' => '1', 'id_devolucao' => '1', 'id_profissional' => '1',
    'nome' => 'Forjado', 'email' => "forjado{$run}@example.test", 'tipo_usuario' => 'admin', 'cargo' => 'admin',
    'status' => 'aprovado', 'valor' => '1', 'preco' => '1', 'forma_pagamento' => 'pix', 'data_inicio' => '2020-01-01', 'data_fim' => '2099-12-31',
];

// Rotas de funcionalidades/ que passam por _shared.php: cargos permitidos lidos do próprio arquivo.
$rotas = [];
foreach (glob(__DIR__ . '/../pages/dashboard/funcionalidades/*.php') as $arquivo) {
    $codigo = file_get_contents($arquivo);
    if (basename($arquivo) === '_shared.php' || !str_contains($codigo, "/_shared.php'")) {
        continue;
    }
    $permitidos = ['admin'];
    if (preg_match('/\$bo_papeis_permitidos\s*=\s*\[([^\]]*)\]/', $codigo, $m)) {
        preg_match_all("/'([a-z]+)'/", $m[1], $p);
        $permitidos = $p[1];
    }
    preg_match_all("/'(create|update|delete|[a-z]+(?:-[a-z]+)+|aprovar|reprovar|recusar|cancelar|concluir|massa|selecionar|status)'/", $codigo, $acoes);
    $rotas[basename($arquivo)] = ['permitidos' => $permitidos, 'acoes' => array_values(array_unique(array_merge([''], $acoes[1])))];
}
pmCheck(count($rotas) >= 19, 'Rotas do painel encontradas (' . count($rotas) . ')');

$anonimo = new Navegador($base);
$negadas = 0;
foreach ($rotas as $rota => ['permitidos' => $permitidos, 'acoes' => $acoes]) {
    $url = "pages/dashboard/funcionalidades/$rota";
    // Quem não entrou: volta para o login.
    $antes = pmRetrato($db);
    foreach ($acoes as $acao) {
        $r = $anonimo->req('POST', $url, ['acao' => $acao] + $forjados);
        pmCheck($r['status'] === 302 && str_contains($r['location'], 'pages/login/login.php'), "$rota sem sessão ($acao): vai para o login");
    }
    pmCheck(($alteradas = pmAlteradas($antes, pmRetrato($db))) === '', "$rota sem sessão alterou: $alteradas");
    foreach ($navegadores as $cargo => ['nav' => $nav, 'csrf' => $csrf]) {
        $permitido = onefitPapelPermitido($cargo, $permitidos);
        $antes = pmRetrato($db);
        foreach ($acoes as $acao) {
            // Sem permissão: CSRF válido não adianta. Com permissão: sem CSRF, nada acontece.
            $campos = ['acao' => $acao] + $forjados + ($permitido ? [] : ['csrf_token' => $csrf]);
            $r = $nav->req('POST', $url, $campos);
            pmCheck($r['status'] === 302, "$rota por $cargo ($acao): redireciona");
            if (!$permitido) {
                pmCheck(str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), "$rota por $cargo ($acao): recusado na entrada");
                $negadas++;
            }
        }
        $rotulo = $permitido ? "$rota por $cargo sem CSRF" : "$rota por $cargo (sem permissão)";
        pmCheck(($alteradas = pmAlteradas($antes, pmRetrato($db))) === '', "$rotulo alterou: $alteradas");
    }
}
pmCheck($negadas >= 100, "Tentativas sem permissão recusadas ($negadas)");

// Rotas fora de _shared.php.
$antes = pmRetrato($db);
foreach (['professor', 'vendedor', 'recepcionista', 'admin', 'diretor'] as $cargo) {
    $r = $navegadores[$cargo]['nav']->req('POST', 'pages/dashboard/funcionalidades/treino.php', ['csrf_token' => $navegadores[$cargo]['csrf'], 'id_usuario' => (string) $vitima['id']]);
    pmCheck($r['status'] === 403, "Treino do aluno por $cargo: 403");
    $r = $navegadores[$cargo]['nav']->req('POST', 'pages/dashboard/actions/fotos-faciais.php', ['csrf_token' => $navegadores[$cargo]['csrf'], 'acao' => 'desativar', 'id_usuario' => (string) $vitima['id'], 'id_foto_facial' => '1']);
    pmCheck(in_array($cargo, ['admin', 'diretor', 'recepcionista'], true) || $r['status'] === 403, "Fotos faciais por $cargo: 403");
}
foreach (['aluno', 'professor', 'vendedor', 'recepcionista'] as $cargo) {
    $r = $navegadores[$cargo]['nav']->req('GET', 'pages/dashboard/funcionalidades/confirmar-exclusao.php?recurso=usuarios&id=' . $vitima['id']);
    pmCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), "Confirmação de exclusão por $cargo: recusada");
    foreach (onefitTemas() as $tema) {
        $r = $navegadores[$cargo]['nav']->req('POST', 'pages/dashboard/actions/interface.php', ['csrf_token' => $navegadores[$cargo]['csrf'], 'configuracao' => 'tema_cores', 'valor' => $tema]);
        pmCheck($r['status'] === 403, "Tema do site ($tema) por $cargo: 403");
    }
}
pmCheck(($alteradas = pmAlteradas($antes, pmRetrato($db))) === '', "Rotas fora de _shared.php alteraram: $alteradas");

// O painel de quem não é admin não traz dados de outras pessoas, nem pedindo a seção do admin.
// Exceção prevista: a recepção consulta Alunos e Pagamentos (tests/secoes-cargos-http.php) e
// vê o e-mail do aluno; o código da cobrança não aparece para nenhum desses cargos.
foreach (['aluno', 'professor', 'vendedor', 'recepcionista'] as $cargo) {
    foreach (['usuarios', 'funcionarios', 'pagamentos', 'cadastro-facial'] as $secao) {
        $r = $navegadores[$cargo]['nav']->req('GET', "pages/dashboard/dashboard.php?section=$secao");
        $semEmail = $cargo === 'recepcionista' || !str_contains($r['body'], $vitima['email']);
        pmCheck($r['status'] === 200 && $semEmail && !str_contains($r['body'], $codigoVitima), "Painel do $cargo (?section=$secao) sem dados da vítima");
    }
}
$painelAdmin = $navegadores['admin']['nav']->req('GET', 'pages/dashboard/dashboard.php?section=usuarios');
pmCheck(str_contains($painelAdmin['body'], $vitima['email']), 'Controle: o admin vê a vítima na tela de usuários');

// Vítima intacta no fim: cargo, status, matrícula e cobrança.
pmCheck($db->query("SELECT CONCAT(tipo_usuario, '/', status) FROM usuarios WHERE id_usuario = {$vitima['id']}")->fetch_row()[0] === 'aluno/ativo', 'Vítima continua aluno ativo');
pmCheck($db->query("SELECT status FROM matricula WHERE id_matricula = $idMatricula")->fetch_row()[0] === 'pendente', 'Matrícula da vítima continua pendente');
pmCheck($db->query("SELECT status FROM pagamento WHERE id_pagamento = $idPagamento")->fetch_row()[0] === 'pendente', 'Cobrança da vítima continua pendente');

echo "Permissões via HTTP: $checks verificações passaram (" . count($rotas) . " rotas, $negadas tentativas sem permissão).\n";
