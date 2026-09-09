<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($perfilCompras); ?>" data-bo-compras data-endpoint="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/buscar-compras.php'); ?>" data-section="compras">
    <div class="bo-page-title">
        <div>
            <h1>Minhas compras</h1>
            <p>Acompanhamento e histórico dos seus pedidos.</p>
        </div>
    </div>

    <div class="bo-filters">
        <input data-compras-busca aria-label="Buscar por produto ou ID da transação" type="search" maxlength="150" class="form-control" style="max-width:280px" placeholder="Buscar por produto, ID da transação ou pedido">
        <select data-compras-status aria-label="Status da compra" class="form-select" style="max-width:200px">
            <option value="">Todos</option>
            <option value="aguardando">Aguardando</option>
            <option value="entregue">Entregue</option>
            <option value="cancelado">Cancelado</option>
            <option value="devolvido">Devolvido</option>
        </select>
    </div>

    <p data-compras-feedback role="status" aria-live="polite" hidden></p>
    <div data-compras-resultados>
    <?php require __DIR__ . '/compras-resultados.php'; ?>
    </div>
</section>
