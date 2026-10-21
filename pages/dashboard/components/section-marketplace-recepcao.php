<?php
/**
 * components/section-marketplace-recepcao.php
 * Tela "Marketplace" do painel da recepção, com duas opções:
 *  - Comprar produtos: link para a loja (pages/marketplace/marketplace.php),
 *    a mesma de antes, com o mesmo carrinho e checkout;
 *  - Consultar pedidos e vendas: todos os pedidos do Marketplace, só para
 *    consulta no atendimento. Nenhum botão, formulário ou rota de gravação:
 *    status, entrega, devolução e cashback continuam sendo alterados só pelo
 *    vendedor e pela administração (funcionalidades/vendas.php e
 *    devolucoes.php recusam a recepção no servidor).
 * Do cliente, só nome e ID. O sistema não registra status de pagamento de
 * pedidos nem a data de cada mudança de status da entrega.
 * Depende de $recepcaoPedidos (bo_carregar_pedidos_consulta, includes/compras.php).
 */
$boPedidosStatus = array_intersect_key(bo_status_pedido_labels(), array_flip(['aguardando', 'preparando', 'despachado', 'entregue', 'cancelado', 'devolvido', 'extraviado']));
$boTrx = static fn(int $id): string => 'TRX-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
$boIdCliente = static fn(int $id): string => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
?>

<!-- ===== Marketplace: comprar produtos ou consultar pedidos e vendas ===== -->
<section class="bo-content-section" data-perfil="recepcionista" data-section="marketplace" data-bo-pedidos-consulta>
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Marketplace'); ?></h1>
            <p><?php echo of_t('Compre produtos na loja ou consulte as compras dos clientes durante o atendimento.'); ?></p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="bo-card h-100" data-bo-opcao="comprar">
                <div class="bo-card-label"><i class="bi bi-shop"></i> <?php echo of_t('Comprar produtos'); ?></div>
                <p class="my-2"><?php echo of_t('Abre a loja do Marketplace para comprar produtos.'); ?></p>
                <a class="btn-bo-gold" href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php"><i class="bi bi-bag"></i> <?php echo of_t('Abrir a loja'); ?></a>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="bo-card h-100" data-bo-opcao="consultar">
                <div class="bo-card-label"><i class="bi bi-receipt"></i> <?php echo of_t('Consultar pedidos e vendas'); ?></div>
                <p class="my-2"><?php echo of_t('Localize uma compra pelo número do pedido, cliente, status ou período. Somente consulta.'); ?></p>
                <a class="btn-bo-outline" href="#boRecepcaoPedidos"><i class="bi bi-search"></i> <?php echo of_t('Consultar pedidos'); ?></a>
            </div>
        </div>
    </div>

    <div id="boRecepcaoPedidos">
        <?php echo bo_selo_somente_consulta(); ?>
        <div class="bo-section-heading mt-1"><?php echo of_t('Pedidos e vendas ({n})', ['{n}' => (string) count($recepcaoPedidos)]); ?></div>
        <div class="bo-filters">
            <input type="text" class="form-control" style="max-width:280px" placeholder="<?php echo of_t('Buscar por pedido, cliente, produto ou rastreio'); ?>"
                data-bo-filter="search" data-bo-target="recepcaoPedidos">
            <input type="text" inputmode="numeric" class="form-control" style="max-width:220px" placeholder="<?php echo of_t('Buscar por ID do usuário'); ?>"
                data-bo-filter="user" data-bo-target="recepcaoPedidos">
            <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="recepcaoPedidos">
                <option value=""><?php echo of_t('Todos os status'); ?></option>
                <?php foreach ($boPedidosStatus as $valor => $label): ?>
                    <option value="<?php echo $valor; ?>"><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="bo-daterange">
                <?php echo of_t('De'); ?> <input type="date" class="form-control" data-bo-filter="date-from" data-bo-target="recepcaoPedidos">
                <?php echo of_t('até'); ?> <input type="date" class="form-control" data-bo-filter="date-to" data-bo-target="recepcaoPedidos">
            </div>
        </div>

        <div class="bo-table-wrap">
            <div class="table-responsive">
                <table class="bo-table" data-bo-table="recepcaoPedidos">
                    <thead>
                        <tr>
                            <th><?php echo of_t('Pedido'); ?></th>
                            <th><?php echo of_t('Data'); ?></th>
                            <th><?php echo of_t('Cliente'); ?></th>
                            <th><?php echo of_t('Produtos'); ?></th>
                            <th><?php echo of_t('Total'); ?></th>
                            <th><?php echo of_t('Pagamento'); ?></th>
                            <th><?php echo of_t('Cashback'); ?></th>
                            <th><?php echo of_t('Status do pedido'); ?></th>
                            <th><?php echo of_t('Detalhes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recepcaoPedidos as $ped): ?>
                            <?php
                            $boBusca = $ped['transacao'] . ' ' . $ped['idPedido'] . ' ' . $boIdCliente($ped['clienteId']) . ' ' . $ped['clienteId'] . ' ' . $ped['cliente'] . ' '
                                . implode(' ', array_column($ped['itens'], 'produto')) . ' ' . implode(' ', array_filter(array_column($ped['itens'], 'codigoRastreio')));
                            ?>
                            <tr data-status="<?php echo htmlspecialchars($ped['statusBanco'], ENT_QUOTES, 'UTF-8'); ?>" data-user="<?php echo (int) $ped['clienteId']; ?>"
                                data-date="<?php echo substr($ped['dataBanco'], 0, 10); ?>" data-bo-pedido="<?php echo (int) $ped['idPedido']; ?>"
                                data-search="<?php echo htmlspecialchars(mb_strtolower($boBusca), ENT_QUOTES, 'UTF-8'); ?>">
                                <td><strong><?php echo $ped['transacao']; ?></strong></td>
                                <td><?php echo $ped['data']; ?></td>
                                <td><?php echo htmlspecialchars($ped['cliente']); ?> <small class="text-secondary"><?php echo $boIdCliente($ped['clienteId']); ?></small></td>
                                <td>
                                    <?php foreach ($ped['itens'] as $it): ?>
                                        <div><?php echo (int) $it['quantidade']; ?>x <?php echo htmlspecialchars($it['produto']); ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo bo_money($ped['valorTotal']); ?></td>
                                <td><?php echo htmlspecialchars($ped['formaPagamento']); ?></td>
                                <td>
                                    <?php if ($ped['cashbackUsado'] > 0): ?><div><?php echo of_t('Usado: {valor}', ['{valor}' => bo_money($ped['cashbackUsado'])]); ?></div><?php endif; ?>
                                    <?php if ($ped['cashbackConcedido'] > 0): ?><div><?php echo of_t('Concedido: {valor}', ['{valor}' => bo_money($ped['cashbackConcedido'])]); ?></div><?php endif; ?>
                                    <?php if ($ped['cashbackUsado'] <= 0 && $ped['cashbackConcedido'] <= 0): ?>—<?php endif; ?>
                                </td>
                                <td><span class="bo-badge bo-compra-<?php echo htmlspecialchars($ped['statusBanco'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $ped['status']; ?></span></td>
                                <td>
                                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Ver detalhes do pedido'); ?>" aria-label="<?php echo of_t('Ver detalhes do pedido'); ?>"
                                        data-bs-toggle="modal" data-bs-target="#boRecepcaoPedido<?php echo (int) $ped['idPedido']; ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bo-empty-row" <?php echo empty($recepcaoPedidos) ? '' : 'style="display:none"'; ?>>
                            <td colspan="9"><?php echo of_t('Nenhum pedido encontrado para os filtros selecionados.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detalhes de cada pedido: só leitura, sem nenhuma ação -->
    <?php foreach ($recepcaoPedidos as $ped): ?>
        <div class="modal fade" id="boRecepcaoPedido<?php echo (int) $ped['idPedido']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Pedido {pedido}', ['{pedido}' => $ped['transacao']]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Cliente'); ?></div><div><?php echo htmlspecialchars($ped['cliente']); ?> <small class="text-secondary"><?php echo $boIdCliente($ped['clienteId']); ?></small></div></div>
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Data da compra'); ?></div><div><?php echo $ped['data']; ?></div></div>
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Status do pedido'); ?></div><div><span class="bo-badge bo-compra-<?php echo htmlspecialchars($ped['statusBanco'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $ped['status']; ?></span></div></div>
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Forma de pagamento'); ?></div><div><?php echo htmlspecialchars($ped['formaPagamento']); ?></div></div>
                    </div>

                    <div class="bo-section-heading"><?php echo of_t('Produtos'); ?></div>
                    <div class="bo-table-wrap"><div class="table-responsive">
                        <table class="bo-table">
                            <thead><tr><th><?php echo of_t('Produto'); ?></th><th><?php echo of_t('Vendido por'); ?></th><th><?php echo of_t('Qtd.'); ?></th><th><?php echo of_t('Preço unitário'); ?></th><th><?php echo of_t('Subtotal'); ?></th><th><?php echo of_t('Entrega'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($ped['itens'] as $it): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($it['produto']); ?></td>
                                        <td><?php echo htmlspecialchars($it['vendedor']); ?></td>
                                        <td><?php echo (int) $it['quantidade']; ?></td>
                                        <td><?php echo bo_money($it['precoUnitario']); ?></td>
                                        <td><?php echo bo_money($it['subtotal']); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($it['statusLogistica']); ?><?php if ($it['transportadora']): ?> · <?php echo htmlspecialchars($it['transportadora']); ?><?php endif; ?></div>
                                            <?php if ($it['codigoRastreio']): ?><div><small><?php echo of_t('Rastreio:'); ?> <?php echo htmlspecialchars($it['codigoRastreio']); ?></small></div><?php endif; ?>
                                            <?php if ($it['recebidoEm']): ?><div><small><?php echo of_t('Recebido em {data}', ['{data}' => $it['recebidoEm']]); ?></small></div><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$ped['itens']): ?><tr><td colspan="6"><?php echo of_t('Nenhum item registrado neste pedido.'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div></div>

                    <div class="row g-3 mt-1 mb-3">
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Produtos'); ?></div><div><?php echo bo_money($ped['subtotalItens']); ?></div></div>
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Frete'); ?></div><div><?php echo bo_money($ped['frete']); ?></div></div>
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Total do pedido'); ?></div><div><strong><?php echo bo_money($ped['valorTotal']); ?></strong></div></div>
                        <div class="col-6 col-md-3"><div class="bo-card-label"><?php echo of_t('Pagamento'); ?></div><div><?php echo htmlspecialchars($ped['formaPagamento']); ?></div></div>
                    </div>

                    <div class="bo-section-heading"><?php echo of_t('Cashback do pedido'); ?></div>
                    <div class="row g-3 mb-3" data-bo-pedido-cashback>
                        <div class="col-12 col-md-4"><div class="bo-card-label"><?php echo of_t('Usado no pedido'); ?></div><div data-bo-cashback-usado><?php echo $ped['cashbackUsado'] > 0 ? bo_money($ped['cashbackUsado']) : of_t('Não utilizado'); ?></div></div>
                        <div class="col-12 col-md-4"><div class="bo-card-label"><?php echo of_t('Concedido pela compra'); ?></div><div data-bo-cashback-concedido><?php echo $ped['cashbackConcedido'] > 0 ? bo_money($ped['cashbackConcedido']) : of_t('Sem cashback'); ?></div></div>
                        <?php if ($ped['devolucaoCreditada'] > 0): ?>
                            <div class="col-12 col-md-4"><div class="bo-card-label"><?php echo of_t('Devolução creditada'); ?></div><div data-bo-cashback-devolucao><?php echo bo_money($ped['devolucaoCreditada']); ?></div></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($ped['devolucao']): ?>
                        <div class="bo-section-heading"><?php echo of_t('Devolução'); ?></div>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($ped['devolucao']['status']); ?></strong></p>
                        <p class="mb-1"><?php echo of_t('Motivo:'); ?> <?php echo htmlspecialchars($ped['devolucao']['motivo']); ?></p>
                        <?php if ($ped['devolucao']['observacao'] !== ''): ?><p class="mb-3"><?php echo of_t('Observação:'); ?> <?php echo htmlspecialchars($ped['devolucao']['observacao']); ?></p><?php endif; ?>
                    <?php endif; ?>

                    <div class="bo-section-heading"><?php echo of_t('Linha do tempo'); ?></div>
                    <div class="bo-list" data-bo-pedido-eventos>
                        <?php foreach ($ped['eventos'] as $ev): ?>
                            <div class="bo-list-item">
                                <div class="bo-list-title"><?php echo htmlspecialchars($ev['texto']); ?></div>
                                <div class="bo-list-sub"><?php echo $ev['data']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-secondary small mt-2 mb-0"><?php echo of_t('O sistema não registra a data de cada mudança de status da entrega nem um status de pagamento separado para pedidos do Marketplace. Alterações no pedido são feitas pelo vendedor ou pela administração.'); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Fechar'); ?></button>
                </div>
            </div></div>
        </div>
    <?php endforeach; ?>
</section>
