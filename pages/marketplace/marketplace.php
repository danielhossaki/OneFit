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
$sql = "SELECT id_produto AS id, nome, descricao, categoria, preco, desconto, cashback_percentual AS cashback, imagem, estoque, status
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
        $row['cashbackValor'] = round($row['valorFinal'] * ($row['cashback'] / 100), 2);
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

/* ===== Endpoint AJAX (mesmo arquivo) — adicionar/remover do carrinho e favoritar ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    header('Content-Type: application/json; charset=utf-8');
    $produtoId = (int) ($_POST['produto_id'] ?? 0);

    switch ($_POST['acao']) {
        case 'add_carrinho':
            if (isset($produtos[$produtoId])) {
                $_SESSION['carrinho'][$produtoId] = ($_SESSION['carrinho'][$produtoId] ?? 0) + 1;
            }
            break;

        case 'favoritar':
            if (isset($produtos[$produtoId])) {
                if (!empty($_SESSION['favoritos'][$produtoId])) {
                    unset($_SESSION['favoritos'][$produtoId]);
                } else {
                    $_SESSION['favoritos'][$produtoId] = true;
                }
            }
            break;
    }

    $favoritosItens = [];
    foreach (array_keys($_SESSION['favoritos']) as $pid) {
        if (!isset($produtos[$pid])) {
            continue;
        }
        $p = $produtos[$pid];
        $favoritosItens[] = [
            'id' => $pid,
            'nome' => $p['nome'],
            'imagem' => $p['imagem'],
            'valorFinal' => mkt_money($p['valorFinal']),
        ];
    }

    echo json_encode([
        'ok' => true,
        'favorito' => isset($_SESSION['favoritos'][$produtoId]),
        'carrinhoCount' => array_sum($_SESSION['carrinho']),
        'favoritosCount' => count($_SESSION['favoritos']),
        'favoritosItens' => $favoritosItens,
    ]);
    exit;
}

$carrinhoCount = array_sum($_SESSION['carrinho']);
$favoritosCount = count($_SESSION['favoritos']);
$saldoCashback = (float) ($_SESSION['saldo_cashback'] ?? 0);
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
            <button type="button" class="mkt-icon-btn" id="mktBackBtn" aria-label="Voltar" title="Voltar">
                <i class="bi bi-arrow-left"></i>
            </button>
            <button type="button" class="mkt-icon-btn" id="mktFavBtn" aria-label="Favoritos" title="Favoritos" data-bs-toggle="offcanvas" data-bs-target="#mktFavoritosOffcanvas">
                <i class="bi bi-star-fill"></i>
                <span class="mkt-icon-badge" id="mktFavCount"><?php echo (int) $favoritosCount; ?></span>
            </button>
            <a class="mkt-icon-btn" id="mktCartBtn" href="<?php echo BASE_URL; ?>pages/carrinho/carrinho.php" aria-label="Carrinho" title="Carrinho">
                <i class="bi bi-cart3"></i>
                <span class="mkt-icon-badge" id="mktCartCount"><?php echo (int) $carrinhoCount; ?></span>
            </a>
        </div>
    </header>

    <main class="mkt-main">

        <div class="mkt-page-title">
            <h1>Marketplace ONE FIT</h1>
            <p class="mkt-cashback-saldo">Seu Cashback: <strong><?php echo mkt_money($saldoCashback); ?></strong></p>
        </div>

        <?php if (empty($categorias)): ?>
            <div class="mkt-empty">
                <i class="bi bi-shop"></i>
                <p class="mb-0">Nenhum produto disponível no momento. Volte em breve!</p>
            </div>
        <?php else: ?>

            <div class="mkt-tabs" id="mktTabs">
                <?php foreach ($categorias as $i => $categoria): ?>
                    <button type="button" class="mkt-tab<?php echo $i === 0 ? ' active' : ''; ?>" data-categoria="<?php echo htmlspecialchars($categoria); ?>">
                        <?php echo htmlspecialchars($categoria); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="row g-3" id="mktGrid">
                <?php foreach ($produtos as $produto): ?>
                    <?php
                    $isFavorito = !empty($_SESSION['favoritos'][$produto['id']]);
                    ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mkt-card-col" data-categoria="<?php echo htmlspecialchars($produto['categoria']); ?>" style="<?php echo $categorias[0] === $produto['categoria'] ? '' : 'display:none;'; ?>">
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

                                <button type="button" class="mkt-fav-btn<?php echo $isFavorito ? ' active' : ''; ?>" data-produto-id="<?php echo (int) $produto['id']; ?>" aria-label="Favoritar" title="Favoritar">
                                    <i class="bi <?php echo $isFavorito ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                </button>
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
                                    <button type="button" class="btn-mkt-gold mkt-add-cart-btn" data-produto-id="<?php echo (int) $produto['id']; ?>">
                                        <i class="bi bi-cart-plus"></i> Adicionar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- Offcanvas: Favoritos -->
    <div class="offcanvas offcanvas-end mkt-offcanvas" tabindex="-1" id="mktFavoritosOffcanvas">
        <div class="offcanvas-header">
            <h5 class="mb-0"><i class="bi bi-star-fill"></i> Seus favoritos</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body">
            <div id="mktFavoritosLista"></div>
        </div>
    </div>

    <div class="mkt-toast" id="mktToast"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const MKT_URL = window.location.pathname;

        /* ===== Abas de categoria ===== */
        document.querySelectorAll('.mkt-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.mkt-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const categoria = tab.dataset.categoria;
                document.querySelectorAll('.mkt-card-col').forEach(col => {
                    col.style.display = col.dataset.categoria === categoria ? '' : 'none';
                });
            });
        });

        /* ===== Toast ===== */
        let mktToastTimer = null;
        function mktShowToast(msg) {
            const toast = document.getElementById('mktToast');
            toast.textContent = msg;
            toast.classList.add('show');
            clearTimeout(mktToastTimer);
            mktToastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
        }

        /* ===== Chamada AJAX ===== */
        async function mktPost(acao, produtoId) {
            const body = new URLSearchParams({ acao, produto_id: produtoId });
            const resp = await fetch(MKT_URL, { method: 'POST', body });
            return resp.json();
        }

        function mktUpdateBadges(data) {
            document.getElementById('mktCartCount').textContent = data.carrinhoCount;
            document.getElementById('mktFavCount').textContent = data.favoritosCount;
        }

        function mktRenderFavoritos(data) {
            const lista = document.getElementById('mktFavoritosLista');

            if (!data.favoritosItens.length) {
                lista.innerHTML = '<div class="mkt-off-empty">Você ainda não favoritou nenhum produto.</div>';
                return;
            }

            lista.innerHTML = data.favoritosItens.map(item => `
                <div class="mkt-off-item">
                    <div class="mkt-off-thumb">
                        ${item.imagem ? `<img src="${item.imagem}" alt="">` : '<i class="bi bi-image"></i>'}
                    </div>
                    <div class="mkt-off-info">
                        <div class="nome">${item.nome}</div>
                        <div class="sub">${item.valorFinal}</div>
                    </div>
                    <button type="button" class="mkt-off-remove" data-produto-id="${item.id}" title="Remover dos favoritos">
                        <i class="bi bi-star-fill"></i>
                    </button>
                </div>
            `).join('');

            lista.querySelectorAll('.mkt-off-remove').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const data = await mktPost('favoritar', btn.dataset.produtoId);
                    mktSyncFavoritoButtons(data.favorito, btn.dataset.produtoId);
                    mktUpdateBadges(data);
                    mktRenderFavoritos(data);
                });
            });
        }

        function mktSyncFavoritoButtons(isFavorito, produtoId) {
            const cardBtn = document.querySelector(`.mkt-fav-btn[data-produto-id="${produtoId}"]`);
            if (cardBtn) {
                cardBtn.classList.toggle('active', isFavorito);
                const icon = cardBtn.querySelector('i');
                icon.classList.toggle('bi-star', !isFavorito);
                icon.classList.toggle('bi-star-fill', isFavorito);
            }
        }

        /* ===== Adicionar ao carrinho ===== */
        document.querySelectorAll('.mkt-add-cart-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                const data = await mktPost('add_carrinho', btn.dataset.produtoId);
                mktUpdateBadges(data);
                mktShowToast('Produto adicionado ao carrinho!');
                btn.disabled = false;
            });
        });

        /* ===== Favoritar ===== */
        document.querySelectorAll('.mkt-fav-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const data = await mktPost('favoritar', btn.dataset.produtoId);
                mktSyncFavoritoButtons(data.favorito, btn.dataset.produtoId);
                mktUpdateBadges(data);
                mktRenderFavoritos(data);
                mktShowToast(data.favorito ? 'Adicionado aos favoritos!' : 'Removido dos favoritos.');
            });
        });

        /* ===== Voltar ===== */
        document.getElementById('mktBackBtn').addEventListener('click', () => {
            if (document.referrer) {
                history.back();
            } else {
                window.location.href = '<?php echo BASE_URL; ?>index.php';
            }
        });

        /* ===== Carrega lista de favoritos ao abrir o offcanvas ===== */
        document.getElementById('mktFavoritosOffcanvas').addEventListener('show.bs.offcanvas', async () => {
            const data = await mktPost('listar_favoritos', 0);
            mktRenderFavoritos(data);
        });
    </script>

</body>

</html>
