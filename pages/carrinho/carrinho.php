<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

session_start();

// Finalizar a compra grava pedido/pagamento/cashback reais no banco, então
// exige usuário autenticado (id_usuario vem da sessão de login).
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . 'pages/login/login.php');
    exit;
}
$idUsuarioLogado = (int) $_SESSION['id_usuario'];

function cart_money($v)
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
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

    $formaPagamento = in_array($post['forma_pagamento'] ?? '', ['pix', 'cartao'], true)
        ? $post['forma_pagamento']
        : 'pix';

    // Recarrega os produtos do carrinho direto do banco (preço/estoque/status atuais).
    $ids = array_map('intval', array_keys($_SESSION['carrinho']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("SELECT id_produto, preco, desconto, cashback_percentual, estoque, status FROM produtos WHERE id_produto IN ($placeholders)");
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
    foreach ($_SESSION['carrinho'] as $produtoId => $quantidade) {
        if (!isset($produtosBanco[$produtoId]) || $produtosBanco[$produtoId]['status'] !== 'ativo') {
            continue;
        }
        $p = $produtosBanco[$produtoId];
        $quantidade = min((int) $quantidade, (int) $p['estoque']);
        if ($quantidade <= 0) {
            continue;
        }
        $valorFinal = $p['desconto'] > 0
            ? round((float) $p['preco'] * (1 - (float) $p['desconto'] / 100), 2)
            : (float) $p['preco'];
        $subtotal = round($valorFinal * $quantidade, 2);

        $itens[] = [
            'id' => $produtoId,
            'quantidade' => $quantidade,
            'precoUnitario' => $valorFinal,
            'subtotal' => $subtotal,
            'cashbackPercentual' => (float) $p['cashback_percentual'],
        ];
        $totalCompra += $subtotal;
        $cashbackGanho += round($subtotal * ((float) $p['cashback_percentual']) / 100, 2);
    }

    if (empty($itens)) {
        header('Location: carrinho.php?erro=1');
        exit;
    }

    // Saldo real de cashback do usuário (créditos - débitos, ignorando cancelados).
    $stmtSaldo = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmtSaldo->bind_param('i', $idUsuario);
    $stmtSaldo->execute();
    $saldoAtual = (float) ($stmtSaldo->get_result()->fetch_assoc()['saldo'] ?? 0);
    $stmtSaldo->close();

    $cashbackUsado = round(max(0, min((float) ($post['cashback_usado'] ?? 0), $saldoAtual, $totalCompra)), 2);

    $conn->begin_transaction();
    try {
        $stmtPedido = $conn->prepare('INSERT INTO pedido (id_usuario, valor_total, forma_pagamento, status, data_pedido) VALUES (?, ?, ?, "aguardando", NOW())');
        $stmtPedido->bind_param('ids', $idUsuario, $totalCompra, $formaPagamento);
        $stmtPedido->execute();
        $idPedido = (int) $conn->insert_id;
        $stmtPedido->close();

        $stmtItem = $conn->prepare('INSERT INTO pedido_item (id_pedido, id_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)');
        $stmtEstoque = $conn->prepare('UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ?');
        foreach ($itens as $item) {
            $stmtItem->bind_param('iiidd', $idPedido, $item['id'], $item['quantidade'], $item['precoUnitario'], $item['subtotal']);
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
    $_SESSION['ultimo_pedido'] = [
        'id' => $idPedido,
        'total' => $totalCompra,
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
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
                $_SESSION['carrinho'][$produtoId]++;
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
        $stmt = $conn->prepare("SELECT id_produto AS id, nome, categoria, preco, desconto, cashback_percentual AS cashback, imagem FROM produtos WHERE id_produto IN ($placeholders)");
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
        $cashbackItem = round($subtotal * ($cashback / 100), 2);

        $totalGeral += $subtotal;
        $cashbackTotal += $cashbackItem;

        $itens[] = [
            'id' => $produtoId,
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

/* ===== Saldo real de cashback do usuário (créditos - débitos, exceto cancelados) ===== */
$saldoCashback = 0.0;
$stmtSaldoCashback = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
$stmtSaldoCashback->bind_param('i', $idUsuarioLogado);
$stmtSaldoCashback->execute();
$saldoCashback = (float) ($stmtSaldoCashback->get_result()->fetch_assoc()['saldo'] ?? 0);
$stmtSaldoCashback->close();
$saldoCashback = max(0.0, $saldoCashback);
$cashbackMaximoUsavel = round(min($saldoCashback, $totalGeral), 2);

/* ===== Mensagens vindas do redirecionamento após finalizar a compra ===== */
$pedidoConcluido = null;
if (isset($_GET['sucesso']) && !empty($_SESSION['ultimo_pedido'])) {
    $pedidoConcluido = $_SESSION['ultimo_pedido'];
    unset($_SESSION['ultimo_pedido']);
}
$erroFinalizar = isset($_GET['erro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

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
                            <input type="hidden" name="acao" value="decrementar">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="crt-qty-btn" aria-label="Diminuir quantidade">−</button>
                        </form>
                        <span class="crt-qty-value"><?php echo (int) $item['quantidade']; ?></span>
                        <form method="POST" action="carrinho.php">
                            <input type="hidden" name="acao" value="incrementar">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="crt-qty-btn" aria-label="Aumentar quantidade">+</button>
                        </form>
                    </div>

                    <div class="crt-subtotal"><?php echo cart_money($item['subtotal']); ?></div>

                    <form method="POST" action="carrinho.php">
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
                        <p>Abra cada etapa quando precisar: resumo, cashback ou pagamento.</p>
                    </div>
                    <div class="checkout-access-actions">
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-cashback"><i class="bi bi-coin"></i> Usar meu cashback</button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-pagamento"><i class="bi bi-credit-card"></i> Forma de pagamento</button>
                    </div>
                    <button type="button" class="checkout-open-primary" data-open-checkout="checkout-resumo"><i class="bi bi-bag-check"></i> Abrir checkout</button>
                </section>

            <aside class="crt-checkout" id="checkout-panel" aria-hidden="true" aria-label="Checkout">
            <div class="checkout-panel-header"><strong>Checkout ONE FIT</strong><button class="checkout-close" type="button" aria-label="Fechar checkout"><i class="bi bi-x-lg"></i></button></div>

            <?php if ($erroFinalizar): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Não foi possível finalizar a compra (produto sem estoque ou indisponível). Revise o carrinho e tente novamente.</div>
            <?php endif; ?>

            <!-- Formulário real de checkout: processado 100% em PHP (ação "finalizar" no topo deste arquivo) -->
            <form method="POST" action="carrinho.php" id="checkout-form">
            <input type="hidden" name="acao" value="finalizar">

            <div class="crt-summary checkout-card checkout-step is-active" id="checkout-resumo">
                <h2 class="checkout-title">Resumo da compra</h2>
                <div class="crt-summary-row">
                    <span>Subtotal</span>
                    <span><?php echo cart_money($totalGeral); ?></span>
                </div>
                <div class="crt-summary-row">
                    <span>Cashback a receber</span>
                    <span class="cashback-valor" id="resumo-cashback"><?php echo cart_money($cashbackTotal); ?></span>
                </div>
                <div class="crt-summary-row total">
                    <span>Total</span>
                    <span id="total-final"><?php echo cart_money($totalGeral); ?></span>
                </div>

                <button type="button" class="btn-crt-gold" data-open-checkout="checkout-pagamento">
                    <i class="bi bi-credit-card"></i> Ir para pagamento
                </button>
            </div>

            <div class="checkout-card checkout-step" id="checkout-cashback">
                <h2 class="checkout-title">Usar meu cashback</h2>
                <div class="cashback-disponivel"><span>Disponível</span><strong><?php echo cart_money($saldoCashback); ?></strong></div>
                <div class="cashback-disponivel"><span>Máximo permitido nesta compra: <?php echo cart_money($cashbackMaximoUsavel); ?></span></div>
                <input id="cashback-range" class="cashback-range" type="range" name="cashback_usado"
                    min="0" max="<?php echo $cashbackMaximoUsavel; ?>" value="0" step="0.01"
                    aria-label="Cashback a utilizar">
                <div class="cashback-actions">
                    <button type="button" class="cashback-action" data-cashback="<?php echo $cashbackMaximoUsavel; ?>">Usar máximo</button>
                    <button type="button" class="cashback-action" data-cashback="0">Não usar</button>
                </div>
                <div class="cashback-aplicado"><span>Aplicado</span><span id="cashback-aplicado">R$ 0,00</span></div>
                <p class="cashback-remaining">Restante para pagamento: <strong id="cashback-restante"><?php echo cart_money($totalGeral); ?></strong></p>
                <button type="button" class="cashback-continue" data-open-checkout="checkout-pagamento">Continuar para pagamento <i class="bi bi-arrow-right"></i></button>
            </div>
            <div class="checkout-card checkout-step" id="checkout-pagamento">
                <div class="payment-heading"><h2 class="checkout-title">Forma de pagamento</h2></div>
                <div class="payment-tabs">
                    <input type="radio" class="payment-radio" name="forma_pagamento" id="payPix" value="pix" checked>
                    <label class="payment-tab" for="payPix"><i class="bi bi-qr-code"></i> PIX</label>
                    <input type="radio" class="payment-radio" name="forma_pagamento" id="payCartao" value="cartao">
                    <label class="payment-tab" for="payCartao"><i class="bi bi-credit-card"></i> Cartão</label>
                </div>
                <div id="pix-payment"><label class="pix-label">Valor a pagar no PIX</label><div class="pix-key"><span id="pix-value"><?php echo cart_money($totalGeral); ?></span></div><label class="pix-label">CHAVE PIX</label><div class="pix-key"><span>onefit@pagamentos.com</span><button type="button" id="copy-pix" class="copy-key">Copiar chave PIX</button></div></div>
                <div id="card-payment" class="card-payment"><label class="pix-label">Dados do cartão (simulação)</label><input class="payment-input" type="text" placeholder="Número do cartão"><input class="payment-input" type="text" placeholder="Nome impresso no cartão"></div>
                <div class="payment-summary"><div><span>Total da compra</span><strong id="payment-total"><?php echo cart_money($totalGeral); ?></strong></div><div><span>Cashback aplicado</span><strong id="payment-cashback">R$ 0,00</strong></div><div><span>Restante via <span id="payment-method-name">PIX</span></span><strong id="payment-remaining"><?php echo cart_money($totalGeral); ?></strong></div></div>

                <button type="submit" class="checkout-finish">Finalizar compra</button>
            </div>

            </form>

            <form method="POST" action="carrinho.php"><input type="hidden" name="acao" value="limpar"><button type="submit" class="checkout-clear">Limpar carrinho</button></form>
            </aside>
            <div class="checkout-backdrop" data-close-checkout></div>
            </div>

        <?php endif; ?>

    </main>

    <script>
        (() => {
            const total = <?php echo json_encode($totalGeral); ?>;
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
