<?php
/**
 * components/modal-pagar-plano.php
 * Modal específico de pagamento, aberto pelo botão "Pagar Plano" na tela
 * "Histórico" do aluno (data-bs-target="#modalPagarPlano"). Tem campos
 * fixos porque o fluxo de pagamento (Pix x Cartão) tem uma interação
 * própria — ver dashboard.js, bloco "Modal Pagar plano (Pix/Cartão)".
 * O <form> envia direto para funcionalidades/pagar-plano.php (PRG real).
 *
 * IMPORTANTE: os campos de cartão aqui (número, validade, CVV) são só
 * para simulação visual — nenhum dado de cartão deve ser enviado/salvo
 * de verdade sem passar por um gateway de pagamento homologado (PCI-DSS).
 */
?>
<div class="modal fade bo-modal" id="modalPagarPlano" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo of_t('Pagar plano'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
            </div>
            <form id="formPagarPlano" method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/pagar-plano.php', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="secao" value="historico">

                <!-- Resumo do plano atual (somente leitura) -->
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label"><?php echo of_t('Valor'); ?></label>
                        <input type="text" class="form-control" value="<?php echo bo_money($alunoPerfil['valorContratado'] ?? 0); ?>" readonly>
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?php echo of_t('Tipo do plano'); ?></label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($alunoPerfil['plano'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                </div>

                <!-- Seletor do método de pagamento: alterna painelPix / painelCartao no JS -->
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="forma_pagamento" id="metodoPix" value="pix" checked>
                    <label class="btn-bo-outline" for="metodoPix" style="flex:1;text-align:center;">PIX</label>
                    <input type="radio" class="btn-check" name="forma_pagamento" id="metodoCredito" value="cartao">
                    <label class="btn-bo-outline" for="metodoCredito" style="flex:1;text-align:center;"><?php echo of_t('Crédito'); ?></label>
                    <input type="radio" class="btn-check" name="forma_pagamento" id="metodoDebito" value="cartao">
                    <label class="btn-bo-outline" for="metodoDebito" style="flex:1;text-align:center;"><?php echo of_t('Débito'); ?></label>
                </div>

                <!-- Painel Pix: QR code simulado + código "copia e cola" -->
                <div id="painelPix">
                    <button type="button" class="btn-bo-outline mb-3" id="btnGerarQr">
                        <i class="bi bi-qr-code"></i> <?php echo of_t('Gerar QR Code'); ?>
                    </button>
                    <div class="bo-pix-box" id="pixResultado" style="display:none">
                        <div class="bo-qr-placeholder"></div>
                        <input type="text" class="form-control mb-2" readonly id="pixCopiaCola" value="00020126580014BR.GOV.BCB.PIX0136onefit-pagamento-simulado5204000053039865802BR5909ONE FIT6009SAO PAULO62070503***6304ABCD">
                        <button type="button" class="btn-bo-outline" id="btnCopiarPix"><i class="bi bi-clipboard"></i> <?php echo of_t('Copiar código Pix'); ?></button>
                    </div>
                </div>

                <!-- Painel Cartão: campos apenas visuais/simulados (ver aviso no topo do arquivo) -->
                <div id="painelCartao" style="display:none">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Número do cartão'); ?></label>
                            <input type="text" class="form-control" placeholder="0000 0000 0000 0000">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Validade'); ?></label>
                            <input type="text" class="form-control" placeholder="MM/AA">
                        </div>
                        <div class="col-6">
                            <label class="form-label">CVV</label>
                            <input type="text" class="form-control" placeholder="123">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                <button type="submit" class="btn-bo-gold" id="btnPagar"><?php echo of_t('Pagar'); ?></button>
            </div>
            </form>
        </div>
    </div>
</div>
