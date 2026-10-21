<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Cashback dos clientes e Marketplace da recepção via HTTP real, na cópia sintética ligada ao banco
// descartável (tests/papeis-http.ps1 -Script recepcao-marketplace-http.php, depois de
// tests/funcionarios-preparar.ps1):
//  - indicadores de cashback iguais aos calculados direto no banco (concedido, devoluções,
//    utilizado, saldo disponível), saldo por cliente e movimentações, para recepção, vendedor e admin;
//  - Marketplace da recepção: "Comprar produtos" continua levando à loja e a compra completa funciona;
//    a consulta mostra pedidos com e sem cashback, em vários status e com devolução, sem dados
//    pessoais além de nome e ID;
//  - nenhuma alteração de pedido, item, devolução, produto, cobrança ou cashback pela recepção,
//    nem pela interface, nem por requisição direta.
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

$checks = 0;
function rmCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function rmValor(mysqli $db, string $sql): string
{
    return (string) ($db->query($sql)->fetch_row()[0] ?? '');
}
function rmInserir(mysqli $db, string $tabela, array $dados): int
{
    $stmt = $db->prepare("INSERT INTO `$tabela` (`" . implode('`, `', array_keys($dados)) . '`) VALUES (' . implode(', ', array_fill(0, count($dados), '?')) . ')');
    $valores = array_values($dados);
    $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute();
    return (int) $stmt->insert_id;
}
/** HTML de uma seção (da abertura da <section> até o primeiro </section>). */
function rmSecao(string $html, string $perfil, string $secao): string
{
    $padrao = '#<section\b[^>]*data-perfil="' . $perfil . '"[^>]*data-section="' . preg_quote($secao, '#') . '"[^>]*>.*?</section>#s';
    return preg_match($padrao, $html, $m) ? $m[0] : '';
}
/** Valor exibido num indicador (data-bo-cashback="...") da tela de cashback. */
function rmIndicador(string $html, string $chave): string
{
    return preg_match('#data-bo-cashback="' . $chave . '">([^<]*)<#', $html, $m) ? trim(html_entity_decode($m[1])) : '';
}
function rmModal(string $html, int $idPedido): string
{
    return preg_match('#id="boRecepcaoPedido' . $idPedido . '".*?<div class="modal-footer">#s', $html, $m) ? $m[0] : '';
}
$moeda = static fn(float $v): string => onefitMoeda($v);
// Tabelas que a recepção não pode alterar (as compras da própria recepção são conferidas à parte).
$retrato = static fn(): array => $db->query('CHECKSUM TABLE pedido, pedido_item, pedido_devolucao, cashback, produtos, pagamento, matricula, transportadoras, faixas_cep_frete, categorias, enderecos_entrega, usuarios')->fetch_all();

// --- Contas e dados próprios desta execução ------------------------------------------------
$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$criar = static function (string $cargo) use ($db, $run, &$seq, $senha): array {
    $seq++;
    $email = "rm{$run}x{$seq}@example.test";
    $nome = "Marketplace Http $run $seq";
    $cpf = sprintf('%011d', $run * 100 + $seq + 20);
    $celular = sprintf('(11) 9%04d-%04d', $seq, $run % 10000);
    $id = rmInserir($db, 'usuarios', ['nome' => $nome, 'senha' => $senha, 'nacionalidade' => 'BR', 'data_nascimento' => '1990-01-01', 'genero' => 'outro',
        'cpf' => $cpf, 'endereco' => 'Rua Teste, 3', 'cidade_estado' => 'Santos/SP', 'email' => $email, 'email_verificado' => 1, 'celular' => $celular,
        'tipo_usuario' => $cargo, 'status' => 'ativo']);
    return ['id' => $id, 'email' => $email, 'nome' => $nome, 'cpf' => $cpf, 'celular' => $celular];
};
$recep = $criar('recepcionista');
$admin = $criar('admin');
$vendedor = $criar('vendedor');
$comCashback = $criar('aluno');
$semCashback = $criar('aluno');
$negativo = $criar('aluno');

$produtoCb = rmInserir($db, 'produtos', ['id_vendedor' => $vendedor['id'], 'nome' => "Garrafa $run", 'preco' => '50.00', 'estoque' => 20, 'categoria' => 'Teste',
    'cashback_valor' => '3.00', 'status' => 'ativo', 'desconto' => '0']);
$produtoSemCb = rmInserir($db, 'produtos', ['id_vendedor' => $vendedor['id'], 'nome' => "Toalha $run", 'preco' => '20.00', 'estoque' => 20, 'categoria' => 'Teste',
    'cashback_valor' => '0.00', 'status' => 'ativo', 'desconto' => '0']);
$enderecoPedido = "Rua Pedido $run";
$pedido = static function (array $cliente, string $forma, string $status, string $data, array $itens) use ($db, $vendedor, $enderecoPedido): array {
    $total = array_sum(array_map(static fn(array $i): float => $i[1] * $i[2], $itens));
    $id = rmInserir($db, 'pedido', ['id_usuario' => $cliente['id'], 'valor_total' => number_format($total, 2, '.', ''), 'forma_pagamento' => $forma, 'status' => $status,
        'data_pedido' => $data, 'endereco_cep' => '11000000', 'endereco_logradouro' => $enderecoPedido, 'endereco_numero' => '1', 'endereco_bairro' => 'Centro',
        'endereco_cidade' => 'Santos', 'endereco_uf' => 'SP']);
    $idsItens = [];
    foreach ($itens as [$produto, $qtd, $preco, $logistica, $rastreio, $recebido]) {
        $idsItens[] = rmInserir($db, 'pedido_item', ['id_pedido' => $id, 'id_produto' => $produto, 'id_vendedor' => $vendedor['id'], 'quantidade' => $qtd,
            'preco_unitario' => number_format($preco, 2, '.', ''), 'subtotal' => number_format($preco * $qtd, 2, '.', ''), 'valor_frete' => '0.00',
            'status_logistica' => $logistica, 'codigo_rastreio' => $rastreio, 'confirmado_recebimento' => $recebido ? 1 : 0, 'confirmado_recebimento_em' => $recebido]);
    }
    return ['id' => $id, 'total' => $total, 'itens' => $idsItens];
};
$cashback = static fn(array $cliente, string $tipo, string $origem, float $valor, string $descricao, string $data, string $status = 'disponivel'): int =>
    rmInserir($db, 'cashback', ['id_usuario' => $cliente['id'], 'valor' => number_format($valor, 2, '.', ''), 'tipo' => $tipo, 'origem' => $origem,
        'descricao' => $descricao, 'status' => $status, 'data_criacao' => $data]);

// Entregue e recebido, com cashback concedido (e um lançamento cancelado, que fica fora dos totais).
$p1 = $pedido($comCashback, 'pix', 'entregue', '2026-09-01 10:00:00', [[$produtoCb, 2, 50.0, 'entregue', "RAS-$run-1", '2026-09-04 09:30:00']]);
$cashback($comCashback, 'credito', 'produto', 6.00, "Cashback do pedido #{$p1['id']}", '2026-09-01 10:00:00');
$cashback($comCashback, 'credito', 'produto', 50.00, "Cashback do pedido #{$p1['id']}", '2026-09-01 10:05:00', 'cancelado');
// Enviado, com cashback usado e concedido.
$p2 = $pedido($comCashback, 'cartao', 'despachado', '2026-09-10 15:00:00', [[$produtoCb, 1, 50.0, 'despachado', "RAS-$run-2", null]]);
$cashback($comCashback, 'debito', 'uso', 4.00, "Uso de cashback no pedido #{$p2['id']}", '2026-09-10 15:00:00', 'utilizado');
$cashback($comCashback, 'credito', 'produto', 3.00, "Cashback do pedido #{$p2['id']}", '2026-09-10 15:00:00');
// Aguardando, sem cashback (produto sem cashback e nada usado).
$p3 = $pedido($semCashback, 'pix', 'aguardando', '2026-10-01 08:00:00', [[$produtoSemCb, 1, 20.0, 'aguardando', null, null]]);
// Devolvido: devolução concluída com o valor do pedido creditado como cashback.
$p4 = $pedido($comCashback, 'pix', 'devolvido', '2026-09-15 11:00:00', [[$produtoSemCb, 2, 20.0, 'devolvido', "RAS-$run-4", '2026-09-18 10:00:00']]);
$creditoDevolucao = $cashback($comCashback, 'credito', 'devolucao', $p4['total'], "Devolução do pedido #{$p4['id']}", '2026-09-25 16:00:00');
$motivo = "Produto com defeito $run";
rmInserir($db, 'pedido_devolucao', ['id_pedido' => $p4['id'], 'motivo' => $motivo, 'observacao' => $motivo, 'status' => 'concluida', 'data_solicitacao' => '2026-09-20 09:00:00',
    'data_analise' => '2026-09-22 14:00:00', 'data_conclusao' => '2026-09-25 16:00:00', 'id_admin' => $admin['id'], 'id_cashback' => $creditoDevolucao]);
// Ajustes manuais do admin que deixam um cliente com saldo negativo (fora da soma do saldo disponível).
$cashback($negativo, 'credito', 'uso', 2.00, 'Lançamento manual pelo admin', '2026-09-05 12:00:00');
$cashback($negativo, 'debito', 'uso', 5.00, 'Lançamento manual pelo admin', '2026-09-06 12:00:00', 'utilizado');

// Valores esperados, calculados direto no banco inteiro (a fixture pode ter outros lançamentos).
$soma = static fn(string $onde): float => (float) rmValor($db, "SELECT COALESCE(SUM(valor), 0) FROM cashback WHERE status <> 'cancelado' AND $onde");
$usoCompra = "descricao REGEXP '^Uso de cashback no pedido #[0-9]+$'";
$esperado = [
    'concedido' => $soma("tipo = 'credito' AND origem <> 'devolucao'"),
    'devolucoes' => $soma("tipo = 'credito' AND origem = 'devolucao'"),
    'utilizado' => $soma("tipo = 'debito' AND $usoCompra"),
    'saldo' => (float) rmValor($db, "SELECT COALESCE(SUM(GREATEST(saldo, 0)), 0) FROM (SELECT SUM(IF(tipo = 'credito', valor, -valor)) AS saldo FROM cashback WHERE status <> 'cancelado' GROUP BY id_usuario) s"),
];
$debitosManuais = $soma("tipo = 'debito' AND NOT ($usoCompra)");
$saldoCliente = static fn(array $c): float => (float) rmValor($db, "SELECT COALESCE(SUM(IF(tipo = 'credito', valor, -valor)), 0) FROM cashback WHERE status <> 'cancelado' AND id_usuario = {$c['id']}");
rmCheck(abs($saldoCliente($comCashback) - (6 + 3 - 4 + $p4['total'])) < 0.001 && abs($saldoCliente($negativo) + 3) < 0.001, 'Cenário montado: saldos dos clientes de teste');

$nav = [];
foreach (['recep' => $recep, 'admin' => $admin, 'vendedor' => $vendedor, 'cliente' => $comCashback] as $chave => $conta) {
    $nav[$chave] = new Navegador($base);
    rmCheck(str_contains($nav[$chave]->entrar($conta['email'])['location'], 'pages/dashboard/dashboard.php'), "$chave entra no painel");
}
$painel = static fn(string $chave): string => $nav[$chave]->req('GET', 'pages/dashboard/dashboard.php')['body'];
// Nenhum aviso ou erro do PHP nos painéis envolvidos.
foreach (['recep', 'admin', 'vendedor', 'cliente'] as $chave) {
    $r = $nav[$chave]->req('GET', 'pages/dashboard/dashboard.php');
    rmCheck($r['status'] === 200 && !preg_match('/<b>(?:Warning|Notice|Deprecated|Fatal error)<\/b>|Uncaught /', $r['body']), "Painel do $chave sem aviso ou erro do PHP");
}

// --- 1. Cashback dos clientes: indicadores, saldo por cliente e movimentações -------------------
$telas = ['recep' => 'recepcionista', 'vendedor' => 'vendedor', 'admin' => 'admin'];
foreach ($telas as $chave => $perfil) {
    $tela = rmSecao($painel($chave), $perfil, 'cashbacks');
    rmCheck($tela !== '', "$chave tem a tela de cashback");
    foreach ($esperado as $indicador => $valor) {
        rmCheck(rmIndicador($tela, $indicador) === $moeda($valor), "$chave: indicador $indicador = {$moeda($valor)} (tela: " . rmIndicador($tela, $indicador) . ')');
    }
    rmCheck(rmIndicador($tela, 'debitos-manuais') !== '' && str_contains($tela, $moeda($debitosManuais)), "$chave: débitos manuais do admin aparecem à parte");
    rmCheck(str_contains($tela, 'data-bo-cashback="saldo-negativo"'), "$chave: aviso de cliente com saldo negativo fora da soma");
    // Nenhuma métrica repetida: os quatro indicadores são diferentes entre si nesta base.
    rmCheck(!str_contains($tela, 'Distribuídos') && !str_contains($tela, 'Saldo total'), "$chave: sem os indicadores antigos (Distribuídos, Saldo total)");
    // Movimentações: todas as do cliente de teste, com origem e pedido; a cancelada aparece marcada.
    preg_match_all('#<tr data-type="[a-z]+" data-user="' . $comCashback['id'] . '"[^>]*>.*?</tr>#s', $tela, $linhas);
    rmCheck(count($linhas[0]) === (int) rmValor($db, "SELECT COUNT(*) FROM cashback WHERE id_usuario = {$comCashback['id']}"), "$chave: todas as movimentações do cliente");
    $todas = implode('', $linhas[0]);
    foreach (['Cashback de compra', 'Uso em compra', 'Devolução de pedido', 'Cancelado', 'TRX-' . str_pad((string) $p1['id'], 4, '0', STR_PAD_LEFT), 'TRX-' . str_pad((string) $p4['id'], 4, '0', STR_PAD_LEFT)] as $texto) {
        rmCheck(str_contains($todas, $texto), "$chave: movimentações do cliente mostram \"$texto\"");
    }
    rmCheck(str_contains($tela, 'data-status="debito_manual"') && str_contains($tela, 'data-status="admin"'), "$chave: lançamentos manuais do admin classificados");
    rmCheck(str_contains($tela, 'data-bo-filter="user" data-bo-target="cashbacks"'), "$chave: filtro por ID do usuário nas movimentações");
    // Nome do cliente e saldo por cliente: só admin e recepção. O vendedor continua vendo só o ID.
    $comNomes = $chave !== 'vendedor';
    rmCheck(str_contains($tela, $comCashback['nome']) === $comNomes, "$chave: nome do cliente " . ($comNomes ? 'visível' : 'oculto'));
    rmCheck(str_contains($tela, 'data-bo-cashback-cliente=') === $comNomes, "$chave: saldo por cliente " . ($comNomes ? 'presente' : 'ausente'));
    if ($comNomes) {
        rmCheck(preg_match('#data-bo-cashback-cliente="' . $comCashback['id'] . '".*?data-bo-cashback-saldo>(.*?)</td>#s', $tela, $m) === 1
            && str_contains($m[1], $moeda($saldoCliente($comCashback))), "$chave: saldo individual do cliente");
        rmCheck(preg_match('#data-bo-cashback-cliente="' . $negativo['id'] . '".*?data-bo-cashback-saldo>(.*?)</td>#s', $tela, $m) === 1
            && str_contains($m[1], 'bo-badge-inactive') && str_contains($m[1], $moeda(-3)), "$chave: saldo negativo destacado");
        rmCheck(str_contains($tela, 'data-bo-filtrar-usuario="' . $comCashback['id'] . '" data-bo-target="cashbacks"'), "$chave: \"Ver histórico\" filtra as movimentações do cliente");
    }
    // E-mail, documento e telefone dos clientes não aparecem nesta tela.
    foreach (['email', 'cpf', 'celular'] as $campo) {
        rmCheck(!str_contains($tela, $comCashback[$campo]), "$chave: tela de cashback sem o $campo do cliente");
    }
}
$telaRecep = rmSecao($painel('recep'), 'recepcionista', 'cashbacks');
rmCheck(str_contains($telaRecep, 'data-bo-somente-consulta') && str_contains($telaRecep, 'Cashback dos clientes') && str_contains($telaRecep, 'Estes valores não são o seu saldo.'), 'Recepção: tela identificada como cashback dos clientes, só consulta');
preg_match_all('/<form\b/i', $telaRecep, $formulariosConsultaCpf);
rmCheck(count($formulariosConsultaCpf[0]) === 1 && str_contains($telaRecep, 'cashbacks-consulta.php'), 'Recepção: somente busca autorizada de cashback por CPF');
$telaRecepSomenteConsulta = preg_replace('#<form\b[^>]*action="[^"]*cashbacks-consulta\.php"[^>]*>.*?</form>#s', '', $telaRecep) ?? $telaRecep;
rmCheck(!preg_match('/<form\b|funcionalidades\/|btn-bo-gold|data-bs-toggle="modal"|bi-trash|bi-pencil|modalCashback/', $telaRecepSomenteConsulta), 'Recepção: cashback sem botões ou formulários de gravação');
$telaAdmin = rmSecao($painel('admin'), 'admin', 'cashbacks');
rmCheck(str_contains($telaAdmin, 'data-bs-target="#modalCashbackLancar"') && str_contains($telaAdmin, 'data-bs-target="#modalCashbackMassa"') && str_contains($telaAdmin, 'bi-trash'), 'Admin mantém lançar, distribuir e excluir cashback');
// O aluno continua vendo só o próprio saldo, com a mesma regra.
$aluno = rmSecao($painel('cliente'), 'aluno', 'cashback');
rmCheck(str_contains($aluno, $moeda($saldoCliente($comCashback))) && !str_contains($aluno, 'data-bo-cashback='), 'Aluno: o próprio saldo, sem os indicadores dos clientes');

// --- 2. Marketplace da recepção ---------------------------------------------------------------
$html = $painel('recep');
$mkt = rmSecao($html, 'recepcionista', 'marketplace');
rmCheck($mkt !== '' && str_contains($mkt, 'data-bo-opcao="comprar"') && str_contains($mkt, 'data-bo-opcao="consultar"'), 'Marketplace da recepção com as duas opções');
rmCheck(str_contains($mkt, 'href="' . $base . 'pages/marketplace/marketplace.php"'), '"Comprar produtos" leva à loja');
rmCheck(str_contains($mkt, 'data-bo-somente-consulta'), 'Consulta de pedidos marcada como somente consulta');
rmCheck(!preg_match('/<form\b|method="post"|funcionalidades\/|name="status_logistica"|data-bo-acao|bi-trash|bi-pencil|btn-bo-danger/i', $mkt), 'Consulta sem formulário, ação de gravação ou botão de exclusão');
rmCheck(!str_contains($html, 'data-section="vendas"') && !str_contains($html, 'data-section="produtos"'), 'Recepção continua sem as telas de gestão de vendas e produtos');
foreach (['search', 'user', 'status', 'date-from', 'date-to'] as $filtro) {
    rmCheck(str_contains($mkt, 'data-bo-filter="' . $filtro . '" data-bo-target="recepcaoPedidos"'), "Consulta filtra por $filtro");
}
$trx = static fn(array $p): string => 'TRX-' . str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT);
foreach ([[$p1, 'entregue', $comCashback], [$p2, 'despachado', $comCashback], [$p3, 'aguardando', $semCashback], [$p4, 'devolvido', $comCashback]] as [$p, $status, $cliente]) {
    rmCheck(preg_match('#<tr data-status="' . $status . '" data-user="' . $cliente['id'] . '"\s+data-date="[0-9-]{10}" data-bo-pedido="' . $p['id'] . '"#', $mkt) === 1, "Pedido {$p['id']} listado ($status, cliente {$cliente['id']})");
    rmCheck(preg_match('#data-bo-pedido="' . $p['id'] . '"\s+data-search="[^"]*' . strtolower($trx($p)) . '[^"]*"#', $mkt) === 1, "Pedido {$p['id']} localizável pelo número");
    rmCheck(rmModal($mkt, $p['id']) !== '', "Detalhes do pedido {$p['id']}");
}
$d1 = rmModal($mkt, $p1['id']);
rmCheck(str_contains($d1, "Garrafa $run") && str_contains($d1, $moeda(100)) && str_contains($d1, "RAS-$run-1") && str_contains($d1, 'Recebimento confirmado pelo cliente'), 'Pedido entregue: produto, total, rastreio e recebimento');
rmCheck(preg_match('#data-bo-cashback-concedido>([^<]*)<#', $d1, $m) && trim($m[1]) === $moeda(6), 'Pedido entregue: cashback concedido sem o lançamento cancelado');
rmCheck(str_contains($d1, 'lançamento cancelado'), 'Pedido entregue: lançamento cancelado aparece na linha do tempo');
$d2 = rmModal($mkt, $p2['id']);
rmCheck(preg_match('#data-bo-cashback-usado>([^<]*)<#', $d2, $m) && trim($m[1]) === $moeda(4) && preg_match('#data-bo-cashback-concedido>([^<]*)<#', $d2, $m2) && trim($m2[1]) === $moeda(3), 'Pedido com cashback: usado e concedido');
rmCheck(str_contains($d2, 'Cartão') && str_contains($d2, 'Enviado'), 'Pedido enviado: forma de pagamento e status');
$d3 = rmModal($mkt, $p3['id']);
rmCheck(str_contains($d3, 'Não utilizado') && str_contains($d3, 'Sem cashback') && str_contains($d3, 'Aguardando'), 'Pedido sem cashback');
$d4 = rmModal($mkt, $p4['id']);
rmCheck(str_contains($d4, $motivo) && str_contains($d4, 'Devolução concluída') && str_contains($d4, 'Devolução aprovada') && preg_match('#data-bo-cashback-devolucao>([^<]*)<#', $d4, $m) && trim($m[1]) === $moeda($p4['total']), 'Pedido devolvido: motivo, etapas e crédito da devolução');
// Dados pessoais: só nome e ID do cliente.
foreach ([$comCashback, $semCashback] as $cliente) {
    rmCheck(str_contains($mkt, $cliente['nome']) && !str_contains($mkt, $cliente['email']) && !str_contains($mkt, $cliente['cpf']) && !str_contains($mkt, $cliente['celular']), 'Consulta mostra o nome, sem e-mail, documento ou telefone');
}
rmCheck(!str_contains($mkt, $enderecoPedido), 'Consulta sem o endereço de entrega');

// --- 3. Nada muda pela recepção: requisições diretas às rotas de gravação ---------------------
$csrf = $nav['recep']->csrf();
$antes = $retrato();
$tentativas = [
    'vendas.php' => [['acao' => 'update-status', 'id' => (string) $p2['itens'][0], 'status_logistica' => 'entregue', 'codigo_rastreio' => 'FORJADO']],
    'devolucoes.php' => [['acao' => 'aprovar', 'id' => '1'], ['acao' => 'recusar', 'id' => '1', 'observacao' => 'x'], ['acao' => 'concluir', 'id' => '1']],
    'meus-pedidos.php' => [['acao' => 'confirmar-recebimento', 'id_item' => (string) $p2['itens'][0]], ['acao' => 'solicitar-devolucao', 'id_pedido' => (string) $p1['id'], 'motivo' => 'Forjado']],
    'produtos.php' => [['acao' => 'update', 'id' => (string) $produtoCb, 'nome' => 'Forjado', 'preco' => '1', 'estoque' => '0', 'cashback' => '99'], ['acao' => 'toggle-status', 'id' => (string) $produtoCb], ['acao' => 'delete', 'id' => (string) $produtoSemCb]],
    'cashbacks.php' => [['acao' => 'create', 'data' => '2026-01-01', 'tipo' => 'credito', 'valor' => '99', 'usuarioId' => (string) $recep['id']], ['acao' => 'massa', 'data' => '2026-01-01', 'valor' => '1', 'alvo' => 'Todos'], ['acao' => 'delete', 'id' => (string) $creditoDevolucao]],
    'pagamentos.php' => [['acao' => 'create', 'data' => '2026-01-01', 'tipo' => 'PIX', 'valor' => '10', 'usuarioId' => (string) $comCashback['id']], ['acao' => 'delete', 'id' => '1']],
    'transportadoras.php' => [['acao' => 'create', 'nome' => 'Forjada', 'tipo' => 'outros'], ['acao' => 'delete', 'id' => '1']],
    'categorias.php' => [['acao' => 'create', 'nome' => "Forjada $run"]],
];
foreach ($tentativas as $rota => $lista) {
    foreach ($lista as $campos) {
        $r = $nav['recep']->req('POST', "pages/dashboard/funcionalidades/$rota", ['csrf_token' => $csrf, 'secao' => 'marketplace'] + $campos);
        rmCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), "$rota ({$campos['acao']}) pela recepção: recusado na entrada");
    }
}
rmCheck($nav['recep']->req('GET', 'pages/dashboard/funcionalidades/buscar-compras.php')['status'] === 403, 'Busca de "Minhas compras" continua recusando a recepção');
// O próprio painel não grava nada com campos forjados.
$r = $nav['recep']->req('POST', 'pages/dashboard/dashboard.php?section=marketplace', ['csrf_token' => $csrf, 'acao' => 'update-status', 'prof_action' => 'agendar',
    'id' => (string) $p2['itens'][0], 'id_pedido' => (string) $p2['id'], 'status' => 'cancelado', 'status_logistica' => 'entregue', 'valor' => '99']);
rmCheck($r['status'] === 200, 'POST forjado no painel da recepção só devolve a tela');
rmCheck($retrato() === $antes, 'Recusas não alteram pedidos, itens, devoluções, cashback, produtos, cobranças, matrículas, transportadoras, categorias, endereços nem usuários');
rmCheck(rmValor($db, "SELECT CONCAT(status_logistica, '/', IFNULL(codigo_rastreio, '')) FROM pedido_item WHERE id_item = {$p2['itens'][0]}") === "despachado/RAS-$run-2", 'Item do pedido intacto');

// --- 4. A recepção continua comprando na loja, como antes ------------------------------------
$loja = $nav['recep']->req('GET', 'pages/marketplace/marketplace.php?categoria=Teste');
rmCheck($loja['status'] === 200 && str_contains($loja['body'], "Garrafa $run") && !preg_match('/<b>(?:Warning|Notice|Deprecated|Fatal error)<\/b>/', $loja['body']), 'Recepção abre a loja e vê o produto');
$lojaTodos = $nav['recep']->req('GET', 'pages/marketplace/marketplace.php');
$totalProdutosDisponiveis = (int) rmValor($db, "SELECT COUNT(*) FROM produtos WHERE status = 'ativo' AND estoque > 0");
$cardsTodos = preg_match_all('#<div class="mkt-card">#', $lojaTodos['body']);
$fotosTodos = preg_match_all('#<div class="mkt-card-media">\s*<img src="([^"]+)"#', $lojaTodos['body'], $fotosMkt);
rmCheck($lojaTodos['status'] === 200
    && preg_match('#<a class="mkt-tab active" href="\?categoria=todos" aria-current="page">\s*Todos\s*</a>#', $lojaTodos['body'])
    && $cardsTodos === $totalProdutosDisponiveis
    && $fotosTodos === $totalProdutosDisponiveis
    && !str_contains($lojaTodos['body'], 'mkt-no-image'),
    'Marketplace abre em Todos e mostra foto para cada produto disponível');
$lojaTodosExplicito = $nav['recep']->req('GET', 'pages/marketplace/marketplace.php?categoria=todos');
rmCheck(preg_match_all('#<div class="mkt-card">#', $lojaTodosExplicito['body']) === $totalProdutosDisponiveis, 'Filtro Todos inclui todos os produtos disponíveis');
$estoqueAntes = (int) rmValor($db, "SELECT estoque FROM produtos WHERE id_produto = $produtoCb");
$r = $nav['recep']->req('POST', 'pages/marketplace/marketplace.php', ['csrf_token' => $csrf, 'form_acao' => 'add_carrinho', 'produto_id' => (string) $produtoCb, 'categoria' => 'Teste']);
rmCheck($r['status'] === 302, 'Recepção adiciona o produto ao carrinho');
$r = $nav['recep']->req('POST', 'pages/dashboard/funcionalidades/enderecos.php', ['csrf_token' => $csrf, 'acao' => 'create', 'apelido' => 'Recepção', 'cep' => '11010-000',
    'logradouro' => "Rua Recepcao $run", 'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'Santos', 'uf' => 'SP']);
rmCheck($r['status'] === 302 && (int) rmValor($db, "SELECT COUNT(*) FROM enderecos_entrega WHERE id_usuario = {$recep['id']}") === 1, 'Recepção cadastra o endereço de entrega');
$carrinho = $nav['recep']->req('GET', 'pages/carrinho/carrinho.php')['body'];
rmCheck(str_contains($carrinho, "Garrafa $run") && preg_match('/name="checkout_token" value="([a-f0-9]{64})"/', $carrinho, $m) === 1, 'Carrinho com o produto e pronto para finalizar');
$r = $nav['recep']->req('POST', 'pages/carrinho/carrinho.php', ['csrf_token' => $csrf, 'acao' => 'finalizar', 'checkout_token' => $m[1], 'forma_pagamento' => 'pix', 'cashback_usado' => '0']);
$pedidoRecep = (int) rmValor($db, "SELECT MAX(id_pedido) FROM pedido WHERE id_usuario = {$recep['id']}");
rmCheck($r['status'] === 302 && str_contains($r['location'], 'sucesso=1') && $pedidoRecep > 0, 'Recepção finaliza a compra (' . $r['location'] . ')');
rmCheck((int) rmValor($db, "SELECT estoque FROM produtos WHERE id_produto = $produtoCb") === $estoqueAntes - 1
    && rmValor($db, "SELECT valor FROM cashback WHERE id_usuario = {$recep['id']} AND descricao = 'Cashback do pedido #$pedidoRecep'") === '3.00', 'Compra da recepção: estoque baixado e cashback concedido, como no checkout de sempre');
$mkt = rmSecao($painel('recep'), 'recepcionista', 'marketplace');
rmCheck(str_contains($mkt, 'data-bo-pedido="' . $pedidoRecep . '"'), 'A compra aparece na consulta de pedidos');

echo "Cashback dos clientes e Marketplace da recepção via HTTP: $checks verificações passaram.\n";
