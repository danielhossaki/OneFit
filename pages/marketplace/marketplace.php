<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

session_start();

function mkt_money($v)
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

/* ===== Produtos disponíveis (cadastrados no backoffice) ===== */
$produtos = [];
$sql = "SELECT id, nome, descricao, categoria, preco, desconto, cashback, imagem, estoque, status
        FROM produtos
        WHERE status = 'disponivel'
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
if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}
if (!isset($_SESSION['favoritos']) || !is_array($_SESSION['favoritos'])) {
    $_SESSION['favoritos'] = [];
}

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
    <title>Marketplace — ONE FIT</title>
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
            --bo-success: #4caf50;
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

        .mkt-header {
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

        .mkt-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mkt-logo img {
            height: 42px;
            width: auto;
        }

        .mkt-logo span {
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--bo-gold);
            font-size: 14px;
            text-transform: uppercase;
        }

        .mkt-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mkt-icon-btn {
            position: relative;
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
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        .mkt-icon-btn:hover {
            border-color: var(--bo-gold);
            color: var(--bo-gold-bright);
        }

        .mkt-icon-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 999px;
            background: var(--bo-gold-bright);
            color: #14110e;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .mkt-main {
            margin-top: var(--bo-header-h);
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding: 28px 24px 60px;
        }

        .mkt-page-title h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--bo-gold);
            margin: 0 0 6px;
        }

        .mkt-cashback-saldo {
            font-size: 14px;
            color: var(--bo-text-muted);
        }

        .mkt-cashback-saldo strong {
            color: var(--bo-gold-bright);
        }

        .mkt-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 24px 0 22px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .mkt-tab {
            border: 1px solid var(--bo-border);
            background: var(--bo-surface-2);
            color: var(--bo-text-muted);
            padding: 9px 20px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .mkt-tab:hover {
            border-color: var(--bo-gold);
            color: var(--bo-text);
        }

        .mkt-tab.active {
            background: linear-gradient(120deg, var(--bo-gold-dim), var(--bo-gold) 60%, var(--bo-gold-bright));
            border-color: transparent;
            color: #14110e;
        }

        .mkt-card {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .mkt-card-media {
            position: relative;
            aspect-ratio: 4 / 3;
            background: var(--bo-surface-2);
        }

        .mkt-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mkt-card-media .mkt-no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bo-text-muted);
            font-size: 34px;
        }

        .mkt-badge-off {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--bo-gold-bright);
            color: #14110e;
            font-weight: 800;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .mkt-fav-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid var(--bo-border);
            background: rgba(14, 11, 8, 0.75);
            color: var(--bo-text);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.2s ease, border-color 0.2s ease;
        }

        .mkt-fav-btn:hover {
            border-color: var(--bo-gold);
        }

        .mkt-fav-btn.active {
            color: var(--bo-gold-bright);
            border-color: var(--bo-gold);
        }

        .mkt-card-body {
            padding: 16px 18px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .mkt-card-categoria {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--bo-gold-dim);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .mkt-card-nome {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .mkt-card-desc {
            font-size: 13px;
            color: var(--bo-text-muted);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .mkt-card-preco {
            margin-bottom: 6px;
        }

        .mkt-preco-antigo {
            color: var(--bo-text-muted);
            text-decoration: line-through;
            font-size: 13px;
            margin-right: 8px;
        }

        .mkt-preco-final {
            color: var(--bo-text);
            font-size: 20px;
            font-weight: 800;
        }

        .mkt-cashback-linha {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--bo-gold-bright);
            font-weight: 700;
            margin-bottom: 14px;
        }

        .mkt-card-footer {
            margin-top: auto;
        }

        .btn-mkt-gold {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
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

        .btn-mkt-gold:hover {
            background-position: 100% 0;
            color: #14110e;
            transform: translateY(-1px);
        }

        .btn-mkt-gold:disabled {
            opacity: 0.6;
            cursor: default;
            transform: none;
        }

        .mkt-empty {
            background: var(--bo-surface);
            border: 1px dashed var(--bo-border);
            border-radius: 14px;
            padding: 60px 32px;
            text-align: center;
            color: var(--bo-text-muted);
        }

        .mkt-empty i {
            font-size: 34px;
            color: var(--bo-gold);
            margin-bottom: 12px;
            display: block;
        }

        .mkt-offcanvas {
            background: var(--bo-surface);
            color: var(--bo-text);
            border-left: 1px solid var(--bo-border);
            width: min(400px, 92vw);
        }

        .mkt-offcanvas .offcanvas-header {
            border-bottom: 1px solid var(--bo-border);
        }

        .mkt-offcanvas .btn-close {
            filter: invert(1) grayscale(1) brightness(1.6);
        }

        .mkt-off-item {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--bo-border);
        }

        .mkt-off-item:last-child {
            border-bottom: none;
        }

        .mkt-off-thumb {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            background: var(--bo-surface-2);
            border: 1px solid var(--bo-border);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bo-text-muted);
        }

        .mkt-off-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mkt-off-info {
            flex: 1;
            min-width: 0;
        }

        .mkt-off-info .nome {
            font-size: 13.5px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mkt-off-info .sub {
            font-size: 12px;
            color: var(--bo-text-muted);
        }

        .mkt-off-remove {
            background: none;
            border: none;
            color: var(--bo-text-muted);
            font-size: 16px;
            cursor: pointer;
        }

        .mkt-off-remove:hover {
            color: var(--bo-danger);
        }

        .mkt-off-empty {
            text-align: center;
            color: var(--bo-text-muted);
            padding: 40px 10px;
            font-size: 13px;
        }

        .mkt-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--bo-surface);
            border: 1px solid var(--bo-gold);
            color: var(--bo-text);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            z-index: 2000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            pointer-events: none;
        }

        .mkt-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 560px) {
            .mkt-logo span {
                display: none;
            }
        }
    </style>
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
