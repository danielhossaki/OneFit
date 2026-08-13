<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

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
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho — ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">

    <style>
        :root {
            --bo-bg: #0e0b08;
            --bo-surface: #17130e;
            --bo-surface-2: #1f1912;
            --bo-border: rgba(212, 175, 55, 0.18);
            --bo-text: #f3ede2;
            --bo-text-muted: #a79e8c;
            --bo-gold: #d4af37;
            --bo-gold-bright: #f4c430;
            --bo-gold-dim: #8a6b21;
            --bo-danger: #e4572e;
            --bo-header-h: 76px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: radial-gradient(circle at 15% 0%, rgba(212, 175, 55, 0.08), transparent 45%), var(--bo-bg);
            color: var(--bo-text);
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        .crt-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--bo-header-h);
            background: rgba(14, 11, 8, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--bo-border);
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        .crt-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .crt-logo img {
            height: 42px;
            width: auto;
        }

        .crt-logo span {
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--bo-gold);
            font-size: 14px;
            text-transform: uppercase;
        }

        .crt-icon-btn {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid var(--bo-border);
            background: var(--bo-surface-2);
            color: var(--bo-text);
            cursor: pointer;
            font-size: 18px;
            text-decoration: none;
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        .crt-icon-btn:hover {
            border-color: var(--bo-gold);
            color: var(--bo-gold-bright);
        }

        .crt-main {
            margin-top: var(--bo-header-h);
            max-width: 960px;
            margin-left: auto;
            margin-right: auto;
            padding: 28px 24px 60px;
        }

        .crt-page-title h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--bo-gold);
            margin: 0 0 24px;
        }

        .crt-item {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 14px;
        }

        .crt-thumb {
            width: 76px;
            height: 76px;
            border-radius: 10px;
            background: var(--bo-surface-2);
            border: 1px solid var(--bo-border);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bo-text-muted);
            font-size: 24px;
        }

        .crt-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .crt-info {
            flex: 1;
            min-width: 0;
        }

        .crt-info .crt-categoria {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--bo-gold-dim);
            font-weight: 700;
        }

        .crt-info .crt-nome {
            font-weight: 800;
            font-size: 15px;
            margin: 2px 0 4px;
        }

        .crt-info .crt-cashback {
            font-size: 12px;
            color: var(--bo-gold-bright);
            font-weight: 600;
        }

        .crt-qty {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .crt-qty form {
            display: inline;
        }

        .crt-qty-btn {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid var(--bo-border);
            background: var(--bo-surface-2);
            color: var(--bo-text);
            cursor: pointer;
            font-weight: 700;
        }

        .crt-qty-btn:hover {
            border-color: var(--bo-gold);
            color: var(--bo-gold-bright);
        }

        .crt-qty-value {
            min-width: 22px;
            text-align: center;
            font-weight: 700;
        }

        .crt-subtotal {
            min-width: 100px;
            text-align: right;
            font-weight: 800;
            font-size: 15px;
        }

        .crt-remove-btn {
            background: none;
            border: none;
            color: var(--bo-text-muted);
            font-size: 18px;
            cursor: pointer;
        }

        .crt-remove-btn:hover {
            color: var(--bo-danger);
        }

        .crt-summary {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            padding: 20px 22px;
            margin-top: 20px;
        }

        .crt-summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: var(--bo-text-muted);
            padding: 6px 0;
        }

        .crt-summary-row.total {
            border-top: 1px solid var(--bo-border);
            margin-top: 8px;
            padding-top: 14px;
            font-size: 18px;
            font-weight: 800;
            color: var(--bo-text);
        }

        .crt-summary-row .cashback-valor {
            color: var(--bo-gold-bright);
            font-weight: 700;
        }

        .btn-crt-gold {
            width: 100%;
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 8px;
            border: none;
            background: linear-gradient(120deg, var(--bo-gold-dim), var(--bo-gold) 45%, var(--bo-gold-bright) 60%, var(--bo-gold) 75%, var(--bo-gold-dim));
            background-size: 250% 100%;
            color: #14110e;
            cursor: pointer;
            transition: background-position 0.3s ease, transform 0.2s ease;
        }

        .btn-crt-gold:hover:not(:disabled) {
            background-position: 100% 0;
            transform: translateY(-1px);
        }

        .btn-crt-gold:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .crt-empty {
            background: var(--bo-surface);
            border: 1px dashed var(--bo-border);
            border-radius: 14px;
            padding: 60px 32px;
            text-align: center;
            color: var(--bo-text-muted);
        }

        .crt-empty i {
            font-size: 34px;
            color: var(--bo-gold);
            margin-bottom: 12px;
            display: block;
        }

        .btn-crt-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            font-weight: 600;
            font-size: 13px;
            border-radius: 8px;
            border: 1px solid var(--bo-border);
            background: transparent;
            color: var(--bo-text);
            text-decoration: none;
            margin-top: 16px;
        }

        .btn-crt-outline:hover {
            border-color: var(--bo-gold);
            color: var(--bo-gold-bright);
        }

        @media (max-width: 560px) {
            .crt-logo span {
                display: none;
            }

            .crt-item {
                flex-wrap: wrap;
            }

            .crt-subtotal {
                text-align: left;
            }
        }
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

        <?php if (empty($itens)): ?>
            <div class="crt-empty">
                <i class="bi bi-cart-x"></i>
                <p class="mb-0">Seu carrinho está vazio.</p>
                <a href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" class="btn-crt-outline">
                    <i class="bi bi-shop"></i> Ir para o Marketplace
                </a>
            </div>
        <?php else: ?>

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

            <div class="crt-summary">
                <div class="crt-summary-row">
                    <span>Subtotal</span>
                    <span><?php echo cart_money($totalGeral); ?></span>
                </div>
                <div class="crt-summary-row">
                    <span>Cashback a receber</span>
                    <span class="cashback-valor"><?php echo cart_money($cashbackTotal); ?></span>
                </div>
                <div class="crt-summary-row total">
                    <span>Total</span>
                    <span><?php echo cart_money($totalGeral); ?></span>
                </div>

                <button type="button" class="btn-crt-gold" disabled title="Finalização de compra em breve">
                    <i class="bi bi-credit-card"></i> Finalizar compra
                </button>
            </div>

        <?php endif; ?>

    </main>

</body>

</html>
