<?php
// Histórico mostra só pedidos finalizado/cancelado/devolvido — a consulta em
// bo_carregar_pedidos() já vem filtrada assim, sem misturar com o que ainda
// está em andamento.
$comprasHistoricoExibido = $comprasHistorico;

$idsPedidoVisiveis = array_column(array_merge($comprasPedidos, $comprasHistorico), 'idPedido');
$devolucoesPorPedido = bo_carregar_devolucoes_por_pedido($conn, $idsPedidoVisiveis);
$devolucaoStatusLabel = bo_status_devolucao_labels();
?>
<?php if (!$comprasPedidos && !$comprasHistorico): ?><p class="bo-card"><?php echo of_t('Nenhuma compra encontrada.'); ?></p><?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Total de pedidos'); ?></div>
                <div class="bo-card-value"><?php echo count($comprasPedidos) + count($comprasHistorico); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-section-heading"><?php echo of_t('Acompanhamento de pedido'); ?></div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th><?php echo of_t('ID transação'); ?></th>
                        <th><?php echo of_t('Produto'); ?></th>
                        <th><?php echo of_t('Vendido por'); ?></th>
                        <th><?php echo of_t('Quantidade'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Recebimento'); ?></th>
                        <th><?php echo of_t('Devolução'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasPedidos)): ?><tr><td colspan="8"><?php echo of_t('Nenhum pedido em andamento corresponde aos filtros selecionados.'); ?></td></tr><?php endif; ?>
                    <?php foreach ($comprasPedidos as $ped): ?>
                        <?php
                        $vendedoresPedido = array_unique(array_column($ped['itens'], 'vendedor'));
                        $itensRecebidos = array_filter($ped['itens'], static fn(array $item): bool => $item['confirmadoRecebimento']);
                        $itensConfirmaveis = array_filter($ped['itens'], static fn(array $item): bool =>
                            $item['statusLogisticaBanco'] === 'entregue' && !$item['confirmadoRecebimento']);
                        $recebimentoPedido = 'Não definido';
                        if ($itensRecebidos) {
                            $recebimentoPedido = 'Recebimento parcial (' . count($itensRecebidos) . '/' . count($ped['itens']) . ' itens)';
                            if (count($itensRecebidos) === count($ped['itens'])) {
                                $datasRecebidas = array_filter(array_map(static fn(array $item) =>
                                    $item['confirmadoRecebimentoEm']
                                        ? DateTimeImmutable::createFromFormat('d/m/Y H:i', $item['confirmadoRecebimentoEm']) : false,
                                    $itensRecebidos));
                                $recebimentoPedido = $datasRecebidas
                                    ? 'Recebido em ' . max($datasRecebidas)->format('d/m/Y H:i') : 'Recebido';
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <div><?php echo (int) $it['quantidade']; ?>x <?php echo htmlspecialchars($it['produto']); ?></div>
                                    <?php if ($it['codigoRastreio']): ?><div><small><?php echo of_t('Rastreio:'); ?> <?php echo htmlspecialchars($it['codigoRastreio']); ?></small></div><?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <?php foreach ($vendedoresPedido as $vendedorPedido): ?>
                                    <div><?php echo htmlspecialchars($vendedorPedido); ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo array_sum(array_column($ped['itens'], 'quantidade')); ?></td>
                            <td><?php echo bo_money($ped['valor']); ?></td>
                            <td><span class="bo-badge bo-compra-<?php echo $ped['statusBanco']; ?>"><?php echo $ped['status']; ?></span></td>
                            <td>
                                <small><?php echo htmlspecialchars($recebimentoPedido); ?></small>
                                <?php if ($itensConfirmaveis): ?>
                                    <details>
                                        <summary><?php echo of_t('Confirmar entrega'); ?></summary>
                                        <?php foreach ($itensConfirmaveis as $it): ?>
                                        <div><small><?php echo htmlspecialchars($it['produto']); ?></small></div>
                                        <form method="POST" action="<?php echo bo_form_action('meus-pedidos.php'); ?>" class="bo-inline-form">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'compras'); ?>
                                            <?php echo bo_hidden('acao', 'confirmar-recebimento'); ?>
                                            <?php echo bo_hidden('id_item', $it['idItem']); ?>
                                            <button type="submit" class="btn-bo-gold btn-sm"><?php echo of_t('Confirmar entrega'); ?></button>
                                        </form>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td><?php require __DIR__ . '/compras-devolucao-celula.php'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bo-section-heading"><?php echo of_t('Histórico de compra'); ?></div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th><?php echo of_t('ID transação'); ?></th>
                        <th><?php echo of_t('Data/hora'); ?></th>
                        <th><?php echo of_t('Produto'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Devolução'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasHistoricoExibido)): ?><tr><td colspan="5"><?php echo of_t('Nenhuma compra no histórico corresponde aos filtros selecionados.'); ?></td></tr><?php endif; ?>
                    <?php foreach ($comprasHistoricoExibido as $ped): ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td><?php echo $ped['data']; ?></td>
                            <td>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <div><?php echo (int) $it['quantidade']; ?>x <?php echo htmlspecialchars($it['produto']); ?></div>
                                    <?php if ($it['codigoRastreio']): ?><div><small><?php echo of_t('Rastreio:'); ?> <?php echo htmlspecialchars($it['codigoRastreio']); ?></small></div><?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td><span class="bo-badge bo-compra-<?php echo $ped['statusBanco']; ?>"><?php echo $ped['status']; ?></span></td>
                            <td><?php require __DIR__ . '/compras-devolucao-celula.php'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
