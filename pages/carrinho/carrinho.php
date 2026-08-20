<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

// Sem sessão -> manda pro login. O acesso ao backoffice inteiro depende de
// estar logado (id_usuario e tipo_usuario são gravados em processa_login.php).
// if (!isset($_SESSION['id_usuario'])) { header('Location: ' . BASE_URL . 'pages/login/login.php'); exit; }

session_start();

function cart_money($v)
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
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
    }

    header('Location: carrinho.php');
    exit;
}

/* ===== Carrega os produtos que estão no carrinho ===== */
$itens = [];
$totalGeral = 0.0;
$cashbackTotal = 0.0;
$carrinhoDemonstracao = false;

if (!empty($_SESSION['carrinho'])) {
    $ids = array_map('intval', array_keys($_SESSION['carrinho']));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $produtosMap = [];
    try {
        $stmt = $conn->prepare("SELECT id, nome, categoria, preco, desconto, cashback, imagem FROM produtos WHERE id IN ($placeholders)");
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

/* Exibe uma prévia completa do checkout enquanto não há produtos reais no carrinho. */
if (empty($itens)) {
    $carrinhoDemonstracao = true;
    $itens = [
        [
            'id' => -101,
            'nome' => 'Whey Protein Isolado 900g',
            'categoria' => 'Suplementos',
            'imagem' => '',
            'quantidade' => 1,
            'valorFinal' => 129.90,
            'subtotal' => 129.90,
            'cashbackItem' => 6.50,
        ],
        [
            'id' => -102,
            'nome' => 'Creatina Monohidratada 300g',
            'categoria' => 'Performance',
            'imagem' => '',
            'quantidade' => 1,
            'valorFinal' => 89.90,
            'subtotal' => 89.90,
            'cashbackItem' => 4.50,
        ],
        [
            'id' => -103,
            'nome' => 'Coqueteleira One Fit',
            'categoria' => 'Acessórios',
            'imagem' => '',
            'quantidade' => 1,
            'valorFinal' => 29.90,
            'subtotal' => 29.90,
            'cashbackItem' => 1.50,
        ],
    ];
    $totalGeral = 249.70;
    $cashbackTotal = 12.50;
}
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
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">0
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/carrinho.css">
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
            <?php if ($carrinhoDemonstracao): ?>
                <p class="crt-demo-note"><i class="bi bi-eye"></i> Prévia com produtos fictícios para visualização do checkout.</p>
            <?php endif; ?>
        </div>

        <?php if (empty($itens)): ?>
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
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-resumo"><i class="bi bi-receipt"></i> Resumo da compra</button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-cashback"><i class="bi bi-coin"></i> Usar meu cashback</button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-pagamento"><i class="bi bi-credit-card"></i> Forma de pagamento</button>
                    </div>
                    <button type="button" class="checkout-open-primary" data-open-checkout="checkout-resumo"><i class="bi bi-bag-check"></i> Abrir checkout</button>
                </section>

            <aside class="crt-checkout" id="checkout-panel" aria-hidden="true" aria-label="Checkout">
            <div class="checkout-panel-header"><strong>Checkout ONE FIT</strong><button class="checkout-close" type="button" aria-label="Fechar checkout"><i class="bi bi-x-lg"></i></button></div>
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

                <button type="button" class="btn-crt-gold">
                    <i class="bi bi-wallet2"></i> Finalizar compra
                </button>
            </div>

            <div class="checkout-card checkout-step" id="checkout-cashback">
                <h2 class="checkout-title">Usar meu cashback</h2>
                <div class="cashback-disponivel"><span>Disponível</span><strong>R$ 200,00</strong></div>
                <div class="cashback-disponivel"><span>Máximo permitido nesta compra: R$ 200,00</span></div>
                <input id="cashback-range" class="cashback-range" type="range" min="0" max="200" value="0" step="1" aria-label="Cashback a utilizar">
                <div class="cashback-actions"><button type="button" class="cashback-action" data-cashback="200">Usar máximo</button><button type="button" class="cashback-action" data-cashback="0">Não usar</button></div>
                <div class="cashback-aplicado"><span>Aplicado</span><span id="cashback-aplicado">R$ 0,00</span></div>
                <p class="cashback-remaining">Restante para pagamento: <strong id="cashback-restante"><?php echo cart_money($totalGeral); ?></strong></p>
                <button type="button" class="cashback-continue" data-open-checkout="checkout-pagamento">Continuar para pagamento <i class="bi bi-arrow-right"></i></button>
            </div>
            <div class="checkout-card checkout-step" id="checkout-pagamento">
                <div class="payment-heading"><h2 class="checkout-title">Forma de pagamento</h2><small>SPLIT HABILITADO</small></div>
                <div class="payment-tabs payment-tabs--three"><button class="payment-tab active" type="button" data-payment="pix"><i class="bi bi-qr-code"></i> PIX</button><button class="payment-tab" type="button" data-payment="card"><i class="bi bi-credit-card"></i> Crédito</button><button class="payment-tab" type="button" data-payment="debit"><i class="bi bi-credit-card-2-front"></i> Débito</button></div>
                <div id="pix-payment"><label class="pix-label">Valor a pagar no PIX</label><div class="pix-key"><span id="pix-value"><?php echo cart_money($totalGeral); ?></span></div><label class="pix-label">CHAVE PIX</label><div class="pix-key"><span>onefit@pagamentos.com</span><button type="button" id="copy-pix" class="copy-key">Copiar chave PIX</button></div><div class="payment-features"><div class="payment-feature">⚡ PIX automático</div><div class="payment-feature">⚡ Crédito automático</div></div></div>
                <div id="card-payment" class="card-payment"><label class="pix-label">Dados do cartão de crédito</label><input class="payment-input" type="text" placeholder="Número do cartão"><input class="payment-input" type="text" placeholder="Nome impresso no cartão"></div>
                <div id="debit-payment" class="card-payment"><label class="pix-label">Dados do cartão de débito</label><input class="payment-input" type="text" placeholder="Número do cartão"><input class="payment-input" type="text" placeholder="Nome impresso no cartão"></div>
                <div class="payment-summary"><div><span>Total da compra</span><strong id="payment-total"><?php echo cart_money($totalGeral); ?></strong></div><div><span>Cashback aplicado</span><strong id="payment-cashback">R$ 0,00</strong></div><div><span>Restante via <span id="payment-method-name">PIX</span></span><strong id="payment-remaining"><?php echo cart_money($totalGeral); ?></strong></div><div><span>Total pago (cashback + pagamento)</span><strong id="payment-split"><?php echo cart_money($totalGeral); ?></strong></div></div>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> Pagamento pendente. Selecione uma forma de pagamento para finalizar.</div>
            </div>
            <button type="button" class="checkout-finish">Finalizar compra</button>
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
                document.getElementById('payment-split').textContent = money(total);
            };
            range.addEventListener('input', update);
            document.querySelectorAll('[data-cashback]').forEach(button => button.addEventListener('click', () => { range.value = button.dataset.cashback; update(); }));
            document.querySelectorAll('[data-payment]').forEach(button => button.addEventListener('click', () => {
                document.querySelectorAll('[data-payment]').forEach(tab => tab.classList.toggle('active', tab === button));
                const pix = button.dataset.payment === 'pix';
                const credit = button.dataset.payment === 'card';
                document.getElementById('pix-payment').style.display = pix ? 'block' : 'none';
                document.getElementById('card-payment').classList.toggle('show', credit);
                document.getElementById('debit-payment').classList.toggle('show', !pix && !credit);
                document.getElementById('payment-method-name').textContent = pix ? 'PIX' : credit ? 'cartão de crédito' : 'cartão de débito';
            }));
            document.getElementById('copy-pix').addEventListener('click', async () => {
                try { await navigator.clipboard.writeText('onefit@pagamentos.com'); document.getElementById('copy-pix').textContent = 'Chave copiada!'; } catch (e) { document.getElementById('copy-pix').textContent = 'onefit@pagamentos.com'; }
            });
        })();
    </script>
</body>

</html>
