<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/pages/dashboard/includes/frete.php');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Finalizar a compra grava pedido/itens/cashback reais no banco, então
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
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function cart_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
}

function cart_money($v)
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

require_once __DIR__ . '/checkout.php';

function cart_finalizar_compra(mysqli $conn, int $idUsuario, array $post): void
{
    try {
        $pedido = cart_processar_checkout($conn, $_SESSION, $post);
    } catch (Throwable $erro) {
        error_log('ONE FIT checkout: usuario=' . $idUsuario . '; tipo=' . get_class($erro) . '; codigo=' . $erro->getCode()
            . ($erro instanceof DomainException ? '; motivo=' . $erro->getMessage() : ''));
        $_SESSION['checkout_erro'] = $erro instanceof DomainException ? $erro->getMessage()
            : onefitTraduzir('Não foi possível salvar o pedido. Seu carrinho foi mantido. Tente novamente.');
        header('Location: carrinho.php?erro=1');
        exit;
    }
    try {
        require_once __DIR__ . '/../../config/notificacoes.php';
        criarNotificacao($idUsuario, 'Pedido recebido',
            'Seu pedido ' . $pedido['transacao'] . ' foi recebido e está aguardando processamento.',
            'compra', '/AN25/OneFit/pages/dashboard/dashboard.php?section=compras');
    } catch (Throwable $erroNotificacao) {
        error_log('ONE FIT: falha ao notificar pedido #' . $pedido['id'] . '; codigo ' . $erroNotificacao->getCode());
    }
    // Independent from buyer notifications: either channel may fail separately.
    try {
        require_once __DIR__ . '/../../config/notificacoes.php';
        notificarCompraBackoffice($conn, (int) $pedido['id']);
    } catch (Throwable $erroNotificacao) {
        error_log('ONE FIT: falha no alerta administrativo; codigo ' . $erroNotificacao->getCode());
    }
    header('Location: carrinho.php?sucesso=1');
    exit;
}

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !cart_csrf_valido()) {
    $_SESSION['checkout_erro'] = onefitTraduzir('Sua sessão expirou. Atualize a página e tente novamente.');
    header('Location: carrinho.php?erro=1');
    exit;
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

        case 'aplicar_cashback':
            // Guardado na sessão e reaplicado a cada carregamento da página
            // (clamp final contra o saldo/total real acontece no render,
            // logo abaixo, e de novo em cart_gravar_compra() ao finalizar) —
            // fluxo de ida-e-volta ao servidor, sem depender de JS.
            $valorCashback = filter_var($_POST['cashback_usado'] ?? '0', FILTER_VALIDATE_FLOAT);
            $_SESSION['checkout_cashback_usado'] = ($valorCashback !== false && $valorCashback > 0) ? $valorCashback : 0.0;
            break;

        case 'finalizar':
            cart_finalizar_compra($conn, $idUsuarioLogado, $_POST);
            // cart_finalizar_compra sempre redireciona (sucesso ou erro) e encerra o script.
            break;
    }

    header('Location: carrinho.php');
    exit;
}

if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
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
$pixLocalSemFrete = $freteIndisponivel && cart_pix_local();
$bloquearPagamento = !$enderecoSelecionado || ($freteIndisponivel && !$pixLocalSemFrete);
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

/* Cashback já aplicado nesta sessão de checkout (ação "aplicar_cashback"),
 * sempre reclamado contra o máximo real de novo aqui — se o endereço/frete
 * mudou depois de aplicar, o valor não pode passar do novo máximo. */
$cashbackAplicado = round(min((float) ($_SESSION['checkout_cashback_usado'] ?? 0), $cashbackMaximoUsavel), 2);
$restanteAposCashback = round(max(0, $totalComFrete - $cashbackAplicado), 2);
$cashbackCobreTudo = $restanteAposCashback <= 0.01;

/* ===== Mensagens vindas do redirecionamento após finalizar a compra ===== */
$pedidoConcluido = null;
if (isset($_GET['sucesso']) && !empty($_SESSION['ultimo_pedido'])) {
    $pedidoConcluido = $_SESSION['ultimo_pedido'];
    unset($_SESSION['ultimo_pedido']);
}
$erroCheckout = $_SESSION['checkout_erro'] ?? '';
unset($_SESSION['checkout_erro']);
$erroFinalizar = isset($_GET['erro']) && $_GET['erro'] === '1';
$erroSemEndereco = isset($_GET['erro']) && $_GET['erro'] === 'endereco';
$erroSemFrete = isset($_GET['erro']) && $_GET['erro'] === 'frete';
$erroSemEstoque = isset($_GET['semestoque']);

/* "Comprar agora" no marketplace já manda o produto pro carrinho e cai aqui
   com ?comprar=1 — abre o checkout direto na etapa de pagamento (renderizado
   assim já na primeira resposta do servidor, sem depender de JS). */
$abrirCheckoutPagamento = (isset($_GET['comprar']) || isset($_GET['erro'])) && !empty($itens);

/* Tema (dark/light) escolhido no dashboard, persistido em cookie por assets/js/dashboard.js. */
$cartTema = ($_COOKIE['onefit_theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
?>
<!DOCTYPE html>
<html lang="<?php echo onefitIdioma(); ?>" data-site-theme="<?php echo htmlspecialchars($GLOBALS['onefitTemaGlobal'] ?? 'dourado', ENT_QUOTES, 'UTF-8'); ?>" data-theme="<?php echo $cartTema; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo of_t('Carrinho · ONE FIT'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" data-brand-logo href="<?php echo onefitLogo(); ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/carrinho.css">
    <style>
        /* Os inputs de forma de pagamento agora são radios reais (funcionam sem JS);
           o rótulo (label) continua com a aparência de aba já existente. */
        .payment-radio { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
        .payment-radio:checked + .payment-tab { background: #ffc400; color: #17130b; }
        .payment-radio:focus-visible + .payment-tab { outline: 2px solid #ffc400; outline-offset: -2px; }
    </style>
<?php onefitInterfaceHead(); ?>
</head>

<body class="<?php echo $abrirCheckoutPagamento ? 'checkout-open' : ''; ?>">

    <header class="crt-header">
        <div class="crt-logo">
            <img data-brand-logo src="<?php echo onefitLogo(); ?>" alt="Logo <?php echo onefitNomeMarca(); ?>">
            <span><?php echo of_t('One Fit · Carrinho'); ?></span>
        </div>

        <a class="crt-icon-btn" href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" aria-label="Voltar ao marketplace" title="Voltar ao marketplace">
            <i class="bi bi-arrow-left"></i>
        </a>
    </header>

    <main class="crt-main">

        <div class="crt-page-title">
            <h1><?php echo of_t('Seu carrinho'); ?></h1>
        </div>

        <?php if ($erroSemEstoque): ?>
            <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo of_t('Sem estoque suficiente para adicionar mais unidades deste produto.'); ?></div>
        <?php endif; ?>

        <?php if ($erroFinalizar && empty($itens)): ?>
            <div class="payment-error"><?php echo htmlspecialchars($erroCheckout ?: 'Não foi possível finalizar a compra. Revise o carrinho.', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($pedidoConcluido): ?>
            <div class="crt-empty">
                <i class="bi bi-check-circle"></i>
                <p class="mb-0">Pedido <?php echo htmlspecialchars($pedidoConcluido['transacao']); ?> registrado com sucesso! Status: Aguardando.</p>
                <p class="mb-0">Total do pedido: <?php echo cart_money($pedidoConcluido['total']); ?><?php if ($pedidoConcluido['cashbackUsado'] > 0): ?> (<?php echo cart_money($pedidoConcluido['cashbackUsado']); ?> em cashback) <?php endif; ?></p>
                <?php if ($pedidoConcluido['cashbackGanho'] > 0): ?>
                    <p class="mb-0">Você ganhou <?php echo cart_money($pedidoConcluido['cashbackGanho']); ?> de cashback nesta compra.</p>
                <?php endif; ?>
                <div class="crt-confirmation-actions">
                    <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=compras&amp;compra_finalizada=1" class="btn-crt-outline">
                        <i class="bi bi-bag" aria-hidden="true"></i> <?php echo of_t('Ver minhas compras'); ?>
                    </a>
                    <a href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" class="btn-crt-outline">
                        <i class="bi bi-shop" aria-hidden="true"></i> <?php echo of_t('Continuar comprando'); ?>
                    </a>
                </div>
            </div>
        <?php elseif (empty($itens)): ?>
            <div class="crt-empty">
                <i class="bi bi-cart-x"></i>
                <p class="mb-0"><?php echo of_t('Seu carrinho está vazio.'); ?></p>
                <a href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php" class="btn-crt-gold">
                    <i class="bi bi-shop"></i> <?php echo of_t('Ir para o Marketplace'); ?>
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
                        <span class="checkout-access-kicker"><?php echo of_t('Checkout seguro'); ?></span>
                        <h2 id="checkout-access-title"><?php echo of_t('Revise e finalize sua compra'); ?></h2>
                        <p><?php echo of_t('Abra cada etapa quando precisar: resumo, endereço, cashback ou pagamento.'); ?></p>
                    </div>
                    <div class="checkout-access-actions">
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-endereco"><i class="bi bi-geo-alt"></i> <?php echo of_t('Endereço de entrega'); ?></button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-cashback"><i class="bi bi-coin"></i> <?php echo of_t('Usar meu cashback'); ?></button>
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-pagamento"<?php echo $bloquearPagamento ? ' disabled' : ''; ?>><i class="bi bi-credit-card"></i> <?php echo of_t('Forma de pagamento'); ?></button>
                    </div>
                    <button type="button" class="checkout-open-primary" data-open-checkout="checkout-resumo"><i class="bi bi-bag-check"></i> <?php echo of_t('Abrir checkout'); ?></button>
                </section>

            <aside class="crt-checkout<?php echo $abrirCheckoutPagamento ? ' is-open' : ''; ?>" id="checkout-panel" aria-hidden="<?php echo $abrirCheckoutPagamento ? 'false' : 'true'; ?>" aria-label="Checkout">
            <div class="checkout-panel-header"><strong>Checkout ONE FIT</strong><button class="checkout-close" type="button" aria-label="Fechar checkout"><i class="bi bi-x-lg"></i></button></div>

            <?php if ($erroFinalizar): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($erroCheckout ?: 'Não foi possível finalizar a compra. Revise o carrinho e tente novamente.', ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($erroSemEndereco): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo of_t('Escolha um endereço de entrega antes de finalizar a compra.'); ?></div>
            <?php endif; ?>
            <?php if ($erroSemFrete): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo of_t('Não entregamos no CEP do endereço selecionado. Tente outro endereço.'); ?></div>
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
            <input type="hidden" name="checkout_token" value="<?php echo htmlspecialchars($_SESSION['checkout_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="cashback_usado" value="<?php echo $cashbackAplicado; ?>">
            </form>

            <div class="crt-summary checkout-card checkout-step<?php echo $abrirCheckoutPagamento ? '' : ' is-active'; ?>" id="checkout-resumo">
                <h2 class="checkout-title"><?php echo of_t('Resumo da compra'); ?></h2>
                <div class="crt-summary-row">
                    <span>Subtotal</span>
                    <span><?php echo cart_money($totalGeral); ?></span>
                </div>
                <div class="crt-summary-row">
                    <span><?php echo of_t('Frete'); ?></span>
                    <span id="resumo-frete"><?php echo $freteInfo ? cart_money($valorFrete) : 'Escolha um endereço'; ?></span>
                </div>
                <div class="crt-summary-row">
                    <span><?php echo of_t('Cashback a receber'); ?></span>
                    <span class="cashback-valor" id="resumo-cashback"><?php echo cart_money($cashbackTotal); ?></span>
                </div>
                <div class="crt-summary-row total">
                    <span>Total</span>
                    <span id="total-final"><?php echo cart_money($totalComFrete); ?></span>
                </div>

                <button type="button" class="btn-crt-gold" data-open-checkout="checkout-endereco">
                    <i class="bi bi-geo-alt"></i> <?php echo of_t('Ir para endereço de entrega'); ?>
                </button>
            </div>

            <div class="checkout-card checkout-step" id="checkout-endereco">
                <h2 class="checkout-title"><?php echo of_t('Endereço de entrega'); ?></h2>

                <?php if (empty($enderecosUsuario)): ?>
                    <p class="cashback-remaining"><?php echo of_t('Você ainda não tem nenhum endereço salvo.'); ?></p>
                <?php else: ?>
                    <?php foreach ($enderecosUsuario as $end): ?>
                        <div class="crt-endereco-card<?php echo $enderecoSelecionadoId === (int) $end['id_endereco'] ? ' is-selected' : ''; ?>">
                            <div class="crt-endereco-info">
                                <strong><?php echo htmlspecialchars($end['apelido'] ?: ($end['logradouro'] . ', ' . $end['numero'])); ?></strong>
                                <?php if ((int) $end['principal'] === 1): ?><span class="crt-endereco-badge"><?php echo of_t('Principal'); ?></span><?php endif; ?>
                                <p class="mb-0"><?php echo htmlspecialchars($end['logradouro'] . ', ' . $end['numero'] . ($end['complemento'] ? ' - ' . $end['complemento'] : '')); ?></p>
                                <p class="mb-0"><?php echo htmlspecialchars($end['bairro'] . ' - ' . $end['cidade'] . '/' . $end['uf'] . ' - CEP ' . $end['cep']); ?></p>
                            </div>
                            <div class="crt-endereco-actions">
                                <?php if ($enderecoSelecionadoId === (int) $end['id_endereco']): ?>
                                    <span class="crt-endereco-badge"><?php echo of_t('Selecionado para entrega'); ?></span>
                                <?php else: ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/enderecos.php">
                                        <?php echo cart_csrf_field(); ?>
                                        <input type="hidden" name="acao" value="selecionar">
                                        <input type="hidden" name="id" value="<?php echo (int) $end['id_endereco']; ?>">
                                        <button type="submit" class="btn-crt-outline"><?php echo of_t('Usar este endereço'); ?></button>
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
                    <i class="bi bi-plus-circle"></i> <?php echo of_t('Adicionar novo endereço'); ?>
                </button>

                <?php if ($pixLocalSemFrete): ?>
                    <p class="cashback-remaining"><?php echo of_t('Pix local: compra sem cobrança de frete. Recebimento não definido.'); ?></p>
                <?php elseif ($freteIndisponivel): ?>
                    <p class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo of_t('Não entregamos no CEP deste endereço.'); ?></p>
                <?php elseif ($freteInfo): ?>
                    <h3 class="checkout-subtitle"><?php echo of_t('Tipo de entrega'); ?></h3>
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
                        <button type="submit" class="btn-crt-outline"><?php echo of_t('Usar esta forma de entrega'); ?></button>
                    </form>
                <?php endif; ?>

                <button type="button" class="btn-crt-gold" data-open-checkout="checkout-pagamento"<?php echo $bloquearPagamento ? ' disabled' : ''; ?>><?php echo of_t('Continuar para pagamento'); ?> <i class="bi bi-arrow-right"></i></button>
            </div>

            <div class="checkout-card checkout-step" id="checkout-cashback">
                <h2 class="checkout-title"><?php echo of_t('Usar meu cashback'); ?></h2>
                <div class="cashback-disponivel"><span><?php echo of_t('Disponível'); ?></span><strong><?php echo cart_money($saldoCashback); ?></strong></div>
                <div class="cashback-disponivel"><span>Máximo permitido nesta compra: <?php echo cart_money($cashbackMaximoUsavel); ?></span></div>

                <form method="POST" action="carrinho.php" class="crt-cashback-apply">
                    <?php echo cart_csrf_field(); ?>
                    <input type="hidden" name="acao" value="aplicar_cashback">
                    <input type="number" class="form-control" name="cashback_usado" min="0" max="<?php echo $cashbackMaximoUsavel; ?>" step="0.01" value="<?php echo $cashbackAplicado; ?>" aria-label="Cashback a utilizar">
                    <button type="submit" class="btn-crt-outline"><?php echo of_t('Aplicar'); ?></button>
                </form>
                <div class="cashback-actions">
                    <form method="POST" action="carrinho.php" class="bo-inline-form">
                        <?php echo cart_csrf_field(); ?>
                        <input type="hidden" name="acao" value="aplicar_cashback">
                        <input type="hidden" name="cashback_usado" value="<?php echo $cashbackMaximoUsavel; ?>">
                        <button type="submit" class="cashback-action"><?php echo of_t('Usar máximo'); ?></button>
                    </form>
                    <form method="POST" action="carrinho.php" class="bo-inline-form">
                        <?php echo cart_csrf_field(); ?>
                        <input type="hidden" name="acao" value="aplicar_cashback">
                        <input type="hidden" name="cashback_usado" value="0">
                        <button type="submit" class="cashback-action"><?php echo of_t('Não usar'); ?></button>
                    </form>
                </div>
                <div class="cashback-aplicado"><span><?php echo of_t('Aplicado'); ?></span><span><?php echo cart_money($cashbackAplicado); ?></span></div>
                <p class="cashback-remaining"><?php echo of_t('Restante para pagamento:'); ?> <strong><?php echo cart_money($restanteAposCashback); ?></strong></p>

                <?php if ($cashbackCobreTudo): ?>
                    <button type="submit" form="checkout-form" class="btn-crt-gold"><?php echo of_t('Concluir compra'); ?></button>
                <?php else: ?>
                    <button type="button" class="btn-crt-gold" data-open-checkout="checkout-pagamento"<?php echo $bloquearPagamento ? ' disabled' : ''; ?>><?php echo of_t('Continuar para pagamento'); ?> <i class="bi bi-arrow-right"></i></button>
                <?php endif; ?>
            </div>
            <div class="checkout-card checkout-step<?php echo $abrirCheckoutPagamento ? ' is-active' : ''; ?>" id="checkout-pagamento">
                <div class="payment-heading"><h2 class="checkout-title"><?php echo of_t('Forma de pagamento'); ?></h2></div>
                <?php if (!$enderecoSelecionado): ?>
                    <p class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo of_t('Escolha um endereço de entrega antes de finalizar.'); ?></p>
                <?php endif; ?>
                <div class="payment-tabs">
                    <input type="radio" class="payment-radio" name="forma_pagamento" form="checkout-form" id="payPix" value="pix" checked>
                    <label class="payment-tab" for="payPix"><i class="bi bi-qr-code"></i> PIX</label>
                    <input type="radio" class="payment-radio" name="forma_pagamento" form="checkout-form" id="payCartao" value="cartao">
                    <label class="payment-tab" for="payCartao"><i class="bi bi-credit-card"></i> <?php echo of_t('Cartão'); ?></label>
                </div>
                <div id="pix-payment"><label class="pix-label"><?php echo of_t('Valor a pagar no PIX'); ?></label><div class="pix-key"><span id="pix-value"><?php echo cart_money($restanteAposCashback); ?></span></div><label class="pix-label"><?php echo of_t('CHAVE PIX'); ?></label><div class="pix-key"><span>onefit@pagamentos.com</span><button type="button" id="copy-pix" class="copy-key"><?php echo of_t('Copiar chave PIX'); ?></button></div></div>
                <div id="card-payment" class="card-payment">
                    <label class="pix-label"><?php echo of_t('Dados do cartão (simulação)'); ?></label>
                    <input class="payment-input" type="text" inputmode="numeric" maxlength="19" placeholder="<?php echo of_t('Número do cartão'); ?>" name="cartao_numero" form="checkout-form">
                    <input class="payment-input" type="text" placeholder="<?php echo of_t('Nome impresso no cartão'); ?>" name="cartao_nome" form="checkout-form">
                    <div class="card-payment-row">
                        <input class="payment-input" type="text" inputmode="numeric" maxlength="5" placeholder="Validade (MM/AA)" name="cartao_validade" form="checkout-form">
                        <input class="payment-input" type="text" inputmode="numeric" maxlength="4" placeholder="CVV" name="cartao_cvv" form="checkout-form">
                    </div>
                </div>
                <div class="payment-summary"><div><span><?php echo of_t('Total da compra'); ?></span><strong><?php echo cart_money($totalComFrete); ?></strong></div><div><span><?php echo of_t('Cashback aplicado'); ?></span><strong><?php echo cart_money($cashbackAplicado); ?></strong></div><div><span><?php echo of_t('Restante via'); ?> <span id="payment-method-name">PIX</span></span><strong><?php echo cart_money($restanteAposCashback); ?></strong></div></div>

                <button type="submit" form="checkout-form" class="checkout-finish"><?php echo of_t('Finalizar compra'); ?></button>
            </div>

            <form method="POST" action="carrinho.php"><?php echo cart_csrf_field(); ?><input type="hidden" name="acao" value="limpar"><button type="submit" class="checkout-clear"><?php echo of_t('Limpar carrinho'); ?></button></form>
            </aside>
            <div class="checkout-backdrop" data-close-checkout></div>
            </div>

            <div class="modal fade crt-modal" id="modalEnderecoNovo" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo of_t('Novo endereço de entrega'); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                        </div>
                        <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/enderecos.php">
                            <div class="modal-body row g-3">
                                <?php echo cart_csrf_field(); ?>
                                <input type="hidden" name="acao" value="create">
                                <div class="col-12">
                                    <label class="form-label"><?php echo of_t('Apelido (opcional)'); ?></label>
                                    <input type="text" class="form-control" name="apelido" placeholder="Casa, trabalho...">
                                </div>
                                <div class="col-4">
                                    <label class="form-label"><?php echo of_t('CEP'); ?></label>
                                    <input type="text" class="form-control" name="cep" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label"><?php echo of_t('Logradouro'); ?></label>
                                    <input type="text" class="form-control" name="logradouro" required>
                                </div>
                                <div class="col-2">
                                    <label class="form-label"><?php echo of_t('Número'); ?></label>
                                    <input type="text" class="form-control" name="numero" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label"><?php echo of_t('Complemento'); ?></label>
                                    <input type="text" class="form-control" name="complemento">
                                </div>
                                <div class="col-6">
                                    <label class="form-label"><?php echo of_t('Bairro'); ?></label>
                                    <input type="text" class="form-control" name="bairro" required>
                                </div>
                                <div class="col-8">
                                    <label class="form-label"><?php echo of_t('Cidade'); ?></label>
                                    <input type="text" class="form-control" name="cidade" required>
                                </div>
                                <div class="col-4">
                                    <label class="form-label"><?php echo of_t('UF'); ?></label>
                                    <input type="text" class="form-control" name="uf" maxlength="2" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-crt-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                                <button type="submit" class="btn-crt-gold"><?php echo of_t('Salvar endereço'); ?></button>
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
            const pixLocalSemFrete = <?php echo json_encode($pixLocalSemFrete); ?>;
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
                if (!checkoutPanel) return;
                checkoutPanel.classList.remove('is-open');
                checkoutPanel.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('checkout-open');
            };
            document.querySelectorAll('[data-open-checkout]').forEach(button => button.addEventListener('click', () => openCheckout(button.dataset.openCheckout)));
            document.querySelector('.checkout-close')?.addEventListener('click', closeCheckout);
            document.querySelector('[data-close-checkout]')?.addEventListener('click', closeCheckout);
            document.addEventListener('keydown', event => { if (event.key === 'Escape') closeCheckout(); });
            const checkoutForm = document.getElementById('checkout-form');
            checkoutForm?.addEventListener('submit', event => {
                if (checkoutForm.dataset.sending) { event.preventDefault(); return; }
                checkoutForm.dataset.sending = '1';
                const button = document.querySelector('.checkout-finish');
                button.disabled = true;
                button.textContent = 'Finalizando...';
            });
            window.addEventListener('pageshow', () => {
                if (!checkoutForm) return;
                delete checkoutForm.dataset.sending;
                const button = document.querySelector('.checkout-finish');
                button.disabled = pixLocalSemFrete && !document.getElementById('payPix').checked;
                button.textContent = 'Finalizar compra';
            });

            // Seleção de pagamento: os "botões" agora são <label for="..."> ligados a
            // <input type="radio">, então já funcionam nativamente (sem JS). O trecho
            // abaixo só atualiza o texto/painel de apoio quando o JS está disponível.
            document.querySelectorAll('.payment-radio').forEach(radio => radio.addEventListener('change', () => {
                const pix = radio.value === 'pix';
                if (!radio.checked) return;
                document.querySelector('.checkout-finish').disabled = pixLocalSemFrete && !pix;
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
