<?php
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
function bo_modal_usuario(?array $u, string $secao): void
{
    $isEdit = $u !== null;
    $modalId = $isEdit ? 'modalUsuarioEditar' . $u['id'] : 'modalUsuarioNovo';
    $titulo = $isEdit ? 'Editar usuário' : 'Novo usuário';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $titulo; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('usuarios.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $u['id']); ?><?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Nome completo</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($u['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">CPF (11 números)</label>
                            <input type="text" class="form-control" name="cpf" value="<?php echo bo_val($u['cpf'] ?? ''); ?>" required>
                        </div>
                        <?php if (!$isEdit): ?>
                            <div class="col-6">
                                <label class="form-label">Senha</label>
                                <input type="password" class="form-control" name="senha" minlength="6" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Celular</label>
                                <input type="text" class="form-control" name="celular" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Data de nascimento</label>
                                <input type="date" class="form-control" name="nascimento" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Gênero</label>
                                <select class="form-select" name="genero" required>
                                    <option value="masculino">Masculino</option>
                                    <option value="feminino">Feminino</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nacionalidade</label>
                                <input type="text" class="form-control" name="nacionalidade" value="Brasil" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Estado (UF)</label>
                                <input type="text" class="form-control" name="estado" maxlength="2" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" name="endereco" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" name="cidade" required>
                            </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="ativo" <?php echo ($u['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($u['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Acesso</label>
                            <select class="form-select" name="acesso">
                                <option value="Liberado" <?php echo ($u['acesso'] ?? 'Liberado') === 'Liberado' ? 'selected' : ''; ?>>Liberado</option>
                                <option value="Bloqueado" <?php echo ($u['acesso'] ?? '') === 'Bloqueado' ? 'selected' : ''; ?>>Bloqueado</option>
                            </select>
                        </div>
                        <?php if ($isEdit): ?>
                            <div class="col-6">
                                <label class="form-label">Data inicial (matrícula)</label>
                                <input type="date" class="form-control" name="dataInicial" value="<?php echo bo_val($u['dataInicial'] ?? ''); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Final de contrato (matrícula)</label>
                                <input type="date" class="form-control" name="dataFinal" value="<?php echo bo_val($u['dataFinal'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>
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

/* =======================================================================
 * PERMISSÕES
 * ===================================================================== */
function bo_modal_permissao_nova(string $secao, array $funcoes): void
{
    ?>
    <div class="modal fade bo-modal" id="modalPermissaoNova" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cadastrar permissão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('permissoes.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'create'); ?>
                        <div class="col-12">
                            <label class="form-label">E-mail do usuário</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tipo de função</label>
                            <select class="form-select" name="funcao" required>
                                <?php foreach ($funcoes as $f): ?>
                                    <option value="<?php echo (int) $f['id']; ?>"><?php echo bo_val($f['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
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

function bo_modal_permissao_editar(array $p, string $secao, array $funcoes): void
{
    $modalId = 'modalPermissaoEditar' . $p['id'];
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar permissão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('permissoes.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'update'); ?>
                        <?php echo bo_hidden('id', $p['id']); ?>
                        <div class="col-12">
                            <p class="mb-0"><strong><?php echo bo_val($p['nome']); ?></strong> · <?php echo bo_val($p['email']); ?></p>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tipo de função</label>
                            <select class="form-select" name="funcao" required>
                                <?php foreach ($funcoes as $f): ?>
                                    <option value="<?php echo (int) $f['id']; ?>" <?php echo (int) $p['id_funcao'] === (int) $f['id'] ? 'selected' : ''; ?>><?php echo bo_val($f['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
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
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar pagamento' : 'Registrar pagamento'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('pagamentos.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <div class="col-6">
                            <label class="form-label">Data</label>
                            <input type="date" class="form-control" name="data" max="<?php echo date('Y-m-d'); ?>" value="<?php echo bo_val(isset($p['data']) ? date('Y-m-d', strtotime($p['data'])) : ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo">
                                <option value="PIX" <?php echo ($p['tipo'] ?? 'PIX') === 'PIX' ? 'selected' : ''; ?>>PIX</option>
                                <option value="Cartão" <?php echo ($p['tipo'] ?? '') === 'Cartão' ? 'selected' : ''; ?>>Cartão</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" value="<?php echo bo_val($p['valor'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">ID do usuário</label>
                            <input type="text" class="form-control" name="usuarioId" value="<?php echo bo_val($p['usuarioId'] ?? ''); ?>" required>
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
                    <h5 class="modal-title">Lançar cashback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('cashbacks.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'create'); ?>
                        <div class="col-6">
                            <label class="form-label">Data</label>
                            <input type="date" class="form-control" name="data" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo">
                                <option value="credito">Crédito</option>
                                <option value="debito">Débito</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">ID do usuário</label>
                            <input type="text" class="form-control" name="usuarioId" required>
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

function bo_modal_cashback_massa(string $secao): void
{
    ?>
    <div class="modal fade bo-modal" id="modalCashbackMassa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Distribuição em massa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('cashbacks.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'massa'); ?>
                        <div class="col-6">
                            <label class="form-label">Data</label>
                            <input type="date" class="form-control" name="data" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor (por usuário)</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alvo</label>
                            <select class="form-select" name="alvo">
                                <option value="Todos">Todos os usuários</option>
                                <option value="Ativos">Somente usuários ativos</option>
                            </select>
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
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar categoria' : 'Nova categoria'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('categorias.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $c['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome da categoria</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($c['nome'] ?? ''); ?>" required>
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

/* =======================================================================
 * FUNÇÕES
 * ===================================================================== */
function bo_modal_funcao(?array $f, string $secao): void
{
    $isEdit = $f !== null;
    $modalId = $isEdit ? 'modalFuncaoEditar' . $f['id'] : 'modalFuncaoNova';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar função' : 'Nova função'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('funcoes.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $f['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome da função</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($f['nome'] ?? ''); ?>" required>
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

/* =======================================================================
 * MODALIDADES
 * ===================================================================== */
function bo_modal_modalidade(?array $m, string $secao): void
{
    $isEdit = $m !== null;
    $modalId = $isEdit ? 'modalModalidadeEditar' . $m['id'] : 'modalModalidadeNova';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar modalidade' : 'Nova modalidade'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('modalidades.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $m['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome da modalidade</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($m['nome'] ?? ''); ?>" required>
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
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar produto' : 'Cadastro de Produto'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('produtos.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <?php echo bo_hidden('imagem_atual', $p['imagem'] ?? ''); ?>
                        <div class="col-12">
                            <label class="form-label">Nome do produto</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($p['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="categoria">
                                <?php foreach ($categoriasOptions as $opt): ?>
                                    <option value="<?php echo bo_val($opt); ?>" <?php echo ($p['categoria'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo bo_val($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Preço</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="preco" value="<?php echo bo_val($p['preco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Desconto (%)</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" name="desconto" value="<?php echo bo_val($p['desconto'] ?? '0'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Cashback (R$)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="cashback" value="<?php echo bo_val($p['cashback'] ?? '0'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Estoque</label>
                            <input type="number" step="1" min="0" class="form-control" name="estoque" value="<?php echo bo_val($p['estoque'] ?? '0'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Imagem do produto</label>
                            <input type="file" class="form-control" name="imagem_arquivo" accept="image/png,image/jpeg,image/webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" name="descricao" rows="3"><?php echo bo_val($p['descricao'] ?? ''); ?></textarea>
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
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar plano' : 'Novo Plano'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('planos.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome do plano</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($p['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor do plano</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="valor" value="<?php echo bo_val($p['valor'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Ciclo</label>
                            <select class="form-select" name="ciclo">
                                <?php foreach (['Mensal', 'Trimestral', 'Semestral', 'Anual'] as $ciclo): ?>
                                    <option value="<?php echo $ciclo; ?>" <?php echo ($p['ciclo'] ?? 'Mensal') === $ciclo ? 'selected' : ''; ?>><?php echo $ciclo; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="ativo" <?php echo ($p['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($p['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" name="descricao" rows="3"><?php echo bo_val($p['descricao'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Benefícios (um por linha, exibidos no card do site)</label>
                            <textarea class="form-control" name="beneficios" rows="4" placeholder="Acesso à musculação&#10;2 modalidades por semana&#10;Avaliação física inicial"><?php echo bo_val($p['beneficios'] ?? ''); ?></textarea>
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

/* =======================================================================
 * PROFISSIONAIS
 * ===================================================================== */
function bo_modal_profissional(?array $p, string $secao, array $modalidadesOptions): void
{
    $isEdit = $p !== null;
    $modalId = $isEdit ? 'modalProfissionalEditar' . $p['id'] : 'modalProfissionalNovo';
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar profissional' : 'Novo Profissional'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('profissionais.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $p['id']); ?><?php endif; ?>
                        <?php echo bo_hidden('foto_atual', $p['foto'] ?? ''); ?>
                        <div class="col-6">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($p['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Função / especialidade</label>
                            <input type="text" class="form-control" name="funcao" value="<?php echo bo_val($p['funcao'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Modalidades ministradas</label>
                            <?php
                            $modalidadesSelecionadas = array_map('trim', explode(',', (string) ($p['modalidades'] ?? '')));
                            ?>
                            <div class="bo-checklist">
                                <?php foreach ($modalidadesOptions as $modalidadeOpt): ?>
                                    <label class="bo-checklist-item">
                                        <input type="checkbox" name="modalidades[]" value="<?php echo bo_val($modalidadeOpt); ?>" <?php echo in_array($modalidadeOpt, $modalidadesSelecionadas, true) ? 'checked' : ''; ?>>
                                        <?php echo bo_val($modalidadeOpt); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Documento / registro</label>
                            <input type="text" class="form-control" name="documento" value="<?php echo bo_val($p['documento'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="ativo" <?php echo ($p['status'] ?? 'ativo') === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($p['status'] ?? '') === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" name="email" value="<?php echo bo_val($p['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Celular</label>
                            <input type="text" class="form-control" name="celular" value="<?php echo bo_val($p['celular'] ?? ''); ?>" placeholder="DDD + número" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Foto</label>
                            <input type="file" class="form-control" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
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
                    <h5 class="modal-title">Editar perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('update-profile.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('foto_atual', $u['foto'] ?? ''); ?>
                        <div class="col-6">
                            <label class="form-label">ID do usuário</label>
                            <input type="text" class="form-control" value="#<?php echo str_pad((string) $idUsuario, 4, '0', STR_PAD_LEFT); ?>" disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Documento (CPF)</label>
                            <input type="text" class="form-control" name="documento" value="<?php echo bo_val($u['documento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($u['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Telefone/Celular</label>
                            <input type="text" class="form-control" name="telefone" value="<?php echo bo_val($u['telefone'] ?? ''); ?>" placeholder="DDD + número" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Nacionalidade</label>
                            <input type="text" class="form-control" name="nacionalidade" value="<?php echo bo_val($u['nacionalidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Data de nascimento</label>
                            <input type="date" class="form-control" name="nascimento" value="<?php echo bo_val($u['nascimento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Gênero</label>
                            <select class="form-select" name="genero" required>
                                <option value="masculino" <?php echo ($u['genero'] ?? '') === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="feminino" <?php echo ($u['genero'] ?? '') === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="outro" <?php echo ($u['genero'] ?? '') === 'outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Estado (UF)</label>
                            <input type="text" class="form-control" name="estado" maxlength="2" value="<?php echo bo_val($u['estado'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Endereço</label>
                            <input type="text" class="form-control" name="endereco" value="<?php echo bo_val($u['endereco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Cidade</label>
                            <input type="text" class="form-control" name="cidade" value="<?php echo bo_val($u['cidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Altura (m)</label>
                            <input type="number" step="0.01" min="0.5" max="3" class="form-control" name="altura" value="<?php echo bo_val($u['altura'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" step="0.1" min="1" max="500" class="form-control" name="peso" value="<?php echo bo_val($u['peso'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Foto</label>
                            <input type="file" class="form-control" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
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

function bo_modal_senha_alterar(): void
{
    ?>
    <div class="modal fade bo-modal" id="modalSenhaAlterar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Alterar senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('senha.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <div class="col-12">
                            <label class="form-label">Senha atual</label>
                            <input type="password" class="form-control" name="senha_atual" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Nova senha</label>
                            <input type="password" class="form-control" name="senha_nova" minlength="6" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Confirmar nova senha</label>
                            <input type="password" class="form-control" name="senha_confirma" minlength="6" required>
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

/**
 * Botão "Excluir" sem JS customizado: abre um modal Bootstrap centralizado
 * (modal-dialog-centered) com um <form method="POST"> real apontando pro
 * handler do recurso. Usar sempre em conjunto com bo_modal_confirmar_exclusao()
 * para o mesmo $recurso/$id.
 */
function bo_botao_excluir(string $recurso, $id): string
{
    $modalId = 'modalExcluir' . $recurso . $id;
    return '<button type="button" class="btn-bo-icon danger" title="Excluir" data-bs-toggle="modal" data-bs-target="#' . $modalId . '"><i class="bi bi-trash"></i></button>';
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
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Excluir <?php echo bo_val($nome); ?>?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action($recurso . '.php'); ?>">
                    <div class="modal-body">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'delete'); ?>
                        <?php echo bo_hidden('id', $id); ?>
                        <p class="mb-0">Essa exclusão remove o registro definitivamente do banco de dados e não pode ser desfeita.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-bo-gold" style="background:#dc3545;border-color:#dc3545;color:#fff;">
                            <i class="bi bi-trash"></i> Sim, excluir
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
    $tipos = ['transportadora' => 'Transportadora', 'correios' => 'Correios', 'sedex' => 'Sedex', 'motoboy' => 'Motoboy', 'outros' => 'Outros'];
    ?>
    <div class="modal fade bo-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $isEdit ? 'Editar transportadora' : 'Novo transportador'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('transportadoras.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', $isEdit ? 'update' : 'create'); ?>
                        <?php if ($isEdit): ?><?php echo bo_hidden('id', $t['id']); ?><?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" value="<?php echo bo_val($t['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tipo de envio</label>
                            <select class="form-select" name="tipo">
                                <?php foreach ($tipos as $valor => $label): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo ($t['tipo'] ?? '') === $valor ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
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
                    <h5 class="modal-title">Nova faixa de CEP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_form_action('transportadoras.php'); ?>">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', $secao); ?>
                        <?php echo bo_hidden('acao', 'create-faixa'); ?>
                        <?php echo bo_hidden('id_transportadora', $idTransportadora); ?>
                        <div class="col-6">
                            <label class="form-label">CEP inicial</label>
                            <input type="text" class="form-control" name="cep_inicial" placeholder="01000-000" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">CEP final</label>
                            <input type="text" class="form-control" name="cep_final" placeholder="05999-999" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor do frete (R$)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="valor_frete" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Prazo (dias)</label>
                            <input type="number" step="1" min="0" class="form-control" name="prazo_dias" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-bo-gold">Adicionar faixa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
