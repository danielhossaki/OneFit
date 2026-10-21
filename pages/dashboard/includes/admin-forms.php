<?php
require_once __DIR__ . '/../../../config/localidades.php';
/**
 * includes/admin-forms.php
 * Modais de cadastro/edição do admin, 100% em PHP: cada função aqui
 * imprime um <div class="modal"> do Bootstrap com um <form method="POST">
 * de verdade dentro, que envia direto para funcionalidades/<recurso>.php
 * (sem fetch, sem JS montando campo por campo). O Bootstrap só é usado
 * para abrir/fechar o modal (via data-bs-toggle/data-bs-target, que já
 * vem de fábrica no bootstrap.bundle.min.js — não é lógica de negócio).
 *
 * Como não há JS guardando "em qual aba eu estava", cada formulário leva
 * um <input type="hidden" name="secao"> para o handler saber pra onde
 * redirecionar depois de salvar.
 *
 * Convenção de IDs: um modal de criação usa "Novo" (ex: #modalUsuarioNovo)
 * e um modal de edição usa o ID do registro (ex: #modalUsuarioEditar12),
 * já que cada linha da tabela precisa do seu próprio modal pré-preenchido
 * (não existe "preencher campo dinamicamente" sem JS).
 */

function bo_form_action(string $arquivo): string
{
    return BASE_URL . 'pages/dashboard/funcionalidades/' . $arquivo;
}

function bo_action_url(string $arquivo): string
{
    return BASE_URL . 'pages/dashboard/actions/' . $arquivo;
}

function bo_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
}

function bo_hidden(string $name, $value): string
{
    return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
}

function bo_val($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =======================================================================
 * USUÁRIOS
 * ===================================================================== */
function bo_modal_usuario(?array $u, string $secao, array $planos = []): void
{
    $isEdit = $u !== null;
    $modalId = $isEdit ? 'modalUsuarioEditar' . $u['id'] : 'modalUsuarioNovo';
    $titulo = $isEdit ? 'Editar usuário' : 'Novo usuário';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($titulo); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('usuarios.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $u['id']); ?><?php endif; ?>

                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome completo'); ?></label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($u['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('E-mail'); ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('CPF (11 números)'); ?></label>
                            <input type="text" class="form-control" name="cpf" value="<?php echo bo_val($u['cpf'] ?? ''); ?>" required>
                        </div>
                        <?php if (!$isEdit): ?>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Senha'); ?></label>
                                <input type="password" class="form-control" name="senha" minlength="6" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Celular'); ?></label>
                                <input type="text" class="form-control" name="celular" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Data de nascimento'); ?></label>
                                <input type="date" class="form-control" name="nascimento" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Gênero'); ?></label>
                                <select class="form-select" name="genero" required>
                                    <option value="masculino"><?php echo of_t('Masculino'); ?></option>
                                    <option value="feminino"><?php echo of_t('Feminino'); ?></option>
                                    <option value="outro"><?php echo of_t('Outro'); ?></option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Nacionalidade'); ?></label>
                                <input type="text" class="form-control" name="nacionalidade" value="Brasil" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Estado (UF)'); ?></label>
                                <input type="text" class="form-control" name="estado" maxlength="2" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo of_t('Endereço'); ?></label>
                                <input type="text" class="form-control" name="endereco" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?php echo of_t('Cidade'); ?></label>
                                <input type="text" class="form-control" name="cidade" required>
                            </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Status'); ?></label>
                            <select class="form-select" name="status">
                                <option value="ativo" <?php echo ($u['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>><?php echo of_t('Ativo'); ?></option>
                                <option value="inativo" <?php echo ($u['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>><?php echo of_t('Inativo'); ?></option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Acesso'); ?></label>
                            <select class="form-select" name="acesso">
                                <option value="Liberado" <?php echo ($u['acesso'] ?? onefitTraduzir('Liberado')) === onefitTraduzir('Liberado') ? 'selected' : ''; ?>><?php echo of_t('Liberado'); ?></option>
                                <option value="Bloqueado" <?php echo ($u['acesso'] ?? '') === onefitTraduzir('Bloqueado') ? 'selected' : ''; ?>><?php echo of_t('Bloqueado'); ?></option>
                            </select>
                        </div>
                        <?php if ($isEdit): ?>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Plano'); ?></label>
                                <select class="form-select" name="id_plano">
                                    <option value=""><?php echo of_t('Sem plano'); ?></option>
                                    <?php foreach ($planos as $p): ?>
                                        <option value="<?php echo (int) $p['id']; ?>" <?php echo (int) ($u['idPlano'] ?? 0) === $p['id'] ? 'selected' : ''; ?>>
                                            <?php echo bo_val($p['nome']); ?><?php echo $p['status'] !== 'ativo' ? ' (inativo)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Data inicial (matrícula)'); ?></label>
                                <input type="date" class="form-control" name="dataInicial" value="<?php echo bo_val($u['dataInicial'] ?? ''); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php echo of_t('Final de contrato (matrícula)'); ?></label>
                                <input type="date" class="form-control" name="dataFinal" value="<?php echo bo_val($u['dataFinal'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
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

/* =======================================================================
 * FUNCIONÁRIOS (regras em includes/funcionarios.php)
 * ===================================================================== */

/** Campos de vínculo com cadastro_profissional (usados só para o cargo Professor). */
function bo_campos_cadastro_profissional(string $idBase, array $cadastrosLivres): void
{
    ?>
    <div class="col-12">
        <fieldset class="border rounded p-3">
            <legend class="fs-6 mb-2"><?php echo of_t('Cadastro profissional (somente para o cargo Professor)'); ?></legend>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="cadastro_modo" id="<?php echo $idBase; ?>Novo" value="novo" checked>
                <label class="form-check-label" for="<?php echo $idBase; ?>Novo"><?php echo of_t('Criar novo cadastro profissional'); ?></label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="cadastro_modo" id="<?php echo $idBase; ?>Existente" value="existente" <?php echo $cadastrosLivres ? '' : 'disabled'; ?>>
                <label class="form-check-label" for="<?php echo $idBase; ?>Existente"><?php echo of_t('Vincular cadastro existente (ainda sem conta)'); ?></label>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="<?php echo $idBase; ?>Id"><?php echo of_t('Cadastro existente'); ?></label>
                    <select class="form-select" name="id_profissional" id="<?php echo $idBase; ?>Id">
                        <option value=""><?php echo of_t('Selecione'); ?></option>
                        <?php foreach ($cadastrosLivres as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>">
                                <?php echo bo_val($c['nome'] . ' — ' . $c['email'] . ($c['especialidade'] ? ' (' . $c['especialidade'] . ')' : '')); ?><?php echo $c['status'] !== 'ativo' ? ' · ' . of_t('inativo') : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="<?php echo $idBase; ?>Especialidade"><?php echo of_t('Especialidade (cadastro novo)'); ?></label>
                    <input type="text" class="form-control" name="especialidade" id="<?php echo $idBase; ?>Especialidade" maxlength="100">
                </div>
            </div>
            <small class="text-muted d-block mt-2"><?php echo of_t('O vínculo nunca é feito pela coincidência de e-mail: escolha explicitamente o cadastro.'); ?></small>
            <small class="text-muted d-block mt-1"><i class="bi bi-calendar2-check"></i> <?php echo of_t('Com o cadastro profissional vinculado, o professor aparece na tela Professores, disponível para agendamentos; lá você define a disponibilidade e os dados profissionais.'); ?></small>
        </fieldset>
    </div>
    <?php
}

/** $cargos: os que o operador pode atribuir (onefitCargosAtribuiveis); o servidor confere de novo. */
function bo_modal_funcionario(?array $f, array $cadastrosLivres, string $secao, ?array $cargos = null): void
{
    $isEdit = $f !== null;
    $sufixo = $isEdit ? 'Editar' . (int) $f['id'] : 'Novo';
    $modalId = 'modalFuncionario' . $sufixo;
    $rotulos = bo_funcionarios_rotulos_cargo();
    $cargoAtual = $f['cargo'] ?? '';
    // Professor já vinculado não mostra os campos de cadastro profissional.
    $mostrarCadastro = !$isEdit || $cargoAtual !== 'professor' || (int) $f['cadastros'] === 0;
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar funcionário' : 'Novo funcionário'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('funcionarios.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', (int) $f['id']); ?><?php endif; ?>

                        <div class="col-12">
                            <label class="form-label" for="funcNome<?php echo $sufixo; ?>"><?php echo of_t('Nome completo'); ?></label>
                            <input type="text" class="form-control" id="funcNome<?php echo $sufixo; ?>" name="nome" maxlength="150" value="<?php echo bo_val($f['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcEmail<?php echo $sufixo; ?>"><?php echo of_t('E-mail'); ?></label>
                            <input type="email" class="form-control" id="funcEmail<?php echo $sufixo; ?>" name="email" maxlength="150" value="<?php echo bo_val($f['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcCpf<?php echo $sufixo; ?>"><?php echo of_t('CPF'); ?></label>
                            <input type="text" class="form-control" id="funcCpf<?php echo $sufixo; ?>" name="cpf" inputmode="numeric" maxlength="14" value="<?php echo bo_val($f['cpf'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcCelular<?php echo $sufixo; ?>"><?php echo of_t('Celular'); ?></label>
                            <input type="text" class="form-control" id="funcCelular<?php echo $sufixo; ?>" name="celular" inputmode="tel" maxlength="20" value="<?php echo bo_val($f['celular'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcNascimento<?php echo $sufixo; ?>"><?php echo of_t('Data de nascimento'); ?></label>
                            <input type="date" class="form-control" id="funcNascimento<?php echo $sufixo; ?>" name="nascimento" value="<?php echo bo_val($f['nascimento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcGenero<?php echo $sufixo; ?>"><?php echo of_t('Gênero'); ?></label>
                            <select class="form-select" id="funcGenero<?php echo $sufixo; ?>" name="genero" required>
                                <?php foreach (['masculino' => 'Masculino', 'feminino' => 'Feminino', 'outro' => 'Outro'] as $valor => $rotulo): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo ($f['genero'] ?? '') === $valor ? 'selected' : ''; ?>><?php echo of_t($rotulo); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcCargo<?php echo $sufixo; ?>"><?php echo of_t('Cargo'); ?></label>
                            <select class="form-select" id="funcCargo<?php echo $sufixo; ?>" name="cargo" required>
                                <?php foreach ($cargos ?? onefitCargosAtribuiveisPorAdmin() as $cargo): ?>
                                    <option value="<?php echo bo_val($cargo); ?>" <?php echo $cargoAtual === $cargo ? 'selected' : ''; ?>><?php echo of_t($rotulos[$cargo]); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcNacionalidade<?php echo $sufixo; ?>"><?php echo of_t('Nacionalidade'); ?></label>
                            <?php onefitSelectLocalidade('funcNacionalidade' . $sufixo, 'nacionalidade', (string) ($f['nacionalidade'] ?? 'BR'), 'country'); ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcEstado<?php echo $sufixo; ?>"><?php echo of_t('Estado (UF)'); ?></label>
                            <?php onefitSelectLocalidade('funcEstado' . $sufixo, 'estado', (string) ($f['estado'] ?? ''), 'state'); ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="funcCidade<?php echo $sufixo; ?>"><?php echo of_t('Cidade'); ?></label>
                            <input type="text" class="form-control" id="funcCidade<?php echo $sufixo; ?>" name="cidade" maxlength="100" value="<?php echo bo_val($f['cidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="funcEndereco<?php echo $sufixo; ?>"><?php echo of_t('Endereço'); ?></label>
                            <input type="text" class="form-control" id="funcEndereco<?php echo $sufixo; ?>" name="endereco" maxlength="255" value="<?php echo bo_val($f['endereco'] ?? ''); ?>" required>
                        </div>

                        <?php if ($mostrarCadastro): ?>
                            <?php bo_campos_cadastro_profissional('funcCadastro' . $sufixo, $cadastrosLivres); ?>
                        <?php else: ?>
                            <div class="col-12"><small class="text-muted"><i class="bi bi-link-45deg"></i> <?php echo of_t('Cadastro profissional já vinculado a esta conta.'); ?> <?php echo of_t('Nome, e-mail e celular salvos aqui também atualizam o cadastro profissional; a disponibilidade para agendamentos fica na tela Professores.'); ?></small></div>
                        <?php endif; ?>

                        <div class="col-12">
                            <small class="text-muted">
                                <i class="bi bi-shield-lock"></i>
                                <?php echo of_t($isEdit
                                    ? 'A senha não é alterada por aqui. Se o e-mail mudar, a pessoa precisará confirmar o novo endereço.'
                                    : 'A senha nunca é definida pelo administrador: quem tem acesso ao painel recebe por e-mail um link de uso único para criar a própria senha. Faxineiras não têm login.'); ?>
                            </small>
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

/** Confirmação explícita para ativar ou inativar (envia o status desejado, nunca alterna). */
function bo_modal_funcionario_status(array $f, string $secao): void
{
    $ativo = $f['status'] === 'ativo';
    $modalId = 'modalFuncionarioStatus' . (int) $f['id'];
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($ativo ? 'Inativar {nome}?' : 'Ativar {nome}?', ['{nome}' => $f['nome']]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('funcionarios.php'); ?>">
                    <div class="modal-body">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'status'); ?>
                        <?php echo bo_hidden('id', (int) $f['id']); ?>
                        <?php echo bo_hidden('status', $ativo ? 'inativo' : 'ativo'); ?>
                        <p class="mb-0"><?php echo of_t($ativo
                            ? 'A pessoa perde o acesso ao painel na próxima ação e a catraca deixa de liberá-la. Links de acesso pendentes são cancelados.'
                            : 'A conta volta a valer para o painel (se o cargo tiver acesso) e para a catraca, conforme a regra do cargo.'); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                        <button type="submit" class="btn-bo-gold"><?php echo of_t($ativo ? 'Sim, inativar' : 'Sim, ativar'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function bo_modal_funcionario_acesso(array $f, string $secao): void
{
    $modalId = 'modalFuncionarioAcesso' . (int) $f['id'];
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Enviar link de acesso para {nome}?', ['{nome}' => $f['nome']]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('funcionarios.php'); ?>">
                    <div class="modal-body">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'reenviar-acesso'); ?>
                        <?php echo bo_hidden('id', (int) $f['id']); ?>
                        <p class="mb-0"><?php echo of_t('Um novo link de uso único, válido por 30 minutos, será enviado para {email}. Links enviados antes deixam de valer.', ['{email}' => $f['email']]); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                        <button type="submit" class="btn-bo-gold"><i class="bi bi-envelope"></i> <?php echo of_t('Enviar link'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function bo_modal_funcionario_vincular(array $f, array $cadastrosLivres, string $secao): void
{
    $modalId = 'modalFuncionarioVincular' . (int) $f['id'];
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Vincular cadastro profissional de {nome}', ['{nome}' => $f['nome']]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('funcionarios.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'vincular-cadastro'); ?>
                        <?php echo bo_hidden('id', (int) $f['id']); ?>
                        <?php bo_campos_cadastro_profissional('funcVincular' . (int) $f['id'], $cadastrosLivres); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                        <button type="submit" class="btn-bo-gold"><?php echo of_t('Vincular'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/* =======================================================================
 * PAGAMENTOS
 * ===================================================================== */
function bo_modal_pagamento(?array $p, string $secao): void
{
    $isEdit = $p !== null;
    $modalId = $isEdit ? 'modalPagamentoEditar' . $p['id'] : 'modalPagamentoNovo';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar pagamento' : 'Registrar pagamento'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('pagamentos.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Data'); ?></label>
                            <input type="date" class="form-control" name="data" max="<?php echo date('Y-m-d'); ?>" value="<?php echo bo_val(isset($p['data']) ? date('Y-m-d', strtotime($p['data'])) : ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Tipo'); ?></label>
                            <select class="form-select" name="tipo">
                                <option value="PIX" <?php echo ($p['tipo'] ?? 'PIX') === 'PIX' ? 'selected' : ''; ?>>PIX</option>
                                <option value="Cartão" <?php echo ($p['tipo'] ?? '') === 'Cartão' ? 'selected' : ''; ?>><?php echo of_t('Cartão'); ?></option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Valor'); ?></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" value="<?php echo bo_val($p['valor'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('ID do usuário'); ?></label>
                            <input type="text" class="form-control" name="usuarioId" value="<?php echo bo_val($p['usuarioId'] ?? ''); ?>" required>
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

/* =======================================================================
 * CASHBACKS
 * ===================================================================== */
function bo_modal_cashback_lancar(string $secao): void
{
    ?>
    <div class="modal fade bo-modal" id="modalCashbackLancar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Lançar cashback'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('cashbacks.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'create'); ?>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Data'); ?></label>
                            <input type="date" class="form-control" name="data" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Tipo'); ?></label>
                            <select class="form-select" name="tipo">
                                <option value="credito"><?php echo of_t('Crédito'); ?></option>
                                <option value="debito"><?php echo of_t('Débito'); ?></option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Valor'); ?></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('ID do usuário'); ?></label>
                            <input type="text" class="form-control" name="usuarioId" required>
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

function bo_modal_cashback_massa(string $secao): void
{
    ?>
    <div class="modal fade bo-modal" id="modalCashbackMassa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Distribuição em massa'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('cashbacks.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'massa'); ?>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Data'); ?></label>
                            <input type="date" class="form-control" name="data" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Valor (por usuário)'); ?></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Alvo'); ?></label>
                            <select class="form-select" name="alvo">
                                <option value="Todos"><?php echo of_t('Todos os usuários'); ?></option>
                                <option value="Ativos"><?php echo of_t('Somente usuários ativos'); ?></option>
                            </select>
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

/* =======================================================================
 * CATEGORIAS
 * ===================================================================== */
function bo_modal_categoria(?array $c, string $secao): void
{
    $isEdit = $c !== null;
    $modalId = $isEdit ? 'modalCategoriaEditar' . $c['id'] : 'modalCategoriaNova';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar categoria' : 'Nova categoria'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('categorias.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $c['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome da categoria'); ?></label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($c['nome'] ?? ''); ?>" required>
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

/* =======================================================================
 * MODALIDADES
 * ===================================================================== */

/**
 * Ícones disponíveis para uma modalidade (chave => rótulo exibido no
 * select). As chaves batem com ICONES_MODALIDADE em index.php, que tem o
 * SVG de cada uma — mesmos ícones que já existem na home hoje, mais um
 * genérico para modalidades novas.
 */
function bo_icones_modalidade_options(): array
{
    return [
        'musculacao' => 'Halteres (Musculação)',
        'crosstraining' => 'Raio (CrossTraining)',
        'funcional' => 'Gota (Funcional)',
        'spinning' => 'Relógio (Spinning)',
        'boxe' => 'Luva (Boxe)',
        'mobilidade' => 'Folha (Mobilidade & Yoga)',
        'generico' => 'Estrela (genérico)',
    ];
}

function bo_modal_modalidade(?array $m, string $secao): void
{
    $isEdit = $m !== null;
    $modalId = $isEdit ? 'modalModalidadeEditar' . $m['id'] : 'modalModalidadeNova';
    $iconeAtual = $m['icone'] ?? 'generico';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar modalidade' : 'Nova modalidade'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('modalidades.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $m['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome da modalidade'); ?></label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($m['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Descrição (exibida na home)'); ?></label>
                            <textarea class="form-control" name="descricao" rows="3" maxlength="300"><?php echo bo_val($m['descricao'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Ícone (exibido na home)'); ?></label>
                            <select class="form-select" name="icone">
                                <?php foreach (bo_icones_modalidade_options() as $chave => $rotulo): ?>
                                    <option value="<?php echo $chave; ?>" <?php echo $iconeAtual === $chave ? 'selected' : ''; ?>><?php echo of_t($rotulo); ?></option>
                                <?php endforeach; ?>
                            </select>
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

/* =======================================================================
 * PRODUTOS
 * ===================================================================== */
function bo_modal_produto(?array $p, string $secao, array $categoriasOptions): void
{
    $isEdit = $p !== null;
    $modalId = $isEdit ? 'modalProdutoEditar' . $p['id'] : 'modalProdutoNovo';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar produto' : onefitTraduzir('Cadastro de Produto')); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('produtos.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <?php echo bo_hidden('imagem_atual', $p['imagem'] ?? ''); ?>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome do produto'); ?></label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($p['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Categoria'); ?></label>
                            <select class="form-select" name="categoria">
                                <?php foreach ($categoriasOptions as $opt): ?>
                                    <option value="<?php echo bo_val($opt); ?>" <?php echo ($p['categoria'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo bo_val($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Preço'); ?></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="preco" value="<?php echo bo_val($p['preco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Desconto (%)'); ?></label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" name="desconto" value="<?php echo bo_val($p['desconto'] ?? '0'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Cashback (R$)'); ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="cashback" value="<?php echo bo_val($p['cashback'] ?? '0'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Estoque'); ?></label>
                            <input type="number" step="1" min="0" class="form-control" name="estoque" value="<?php echo bo_val($p['estoque'] ?? '0'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Imagem do produto'); ?></label>
                            <input type="file" class="form-control" name="imagem_arquivo" accept="image/png,image/jpeg,image/webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Descrição'); ?></label>
                            <textarea class="form-control" name="descricao" rows="3"><?php echo bo_val($p['descricao'] ?? ''); ?></textarea>
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

/* =======================================================================
 * PLANOS
 * ===================================================================== */
function bo_modal_plano(?array $p, string $secao): void
{
    $isEdit = $p !== null;
    $modalId = $isEdit ? 'modalPlanoEditar' . $p['id'] : 'modalPlanoNovo';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar plano' : onefitTraduzir('Novo Plano')); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('planos.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome do plano'); ?></label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($p['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Valor do plano'); ?></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" value="<?php echo bo_val($p['valor'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Ciclo'); ?></label>
                            <select class="form-select" name="ciclo">
                                <?php foreach (['Mensal', 'Trimestral', 'Semestral', 'Anual'] as $ciclo): ?>
                                    <option value="<?php echo $ciclo; ?>" <?php echo ($p['ciclo'] ?? 'Mensal') === $ciclo ? 'selected' : ''; ?>><?php echo of_t($ciclo); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Status'); ?></label>
                            <select class="form-select" name="status">
                                <option value="ativo" <?php echo ($p['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>><?php echo of_t('Ativo'); ?></option>
                                <option value="inativo" <?php echo ($p['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>><?php echo of_t('Inativo'); ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Descrição'); ?></label>
                            <textarea class="form-control" name="descricao" rows="3"><?php echo bo_val($p['descricao'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Benefícios (um por linha, exibidos no card do site)'); ?></label>
                            <textarea class="form-control" name="beneficios" rows="4" placeholder="<?php echo of_t('Acesso à musculação') . '&#10;' . of_t('2 modalidades por semana') . '&#10;' . of_t('Avaliação física inicial'); ?>"><?php echo bo_val($p['beneficios'] ?? ''); ?></textarea>
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

/* =======================================================================
 * PROFESSORES (regras em includes/professores.php)
 * ===================================================================== */

/**
 * Dados profissionais de um professor. Nome, e-mail, celular e situação da
 * conta são da tela Funcionários; a disponibilidade tem controle próprio na
 * lista. A foto atual nunca vai no formulário: só um upload novo a troca.
 */
function bo_modal_professor(array $p, string $secao, array $modalidadesOptions): void
{
    $modalId = 'modalProfissionalEditar' . (int) $p['id'];
    $sufixo = (int) $p['id'];
    $modalidadesSelecionadas = array_map('trim', explode(',', $p['modalidades']));
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Dados profissionais de {nome}', ['{nome}' => $p['nome']]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('profissionais.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'update'); ?>
                        <?php echo bo_hidden('id', (int) $p['id']); ?>
                        <div class="col-12">
                            <small class="text-muted"><i class="bi bi-person-vcard"></i> <?php echo of_t('Nome, e-mail, celular e situação da conta são alterados na tela Funcionários.'); ?></small>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="profEspecialidade<?php echo $sufixo; ?>"><?php echo of_t('Especialidade'); ?></label>
                            <input type="text" class="form-control" id="profEspecialidade<?php echo $sufixo; ?>" name="especialidade" maxlength="100" value="<?php echo bo_val($p['especialidade']); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="profDocumento<?php echo $sufixo; ?>"><?php echo of_t('Documento / registro'); ?></label>
                            <input type="text" class="form-control" id="profDocumento<?php echo $sufixo; ?>" name="documento" maxlength="100" value="<?php echo bo_val($p['documento']); ?>">
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block"><?php echo of_t('Modalidades ministradas'); ?></span>
                            <div class="bo-checklist">
                                <?php foreach ($modalidadesOptions as $modalidadeOpt): ?>
                                    <label class="bo-checklist-item">
                                        <input type="checkbox" name="modalidades[]" value="<?php echo bo_val($modalidadeOpt); ?>" <?php echo in_array($modalidadeOpt, $modalidadesSelecionadas, true) ? 'checked' : ''; ?>>
                                        <?php echo bo_val($modalidadeOpt); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="profDescricao<?php echo $sufixo; ?>"><?php echo of_t('Descrição'); ?></label>
                            <textarea class="form-control" id="profDescricao<?php echo $sufixo; ?>" name="descricao" rows="3" maxlength="2000"><?php echo bo_val($p['descricao']); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="profFoto<?php echo $sufixo; ?>"><?php echo of_t('Foto'); ?></label>
                            <input type="file" class="form-control" id="profFoto<?php echo $sufixo; ?>" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
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

/* =======================================================================
 * MEU PERFIL (qualquer perfil logado — não é exclusivo do admin)
 * ===================================================================== */
function bo_modal_perfil_editar(array $u, int $idUsuario): void
{
    ?>
    <div class="modal fade bo-modal" id="modalPerfilEditar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Editar perfil'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('update-profile.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('foto_atual', $u['foto'] ?? ''); ?>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('ID do usuário'); ?></label>
                            <input type="text" class="form-control" value="#<?php echo str_pad((string) $idUsuario, 4, '0', STR_PAD_LEFT); ?>" disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Documento (CPF)'); ?></label>
                            <input type="text" class="form-control" name="documento" value="<?php echo bo_val($u['documento'] ?? ''); ?>" readonly aria-readonly="true" style="opacity:.7;cursor:not-allowed">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome'); ?></label><small><?php echo of_t('Para alterar nome ou documento, entre em contato com o atendimento administrativo.'); ?></small>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($u['nome'] ?? ''); ?>" readonly aria-readonly="true" style="opacity:.7;cursor:not-allowed">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('E-mail'); ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Telefone/Celular'); ?></label>
                            <input type="text" class="form-control" name="telefone" value="<?php echo bo_val($u['telefone'] ?? ''); ?>" placeholder="<?php echo of_t('DDD + número'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="perfil-nacionalidade"><?php echo of_t('Nacionalidade'); ?></label>
                            <?php onefitSelectLocalidade('perfil-nacionalidade', 'nacionalidade', (string) ($u['nacionalidade'] ?? ''), 'country'); ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Data de nascimento'); ?></label>
                            <input type="date" class="form-control" name="nascimento" value="<?php echo bo_val($u['nascimento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Gênero'); ?></label>
                            <select class="form-select" name="genero" required>
                                <option value="masculino" <?php echo ($u['genero'] ?? '') === 'masculino' ? 'selected' : ''; ?>><?php echo of_t('Masculino'); ?></option>
                                <option value="feminino" <?php echo ($u['genero'] ?? '') === 'feminino' ? 'selected' : ''; ?>><?php echo of_t('Feminino'); ?></option>
                                <option value="outro" <?php echo ($u['genero'] ?? '') === 'outro' ? 'selected' : ''; ?>><?php echo of_t('Outro'); ?></option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="perfil-estado"><?php echo of_t('Estado (UF)'); ?></label>
                            <?php onefitSelectLocalidade('perfil-estado', 'estado', (string) ($u['estado'] ?? ''), 'state'); ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Endereço'); ?></label>
                            <input type="text" class="form-control" name="endereco" value="<?php echo bo_val($u['endereco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Cidade'); ?></label>
                            <input type="text" class="form-control" name="cidade" value="<?php echo bo_val($u['cidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Altura (m)'); ?></label>
                            <input type="number" step="0.01" min="0.5" max="3" class="form-control" name="altura" value="<?php echo bo_val($u['altura'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Peso (kg)'); ?></label>
                            <input type="number" step="0.1" min="1" max="500" class="form-control" name="peso" value="<?php echo bo_val($u['peso'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Foto'); ?></label>
                            <input type="file" class="form-control" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
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

function bo_modal_senha_alterar(): void
{
    ?>
    <div class="modal fade bo-modal" id="modalSenhaAlterar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Alterar senha'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('senha.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <div class="col-12">
                            <label class="form-label" for="boSenhaAtual"><?php echo of_t('Senha atual'); ?></label>
                            <input type="password" class="form-control" id="boSenhaAtual" name="senha_atual" autocomplete="current-password" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="boSenhaNova"><?php echo of_t('Nova senha'); ?></label>
                            <input type="password" class="form-control" id="boSenhaNova" name="senha_nova" autocomplete="new-password" minlength="8" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="boSenhaConfirma"><?php echo of_t('Confirmar nova senha'); ?></label>
                            <input type="password" class="form-control" id="boSenhaConfirma" name="senha_confirma" autocomplete="new-password" minlength="8" required>
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

/**
 * Botão "Excluir" sem JS customizado: abre um modal Bootstrap centralizado
 * (modal-dialog-centered) com um <form method="POST"> real apontando pro
 * handler do recurso. Usar sempre em conjunto com bo_modal_confirmar_exclusao()
 * para o mesmo $recurso/$id.
 */
function bo_botao_excluir(string $recurso, $id): string
{
    $modalId = 'modalExcluir' . $recurso . $id;
    return '<button type="button" class="btn-bo-icon danger" title="' . of_t('Excluir') . '" data-bs-toggle="modal" data-bs-target="#' . $modalId . '"><i class="bi bi-trash"></i></button>';
}

/**
 * Modal de confirmação de exclusão, centralizado na tela. O <form> envia
 * acao=delete direto pro handler do recurso (ex: produtos.php) — quem
 * efetivamente apaga é sempre o handler, nunca este modal.
 */
function bo_modal_confirmar_exclusao(string $recurso, $id, string $nome, string $secao): void
{
    $modalId = 'modalExcluir' . $recurso . $id;
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> <?php echo of_t('Excluir {nome}?', ['{nome}' => $nome]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action($recurso . '.php'); ?>">
                    <div class="modal-body">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'delete'); ?>
                        <?php echo bo_hidden('id', $id); ?>
                        <p class="mb-0"><?php echo of_t('Essa exclusão remove o registro definitivamente do banco de dados e não pode ser desfeita.'); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                        <button type="submit" class="btn-bo-gold" style="background:#dc3545;border-color:#dc3545;color:#fff;">
                            <i class="bi bi-trash"></i> <?php echo of_t('Sim, excluir'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Botão "Pausar/Ativar" sem JS: um <form method="POST"> de um clique só,
 * igual ao padrão já usado no carrinho (± quantidade).
 */
function bo_form_toggle(string $recurso, $id, string $secao, bool $ativo, string $onLabel = 'Ativo', string $offLabel = 'Inativo'): void
{
    $arquivo = $recurso . '.php';
    ?>
    <form method="POST" action="<?php echo bo_form_action($arquivo); ?>" style="display:inline;">
        <?php echo bo_csrf_field(); ?>
        <?php echo bo_hidden('secao', $secao); ?>
        <?php echo bo_hidden('acao', 'toggle-status'); ?>
        <?php echo bo_hidden('id', $id); ?>
        <button type="submit" class="btn-bo-icon" title="<?php echo $ativo ? 'Inativar' : 'Ativar'; ?>">
            <i class="bi <?php echo $ativo ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
        </button>
    </form>
    <?php
}

/* =======================================================================
 * TRANSPORTADORAS (tela "Vendas Marketplace" > aba Transportadoras, admin)
 * ===================================================================== */
function bo_modal_transportadora(?array $t, string $secao): void
{
    $isEdit = $t !== null;
    $modalId = $isEdit ? 'modalTransportadoraEditar' . $t['id'] : 'modalTransportadoraNova';
    $tipos = ['transportadora' => onefitTraduzir('Transportadora'), 'correios' => 'Correios', 'sedex' => 'Sedex', 'motoboy' => 'Motoboy', 'outros' => 'Outros'];
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t($isEdit ? 'Editar transportadora' : 'Novo transportador'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('transportadoras.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $t['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Nome'); ?></label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($t['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?php echo of_t('Tipo de envio'); ?></label>
                            <select class="form-select" name="tipo">
                                <?php foreach ($tipos as $valor => $label): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo ($t['tipo'] ?? '') === $valor ? 'selected' : ''; ?>><?php echo of_t($label); ?></option>
                                <?php endforeach; ?>
                            </select>
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

/**
 * Faixa de CEP/frete de uma transportadora específica.
 */
function bo_modal_faixa_cep(int $idTransportadora, string $secao): void
{
    $modalId = 'modalFaixaCep' . $idTransportadora;
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Nova faixa de CEP'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('transportadoras.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'create-faixa'); ?>
                        <?php echo bo_hidden('id_transportadora', $idTransportadora); ?>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('CEP inicial'); ?></label>
                            <input type="text" class="form-control" name="cep_inicial" placeholder="01000-000" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('CEP final'); ?></label>
                            <input type="text" class="form-control" name="cep_final" placeholder="05999-999" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Valor do frete (R$)'); ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="valor_frete" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php echo of_t('Prazo (dias)'); ?></label>
                            <input type="number" step="1" min="0" class="form-control" name="prazo_dias" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                        <button type="submit" class="btn-bo-gold"><?php echo of_t('Adicionar faixa'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
