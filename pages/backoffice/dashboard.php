<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');

function bo_badge($isActive, $onLabel = 'Ativo', $offLabel = 'Inativo')
{
    $cls = $isActive ? 'bo-badge-active' : 'bo-badge-inactive';
    $label = $isActive ? $onLabel : $offLabel;
    return '<span class="bo-badge ' . $cls . '">' . $label . '</span>';
}

function bo_money($v)
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function bo_json($data)
{
    return json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
}

/* ===== Mock data — Administrador ===== */
$usuarios = [
    ['id' => 1, 'nome' => 'Ana Beatriz Souza', 'email' => 'ana.souza@email.com', 'cpf' => '123.456.789-01', 'status' => 'ativo', 'matricula' => 'MAT-0001', 'dataInicial' => '2025-01-10', 'dataFinal' => '2026-01-10', 'acesso' => 'Liberado', 'observacao' => ''],
    ['id' => 2, 'nome' => 'Bruno Carvalho Lima', 'email' => 'bruno.lima@email.com', 'cpf' => '234.567.890-12', 'status' => 'ativo', 'matricula' => 'MAT-0002', 'dataInicial' => '2025-02-15', 'dataFinal' => '2026-02-15', 'acesso' => 'Liberado', 'observacao' => ''],
    ['id' => 3, 'nome' => 'Camila Ferreira Dias', 'email' => 'camila.dias@email.com', 'cpf' => '345.678.901-23', 'status' => 'inativo', 'matricula' => 'MAT-0003', 'dataInicial' => '2024-11-01', 'dataFinal' => '2025-11-01', 'acesso' => 'Bloqueado', 'observacao' => 'Contrato suspenso por inadimplência.'],
    ['id' => 4, 'nome' => 'Diego Martins Rocha', 'email' => 'diego.rocha@email.com', 'cpf' => '456.789.012-34', 'status' => 'ativo', 'matricula' => 'MAT-0004', 'dataInicial' => '2025-03-20', 'dataFinal' => '2026-03-20', 'acesso' => 'Liberado', 'observacao' => ''],
    ['id' => 5, 'nome' => 'Elaine Cristina Melo', 'email' => 'elaine.melo@email.com', 'cpf' => '567.890.123-45', 'status' => 'inativo', 'matricula' => 'MAT-0005', 'dataInicial' => '2024-06-05', 'dataFinal' => '2025-06-05', 'acesso' => 'Bloqueado', 'observacao' => 'Contrato encerrado.'],
    ['id' => 6, 'nome' => 'Felipe Augusto Nunes', 'email' => 'felipe.nunes@email.com', 'cpf' => '678.901.234-56', 'status' => 'ativo', 'matricula' => 'MAT-0006', 'dataInicial' => '2025-05-12', 'dataFinal' => '2026-05-12', 'acesso' => 'Liberado', 'observacao' => ''],
];

$permissoes = [
    ['id' => 1, 'usuarioId' => 2, 'nome' => 'Bruno Carvalho Lima', 'email' => 'bruno.lima@email.com', 'funcao' => 'Instrutor'],
    ['id' => 2, 'usuarioId' => 4, 'nome' => 'Diego Martins Rocha', 'email' => 'diego.rocha@email.com', 'funcao' => 'Recepção'],
    ['id' => 3, 'usuarioId' => 6, 'nome' => 'Felipe Augusto Nunes', 'email' => 'felipe.nunes@email.com', 'funcao' => 'Gerente'],
];

$funcoes = [
    ['id' => 1, 'nome' => 'Administrador', 'permissoes' => 'Usuários, Pagamentos, Cashbacks, Produtos, Planos, Profissionais'],
    ['id' => 2, 'nome' => 'Instrutor', 'permissoes' => 'Alunos, Agenda'],
    ['id' => 3, 'nome' => 'Recepção', 'permissoes' => 'Usuários, Pagamentos'],
];

$pagamentos = [
    ['id' => 1, 'data' => '2026-08-01', 'tipo' => 'PIX', 'valor' => 129.90, 'usuarioId' => 1, 'observacao' => 'Mensalidade agosto'],
    ['id' => 2, 'data' => '2026-08-02', 'tipo' => 'Crédito', 'valor' => 189.90, 'usuarioId' => 2, 'observacao' => 'Compra whey protein'],
    ['id' => 3, 'data' => '2026-08-03', 'tipo' => 'Dinheiro', 'valor' => 50.00, 'usuarioId' => 4, 'observacao' => 'Taxa de matrícula'],
    ['id' => 4, 'data' => '2026-08-05', 'tipo' => 'Débito', 'valor' => 129.90, 'usuarioId' => 6, 'observacao' => 'Mensalidade agosto'],
    ['id' => 5, 'data' => '2026-07-28', 'tipo' => 'PIX', 'valor' => 79.90, 'usuarioId' => 3, 'observacao' => 'Camiseta dry fit'],
];

$cashbackResumo = ['saldoTotal' => 22140.00, 'distribuidos' => 6512.90, 'debitado' => 1820.40, 'creditado' => 8333.30];
$cashbackTransacoes = [
    ['id' => 1, 'data' => '2026-08-05', 'tipo' => 'credito', 'valor' => 25.00, 'usuarioId' => 1, 'motivo' => 'Indicação de amigo'],
    ['id' => 2, 'data' => '2026-08-04', 'tipo' => 'debito', 'valor' => 15.00, 'usuarioId' => 2, 'motivo' => 'Uso em compra de produto'],
    ['id' => 3, 'data' => '2026-08-02', 'tipo' => 'credito', 'valor' => 6.50, 'usuarioId' => 4, 'motivo' => 'Cashback de mensalidade'],
    ['id' => 4, 'data' => '2026-07-30', 'tipo' => 'credito', 'valor' => 40.00, 'usuarioId' => 6, 'motivo' => 'Distribuição em massa'],
];

$categorias = [
    ['id' => 1, 'nome' => 'Suplementos'],
    ['id' => 2, 'nome' => 'Vestuário'],
    ['id' => 3, 'nome' => 'Acessórios'],
    ['id' => 4, 'nome' => 'Equipamentos'],
];

$produtosResumo = ['total' => 48, 'disponiveis' => 41, 'indisponiveis' => 7];
$produtos = [
    ['id' => 1, 'nome' => 'Whey Protein 900g', 'categoria' => 'Suplementos', 'preco' => 189.90, 'desconto' => 10, 'valorFinal' => 170.91, 'cashback' => 5, 'estoque' => 32, 'status' => 'disponivel', 'imagem' => '', 'descricao' => 'Whey concentrado sabor baunilha.'],
    ['id' => 2, 'nome' => 'Camiseta Dry Fit', 'categoria' => 'Vestuário', 'preco' => 79.90, 'desconto' => 0, 'valorFinal' => 79.90, 'cashback' => 3, 'estoque' => 0, 'status' => 'indisponivel', 'imagem' => '', 'descricao' => 'Camiseta respirável para treino.'],
    ['id' => 3, 'nome' => 'Luva de Treino', 'categoria' => 'Acessórios', 'preco' => 49.90, 'desconto' => 15, 'valorFinal' => 42.42, 'cashback' => 4, 'estoque' => 18, 'status' => 'disponivel', 'imagem' => '', 'descricao' => 'Luva com proteção para levantamento.'],
    ['id' => 4, 'nome' => 'Halteres 5kg (par)', 'categoria' => 'Equipamentos', 'preco' => 129.90, 'desconto' => 0, 'valorFinal' => 129.90, 'cashback' => 2, 'estoque' => 6, 'status' => 'disponivel', 'imagem' => '', 'descricao' => 'Par de halteres emborrachados 5kg.'],
];

$planos = [
    ['id' => 1, 'nome' => 'Mensal Fit', 'valor' => 129.90, 'ciclo' => 'Mensal', 'descricao' => 'Acesso completo à academia.', 'status' => 'ativo', 'textoBotao' => 'Assinar agora'],
    ['id' => 2, 'nome' => 'Trimestral Fit', 'valor' => 349.90, 'ciclo' => 'Trimestral', 'descricao' => 'Economia de 10% no plano trimestral.', 'status' => 'ativo', 'textoBotao' => 'Quero esse'],
    ['id' => 3, 'nome' => 'Anual Fit', 'valor' => 1199.90, 'ciclo' => 'Anual', 'descricao' => 'Melhor custo-benefício do ano.', 'status' => 'inativo', 'textoBotao' => 'Assinar agora'],
];

$profissionaisAdm = [
    ['id' => 1, 'nome' => 'Marina Alves Prado', 'funcao' => 'Personal Trainer', 'tituloCard' => 'Personal Trainer Sênior', 'documento' => '123.456.789-00', 'status' => 'ativo', 'email' => 'marina.alves@onefit.com', 'telefone' => '(12) 3456-7890', 'celular' => '(12) 99876-5432', 'descricao' => 'Especialista em treinamento funcional.', 'experiencia' => '8 anos de experiência em academias.', 'foto' => '', 'observacaoInterna' => ''],
    ['id' => 2, 'nome' => 'João Pedro Ramos', 'funcao' => 'Instrutor de Funcional', 'tituloCard' => 'Instrutor de Funcional', 'documento' => '234.567.890-11', 'status' => 'ativo', 'email' => 'joao.ramos@onefit.com', 'telefone' => '(12) 3456-1234', 'celular' => '(12) 99123-4567', 'descricao' => 'Foco em treinos funcionais em grupo.', 'experiencia' => '5 anos de experiência.', 'foto' => '', 'observacaoInterna' => ''],
    ['id' => 3, 'nome' => 'Carla Menezes', 'funcao' => 'Nutricionista', 'tituloCard' => 'Nutricionista Esportiva', 'documento' => '345.678.901-22', 'status' => 'inativo', 'email' => 'carla.menezes@onefit.com', 'telefone' => '(12) 3456-5678', 'celular' => '(12) 99234-5678', 'descricao' => 'Acompanhamento nutricional esportivo.', 'experiencia' => '10 anos de experiência.', 'foto' => '', 'observacaoInterna' => 'Em licença até setembro.'],
];

/* ===== Mock data — Profissional ===== */
$profContrato = ['status' => 'Ativo', 'validade' => '31/12/2026', 'saldoCashback' => 125.40];
$profHistorico = [
    ['competencia' => 'Julho/2026', 'valor' => 2400.00, 'tipo' => 'credito', 'cashback' => 36.00],
    ['competencia' => 'Junho/2026', 'valor' => 2200.00, 'tipo' => 'credito', 'cashback' => 33.00],
    ['competencia' => 'Maio/2026', 'valor' => 2100.00, 'tipo' => 'pix', 'cashback' => 0.00],
];
$profAlunos = [
    ['id' => 1, 'nome' => 'Rafael Costa', 'contato' => '(12) 99123-4567', 'plano' => 'Mensal Fit', 'status' => 'ativo', 'valor' => 129.90, 'observacao' => ''],
    ['id' => 2, 'nome' => 'Juliana Prado', 'contato' => '(12) 99876-1234', 'plano' => 'Trimestral Fit', 'status' => 'ativo', 'valor' => 349.90, 'observacao' => ''],
    ['id' => 3, 'nome' => 'Marcos Lima', 'contato' => '(12) 99321-8765', 'plano' => 'Mensal Fit', 'status' => 'inativo', 'valor' => 129.90, 'observacao' => 'Trancado temporariamente.'],
];
$profAgendados = [
    ['aluno' => 'Rafael Costa', 'contato' => '(12) 99123-4567', 'data' => '12/08/2026 08:00', 'modalidade' => 'Musculação'],
    ['aluno' => 'Juliana Prado', 'contato' => '(12) 99876-1234', 'data' => '12/08/2026 10:00', 'modalidade' => 'Funcional'],
];
$profDisponiveis = [
    ['data' => '12/08/2026 09:00', 'modalidade' => 'Funcional'],
    ['data' => '13/08/2026 07:00', 'modalidade' => 'Avaliação física'],
];
$profCashbackHistorico = [
    ['data' => '01/08/2026', 'descricao' => 'Bônus por avaliação', 'valor' => 15.00],
    ['data' => '15/07/2026', 'descricao' => 'Indicação de aluno', 'valor' => 20.00],
];
$profPedidos = [
    ['transacao' => 'TRX-3301', 'produto' => 'Camiseta Dry Fit', 'quantidade' => 1, 'valor' => 79.90, 'status' => 'Aguardando'],
];
$profPedidosHistorico = [
    ['transacao' => 'TRX-3288', 'data' => '28/07/2026 14:20', 'produto' => 'Whey Protein 900g', 'status' => 'Entregue'],
];

/* ===== Mock data — Aluno ===== */
$alunoPerfil = ['nome' => 'Rafael Costa', 'email' => 'rafael.costa@email.com', 'plano' => 'Mensal Fit', 'status' => 'Ativo', 'documento' => '321.654.987-00', 'telefone' => '(12) 99123-4567', 'dataCadastro' => '10/01/2026', 'nascimento' => '22/05/1995', 'altura' => 1.78, 'peso' => 82.0, 'objetivo' => 'Hipertrofia'];
$alunoHistorico = [
    ['data' => '01/08/2026 09:00', 'descricao' => 'Mensalidade agosto', 'tipo' => 'PIX', 'status' => 'Pago', 'valor' => 129.90, 'cashback' => 6.50],
    ['data' => '01/07/2026 09:00', 'descricao' => 'Mensalidade julho', 'tipo' => 'Crédito', 'status' => 'Pago', 'valor' => 129.90, 'cashback' => 6.50],
];
$alunoCashbackHistorico = [
    ['data' => '05/08/2026', 'tipo' => 'credito', 'descricao' => 'Cashback de mensalidade', 'valor' => 6.50],
    ['data' => '20/07/2026', 'tipo' => 'debito', 'descricao' => 'Uso em compra', 'valor' => 15.00],
];
$alunoPedidos = [
    ['transacao' => 'TRX-3305', 'produto' => 'Luva de Treino', 'quantidade' => 1, 'valor' => 42.42, 'status' => 'Aguardando'],
];
$alunoPedidosHistorico = [
    ['transacao' => 'TRX-3210', 'data' => '15/07/2026 11:00', 'produto' => 'Camiseta Dry Fit', 'status' => 'Entregue'],
];
$alunoTreino = [
    ['id' => 1, 'nome' => 'Supino reto', 'series' => 4, 'repeticoes' => 10, 'carga' => 40],
    ['id' => 2, 'nome' => 'Agachamento livre', 'series' => 4, 'repeticoes' => 12, 'carga' => 60],
    ['id' => 3, 'nome' => 'Puxada frontal', 'series' => 3, 'repeticoes' => 12, 'carga' => 35],
];
$alunoAgendaDisponiveis = [
    ['data' => '13/08/2026 07:00', 'tipo' => 'Avaliação física'],
    ['data' => '14/08/2026 18:00', 'tipo' => 'Avaliação física'],
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

        .bo-page-title .bo-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

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

        .bo-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
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

        .bo-filters .bo-daterange {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--bo-text-muted);
            font-size: 13px;
        }

        .bo-table-wrap {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 24px;
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

        .bo-thumb {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            background: var(--bo-surface-2);
            border: 1px solid var(--bo-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bo-text-muted);
            font-size: 16px;
            overflow: hidden;
        }

        .bo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .bo-modal img[data-bo-preview] {
            max-height: 140px;
            border-radius: 8px;
            border: 1px solid var(--bo-border);
            display: none;
            object-fit: cover;
        }

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

        .bo-list {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            overflow: hidden;
        }

        .bo-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--bo-border);
            gap: 12px;
        }

        .bo-list-item:last-child {
            border-bottom: none;
        }

        .bo-list-item .bo-list-title {
            font-weight: 700;
            font-size: 14px;
        }

        .bo-list-item .bo-list-sub {
            font-size: 12px;
            color: var(--bo-text-muted);
            margin-top: 2px;
        }

        .bo-agenda-card {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .bo-agenda-card.disponivel {
            border-style: dashed;
        }

        .bo-agenda-card .bo-agenda-title {
            font-weight: 700;
            font-size: 14px;
        }

        .bo-agenda-card .bo-agenda-sub {
            font-size: 12px;
            color: var(--bo-text-muted);
            margin-top: 2px;
        }

        .bo-section-heading {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--bo-text-muted);
            margin: 24px 0 12px;
            font-weight: 700;
        }

        .bo-section-heading:first-child {
            margin-top: 0;
        }

        .bo-profile-block {
            background: var(--bo-surface);
            border: 1px solid var(--bo-border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .bo-profile-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--bo-border);
            font-size: 14px;
            gap: 12px;
        }

        .bo-profile-row:last-child {
            border-bottom: none;
        }

        .bo-profile-row span:first-child {
            color: var(--bo-text-muted);
        }

        .bo-imc-box {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .bo-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--bo-surface);
            border: 1px solid var(--bo-gold);
            color: var(--bo-text);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            z-index: 2000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            pointer-events: none;
        }

        .bo-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .bo-pix-box {
            background: var(--bo-surface-2);
            border: 1px dashed var(--bo-border);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }

        .bo-qr-placeholder {
            width: 140px;
            height: 140px;
            margin: 0 auto 12px;
            border-radius: 8px;
            background:
                repeating-linear-gradient(45deg, var(--bo-gold-dim) 0 8px, transparent 8px 16px),
                var(--bo-surface);
            border: 1px solid var(--bo-border);
        }

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
                        <div class="bo-card-sub">Ano R$ 412.900 · Semana R$ 8.960 · Dia R$ 1.940</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Cashback distribuído</div>
                        <div class="bo-card-value">R$ 6.512,90</div>
                        <div class="bo-card-sub">Ano R$ 22.140 · Semana R$ 1.480 · Dia R$ 210</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <div class="bo-card">
                        <div class="bo-card-label">Acessos liberados</div>
                        <div class="bo-card-value">463</div>
                        <div class="bo-card-sub">96% da base</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="bo-card">
                        <div class="bo-card-label">Acessos bloqueados</div>
                        <div class="bo-card-value">19</div>
                        <div class="bo-card-sub">4% da base</div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
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
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("usuarioEdit","Novo usuário", {})'>
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
                                <th>E-mail</th>
                                <th>Status</th>
                                <th>Nº matrícula</th>
                                <th>Data inicial</th>
                                <th>Final de contrato</th>
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
                                    <td><?php echo bo_badge($u['status'] === 'ativo'); ?></td>
                                    <td><?php echo $u['matricula']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($u['dataInicial'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($u['dataFinal'])); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("usuarioEdit","Editar usuário", <?php echo bo_json($u); ?>)'>
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
                                <td colspan="8">Nenhum usuário encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Permissões ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="permissoes">
            <div class="bo-page-title">
                <div>
                    <h1>Permissões</h1>
                    <p>Controle os níveis de acesso concedidos aos usuários.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("permissaoNova","Cadastrar permissão", {}, {doubleConfirm: true})'>
                    <i class="bi bi-plus-lg"></i> Cadastrar Permissão
                </button>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="permissoes">
                        <thead>
                            <tr>
                                <th>ID usuário</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Tipo de função</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permissoes as $p): ?>
                                <tr data-search="<?php echo strtolower($p['usuarioId'] . ' ' . $p['nome'] . ' ' . $p['email']); ?>">
                                    <td>#<?php echo str_pad($p['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['nome']; ?></td>
                                    <td><?php echo $p['email']; ?></td>
                                    <td><?php echo $p['funcao']; ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("permissaoNova","Editar permissão", <?php echo bo_json($p); ?>, {doubleConfirm: true})'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                                data-bo-action="delete" data-bo-name="a permissão de <?php echo htmlspecialchars($p['nome']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="5">Nenhuma permissão encontrada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Funções ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="funcoes">
            <div class="bo-page-title">
                <div>
                    <h1>Funções</h1>
                    <p>Defina funções e as permissões de acesso associadas.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("funcaoForm","Nova função", {})'>
                    <i class="bi bi-plus-lg"></i> Nova Função
                </button>
            </div>

            <div class="bo-list">
                <?php foreach ($funcoes as $f): ?>
                    <div class="bo-list-item">
                        <div>
                            <div class="bo-list-title"><?php echo $f['nome']; ?></div>
                            <div class="bo-list-sub"><?php echo $f['permissoes']; ?></div>
                        </div>
                        <div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar"
                                onclick='boOpenForm("funcaoForm","Editar função", <?php echo bo_json($f); ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                data-bo-action="delete" data-bo-name="a função <?php echo htmlspecialchars($f['nome']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ===== ADMIN · Pagamentos ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="pagamentos">
            <div class="bo-page-title">
                <div>
                    <h1>Pagamentos</h1>
                    <p>Acompanhe e registre os pagamentos recebidos.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("pagamentoForm","Registrar pagamento", {})'>
                    <i class="bi bi-plus-lg"></i> Registrar Pagamento
                </button>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:200px" placeholder="Buscar por ID"
                    data-bo-filter="search" data-bo-target="pagamentos">
                <select class="form-select" style="max-width:180px" data-bo-filter="type" data-bo-target="pagamentos">
                    <option value="">Todos os tipos</option>
                    <option value="PIX">PIX</option>
                    <option value="Dinheiro">Dinheiro</option>
                    <option value="Crédito">Crédito</option>
                    <option value="Débito">Débito</option>
                </select>
                <div class="bo-daterange">
                    De <input type="date" class="form-control" data-bo-filter="date-from" data-bo-target="pagamentos">
                    até <input type="date" class="form-control" data-bo-filter="date-to" data-bo-target="pagamentos">
                </div>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="pagamentos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>ID usuário</th>
                                <th>Observação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagamentos as $p): ?>
                                <tr data-type="<?php echo $p['tipo']; ?>" data-date="<?php echo $p['data']; ?>"
                                    data-search="<?php echo strtolower($p['id']); ?>">
                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($p['data'])); ?></td>
                                    <td><?php echo $p['tipo']; ?></td>
                                    <td><?php echo bo_money($p['valor']); ?></td>
                                    <td>#<?php echo str_pad($p['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['observacao']; ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("pagamentoForm","Editar pagamento", <?php echo bo_json($p); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                                data-bo-action="delete" data-bo-name="o pagamento #<?php echo $p['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="7">Nenhum pagamento encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Cashbacks ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="cashbacks">
            <div class="bo-page-title">
                <div>
                    <h1>Cashbacks</h1>
                    <p>Acompanhe saldo, distribuição e lançamentos de cashback.</p>
                </div>
                <div class="bo-actions">
                    <button type="button" class="btn-bo-outline" onclick='boOpenForm("cashbackMassa","Distribuição em massa", {})'>
                        <i class="bi bi-people"></i> Distribuição em Massa
                    </button>
                    <button type="button" class="btn-bo-gold" onclick='boOpenForm("cashbackLancar","Lançar cashback", {})'>
                        <i class="bi bi-plus-lg"></i> Lançar Cashback
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="bo-card">
                        <div class="bo-card-label">Saldo total</div>
                        <div class="bo-card-value"><?php echo bo_money($cashbackResumo['saldoTotal']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bo-card">
                        <div class="bo-card-label">Distribuídos</div>
                        <div class="bo-card-value"><?php echo bo_money($cashbackResumo['distribuidos']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bo-card">
                        <div class="bo-card-label">Debitado</div>
                        <div class="bo-card-value"><?php echo bo_money($cashbackResumo['debitado']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bo-card">
                        <div class="bo-card-label">Creditado</div>
                        <div class="bo-card-value"><?php echo bo_money($cashbackResumo['creditado']); ?></div>
                    </div>
                </div>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:200px" placeholder="Buscar por ID"
                    data-bo-filter="search" data-bo-target="cashbacks">
                <select class="form-select" style="max-width:180px" data-bo-filter="type" data-bo-target="cashbacks">
                    <option value="">Todos os tipos</option>
                    <option value="credito">Crédito</option>
                    <option value="debito">Débito</option>
                </select>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="cashbacks">
                        <thead>
                            <tr>
                                <th>ID transação</th>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>ID usuário</th>
                                <th>Motivo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cashbackTransacoes as $c): ?>
                                <tr data-type="<?php echo $c['tipo']; ?>" data-search="<?php echo strtolower($c['id']); ?>">
                                    <td>#<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($c['data'])); ?></td>
                                    <td><?php echo $c['tipo'] === 'credito' ? 'Crédito' : 'Débito'; ?></td>
                                    <td><?php echo bo_money($c['valor']); ?></td>
                                    <td>#<?php echo str_pad($c['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $c['motivo']; ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                                data-bo-action="delete" data-bo-name="a transação #<?php echo $c['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="7">Nenhuma transação encontrada para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Categorias ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="categorias">
            <div class="bo-page-title">
                <div>
                    <h1>Categorias</h1>
                    <p>Organize as categorias de produtos da loja.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("categoriaForm","Nova categoria", {})'>
                    <i class="bi bi-plus-lg"></i> Nova Categoria
                </button>
            </div>

            <div class="bo-list">
                <?php foreach ($categorias as $c): ?>
                    <div class="bo-list-item">
                        <div class="bo-list-title"><?php echo $c['nome']; ?></div>
                        <div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar"
                                onclick='boOpenForm("categoriaForm","Editar categoria", <?php echo bo_json($c); ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                data-bo-action="delete" data-bo-name="a categoria <?php echo htmlspecialchars($c['nome']); ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ===== ADMIN · Produtos ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="produtos">
            <div class="bo-page-title">
                <div>
                    <h1>Produtos</h1>
                    <p>Gerencie o catálogo de produtos da loja.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("produtoForm","Cadastro de Produto", {})'>
                    <i class="bi bi-plus-lg"></i> Cadastro de Produto
                </button>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Total cadastrados</div>
                        <div class="bo-card-value"><?php echo $produtosResumo['total']; ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Disponíveis</div>
                        <div class="bo-card-value"><?php echo $produtosResumo['disponiveis']; ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Indisponíveis</div>
                        <div class="bo-card-value"><?php echo $produtosResumo['indisponiveis']; ?></div>
                    </div>
                </div>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por nome ou ID"
                    data-bo-filter="search" data-bo-target="produtos">
                <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="produtos">
                    <option value="">Todos</option>
                    <option value="disponivel">Disponível</option>
                    <option value="indisponivel">Indisponível</option>
                </select>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="produtos">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Preço</th>
                                <th>Desconto</th>
                                <th>Valor final</th>
                                <th>Cashback</th>
                                <th>Estoque</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $p): ?>
                                <tr data-status="<?php echo $p['status']; ?>"
                                    data-search="<?php echo strtolower($p['id'] . ' ' . $p['nome']); ?>">
                                    <td>
                                        <div class="bo-thumb">
                                            <?php if ($p['imagem']): ?>
                                                <img src="<?php echo htmlspecialchars($p['imagem']); ?>" alt="">
                                            <?php else: ?>
                                                <i class="bi bi-image"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['nome']; ?></td>
                                    <td><?php echo bo_money($p['preco']); ?></td>
                                    <td><?php echo $p['desconto']; ?>%</td>
                                    <td><?php echo bo_money($p['valorFinal']); ?></td>
                                    <td><?php echo $p['cashback']; ?>%</td>
                                    <td><?php echo $p['estoque']; ?></td>
                                    <td><?php echo bo_badge($p['status'] === 'disponivel', 'Disponível', 'Indisponível'); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Pausar/Ativar anúncio"
                                                data-bo-action="toggle-status" data-on="Disponível" data-off="Indisponível">
                                                <i class="bi <?php echo $p['status'] === 'disponivel' ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("produtoForm","Editar produto", <?php echo bo_json($p); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                                data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($p['nome']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="10">Nenhum produto encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Cadastro de Planos ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="planos">
            <div class="bo-page-title">
                <div>
                    <h1>Cadastro de Planos</h1>
                    <p>Configure os planos de assinatura disponíveis.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("planoForm","Novo Plano", {})'>
                    <i class="bi bi-plus-lg"></i> Novo Plano
                </button>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por plano ou ID"
                    data-bo-filter="search" data-bo-target="planos">
                <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="planos">
                    <option value="">Todos</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="planos">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Plano</th>
                                <th>Valor</th>
                                <th>Ciclo</th>
                                <th>Descrição</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($planos as $p): ?>
                                <tr data-status="<?php echo $p['status']; ?>"
                                    data-search="<?php echo strtolower($p['id'] . ' ' . $p['nome']); ?>">
                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['nome']; ?></td>
                                    <td><?php echo bo_money($p['valor']); ?></td>
                                    <td><?php echo $p['ciclo']; ?></td>
                                    <td><?php echo $p['descricao']; ?></td>
                                    <td><?php echo bo_badge($p['status'] === 'ativo'); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Pausar/Ativar plano"
                                                data-bo-action="toggle-status">
                                                <i class="bi <?php echo $p['status'] === 'ativo' ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("planoForm","Editar plano", <?php echo bo_json($p); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="7">Nenhum plano encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ADMIN · Profissionais ===== -->
        <section class="bo-content-section" data-perfil="admin" data-section="profissionais">
            <div class="bo-page-title">
                <div>
                    <h1>Profissionais</h1>
                    <p>Gerencie os profissionais cadastrados na plataforma.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("profissionalForm","Novo Profissional", {})'>
                    <i class="bi bi-plus-lg"></i> Novo Profissional
                </button>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por nome, função ou documento"
                    data-bo-filter="search" data-bo-target="profissionais">
                <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="profissionais">
                    <option value="">Todos</option>
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="profissionais">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Função</th>
                                <th>Documento</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profissionaisAdm as $p): ?>
                                <tr data-status="<?php echo $p['status']; ?>"
                                    data-search="<?php echo strtolower($p['nome'] . ' ' . $p['funcao'] . ' ' . $p['documento']); ?>">
                                    <td>
                                        <div class="bo-thumb">
                                            <?php if ($p['foto']): ?>
                                                <img src="<?php echo htmlspecialchars($p['foto']); ?>" alt="">
                                            <?php else: ?>
                                                <i class="bi bi-person"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['nome']; ?></td>
                                    <td><?php echo $p['funcao']; ?></td>
                                    <td><?php echo $p['documento']; ?></td>
                                    <td><?php echo bo_badge($p['status'] === 'ativo'); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("profissionalForm","Editar profissional", <?php echo bo_json($p); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                                data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($p['nome']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="7">Nenhum profissional encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== PROFISSIONAL · Dashboard ===== -->
        <section class="bo-content-section" data-perfil="profissional" data-section="dashboard">
            <div class="bo-page-title">
                <div>
                    <h1>Dashboard</h1>
                    <p>Resumo do seu contrato e saldo com a ONE FIT.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Status de contrato</div>
                        <div class="bo-card-value"><?php echo $profContrato['status']; ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Validade de contrato</div>
                        <div class="bo-card-value"><?php echo $profContrato['validade']; ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Saldo de cashback</div>
                        <div class="bo-card-value"><?php echo bo_money($profContrato['saldoCashback']); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== PROFISSIONAL · Histórico ===== -->
        <section class="bo-content-section" data-perfil="profissional" data-section="historico">
            <div class="bo-page-title">
                <div>
                    <h1>Histórico</h1>
                    <p>Histórico de competências e valores recebidos.</p>
                </div>
                <button type="button" class="btn-bo-outline" data-bo-export="profHistorico">
                    <i class="bi bi-download"></i> Exportar
                </button>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="profHistorico">
                        <thead>
                            <tr>
                                <th>Competência</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Cashback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profHistorico as $h): ?>
                                <tr>
                                    <td><?php echo $h['competencia']; ?></td>
                                    <td><?php echo bo_money($h['valor']); ?></td>
                                    <td><?php echo ucfirst($h['tipo']); ?></td>
                                    <td><?php echo bo_money($h['cashback']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="4">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== PROFISSIONAL · Alunos ===== -->
        <section class="bo-content-section" data-perfil="profissional" data-section="alunos">
            <div class="bo-page-title">
                <div>
                    <h1>Alunos</h1>
                    <p>Alunos vinculados aos seus atendimentos.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("alunoDoProfissionalForm","Adicionar Aluno", {})'>
                    <i class="bi bi-plus-lg"></i> Adicionar Aluno
                </button>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="profAlunos">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Plano</th>
                                <th>Status</th>
                                <th>Valor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profAlunos as $a): ?>
                                <tr>
                                    <td><?php echo $a['nome']; ?></td>
                                    <td><?php echo $a['plano']; ?></td>
                                    <td><?php echo bo_badge($a['status'] === 'ativo'); ?></td>
                                    <td><?php echo bo_money($a['valor']); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("alunoDoProfissionalForm","Editar aluno", <?php echo bo_json($a); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon danger" title="Excluir"
                                                data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($a['nome']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="5">Nenhum aluno vinculado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== PROFISSIONAL · Agenda ===== -->
        <section class="bo-content-section" data-perfil="profissional" data-section="agenda">
            <div class="bo-page-title">
                <div>
                    <h1>Agenda</h1>
                    <p>Horários agendados e disponíveis.</p>
                </div>
                <div class="bo-actions">
                    <button type="button" class="btn-bo-outline" onclick='boOpenForm("agendaDisponivel","Novo horário disponível", {})'>
                        <i class="bi bi-calendar-plus"></i> Horário Disponível
                    </button>
                    <button type="button" class="btn-bo-gold" onclick='boOpenForm("agendaAgendar","Agendar horário", {})'>
                        <i class="bi bi-plus-lg"></i> Agenda
                    </button>
                </div>
            </div>

            <div class="bo-filters">
                <div class="bo-daterange">
                    De <input type="date" class="form-control">
                    até <input type="date" class="form-control">
                </div>
            </div>

            <div class="bo-section-heading">Agendados</div>
            <?php foreach ($profAgendados as $ag): ?>
                <div class="bo-agenda-card">
                    <div>
                        <div class="bo-agenda-title"><?php echo $ag['aluno']; ?> · <?php echo $ag['modalidade']; ?></div>
                        <div class="bo-agenda-sub"><?php echo $ag['contato']; ?> · <?php echo $ag['data']; ?></div>
                    </div>
                    <button type="button" class="btn-bo-icon danger" title="Cancelar" data-bo-remove-card>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            <?php endforeach; ?>

            <div class="bo-section-heading">Disponíveis</div>
            <?php foreach ($profDisponiveis as $d): ?>
                <div class="bo-agenda-card disponivel">
                    <div>
                        <div class="bo-agenda-title"><?php echo $d['modalidade']; ?></div>
                        <div class="bo-agenda-sub"><?php echo $d['data']; ?></div>
                    </div>
                    <button type="button" class="btn-bo-icon danger" title="Remover" data-bo-remove-card>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- ===== PROFISSIONAL · Meu cashback ===== -->
        <section class="bo-content-section" data-perfil="profissional" data-section="cashback">
            <div class="bo-page-title">
                <div>
                    <h1>Meu cashback</h1>
                    <p>Saldo disponível e histórico de créditos.</p>
                </div>
                <div class="bo-actions">
                    <button type="button" class="btn-bo-outline" data-bo-export="profCashback">
                        <i class="bi bi-download"></i> Exportar
                    </button>
                    <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Utilizar cashback", {})'>
                        <i class="bi bi-wallet2"></i> Utilizar Cashback
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Saldo total</div>
                        <div class="bo-card-value"><?php echo bo_money($profContrato['saldoCashback']); ?></div>
                    </div>
                </div>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="profCashback">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profCashbackHistorico as $h): ?>
                                <tr>
                                    <td><?php echo $h['data']; ?></td>
                                    <td><?php echo $h['descricao']; ?></td>
                                    <td><?php echo bo_money($h['valor']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="3">Nenhum registro encontrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== PROFISSIONAL · Minhas compras ===== -->
        <section class="bo-content-section" data-perfil="profissional" data-section="compras">
            <div class="bo-page-title">
                <div>
                    <h1>Minhas compras</h1>
                    <p>Acompanhamento e histórico dos seus pedidos.</p>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Total de pedidos</div>
                        <div class="bo-card-value"><?php echo count($profPedidos) + count($profPedidosHistorico); ?></div>
                    </div>
                </div>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por produto, ID da transação ou pedido">
                <select class="form-select" style="max-width:200px">
                    <option value="">Todos</option>
                    <option value="aguardando">Aguardando</option>
                    <option value="entregue">Entregue</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="devolvido">Devolvido</option>
                </select>
            </div>

            <div class="bo-section-heading">Acompanhamento de pedido</div>
            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table">
                        <thead>
                            <tr>
                                <th>ID transação</th>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profPedidos as $ped): ?>
                                <tr>
                                    <td><?php echo $ped['transacao']; ?></td>
                                    <td><?php echo $ped['produto']; ?></td>
                                    <td><?php echo $ped['quantidade']; ?></td>
                                    <td><?php echo bo_money($ped['valor']); ?></td>
                                    <td><?php echo $ped['status']; ?></td>
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
                            <?php foreach ($profPedidosHistorico as $ped): ?>
                                <tr>
                                    <td><?php echo $ped['transacao']; ?></td>
                                    <td><?php echo $ped['data']; ?></td>
                                    <td><?php echo $ped['produto']; ?></td>
                                    <td><?php echo $ped['status']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ALUNO · Perfil ===== -->
        <section class="bo-content-section" data-perfil="aluno" data-section="perfil">
            <div class="bo-page-title">
                <div>
                    <h1>Perfil</h1>
                    <p>Seus dados cadastrais na ONE FIT.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("perfilEdit","Editar perfil", <?php echo bo_json($alunoPerfil); ?>)'>
                    <i class="bi bi-pencil"></i> Editar
                </button>
            </div>

            <div class="bo-profile-block">
                <div class="bo-thumb mb-3" style="width:72px;height:72px;font-size:28px;">
                    <i class="bi bi-person"></i>
                </div>
                <div class="bo-profile-row"><span>E-mail</span><span><?php echo $alunoPerfil['email']; ?></span></div>
                <div class="bo-profile-row">
                    <span>Plano</span>
                    <span>
                        <?php echo $alunoPerfil['plano']; ?>
                        <button type="button" class="btn-bo-outline ms-2" style="padding:4px 10px;font-size:12px;"
                            onclick='boOpenForm("planoAlterar","Alterar plano", {plano: "<?php echo $alunoPerfil['plano']; ?>"})'>Alterar</button>
                    </span>
                </div>
                <div class="bo-profile-row"><span>Status</span><span><?php echo bo_badge($alunoPerfil['status'] === 'Ativo'); ?></span></div>
                <div class="bo-profile-row"><span>Documento</span><span><?php echo $alunoPerfil['documento']; ?></span></div>
                <div class="bo-profile-row"><span>Telefone</span><span><?php echo $alunoPerfil['telefone']; ?></span></div>
                <div class="bo-profile-row"><span>Data de cadastro</span><span><?php echo $alunoPerfil['dataCadastro']; ?></span></div>
                <div class="bo-profile-row"><span>Data de nascimento</span><span><?php echo $alunoPerfil['nascimento']; ?></span></div>
            </div>

            <div class="bo-profile-block">
                <div class="bo-section-heading">Avaliação física</div>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label">Altura (m)</label>
                        <input type="number" step="0.01" class="form-control" id="imcAltura" value="<?php echo $alunoPerfil['altura']; ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Peso (kg)</label>
                        <input type="number" step="0.1" class="form-control" id="imcPeso" value="<?php echo $alunoPerfil['peso']; ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Objetivo</label>
                        <input type="text" class="form-control" id="imcObjetivo" value="<?php echo $alunoPerfil['objetivo']; ?>">
                    </div>
                </div>
                <div class="bo-imc-box mb-3">
                    <button type="button" class="btn-bo-outline" onclick="boCalcularIMC()">
                        <i class="bi bi-calculator"></i> Calcular IMC
                    </button>
                    <div>
                        <div class="bo-card-label" style="margin-bottom:2px;">Status de IMC</div>
                        <div class="bo-card-value" id="imcResultado" style="font-size:18px;">—</div>
                    </div>
                </div>
                <div class="bo-table-actions">
                    <button type="button" class="btn-bo-outline">Cancelar</button>
                    <button type="button" class="btn-bo-gold" onclick="boToast('Alterações salvas.')">Salvar</button>
                </div>
            </div>
        </section>

        <!-- ===== ALUNO · Histórico ===== -->
        <section class="bo-content-section" data-perfil="aluno" data-section="historico">
            <div class="bo-page-title">
                <div>
                    <h1>Histórico</h1>
                    <p>Histórico de pagamentos e movimentações.</p>
                </div>
                <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPagarPlano">
                    <i class="bi bi-credit-card"></i> Pagar Plano
                </button>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table">
                        <thead>
                            <tr>
                                <th>Data/hora</th>
                                <th>Descrição</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Valor</th>
                                <th>Cashback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunoHistorico as $h): ?>
                                <tr>
                                    <td><?php echo $h['data']; ?></td>
                                    <td><?php echo $h['descricao']; ?></td>
                                    <td><?php echo $h['tipo']; ?></td>
                                    <td><?php echo $h['status']; ?></td>
                                    <td><?php echo bo_money($h['valor']); ?></td>
                                    <td><?php echo bo_money($h['cashback']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ALUNO · Cashback ===== -->
        <section class="bo-content-section" data-perfil="aluno" data-section="cashback">
            <div class="bo-page-title">
                <div>
                    <h1>Cashback</h1>
                    <p>Saldo disponível e histórico de movimentações.</p>
                </div>
                <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Usar cashback", {})'>
                    <i class="bi bi-wallet2"></i> Usar Cashback
                </button>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Saldo de cashback</div>
                        <div class="bo-card-value">R$ 125,40</div>
                    </div>
                </div>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunoCashbackHistorico as $h): ?>
                                <tr>
                                    <td><?php echo $h['data']; ?></td>
                                    <td><?php echo $h['tipo'] === 'credito' ? 'Crédito' : 'Débito'; ?></td>
                                    <td><?php echo $h['descricao']; ?></td>
                                    <td><?php echo bo_money($h['valor']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ALUNO · Minhas compras ===== -->
        <section class="bo-content-section" data-perfil="aluno" data-section="compras">
            <div class="bo-page-title">
                <div>
                    <h1>Minhas compras</h1>
                    <p>Acompanhamento e histórico dos seus pedidos.</p>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Total de pedidos</div>
                        <div class="bo-card-value"><?php echo count($alunoPedidos) + count($alunoPedidosHistorico); ?></div>
                    </div>
                </div>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por produto, ID da transação ou pedido">
                <select class="form-select" style="max-width:200px">
                    <option value="">Todos</option>
                    <option value="aguardando">Aguardando</option>
                    <option value="entregue">Entregue</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="devolvido">Devolvido</option>
                </select>
            </div>

            <div class="bo-section-heading">Acompanhamento de pedido</div>
            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table">
                        <thead>
                            <tr>
                                <th>ID transação</th>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunoPedidos as $ped): ?>
                                <tr>
                                    <td><?php echo $ped['transacao']; ?></td>
                                    <td><?php echo $ped['produto']; ?></td>
                                    <td><?php echo $ped['quantidade']; ?></td>
                                    <td><?php echo bo_money($ped['valor']); ?></td>
                                    <td><?php echo $ped['status']; ?></td>
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
                            <?php foreach ($alunoPedidosHistorico as $ped): ?>
                                <tr>
                                    <td><?php echo $ped['transacao']; ?></td>
                                    <td><?php echo $ped['data']; ?></td>
                                    <td><?php echo $ped['produto']; ?></td>
                                    <td><?php echo $ped['status']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ALUNO · Treino ===== -->
        <section class="bo-content-section" data-perfil="aluno" data-section="treino">
            <div class="bo-page-title">
                <div>
                    <h1>Treino</h1>
                    <p>Monte e acompanhe sua ficha de treino.</p>
                </div>
                <div class="bo-actions">
                    <button type="button" class="btn-bo-outline" data-bo-action="clear-table" data-bo-target-table="alunoTreino">
                        <i class="bi bi-eraser"></i> Limpar Treino
                    </button>
                    <button type="button" class="btn-bo-gold" onclick='boOpenForm("treinoExercicio","Adicionar exercício", {})'>
                        <i class="bi bi-plus-lg"></i> Adicionar Treino
                    </button>
                </div>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="alunoTreino">
                        <thead>
                            <tr>
                                <th>Exercício</th>
                                <th>Séries</th>
                                <th>Repetições</th>
                                <th>Carga</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alunoTreino as $t): ?>
                                <tr>
                                    <td><?php echo $t['nome']; ?></td>
                                    <td><?php echo $t['series']; ?></td>
                                    <td><?php echo $t['repeticoes']; ?></td>
                                    <td><?php echo $t['carga']; ?> kg</td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <button type="button" class="btn-bo-icon" title="Editar"
                                                onclick='boOpenForm("treinoExercicio","Editar exercício", <?php echo bo_json($t); ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-bo-icon danger" title="Remover"
                                                data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($t['nome']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="5">Nenhum exercício cadastrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ===== ALUNO · Minha agenda ===== -->
        <section class="bo-content-section" data-perfil="aluno" data-section="agenda">
            <div class="bo-page-title">
                <div>
                    <h1>Minha agenda</h1>
                    <p>Datas disponíveis e agendadas com seus profissionais.</p>
                </div>
            </div>

            <div class="bo-section-heading">Agenda de avaliação física</div>
            <?php foreach ($alunoAgendaDisponiveis as $d): ?>
                <div class="bo-agenda-card disponivel">
                    <div>
                        <div class="bo-agenda-title"><?php echo $d['tipo']; ?></div>
                        <div class="bo-agenda-sub"><?php echo $d['data']; ?></div>
                    </div>
                    <button type="button" class="btn-bo-gold" style="padding:8px 16px;"
                        onclick='boOpenForm("agendaAgendar","Confirmar agendamento", {data: "<?php echo $d['data']; ?>", modalidade: "<?php echo $d['tipo']; ?>"})'>
                        Agendar
                    </button>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- ===== Painel genérico "em construção" (fallback) ===== -->
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

    <!-- ===== Modal genérico de formulário ===== -->
    <div class="modal fade bo-modal" id="boFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="boFormModalTitle">Formulário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="boFormModalForm" class="row g-3" onsubmit="return false;"></form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-bo-gold" id="boFormModalSave">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Modal · Pagar plano (Aluno) ===== -->
    <div class="modal fade bo-modal" id="modalPagarPlano" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pagar plano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Valor</label>
                            <input type="text" class="form-control" value="R$ 129,90" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tipo do plano</label>
                            <input type="text" class="form-control" value="Mensal Fit" readonly>
                        </div>
                    </div>

                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="metodoPagamento" id="metodoPix" checked>
                        <label class="btn-bo-outline" for="metodoPix" style="flex:1;text-align:center;">PIX</label>
                        <input type="radio" class="btn-check" name="metodoPagamento" id="metodoCredito">
                        <label class="btn-bo-outline" for="metodoCredito" style="flex:1;text-align:center;">Crédito</label>
                        <input type="radio" class="btn-check" name="metodoPagamento" id="metodoDebito">
                        <label class="btn-bo-outline" for="metodoDebito" style="flex:1;text-align:center;">Débito</label>
                    </div>

                    <div id="painelPix">
                        <button type="button" class="btn-bo-outline mb-3" id="btnGerarQr">
                            <i class="bi bi-qr-code"></i> Gerar QR Code
                        </button>
                        <div class="bo-pix-box" id="pixResultado" style="display:none">
                            <div class="bo-qr-placeholder"></div>
                            <input type="text" class="form-control mb-2" readonly id="pixCopiaCola" value="00020126580014BR.GOV.BCB.PIX0136onefit-pagamento-simulado5204000053039865802BR5909ONE FIT6009SAO PAULO62070503***6304ABCD">
                            <button type="button" class="btn-bo-outline" id="btnCopiarPix"><i class="bi bi-clipboard"></i> Copiar código Pix</button>
                        </div>
                    </div>

                    <div id="painelCartao" style="display:none">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Número do cartão</label>
                                <input type="text" class="form-control" placeholder="0000 0000 0000 0000">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Validade</label>
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
                    <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-bo-gold" id="btnPagar">Pagar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="bo-toast" id="boToast"></div>

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
                    { key: 'cashback', label: 'Meu cashback', icon: 'bi-wallet2' },
                    { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
                ],
            },
            aluno: {
                label: 'Aluno',
                menus: [
                    { key: 'perfil', label: 'Perfil', icon: 'bi-person-circle' },
                    { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
                    { key: 'cashback', label: 'Cashback', icon: 'bi-wallet2' },
                    { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
                    { key: 'treino', label: 'Treino', icon: 'bi-lightning-charge' },
                    { key: 'agenda', label: 'Minha agenda', icon: 'bi-calendar3' },
                ],
            },
        };

        let boPerfilAtual = 'admin';
        let boSectionAtual = 'dashboard';
        let boFormModalInstance = null;

        /* ---------- Esquemas de formulário (genérico) ---------- */
        const BO_CATEGORIAS_OPTIONS = [<?php echo implode(', ', array_map(fn($c) => '"' . $c['nome'] . '"', $categorias)); ?>];
        const BO_PLANOS_OPTIONS = [<?php echo implode(', ', array_map(fn($p) => '"' . $p['nome'] . '"', $planos)); ?>];

        const BO_FORM_SCHEMAS = {
            usuarioEdit: [
                { key: 'nome', label: 'Nome completo', type: 'text', col: 12 },
                { key: 'email', label: 'E-mail', type: 'email', col: 12 },
                { key: 'cpf', label: 'CPF', type: 'text', col: 6 },
                { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
                { key: 'matricula', label: 'Nº da matrícula', type: 'text', col: 6 },
                { key: 'dataInicial', label: 'Data inicial', type: 'date', col: 6 },
                { key: 'dataFinal', label: 'Final de contrato', type: 'date', col: 6 },
                { key: 'acesso', label: 'Acesso', type: 'select', options: ['Liberado', 'Bloqueado'], col: 6 },
                { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
            ],
            permissaoNova: [
                { key: 'usuarioId', label: 'ID do usuário', type: 'text', col: 6 },
                { key: 'nome', label: 'Nome', type: 'text', col: 6 },
                { key: 'email', label: 'E-mail', type: 'email', col: 12 },
                { key: 'funcao', label: 'Tipo de função', type: 'select', options: ['Administrador', 'Gerente', 'Instrutor', 'Recepção'], col: 12 },
            ],
            funcaoForm: [
                { key: 'nome', label: 'Nome da função', type: 'text', col: 12 },
                { key: 'permissoes', label: 'Permissões de acesso', type: 'checklist', options: ['Usuários', 'Pagamentos', 'Cashbacks', 'Produtos', 'Planos', 'Profissionais', 'Alunos', 'Agenda'], col: 12 },
            ],
            pagamentoForm: [
                { key: 'data', label: 'Data', type: 'date', col: 6 },
                { key: 'tipo', label: 'Tipo', type: 'select', options: ['PIX', 'Dinheiro', 'Crédito', 'Débito'], col: 6 },
                { key: 'valor', label: 'Valor', type: 'number', col: 6 },
                { key: 'usuarioId', label: 'ID do usuário', type: 'text', col: 6 },
                { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
            ],
            cashbackLancar: [
                { key: 'data', label: 'Data', type: 'date', col: 6 },
                { key: 'tipo', label: 'Tipo', type: 'select', options: ['credito', 'debito'], optionLabels: ['Crédito', 'Débito'], col: 6 },
                { key: 'valor', label: 'Valor', type: 'number', col: 6 },
                { key: 'usuarioId', label: 'ID do usuário', type: 'text', col: 6 },
            ],
            cashbackMassa: [
                { key: 'data', label: 'Data', type: 'date', col: 6 },
                { key: 'valor', label: 'Valor', type: 'number', col: 6 },
                { key: 'alvo', label: 'Alvo', type: 'select', options: ['Todos', 'Ativos'], col: 12 },
            ],
            categoriaForm: [
                { key: 'nome', label: 'Nome da categoria', type: 'text', col: 12 },
            ],
            produtoForm: [
                { key: 'nome', label: 'Nome do produto', type: 'text', col: 12 },
                { key: 'categoria', label: 'Categoria', type: 'select', options: BO_CATEGORIAS_OPTIONS, col: 6 },
                { key: 'preco', label: 'Preço', type: 'number', col: 6 },
                { key: 'desconto', label: 'Desconto (%)', type: 'number', col: 6 },
                { key: 'cashback', label: 'Cashback (%)', type: 'number', col: 6 },
                { key: 'estoque', label: 'Estoque', type: 'number', col: 6 },
                { key: 'imagem', label: 'Imagem do produto (upload ou URL)', type: 'image', col: 12 },
                { key: 'valorFinal', label: 'Valor final', type: 'text', col: 6, readonly: true },
                { key: 'descricao', label: 'Descrição', type: 'textarea', col: 12 },
            ],
            planoForm: [
                { key: 'nome', label: 'Nome do plano', type: 'text', col: 12 },
                { key: 'valor', label: 'Valor do plano', type: 'number', col: 6 },
                { key: 'ciclo', label: 'Ciclo', type: 'select', options: ['Mensal', 'Trimestral', 'Semestral', 'Anual'], col: 6 },
                { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
                { key: 'textoBotao', label: 'Texto do botão', type: 'text', col: 6 },
                { key: 'descricao', label: 'Descrição', type: 'textarea', col: 12 },
            ],
            profissionalForm: [
                { key: 'nome', label: 'Nome', type: 'text', col: 6 },
                { key: 'funcao', label: 'Função', type: 'text', col: 6 },
                { key: 'tituloCard', label: 'Título do card', type: 'text', col: 6 },
                { key: 'documento', label: 'Documento', type: 'text', col: 6 },
                { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
                { key: 'email', label: 'E-mail', type: 'email', col: 6 },
                { key: 'telefone', label: 'Telefone', type: 'text', col: 6 },
                { key: 'celular', label: 'Celular', type: 'text', col: 6 },
                { key: 'descricao', label: 'Descrição', type: 'textarea', col: 12 },
                { key: 'experiencia', label: 'Experiência', type: 'textarea', col: 12 },
                { key: 'foto', label: 'Foto (upload ou URL)', type: 'image', col: 12 },
                { key: 'observacaoInterna', label: 'Observação interna', type: 'textarea', col: 12 },
            ],
            alunoDoProfissionalForm: [
                { key: 'nome', label: 'Nome', type: 'text', col: 12 },
                { key: 'contato', label: 'Contato', type: 'text', col: 6 },
                { key: 'plano', label: 'Plano', type: 'text', col: 6 },
                { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
                { key: 'valor', label: 'Valor', type: 'number', col: 6 },
                { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
            ],
            agendaDisponivel: [
                { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
                { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
            ],
            agendaAgendar: [
                { key: 'aluno', label: 'Aluno', type: 'text', col: 12 },
                { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
                { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
                { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
            ],
            utilizarCashback: [
                { key: 'valor', label: 'Valor a utilizar', type: 'number', col: 12 },
            ],
            planoAlterar: [
                { key: 'plano', label: 'Novo plano', type: 'select', options: BO_PLANOS_OPTIONS, col: 12 },
            ],
            perfilEdit: [
                { key: 'nome', label: 'Nome', type: 'text', col: 6 },
                { key: 'documento', label: 'Documento', type: 'text', col: 6 },
                { key: 'email', label: 'E-mail', type: 'email', col: 6 },
                { key: 'telefone', label: 'Telefone', type: 'text', col: 6 },
                { key: 'nacionalidade', label: 'Nacionalidade', type: 'text', col: 6 },
                { key: 'nascimento', label: 'Data de nascimento', type: 'date', col: 6 },
                { key: 'genero', label: 'Gênero', type: 'select', options: ['Masculino', 'Feminino'], col: 6 },
                { key: 'endereco', label: 'Endereço', type: 'text', col: 12 },
                { key: 'cidade', label: 'Cidade', type: 'text', col: 6 },
                { key: 'estado', label: 'Estado', type: 'text', col: 6 },
                { key: 'altura', label: 'Altura (m)', type: 'number', col: 6 },
                { key: 'peso', label: 'Peso (kg)', type: 'number', col: 6 },
                { key: 'foto', label: 'Foto (upload ou URL)', type: 'image', col: 12 },
            ],
            treinoExercicio: [
                { key: 'nome', label: 'Exercício', type: 'text', col: 12 },
                { key: 'series', label: 'Séries', type: 'number', col: 4 },
                { key: 'repeticoes', label: 'Repetições', type: 'number', col: 4 },
                { key: 'carga', label: 'Carga (kg)', type: 'number', col: 4 },
            ],
        };

        function boBuildField(field) {
            const wrap = document.createElement('div');
            wrap.className = 'col-' + (field.col || 12);

            const label = document.createElement('label');
            label.className = 'form-label';
            label.textContent = field.label;
            wrap.appendChild(label);

            if (field.type === 'select') {
                const select = document.createElement('select');
                select.className = 'form-select';
                select.setAttribute('data-bo-field', field.key);
                field.options.forEach((opt, i) => {
                    const o = document.createElement('option');
                    o.value = opt;
                    o.textContent = (field.optionLabels && field.optionLabels[i]) || opt;
                    select.appendChild(o);
                });
                wrap.appendChild(select);
            } else if (field.type === 'textarea') {
                const ta = document.createElement('textarea');
                ta.className = 'form-control';
                ta.rows = 3;
                ta.setAttribute('data-bo-field', field.key);
                wrap.appendChild(ta);
            } else if (field.type === 'checklist') {
                const box = document.createElement('div');
                box.className = 'd-flex flex-wrap gap-3';
                field.options.forEach((opt) => {
                    const id = 'chk_' + field.key + '_' + opt.replace(/\s+/g, '');
                    const chkWrap = document.createElement('div');
                    chkWrap.className = 'form-check';
                    chkWrap.innerHTML = `<input class="form-check-input" type="checkbox" id="${id}" value="${opt}" data-bo-checklist="${field.key}"><label class="form-check-label" for="${id}">${opt}</label>`;
                    box.appendChild(chkWrap);
                });
                wrap.appendChild(box);
            } else if (field.type === 'image') {
                const url = document.createElement('input');
                url.type = 'text';
                url.className = 'form-control mb-2';
                url.placeholder = 'URL da imagem';
                url.setAttribute('data-bo-field', field.key);
                wrap.appendChild(url);

                const file = document.createElement('input');
                file.type = 'file';
                file.accept = 'image/*';
                file.className = 'form-control mb-2';
                wrap.appendChild(file);

                const preview = document.createElement('img');
                preview.setAttribute('data-bo-preview', field.key);
                wrap.appendChild(preview);

                url.addEventListener('input', () => {
                    if (url.value) {
                        preview.src = url.value;
                        preview.style.display = 'block';
                    }
                });
                file.addEventListener('change', () => {
                    const f = file.files[0];
                    if (f) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            preview.src = e.target.result;
                            preview.style.display = 'block';
                            url.value = '';
                        };
                        reader.readAsDataURL(f);
                    }
                });
            } else {
                const input = document.createElement('input');
                input.type = field.type;
                input.className = 'form-control';
                input.setAttribute('data-bo-field', field.key);
                if (field.placeholder) input.placeholder = field.placeholder;
                if (field.readonly) input.readOnly = true;
                wrap.appendChild(input);
            }

            return wrap;
        }

        function boOpenForm(schemaKey, title, values, options) {
            values = values || {};
            options = options || {};

            const form = document.getElementById('boFormModalForm');
            form.innerHTML = '';
            document.getElementById('boFormModalTitle').textContent = title;

            const fields = BO_FORM_SCHEMAS[schemaKey] || [];
            fields.forEach((field) => form.appendChild(boBuildField(field)));

            fields.forEach((field) => {
                if (field.type === 'checklist') {
                    const selected = (values[field.key] || '').split(',').map((s) => s.trim());
                    form.querySelectorAll(`[data-bo-checklist="${field.key}"]`).forEach((chk) => {
                        chk.checked = selected.includes(chk.value);
                    });
                    return;
                }
                const el = form.querySelector(`[data-bo-field="${field.key}"]`);
                if (el && values[field.key] !== undefined) el.value = values[field.key];
                if (field.type === 'image' && values[field.key]) {
                    const preview = form.querySelector(`[data-bo-preview="${field.key}"]`);
                    if (preview) {
                        preview.src = values[field.key];
                        preview.style.display = 'block';
                    }
                }
            });

            const oldSaveBtn = document.getElementById('boFormModalSave');
            const saveBtn = oldSaveBtn.cloneNode(true);
            oldSaveBtn.parentNode.replaceChild(saveBtn, oldSaveBtn);
            saveBtn.textContent = 'Salvar';

            let confirmStep = 0;
            saveBtn.addEventListener('click', () => {
                if (options.doubleConfirm && confirmStep === 0) {
                    confirmStep = 1;
                    saveBtn.textContent = 'Clique novamente para confirmar';
                    return;
                }
                boFormModalInstance.hide();
                boToast('Alterações salvas.');
            });

            boFormModalInstance.show();
        }

        function boToast(msg) {
            const toast = document.getElementById('boToast');
            toast.textContent = msg;
            toast.classList.add('show');
            clearTimeout(window._boToastTimer);
            window._boToastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
        }

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
            boFormModalInstance = new bootstrap.Modal(document.getElementById('boFormModal'));

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
                const typeSelect = document.querySelector(`[data-bo-filter="type"][data-bo-target="${filterId}"]`);
                const dateFrom = document.querySelector(`[data-bo-filter="date-from"][data-bo-target="${filterId}"]`);
                const dateTo = document.querySelector(`[data-bo-filter="date-to"][data-bo-target="${filterId}"]`);
                const emptyRow = table.querySelector('.bo-empty-row');

                const applyFilters = () => {
                    const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
                    const status = statusSelect ? statusSelect.value : '';
                    const type = typeSelect ? typeSelect.value : '';
                    const from = dateFrom ? dateFrom.value : '';
                    const to = dateTo ? dateTo.value : '';
                    let visibleCount = 0;

                    table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => {
                        const haystack = row.getAttribute('data-search') || '';
                        const rowStatus = row.getAttribute('data-status') || '';
                        const rowType = row.getAttribute('data-type') || '';
                        const rowDate = row.getAttribute('data-date') || '';

                        const matchesTerm = term === '' || haystack.toLowerCase().includes(term);
                        const matchesStatus = status === '' || rowStatus === status;
                        const matchesType = type === '' || rowType === type;
                        const matchesFrom = from === '' || rowDate === '' || rowDate >= from;
                        const matchesTo = to === '' || rowDate === '' || rowDate <= to;

                        const visible = matchesTerm && matchesStatus && matchesType && matchesFrom && matchesTo;
                        row.style.display = visible ? '' : 'none';
                        if (visible) visibleCount += 1;
                    });

                    if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';
                };

                [searchInput, statusSelect, typeSelect, dateFrom, dateTo].forEach((el) => {
                    if (!el) return;
                    el.addEventListener('input', applyFilters);
                    el.addEventListener('change', applyFilters);
                });
            });

            document.body.addEventListener('click', (event) => {
                const toggleBtn = event.target.closest('[data-bo-action="toggle-status"]');
                if (toggleBtn) {
                    const row = toggleBtn.closest('tr');
                    const badge = row.querySelector('.bo-badge');
                    const active = badge.classList.contains('bo-badge-active');
                    const onLabel = toggleBtn.getAttribute('data-on') || 'Ativo';
                    const offLabel = toggleBtn.getAttribute('data-off') || 'Inativo';

                    badge.classList.toggle('bo-badge-active', !active);
                    badge.classList.toggle('bo-badge-inactive', active);
                    badge.textContent = active ? offLabel : onLabel;
                    row.setAttribute('data-status', active ? 'inativo' : 'ativo');
                    toggleBtn.innerHTML = `<i class="bi ${active ? 'bi-play-circle' : 'bi-pause-circle'}"></i>`;
                    toggleBtn.title = active ? 'Ativar' : 'Pausar/Inativar';
                }

                const deleteBtn = event.target.closest('[data-bo-action="delete"]');
                if (deleteBtn) {
                    const label = deleteBtn.getAttribute('data-bo-name') || 'este registro';
                    if (window.confirm(`Tem certeza que deseja excluir ${label}?`)) {
                        deleteBtn.closest('tr').remove();
                        boToast('Registro excluído.');
                    }
                }

                const clearBtn = event.target.closest('[data-bo-action="clear-table"]');
                if (clearBtn) {
                    const tableSel = clearBtn.getAttribute('data-bo-target-table');
                    const table = document.querySelector(`[data-bo-table="${tableSel}"]`);
                    if (table && window.confirm('Tem certeza que deseja limpar todos os itens?')) {
                        table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => row.remove());
                        const emptyRow = table.querySelector('.bo-empty-row');
                        if (emptyRow) emptyRow.style.display = '';
                        boToast('Lista limpa.');
                    }
                }

                const removeCardBtn = event.target.closest('[data-bo-remove-card]');
                if (removeCardBtn) {
                    if (window.confirm('Remover este horário?')) {
                        removeCardBtn.closest('.bo-agenda-card').remove();
                    }
                }

                const exportBtn = event.target.closest('[data-bo-export]');
                if (exportBtn) {
                    boExportTableCsv(exportBtn.getAttribute('data-bo-export'));
                }
            });
        });

        /* ---------- IMC ---------- */
        function boCalcularIMC() {
            const altura = parseFloat(document.getElementById('imcAltura').value);
            const peso = parseFloat(document.getElementById('imcPeso').value);
            const resultado = document.getElementById('imcResultado');

            if (!altura || !peso) {
                resultado.textContent = 'Informe altura e peso.';
                return;
            }

            const imc = peso / (altura * altura);
            let status = 'Normal';
            if (imc < 18.5) status = 'Abaixo do peso';
            else if (imc >= 25 && imc < 30) status = 'Sobrepeso';
            else if (imc >= 30) status = 'Obesidade';

            resultado.textContent = imc.toFixed(1) + ' · ' + status;
        }

        /* ---------- Pagar plano (Pix/Cartão) ---------- */
        document.addEventListener('DOMContentLoaded', () => {
            const painelPix = document.getElementById('painelPix');
            const painelCartao = document.getElementById('painelCartao');
            const metodoPix = document.getElementById('metodoPix');

            document.querySelectorAll('input[name="metodoPagamento"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    const isPix = metodoPix.checked;
                    painelPix.style.display = isPix ? 'block' : 'none';
                    painelCartao.style.display = isPix ? 'none' : 'block';
                });
            });

            const btnGerarQr = document.getElementById('btnGerarQr');
            if (btnGerarQr) {
                btnGerarQr.addEventListener('click', () => {
                    document.getElementById('pixResultado').style.display = 'block';
                });
            }

            const btnCopiarPix = document.getElementById('btnCopiarPix');
            if (btnCopiarPix) {
                btnCopiarPix.addEventListener('click', () => {
                    const campo = document.getElementById('pixCopiaCola');
                    campo.select();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(campo.value).then(() => boToast('Código Pix copiado.'));
                    } else {
                        document.execCommand('copy');
                        boToast('Código Pix copiado.');
                    }
                });
            }

            const btnPagar = document.getElementById('btnPagar');
            if (btnPagar) {
                btnPagar.addEventListener('click', () => {
                    const modalEl = document.getElementById('modalPagarPlano');
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    boToast('Pagamento simulado com sucesso!');
                });
            }
        });

        /* ---------- Exportar tabela em CSV ---------- */
        function boExportTableCsv(tableId) {
            const table = document.querySelector(`[data-bo-table="${tableId}"]`);
            if (!table) return;

            const rows = [];
            table.querySelectorAll('thead tr').forEach((tr) => {
                const cols = Array.from(tr.querySelectorAll('th')).map((th) => `"${th.textContent.trim()}"`);
                rows.push(cols.join(';'));
            });
            table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((tr) => {
                if (tr.style.display === 'none') return;
                const cols = Array.from(tr.querySelectorAll('td')).map((td) => `"${td.textContent.trim().replace(/\s+/g, ' ')}"`);
                rows.push(cols.join(';'));
            });

            const blob = new Blob(['﻿' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = tableId + '.csv';
            link.click();
            boToast('Exportação gerada.');
        }
    </script>
</body>

</html>
