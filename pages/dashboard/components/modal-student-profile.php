<?php
function bo_modal_aluno_editar(array $u, int $idUsuario): void
{
    ?>
    <div class="modal fade bo-modal bo-student-modal" id="modalPerfilEditar" tabindex="-1" aria-hidden="true" aria-labelledby="studentModalTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentModalTitle">Editar perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('update-profile.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-id">ID do usuário</label>
                            <input id="student-id" type="text" class="form-control" value="#<?php echo str_pad((string) $idUsuario, 4, '0', STR_PAD_LEFT); ?>" readonly>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-documento">Documento (CPF)</label>
                            <input id="student-documento" type="text" class="form-control" name="documento" value="<?php echo bo_val($u['documento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-nome">Nome</label>
                            <input id="student-nome" type="text" class="form-control" name="nome" value="<?php echo bo_val($u['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-email">E-mail</label>
                            <input id="student-email" type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-telefone">Telefone/Celular</label>
                            <input id="student-telefone" type="text" class="form-control" name="telefone" value="<?php echo bo_val($u['telefone'] ?? ''); ?>" placeholder="DDD + número" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-nacionalidade">Nacionalidade</label>
                            <input id="student-nacionalidade" type="text" class="form-control" name="nacionalidade" value="<?php echo bo_val($u['nacionalidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-nascimento">Data de nascimento</label>
                            <input id="student-nascimento" type="date" class="form-control" name="nascimento" value="<?php echo bo_val($u['nascimento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-genero">Gênero</label>
                            <select id="student-genero" class="form-select" name="genero" required>
                                <option value="masculino" <?php echo ($u['genero'] ?? '') === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="feminino" <?php echo ($u['genero'] ?? '') === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="outro" <?php echo ($u['genero'] ?? '') === 'outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-estado">Estado (UF)</label>
                            <input id="student-estado" type="text" class="form-control" name="estado" maxlength="2" value="<?php echo bo_val($u['estado'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-endereco">Endereço</label>
                            <input id="student-endereco" type="text" class="form-control" name="endereco" value="<?php echo bo_val($u['endereco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-cidade">Cidade</label>
                            <input id="student-cidade" type="text" class="form-control" name="cidade" value="<?php echo bo_val($u['cidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-altura">Altura (m)</label>
                            <input id="student-altura" type="text" inputmode="decimal" maxlength="6" class="form-control" name="altura" value="<?php echo bo_val($u['altura'] ?? ''); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-peso">Peso (kg)</label>
                            <input id="student-peso" type="text" inputmode="decimal" maxlength="7" class="form-control" name="peso" value="<?php echo bo_val($u['peso'] ?? ''); ?>">
                        </div>
                        <div class="col-12"><div class="bo-student-imc" aria-live="polite"><span>IMC calculado (adultos)</span><strong data-student-imc>Não informado</strong><span data-student-class></span></div></div>
                        <div class="col-12">
                            <label class="form-label" for="studentModalPhoto">Foto</label>
                            <input type="file" class="form-control" id="studentModalPhoto" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
                            <small>JPG, PNG ou WEBP · Até 3 MB</small>
                            <img class="bo-student-preview" data-student-preview alt="Prévia da nova foto" hidden>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-bo-gold">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

