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
    <title>Carrinho — ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">

    <style>
        :root {
            --bo-bg: #101116;
            --bo-surface: #1b1c22;
            --bo-surface-2: #17181e;
            --bo-border: #32343f;
            --bo-text: #f6f7fb;
            --bo-text-muted: #a5abba;
            --bo-gold: #f5c400;
            --bo-gold-bright: #ffd52a;
            --bo-gold-dim: #9b7900;
            --bo-blue: #2679f4;
            --bo-danger: #ff5967;
            --bo-header-h: 76px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: radial-gradient(circle at 15% 0%, rgba(38, 121, 244, 0.06), transparent 45%), var(--bo-bg);
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
            background: rgba(16, 17, 22, 0.92);
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
            max-width: 1180px;
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

        .crt-demo-note {
            margin: -16px 0 20px;
            color: #79aaff;
            font-size: 12px;
            font-weight: 600;
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

        /* Checkout inspirado no painel de pagamento da referência */
        .crt-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 24px; align-items: start; }
        .crt-checkout { position: sticky; top: calc(var(--bo-header-h) + 22px); }
        .checkout-card { background: #1b1c22; border: 1px solid #32343f; border-radius: 12px; padding: 15px; margin-bottom: 10px; box-shadow: 0 8px 20px rgba(0,0,0,.18); }
        .checkout-title { font-size: 13px; margin: 0 0 13px; font-weight: 800; color: #fff; }
        .checkout-line { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 11px; color: #aeb4c3; }
        .checkout-line strong { color: #fff; font-weight: 800; }
        .checkout-line .positive { color: #31d485; }
        .checkout-total { border: 1px solid #8a6d09; background: #302807; padding: 11px 9px; border-radius: 7px; margin-top: 11px; display: flex; align-items: center; justify-content: space-between; font-size: 11px; font-weight: 800; color: #fff; }
        .checkout-total strong { color: #ffc400; font-size: 17px; }
        .cashback-disponivel { display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: #98a0b2; margin-bottom: 8px; }
        .cashback-disponivel strong { color: #2684ff; }
        .cashback-range { width: 100%; accent-color: #2679f4; margin: 8px 0 6px; }
        .cashback-actions { display:flex; gap: 7px; margin-top: 7px; }
        .cashback-action { border:1px solid #4a4d58; background:#121319; color:#fff; border-radius:6px; padding:3px 9px; font-size:9px; font-weight:700; cursor:pointer; }
        .cashback-aplicado { margin-top: 10px; padding: 6px 8px; border-radius: 5px; display:flex; justify-content:space-between; font-size:10px; font-weight:700; background:#172a63; color:#d7e4ff; }
        .payment-heading { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .payment-heading .checkout-title { margin:0; }
        .payment-heading small { color:#80889a; font-size:8px; letter-spacing:.08em; font-weight:800; }
        .payment-tabs { display:grid; grid-template-columns:1fr 1fr; border:1px solid #30323c; border-radius:5px; overflow:hidden; }
        .payment-tab { background:#17181e; border:0; color:#a5abba; padding:8px 5px; font-size:10px; font-weight:800; cursor:pointer; }
        .payment-tab.active { background:#ffc400; color:#17130b; }
        .pix-label { display:block; font-size:10px; color:#9ca3b3; margin:15px 0 5px; }
        .pix-key { background:#17181e; border:1px solid #30323c; border-radius:5px; padding:8px; display:flex; align-items:center; justify-content:space-between; gap:6px; font-size:10px; color:#fff; }
        .copy-key { flex-shrink:0; border:1px solid #4c4f5d; background:#252731; color:#fff; border-radius:4px; padding:4px 7px; font-size:8px; font-weight:800; cursor:pointer; }
        .payment-features { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:10px; }
        .payment-feature { border:1px dashed #363946; border-radius:5px; padding:7px 5px; text-align:center; color:#3483ff; font-size:8px; font-weight:700; }
        .payment-summary { border-top:1px solid #363843; margin-top:12px; padding-top:8px; }
        .payment-summary div { display:flex; justify-content:space-between; padding:3px 0; color:#aeb4c3; font-size:9px; }
        .payment-summary strong { color:#fff; }
        .payment-error { margin-top:12px; padding:8px; border:1px solid #8f343d; border-radius:5px; background:#3b1d23; color:#ff5967; font-size:9px; font-weight:700; }
        .checkout-finish { width:100%; border:0; border-radius:9px; margin-top:8px; padding:13px 12px; background:linear-gradient(180deg,#ffd72b,#ffbc00); color:#171208; font-size:11px; font-weight:800; cursor:pointer; box-shadow:0 5px 18px rgba(255,196,0,.16); }
        .checkout-clear { width:100%; background:transparent; border:1px solid #454751; border-radius:7px; padding:8px; color:#bfc4d0; font-size:10px; font-weight:700; margin-top:8px; cursor:pointer; }
        .card-payment { display:none; margin-top:14px; }
        .card-payment.show { display:block; }
        .payment-input { width:100%; background:#17181e; border:1px solid #30323c; color:#fff; border-radius:5px; padding:9px; font-size:10px; margin-bottom:7px; outline:none; }
        .payment-input:focus { border-color:#2679f4; }

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

        /* Checkout premium: cards independentes, leitura e aÃ§Ãµes bem definidas. */
        .crt-layout { grid-template-columns: minmax(0, 1fr) 380px; gap: 28px; }
        .crt-checkout { display: grid; gap: 18px; }
        .checkout-card { margin: 0; padding: 20px; border-radius: 16px; box-shadow: 0 12px 30px rgba(0, 0, 0, .20); }
        .checkout-title { font-size: 16px; margin-bottom: 16px; }
        .crt-summary { margin: 0; padding: 20px; border-radius: 16px; }
        .crt-summary-row { padding: 7px 0; }
        .crt-summary-row.total { margin-top: 10px; padding-top: 16px; font-size: 20px; }
        .cashback-disponivel { font-size: 13px; margin-bottom: 8px; }
        .cashback-disponivel strong { color: var(--bo-blue); font-size: 14px; }
        .cashback-disponivel:last-of-type { display: block; font-size: 12px; margin: -1px 0 10px; }
        .cashback-range { height: 6px; accent-color: var(--bo-blue); cursor: pointer; margin: 12px 0 10px; }
        .cashback-actions { gap: 8px; margin-top: 4px; }
        .cashback-action { border-color: #575a67; background: transparent; border-radius: 8px; padding: 7px 11px; font-size: 11px; }
        .cashback-aplicado { margin-top: 16px; padding: 11px 12px; border-radius: 9px; font-size: 12px; background: #142b55; }
        .payment-heading { margin-bottom: 16px; }
        .payment-heading small { font-size: 9px; white-space: nowrap; }
        .payment-tabs { gap: 8px; border: 0; border-radius: 0; overflow: visible; }
        .payment-tab { border: 1px solid var(--bo-border); border-radius: 9px; padding: 11px 8px; font-size: 11px; }
        .payment-tab.active { border-color: var(--bo-gold); background: var(--bo-gold); }
        .pix-label { font-size: 11px; font-weight: 700; margin: 17px 0 7px; }
        .pix-key { min-height: 43px; border-radius: 9px; padding: 9px 11px; font-size: 12px; }
        .copy-key { border-radius: 7px; padding: 7px 9px; font-size: 10px; }
        .payment-features { gap: 8px; margin-top: 12px; }
        .payment-feature { border-radius: 8px; padding: 8px 6px; font-size: 10px; }
        .payment-summary { margin-top: 17px; padding-top: 11px; }
        .payment-summary div { gap: 14px; padding: 5px 0; font-size: 12px; }
        .payment-summary div:last-child { color: var(--bo-text); font-weight: 800; }
        .payment-error { margin-top: 16px; padding: 11px 12px; border-color: #8e3944; border-radius: 9px; background: #3a2026; color: #ffabb3; font-size: 11px; line-height: 1.45; }
        .checkout-finish { margin-top: 0; border-radius: 11px; padding: 15px 12px; font-size: 13px; }
        .checkout-clear { margin-top: 0; border-radius: 10px; padding: 12px; font-size: 12px; }
        .btn-crt-gold { border: 1px solid #a98400; border-radius: 10px; background: transparent; }
        .btn-crt-gold:hover:not(:disabled) { background: var(--bo-gold); }

        /* Gatilhos no carrinho e drawer lateral do checkout. */
        .crt-layout { grid-template-columns: minmax(0, 1fr); }
        .checkout-access-card { display: grid; gap: 18px; margin-top: 20px; padding: 22px; background: var(--bo-surface); border: 1px solid var(--bo-border); border-radius: 16px; }
        .checkout-access-kicker { display: block; margin-bottom: 6px; color: var(--bo-gold); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .checkout-access-card h2 { margin: 0; color: var(--bo-text); font-size: 18px; font-weight: 800; }
        .checkout-access-card p { margin: 6px 0 0; color: var(--bo-text-muted); font-size: 13px; }
        .checkout-access-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .checkout-access-button { display: flex; flex-direction: column; align-items: flex-start; gap: 9px; min-height: 80px; padding: 13px; border: 1px solid var(--bo-border); border-radius: 11px; background: var(--bo-surface-2); color: var(--bo-text); font: inherit; font-size: 12px; font-weight: 700; text-align: left; cursor: pointer; transition: border-color .2s ease, transform .2s ease; }
        .checkout-access-button i { color: var(--bo-gold); font-size: 17px; }
        .checkout-access-button:hover { border-color: var(--bo-gold); transform: translateY(-2px); }
        .cashback-remaining { display: flex; justify-content: space-between; gap: 12px; margin: 13px 0; color: var(--bo-text-muted); font-size: 12px; }
        .cashback-remaining strong { color: var(--bo-text); }
        .cashback-continue { width: 100%; padding: 11px; border: 1px solid var(--bo-blue); border-radius: 9px; background: rgba(38, 121, 244, .12); color: #a9c8ff; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; }
        .cashback-continue i { margin-left: 5px; }
        .payment-tabs--three { grid-template-columns: repeat(3, 1fr); }
        .payment-tabs--three .payment-tab { font-size: 10px; }
        .checkout-open-primary { width: 100%; padding: 14px; border: 0; border-radius: 10px; background: var(--bo-gold); color: #171208; font: inherit; font-size: 13px; font-weight: 800; cursor: pointer; }
        .checkout-open-primary i { margin-right: 7px; }
        .checkout-backdrop { position: fixed; inset: 0; z-index: 1040; background: rgba(0, 0, 0, .58); opacity: 0; pointer-events: none; transition: opacity .25s ease; }
        .crt-checkout { position: fixed; z-index: 1050; top: 0; right: 0; width: min(100%, 450px); height: 100dvh; padding: 18px; overflow-y: auto; background: var(--bo-bg); border-left: 1px solid var(--bo-border); box-shadow: -20px 0 50px rgba(0, 0, 0, .32); transform: translateX(100%); transition: transform .28s ease; }
        .crt-checkout.is-open { transform: translateX(0); }
        .crt-checkout.is-open + .checkout-backdrop { opacity: 1; pointer-events: auto; }
        .crt-checkout .checkout-step { display: none; }
        .crt-checkout .checkout-step.is-active { display: block; }
        .checkout-panel-header { display: flex; align-items: center; justify-content: space-between; margin: 0 2px 2px; color: var(--bo-text); font-size: 14px; }
        .checkout-close { display: inline-grid; place-items: center; width: 36px; height: 36px; border: 1px solid var(--bo-border); border-radius: 9px; background: var(--bo-surface); color: var(--bo-text); cursor: pointer; }
        body.checkout-open { overflow: hidden; }

        @media (max-width: 560px) {
            .checkout-access-actions { grid-template-columns: 1fr; }
            .checkout-access-button { min-height: auto; flex-direction: row; align-items: center; }
            .payment-tabs--three .payment-tab { padding: 10px 4px; font-size: 9px; }
            .crt-checkout { width: 100%; padding: 14px; }
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
        @media (max-width: 850px) { .crt-layout { grid-template-columns: 1fr; } .crt-checkout { position:static; } }
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
