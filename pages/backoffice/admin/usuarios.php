<?php
$pageTitle = 'Usuários';
$activeMenu = 'usuarios';
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/components/backoffice/layout_top.php');

$usuarios = [
    ['id' => 1, 'nome' => 'Ana Beatriz Souza', 'email' => 'ana.souza@email.com', 'cpf' => '123.456.789-01', 'matricula' => 'MAT-0001', 'contrato' => 'Ativo', 'status' => 'ativo'],
    ['id' => 2, 'nome' => 'Bruno Carvalho Lima', 'email' => 'bruno.lima@email.com', 'cpf' => '234.567.890-12', 'matricula' => 'MAT-0002', 'contrato' => 'Ativo', 'status' => 'ativo'],
    ['id' => 3, 'nome' => 'Camila Ferreira Dias', 'email' => 'camila.dias@email.com', 'cpf' => '345.678.901-23', 'matricula' => 'MAT-0003', 'contrato' => 'Suspenso', 'status' => 'inativo'],
    ['id' => 4, 'nome' => 'Diego Martins Rocha', 'email' => 'diego.rocha@email.com', 'cpf' => '456.789.012-34', 'matricula' => 'MAT-0004', 'contrato' => 'Ativo', 'status' => 'ativo'],
    ['id' => 5, 'nome' => 'Elaine Cristina Melo', 'email' => 'elaine.melo@email.com', 'cpf' => '567.890.123-45', 'matricula' => 'MAT-0005', 'contrato' => 'Encerrado', 'status' => 'inativo'],
    ['id' => 6, 'nome' => 'Felipe Augusto Nunes', 'email' => 'felipe.nunes@email.com', 'cpf' => '678.901.234-56', 'matricula' => 'MAT-0006', 'contrato' => 'Ativo', 'status' => 'ativo'],
];
?>

<div class="bo-page-title">
    <div>
        <h1>Usuários</h1>
        <p>Gerencie os usuários cadastrados na plataforma.</p>
    </div>
    <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalUsuario">
        <i class="bi bi-plus-lg"></i> Novo Usuário
    </button>
</div>

<div class="bo-filters">
    <input type="text" class="form-control" style="max-width:320px" placeholder="Buscar por nome, email, CPF ou ID"
        data-bo-filter="search" data-bo-target="usuarios">
    <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="usuarios">
        <option value="">Todos os status</option>
        <option value="ativo">Ativo</option>
        <option value="inativo">Inativo</option>
    </select>
</div>

<div class="bo-table-wrap">
    <div class="table-responsive">
        <table class="bo-table" data-bo-table="usuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Matrícula</th>
                    <th>Contrato</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr data-status="<?php echo $u['status']; ?>"
                        data-search="<?php echo strtolower($u['id'] . ' ' . $u['nome'] . ' ' . $u['email'] . ' ' . $u['cpf']); ?>">
                        <td>#<?php echo str_pad($u['id'], 4, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo $u['nome']; ?></td>
                        <td><?php echo $u['email']; ?></td>
                        <td><?php echo $u['matricula']; ?></td>
                        <td><?php echo $u['contrato']; ?></td>
                        <td>
                            <span class="bo-badge <?php echo $u['status'] === 'ativo' ? 'bo-badge-active' : 'bo-badge-inactive'; ?>">
                                <?php echo $u['status'] === 'ativo' ? 'Ativo' : 'Inativo'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="bo-table-actions">
                                <button type="button" class="btn-bo-icon" title="Editar"
                                    data-bo-action="edit" data-bo-modal="modalUsuario"
                                    data-nome="<?php echo htmlspecialchars($u['nome']); ?>"
                                    data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                    data-cpf="<?php echo htmlspecialchars($u['cpf']); ?>"
                                    data-matricula="<?php echo htmlspecialchars($u['matricula']); ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn-bo-icon" title="<?php echo $u['status'] === 'ativo' ? 'Inativar' : 'Ativar'; ?>"
                                    data-bo-action="toggle-status">
                                    <i class="bi <?php echo $u['status'] === 'ativo' ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                </button>
                                <button type="button" class="btn-bo-icon danger" title="Excluir"
                                    data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($u['nome']); ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" style="display:none">
                    <td colspan="7">Nenhum usuário encontrado para os filtros selecionados.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade bo-modal" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="formUsuario">
                    <div class="mb-3">
                        <label class="form-label">Nome completo</label>
                        <input type="text" class="form-control" data-bo-field="nome">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" data-bo-field="email">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">CPF</label>
                            <input type="text" class="form-control" data-bo-field="cpf">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Matrícula</label>
                            <input type="text" class="form-control" data-bo-field="matricula">
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Status do contrato</label>
                        <select class="form-select" data-bo-field="contrato">
                            <option>Ativo</option>
                            <option>Suspenso</option>
                            <option>Encerrado</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-bo-gold" data-bs-dismiss="modal">Salvar</button>
            </div>
        </div>
    </div>
</div>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/components/backoffice/layout_bottom.php'); ?>
