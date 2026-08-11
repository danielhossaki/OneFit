<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');

$usuarios = [
    ['id' => 1, 'nome' => 'Ana Beatriz Souza', 'email' => 'ana.souza@email.com', 'cpf' => '123.456.789-01', 'matricula' => 'MAT-0001', 'contrato' => 'Ativo', 'status' => 'ativo'],
    ['id' => 2, 'nome' => 'Bruno Carvalho Lima', 'email' => 'bruno.lima@email.com', 'cpf' => '234.567.890-12', 'matricula' => 'MAT-0002', 'contrato' => 'Ativo', 'status' => 'ativo'],
    ['id' => 3, 'nome' => 'Camila Ferreira Dias', 'email' => 'camila.dias@email.com', 'cpf' => '345.678.901-23', 'matricula' => 'MAT-0003', 'contrato' => 'Suspenso', 'status' => 'inativo'],
    ['id' => 4, 'nome' => 'Diego Martins Rocha', 'email' => 'diego.rocha@email.com', 'cpf' => '456.789.012-34', 'matricula' => 'MAT-0004', 'contrato' => 'Ativo', 'status' => 'ativo'],
    ['id' => 5, 'nome' => 'Elaine Cristina Melo', 'email' => 'elaine.melo@email.com', 'cpf' => '567.890.123-45', 'matricula' => 'MAT-0005', 'contrato' => 'Encerrado', 'status' => 'inativo'],
    ['id' => 6, 'nome' => 'Felipe Augusto Nunes', 'email' => 'felipe.nunes@email.com', 'cpf' => '678.901.234-56', 'matricula' => 'MAT-0006', 'contrato' => 'Ativo', 'status' => 'ativo'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice — ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">

    <style>
        :root {
            --bo-bg: #0e0b08;
            --bo-surface: #17130e;
            --bo-surface-2: #1f1912;
            --bo-border: rgba(212, 175, 55, 0.18);
            --bo-text: #f3ede2;
            --bo-text-muted: #a79e8c;
            --bo-gold: #d4af37;
            --bo-gold-bright: #f4c430;
            --bo-gold-dim: #8a6b21;
            --bo-danger: #e4572e;
            --bo-success: #4caf50;
            --bo-sidebar-w: 260px;
            --bo-header-h: 70px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: radial-gradient(circle at 15% 0%, rgba(212, 175, 55, 0.08), transparent 45%), var(--bo-bg);
            color: var(--bo-text);
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        /* ---------- Header ---------- */
        .bo-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--bo-header-h);
            background: rgba(14, 11, 8, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--bo-border);
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
        }

        .bo-sidebar-toggle {
            background: none;
            border: none;
            color: var(--bo-text);
            font-size: 22px;
            cursor: pointer;
            padding: 4px 8px;
        }

        .bo-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bo-logo img {
            height: 42px;
            width: auto;
        }

        .bo-logo span {
            font-weight: 800;
            letter-spacing: 0.06em;
            color: var(--bo-gold);
            font-size: 14px;
            text-transform: uppercase;
        }

        .bo-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bo-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--bo-gold-bright), var(--bo-gold-dim));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #14110e;
            flex-shrink: 0;
        }

        .bo-perfil-switch .btn-bo-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .bo-perfil-menu.dropdown-menu {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 10px;
            padding: 6px;
            min-width: 200px;
        }

        .bo-perfil-menu .dropdown-item {
            color: var(--bo-text);
            border-radius: 6px;
            font-size: 14px;
            padding: 8px 12px;
        }

        .bo-perfil-menu .dropdown-item:hover {
            background: var(--bo-surface-2);
            color: var(--bo-gold-bright);
        }

        .bo-perfil-menu .dropdown-item.active {
            background: rgba(212, 175, 55, 0.15);
            color: var(--bo-gold-bright);
        }

        /* ---------- Sidebar ---------- */
        .bo-sidebar {
            position: fixed;
            top: var(--bo-header-h);
            left: 0;
            bottom: 0;
            width: var(--bo-sidebar-w);
            background: var(--bo-surface);
            border-right: 1px solid var(--bo-border);
            overflow-y: auto;
            padding: 20px 14px;
            z-index: 1025;
            transition: transform 0.3s ease;
        }

        .bo-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            color: var(--bo-text-muted);
            text-decoration: none;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            background: none;
            width: 100%;
            text-align: left;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .bo-nav-item i {
            font-size: 16px;
            width: 20px;
            text-align: center;
            color: var(--bo-gold-dim);
        }

        .bo-nav-item:hover {
            background: var(--bo-surface-2);
            color: var(--bo-text);
        }

        .bo-nav-item.active {
            background: linear-gradient(120deg, rgba(212, 175, 55, 0.16), rgba(212, 175, 55, 0.04));
            border-color: var(--bo-border);
            color: var(--bo-gold-bright);
        }

        .bo-nav-item.active i {
            color: var(--bo-gold-bright);
        }

        /* ---------- Main content ---------- */
        .bo-main {
            margin-left: var(--bo-sidebar-w);
            margin-top: var(--bo-header-h);
            padding: 28px 32px 60px;
            min-height: calc(100vh - var(--bo-header-h));
        }

        .bo-page-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .bo-page-title h1 {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--bo-gold);
            margin: 0;
        }

        .bo-page-title p {
            color: var(--bo-text-muted);
            font-size: 13px;
            margin: 4px 0 0;
        }

        /* ---------- Cards ---------- */
        .bo-card {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            padding: 20px 22px;
            height: 100%;
        }

        .bo-card-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--bo-text-muted);
            margin-bottom: 10px;
        }

        .bo-card-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--bo-text);
            margin-bottom: 4px;
        }

        .bo-card-sub {
            font-size: 13px;
            color: var(--bo-gold);
            font-weight: 600;
        }

        /* ---------- Buttons ---------- */
        .btn-bo-gold {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 8px;
            border: none;
            background: linear-gradient(120deg, var(--bo-gold-dim), var(--bo-gold) 45%, var(--bo-gold-bright) 60%, var(--bo-gold) 75%, var(--bo-gold-dim));
            background-size: 250% 100%;
            color: #14110e;
            cursor: pointer;
            transition: background-position 0.3s ease, transform 0.2s ease;
        }

        .btn-bo-gold:hover {
            background-position: 100% 0;
            color: #14110e;
            transform: translateY(-1px);
        }

        .btn-bo-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            font-weight: 600;
            font-size: 13px;
            border-radius: 8px;
            border: 1px solid var(--bo-border);
            background: transparent;
            color: var(--bo-text);
            cursor: pointer;
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        .btn-bo-outline:hover {
            border-color: var(--bo-gold);
            color: var(--bo-gold-bright);
        }

        .btn-bo-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--bo-border);
            background: var(--bo-surface-2);
            color: var(--bo-text-muted);
            cursor: pointer;
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        .btn-bo-icon:hover {
            border-color: var(--bo-gold);
            color: var(--bo-gold-bright);
        }

        .btn-bo-icon.danger:hover {
            border-color: var(--bo-danger);
            color: var(--bo-danger);
        }

        /* ---------- Filters ---------- */
        .bo-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .bo-filters .form-control,
        .bo-filters .form-select {
            background: var(--bo-surface-2);
            border: 1px solid var(--bo-border);
            color: var(--bo-text);
        }

        .bo-filters .form-control::placeholder {
            color: var(--bo-text-muted);
        }

        .bo-filters .form-control:focus,
        .bo-filters .form-select:focus {
            background: var(--bo-surface-2);
            border-color: var(--bo-gold);
            color: var(--bo-text);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }

        /* ---------- Table ---------- */
        .bo-table-wrap {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            overflow: hidden;
        }

        .bo-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .bo-table thead th {
            color: var(--bo-text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 1px solid var(--bo-border);
            padding: 14px 16px;
            text-align: left;
            white-space: nowrap;
        }

        .bo-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--bo-border);
            font-size: 14px;
            vertical-align: middle;
        }

        .bo-table tbody tr:last-child td {
            border-bottom: none;
        }

        .bo-table tbody tr:hover {
            background: var(--bo-surface-2);
        }

        .bo-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .bo-badge-active {
            background: rgba(76, 175, 80, 0.15);
            color: var(--bo-success);
        }

        .bo-badge-inactive {
            background: rgba(228, 87, 46, 0.15);
            color: var(--bo-danger);
        }

        .bo-table-actions {
            display: flex;
            gap: 8px;
        }

        .bo-empty-row td {
            text-align: center;
            padding: 40px 16px;
            color: var(--bo-text-muted);
        }

        /* ---------- Modal ---------- */
        .bo-modal .modal-content {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            color: var(--bo-text);
            border-radius: 14px;
        }

        .bo-modal .modal-header,
        .bo-modal .modal-footer {
            border-color: var(--bo-border);
        }

        .bo-modal .form-label {
            font-size: 13px;
            color: var(--bo-text-muted);
            font-weight: 600;
        }

        .bo-modal .form-control,
        .bo-modal .form-select {
            background: var(--bo-surface-2);
            border: 1px solid var(--bo-border);
            color: var(--bo-text);
        }

        .bo-modal .form-control:focus,
        .bo-modal .form-select:focus {
            background: var(--bo-surface-2);
            border-color: var(--bo-gold);
            color: var(--bo-text);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
        }

        .bo-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(1.6);
        }

        /* ---------- Stub / em construção ---------- */
        .bo-stub {
            background: var(--bo-surface);
            border: 1px dashed var(--bo-border);
            border-radius: 14px;
            padding: 60px 32px;
            text-align: center;
        }

        .bo-stub i {
            font-size: 40px;
            color: var(--bo-gold);
            margin-bottom: 16px;
        }

        .bo-stub h2 {
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }

        .bo-stub p {
            color: var(--bo-text-muted);
            max-width: 420px;
            margin: 0 auto;
        }

        /* ---------- Sidebar backdrop (mobile) ---------- */
        .bo-sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1024;
        }

        .bo-content-section {
            display: none;
        }

        .bo-content-section.active {
            display: block;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 991px) {
            .bo-sidebar {
                transform: translateX(-100%);
            }

            .bo-sidebar.active {
                transform: translateX(0);
            }

            .bo-sidebar-backdrop.active {
                display: block;
            }

            .bo-main {
                margin-left: 0;
                padding: 22px 18px 48px;
            }
        }

        @media (max-width: 560px) {
            .bo-logo span {
                display: none;
            }
        }
    </style>
</head>

<body>

    <header class="bo-header">
        <div class="d-flex align-items-center gap-2">
            <button class="bo-sidebar-toggle d-lg-none" id="boSidebarToggle" aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="bo-logo">
                <img src="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" alt="Logo One Fit">
                <span>One Fit · Backoffice</span>
            </div>
        </div>

        <div class="bo-user">
            <div class="dropdown bo-perfil-switch">
                <button class="btn-bo-outline dropdown-toggle" type="button" id="boPerfilBtn"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-badge"></i>
                    <span id="boPerfilLabel">Administrador</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end bo-perfil-menu" id="boPerfilMenu" aria-labelledby="boPerfilBtn"></ul>
            </div>
            <div class="bo-avatar" id="boAvatar">A</div>
        </div>
    </header>

    <div class="bo-sidebar-backdrop" id="boSidebarBackdrop"></div>

    <aside class="bo-sidebar" id="boSidebar">
        <nav class="bo-nav" id="boNav"></nav>
    </aside>

    <main class="bo-main">

        <!-- ===== ADMIN · Visão Geral ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="dashboard">
            <div class="bo-page-title">
                <div>
                    <h1>Dashboard</h1>
                    <p>Resumo geral da operação ONE FIT.</p>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Usuários ativos</div>
                        <div class="bo-card-value">482</div>
                        <div class="bo-card-sub">+18 este mês</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Saldo operacional (mês)</div>
                        <div class="bo-card-value">R$ 38.240,00</div>
                        <div class="bo-card-sub">R$ 1.940 no dia</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Cashback distribuído</div>
                        <div class="bo-card-value">R$ 6.512,90</div>
                        <div class="bo-card-sub">R$ 22.140 no ano</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="bo-card">
                        <div class="bo-card-label">Acessos liberados</div>
                        <div class="bo-card-value">463</div>
                        <div class="bo-card-sub">19 bloqueados</div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="bo-card">
                        <div class="bo-card-label">Profissionais ativos</div>
                        <div class="bo-card-value">27</div>
                        <div class="bo-card-sub">3 pendentes de contrato</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Usuários ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="usuarios">
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
        </section>

        <!-- ===== Painel genérico "em construção" para as demais seções ===== -->
        <section class="bo-content-section" id="boStubSection">
            <div class="bo-page-title">
                <div>
                    <h1 id="boStubTitle"></h1>
                    <p id="boStubDesc"></p>
                </div>
            </div>
            <div class="bo-stub">
                <i class="bi" id="boStubIcon"></i>
                <h2>Em construção</h2>
                <p>Esta seção está no roteiro do backoffice e será implementada em uma próxima etapa.</p>
            </div>
        </section>

    </main>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* ---------- Perfis de acesso ---------- */
        const BO_PERFIS = {
            admin: {
                label: 'Administrador',
                menus: [
                    { key: 'dashboard', label: 'Visão Geral', icon: 'bi-speedometer2' },
                    { key: 'usuarios', label: 'Usuários', icon: 'bi-people' },
                    { key: 'permissoes', label: 'Permissões', icon: 'bi-shield-lock' },
                    { key: 'funcoes', label: 'Funções', icon: 'bi-diagram-3' },
                    { key: 'pagamentos', label: 'Pagamentos', icon: 'bi-credit-card' },
                    { key: 'cashbacks', label: 'Cashbacks', icon: 'bi-wallet2' },
                    { key: 'categorias', label: 'Categorias', icon: 'bi-tags' },
                    { key: 'produtos', label: 'Produtos', icon: 'bi-box-seam' },
                    { key: 'planos', label: 'Cadastro de Planos', icon: 'bi-clipboard-check' },
                    { key: 'profissionais', label: 'Profissionais', icon: 'bi-person-badge' },
                ],
            },
            profissional: {
                label: 'Profissional',
                menus: [
                    { key: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
                    { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
                    { key: 'alunos', label: 'Alunos', icon: 'bi-people' },
                    { key: 'agenda', label: 'Agenda', icon: 'bi-calendar3' },
                    { key: 'cashback', label: 'Cashback', icon: 'bi-wallet2' },
                    { key: 'compras', label: 'Compras', icon: 'bi-bag-check' },
                ],
            },
            aluno: {
                label: 'Aluno',
                menus: [
                    { key: 'perfil', label: 'Perfil', icon: 'bi-person-circle' },
                    { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
                    { key: 'cashback', label: 'Cashback', icon: 'bi-wallet2' },
                    { key: 'compras', label: 'Compras', icon: 'bi-bag-check' },
                    { key: 'treino', label: 'Treino', icon: 'bi-lightning-charge' },
                    { key: 'agenda', label: 'Agenda', icon: 'bi-calendar3' },
                ],
            },
        };

        let boPerfilAtual = 'admin';
        let boSectionAtual = 'dashboard';

        function boRenderSidebar() {
            const nav = document.getElementById('boNav');
            nav.innerHTML = '';
            BO_PERFIS[boPerfilAtual].menus.forEach((item) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'bo-nav-item' + (item.key === boSectionAtual ? ' active' : '');
                btn.setAttribute('data-section', item.key);
                btn.innerHTML = `<i class="bi ${item.icon}"></i><span>${item.label}</span>`;
                btn.addEventListener('click', () => boGoToSection(item.key));
                nav.appendChild(btn);
            });
        }

        function boRenderPerfilMenu() {
            const menu = document.getElementById('boPerfilMenu');
            menu.innerHTML = '';
            Object.keys(BO_PERFIS).forEach((key) => {
                const li = document.createElement('li');
                const link = document.createElement('a');
                link.href = '#';
                link.className = 'dropdown-item' + (key === boPerfilAtual ? ' active' : '');
                link.setAttribute('data-perfil', key);
                link.textContent = BO_PERFIS[key].label;
                li.appendChild(link);
                menu.appendChild(li);
            });
        }

        function boGoToSection(sectionKey) {
            boSectionAtual = sectionKey;

            document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => {
                btn.classList.toggle('active', btn.getAttribute('data-section') === sectionKey);
            });

            const prebuilt = document.querySelector(
                `.bo-content-section[data-perfil="${boPerfilAtual}"][data-section="${sectionKey}"]`
            );

            document.querySelectorAll('.bo-content-section').forEach((section) => section.classList.remove('active'));

            if (prebuilt) {
                prebuilt.classList.add('active');
            } else {
                const item = BO_PERFIS[boPerfilAtual].menus.find((m) => m.key === sectionKey);
                document.getElementById('boStubTitle').textContent = item ? item.label : '';
                document.getElementById('boStubDesc').textContent = 'Esta tela ainda será detalhada para o perfil ' + BO_PERFIS[boPerfilAtual].label + '.';
                document.getElementById('boStubIcon').className = 'bi ' + (item ? item.icon : 'bi-hourglass-split');
                document.getElementById('boStubSection').classList.add('active');
            }

            document.getElementById('boSidebar').classList.remove('active');
            document.getElementById('boSidebarBackdrop').classList.remove('active');
        }

        function boTrocarPerfil(perfilKey) {
            if (!BO_PERFIS[perfilKey] || perfilKey === boPerfilAtual) return;

            boPerfilAtual = perfilKey;
            document.getElementById('boPerfilLabel').textContent = BO_PERFIS[perfilKey].label;
            document.getElementById('boAvatar').textContent = BO_PERFIS[perfilKey].label.charAt(0);

            boRenderSidebar();
            boRenderPerfilMenu();
            boGoToSection(BO_PERFIS[perfilKey].menus[0].key);
        }

        document.addEventListener('DOMContentLoaded', () => {
            boRenderSidebar();
            boRenderPerfilMenu();
            boGoToSection('dashboard');

            document.getElementById('boPerfilMenu').addEventListener('click', (event) => {
                const link = event.target.closest('a[data-perfil]');
                if (!link) return;
                event.preventDefault();
                boTrocarPerfil(link.getAttribute('data-perfil'));
            });

            const sidebar = document.getElementById('boSidebar');
            const backdrop = document.getElementById('boSidebarBackdrop');
            const toggle = document.getElementById('boSidebarToggle');

            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                backdrop.classList.toggle('active');
            });
            backdrop.addEventListener('click', () => {
                sidebar.classList.remove('active');
                backdrop.classList.remove('active');
            });

            document.querySelectorAll('[data-bo-table]').forEach((table) => {
                const filterId = table.getAttribute('data-bo-table');
                const searchInput = document.querySelector(`[data-bo-filter="search"][data-bo-target="${filterId}"]`);
                const statusSelect = document.querySelector(`[data-bo-filter="status"][data-bo-target="${filterId}"]`);
                const emptyRow = table.querySelector('.bo-empty-row');

                const applyFilters = () => {
                    const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
                    const status = statusSelect ? statusSelect.value : '';
                    let visibleCount = 0;

                    table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => {
                        const haystack = row.getAttribute('data-search') || '';
                        const rowStatus = row.getAttribute('data-status') || '';
                        const matchesTerm = term === '' || haystack.toLowerCase().includes(term);
                        const matchesStatus = status === '' || rowStatus === status;
                        const visible = matchesTerm && matchesStatus;
                        row.style.display = visible ? '' : 'none';
                        if (visible) visibleCount += 1;
                    });

                    if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';
                };

                if (searchInput) searchInput.addEventListener('input', applyFilters);
                if (statusSelect) statusSelect.addEventListener('change', applyFilters);
            });

            document.body.addEventListener('click', (event) => {
                const toggleBtn = event.target.closest('[data-bo-action="toggle-status"]');
                if (toggleBtn) {
                    const row = toggleBtn.closest('tr');
                    const badge = row.querySelector('.bo-badge');
                    const active = badge.classList.contains('bo-badge-active');

                    badge.classList.toggle('bo-badge-active', !active);
                    badge.classList.toggle('bo-badge-inactive', active);
                    badge.textContent = active ? 'Inativo' : 'Ativo';
                    row.setAttribute('data-status', active ? 'inativo' : 'ativo');
                    toggleBtn.innerHTML = `<i class="bi ${active ? 'bi-play-circle' : 'bi-pause-circle'}"></i>`;
                    toggleBtn.title = active ? 'Ativar' : 'Inativar';
                }

                const deleteBtn = event.target.closest('[data-bo-action="delete"]');
                if (deleteBtn) {
                    const label = deleteBtn.getAttribute('data-bo-name') || 'este registro';
                    if (window.confirm(`Tem certeza que deseja excluir ${label}?`)) {
                        deleteBtn.closest('tr').remove();
                    }
                }

                const editBtn = event.target.closest('[data-bo-action="edit"]');
                if (editBtn) {
                    const modalEl = document.getElementById(editBtn.getAttribute('data-bo-modal'));
                    if (modalEl) {
                        modalEl.querySelectorAll('[data-bo-field]').forEach((field) => {
                            const key = field.getAttribute('data-bo-field');
                            if (editBtn.dataset[key] !== undefined) field.value = editBtn.dataset[key];
                        });
                    }
                }
            });
        });
    </script>
</body>

</html>
