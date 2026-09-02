<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

// Sem sessão -> manda pro login. O acesso ao backoffice inteiro depende de
// estar logado (id_usuario e tipo_usuario são gravados em processa_login.php).
// if (!isset($_SESSION['id_usuario'])) { header('Location: ' . BASE_URL . 'pages/login/login.php'); exit; }

session_start();

function mkt_money($v)
{ return 'R$ ' . number_format((float) $v, 2, ',', '.'); }

/* ===== Produtos disponíveis (cadastrados no backoffice) ===== */
$produtos = [];
$sql = "SELECT id_produto AS id, nome, descricao, categoria, preco, desconto, cashback_valor AS cashback, imagem, estoque, status
        FROM produtos
        WHERE status = 'ativo'
        ORDER BY categoria, nome";
try {
    $result = $conn->query($sql);
} catch (\mysqli_sql_exception $e) {
    $result = false;
}
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['preco'] = (float) $row['preco'];
        $row['desconto'] = (float) $row['desconto'];
        $row['cashback'] = (float) $row['cashback'];
        $row['valorFinal'] = $row['desconto'] > 0
            ? round($row['preco'] * (1 - $row['desconto'] / 100), 2)
            : $row['preco'];
        // Cashback do produto é um valor fixo em R$ por unidade (não mais %).
        $row['cashbackValor'] = $row['cashback'];
        $produtos[(int) $row['id']] = $row;
    }
}

/* ===== Categorias, na ordem em que aparecem entre os produtos carregados ===== */
$categorias = [];
foreach ($produtos as $p) {
    if ($p['categoria'] !== '' && !in_array($p['categoria'], $categorias, true)) {
        $categorias[] = $p['categoria'];
    }
}

/* ===== Sessão: carrinho ([produtoId => quantidade]) e favoritos ([produtoId => true]) ===== */
if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) { $_SESSION['carrinho'] = []; }
if (!isset($_SESSION['favoritos']) || !is_array($_SESSION['favoritos'])) { $_SESSION['favoritos'] = []; }

/* ===== Ação via formulário real (sem JS): adicionar ao carrinho a partir do
   modal de detalhes de um favorito. Sempre recarrega a própria página
   (padrão PRG, igual ao carrinho.php), mostrando um aviso quando o estoque
   não permite adicionar mais unidades. ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_acao']) && $_POST['form_acao'] === 'add_carrinho') {
    $produtoIdForm = (int) ($_POST['produto_id'] ?? 0);
    $categoriaVolta = (string) ($_POST['categoria'] ?? '');
    $queryVolta = $categoriaVolta !== '' ? '&categoria=' . urlencode($categoriaVolta) : '';
    if (isset($produtos[$produtoIdForm])) {
        $qtdAtualForm = $_SESSION['carrinho'][$produtoIdForm] ?? 0;
        if ($qtdAtualForm < (int) $produtos[$produtoIdForm]['estoque']) {
            $_SESSION['carrinho'][$produtoIdForm] = $qtdAtualForm + 1;
            header('Location: ' . BASE_URL . 'pages/marketplace/marketplace.php?adicionado=1' . $queryVolta);
        } else {
            header('Location: ' . BASE_URL . 'pages/marketplace/marketplace.php?semestoque=1' . $queryVolta);
        }
    } else {
        header('Location: ' . BASE_URL . 'pages/marketplace/marketplace.php' . ($queryVolta !== '' ? '?' . ltrim($queryVolta, '&') : ''));
    }
    exit;
}

/* ===== Ação via formulário real (sem JS): favoritar/desfavoritar um produto
   (usado tanto no botão de estrela do card quanto no botão de remover dentro
   do offcanvas de favoritos). ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_acao']) && $_POST['form_acao'] === 'toggle_favorito') {
    $produtoIdForm = (int) ($_POST['produto_id'] ?? 0);
    $categoriaVolta = (string) ($_POST['categoria'] ?? '');
    if (isset($produtos[$produtoIdForm])) {
        if (!empty($_SESSION['favoritos'][$produtoIdForm])) {
            unset($_SESSION['favoritos'][$produtoIdForm]);
        } else {
            $_SESSION['favoritos'][$produtoIdForm] = true;
        }
    }
    header('Location: ' . BASE_URL . 'pages/marketplace/marketplace.php' . ($categoriaVolta !== '' ? '?categoria=' . urlencode($categoriaVolta) : ''));
    exit;
}

$carrinhoCount = array_sum($_SESSION['carrinho']);
$favoritosCount = count($_SESSION['favoritos']);

/* Saldo real de cashback do usuário logado (mesma conta usada no checkout do
   carrinho: créditos - débitos, ignorando lançamentos cancelados). Antes este
   valor vinha de uma sessão ('saldo_cashback') que nunca era preenchida em
   nenhum outro lugar do sistema, então a tela principal sempre mostrava um
   saldo desatualizado/zerado, diferente do saldo real exibido no checkout. */
$saldoCashback = 0.0;
if (isset($_SESSION['id_usuario'])) {
    $stmtSaldoMkt = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmtSaldoMkt->bind_param('i', $_SESSION['id_usuario']);
    $stmtSaldoMkt->execute();
    $saldoCashback = max(0.0, (float) ($stmtSaldoMkt->get_result()->fetch_assoc()['saldo'] ?? 0));
    $stmtSaldoMkt->close();
}

$mktAvisoAdicionado = isset($_GET['adicionado']);
$mktAvisoSemEstoque = isset($_GET['semestoque']);

/* ===== Aba de categoria ativa (filtro por recarregamento de página, sem JS) ===== */
$categoriaAtiva = (string) ($_GET['categoria'] ?? '');
if (!in_array($categoriaAtiva, $categorias, true)) {
    $categoriaAtiva = $categorias[0] ?? '';
}

/* URL de "Voltar": usa o referenciador quando disponível, senão a home. */
$mktVoltarUrl = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : (BASE_URL . 'index.php');
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace · ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/marketplace.css">
</head>

<body>

    <header class="mkt-header">
        <div class="mkt-logo">
            <img src="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" alt="Logo One Fit">
            <span>One Fit · Marketplace</span>
        </div>

        <div class="mkt-header-actions">
            <a class="mkt-icon-btn" href="<?php echo htmlspecialchars($mktVoltarUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Voltar" title="Voltar">
                <i class="bi bi-arrow-left"></i>
            </a>
            <button type="button" class="mkt-icon-btn" aria-label="Favoritos" title="Favoritos" data-bs-toggle="offcanvas" data-bs-target="#mktFavoritosOffcanvas">
                <i class="bi bi-star-fill"></i>
                <span class="mkt-icon-badge"><?php echo (int) $favoritosCount; ?></span>
            </button>
            <a class="mkt-icon-btn" href="<?php echo BASE_URL; ?>pages/carrinho/carrinho.php" aria-label="Carrinho" title="Carrinho">
                <i class="bi bi-cart3"></i>
                <span class="mkt-icon-badge"><?php echo (int) $carrinhoCount; ?></span>
            </a>
        </div>
    </header>

    <main class="mkt-main">

        <div class="mkt-page-title">
            <h1>Marketplace ONE FIT</h1>
            <p class="mkt-cashback-saldo">Seu Cashback: <strong><?php echo mkt_money($saldoCashback); ?></strong></p>
        </div>

        <?php if ($mktAvisoAdicionado): ?>
            <div class="mkt-aviso"><i class="bi bi-check-circle"></i> Produto adicionado ao carrinho!</div>
        <?php elseif ($mktAvisoSemEstoque): ?>
            <div class="mkt-aviso mkt-aviso-erro"><i class="bi bi-exclamation-triangle"></i> Sem estoque suficiente para adicionar mais unidades deste produto.</div>
        <?php endif; ?>

        <?php if (empty($categorias)): ?>
            <div class="mkt-empty">
                <i class="bi bi-shop"></i>
                <p class="mb-0">Nenhum produto disponível no momento. Volte em breve!</p>
            </div>
        <?php else: ?>

            <div class="mkt-tabs">
                <?php foreach ($categorias as $categoria): ?>
                    <a class="mkt-tab<?php echo $categoria === $categoriaAtiva ? ' active' : ''; ?>" href="?categoria=<?php echo urlencode($categoria); ?>">
                        <?php echo htmlspecialchars($categoria); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="row g-3">
                <?php foreach ($produtos as $produto): ?>
                    <?php
                    if ($produto['categoria'] !== $categoriaAtiva) {
                        continue;
                    }
                    $isFavorito = !empty($_SESSION['favoritos'][$produto['id']]);
                    ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="mkt-card">
                            <div class="mkt-card-media">
                                <?php if (!empty($produto['imagem'])): ?>
                                    <img src="<?php echo htmlspecialchars($produto['imagem']); ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                <?php else: ?>
                                    <div class="mkt-no-image"><i class="bi bi-image"></i></div>
                                <?php endif; ?>

                                <?php if ($produto['desconto'] > 0): ?>
                                    <span class="mkt-badge-off"><?php echo (int) $produto['desconto']; ?>% OFF</span>
                                <?php endif; ?>

                                <form method="POST" action="marketplace.php" class="mkt-fav-form">
                                    <input type="hidden" name="form_acao" value="toggle_favorito">
                                    <input type="hidden" name="produto_id" value="<?php echo (int) $produto['id']; ?>">
                                    <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoriaAtiva, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="mkt-fav-btn<?php echo $isFavorito ? ' active' : ''; ?>" aria-label="Favoritar" title="Favoritar">
                                        <i class="bi <?php echo $isFavorito ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                    </button>
                                </form>
                            </div>

                            <div class="mkt-card-body">
                                <div class="mkt-card-categoria"><?php echo htmlspecialchars($produto['categoria']); ?></div>
                                <div class="mkt-card-nome"><?php echo htmlspecialchars($produto['nome']); ?></div>
                                <div class="mkt-card-desc"><?php echo htmlspecialchars($produto['descricao']); ?></div>

                                <div class="mkt-card-preco">
                                    <?php if ($produto['desconto'] > 0): ?>
                                        <span class="mkt-preco-antigo"><?php echo mkt_money($produto['preco']); ?></span>
                                    <?php endif; ?>
                                    <span class="mkt-preco-final"><?php echo mkt_money($produto['valorFinal']); ?></span>
                                </div>

                                <?php if ($produto['cashback'] > 0): ?>
                                    <div class="mkt-cashback-linha">
                                        <i class="bi bi-coin"></i>
                                        Ganhe cashback: <?php echo mkt_money($produto['cashbackValor']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mkt-card-footer">
                                    <form method="POST" action="marketplace.php">
                                        <input type="hidden" name="form_acao" value="add_carrinho">
                                        <input type="hidden" name="produto_id" value="<?php echo (int) $produto['id']; ?>">
                                        <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoriaAtiva, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn-mkt-gold">
                                            <i class="bi bi-cart-plus"></i> Adicionar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- Offcanvas: Favoritos (renderizado 100% em PHP a partir da sessão) -->
    <div class="offcanvas offcanvas-end mkt-offcanvas" tabindex="-1" id="mktFavoritosOffcanvas">
        <div class="offcanvas-header">
            <h5 class="mb-0"><i class="bi bi-star-fill"></i> Seus favoritos</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body">
            <?php
            $favoritosProdutos = array_values(array_filter(
                array_map(static fn($pid) => $produtos[$pid] ?? null, array_keys($_SESSION['favoritos'])),
                static fn($p) => $p !== null
            ));
            ?>
            <?php if (empty($favoritosProdutos)): ?>
                <div class="mkt-off-empty">Você ainda não favoritou nenhum produto.</div>
            <?php else: ?>
                <?php foreach ($favoritosProdutos as $fp): ?>
                    <div class="mkt-off-item">
                        <a class="mkt-off-link" href="#mktDetalheModal<?php echo (int) $fp['id']; ?>" data-bs-toggle="modal" data-bs-target="#mktDetalheModal<?php echo (int) $fp['id']; ?>">
                            <div class="mkt-off-thumb">
                                <?php if (!empty($fp['imagem'])): ?>
                                    <img src="<?php echo htmlspecialchars($fp['imagem']); ?>" alt="">
                                <?php else: ?>
                                    <i class="bi bi-image"></i>
                                <?php endif; ?>
                            </div>
                            <div class="mkt-off-info">
                                <div class="nome"><?php echo htmlspecialchars($fp['nome']); ?></div>
                                <div class="sub"><?php echo mkt_money($fp['valorFinal']); ?></div>
                            </div>
                        </a>
                        <form method="POST" action="marketplace.php">
                            <input type="hidden" name="form_acao" value="toggle_favorito">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $fp['id']; ?>">
                            <button type="submit" class="mkt-off-remove" title="Remover dos favoritos">
                                <i class="bi bi-star-fill"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modais de detalhes: um por produto favoritado, aberto pelo item correspondente
         no offcanvas (data-bs-toggle/data-bs-target do próprio Bootstrap, sem JS extra). -->
    <?php foreach ($favoritosProdutos ?? [] as $fp): ?>
        <div class="modal fade mkt-detalhe-modal" id="mktDetalheModal<?php echo (int) $fp['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalhes do produto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mkt-detalhe-media">
                            <?php if (!empty($fp['imagem'])): ?>
                                <img src="<?php echo htmlspecialchars($fp['imagem']); ?>" alt="<?php echo htmlspecialchars($fp['nome']); ?>">
                            <?php else: ?>
                                <div class="mkt-no-image"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="mkt-detalhe-categoria"><?php echo htmlspecialchars($fp['categoria']); ?></div>
                        <h4 class="mkt-detalhe-nome"><?php echo htmlspecialchars($fp['nome']); ?></h4>
                        <p class="mkt-detalhe-desc"><?php echo htmlspecialchars($fp['descricao']); ?></p>
                        <div class="mkt-detalhe-preco">
                            <?php if ($fp['desconto'] > 0): ?>
                                <span class="mkt-preco-antigo"><?php echo mkt_money($fp['preco']); ?></span>
                            <?php endif; ?>
                            <span class="mkt-preco-final"><?php echo mkt_money($fp['valorFinal']); ?></span>
                        </div>
                        <?php if ($fp['cashback'] > 0): ?>
                            <div class="mkt-cashback-linha">
                                <i class="bi bi-coin"></i> Ganhe cashback: <?php echo mkt_money($fp['cashbackValor']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" action="marketplace.php">
                            <input type="hidden" name="form_acao" value="add_carrinho">
                            <input type="hidden" name="produto_id" value="<?php echo (int) $fp['id']; ?>">
                            <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoriaAtiva, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn-mkt-gold">
                                <i class="bi bi-cart-plus"></i> Adicionar ao carrinho
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
