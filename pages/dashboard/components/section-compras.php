<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($perfilCompras); ?>" data-bo-compras data-endpoint="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/buscar-compras.php'); ?>" data-section="compras">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Minhas compras'); ?></h1>
            <p><?php echo of_t('Acompanhamento e histórico dos seus pedidos.'); ?></p>
        </div>
    </div>

    <div class="bo-filters">
        <input data-compras-busca aria-label="Buscar por produto ou ID da transação" type="search" maxlength="150" class="form-control" style="max-width:280px" placeholder="Buscar por produto, ID da transação ou pedido">
        <select data-compras-status aria-label="Status da compra" class="form-select" style="max-width:200px">
            <option value=""><?php echo of_t('Todos'); ?></option>
            <option value="aguardando"><?php echo of_t('Aguardando'); ?></option>
            <option value="preparando"><?php echo of_t('Em preparação'); ?></option>
            <option value="despachado"><?php echo of_t('Enviado'); ?></option>
            <option value="entregue"><?php echo of_t('Finalizado'); ?></option>
            <option value="cancelado"><?php echo of_t('Cancelado'); ?></option>
            <option value="devolvido"><?php echo of_t('Devolvido'); ?></option>
            <option value="extraviado"><?php echo of_t('Extraviado'); ?></option>
        </select>
    </div>

    <p data-compras-feedback role="status" aria-live="polite" hidden></p>
    <div data-compras-resultados>
    <?php require __DIR__ . '/compras-resultados.php'; ?>
    </div>
</section>
