<section class="bo-content-section" data-perfil="aluno" data-section="treino" id="boTreino"
    data-endpoint="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/treino.php'); ?>"
    data-csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="bo-page-title">
        <div><h1>Treino</h1><p>Monte e acompanhe sua ficha de treino.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-treino-limpar><i class="bi bi-eraser"></i> Limpar Treino</button>
            <button type="button" class="btn-bo-gold" data-treino-adicionar><i class="bi bi-plus-lg"></i> Adicionar Treino</button>
        </div>
    </div>
    <p data-treino-aviso role="status" aria-live="polite" hidden></p>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table"><thead><tr><th>Exercício</th><th>Séries</th><th>Repetições</th><th>Carga</th><th>Ações</th></tr></thead>
            <tbody data-treino-linhas>
                <?php foreach ($alunoTreino as $exercicio): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($exercicio['nome']); ?></td>
                        <td><?php echo (int) $exercicio['series']; ?></td>
                        <td><?php echo (int) $exercicio['repeticoes']; ?></td>
                        <td><?php echo (int) $exercicio['carga']; ?> kg</td>
                        <td><div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar" data-treino-editar="<?php echo (int) $exercicio['id']; ?>"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn-bo-icon danger" title="Excluir" data-treino-excluir="<?php echo (int) $exercicio['id']; ?>"><i class="bi bi-trash"></i></button>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$alunoTreino): ?><tr><td colspan="5">Nenhum exercício cadastrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
    <script type="application/json" data-treino-dados><?php echo bo_json($alunoTreino); ?></script>
</section>
<div class="modal fade bo-modal" id="boTreinoModal" tabindex="-1" aria-labelledby="boTreinoTitulo" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="boTreinoTitulo">Adicionar exercício</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <form id="boTreinoForm" class="row g-3">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="token">
                <div class="col-12">
                    <label class="form-label" for="boTreinoNome">Exercício</label>
                    <select class="form-select" name="nome" id="boTreinoNome" required>
                        <option value="">Selecione um exercício</option>
                        <?php foreach (bo_treino_catalogo() as $grupo => $nomes): ?>
                            <optgroup label="<?php echo htmlspecialchars($grupo); ?>">
                                <?php foreach ($nomes as $nome): ?><option><?php echo htmlspecialchars($nome); ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['series' => ['Séries', 1, 10, 3], 'repeticoes' => ['Repetições', 1, 50, 12], 'carga' => ['Carga (kg)', 0, 300, 0]] as $campo => [$label, $minimo, $maximo, $padrao]): ?>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="boTreino-<?php echo $campo; ?>"><?php echo $label; ?></label>
                        <select class="form-select" name="<?php echo $campo; ?>" id="boTreino-<?php echo $campo; ?>" required>
                            <?php for ($valor = $minimo; $valor <= $maximo; $valor++): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $valor === $padrao ? 'selected' : ''; ?>><?php echo $valor . ($campo === 'carga' ? ' kg' : ''); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <p class="col-12 mb-0" data-treino-erro role="alert" hidden></p>
            </form>
        </div>
        <div class="modal-footer"><button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn-bo-gold" form="boTreinoForm">Salvar</button></div>
    </div></div>
</div>
<div class="modal fade bo-modal" id="boTreinoConfirmar" tabindex="-1" aria-labelledby="boTreinoConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="boTreinoConfirmarTitulo">Limpar treino</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><p data-treino-pergunta></p><p data-treino-confirmar-erro role="alert" hidden></p></div>
        <div class="modal-footer"><button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn-bo-gold" data-treino-confirmar>Limpar treino</button></div>
    </div></div>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/treino.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/treino.js'); ?>" defer></script>
