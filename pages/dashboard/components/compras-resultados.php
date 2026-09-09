<?php
// Histórico registra todas as compras, inclusive as que ainda estão aguardando.
// O contador continua somando os grupos disjuntos da consulta, sem duplicação.
$comprasHistoricoExibido = array_merge($comprasPedidos, $comprasHistorico);
usort($comprasHistoricoExibido, static function (array $a, array $b): int {
    $dataA = DateTimeImmutable::createFromFormat('d/m/Y H:i', $a['data']);
    $dataB = DateTimeImmutable::createFromFormat('d/m/Y H:i', $b['data']);
    return ($dataB <=> $dataA) ?: ((int) substr($b['transacao'], 4) <=> (int) substr($a['transacao'], 4));
});
?>
<?php if (!$comprasPedidos && !$comprasHistorico): ?><p class="bo-card">Nenhuma compra encontrada.</p><?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Total de pedidos</div>
                <div class="bo-card-value"><?php echo count($comprasPedidos) + count($comprasHistorico); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-section-heading">Acompanhamento de pedido</div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Produto</th>
                        <th>Vendido por</th>
                        <th>Quantidade</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Recebimento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasPedidos)): ?><tr><td colspan="7">Nenhum pedido em andamento corresponde aos filtros selecionados.</td></tr><?php endif; ?>
                    <?php foreach ($comprasPedidos as $ped): ?>
                        <?php
                        $vendedoresPedido = array_unique(array_column($ped['itens'], 'vendedor'));
                        $itensRecebidos = array_filter($ped['itens'], static fn(array $item): bool => $item['confirmadoRecebimento']);
                        $itensConfirmaveis = array_filter($ped['itens'], static fn(array $item): bool =>
                            $ped['statusBanco'] === 'aguardando' && $item['statusLogisticaBanco'] === 'despachado');
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
                                        <summary>Confirmar itens recebidos</summary>
                                        <?php foreach ($itensConfirmaveis as $it): ?>
                                        <div><small><?php echo htmlspecialchars($it['produto']); ?></small></div>
                                        <form method="POST" action="<?php echo bo_form_action('meus-pedidos.php'); ?>" class="bo-inline-form">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'compras'); ?>
                                            <?php echo bo_hidden('acao', 'confirmar-recebimento'); ?>
                                            <?php echo bo_hidden('id_item', $it['idItem']); ?>
                                            <button type="submit" class="btn-bo-outline btn-sm">Confirmar recebimento</button>
                                        </form>
                                        <?php endforeach; ?>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bo-section-heading">Histórico de compra</div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Data/hora</th>
                        <th>Produto</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comprasHistoricoExibido)): ?><tr><td colspan="4">Nenhuma compra no histórico corresponde aos filtros selecionados.</td></tr><?php endif; ?>
                    <?php foreach ($comprasHistoricoExibido as $ped): ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td><?php echo $ped['data']; ?></td>
                            <td>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <div><?php echo (int) $it['quantidade']; ?>x <?php echo htmlspecialchars($it['produto']); ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td><span class="bo-badge bo-compra-<?php echo $ped['statusBanco']; ?>"><?php echo $ped['status']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
