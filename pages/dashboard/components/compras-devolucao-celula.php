<?php
/**
 * Célula "Devolução" de uma linha de pedido em compras-resultados.php
 * (usada tanto no Acompanhamento quanto no Histórico). Espera no escopo:
 * $ped (pedido atual), $devolucoesPorPedido, $devolucaoStatusLabel.
 */
$devolucao = $devolucoesPorPedido[$ped['idPedido']] ?? null;

if ($devolucao) {
    echo '<small>' . of_t($devolucaoStatusLabel[$devolucao['status']] ?? ucfirst($devolucao['status'])) . '</small>';
} elseif (bo_status_permite_devolucao($ped['statusBanco'])) {
    ?>
    <details>
        <summary><?php echo of_t('Solicitar devolução'); ?></summary>
        <form method="POST" action="<?php echo bo_form_action('meus-pedidos.php'); ?>" class="bo-inline-form">
            <?php echo bo_csrf_field(); ?>
            <?php echo bo_hidden('secao', 'compras'); ?>
            <?php echo bo_hidden('acao', 'solicitar-devolucao'); ?>
            <?php echo bo_hidden('id_pedido', $ped['idPedido']); ?>
            <textarea name="motivo" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="<?php echo of_t('Motivo da devolução'); ?>" required></textarea>
            <button type="submit" class="btn-bo-outline btn-sm mt-1"><?php echo of_t('Solicitar devolução'); ?></button>
        </form>
    </details>
    <?php
} else {
    echo '<small>—</small>';
}
