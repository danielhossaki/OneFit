<?php
/**
 * includes/db-data.php
 * Substitui includes/mock-data.php: monta as mesmas variáveis que os
 * components/section-admin.php, section-profissional.php e
 * section-aluno.php esperam, só que lendo do banco de verdade em vez de
 * usar dados fictícios. Cada bloco só roda para o perfil correspondente
 * ($perfilLogado), evitando consultas (e exposição de dados) desnecessárias.
 *
 * Depende de $conn (config/conn.php) e $perfilLogado/$_SESSION['id_usuario']
 * (já carregados em dashboard.php antes deste require).
 */

/**
 * Mapeia o status de pagamento/matrícula do banco para um rótulo em pt-BR.
 */
function bo_label_status_pagamento(string $status): string
{
    $labels = [
        'pendente' => 'Pendente',
        'aprovado' => 'Pago',
        'recusado' => 'Recusado',
        'cancelado' => 'Cancelado',
        'atrasado' => 'Atrasado',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function bo_label_forma_pagamento(string $forma): string
{
    $labels = ['pix' => 'PIX', 'cartao' => 'Cartão', 'cashback' => 'Cashback'];
    return $labels[$forma] ?? ucfirst($forma);
}

function bo_ciclo_por_duracao(?int $dias): string
{
    if ($dias === null) return '—';
    if ($dias <= 31) return 'Mensal';
    if ($dias <= 93) return 'Trimestral';
    if ($dias <= 186) return 'Semestral';
    return 'Anual';
}

/* =======================================================================
 * PERFIL: ADMINISTRADOR
 * ===================================================================== */
if ($perfilLogado === 'admin') {

    // Cards da tela "Dashboard"
    $admDashboard = [
        'usuariosAtivos' => 0, 'usuariosNovosMes' => 0,
        'saldoDia' => 0, 'saldoSemana' => 0, 'saldoMes' => 0, 'saldoAno' => 0,
        'cashbackDia' => 0, 'cashbackSemana' => 0, 'cashbackMes' => 0, 'cashbackAno' => 0,
        'acessosLiberados' => 0, 'acessosBloqueados' => 0, 'totalUsuarios' => 0,
        'profissionaisAtivos' => 0, 'profissionaisPendentes' => 0,
    ];

    if ($r = $conn->query("SELECT
            SUM(status = 'ativo') AS usuarios_ativos,
            SUM(status != 'bloqueado') AS acessos_liberados,
            SUM(status = 'bloqueado') AS acessos_bloqueados,
            SUM(data_cadastro >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS usuarios_novos_mes,
            COUNT(*) AS total
        FROM usuarios")) {
        $row = $r->fetch_assoc();
        $admDashboard['usuariosAtivos'] = (int) $row['usuarios_ativos'];
        $admDashboard['acessosLiberados'] = (int) $row['acessos_liberados'];
        $admDashboard['acessosBloqueados'] = (int) $row['acessos_bloqueados'];
        $admDashboard['usuariosNovosMes'] = (int) $row['usuarios_novos_mes'];
        $admDashboard['totalUsuarios'] = (int) $row['total'];
    }

    if ($r = $conn->query("SELECT
            SUM(CASE WHEN DATE(data_pagamento) = CURDATE() THEN valor ELSE 0 END) AS dia,
            SUM(CASE WHEN YEARWEEK(data_pagamento, 1) = YEARWEEK(CURDATE(), 1) THEN valor ELSE 0 END) AS semana,
            SUM(CASE WHEN YEAR(data_pagamento) = YEAR(CURDATE()) AND MONTH(data_pagamento) = MONTH(CURDATE()) THEN valor ELSE 0 END) AS mes,
            SUM(CASE WHEN YEAR(data_pagamento) = YEAR(CURDATE()) THEN valor ELSE 0 END) AS ano
        FROM pagamento WHERE status = 'aprovado'")) {
        $row = $r->fetch_assoc();
        $admDashboard['saldoDia'] = (float) $row['dia'];
        $admDashboard['saldoSemana'] = (float) $row['semana'];
        $admDashboard['saldoMes'] = (float) $row['mes'];
        $admDashboard['saldoAno'] = (float) $row['ano'];
    }

    if ($r = $conn->query("SELECT
            SUM(CASE WHEN DATE(data_criacao) = CURDATE() THEN valor ELSE 0 END) AS dia,
            SUM(CASE WHEN YEARWEEK(data_criacao, 1) = YEARWEEK(CURDATE(), 1) THEN valor ELSE 0 END) AS semana,
            SUM(CASE WHEN YEAR(data_criacao) = YEAR(CURDATE()) AND MONTH(data_criacao) = MONTH(CURDATE()) THEN valor ELSE 0 END) AS mes,
            SUM(CASE WHEN YEAR(data_criacao) = YEAR(CURDATE()) THEN valor ELSE 0 END) AS ano
        FROM cashback WHERE tipo = 'credito'")) {
        $row = $r->fetch_assoc();
        $admDashboard['cashbackDia'] = (float) $row['dia'];
        $admDashboard['cashbackSemana'] = (float) $row['semana'];
        $admDashboard['cashbackMes'] = (float) $row['mes'];
        $admDashboard['cashbackAno'] = (float) $row['ano'];
    }

    if ($r = $conn->query("SELECT
            SUM(status = 'ativo') AS ativos,
            SUM(status = 'inativo') AS pendentes
        FROM cadastro_profissional")) {
        $row = $r->fetch_assoc();
        $admDashboard['profissionaisAtivos'] = (int) $row['ativos'];
        $admDashboard['profissionaisPendentes'] = (int) $row['pendentes'];
    }

    // Tela "Usuários"
    $usuarios = [];
    $sql = "SELECT u.id_usuario, u.nome, u.email, u.cpf, u.status,
                   u.celular, u.genero, u.data_nascimento, u.nacionalidade, u.endereco, u.cidade_estado,
                   m.id_matricula, m.data_inicio, m.data_fim
            FROM usuarios u
            LEFT JOIN matricula m ON m.id_matricula = (
                SELECT id_matricula FROM matricula
                WHERE id_usuario = u.id_usuario
                ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1
            )
            ORDER BY u.nome";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $cidadeEstadoUsr = explode('/', $row['cidade_estado'] ?? '', 2);
            $usuarios[] = [
                'id' => (int) $row['id_usuario'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'cpf' => $row['cpf'],
                'status' => $row['status'] === 'ativo' ? 'ativo' : 'inativo',
                'matricula' => $row['id_matricula'] ? 'MAT-' . str_pad($row['id_matricula'], 4, '0', STR_PAD_LEFT) : '—',
                'dataInicial' => $row['data_inicio'] ?: '',
                'dataFinal' => $row['data_fim'] ?: '',
                'acesso' => $row['status'] === 'bloqueado' ? 'Bloqueado' : 'Liberado',
                'observacao' => '',
                'celular' => $row['celular'],
                'genero' => $row['genero'],
                'nascimento' => $row['data_nascimento'],
                'nacionalidade' => $row['nacionalidade'],
                'endereco' => $row['endereco'],
                'cidade' => trim($cidadeEstadoUsr[0] ?? ''),
                'estado' => trim($cidadeEstadoUsr[1] ?? ''),
            ];
        }
    }

    // Tela "Funções" — tabela `funcao`
    $funcoes = [];
    if ($r = $conn->query('SELECT id_funcao, nome FROM funcao ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $funcoes[] = ['id' => (int) $row['id_funcao'], 'nome' => $row['nome']];
        }
    }

    // Tela "Permissões" — tabela `permissoes` (email + função concedida)
    $permissoes = [];
    $sql = "SELECT pe.id_permissao, pe.nome, pe.email, pe.funcao AS id_funcao, f.nome AS funcaoLabel
            FROM permissoes pe
            LEFT JOIN funcao f ON f.id_funcao = pe.funcao
            ORDER BY pe.nome";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $permissoes[] = [
                'id' => (int) $row['id_permissao'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'id_funcao' => (int) $row['id_funcao'],
                'funcaoLabel' => $row['funcaoLabel'] ?? '—',
            ];
        }
    }

    // Tela "Pagamentos"
    $pagamentos = [];
    $sql = "SELECT p.id_pagamento, COALESCE(p.data_pagamento, p.data_vencimento) AS data, p.forma_pagamento, p.valor, p.status, m.id_usuario
            FROM pagamento p
            JOIN matricula m ON m.id_matricula = p.id_matricula
            ORDER BY data DESC";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $pagamentos[] = [
                'id' => (int) $row['id_pagamento'],
                'data' => $row['data'],
                'tipo' => bo_label_forma_pagamento($row['forma_pagamento']),
                'valor' => (float) $row['valor'],
                'usuarioId' => (int) $row['id_usuario'],
                'observacao' => bo_label_status_pagamento($row['status']),
            ];
        }
    }

    // Tela "Cashbacks"
    $cashbackResumo = ['saldoTotal' => 0, 'distribuidos' => 0, 'debitado' => 0, 'creditado' => 0];
    if ($r = $conn->query("SELECT
            SUM(CASE WHEN tipo = 'credito' THEN valor ELSE 0 END) AS creditado,
            SUM(CASE WHEN tipo = 'debito' THEN valor ELSE 0 END) AS debitado
        FROM cashback WHERE status != 'cancelado'")) {
        $row = $r->fetch_assoc();
        $cashbackResumo['creditado'] = (float) $row['creditado'];
        $cashbackResumo['distribuidos'] = (float) $row['creditado'];
        $cashbackResumo['debitado'] = (float) $row['debitado'];
        $cashbackResumo['saldoTotal'] = $cashbackResumo['creditado'] - $cashbackResumo['debitado'];
    }

    $cashbackTransacoes = [];
    $sql = "SELECT id_cashback, data_criacao, tipo, valor, id_usuario, descricao FROM cashback ORDER BY data_criacao DESC";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $cashbackTransacoes[] = [
                'id' => (int) $row['id_cashback'],
                'data' => $row['data_criacao'],
                'tipo' => $row['tipo'],
                'valor' => (float) $row['valor'],
                'usuarioId' => (int) $row['id_usuario'],
                'motivo' => $row['descricao'],
            ];
        }
    }

    // Tela "Categorias"
    $categorias = [];
    if ($r = $conn->query('SELECT id_categoria, nome, status FROM categorias ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $categorias[] = ['id' => (int) $row['id_categoria'], 'nome' => $row['nome'], 'status' => $row['status']];
        }
    }
    $categoriasAtivasOptions = array_values(array_map(
        static fn(array $c): string => $c['nome'],
        array_filter($categorias, static fn(array $c): bool => $c['status'] === 'ativo')
    ));

    // Tela "Produtos"
    $produtosResumo = ['total' => 0, 'disponiveis' => 0, 'indisponiveis' => 0];
    if ($r = $conn->query("SELECT COUNT(*) total, SUM(status='ativo') disponiveis, SUM(status='inativo') indisponiveis FROM produtos")) {
        $row = $r->fetch_assoc();
        $produtosResumo['total'] = (int) $row['total'];
        $produtosResumo['disponiveis'] = (int) $row['disponiveis'];
        $produtosResumo['indisponiveis'] = (int) $row['indisponiveis'];
    }

    $produtos = [];
    $sql = "SELECT id_produto, nome, categoria, preco, desconto, cashback_valor, estoque, status, imagem, descricao FROM produtos ORDER BY nome";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $desconto = (float) $row['desconto'];
            $preco = (float) $row['preco'];
            $produtos[] = [
                'id' => (int) $row['id_produto'],
                'nome' => $row['nome'],
                'categoria' => $row['categoria'],
                'preco' => $preco,
                'desconto' => $desconto,
                'valorFinal' => round($preco - ($preco * $desconto / 100), 2),
                'cashback' => (float) $row['cashback_valor'],
                'estoque' => (int) $row['estoque'],
                'status' => $row['status'] === 'ativo' ? 'disponivel' : 'indisponivel',
                'imagem' => $row['imagem'],
                'descricao' => $row['descricao'],
            ];
        }
    }

    // Tela "Cadastro de Planos"
    $planos = [];
    $sql = "SELECT id_plano, nome, valor, duracao_dias, descricao, beneficios, status FROM cadastro_planos ORDER BY valor";
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $planos[] = [
                'id' => (int) $row['id_plano'],
                'nome' => $row['nome'],
                'valor' => (float) $row['valor'],
                'ciclo' => bo_ciclo_por_duracao((int) $row['duracao_dias']),
                'descricao' => $row['descricao'],
                'beneficios' => $row['beneficios'],
                'status' => $row['status'],
                'textoBotao' => 'Assinar agora',
            ];
        }
    }
    $planosAtivosOptions = array_values(array_map(
        static fn(array $p): string => $p['nome'],
        array_filter($planos, static fn(array $p): bool => $p['status'] === 'ativo')
    ));

    // Tela "Modalidades"
    $modalidadesAdm = [];
    if ($r = $conn->query('SELECT id_modalidade, nome FROM modalidades ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $modalidadesAdm[] = ['id' => (int) $row['id_modalidade'], 'nome' => $row['nome']];
        }
    }
    $modalidadesOptions = array_map(static fn(array $m): string => $m['nome'], $modalidadesAdm);

    // Tela "Profissionais"
    $profissionaisAdm = [];
    try {
        $sql = "SELECT id_profissional, nome, especialidade, modalidades, registro_profissional, status, email, celular, descricao, foto
                FROM cadastro_profissional ORDER BY nome";
        $r = $conn->query($sql);
    } catch (\mysqli_sql_exception $e) {
        // Coluna "modalidades" ainda não existe neste banco (migração
        // modalidades-profissional-migration.sql pendente): consulta sem ela.
        $sql = "SELECT id_profissional, nome, especialidade, registro_profissional, status, email, celular, descricao, foto
                FROM cadastro_profissional ORDER BY nome";
        $r = $conn->query($sql);
    }
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $profissionaisAdm[] = [
                'id' => (int) $row['id_profissional'],
                'nome' => $row['nome'],
                'funcao' => $row['especialidade'],
                'tituloCard' => $row['especialidade'],
                'modalidades' => $row['modalidades'] ?? '',
                'documento' => $row['registro_profissional'],
                'status' => $row['status'],
                'email' => $row['email'],
                'telefone' => $row['celular'],
                'celular' => $row['celular'],
                'descricao' => $row['descricao'],
                'experiencia' => '',
                'foto' => $row['foto'],
                'observacaoInterna' => '',
            ];
        }
    }
}

/* =======================================================================
 * PERFIL: PROFISSIONAL
 * ===================================================================== */
if ($perfilLogado === 'profissional') {

    $idUsuarioLogado = (int) $_SESSION['id_usuario'];
    $idProfissional = null;
    $stmt = $conn->prepare('SELECT id_profissional, status FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $cadProf = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $idProfissional = $cadProf['id_profissional'] ?? null;

    $profContrato = ['status' => $cadProf ? ucfirst($cadProf['status']) : '—', 'validade' => '—', 'saldoCashback' => 0];

    $stmt = $conn->prepare("SELECT
            SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo
        FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $profContrato['saldoCashback'] = (float) ($row['saldo'] ?? 0);

    // Tela "Histórico" — não há tabela de repasses/comissão por competência no banco
    $profHistorico = [];

    // Tela "Alunos" — vinculados por agendamentos já realizados com este profissional
    $profAlunos = [];
    if ($idProfissional) {
        $stmt = $conn->prepare("SELECT DISTINCT u.id_usuario, u.nome, u.celular
                FROM agendamento a
                JOIN usuarios u ON u.id_usuario = a.id_usuario
                WHERE a.id_profissional = ?
                ORDER BY u.nome");
        $stmt->bind_param('i', $idProfissional);
        $stmt->execute();
        $alunosRes = $stmt->get_result();
        while ($aluno = $alunosRes->fetch_assoc()) {
            $stmt2 = $conn->prepare("SELECT m.status, m.valor_contratado, pl.nome AS plano
                    FROM matricula m
                    LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
                    WHERE m.id_usuario = ?
                    ORDER BY m.data_matricula DESC LIMIT 1");
            $stmt2->bind_param('i', $aluno['id_usuario']);
            $stmt2->execute();
            $mat = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            $profAlunos[] = [
                'id' => (int) $aluno['id_usuario'],
                'nome' => $aluno['nome'],
                'contato' => $aluno['celular'],
                'plano' => $mat['plano'] ?? '—',
                'status' => ($mat['status'] ?? '') === 'ativa' ? 'ativo' : 'inativo',
                'valor' => (float) ($mat['valor_contratado'] ?? 0),
                'observacao' => '',
            ];
        }
        $stmt->close();
    }

    // Tela "Agenda"
    $profAgendados = [];
    $profDisponiveis = []; // não há tabela de horários "livres" oferecidos pelo profissional no banco
    if ($idProfissional) {
        $stmt = $conn->prepare("SELECT a.titulo, a.tipo, a.data_evento, a.hora_inicio, u.nome, u.celular
                FROM agendamento a
                JOIN usuarios u ON u.id_usuario = a.id_usuario
                WHERE a.id_profissional = ? AND a.status IN ('agendado','confirmado')
                ORDER BY a.data_evento, a.hora_inicio");
        $stmt->bind_param('i', $idProfissional);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $profAgendados[] = [
                'aluno' => $row['nome'],
                'contato' => $row['celular'],
                'data' => date('d/m/Y', strtotime($row['data_evento'])) . ' ' . substr($row['hora_inicio'], 0, 5),
                'modalidade' => $row['titulo'] ?: ucfirst($row['tipo']),
            ];
        }
        $stmt->close();
    }

    // Tela "Meu cashback"
    $profCashbackHistorico = [];
    $stmt = $conn->prepare('SELECT data_criacao, descricao, valor FROM cashback WHERE id_usuario = ? ORDER BY data_criacao DESC');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $profCashbackHistorico[] = [
            'data' => date('d/m/Y', strtotime($row['data_criacao'])),
            'descricao' => $row['descricao'],
            'valor' => (float) $row['valor'],
        ];
    }
    $stmt->close();

    // Tela "Minhas compras"
    [$profPedidos, $profPedidosHistorico] = bo_carregar_pedidos($conn, $idUsuarioLogado);
}

/* =======================================================================
 * PERFIL: ALUNO
 * ===================================================================== */
if ($perfilLogado === 'aluno') {

    $idUsuarioLogado = (int) $_SESSION['id_usuario'];

    $stmt = $conn->prepare("SELECT m.status, m.valor_contratado, pl.nome AS plano
            FROM matricula m
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
            WHERE m.id_usuario = ?
            ORDER BY m.data_matricula DESC LIMIT 1");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $matriculaAtual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $statusLabel = ['ativa' => 'Ativo', 'pendente' => 'Pendente', 'vencida' => 'Vencido', 'cancelada' => 'Cancelado'];

    $alunoPerfil = [
        'nome' => $usuarioBanco['nome'],
        'email' => $usuarioBanco['email'],
        'plano' => $matriculaAtual['plano'] ?? '—',
        'status' => $statusLabel[$matriculaAtual['status'] ?? ''] ?? '—',
        'documento' => $usuarioBanco['cpf'],
        'telefone' => $usuarioBanco['celular'],
        'dataCadastro' => $usuarioBanco['data_cadastro'] ? date('d/m/Y', strtotime($usuarioBanco['data_cadastro'] ?? '')) : '—',
        'nascimento' => $usuarioBanco['data_nascimento'] ? date('d/m/Y', strtotime($usuarioBanco['data_nascimento'])) : '—',
        'altura' => (float) $usuarioBanco['altura'],
        'peso' => (float) $usuarioBanco['peso'],
        'objetivo' => $usuarioBanco['objetivo'] ?: '',
        'valorContratado' => (float) ($matriculaAtual['valor_contratado'] ?? 0),
    ];

    // Tela "Histórico"
    $alunoHistorico = [];
    $stmt = $conn->prepare("SELECT p.data_pagamento, p.data_vencimento, p.forma_pagamento, p.status, p.valor, p.id_pagamento, pl.nome AS plano
            FROM pagamento p
            JOIN matricula m ON m.id_matricula = p.id_matricula
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
            WHERE m.id_usuario = ?
            ORDER BY COALESCE(p.data_pagamento, p.data_vencimento) DESC");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dataRef = $row['data_pagamento'] ?: $row['data_vencimento'];
        $stmt2 = $conn->prepare('SELECT SUM(valor) total FROM cashback WHERE id_pagamento = ? AND tipo = "credito"');
        $stmt2->bind_param('i', $row['id_pagamento']);
        $stmt2->execute();
        $cb = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        $alunoHistorico[] = [
            'data' => date('d/m/Y H:i', strtotime($dataRef)),
            'descricao' => 'Mensalidade ' . ($row['plano'] ?? ''),
            'tipo' => bo_label_forma_pagamento($row['forma_pagamento']),
            'status' => bo_label_status_pagamento($row['status']),
            'valor' => (float) $row['valor'],
            'cashback' => (float) ($cb['total'] ?? 0),
        ];
    }
    $stmt->close();

    // Tela "Cashback"
    $alunoCashbackSaldo = 0;
    $stmt = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo
            FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $alunoCashbackSaldo = (float) ($row['saldo'] ?? 0);

    $alunoCashbackHistorico = [];
    $stmt = $conn->prepare('SELECT data_criacao, tipo, descricao, valor FROM cashback WHERE id_usuario = ? ORDER BY data_criacao DESC');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $alunoCashbackHistorico[] = [
            'data' => date('d/m/Y', strtotime($row['data_criacao'])),
            'tipo' => $row['tipo'],
            'descricao' => $row['descricao'],
            'valor' => (float) $row['valor'],
        ];
    }
    $stmt->close();

    // Tela "Minhas compras"
    [$alunoPedidos, $alunoPedidosHistorico] = bo_carregar_pedidos($conn, $idUsuarioLogado);

    // Tela "Treino" — não há tabela de ficha de treino no banco
    $alunoTreino = [];

    // Tela "Minha agenda" — não há tabela de horários "disponíveis" distinta dos agendamentos
    $alunoAgendaDisponiveis = [];
}

// Nomes de planos ativos: usados no <select> do modal "Alterar plano" do
// aluno. Para o admin, já foi calculado no bloco acima (evita repetir a query).
if (!isset($planosAtivosOptions)) {
    $planosAtivosOptions = [];
    if ($r = $conn->query("SELECT nome FROM cadastro_planos WHERE status = 'ativo' ORDER BY nome")) {
        while ($row = $r->fetch_assoc()) {
            $planosAtivosOptions[] = $row['nome'];
        }
    }
}

/**
 * Carrega os pedidos (marketplace) de um usuário, já separados em
 * "em andamento" e "histórico" — usado pelas telas "Minhas compras"
 * do profissional e do aluno.
 *
 * @return array{0: array, 1: array}
 */
function bo_carregar_pedidos(mysqli $conn, int $idUsuario): array
{
    $emAndamento = [];
    $historico = [];

    $stmt = $conn->prepare("SELECT pe.id_pedido, pe.status, pe.data_pedido,
            GROUP_CONCAT(pr.nome SEPARATOR ', ') AS produtos,
            SUM(pi.quantidade) AS quantidade,
            SUM(pi.subtotal) AS valor
        FROM pedido pe
        JOIN pedido_item pi ON pi.id_pedido = pe.id_pedido
        JOIN produtos pr ON pr.id_produto = pi.id_produto
        WHERE pe.id_usuario = ?
        GROUP BY pe.id_pedido
        ORDER BY pe.data_pedido DESC");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();

    $statusLabel = ['aguardando' => 'Aguardando', 'pago' => 'Pago', 'processando' => 'Processando', 'entregue' => 'Entregue', 'cancelado' => 'Cancelado', 'devolvido' => 'Devolvido'];
    $finalizados = ['entregue', 'cancelado', 'devolvido'];

    while ($row = $res->fetch_assoc()) {
        $item = [
            'transacao' => 'TRX-' . str_pad($row['id_pedido'], 4, '0', STR_PAD_LEFT),
            'produto' => $row['produtos'],
            'quantidade' => (int) $row['quantidade'],
            'valor' => (float) $row['valor'],
            'status' => $statusLabel[$row['status']] ?? ucfirst($row['status']),
            'data' => date('d/m/Y H:i', strtotime($row['data_pedido'])),
        ];
        if (in_array($row['status'], $finalizados, true)) {
            $historico[] = $item;
        } else {
            $emAndamento[] = $item;
        }
    }
    $stmt->close();

    return [$emAndamento, $historico];
}
