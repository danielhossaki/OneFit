<?php
require_once __DIR__ . '/../../../config/localidades.php';
function bo_modal_aluno_editar(array $u, int $idUsuario): void
{
    ?>
    <div class="modal fade bo-modal bo-student-modal" id="modalPerfilEditar" tabindex="-1" aria-hidden="true" aria-labelledby="studentModalTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentModalTitle"><?php echo of_t('Editar perfil'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('update-profile.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-id"><?php echo of_t('ID do usuário'); ?></label>
                            <input id="student-id" type="text" class="form-control" value="#<?php echo str_pad((string) $idUsuario, 4, '0', STR_PAD_LEFT); ?>" readonly>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-documento"><?php echo of_t('Documento (CPF)'); ?></label>
                            <input id="student-documento" type="text" class="form-control" value="<?php echo bo_val($u['documento'] ?? ''); ?>" readonly aria-readonly="true" aria-describedby="student-identity-help">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-nome"><?php echo of_t('Nome'); ?></label>
                            <input id="student-nome" type="text" class="form-control" value="<?php echo bo_val($u['nome'] ?? ''); ?>" readonly aria-readonly="true" aria-describedby="student-identity-help">
                            <small id="student-identity-help"><?php echo of_t('Para alterar nome ou documento, entre em contato com o atendimento administrativo.'); ?></small>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-email"><?php echo of_t('E-mail'); ?></label>
                            <input id="student-email" type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-telefone"><?php echo of_t('Telefone/Celular'); ?></label>
                            <input id="student-telefone" type="text" class="form-control" name="telefone" value="<?php echo bo_val($u['telefone'] ?? ''); ?>" placeholder="DDD + número" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-nacionalidade"><?php echo of_t('Nacionalidade'); ?></label>
                            <?php onefitSelectLocalidade('student-nacionalidade', 'nacionalidade', (string) ($u['nacionalidade'] ?? ''), 'country'); ?>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-nascimento"><?php echo of_t('Data de nascimento'); ?></label>
                            <input id="student-nascimento" type="date" class="form-control" name="nascimento" value="<?php echo bo_val($u['nascimento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-genero"><?php echo of_t('Gênero'); ?></label>
                            <select id="student-genero" class="form-select" name="genero" required>
                                <option value="masculino" <?php echo ($u['genero'] ?? '') === 'masculino' ? 'selected' : ''; ?>><?php echo of_t('Masculino'); ?></option>
                                <option value="feminino" <?php echo ($u['genero'] ?? '') === 'feminino' ? 'selected' : ''; ?>><?php echo of_t('Feminino'); ?></option>
                                <option value="outro" <?php echo ($u['genero'] ?? '') === 'outro' ? 'selected' : ''; ?>><?php echo of_t('Outro'); ?></option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-estado"><?php echo of_t('Estado (UF)'); ?></label>
                            <?php onefitSelectLocalidade('student-estado', 'estado', (string) ($u['estado'] ?? ''), 'state'); ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-endereco"><?php echo of_t('Endereço'); ?></label>
                            <input id="student-endereco" type="text" class="form-control" name="endereco" value="<?php echo bo_val($u['endereco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-cidade"><?php echo of_t('Cidade'); ?></label>
                            <input id="student-cidade" type="text" class="form-control" name="cidade" value="<?php echo bo_val($u['cidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-altura"><?php echo of_t('Altura (m)'); ?></label>
                            <input id="student-altura" type="text" inputmode="decimal" maxlength="6" class="form-control" name="altura" value="<?php echo bo_val($u['altura'] ?? ''); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-peso"><?php echo of_t('Peso (kg)'); ?></label>
                            <input id="student-peso" type="text" inputmode="decimal" maxlength="7" class="form-control" name="peso" value="<?php echo bo_val($u['peso'] ?? ''); ?>">
                        </div>
                        <div class="col-12"><div class="bo-student-imc" aria-live="polite"><span><?php echo of_t('IMC calculado (adultos)'); ?></span><strong data-student-imc><?php echo of_t('Não informado'); ?></strong><span data-student-class></span></div></div>
                        <div class="col-12">
                            <label class="form-label" for="studentModalPhoto"><?php echo of_t('Foto'); ?></label>
                            <input type="file" class="form-control" id="studentModalPhoto" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
                            <small><?php echo of_t('JPG, PNG ou WEBP · Até 3 MB'); ?></small>
                            <img class="bo-student-preview" data-student-preview alt="<?php echo of_t('Prévia da nova foto'); ?>" hidden>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                        <button type="submit" class="btn-bo-gold"><?php echo of_t('Salvar'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

