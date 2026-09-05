<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/pages/dashboard/includes/frete.php');

session_start();

// Finalizar a compra grava pedido/pagamento/cashback reais no banco, então
// exige usuário autenticado (id_usuario vem da sessão de login).
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . 'pages/login/login.php');
    exit;
}
$idUsuarioLogado = (int) $_SESSION['id_usuario'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function cart_csrf_valido(): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''));
}

function cart_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
}

function cart_money($v)
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

/**
 * Agrupa os itens do carrinho por vendedor (id_vendedor NULL = produto
 * legado "ONE FIT", tratado como grupo 0) e aplica a transportadora
 * escolhida (tipo de entrega) a cada grupo. Se nenhuma transportadora foi
 * escolhida ainda (ou a escolhida não cobre mais o CEP), cai para a opção
 * mais barata disponível. Retorna null quando não há nenhuma transportadora
 * que cubra o CEP informado.
 */
function cart_calcular_fretes(mysqli $conn, array $itensPorVendedor, string $cep, ?int $idTransportadoraEscolhida = null): ?array
{
    $opcoes = bo_listar_opcoes_frete($conn, $cep);
    if (empty($opcoes)) {
        return null;
    }

    $opcaoEscolhida = $opcoes[0];
    if ($idTransportadoraEscolhida !== null) {
        foreach ($opcoes as $opcao) {
            if ($opcao['id_transportadora'] === $idTransportadoraEscolhida) {
                $opcaoEscolhida = $opcao;
                break;
            }
        }
    }

    $fretes = [];
    $totalFrete = 0.0;
    foreach ($itensPorVendedor as $idVendedor => $dados) {
        $fretes[$idVendedor] = $opcaoEscolhida + ['vendedorNome' => $dados['nome']];
        $totalFrete += $opcaoEscolhida['valor_frete'];
    }
    return ['porVendedor' => $fretes, 'total' => round($totalFrete, 2), 'opcoes' => $opcoes, 'escolhida' => $opcaoEscolhida['id_transportadora']];
}

/**
 * Processa o checkout: revalida os itens do carrinho contra o banco (nunca
 * confia em valores vindos do POST), grava pedido + itens + baixa de
 * estoque + lançamentos de cashback (uso e ganho) em uma transação, limpa
 * o carrinho da sessão e redireciona. Sempre termina o script (redirect + exit).
 */
function cart_finalizar_compra(mysqli $conn, int $idUsuario, array $post): void
{
    if (empty($_SESSION['carrinho'])) {
        header('Location: carrinho.php');
        exit;
    }

    // Endereço de entrega: precisa ter sido escolhido/salvo antes (etapa
    // "checkout-endereco") e pertencer ao próprio usuário — nunca confia
    // em id_endereco_entrega vindo direto do POST.
    $idEndereco = (int) ($_SESSION['checkout_endereco_id'] ?? 0);
    $stmtEndereco = $conn->prepare('SELECT * FROM enderecos_entrega WHERE id_endereco = ? AND id_usuario = ?');
    $stmtEndereco->bind_param('ii', $idEndereco, $idUsuario);
    $stmtEndereco->execute();
    $endereco = $stmtEndereco->get_result()->fetch_assoc();
    $stmtEndereco->close();
    if (!$endereco) {
        header('Location: carrinho.php?erro=endereco');
        exit;
    }

    $formaPagamento = in_array($post['forma_pagamento'] ?? '', ['pix', 'cartao'], true)
        ? $post['forma_pagamento']
        : 'pix';

    // Saldo real de cashback do usuário (créditos - débitos, ignorando cancelados).
    $stmtSaldo = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmtSaldo->bind_param('i', $idUsuario);
    $stmtSaldo->execute();
    $saldoAtual = (float) ($stmtSaldo->get_result()->fetch_assoc()['saldo'] ?? 0);
    $stmtSaldo->close();

    $ids = array_map('intval', array_keys($_SESSION['carrinho']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $conn->begin_transaction();
    try {
        // SELECT ... FOR UPDATE dentro da transação: trava as linhas dos produtos
        // do carrinho até o commit/rollback, evitando que duas finalizações
        // concorrentes vendam mais unidades do que o estoque realmente permite.
        $stmt = $conn->prepare("SELECT id_produto, id_vendedor, preco, desconto, cashback_valor, estoque, status FROM produtos WHERE id_produto IN ($placeholders) FOR UPDATE");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $produtosBanco = [];
        while ($row = $res->fetch_assoc()) {
            $produtosBanco[(int) $row['id_produto']] = $row;
        }
        $stmt->close();

        $itens = [];
        $totalCompra = 0.0;
        $cashbackGanho = 0.0;
        // Agrupado por vendedor (chave 0 = produto legado "ONE FIT", sem dono)
        // para calcular o frete de cada loja separadamente.
        $itensPorVendedor = [];
        foreach ($_SESSION['carrinho'] as $produtoId => $quantidade) {
            if (!isset($produtosBanco[$produtoId]) || $produtosBanco[$produtoId]['status'] !== 'ativo') {
                continue;
            }
            $p = $produtosBanco[$produtoId];
            $quantidade = (int) $quantidade;
            // Estoque insuficiente: rejeita a compra inteira em vez de reduzir a
            // quantidade em silêncio, para o cliente não pagar/receber menos do
            // que via no carrinho nem finalizar um pedido maior que o estoque real.
            if ($quantidade <= 0 || $quantidade > (int) $p['estoque']) {
                $conn->rollback();
                header('Location: carrinho.php?erro=1');
                exit;
            }
            $valorFinal = $p['desconto'] > 0
                ? round((float) $p['preco'] * (1 - (float) $p['desconto'] / 100), 2)
                : (float) $p['preco'];
            $subtotal = round($valorFinal * $quantidade, 2);

            $cashbackUnitario = (float) $p['cashback_valor'];
            $idVendedor = (int) ($p['id_vendedor'] ?? 0);

            $itens[] = [
                'id' => $produtoId,
                'idVendedor' => $idVendedor,
                'quantidade' => $quantidade,
                'precoUnitario' => $valorFinal,
                'subtotal' => $subtotal,
                'cashbackUnitario' => $cashbackUnitario,
            ];
            $itensPorVendedor[$idVendedor]['nome'] = $idVendedor > 0 ? 'Loja' : 'ONE FIT';
            $totalCompra += $subtotal;
            // Cashback do produto é um valor fixo em R$ por unidade (não mais %).
            $cashbackGanho += round($cashbackUnitario * $quantidade, 2);
        }

        if (empty($itens)) {
            $conn->rollback();
            header('Location: carrinho.php?erro=1');
            exit;
        }

        $idTransportadoraEscolhida = isset($_SESSION['checkout_transportadora_id']) ? (int) $_SESSION['checkout_transportadora_id'] : null;
        $fretes = cart_calcular_fretes($conn, $itensPorVendedor, $endereco['cep'], $idTransportadoraEscolhida);
        if ($fretes === null) {
            $conn->rollback();
            header('Location: carrinho.php?erro=frete');
            exit;
        }
        $totalCompra = round($totalCompra + $fretes['total'], 2);

        $cashbackUsado = round(max(0, min((float) ($post['cashback_usado'] ?? 0), $saldoAtual, $totalCompra)), 2);

        $stmtPedido = $conn->prepare(
            'INSERT INTO pedido (id_usuario, id_endereco_entrega, valor_total, forma_pagamento, status, data_pedido,
                endereco_cep, endereco_logradouro, endereco_numero, endereco_complemento, endereco_bairro, endereco_cidade, endereco_uf)
             VALUES (?, ?, ?, ?, "aguardando", NOW(), ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtPedido->bind_param(
            'iidssssssss',
            $idUsuario,
            $idEndereco,
            $totalCompra,
            $formaPagamento,
            $endereco['cep'],
            $endereco['logradouro'],
            $endereco['numero'],
            $endereco['complemento'],
            $endereco['bairro'],
            $endereco['cidade'],
            $endereco['uf']
        );
        $stmtPedido->execute();
        $idPedido = (int) $conn->insert_id;
        $stmtPedido->close();

        $stmtItem = $conn->prepare(
            'INSERT INTO pedido_item (id_pedido, id_produto, id_vendedor, quantidade, preco_unitario, subtotal, id_transportadora, valor_frete)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtEstoque = $conn->prepare('UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ?');
        // O frete é cobrado uma vez por vendedor: para não duplicar o valor
        // ao somar pedido_item.valor_frete, ele é lançado só no primeiro
        // item de cada grupo de vendedor (os demais itens do mesmo grupo
        // ficam com valor_frete = 0).
        $vendedorJaCobrado = [];
        foreach ($itens as $item) {
            $idVendedorItem = $item['idVendedor'];
            $idVendedorSql = $idVendedorItem > 0 ? $idVendedorItem : null;
            $idTransportadoraItem = $fretes['porVendedor'][$idVendedorItem]['id_transportadora'];
            $valorFreteItem = 0.0;
            if (empty($vendedorJaCobrado[$idVendedorItem])) {
                $valorFreteItem = $fretes['porVendedor'][$idVendedorItem]['valor_frete'];
                $vendedorJaCobrado[$idVendedorItem] = true;
            }

            $stmtItem->bind_param(
                'iiiiddid',
                $idPedido,
                $item['id'],
                $idVendedorSql,
                $item['quantidade'],
                $item['precoUnitario'],
                $item['subtotal'],
                $idTransportadoraItem,
                $valorFreteItem
            );
            $stmtItem->execute();
            $stmtEstoque->bind_param('ii', $item['quantidade'], $item['id']);
            $stmtEstoque->execute();
        }
        $stmtItem->close();
        $stmtEstoque->close();

        if ($cashbackUsado > 0) {
            $descUso = 'Uso de cashback no pedido #' . $idPedido;
            $stmtCbUso = $conn->prepare('INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, "debito", "uso", ?, "utilizado", NOW())');
            $stmtCbUso->bind_param('ids', $idUsuario, $cashbackUsado, $descUso);
            $stmtCbUso->execute();
            $stmtCbUso->close();
        }

        if ($cashbackGanho > 0) {
            $descGanho = 'Cashback do pedido #' . $idPedido;
            $stmtCbGanho = $conn->prepare('INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, "credito", "produto", ?, "disponivel", NOW())');
            $stmtCbGanho->bind_param('ids', $idUsuario, $cashbackGanho, $descGanho);
            $stmtCbGanho->execute();
            $stmtCbGanho->close();
        }

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        header('Location: carrinho.php?erro=1');
        exit;
    }

    $_SESSION['carrinho'] = [];
    unset($_SESSION['checkout_endereco_id']);
    unset($_SESSION['checkout_transportadora_id']);
    $_SESSION['ultimo_pedido'] = [
        'id' => $idPedido,
        'total' => $totalCompra,
        'frete' => $fretes['total'],
        'cashbackUsado' => $cashbackUsado,
        'cashbackGanho' => $cashbackGanho,
        'formaPagamento' => $formaPagamento,
    ];
    header('Location: carrinho.php?sucesso=1');
    exit;
}

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

/* ===== Ações (remover / alterar quantidade) — recarrega a própria página ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && cart_csrf_valido()) {
    $produtoId = (int) ($_POST['produto_id'] ?? 0);

    switch ($_POST['acao']) {
        case 'limpar':
            $_SESSION['carrinho'] = [];
            break;
        case 'remover':
            unset($_SESSION['carrinho'][$produtoId]);
            break;

        case 'incrementar':
            if (isset($_SESSION['carrinho'][$produtoId])) {
                $stmtEstoqueAtual = $conn->prepare('SELECT estoque FROM produtos WHERE id_produto = ? LIMIT 1');
                $stmtEstoqueAtual->bind_param('i', $produtoId);
                $stmtEstoqueAtual->execute();
                $estoqueAtual = (int) ($stmtEstoqueAtual->get_result()->fetch_assoc()['estoque'] ?? 0);
                $stmtEstoqueAtual->close();
                if ($_SESSION['carrinho'][$produtoId] < $estoqueAtual) {
                    $_SESSION['carrinho'][$produtoId]++;
                } else {
                    header('Location: carrinho.php?semestoque=1');
                    exit;
                }
            }
            break;

        case 'decrementar':
            if (isset($_SESSION['carrinho'][$produtoId])) {
                $_SESSION['carrinho'][$produtoId]--;
                if ($_SESSION['carrinho'][$produtoId] <= 0) {
                    unset($_SESSION['carrinho'][$produtoId]);
                }
            }
            break;

        case 'escolher_transportadora':
            $idTransportadoraForm = (int) ($_POST['transportadora_id'] ?? 0);
            if ($idTransportadoraForm > 0) {
                $_SESSION['checkout_transportadora_id'] = $idTransportadoraForm;
            } else {
                unset($_SESSION['checkout_transportadora_id']);
            }
            break;

        case 'finalizar':
            cart_finalizar_compra($conn, $idUsuarioLogado, $_POST);
            // cart_finalizar_compra sempre redireciona (sucesso ou erro) e encerra o script.
            break;
    }

    header('Location: carrinho.php');
    exit;
}

/* ===== Carrega os produtos que estão no carrinho ===== */
$itens = [];
$totalGeral = 0.0;
$cashbackTotal = 0.0;

if (!empty($_SESSION['carrinho'])) {
    $ids = array_map('intval', array_keys($_SESSION['carrinho']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $produtosMap = [];
    try {
        $stmt = $conn->prepare("SELECT id_produto AS id, id_vendedor, nome, categoria, preco, desconto, cashback_valor AS cashback, imagem FROM produtos WHERE id_produto IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $produtosMap[(int) $row['id']] = $row;
        }
        $stmt->close();
    } catch (\mysqli_sql_exception $e) {
        $produtosMap = [];
    }

    foreach ($_SESSION['carrinho'] as $produtoId => $quantidade) {
        if (!isset($produtosMap[$produtoId])) {
            continue;
        }
        $p = $produtosMap[$produtoId];
        $preco = (float) $p['preco'];
        $desconto = (float) $p['desconto'];
        $cashback = (float) $p['cashback'];
        $valorFinal = $desconto > 0 ? round($preco * (1 - $desconto / 100), 2) : $preco;
        $subtotal = round($valorFinal * $quantidade, 2);
        // Cashback do produto é um valor fixo em R$ por unidade (não mais %).
        $cashbackItem = round($cashback * $quantidade, 2);
        $idVendedorItem = (int) ($p['id_vendedor'] ?? 0);

        $totalGeral += $subtotal;
        $cashbackTotal += $cashbackItem;

        $itens[] = [
            'id' => $produtoId,
            'idVendedor' => $idVendedorItem,
            'nome' => $p['nome'],
            'categoria' => $p['categoria'],
            'imagem' => $p['imagem'],
            'quantidade' => $quantidade,
            'valorFinal' => $valorFinal,
            'subtotal' => $subtotal,
            'cashbackItem' => $cashbackItem,
        ];
    }
}

/* ===== Endereços de entrega salvos pelo usuário ===== */
$enderecosUsuario = [];
$stmtEnd = $conn->prepare('SELECT * FROM enderecos_entrega WHERE id_usuario = ? ORDER BY principal DESC, data_cadastro DESC');
$stmtEnd->bind_param('i', $idUsuarioLogado);
$stmtEnd->execute();
$resEnd = $stmtEnd->get_result();
while ($row = $resEnd->fetch_assoc()) {
    $enderecosUsuario[(int) $row['id_endereco']] = $row;
}
$stmtEnd->close();

// Se nada foi escolhido ainda nesta sessão, usa o endereço principal como
// sugestão inicial (o usuário ainda pode trocar antes de finalizar).
if (!isset($_SESSION['checkout_endereco_id']) || !isset($enderecosUsuario[(int) $_SESSION['checkout_endereco_id']])) {
    foreach ($enderecosUsuario as $end) {
        if ((int) $end['principal'] === 1) {
            $_SESSION['checkout_endereco_id'] = (int) $end['id_endereco'];
            break;
        }
    }
}
$enderecoSelecionadoId = (int) ($_SESSION['checkout_endereco_id'] ?? 0);
$enderecoSelecionado = $enderecosUsuario[$enderecoSelecionadoId] ?? null;

/* ===== Frete: agrupa os itens do carrinho por vendedor e aplica a
   transportadora (tipo de entrega) escolhida pelo cliente para o CEP do
   endereço selecionado — ou a mais barata, se ele ainda não escolheu. ===== */
$freteInfo = null;
$freteIndisponivel = false;
if ($enderecoSelecionado && !empty($itens)) {
    $itensPorVendedorPreview = [];
    foreach ($itens as $item) {
        $itensPorVendedorPreview[$item['idVendedor']]['nome'] = $item['idVendedor'] > 0 ? 'Loja' : 'ONE FIT';
    }
    $idTransportadoraEscolhidaPreview = isset($_SESSION['checkout_transportadora_id']) ? (int) $_SESSION['checkout_transportadora_id'] : null;
    $freteInfo = cart_calcular_fretes($conn, $itensPorVendedorPreview, $enderecoSelecionado['cep'], $idTransportadoraEscolhidaPreview);
    $freteIndisponivel = $freteInfo === null;
}
$valorFrete = $freteInfo['total'] ?? 0.0;
$totalComFrete = round($totalGeral + $valorFrete, 2);

/* ===== Saldo real de cashback do usuário (créditos - débitos, exceto cancelados) ===== */
$saldoCashback = 0.0;
$stmtSaldoCashback = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
$stmtSaldoCashback->bind_param('i', $idUsuarioLogado);
$stmtSaldoCashback->execute();
$saldoCashback = (float) ($stmtSaldoCashback->get_result()->fetch_assoc()['saldo'] ?? 0);
$stmtSaldoCashback->close();
$saldoCashback = max(0.0, $saldoCashback);
$cashbackMaximoUsavel = round(min($saldoCashback, $totalComFrete), 2);

/* ===== Mensagens vindas do redirecionamento após finalizar a compra ===== */
$pedidoConcluido = null;
if (isset($_GET['sucesso']) && !empty($_SESSION['ultimo_pedido'])) {
    $pedidoConcluido = $_SESSION['ultimo_pedido'];
    unset($_SESSION['ultimo_pedido']);
}
$erroFinalizar = isset($_GET['erro']) && $_GET['erro'] === '1';
$erroSemEndereco = isset($_GET['erro']) && $_GET['erro'] === 'endereco';
$erroSemFrete = isset($_GET['erro']) && $_GET['erro'] === 'frete';
$erroSemEstoque = isset($_GET['semestoque']);

/* Tema (dark/light) escolhido no dashboard, persistido em cookie por assets/js/dashboard.js. */
$cartTema = ($_COOKIE['onefit_theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?php echo $cartTema; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho · ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/carrinho.css">
    <style>
        /* Os inputs de forma de pagamento agora são radios reais (funcionam sem JS);
           o rótulo (label) continua com a aparência de aba já existente. */
        .payment-radio { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
        .payment-radio:checked + .payment-tab { background: #ffc400; color: #17130b; }
        .payment-radio:focus-visible + .payment-tab { outline: 2px solid #ffc400; outline-offset: -2px; }
    </style>
</head>

<body>

    <header class="crt-header">
        <div class="crt-logo">
            <img src="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" alt="Logo One Fit">
            <span>One Fit · Carrinho</span>
        </div>

        <a class="crt-icon-btn" href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" aria-label="Voltar ao marketplace" title="Voltar ao marketplace">
            <i class="bi bi-arrow-left"></i>
        </a>
    </header>

    <main class="crt-main">

        <div class="crt-page-title">
            <h1>Seu carrinho</h1>
        </div>

        <?php if ($erroSemEstoque): ?>
            <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Sem estoque suficiente para adicionar mais unidades deste produto.</div>
        <?php endif; ?>

        <?php if ($pedidoConcluido): ?>
            <div class="crt-empty">
                <i class="bi bi-check-circle"></i>
                <p class="mb-0">Pedido #<?php echo (int) $pedidoConcluido['id']; ?> realizado com sucesso!</p>
                <p class="mb-0">Total pago: <?php echo cart_money($pedidoConcluido['total']); ?><?php if ($pedidoConcluido['cashbackUsado'] > 0): ?> (<?php echo cart_money($pedidoConcluido['cashbackUsado']); ?> em cashback) <?php endif; ?></p>
                <?php if ($pedidoConcluido['cashbackGanho'] > 0): ?>
                    <p class="mb-0">Você ganhou <?php echo cart_money($pedidoConcluido['cashbackGanho']); ?> de cashback nesta compra.</p>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" class="btn-crt-outline">
                    <i class="bi bi-shop"></i> Continuar comprando
                </a>
            </div>
        <?php elseif (empty($itens)): ?>
            <div class="crt-empty">
                <i class="bi bi-cart-x"></i>
                <p class="mb-0">Seu carrinho está vazio.</p>
                <a href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" class="btn-crt-outline">
                    <i class="bi bi-shop"></i> Ir para o Marketplace
                </a>
            </div>
        <?php else: ?>

            <div class="crt-layout">
                <section class="crt-products">
            <?php foreach ($itens as $item): ?>
                <div class="crt-item">
                    <div class="crt-thumb">
                        <?php if (!empty($item['imagem'])): ?>
                            <img src="<?php echo htmlspecialchars($item['imagem']); ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>">
                        <?php else: ?>
                            <i class="bi bi-image"></i>
                        <?php endif; ?>
                    </div>

                    <div class="crt-info">
                        <div class="crt-categoria"><?php echo htmlspecialchars($item['categoria']); ?></div>
                        <div class="crt-nome"><?php echo htmlspecialchars($item['nome']); ?></div>
                        <div class="crt-cashback">
                            <i class="bi bi-coin"></i> Cashback: <?php echo cart_money($item['cashbackItem']); ?>
                        </div>
                    </div>

                    <div class="crt-qty">
                        <form method="POST" action="carrinho.php">
                            <?php echo cart_csrf_field(); ?>
                            <input type="hidden" name="acao" value="decrementar">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="crt-qty-btn" aria-label="Diminuir quantidade">−</button>
                        </form>
                        <span class="crt-qty-value"><?php echo (int) $item['quantidade']; ?></span>
                        <form method="POST" action="carrinho.php">
                            <?php echo cart_csrf_field(); ?>
                            <input type="hidden" name="acao" value="incrementar">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="crt-qty-btn" aria-label="Aumentar quantidade">+</button>
                        </form>
                    </div>

                    <div class="crt-subtotal"><?php echo cart_money($item['subtotal']); ?></div>

                    <form method="POST" action="carrinho.php">
                        <?php echo cart_csrf_field(); ?>
                        <input type="hidden" name="acao" value="remover">
                        <input type="hidden" name="produto_id" value="<?php echo (int) $item['id']; ?>">
                        <button type="submit" class="crt-remove-btn" aria-label="Remover item" title="Remover item">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
                </section>

                <section class="checkout-access-card" aria-labelledby="checkout-access-title">
                    <div>
                        <span class="checkout-access-kicker">Checkout seguro</span>
                        <h2 id="checkout-access-title">Revise e finalize sua compra</h2>
                        <p>Abra cada etapa quando precisar: resumo, endereço, cashback ou pagamento.</p>
                    </div>
                    <div class="checkout-access-actions">
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-endereco"><i class="bi bi-geo-alt"></i> Endereço de entrega</button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-cashback"><i class="bi bi-coin"></i> Usar meu cashback</button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-pagamento"<?php echo ($freteIndisponivel || !$enderecoSelecionado) ? ' disabled' : ''; ?>><i class="bi bi-credit-card"></i> Forma de pagamento</button>
                    </div>
                    <button type="button" class="checkout-open-primary" data-open-checkout="checkout-resumo"><i class="bi bi-bag-check"></i> Abrir checkout</button>
                </section>

            <aside class="crt-checkout" id="checkout-panel" aria-hidden="true" aria-label="Checkout">
            <div class="checkout-panel-header"><strong>Checkout ONE FIT</strong><button class="checkout-close" type="button" aria-label="Fechar checkout"><i class="bi bi-x-lg"></i></button></div>

            <?php if ($erroFinalizar): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Não foi possível finalizar a compra (produto sem estoque ou indisponível). Revise o carrinho e tente novamente.</div>
            <?php endif; ?>
            <?php if ($erroSemEndereco): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Escolha um endereço de entrega antes de finalizar a compra.</div>
            <?php endif; ?>
            <?php if ($erroSemFrete): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Não entregamos no CEP do endereço selecionado. Tente outro endereço.</div>
            <?php endif; ?>

            <!--
                Formulário real de checkout: processado 100% em PHP (ação "finalizar" no topo
                deste arquivo). Este <form> só contém os campos fixos (csrf/acao) e é fechado
                imediatamente: os passos abaixo (endereço, cashback, pagamento) NÃO ficam
                aninhados dentro dele, porque o passo "endereço" já tem seus próprios <form>
                (selecionar/excluir endereço) e HTML não permite <form> dentro de <form> — um
                <form> aninhado fecha o <form> externo mais cedo, deixando o botão "Finalizar
                compra" fora de qualquer formulário (por isso ele não funcionava). Os campos que
                precisam ser enviados junto com o "finalizar" (cashback, forma de pagamento,
                botão) usam o atributo form="checkout-form" para continuar associados a este
                formulário mesmo estando fora dele.
            -->
            <form method="POST" action="carrinho.php" id="checkout-form">
            <?php echo cart_csrf_field(); ?>
            <input type="hidden" name="acao" value="finalizar">
            </form>

            <div class="crt-summary checkout-card checkout-step is-active" id="checkout-resumo">
                <h2 class="checkout-title">Resumo da compra</h2>
                <div class="crt-summary-row">
                    <span>Subtotal</span>
                    <span><?php echo cart_money($totalGeral); ?></span>
                </div>
                <div class="crt-summary-row">
                    <span>Frete</span>
                    <span id="resumo-frete"><?php echo $freteInfo ? cart_money($valorFrete) : 'Escolha um endereço'; ?></span>
                </div>
                <div class="crt-summary-row">
                    <span>Cashback a receber</span>
                    <span class="cashback-valor" id="resumo-cashback"><?php echo cart_money($cashbackTotal); ?></span>
                </div>
                <div class="crt-summary-row total">
                    <span>Total</span>
                    <span id="total-final"><?php echo cart_money($totalComFrete); ?></span>
                </div>

                <button type="button" class="btn-crt-gold" data-open-checkout="checkout-endereco">
                    <i class="bi bi-geo-alt"></i> Ir para endereço de entrega
                </button>
            </div>

            <div class="checkout-card checkout-step" id="checkout-endereco">
                <h2 class="checkout-title">Endereço de entrega</h2>

                <?php if (empty($enderecosUsuario)): ?>
                    <p class="cashback-remaining">Você ainda não tem nenhum endereço salvo.</p>
                <?php else: ?>
                    <?php foreach ($enderecosUsuario as $end): ?>
                        <div class="crt-endereco-card<?php echo $enderecoSelecionadoId === (int) $end['id_endereco'] ? ' is-selected' : ''; ?>">
                            <div class="crt-endereco-info">
                                <strong><?php echo htmlspecialchars($end['apelido'] ?: ($end['logradouro'] . ', ' . $end['numero'])); ?></strong>
                                <?php if ((int) $end['principal'] === 1): ?><span class="crt-endereco-badge">Principal</span><?php endif; ?>
                                <p class="mb-0"><?php echo htmlspecialchars($end['logradouro'] . ', ' . $end['numero'] . ($end['complemento'] ? ' - ' . $end['complemento'] : '')); ?></p>
                                <p class="mb-0"><?php echo htmlspecialchars($end['bairro'] . ' - ' . $end['cidade'] . '/' . $end['uf'] . ' - CEP ' . $end['cep']); ?></p>
                            </div>
                            <div class="crt-endereco-actions">
                                <?php if ($enderecoSelecionadoId === (int) $end['id_endereco']): ?>
                                    <span class="crt-endereco-badge">Selecionado para entrega</span>
                                <?php else: ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/enderecos.php">
                                        <?php echo cart_csrf_field(); ?>
                                        <input type="hidden" name="acao" value="selecionar">
                                        <input type="hidden" name="id" value="<?php echo (int) $end['id_endereco']; ?>">
                                        <button type="submit" class="btn-crt-outline">Usar este endereço</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/enderecos.php">
                                    <?php echo cart_csrf_field(); ?>
                                    <input type="hidden" name="acao" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $end['id_endereco']; ?>">
                                    <button type="submit" class="crt-remove-btn" aria-label="Excluir endereço" title="Excluir endereço"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <button type="button" class="btn-crt-outline" data-bs-toggle="modal" data-bs-target="#modalEnderecoNovo">
                    <i class="bi bi-plus-circle"></i> Adicionar novo endereço
                </button>

                <?php if ($freteIndisponivel): ?>
                    <p class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Não entregamos no CEP deste endereço.</p>
                <?php elseif ($freteInfo): ?>
                    <h3 class="checkout-subtitle">Tipo de entrega</h3>
                    <form method="POST" action="carrinho.php" class="crt-frete-opcoes">
                        <?php echo cart_csrf_field(); ?>
                        <input type="hidden" name="acao" value="escolher_transportadora">
                        <?php foreach ($freteInfo['opcoes'] as $opcao): ?>
                            <label class="crt-frete-opcao<?php echo $freteInfo['escolhida'] === $opcao['id_transportadora'] ? ' is-selected' : ''; ?>">
                                <input type="radio" name="transportadora_id" value="<?php echo (int) $opcao['id_transportadora']; ?>" <?php echo $freteInfo['escolhida'] === $opcao['id_transportadora'] ? 'checked' : ''; ?>>
                                <span class="crt-frete-opcao-nome"><?php echo htmlspecialchars($opcao['nome']); ?> <small>(<?php echo htmlspecialchars(ucfirst($opcao['tipo'])); ?>)</small></span>
                                <span class="crt-frete-opcao-prazo"><?php echo (int) $opcao['prazo_dias']; ?> dia(s)</span>
                                <span class="crt-frete-opcao-valor"><?php echo cart_money($opcao['valor_frete']); ?></span>
                            </label>
                        <?php endforeach; ?>
                        <button type="submit" class="btn-crt-outline">Usar esta forma de entrega</button>
                    </form>
                <?php endif; ?>

                <button type="button" class="btn-crt-gold" data-open-checkout="checkout-pagamento"<?php echo ($freteIndisponivel || !$enderecoSelecionado) ? ' disabled' : ''; ?>>Continuar para pagamento <i class="bi bi-arrow-right"></i></button>
            </div>

            <div class="checkout-card checkout-step" id="checkout-cashback">
                <h2 class="checkout-title">Usar meu cashback</h2>
                <div class="cashback-disponivel"><span>Disponível</span><strong><?php echo cart_money($saldoCashback); ?></strong></div>
                <div class="cashback-disponivel"><span>Máximo permitido nesta compra: <?php echo cart_money($cashbackMaximoUsavel); ?></span></div>
                <input id="cashback-range" class="cashback-range" type="range" name="cashback_usado" form="checkout-form"
                    min="0" max="<?php echo $cashbackMaximoUsavel; ?>" value="0" step="0.01"
                    aria-label="Cashback a utilizar">
                <div class="cashback-actions">
                    <button type="button" class="cashback-action" data-cashback="<?php echo $cashbackMaximoUsavel; ?>">Usar máximo</button>
                    <button type="button" class="cashback-action" data-cashback="0">Não usar</button>
                </div>
                <div class="cashback-aplicado"><span>Aplicado</span><span id="cashback-aplicado">R$ 0,00</span></div>
                <p class="cashback-remaining">Restante para pagamento: <strong id="cashback-restante"><?php echo cart_money($totalComFrete); ?></strong></p>
                <button type="button" class="cashback-continue" data-open-checkout="checkout-pagamento">Continuar para pagamento <i class="bi bi-arrow-right"></i></button>
            </div>
            <div class="checkout-card checkout-step" id="checkout-pagamento">
                <div class="payment-heading"><h2 class="checkout-title">Forma de pagamento</h2></div>
                <?php if (!$enderecoSelecionado): ?>
                    <p class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Escolha um endereço de entrega antes de finalizar.</p>
                <?php endif; ?>
                <div class="payment-tabs">
                    <input type="radio" class="payment-radio" name="forma_pagamento" form="checkout-form" id="payPix" value="pix" checked>
                    <label class="payment-tab" for="payPix"><i class="bi bi-qr-code"></i> PIX</label>
                    <input type="radio" class="payment-radio" name="forma_pagamento" form="checkout-form" id="payCartao" value="cartao">
                    <label class="payment-tab" for="payCartao"><i class="bi bi-credit-card"></i> Cartão</label>
                </div>
                <div id="pix-payment"><label class="pix-label">Valor a pagar no PIX</label><div class="pix-key"><span id="pix-value"><?php echo cart_money($totalComFrete); ?></span></div><label class="pix-label">CHAVE PIX</label><div class="pix-key"><span>onefit@pagamentos.com</span><button type="button" id="copy-pix" class="copy-key">Copiar chave PIX</button></div></div>
                <div id="card-payment" class="card-payment">
                    <label class="pix-label">Dados do cartão (simulação)</label>
                    <input class="payment-input" type="text" inputmode="numeric" maxlength="19" placeholder="Número do cartão" name="cartao_numero" form="checkout-form">
                    <input class="payment-input" type="text" placeholder="Nome impresso no cartão" name="cartao_nome" form="checkout-form">
                    <div class="card-payment-row">
                        <input class="payment-input" type="text" inputmode="numeric" maxlength="5" placeholder="Validade (MM/AA)" name="cartao_validade" form="checkout-form">
                        <input class="payment-input" type="text" inputmode="numeric" maxlength="4" placeholder="CVV" name="cartao_cvv" form="checkout-form">
                    </div>
                </div>
                <div class="payment-summary"><div><span>Total da compra</span><strong id="payment-total"><?php echo cart_money($totalComFrete); ?></strong></div><div><span>Cashback aplicado</span><strong id="payment-cashback">R$ 0,00</strong></div><div><span>Restante via <span id="payment-method-name">PIX</span></span><strong id="payment-remaining"><?php echo cart_money($totalComFrete); ?></strong></div></div>

                <button type="submit" form="checkout-form" class="checkout-finish">Finalizar compra</button>
            </div>

            <form method="POST" action="carrinho.php"><?php echo cart_csrf_field(); ?><input type="hidden" name="acao" value="limpar"><button type="submit" class="checkout-clear">Limpar carrinho</button></form>
            </aside>
            <div class="checkout-backdrop" data-close-checkout></div>
            </div>

            <div class="modal fade crt-modal" id="modalEnderecoNovo" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Novo endereço de entrega</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/enderecos.php">
                            <div class="modal-body row g-3">
                                <?php echo cart_csrf_field(); ?>
                                <input type="hidden" name="acao" value="create">
                                <div class="col-12">
                                    <label class="form-label">Apelido (opcional)</label>
                                    <input type="text" class="form-control" name="apelido" placeholder="Casa, trabalho...">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">CEP</label>
                                    <input type="text" class="form-control" name="cep" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Logradouro</label>
                                    <input type="text" class="form-control" name="logradouro" required>
                                </div>
                                <div class="col-2">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" name="numero" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Complemento</label>
                                    <input type="text" class="form-control" name="complemento">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Bairro</label>
                                    <input type="text" class="form-control" name="bairro" required>
                                </div>
                                <div class="col-8">
                                    <label class="form-label">Cidade</label>
                                    <input type="text" class="form-control" name="cidade" required>
                                </div>
                                <div class="col-4">
                                    <label class="form-label">UF</label>
                                    <input type="text" class="form-control" name="uf" maxlength="2" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-crt-outline" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn-crt-gold">Salvar endereço</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const total = <?php echo json_encode($totalComFrete); ?>;
            const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
            const range = document.getElementById('cashback-range');
            const checkoutPanel = document.getElementById('checkout-panel');
            const openCheckout = targetId => {
                const target = document.getElementById(targetId) || document.getElementById('checkout-resumo');
                document.querySelectorAll('.checkout-step').forEach(step => step.classList.toggle('is-active', step === target));
                checkoutPanel.classList.add('is-open');
                checkoutPanel.setAttribute('aria-hidden', 'false');
                document.body.classList.add('checkout-open');
                checkoutPanel.scrollTo({ top: 0, behavior: 'auto' });
            };
            const closeCheckout = () => {
                checkoutPanel.classList.remove('is-open');
                checkoutPanel.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('checkout-open');
            };
            document.querySelectorAll('[data-open-checkout]').forEach(button => button.addEventListener('click', () => openCheckout(button.dataset.openCheckout)));
            document.querySelector('.checkout-close').addEventListener('click', closeCheckout);
            document.querySelector('[data-close-checkout]').addEventListener('click', closeCheckout);
            document.addEventListener('keydown', event => { if (event.key === 'Escape') closeCheckout(); });
            if (!range) return;
            const update = () => {
                const used = Math.min(Number(range.value), total), due = Math.max(0, total - used);
                document.getElementById('cashback-aplicado').textContent = money(used);
                document.getElementById('cashback-restante').textContent = money(due);
                document.getElementById('pix-value').textContent = money(due);
                document.getElementById('payment-total').textContent = money(total);
                document.getElementById('payment-cashback').textContent = money(used);
                document.getElementById('payment-remaining').textContent = money(due);
            };
            range.addEventListener('input', update);
            document.querySelectorAll('[data-cashback]').forEach(button => button.addEventListener('click', () => { range.value = button.dataset.cashback; update(); }));

            // Seleção de pagamento: os "botões" agora são <label for="..."> ligados a
            // <input type="radio">, então já funcionam nativamente (sem JS). O trecho
            // abaixo só atualiza o texto/painel de apoio quando o JS está disponível.
            document.querySelectorAll('.payment-radio').forEach(radio => radio.addEventListener('change', () => {
                const pix = radio.value === 'pix';
                if (!radio.checked) return;
                document.getElementById('pix-payment').style.display = pix ? 'block' : 'none';
                document.getElementById('card-payment').classList.toggle('show', !pix);
                document.getElementById('payment-method-name').textContent = pix ? 'PIX' : 'cartão';
            }));

            const copyPixBtn = document.getElementById('copy-pix');
            if (copyPixBtn) {
                copyPixBtn.addEventListener('click', async () => {
                    try { await navigator.clipboard.writeText('onefit@pagamentos.com'); copyPixBtn.textContent = 'Chave copiada!'; } catch (e) { copyPixBtn.textContent = 'onefit@pagamentos.com'; }
                });
            }
        })();
    </script>
</body>

</html>
