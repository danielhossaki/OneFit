# Pix local sem frete cadastrado

Causa reproduzida: o CEP nao tinha opcao de frete; o checkout interrompia antes do INSERT.

Corrigido para APP_URL em localhost ou loopback: PIX permite pedido sem cobranca de frete quando nao ha cotacao. Nao inventa transportadora ou data. Fora do ambiente local, e para cartao, o bloqueio sem frete permanece. Fretes cadastrados continuam sendo cobrados.

SQL NECESSARIO: nenhum.

Validacao: 15 verificacoes do cenario Strap + Munhequeira (62,80, um pedido, dois itens), mais 37 verificacoes de checkout; SQL real em tabelas temporarias, sem compras inseridas na base de uso. Sem navegador conectado, o teste visual/login permanece nao executado.

ARQUIVO:
pages/carrinho/carrinho.php

CÓDIGO COMPLETO:

```php
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/pages/dashboard/includes/frete.php');

session_start();

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
            : 'Não foi possível salvar o pedido. Seu carrinho foi mantido. Tente novamente.';
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
    header('Location: carrinho.php?sucesso=1');
    exit;
}

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !cart_csrf_valido()) {
    $_SESSION['checkout_erro'] = 'Sua sessão expirou. Atualize a página e tente novamente.';
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

<body class="<?php echo $abrirCheckoutPagamento ? 'checkout-open' : ''; ?>">

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
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=compras&amp;compra_finalizada=1" class="btn-crt-outline">Ver minhas compras</a>
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
                        <button type="button" class="checkout-access-button" data-open-checkout="checkout-pagamento"<?php echo $bloquearPagamento ? ' disabled' : ''; ?>><i class="bi bi-credit-card"></i> Forma de pagamento</button>
                    </div>
                    <button type="button" class="checkout-open-primary" data-open-checkout="checkout-resumo"><i class="bi bi-bag-check"></i> Abrir checkout</button>
                </section>

            <aside class="crt-checkout<?php echo $abrirCheckoutPagamento ? ' is-open' : ''; ?>" id="checkout-panel" aria-hidden="<?php echo $abrirCheckoutPagamento ? 'false' : 'true'; ?>" aria-label="Checkout">
            <div class="checkout-panel-header"><strong>Checkout ONE FIT</strong><button class="checkout-close" type="button" aria-label="Fechar checkout"><i class="bi bi-x-lg"></i></button></div>

            <?php if ($erroFinalizar): ?>
                <div class="payment-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($erroCheckout ?: 'Não foi possível finalizar a compra. Revise o carrinho e tente novamente.', ENT_QUOTES, 'UTF-8'); ?></div>
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
            <input type="hidden" name="checkout_token" value="<?php echo htmlspecialchars($_SESSION['checkout_token'], ENT_QUOTES, 'UTF-8'); ?>">
            </form>

            <div class="crt-summary checkout-card checkout-step<?php echo $abrirCheckoutPagamento ? '' : ' is-active'; ?>" id="checkout-resumo">
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

                <?php if ($pixLocalSemFrete): ?>
                    <p class="cashback-remaining">Pix local: compra sem cobrança de frete. Recebimento não definido.</p>
                <?php elseif ($freteIndisponivel): ?>
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

                <button type="button" class="btn-crt-gold" data-open-checkout="checkout-pagamento"<?php echo $bloquearPagamento ? ' disabled' : ''; ?>>Continuar para pagamento <i class="bi bi-arrow-right"></i></button>
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
            <div class="checkout-card checkout-step<?php echo $abrirCheckoutPagamento ? ' is-active' : ''; ?>" id="checkout-pagamento">
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
            const pixLocalSemFrete = <?php echo json_encode($pixLocalSemFrete); ?>;
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

```

ARQUIVO:
pages/carrinho/checkout.php

CÓDIGO COMPLETO:

```php
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../dashboard/includes/frete.php';
require_once __DIR__ . '/../../config/env.php';

function cart_pix_local(): bool
{
    // Usa a configuração da aplicação, nunca o Host enviado pelo navegador.
    $host = strtolower((string) parse_url(onefitEnv('APP_URL', 'http://localhost/AN25/OneFit/'), PHP_URL_HOST));
    return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

function cart_frete_local_pendente(array $itensPorVendedor): array
{
    // SIMULAÇÃO ACADÊMICA: sem cotação, sem transportadora fictícia e sem
    // cobrança de frete. A data de recebimento continua não definida.
    $grupos = [];
    foreach ($itensPorVendedor as $id => $dados) {
        $grupos[$id] = ['id_transportadora' => null, 'valor_frete' => 0.0];
    }
    return ['porVendedor' => $grupos, 'total' => 0.0];
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

function cart_gravar_compra(mysqli $conn, int $idUsuario, array $carrinho, int $idEndereco, ?int $idTransportadoraEscolhida, array $post): array
{
    if (empty($carrinho)) {
        throw new DomainException('Seu carrinho está vazio.');
    }

    // Endereço de entrega: precisa ter sido escolhido/salvo antes (etapa
    // "checkout-endereco") e pertencer ao próprio usuário — nunca confia
    // em id_endereco_entrega vindo direto do POST.

    $stmtEndereco = $conn->prepare('SELECT * FROM enderecos_entrega WHERE id_endereco = ? AND id_usuario = ?');
    $stmtEndereco->bind_param('ii', $idEndereco, $idUsuario);
    $stmtEndereco->execute();
    $endereco = $stmtEndereco->get_result()->fetch_assoc();
    $stmtEndereco->close();
    if (!$endereco) {
        throw new DomainException('Escolha um endereço de entrega válido.');
    }

    $formaPagamento = $post['forma_pagamento'] ?? 'pix';
    // SIMULAÇÃO LOCAL/ACADÊMICA: finalizar PIX confirma a ação do usuário
    // para este projeto. Não há consulta bancária, webhook ou API PIX aqui.
    // Persistimos forma_pagamento='pix'; o pedido logístico nasce aguardando.
    if (!in_array($formaPagamento, ['pix', 'cartao'], true)) {
        throw new DomainException('Escolha uma forma de pagamento válida.');
    }
    $cashbackSolicitado = $post['cashback_usado'] ?? 0;
    if (!is_numeric($cashbackSolicitado) || !is_finite((float) $cashbackSolicitado) || (float) $cashbackSolicitado < 0) {
        throw new DomainException('Informe um valor de cashback válido.');
    }

    $ids = array_map('intval', array_keys($carrinho));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $conn->begin_transaction();
    try {
        // Serializa checkouts do mesmo comprador, inclusive em sessoes distintas.
        $usuario = $conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? FOR UPDATE');
        $usuario->bind_param('i', $idUsuario);
        $usuario->execute();
        $existeUsuario = $usuario->get_result()->fetch_assoc();
        $usuario->close();
        if (!$existeUsuario) throw new DomainException('Entre novamente para finalizar a compra.');
        // Saldo real de cashback do usuário (créditos - débitos, ignorando cancelados).
        $stmtSaldo = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
        $stmtSaldo->bind_param('i', $idUsuario);
        $stmtSaldo->execute();
        $saldoAtual = (float) ($stmtSaldo->get_result()->fetch_assoc()['saldo'] ?? 0);
        $stmtSaldo->close();

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
        foreach ($carrinho as $produtoId => $quantidade) {
            if (!isset($produtosBanco[$produtoId]) || $produtosBanco[$produtoId]['status'] !== 'ativo') {
                throw new DomainException('Um produto ficou indisponível. Revise o carrinho.');
            }
            $p = $produtosBanco[$produtoId];
            $quantidade = filter_var($quantidade, FILTER_VALIDATE_INT);
            // Estoque insuficiente: rejeita a compra inteira em vez de reduzir a
            // quantidade em silêncio, para o cliente não pagar/receber menos do
            // que via no carrinho nem finalizar um pedido maior que o estoque real.
            if ($quantidade === false || $quantidade <= 0 || $quantidade > (int) $p['estoque']) {
                throw new DomainException('Estoque insuficiente. Revise as quantidades do carrinho.');
            }
            if ((float) $p['preco'] < 0 || (float) $p['desconto'] < 0 || (float) $p['desconto'] > 100) {
                throw new DomainException('Um produto está com preço inválido. Revise o carrinho.');
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
            throw new DomainException('Nenhum produto disponível no carrinho.');
        }

        $fretes = cart_calcular_fretes($conn, $itensPorVendedor, $endereco['cep'], $idTransportadoraEscolhida);
        if ($fretes === null && $formaPagamento === 'pix' && cart_pix_local()) {
            $fretes = cart_frete_local_pendente($itensPorVendedor);
        }
        if ($fretes === null) {
            throw new DomainException('Nenhuma entrega disponível para o CEP selecionado.');
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
        throw $e;
    }

    return [
        'id' => $idPedido,
        'transacao' => 'TRX-' . str_pad((string) $idPedido, 4, '0', STR_PAD_LEFT),
        'total' => $totalCompra,
        'frete' => $fretes['total'],
        'cashbackUsado' => $cashbackUsado,
        'cashbackGanho' => $cashbackGanho,
        'formaPagamento' => $formaPagamento,
    ];
}

/** O chamador mantem o lock da sessao PHP durante toda a finalizacao. */
function cart_processar_checkout(mysqli $conn, array &$sessao, array $post): array
{
    $idUsuario = (int) ($sessao['id_usuario'] ?? 0);
    if ($idUsuario <= 0) throw new DomainException('Entre novamente para finalizar a compra.');
    $csrf = $post['csrf_token'] ?? '';
    if (!is_string($csrf) || $csrf === '' || !hash_equals($sessao['csrf_token'] ?? '', $csrf)) {
        throw new DomainException('Sua sessão expirou. Atualize a página e tente novamente.');
    }
    $token = $post['checkout_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($sessao['checkout_token'] ?? '', $token)) {
        throw new DomainException('Este checkout expirou ou já foi enviado. Revise o carrinho e tente novamente.');
    }
    $pedido = cart_gravar_compra($conn, $idUsuario, $sessao['carrinho'] ?? [],
        (int) ($sessao['checkout_endereco_id'] ?? 0),
        isset($sessao['checkout_transportadora_id']) ? (int) $sessao['checkout_transportadora_id'] : null, $post);
    // Consome o token e limpa o carrinho SOMENTE depois do COMMIT.
    $sessao['carrinho'] = [];
    unset($sessao['checkout_endereco_id'], $sessao['checkout_transportadora_id'], $sessao['checkout_token']);
    $sessao['ultimo_pedido'] = $pedido;
    return $pedido;
}

```

ARQUIVO:
assets/js/dashboard.js

CÓDIGO COMPLETO:

```js
/* =========================================================================
   backoffice.js
   Toda a interatividade do painel: troca de perfil (admin/profissional/
   aluno), montagem dinâmica do menu lateral, abertura do modal de
   formulário genérico (cadastro/edição), filtros de tabela, cálculo de
   IMC, simulação de pagamento (Pix/cartão) e exportação de tabela em CSV.

   Depende de duas variáveis globais definidas ANTES deste arquivo, no
   próprio dashboard.php (porque vêm de dados do PHP):
     - BO_CATEGORIAS_OPTIONS  (nomes das categorias de produto)
     - BO_PLANOS_OPTIONS      (nomes dos planos cadastrados)
   ========================================================================= */

/* ---------- Notificações reais do usuário autenticado ---------- */
document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('boNotificationsWrap');
    if (!wrap) return;
    const toggle = document.getElementById('boNotificationsToggle');
    const panel = document.getElementById('boNotificationsPanel');
    const count = document.getElementById('boNotificationsCount');
    const list = document.getElementById('boNotificationsList');
    const empty = document.getElementById('boNotificationsEmpty');
    const readAll = document.getElementById('boNotificationsReadAll');
    const status = document.getElementById('boNotificationsStatus');
    const feedback = document.getElementById('boNotificationsFeedback');
    let busy = false;
    let unread = 0;
    const render = (data) => {
        unread = data.nao_lidas;
        count.textContent = unread > 99 ? '99+' : String(unread);
        count.hidden = unread === 0;
        toggle.setAttribute('aria-label', `Notificações: ${unread} não lidas`);
        readAll.disabled = unread === 0;
        empty.hidden = data.notificacoes.length > 0;
        list.replaceChildren();
        data.notificacoes.forEach(item => {
            const row = document.createElement('li');
            row.className = `bo-notifications-item${item.lida_em ? '' : ' is-unread'}`;
            const title = document.createElement('strong');
            title.className = 'bo-notifications-item-title';
            title.textContent = item.titulo;
            const text = document.createElement('p');
            text.textContent = item.mensagem;
            if (!item.lida_em) {
                const label = document.createElement('span');
                label.className = 'visually-hidden';
                label.textContent = 'Não lida. ';
                text.prepend(label);
            }
            const time = document.createElement('time');
            const date = new Date(item.criada_em.replace(' ', 'T') + 'Z');
            time.dateTime = date.toISOString();
            time.textContent = date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
            row.append(title, text, time);
            // Defesa adicional para links eventualmente inseridos por outros serviços.
            if (typeof item.link === 'string' && /^\/(?!\/)/.test(item.link) && !/[\\\s\u0000-\u001f]/.test(item.link)) {
                const url = new URL(item.link, location.origin);
                if (url.origin === location.origin) {
                    const link = document.createElement('a');
                    link.href = url.href;
                    link.className = 'bo-notifications-item-link';
                    link.textContent = 'Ver detalhes';
                    row.append(link);
                }
            }
            list.append(row);
        });
    };
    const refresh = async (markRead = false) => {
        if (busy) return;
        busy = true;
        readAll.disabled = true;
        panel.setAttribute('aria-busy', 'true');
        try {
            const options = { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } };
            if (markRead) {
                options.method = 'POST';
                options.body = new URLSearchParams({ csrf_token: BO_CSRF_TOKEN });
            }
            const response = await fetch(wrap.dataset.url, options);
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Não foi possível carregar as notificações.');
            render(data);
            feedback.hidden = true;
            if (markRead) {
                panel.focus();
                status.textContent = 'Todas as notificações foram marcadas como lidas.';
            }
        } catch (error) {
            feedback.textContent = error.message || 'Não foi possível atualizar. Reabra as notificações para tentar novamente.';
            feedback.hidden = false;
        } finally {
            busy = false;
            readAll.disabled = unread === 0;
            panel.setAttribute('aria-busy', 'false');
        }
    };
    const close = () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };
    toggle.addEventListener('click', () => {
        if (!panel.hidden) { close(); return; }
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        panel.focus();
        refresh();
    });
    readAll.addEventListener('click', () => refresh(true));
    document.addEventListener('click', event => {
        if (!wrap.contains(event.target)) close();
    });
    document.addEventListener('focusin', event => {
        if (!wrap.contains(event.target)) close();
    });
    wrap.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            close();
            toggle.focus();
        }
    });
    refresh();
    // Atualiza ao retornar à aba e periodicamente, sem requisições sobrepostas.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    window.setInterval(() => { if (!document.hidden) refresh(); }, 60000);
});

/* ---------- Preferências de notificações: salvar cada switch na conta ---------- */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bo-preference]').forEach((input) => {
        let savedValue = input.checked;
        let saving = false;
        input.addEventListener('change', async () => {
            if (saving) return;
            const value = input.checked ? 1 : 0;
            saving = true;
            input.disabled = true;
            input.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(BO_PREFERENCES_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    body: new URLSearchParams({
                        csrf_token: BO_CSRF_TOKEN,
                        key: input.dataset.boPreference,
                        value: String(value),
                    }),
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true) {
                    throw new Error(result.message || 'Não foi possível salvar a preferência.');
                }
                savedValue = value === 1;
                input.checked = savedValue;
            } catch (error) {
                input.checked = savedValue;
                boToast(error instanceof SyntaxError || error instanceof TypeError
                    ? 'Não foi possível confirmar a gravação. Atualize a página e tente novamente.'
                    : error.message);
            } finally {
                saving = false;
                input.disabled = false;
                input.removeAttribute('aria-busy');
            }
        });
    });
});

/* ---------- Perfis de acesso ----------
   Cada perfil define o rótulo mostrado no header e os itens do menu
   lateral (chave da seção + label + ícone Bootstrap Icons). A seção
   correspondente já existe como <section data-perfil="..." data-section="...">
   no HTML (ver components/section-*.php); se não existir, cai no
   fallback "Em construção" (ver boGoToSection). */
const BO_PERFIS = {
    admin: {
        label: 'Administrador',
        menus: [
            { key: 'dashboard', label: 'Visão Geral', icon: 'bi-speedometer2' },
            { key: 'usuarios', label: 'Usuários', icon: 'bi-people' },
            { key: 'permissoes', label: 'Permissões', icon: 'bi-shield-lock' },
            { key: 'funcoes', label: 'Funções', icon: 'bi-diagram-3' },
            { key: 'pagamentos', label: 'Pagamentos', icon: 'bi-credit-card' },
            { key: 'cashbacks', label: 'Cashbacks', icon: 'bi-wallet2' },
            { key: 'categorias', label: 'Categorias', icon: 'bi-tags' },
            { key: 'produtos', label: 'Produtos', icon: 'bi-box-seam' },
            { key: 'vendas', label: 'Vendas Marketplace', icon: 'bi-truck' },
            { key: 'planos', label: 'Cadastro de Planos', icon: 'bi-clipboard-check' },
            { key: 'profissionais', label: 'Profissionais', icon: 'bi-person-badge' },
            { key: 'modalidades', label: 'Modalidades', icon: 'bi-activity' },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    vendedor: {
        label: 'Vendedor',
        menus: [
            { key: 'vendas', label: 'Vendas Marketplace', icon: 'bi-truck' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    profissional: {
        label: 'Profissional',
        menus: [
            { key: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
            { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
            { key: 'alunos', label: 'Alunos', icon: 'bi-people' },
            { key: 'agenda', label: 'Agenda', icon: 'bi-calendar3' },
            { key: 'cashback', label: 'Meu cashback', icon: 'bi-wallet2' },
            { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    aluno: {
        label: 'Aluno',
        menus: [
            { key: 'perfil', label: 'Perfil', icon: 'bi-person-circle' },
            { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
            { key: 'cashback', label: 'Cashback', icon: 'bi-wallet2' },
            { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
            { key: 'treino', label: 'Treino', icon: 'bi-lightning-charge' },
            { key: 'agenda', label: 'Minha agenda', icon: 'bi-calendar3' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
};

// Estado atual da tela: qual perfil está sendo visualizado e qual seção do menu.
// boPerfilAtual começa no perfil REAL do usuário logado (BO_PERFIL_LOGADO,
// definido no <script> inline do dashboard.php a partir da sessão/tipo_usuario)
// — só o admin pode trocar isso depois, pelo dropdown do header.
let boPerfilAtual = (typeof BO_PERFIL_LOGADO !== 'undefined') ? BO_PERFIL_LOGADO : 'aluno';
let boSectionAtual = null; // definida no DOMContentLoaded, com base no 1º item do menu do perfil
let boFormModalInstance = null; // instância do Modal do Bootstrap (definida no DOMContentLoaded)

// Converte todos os códigos ISO 3166-1 em nomes de países no idioma do painel.
// O nome selecionado é armazenado como nacionalidade no perfil do usuário.
const BO_COUNTRY_CODES = `AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW`.split(' ');
const boRegionNames = typeof Intl.DisplayNames === 'function'
    ? new Intl.DisplayNames(['pt-BR'], { type: 'region' })
    : null;
const BO_NATIONALITY_OPTIONS = BO_COUNTRY_CODES
    .map((code) => boRegionNames ? boRegionNames.of(code) : code)
    .sort((a, b) => a.localeCompare(b, 'pt-BR'));

/* ---------- Esquemas do modal de formulário genérico ----------
   Cada chave corresponde ao 1º argumento passado em boOpenForm(schemaKey, ...)
   nos botões "onclick" do HTML. A lista de campos aqui é usada por
   boBuildField() para montar o formulário dinamicamente dentro do modal
   #boFormModal, sem precisar de um modal HTML diferente para cada tela. */
const BO_FORM_SCHEMAS = {
    alunoDoProfissionalForm: [
        { key: 'nome', label: 'Nome', type: 'text', col: 12 },
        { key: 'contato', label: 'Contato', type: 'text', col: 6 },
        { key: 'plano', label: 'Plano', type: 'text', col: 6 },
        { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
        { key: 'valor', label: 'Valor', type: 'number', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    agendaDisponivel: [
        { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
        { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
    ],
    agendaAgendar: [
        { key: 'aluno', label: 'Aluno', type: 'text', col: 12 },
        { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
        { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    utilizarCashback: [
        { key: 'valor', label: 'Valor a utilizar', type: 'number', col: 12 },
    ],
    planoAlterar: [
        { key: 'plano', label: 'Novo plano', type: 'select', options: BO_PLANOS_OPTIONS, col: 12 },
    ],
    perfilEdit: [
        { key: 'nome', label: 'Nome', type: 'text', col: 6, required: true },
        { key: 'documento', label: 'Documento', type: 'text', col: 6, required: true },
        { key: 'email', label: 'E-mail', type: 'email', col: 6, required: true },
        { key: 'telefone', label: 'Telefone', type: 'text', col: 6, required: true },
        { key: 'nacionalidade', label: 'Nacionalidade', type: 'select', options: BO_NATIONALITY_OPTIONS, col: 6, required: true },
        { key: 'nascimento', label: 'Data de nascimento', type: 'date', col: 6, required: true },
        { key: 'genero', label: 'Gênero', type: 'select', options: ['masculino', 'feminino', 'outro'], optionLabels: ['Masculino', 'Feminino', 'Outro'], col: 6, required: true },
        { key: 'endereco', label: 'Endereço', type: 'text', col: 12, required: true },
        { key: 'cidade', label: 'Cidade', type: 'text', col: 6, required: true },
        { key: 'estado', label: 'Estado (UF)', type: 'text', col: 6, required: true },
        { key: 'altura', label: 'Altura (m)', type: 'number', col: 6, min: 0.5, max: 3, step: 0.01 },
        { key: 'peso', label: 'Peso (kg)', type: 'number', col: 6, min: 1, max: 500, step: 0.1 },
        { key: 'foto', label: 'URL da foto', type: 'url', col: 12 },
    ],
    treinoExercicio: [
        { key: 'nome', label: 'Exercício', type: 'text', col: 12 },
        { key: 'series', label: 'Séries', type: 'number', col: 4 },
        { key: 'repeticoes', label: 'Repetições', type: 'number', col: 4 },
        { key: 'carga', label: 'Carga (kg)', type: 'number', col: 4 },
    ],
};

/**
 * Cria o elemento de UM campo do formulário (label + input/select/textarea/
 * checklist/upload de imagem), de acordo com o "type" definido no schema
 * acima. É chamada uma vez por campo dentro de boOpenForm().
 */
function boBuildField(field) {
    const wrap = document.createElement('div');
    wrap.className = 'col-' + (field.col || 12);

    const label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = field.label;
    wrap.appendChild(label);

    if (field.type === 'select') {
        const select = document.createElement('select');
        select.className = 'form-select';
        select.setAttribute('data-bo-field', field.key);
        field.options.forEach((opt, i) => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = (field.optionLabels && field.optionLabels[i]) || opt;
            select.appendChild(o);
        });
        wrap.appendChild(select);
    } else if (field.type === 'textarea') {
        const ta = document.createElement('textarea');
        ta.className = 'form-control';
        ta.rows = 3;
        ta.setAttribute('data-bo-field', field.key);
        wrap.appendChild(ta);
    } else if (field.type === 'checklist') {
        // Grupo de checkboxes (ex: permissões de uma função)
        const box = document.createElement('div');
        box.className = 'd-flex flex-wrap gap-3';
        field.options.forEach((opt) => {
            const id = 'chk_' + field.key + '_' + opt.replace(/\s+/g, '');
            const chkWrap = document.createElement('div');
            chkWrap.className = 'form-check';
            chkWrap.innerHTML = `<input class="form-check-input" type="checkbox" id="${id}" value="${opt}" data-bo-checklist="${field.key}"><label class="form-check-label" for="${id}">${opt}</label>`;
            box.appendChild(chkWrap);
        });
        wrap.appendChild(box);
    } else if (field.type === 'image') {
        // Campo de imagem: aceita tanto uma URL digitada quanto upload de
        // arquivo local (convertido para base64 e mostrado na pré-visualização)
        const url = document.createElement('input');
        url.type = 'text';
        url.className = 'form-control mb-2';
        url.placeholder = 'URL da imagem';
        url.setAttribute('data-bo-field', field.key);
        wrap.appendChild(url);

        const file = document.createElement('input');
        file.type = 'file';
        file.accept = 'image/*';
        file.className = 'form-control mb-2';
        wrap.appendChild(file);

        const preview = document.createElement('img');
        preview.setAttribute('data-bo-preview', field.key);
        wrap.appendChild(preview);

        url.addEventListener('input', () => {
            if (url.value) {
                preview.src = url.value;
                preview.style.display = 'block';
            }
        });
        file.addEventListener('change', () => {
            const f = file.files[0];
            if (f) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    url.value = ''; // upload tem prioridade sobre a URL digitada
                };
                reader.readAsDataURL(f);
            }
        });
    } else {
        // text / email / date / number (padrão)
        const input = document.createElement('input');
        input.type = field.type;
        input.className = 'form-control';
        input.setAttribute('data-bo-field', field.key);
        if (field.placeholder) input.placeholder = field.placeholder;
        if (field.readonly) input.readOnly = true;
        wrap.appendChild(input);
    }

    const control = wrap.querySelector(`[data-bo-field="${field.key}"]`);
    if (control) {
        if (field.required) control.required = true;
        if (field.min !== undefined) control.min = field.min;
        if (field.max !== undefined) control.max = field.max;
        if (field.step !== undefined) control.step = field.step;
    }

    return wrap;
}

/**
 * Abre o modal genérico de formulário (#boFormModal), monta os campos
 * do schema indicado, preenche com "values" (quando for edição) e
 * prepara o botão "Salvar". Chamada pelos botões "Novo X" / "Editar" no HTML.
 *
 * @param {string} schemaKey  chave em BO_FORM_SCHEMAS (ex: 'produtoForm')
 * @param {string} title      título mostrado no cabeçalho do modal
 * @param {object} values     valores já existentes (edição) ou {} (novo)
 * @param {object} options    { doubleConfirm: true } exige clicar 2x em
 *                             "Salvar" antes de confirmar (usado em ações
 *                             sensíveis, ex: permissões)
 */
function boOpenForm(schemaKey, title, values, options) {
    values = values || {};
    options = options || {};

    const form = document.getElementById('boFormModalForm');
    form.innerHTML = '';
    document.getElementById('boFormModalTitle').textContent = title;

    const fields = BO_FORM_SCHEMAS[schemaKey] || [];
    fields.forEach((field) => form.appendChild(boBuildField(field)));

    // Preenche os campos recém-criados com os valores atuais do registro
    fields.forEach((field) => {
        if (field.type === 'checklist') {
            const selected = (values[field.key] || '').split(',').map((s) => s.trim());
            form.querySelectorAll(`[data-bo-checklist="${field.key}"]`).forEach((chk) => {
                chk.checked = selected.includes(chk.value);
            });
            return;
        }
        const el = form.querySelector(`[data-bo-field="${field.key}"]`);
        if (el && values[field.key] !== undefined) {
            // Preserva valores antigos que ainda não façam parte da lista atual.
            if (field.type === 'select' && values[field.key] && !Array.from(el.options).some((option) => option.value === values[field.key])) {
                el.add(new Option(values[field.key], values[field.key]));
            }
            el.value = values[field.key];
        }
        if (field.type === 'image' && values[field.key]) {
            const preview = form.querySelector(`[data-bo-preview="${field.key}"]`);
            if (preview) {
                preview.src = values[field.key];
                preview.style.display = 'block';
            }
        }
    });

    // Recria o botão "Salvar" a cada abertura para não acumular listeners antigos
    const oldSaveBtn = document.getElementById('boFormModalSave');
    const saveBtn = oldSaveBtn.cloneNode(true);
    oldSaveBtn.parentNode.replaceChild(saveBtn, oldSaveBtn);
    saveBtn.textContent = 'Salvar';

    let confirmStep = 0;
    saveBtn.addEventListener('click', async () => {
        if (options.doubleConfirm && confirmStep === 0) {
            confirmStep = 1;
            saveBtn.textContent = 'Clique novamente para confirmar';
            return;
        }

        if (!form.reportValidity()) return;

        if (schemaKey === 'perfilEdit') {
            const profileValues = {};
            fields.forEach((field) => {
                const input = form.querySelector(`[data-bo-field="${field.key}"]`);
                profileValues[field.key] = input ? input.value.trim() : '';
            });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';

            try {
                const response = await fetch(BO_PROFILE_UPDATE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...profileValues, csrf_token: BO_CSRF_TOKEN }),
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Não foi possível atualizar o perfil.');

                Object.assign(BO_CURRENT_USER, profileValues);
                document.getElementById('boProfileName').textContent = profileValues.nome;
                document.getElementById('boProfileEmail').textContent = profileValues.email;
                const gender = document.getElementById('boProfileGender');
                if (gender) gender.textContent = `Gênero: ${profileValues.genero.charAt(0).toUpperCase()}${profileValues.genero.slice(1)}`;
                document.getElementById('boAvatar').textContent = profileValues.nome.charAt(0).toUpperCase();

                boFormModalInstance.hide();
                boToast(result.message);
            } catch (error) {
                boToast(error.message);
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar';
            }
            return;
        }

        boFormModalInstance.hide();
        boToast('Alterações salvas.');
    });

    boFormModalInstance.show();
}

/**
 * Mostra um aviso flutuante (toast) no canto inferior direito por ~2,5s.
 */
function boToast(msg) {
    const toast = document.getElementById('boToast');
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(window._boToastTimer);
    window._boToastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
}

/**
 * Reconstrói os itens do menu lateral (#boNav) de acordo com o perfil
 * ativo (boPerfilAtual), marcando o item da seção atual como "active".
 */
function boRenderSidebar() {
    const nav = document.getElementById('boNav');
    nav.innerHTML = '';
    BO_PERFIS[boPerfilAtual].menus.forEach((item) => {
        const itemElement = document.createElement(item.href ? 'a' : 'button');
        if (item.href) {
            itemElement.href = item.href;
        } else {
            itemElement.type = 'button';
            itemElement.addEventListener('click', () => boGoToSection(item.key));
        }
        itemElement.className = 'bo-nav-item' + (item.key === boSectionAtual ? ' active' : '');
        itemElement.setAttribute('data-section', item.key);
        itemElement.innerHTML = `<i class="bi ${item.icon}"></i><span>${item.label}</span>`;
        nav.appendChild(itemElement);
    });
}

/**
 * Reconstrói o dropdown "Administrador / Profissional / Aluno" do header,
 * marcando o perfil ativo. (Esse seletor existe só para navegar entre as
 * 3 visões durante o desenvolvimento/testes do backoffice.)
 */
function boRenderPerfilMenu() {
    // Só o admin vê/usa o seletor de perfil (ver header.php) — pra qualquer
    // outro perfil, #boPerfilMenu existe só como placeholder vazio (.d-none).
    if (!BO_IS_ADMIN) return;

    const menu = document.getElementById('boPerfilMenu');
    menu.innerHTML = '';
    Object.keys(BO_PERFIS).forEach((key) => {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item' + (key === boPerfilAtual ? ' active' : '');
        link.setAttribute('data-perfil', key);
        link.textContent = BO_PERFIS[key].label;
        li.appendChild(link);
        menu.appendChild(li);
    });
}

/* ---------- Perfil e busca global ---------- */
const BO_SEARCH_ALIASES = {
    dashboard: ['início', 'inicio', 'visão geral', 'resumo'],
    perfil: ['meu perfil', 'conta', 'dados cadastrais', 'editar perfil'],
    historico: ['histórico', 'historico', 'pagamentos', 'movimentações', 'movimentacoes'],
    cashback: ['saldo', 'benefícios', 'beneficios'],
    compras: ['minhas compras', 'pedidos', 'compras', 'histórico de compras'],
    treino: ['treinos', 'exercícios', 'exercicios', 'ficha'],
    agenda: ['agenda', 'agendamentos', 'horários', 'horarios'],
    configuracoes: ['configurações', 'configuracoes', 'ajustes', 'tema', 'conta'],
    profissionais: ['profissionais', 'equipe', 'personal trainer', 'nutricionista'],
    usuarios: ['usuários', 'usuarios', 'alunos'],
    planos: ['planos', 'assinaturas'],
    pagamentos: ['pagamentos', 'financeiro'],
};

let boSearchItems = [];
let boSearchActiveIndex = -1;
let boSearchDebounceTimer = null;

function boNormalizeSearch(value) {
    return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function boCloseSearch() {
    const results = document.getElementById('boSearchResults');
    const input = document.getElementById('boHeaderSearch');
    if (!results || !input) return;
    results.hidden = true;
    results.innerHTML = '';
    input.setAttribute('aria-expanded', 'false');
    clearTimeout(boSearchDebounceTimer);
    boSearchItems = [];
    boSearchActiveIndex = -1;
}

function boGetSearchPages() {
    const pages = BO_PERFIS[boPerfilAtual].menus.map((item) => ({
        type: 'page',
        key: item.key,
        href: item.href,
        title: item.label,
        subtitle: item.href ? 'Abrir Marketplace' : 'Página do painel',
        icon: item.icon,
        terms: [item.label, ...(BO_SEARCH_ALIASES[item.key] || [])],
    }));

    if (!pages.some((page) => page.key === 'perfil')) {
        pages.unshift({
            type: 'page', key: 'perfil', title: 'Meu perfil', subtitle: 'Dados da sua conta', icon: 'bi-person-circle',
            terms: ['perfil', ...(BO_SEARCH_ALIASES.perfil || [])],
        });
    }

    return pages;
}

function boBuildSearchResult(result, index) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'bo-search-result' + (index === boSearchActiveIndex ? ' is-active' : '');
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', index === boSearchActiveIndex ? 'true' : 'false');
    button.innerHTML = `<i class="bi ${result.icon}"></i><span><strong></strong><small></small></span>`;
    button.querySelector('strong').textContent = result.title;
    button.querySelector('small').textContent = result.subtitle;
    button.addEventListener('click', () => boOpenSearchResult(result));
    return button;
}

function boRenderSearch(query) {
    const resultsBox = document.getElementById('boSearchResults');
    const input = document.getElementById('boHeaderSearch');
    if (!resultsBox || !input) return;

    const term = boNormalizeSearch(query);
    if (!term) {
        boCloseSearch();
        return;
    }

    const pages = boGetSearchPages().filter((page) => boNormalizeSearch(page.terms.join(' ')).includes(term));
    const professionals = (typeof BO_PROFISSIONAIS_SEARCH !== 'undefined' ? BO_PROFISSIONAIS_SEARCH : [])
        .filter((professional) => boNormalizeSearch(`${professional.nome} ${professional.funcao} ${professional.especialidade}`).includes(term))
        .map((professional) => ({
            type: 'professional', id: professional.id, title: professional.nome,
            subtitle: professional.especialidade || professional.funcao, icon: 'bi-person-badge',
        }));

    boSearchItems = [...professionals, ...pages];
    boSearchActiveIndex = boSearchItems.length ? 0 : -1;
    resultsBox.innerHTML = '';

    const appendGroup = (label, group, offset) => {
        if (!group.length) return;
        const heading = document.createElement('span');
        heading.className = 'bo-search-group-label';
        heading.textContent = label;
        resultsBox.appendChild(heading);
        group.forEach((item, index) => resultsBox.appendChild(boBuildSearchResult(item, offset + index)));
    };

    if (boSearchItems.length) {
        appendGroup('Profissionais', professionals, 0);
        appendGroup('Páginas', pages, professionals.length);
    } else {
        const empty = document.createElement('span');
        empty.className = 'bo-search-empty';
        empty.textContent = 'Nenhum resultado encontrado';
        resultsBox.appendChild(empty);
    }

    resultsBox.hidden = false;
    input.setAttribute('aria-expanded', 'true');
}

function boOpenSearchResult(result) {
    if (result.href) {
        window.location.assign(result.href);
    } else if (result.type === 'professional') {
        boShowProfessional(result.id);
    } else {
        boGoToSection(result.key);
    }
    document.getElementById('boHeaderSearch').value = '';
    boCloseSearch();
}

function boOpenProfileEdit() {
    boOpenForm('perfilEdit', 'Editar perfil', typeof BO_CURRENT_USER !== 'undefined' ? BO_CURRENT_USER : {});
}

function boShowProfessional(id, updateRoute = true) {
    const professional = (typeof BO_PROFISSIONAIS_SEARCH !== 'undefined' ? BO_PROFISSIONAIS_SEARCH : [])
        .find((item) => Number(item.id) === Number(id));
    if (!professional) {
        boGoToSection(BO_PERFIS[boPerfilAtual].menus[0].key, false);
        return false;
    }

    let section = document.getElementById('boProfessionalProfileSection');
    if (!section) {
        section = document.createElement('section');
        section.id = 'boProfessionalProfileSection';
        section.className = 'bo-content-section';
        document.querySelector('.bo-main').appendChild(section);
    }
    section.innerHTML = '<div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-badge"></i> Profissional</span><h1></h1><p></p></div></div><div class="bo-settings-card bo-profile-settings"><div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person-workspace"></i></span><div><h2></h2><p></p></div></div></div>';
    section.querySelector('.bo-page-title h1').textContent = professional.nome;
    section.querySelector('.bo-page-title p').textContent = 'Perfil profissional disponível no painel ONE FIT.';
    section.querySelector('.bo-settings-heading h2').textContent = professional.funcao;
    section.querySelector('.bo-settings-heading p').textContent = professional.especialidade || professional.funcao;

    document.querySelectorAll('.bo-content-section').forEach((item) => item.classList.remove('active'));
    section.classList.add('active');
    boSectionAtual = 'profissional';
    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => btn.classList.remove('active'));
    document.getElementById('boSidebar').classList.remove('active');
    document.getElementById('boSidebarBackdrop').classList.remove('active');

    if (updateRoute) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', 'profissional');
        url.searchParams.set('profissional', professional.id);
        window.history.pushState({}, '', url);
    }
    return true;
}

/**
 * Troca a seção visível dentro do perfil atual. Procura uma
 * <section data-perfil="X" data-section="Y"> já pronta no HTML; se não
 * existir, mostra a seção de fallback "Em construção" com o título certo.
 */
function boGoToSection(sectionKey, updateRoute = true) {
    const isSpecialSection = sectionKey === 'configuracoes' || sectionKey === 'perfil';
    if (!isSpecialSection && !BO_PERFIS[boPerfilAtual].menus.some((item) => item.key === sectionKey)) return;
    boSectionAtual = sectionKey;

    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => {
        btn.classList.toggle('active', btn.getAttribute('data-section') === sectionKey);
    });

    const prebuilt = sectionKey === 'configuracoes'
        ? document.getElementById('boSettingsSection')
        : sectionKey === 'perfil'
            ? document.getElementById('boProfileSection')
            : document.querySelector(`.bo-content-section[data-perfil="${boPerfilAtual}"][data-section="${sectionKey}"]`);

    document.querySelectorAll('.bo-content-section').forEach((section) => section.classList.remove('active'));

    if (prebuilt) {
        prebuilt.classList.add('active');
    } else {
        const item = BO_PERFIS[boPerfilAtual].menus.find((m) => m.key === sectionKey);
        document.getElementById('boStubTitle').textContent = item ? item.label : '';
        document.getElementById('boStubDesc').textContent = 'Esta tela ainda será detalhada para o perfil ' + BO_PERFIS[boPerfilAtual].label + '.';
        document.getElementById('boStubIcon').className = 'bi ' + (item ? item.icon : 'bi-hourglass-split');
        document.getElementById('boStubSection').classList.add('active');
    }

    // Fecha a sidebar mobile ao navegar (não faz nada se já estiver fechada/desktop)
    document.getElementById('boSidebar').classList.remove('active');
    document.getElementById('boSidebarBackdrop').classList.remove('active');

    if (updateRoute) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', sectionKey);
        url.searchParams.delete('profissional');
        window.history.pushState({}, '', url);
    }
}

/**
 * Troca o perfil ativo (admin/profissional/aluno), atualiza header,
 * remonta o menu lateral e abre a primeira seção do novo perfil.
 */
function boTrocarPerfil(perfilKey) {
    // Segunda camada de proteção: mesmo que alguém force a chamada dessa
    // função pelo console do navegador, só o admin consegue trocar de perfil.
    // A proteção "de verdade" é o servidor só mandar o HTML das seções que
    // o tipo_usuario da sessão tem direito a ver (ver dashboard.php).
    if (!BO_IS_ADMIN) return;
    if (!BO_PERFIS[perfilKey] || perfilKey === boPerfilAtual) return;

    boPerfilAtual = perfilKey;
    document.getElementById('boPerfilLabel').textContent = BO_PERFIS[perfilKey].label;
    document.getElementById('boAvatar').textContent = BO_PERFIS[perfilKey].label.charAt(0);

    boRenderSidebar();
    boRenderPerfilMenu();
    boGoToSection(BO_PERFIS[perfilKey].menus[0].key);
}

/* ---------- Inicialização geral (menu, sidebar mobile, filtros, ações de tabela) ---------- */
document.addEventListener('DOMContentLoaded', () => {
    boFormModalInstance = new bootstrap.Modal(document.getElementById('boFormModal'));

    boRenderSidebar();
    boRenderPerfilMenu();
    // A primeira seção depende do perfil: admin/profissional começam em
    // "dashboard", mas o Aluno não tem essa chave — o dele é "perfil".
    // Por isso pegamos sempre o primeiro item do MENU DO PERFIL ATUAL,
    // em vez de um valor fixo.
    const routeParams = new URLSearchParams(window.location.search);
    const routeSection = routeParams.get('section');
    const routeProfessional = routeParams.get('profissional');
    if (routeSection === 'profissional' && routeProfessional) {
        boShowProfessional(routeProfessional, false);
    } else {
        const initialSection = (routeSection === 'perfil' || routeSection === 'configuracoes' || BO_PERFIS[boPerfilAtual].menus.some((item) => item.key === routeSection))
            ? routeSection
            : BO_PERFIS[boPerfilAtual].menus[0].key;
        boGoToSection(initialSection, false);
    }

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('onefit-theme', theme); } catch (e) { /* armazenamento indisponível */ }
        try { document.cookie = 'onefit_theme=' + theme + '; path=/; max-age=31536000'; } catch (e) { /* cookie indisponível */ }
        document.querySelectorAll('[data-bo-theme]').forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-bo-theme') === theme);
        });
    };

    let savedTheme = 'dark';
    try { savedTheme = localStorage.getItem('onefit-theme') || 'dark'; } catch (e) { /* usa o padrão escuro */ }
    applyTheme(savedTheme === 'light' ? 'light' : 'dark');
    document.querySelectorAll('[data-bo-theme]').forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.getAttribute('data-bo-theme')));
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.getElementById('boHeaderSearch')?.focus();
        }
    });

    const searchInput = document.getElementById('boHeaderSearch');
    const searchWrap = document.getElementById('boHeaderSearchWrap');
    searchInput.addEventListener('input', () => {
        clearTimeout(boSearchDebounceTimer);
        boSearchDebounceTimer = setTimeout(() => boRenderSearch(searchInput.value), 180);
    });
    searchInput.addEventListener('search', () => {
        if (!searchInput.value) boCloseSearch();
    });
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            boCloseSearch();
            searchInput.blur();
            return;
        }
        if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key) || !boSearchItems.length) return;
        event.preventDefault();
        if (event.key === 'Enter') {
            boOpenSearchResult(boSearchItems[boSearchActiveIndex < 0 ? 0 : boSearchActiveIndex]);
            return;
        }
        const nextIndex = event.key === 'ArrowDown'
            ? (boSearchActiveIndex + 1) % boSearchItems.length
            : (boSearchActiveIndex - 1 + boSearchItems.length) % boSearchItems.length;
        boRenderSearch(searchInput.value);
        // boRenderSearch seleciona o primeiro resultado por padrão; restaura
        // a seleção escolhida pelo teclado para manter a navegação previsível.
        boSearchActiveIndex = nextIndex;
        document.querySelectorAll('.bo-search-result').forEach((button, index) => {
            const active = index === boSearchActiveIndex;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    });

    const avatar = document.getElementById('boAvatar');
    const userMenu = document.getElementById('boUserMenu');
    const userMenuWrap = document.getElementById('boUserMenuWrap');
    const closeUserMenu = () => {
        userMenu.classList.remove('is-open');
        userMenu.setAttribute('aria-hidden', 'true');
        avatar.setAttribute('aria-expanded', 'false');
    };
    avatar.addEventListener('click', () => {
        const willOpen = !userMenu.classList.contains('is-open');
        userMenu.classList.toggle('is-open', willOpen);
        userMenu.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
        avatar.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!searchWrap.contains(event.target)) boCloseSearch();
        if (!userMenuWrap.contains(event.target)) closeUserMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeUserMenu();
    });

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('section') === 'profissional' && params.get('profissional')) {
            boShowProfessional(params.get('profissional'), false);
        } else {
            boGoToSection(params.get('section') || BO_PERFIS[boPerfilAtual].menus[0].key, false);
        }
    });

    // Clique num item do dropdown de perfil (header) -> troca de perfil.
    // #boPerfilMenu só existe quando o seletor de perfil está no header
    // (hoje BO_IS_ADMIN é sempre false, então o elemento nem é renderizado).
    const perfilMenuEl = document.getElementById('boPerfilMenu');
    if (perfilMenuEl) {
        perfilMenuEl.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-perfil]');
            if (!link) return;
            event.preventDefault();
            boTrocarPerfil(link.getAttribute('data-perfil'));
        });
    }

    // Botão hambúrguer (mobile) abre/fecha a sidebar; clicar fora também fecha
    const sidebar = document.getElementById('boSidebar');
    const backdrop = document.getElementById('boSidebarBackdrop');
    const toggle = document.getElementById('boSidebarToggle');

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    });
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('active');
        backdrop.classList.remove('active');
    });

    /* ---- Filtros de tabela ----
       Para cada <table data-bo-table="X">, procura os inputs/selects com
       data-bo-target="X" (busca, status, tipo, data-de, data-até) e
       esconde/mostra as linhas conforme os atributos data-* de cada <tr>
       (data-search, data-status, data-type, data-date) definidos no PHP. */
    document.querySelectorAll('[data-bo-table]').forEach((table) => {
        const filterId = table.getAttribute('data-bo-table');
        const searchInput = document.querySelector(`[data-bo-filter="search"][data-bo-target="${filterId}"]`);
        const statusSelect = document.querySelector(`[data-bo-filter="status"][data-bo-target="${filterId}"]`);
        const typeSelect = document.querySelector(`[data-bo-filter="type"][data-bo-target="${filterId}"]`);
        const dateFrom = document.querySelector(`[data-bo-filter="date-from"][data-bo-target="${filterId}"]`);
        const dateTo = document.querySelector(`[data-bo-filter="date-to"][data-bo-target="${filterId}"]`);
        const emptyRow = table.querySelector('.bo-empty-row');

        const applyFilters = () => {
            const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const status = statusSelect ? statusSelect.value : '';
            const type = typeSelect ? typeSelect.value : '';
            const from = dateFrom ? dateFrom.value : '';
            const to = dateTo ? dateTo.value : '';
            let visibleCount = 0;

            table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => {
                const haystack = row.getAttribute('data-search') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const rowType = row.getAttribute('data-type') || '';
                const rowDate = row.getAttribute('data-date') || '';

                const matchesTerm = term === '' || haystack.toLowerCase().includes(term);
                const matchesStatus = status === '' || rowStatus === status;
                const matchesType = type === '' || rowType === type;
                const matchesFrom = from === '' || rowDate === '' || rowDate >= from;
                const matchesTo = to === '' || rowDate === '' || rowDate <= to;

                const visible = matchesTerm && matchesStatus && matchesType && matchesFrom && matchesTo;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount += 1;
            });

            if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        };

        [searchInput, statusSelect, typeSelect, dateFrom, dateTo].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });
    });

    /* ---- Ações de tabela (delegadas no <body> pois as linhas são dinâmicas) ---- */
    document.body.addEventListener('click', (event) => {

        // Pausar/Ativar — usado só pelas telas sem persistência real (ex:
        // agenda do profissional). No admin isso agora é um <form> em PHP
        // (ver includes/admin-forms.php), sem passar por aqui.
        const toggleBtn = event.target.closest('[data-bo-action="toggle-status"]');
        if (toggleBtn) {
            const row = toggleBtn.closest('tr');
            const badge = row.querySelector('.bo-badge');
            const active = badge.classList.contains('bo-badge-active');
            const onLabel = toggleBtn.getAttribute('data-on') || 'Ativo';
            const offLabel = toggleBtn.getAttribute('data-off') || 'Inativo';

            badge.classList.toggle('bo-badge-active', !active);
            badge.classList.toggle('bo-badge-inactive', active);
            badge.textContent = active ? offLabel : onLabel;
            row.setAttribute('data-status', active ? 'inativo' : 'ativo');
            toggleBtn.innerHTML = `<i class="bi ${active ? 'bi-play-circle' : 'bi-pause-circle'}"></i>`;
            toggleBtn.title = active ? 'Ativar' : 'Pausar/Inativar';
        }

        // Excluir — usado só pelas telas sem persistência real (ex: ficha de
        // treino do aluno). No admin isso agora é um modal Bootstrap centralizado
        // (ver bo_botao_excluir/bo_modal_confirmar_exclusao em includes/admin-forms.php).
        const deleteBtn = event.target.closest('[data-bo-action="delete"]');
        if (deleteBtn) {
            const label = deleteBtn.getAttribute('data-bo-name') || 'este registro';
            if (window.confirm(`Tem certeza que deseja excluir ${label}?`)) {
                deleteBtn.closest('tr').remove();
                boToast('Registro excluído.');
            }
        }

        // Limpar tabela inteira (ex: "Limpar Treino")
        const clearBtn = event.target.closest('[data-bo-action="clear-table"]');
        if (clearBtn) {
            const tableSel = clearBtn.getAttribute('data-bo-target-table');
            const table = document.querySelector(`[data-bo-table="${tableSel}"]`);
            if (table && window.confirm('Tem certeza que deseja limpar todos os itens?')) {
                table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => row.remove());
                const emptyRow = table.querySelector('.bo-empty-row');
                if (emptyRow) emptyRow.style.display = '';
                boToast('Lista limpa.');
            }
        }

        // Remover card de agenda (horário agendado ou disponível)
        const removeCardBtn = event.target.closest('[data-bo-remove-card]');
        if (removeCardBtn) {
            if (window.confirm('Remover este horário?')) {
                removeCardBtn.closest('.bo-agenda-card').remove();
            }
        }

        // Exportar tabela visível para CSV
        const exportBtn = event.target.closest('[data-bo-export]');
        if (exportBtn) {
            boExportTableCsv(exportBtn.getAttribute('data-bo-export'));
        }
    });
});

/* ---------- Cálculo de IMC (tela "Perfil" do aluno) ---------- */
function boCalcularIMC() {
    const altura = parseFloat(document.getElementById('imcAltura').value);
    const peso = parseFloat(document.getElementById('imcPeso').value);
    const resultado = document.getElementById('imcResultado');

    if (!altura || !peso) {
        resultado.textContent = 'Informe altura e peso.';
        return;
    }

    const imc = peso / (altura * altura);
    let status = 'Normal';
    if (imc < 18.5) status = 'Abaixo do peso';
    else if (imc >= 25 && imc < 30) status = 'Sobrepeso';
    else if (imc >= 30) status = 'Obesidade';

    resultado.textContent = imc.toFixed(1) + ' · ' + status;
}

/* ---------- Modal "Pagar plano" (Pix simulado / cartão) ---------- */
document.addEventListener('DOMContentLoaded', () => {
    const painelPix = document.getElementById('painelPix');
    const painelCartao = document.getElementById('painelCartao');
    const metodoPix = document.getElementById('metodoPix');

    // Alterna entre o painel de Pix e o painel de cartão conforme o método escolhido
    document.querySelectorAll('input[name="metodoPagamento"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const isPix = metodoPix.checked;
            painelPix.style.display = isPix ? 'block' : 'none';
            painelCartao.style.display = isPix ? 'none' : 'block';
        });
    });

    // "Gerar QR Code" apenas revela o bloco simulado (não gera Pix real)
    const btnGerarQr = document.getElementById('btnGerarQr');
    if (btnGerarQr) {
        btnGerarQr.addEventListener('click', () => {
            document.getElementById('pixResultado').style.display = 'block';
        });
    }

    // Copia o código "copia e cola" do Pix para a área de transferência
    const btnCopiarPix = document.getElementById('btnCopiarPix');
    if (btnCopiarPix) {
        btnCopiarPix.addEventListener('click', () => {
            const campo = document.getElementById('pixCopiaCola');
            campo.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(campo.value).then(() => boToast('Código Pix copiado.'));
            } else {
                document.execCommand('copy');
                boToast('Código Pix copiado.');
            }
        });
    }

    // "Pagar" apenas fecha o modal e mostra o toast (pagamento simulado)
    const btnPagar = document.getElementById('btnPagar');
    if (btnPagar) {
        btnPagar.addEventListener('click', () => {
            const modalEl = document.getElementById('modalPagarPlano');
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            boToast('Pagamento simulado com sucesso!');
        });
    }
});

/**
 * Exporta as linhas visíveis (não filtradas) de uma tabela para um
 * arquivo .csv baixado pelo navegador.
 */
function boExportTableCsv(tableId) {
    const table = document.querySelector(`[data-bo-table="${tableId}"]`);
    if (!table) return;

    const rows = [];
    table.querySelectorAll('thead tr').forEach((tr) => {
        const cols = Array.from(tr.querySelectorAll('th')).map((th) => `"${th.textContent.trim()}"`);
        rows.push(cols.join(';'));
    });
    table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((tr) => {
        if (tr.style.display === 'none') return; // não exporta linhas escondidas pelo filtro
        const cols = Array.from(tr.querySelectorAll('td')).map((td) => `"${td.textContent.trim().replace(/\s+/g, ' ')}"`);
        rows.push(cols.join(';'));
    });

    const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = tableId + '.csv';
    link.click();
    boToast('Exportação gerada.');
}
// As duas tabelas e o total são renderizados a partir da mesma consulta autenticada.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bo-compras]').forEach((section) => {
        const search = section.querySelector('[data-compras-busca]');
        const status = section.querySelector('[data-compras-status]');
        const results = section.querySelector('[data-compras-resultados]');
        const feedback = section.querySelector('[data-compras-feedback]');
        let timer;
        let controller;
        let version = 0;
        const schedule = (delay) => {
            clearTimeout(timer);
            if (controller) controller.abort();
            const current = ++version;
            results.hidden = true;
            results.setAttribute('aria-busy', 'true');
            feedback.hidden = false;
            feedback.textContent = 'Carregando compras…';
            timer = setTimeout(async () => {
                controller = new AbortController();
                const url = new URL(section.dataset.endpoint, window.location.href);
                url.searchParams.set('busca', search.value.trim());
                url.searchParams.set('status', status.value);
                try {
                    const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.error || 'Não foi possível carregar as compras.');
                    if (current !== version) return;
                    results.innerHTML = data.html;
                    results.hidden = false;
                    feedback.textContent = `${data.total} pedido(s) encontrado(s).`;
                } catch (error) {
                    if (current !== version || error.name === 'AbortError') return;
                    feedback.textContent = `${error.message} Altere a busca ou o status para tentar novamente.`;
                } finally {
                    if (current === version) results.setAttribute('aria-busy', 'false');
                }
            }, delay);
        };
        search.addEventListener('input', () => schedule(250));
        search.addEventListener('search', () => schedule(0));
        status.addEventListener('change', () => schedule(0));
        // O acesso pelo recibo começa em Todos, mesmo se o navegador restaurar
        // um filtro antigo (por exemplo Entregue). Depois, os filtros são livres.
        if (new URLSearchParams(window.location.search).get('compra_finalizada') === '1') {
            search.value = '';
            status.value = '';
            schedule(0);
        }
    });
});

```

ARQUIVO:
tests/pix-sem-frete.php

CÓDIGO COMPLETO:

```php
<?php
// SQL real em tabelas TEMPORARY privadas desta conexão. Nenhuma compra,
// alteração de estoque ou lançamento de cashback persiste na base de uso.
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/carrinho/checkout.php';
$checks = 0;
function checkout_check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function checkout_fails(callable $action): void {
    $failed = false;
    try { $action(); } catch (Throwable $error) { $failed = true; }
    checkout_check($failed, 'Operação inválida foi aceita');
}
// As cópias usam somente registros já existentes, sem inventar usuários/produtos.
foreach (['pedido', 'pedido_item', 'produtos', 'cashback'] as $table) {
    $rows = in_array($table, ['produtos', 'cashback'], true)
        ? $conn->query('SELECT * FROM ' . $table)->fetch_all(MYSQLI_ASSOC) : [];
    $conn->query('CREATE TEMPORARY TABLE checkout_schema_' . $table . ' LIKE ' . $table);
    $conn->query('CREATE TEMPORARY TABLE ' . $table . ' LIKE checkout_schema_' . $table);
    foreach ($rows as $row) {
        $columns = implode(',', array_map(static fn($c) => '`' . $c . '`', array_keys($row)));
        $statement = $conn->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
        $values = array_values($row);
        $statement->bind_param(str_repeat('s', count($values)), ...$values);
        $statement->execute();
        $statement->close();
    }
}

$address = null;
foreach ($conn->query('SELECT id_endereco, id_usuario, cep FROM enderecos_entrega')->fetch_all(MYSQLI_ASSOC) as $candidate) {
    if (!bo_listar_opcoes_frete($conn, $candidate['cep'])) { $address = $candidate; break; }
}
if (!$address || !cart_pix_local()) throw new RuntimeException('Teste requer APP_URL local e endereco real sem frete');
$products=$conn->query("SELECT id_produto,nome,preco FROM produtos WHERE nome IN ('Strap para Musculação','Munhequeira OneFit')")->fetch_all(MYSQLI_ASSOC);
checkout_check(count($products)===2,'Produtos do cenario ausentes');
$cart=array_fill_keys(array_column($products,'id_produto'),1);
$session=['id_usuario'=>(int)$address['id_usuario'],'carrinho'=>$cart,'checkout_endereco_id'=>(int)$address['id_endereco'],'csrf_token'=>bin2hex(random_bytes(32)),'checkout_token'=>bin2hex(random_bytes(32))];
$post=['csrf_token'=>$session['csrf_token'],'checkout_token'=>$session['checkout_token'],'forma_pagamento'=>'pix','cashback_usado'=>0];
$before=$session;
$result=cart_processar_checkout($conn,$session,$post);
checkout_check(abs($result['total']-62.80)<0.001,'Total do exemplo divergente');
checkout_check($result['frete']===0.0,'Frete local incorreto');
checkout_check($session['carrinho']===[],'Carrinho nao foi limpo');
$order=$conn->query('SELECT * FROM pedido')->fetch_assoc();
checkout_check($order['forma_pagamento']==='pix' && $order['status']==='aguardando','Pix/status incorretos');
checkout_check((int)$order['id_usuario']===(int)$address['id_usuario'],'Comprador incorreto');
$items=$conn->query('SELECT * FROM pedido_item')->fetch_all(MYSQLI_ASSOC);
checkout_check(count($items)===2,'Itens ausentes');
foreach($items as $item) {
 checkout_check($item['id_pedido']===$order['id_pedido'],'Pedido diferente por item');
 checkout_check($item['id_transportadora']===null && $item['confirmado_recebimento_em']===null,'Entrega inventada');
}
checkout_fails(function() use($conn,&$session,$post){cart_processar_checkout($conn,$session,$post);});
$card=$before;
checkout_fails(function() use($conn,&$card,$post){cart_processar_checkout($conn,$card,array_replace($post,['forma_pagamento'=>'cartao']));});
checkout_check($card===$before,'Falha no cartao perdeu carrinho');
checkout_check((int)$conn->query('SELECT COUNT(*) FROM pedido')->fetch_row()[0]===1,'Pedido duplicado');
echo "OK: $checks verificacoes; Pix Strap + Munhequeira = 62,80; um pedido e dois itens. Somente tabelas temporarias.\n";
$conn->close();

```

