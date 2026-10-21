<?php
require_once __DIR__ . '/treino.php';
require_once __DIR__ . '/compras.php';
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
    return onefitTraduzir($labels[$status] ?? ucfirst($status));
}

function bo_label_forma_pagamento(string $forma): string
{
    $labels = ['pix' => 'PIX', 'cartao' => 'Cartão', 'cashback' => 'Cashback'];
    return onefitTraduzir($labels[$forma] ?? ucfirst($forma));
}

function bo_ciclo_por_duracao(?int $dias): string
{
    if ($dias === null) return '—';
    if ($dias <= 31) return 'Mensal';
    if ($dias <= 93) return 'Trimestral';
    if ($dias <= 186) return 'Semestral';
    return 'Anual';
}

/*
 * Telas compartilhadas entre perfis: o admin gerencia; a recepção (Alunos,
 * Pagamentos, Modalidades, Cashback) e o vendedor (Categorias, Cashback) só
 * consultam. A gravação continua nas rotas funcionalidades/*.php do admin.
 */

/** Tela "Alunos" (seção "usuarios"): só contas de aluno. Funcionários ficam na tela "Funcionários". */
function bo_carregar_alunos(mysqli $conn): array
{
    $usuarios = [];
    $sql = "SELECT u.id_usuario, u.nome, u.email, u.cpf, u.status,
                   u.celular, u.genero, u.data_nascimento, u.nacionalidade, u.endereco, u.cidade_estado,
                   m.id_matricula, m.data_inicio, m.data_fim, m.id_plano, pl.nome AS plano_nome
            FROM usuarios u
            LEFT JOIN matricula m ON m.id_matricula = (
                SELECT id_matricula FROM matricula
                WHERE id_usuario = u.id_usuario
                ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1
            )
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
            WHERE u.tipo_usuario = 'aluno'
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
                'idPlano' => $row['id_plano'] ? (int) $row['id_plano'] : null,
                'plano' => $row['plano_nome'] ?: '—',
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
    return $usuarios;
}

/** Tela "Pagamentos": cobranças das matrículas. */
function bo_carregar_pagamentos(mysqli $conn): array
{
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
    return $pagamentos;
}

/**
 * Origem de um lançamento de cashback, pela mesma regra que o grava:
 *  - crédito 'produto' (checkout), 'mensalidade' ou 'uso' (lançamento manual
 *    ou distribuição em massa do admin): cashback concedido;
 *  - crédito 'devolucao' (devoluções concluídas): valor do pedido devolvido,
 *    não é benefício;
 *  - débito com pedido (checkout, "Uso de cashback no pedido #N"): uso em compra;
 *  - outro débito (lançamento manual do admin): débito manual.
 */
function bo_cashback_origem(string $tipo, string $origem, ?int $pedido): string
{
    if ($tipo === 'debito') return $pedido !== null ? 'uso_compra' : 'debito_manual';
    return ['produto' => 'compra', 'mensalidade' => 'mensalidade', 'devolucao' => 'devolucao'][$origem] ?? 'admin';
}

function bo_cashback_origem_labels(): array
{
    return [
        'compra' => onefitTraduzir('Cashback de compra'),
        'mensalidade' => onefitTraduzir('Cashback de mensalidade'),
        'admin' => onefitTraduzir('Lançamento do admin'),
        'devolucao' => onefitTraduzir('Devolução de pedido'),
        'uso_compra' => onefitTraduzir('Uso em compra'),
        'debito_manual' => onefitTraduzir('Débito manual do admin'),
    ];
}

/**
 * Tela "Cashbacks": indicadores, lançamentos e saldo por cliente, de todos os
 * clientes (não do usuário logado). Lançamentos cancelados aparecem no
 * histórico, mas ficam fora dos totais, como no saldo do checkout e do aluno.
 * O saldo de cada cliente é créditos - débitos; o saldo disponível soma só os
 * saldos positivos (um saldo negativo não fica disponível para ninguém).
 * Valores somados em centavos para não acumular erro de ponto flutuante.
 * $comClientes: inclui o nome do cliente (admin e recepção; o vendedor vê só o ID).
 */
function bo_carregar_cashbacks(mysqli $conn, bool $comClientes = false): array
{
    $centavos = ['concedido' => 0, 'devolucoes' => 0, 'utilizado' => 0, 'debitosManuais' => 0];
    $cashbackTransacoes = [];
    $porCliente = [];
    $cancelados = 0;
    $sql = 'SELECT c.id_cashback, c.data_criacao, c.tipo, c.origem, c.status, c.valor, c.id_usuario, c.descricao,
                   d.id_pedido AS pedido_devolucao' . ($comClientes ? ', u.nome AS cliente' : '') . '
            FROM cashback c
            LEFT JOIN pedido_devolucao d ON d.id_cashback = c.id_cashback'
        . ($comClientes ? ' LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario' : '')
        . ' ORDER BY c.data_criacao DESC, c.id_cashback DESC';
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $usuarioId = (int) $row['id_usuario'];
            $pedido = $row['pedido_devolucao'] !== null ? (int) $row['pedido_devolucao'] : bo_cashback_pedido_relacionado((string) $row['descricao']);
            $origem = bo_cashback_origem($row['tipo'], $row['origem'], $pedido);
            $cancelado = $row['status'] === 'cancelado';
            $valorCentavos = (int) round((float) $row['valor'] * 100);
            $cashbackTransacoes[] = [
                'id' => (int) $row['id_cashback'],
                'data' => $row['data_criacao'],
                'tipo' => $row['tipo'],
                'valor' => (float) $row['valor'],
                'usuarioId' => $usuarioId,
                'cliente' => $comClientes ? ($row['cliente'] ?? '') : null,
                'motivo' => $row['descricao'],
                'origem' => $origem,
                'pedido' => $pedido,
                'cancelado' => $cancelado,
            ];
            $porCliente[$usuarioId] ??= ['usuarioId' => $usuarioId, 'cliente' => $comClientes ? ($row['cliente'] ?? '') : null,
                'creditos' => 0, 'debitos' => 0, 'movimentos' => 0, 'ultima' => $row['data_criacao']];
            $porCliente[$usuarioId]['movimentos']++;
            if ($cancelado) {
                $cancelados++;
                continue;
            }
            $porCliente[$usuarioId][$row['tipo'] === 'credito' ? 'creditos' : 'debitos'] += $valorCentavos;
            $centavos[['compra' => 'concedido', 'mensalidade' => 'concedido', 'admin' => 'concedido', 'devolucao' => 'devolucoes',
                'uso_compra' => 'utilizado', 'debito_manual' => 'debitosManuais'][$origem]] += $valorCentavos;
        }
    }

    $saldoDisponivel = 0;
    $saldoNegativo = 0;
    $clientesComSaldo = 0;
    $clientesSaldoNegativo = 0;
    foreach ($porCliente as &$cliente) {
        $saldo = $cliente['creditos'] - $cliente['debitos'];
        if ($saldo > 0) { $saldoDisponivel += $saldo; $clientesComSaldo++; }
        if ($saldo < 0) { $saldoNegativo += $saldo; $clientesSaldoNegativo++; }
        $cliente = ['creditos' => $cliente['creditos'] / 100, 'debitos' => $cliente['debitos'] / 100, 'saldo' => $saldo / 100] + $cliente;
    }
    unset($cliente);
    $clientes = array_values($porCliente);
    usort($clientes, static fn(array $a, array $b): int => [$b['saldo'], $a['usuarioId']] <=> [$a['saldo'], $b['usuarioId']]);

    $cashbackResumo = array_map(static fn(int $c): float => $c / 100, $centavos) + [
        'saldoDisponivel' => $saldoDisponivel / 100,
        'saldoNegativo' => $saldoNegativo / 100,
        'clientesComSaldo' => $clientesComSaldo,
        'clientesSaldoNegativo' => $clientesSaldoNegativo,
        'movimentos' => count($cashbackTransacoes),
        'cancelados' => $cancelados,
    ];
    return ['resumo' => $cashbackResumo, 'transacoes' => $cashbackTransacoes, 'clientes' => $clientes];
}

/** Tela "Categorias"; $somenteAtivas para quem só consulta as categorias usadas no cadastro de produtos. */
function bo_carregar_categorias(mysqli $conn, bool $somenteAtivas = false): array
{
    $categorias = [];
    $sql = 'SELECT id_categoria, nome, status FROM categorias' . ($somenteAtivas ? " WHERE status = 'ativo'" : '') . ' ORDER BY nome';
    if ($r = $conn->query($sql)) {
        while ($row = $r->fetch_assoc()) {
            $categorias[] = ['id' => (int) $row['id_categoria'], 'nome' => $row['nome'], 'status' => $row['status']];
        }
    }
    return $categorias;
}

/** Tela "Modalidades" (tabela `modalidades`). */
function bo_carregar_modalidades(mysqli $conn): array
{
    $modalidades = [];
    if ($r = $conn->query('SELECT id_modalidade, nome, descricao, icone, status FROM modalidades ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $modalidades[] = [
                'id' => (int) $row['id_modalidade'],
                'nome' => $row['nome'],
                'descricao' => $row['descricao'],
                'icone' => $row['icone'],
                'status' => $row['status'],
            ];
        }
    }
    return $modalidades;
}

/* =======================================================================
 * PERFIL: ADMINISTRADOR
 * ===================================================================== */
if ($perfilLogado === 'admin') {

    // Cards da tela "Dashboard"
    $admDashboard = [
        'alunosTotal' => 0, 'alunosAtivos' => 0, 'alunosInativos' => 0, 'alunosNovosMes' => 0,
        'funcionariosAtivos' => 0, 'funcionariosInativos' => 0,
        'matriculasAtivas' => 0, 'matriculasVencidas' => 0, 'matriculasPendentes' => 0,
        'pagamentosPendentes' => 0,
        'saldoDia' => 0, 'saldoSemana' => 0, 'saldoMes' => 0, 'saldoAno' => 0,
        'cashbackDia' => 0, 'cashbackSemana' => 0, 'cashbackMes' => 0, 'cashbackAno' => 0,
        'acessosLiberados' => 0, 'acessosBloqueados' => 0, 'totalUsuarios' => 0,
        'profissionaisAtivos' => 0, 'profissionaisAgendaveis' => 0, 'profissionaisIndisponiveis' => 0,
        'agendamentosProximos' => 0, 'agendamentosConcluidos' => 0, 'agendamentosCancelados' => 0,
    ];

    if ($r = $conn->query("SELECT
            SUM(tipo_usuario = 'aluno') AS alunos_total,
            SUM(tipo_usuario = 'aluno' AND status = 'ativo') AS alunos_ativos,
            SUM(tipo_usuario = 'aluno' AND status <> 'ativo') AS alunos_inativos,
            SUM(tipo_usuario = 'aluno' AND data_cadastro >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS alunos_novos_mes,
            SUM(tipo_usuario <> 'aluno' AND status = 'ativo') AS funcionarios_ativos,
            SUM(tipo_usuario <> 'aluno' AND status <> 'ativo') AS funcionarios_inativos,
            SUM(status != 'bloqueado') AS acessos_liberados,
            SUM(status = 'bloqueado') AS acessos_bloqueados,
            COUNT(*) AS total
        FROM usuarios")) {
        $row = $r->fetch_assoc();
        $admDashboard['alunosTotal'] = (int) $row['alunos_total'];
        $admDashboard['alunosAtivos'] = (int) $row['alunos_ativos'];
        $admDashboard['alunosInativos'] = (int) $row['alunos_inativos'];
        $admDashboard['alunosNovosMes'] = (int) $row['alunos_novos_mes'];
        $admDashboard['funcionariosAtivos'] = (int) $row['funcionarios_ativos'];
        $admDashboard['funcionariosInativos'] = (int) $row['funcionarios_inativos'];
        $admDashboard['acessosLiberados'] = (int) $row['acessos_liberados'];
        $admDashboard['acessosBloqueados'] = (int) $row['acessos_bloqueados'];
        $admDashboard['totalUsuarios'] = (int) $row['total'];
    }

    if ($r = $conn->query("SELECT
            SUM(status = 'ativa' AND data_inicio <= CURDATE() AND data_fim >= CURDATE()) AS ativas,
            SUM(status = 'vencida' OR (status = 'ativa' AND data_fim < CURDATE())) AS vencidas,
            SUM(status = 'pendente') AS pendentes
        FROM matricula")) {
        $row = $r->fetch_assoc();
        $admDashboard['matriculasAtivas'] = (int) $row['ativas'];
        $admDashboard['matriculasVencidas'] = (int) $row['vencidas'];
        $admDashboard['matriculasPendentes'] = (int) $row['pendentes'];
    }

    if ($r = $conn->query("SELECT
            SUM(CASE WHEN status = 'aprovado' AND DATE(data_pagamento) = CURDATE() THEN valor ELSE 0 END) AS dia,
            SUM(CASE WHEN status = 'aprovado' AND YEARWEEK(data_pagamento, 1) = YEARWEEK(CURDATE(), 1) THEN valor ELSE 0 END) AS semana,
            SUM(CASE WHEN status = 'aprovado' AND YEAR(data_pagamento) = YEAR(CURDATE()) AND MONTH(data_pagamento) = MONTH(CURDATE()) THEN valor ELSE 0 END) AS mes,
            SUM(CASE WHEN status = 'aprovado' AND YEAR(data_pagamento) = YEAR(CURDATE()) THEN valor ELSE 0 END) AS ano,
            SUM(status IN ('pendente', 'atrasado')) AS pendentes
        FROM pagamento WHERE status = 'aprovado' OR status IN ('pendente', 'atrasado')")) {
        $row = $r->fetch_assoc();
        $admDashboard['saldoDia'] = (float) $row['dia'];
        $admDashboard['saldoSemana'] = (float) $row['semana'];
        $admDashboard['saldoMes'] = (float) $row['mes'];
        $admDashboard['saldoAno'] = (float) $row['ano'];
        $admDashboard['pagamentosPendentes'] = (int) $row['pendentes'];
    }

    if ($r = $conn->query("SELECT
            SUM(CASE WHEN DATE(c.data_criacao) = CURDATE() THEN c.valor ELSE 0 END) AS dia,
            SUM(CASE WHEN YEARWEEK(c.data_criacao, 1) = YEARWEEK(CURDATE(), 1) THEN c.valor ELSE 0 END) AS semana,
            SUM(CASE WHEN YEAR(c.data_criacao) = YEAR(CURDATE()) AND MONTH(c.data_criacao) = MONTH(CURDATE()) THEN c.valor ELSE 0 END) AS mes,
            SUM(CASE WHEN YEAR(c.data_criacao) = YEAR(CURDATE()) THEN c.valor ELSE 0 END) AS ano
        FROM cashback c LEFT JOIN pedido_devolucao d ON d.id_cashback = c.id_cashback
        WHERE c.tipo = 'credito' AND COALESCE(c.origem, '') <> 'devolucao'
          AND COALESCE(c.status, '') <> 'cancelado' AND d.id_cashback IS NULL")) {
        $row = $r->fetch_assoc();
        $admDashboard['cashbackDia'] = (float) $row['dia'];
        $admDashboard['cashbackSemana'] = (float) $row['semana'];
        $admDashboard['cashbackMes'] = (float) $row['mes'];
        $admDashboard['cashbackAno'] = (float) $row['ano'];
    }

    if ($r = $conn->query("SELECT
            SUM(status IN ('agendado', 'confirmado') AND TIMESTAMP(data_evento, hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)) AS proximos,
            SUM(status = 'concluido' AND data_evento BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()) AS concluidos,
            SUM(status = 'cancelado' AND data_evento BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()) AS cancelados
        FROM agendamento")) {
        $row = $r->fetch_assoc();
        $admDashboard['agendamentosProximos'] = (int) $row['proximos'];
        $admDashboard['agendamentosConcluidos'] = (int) $row['concluidos'];
        $admDashboard['agendamentosCancelados'] = (int) $row['cancelados'];
    }

    // Tela "Comentários": depoimentos enviados pelos alunos (tabela ausente
    // no dump original, por isso criada defensivamente aqui) + comentários
    // avulsos sem conta de aluno (id_usuario NULL, seed inicial da home).
    $conn->query(
        "CREATE TABLE IF NOT EXISTS testemunhos (
            id_testemunho INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT NULL,
            nome_exibido VARCHAR(120) NULL,
            tempo_exibido VARCHAR(60) NULL,
            texto VARCHAR(500) NOT NULL,
            aprovacao ENUM('pendente','aprovado','reprovado') NOT NULL DEFAULT 'pendente',
            visibilidade ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_testemunho),
            UNIQUE KEY uk_testemunho_usuario (id_usuario),
            CONSTRAINT fk_testemunho_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $testemunhosAdmin = [];
    $r = $conn->query(
        "SELECT t.id_testemunho, t.texto, t.aprovacao, t.visibilidade, t.data_criacao,
                COALESCE(t.nome_exibido, u.nome) AS nome, u.genero
         FROM testemunhos t LEFT JOIN usuarios u ON u.id_usuario = t.id_usuario
         ORDER BY (t.aprovacao = 'pendente') DESC, t.data_criacao DESC"
    );
    while ($row = $r->fetch_assoc()) {
        $testemunhosAdmin[] = $row;
    }

    // Tela "Alunos" (seção "usuarios"): só contas de aluno.
    $usuarios = bo_carregar_alunos($conn);

    // As telas "Funções" e "Permissões" foram desativadas (config/papeis.php).

    // Tela "Funcionários" (includes/funcionarios.php). Falha de leitura deixa
    // a tela sem dados e sem nenhuma ação de gravação.
    require_once __DIR__ . '/funcionarios.php';
    $funcionarios = [];
    $cadastrosProfissionaisLivres = [];
    $funcionariosMigracao = ['ok' => false, 'faltando' => []];
    $funcionariosErro = false;
    // Cargo da sessão (revalidado no banco por conn.php): só decide o que a tela mostra.
    $funcionariosCargos = [];
    try {
        $funcionariosMigracao = bo_funcionarios_migracao($conn);
        $funcionariosCargos = array_values(array_filter(onefitCargosAtribuiveis($_SESSION['tipo_usuario'] ?? null), static fn(string $cargo): bool => bo_funcionarios_enum_aceita($conn, $cargo)));
        $funcionarios = bo_funcionarios_listar($conn, $_SESSION['tipo_usuario'] ?? null, (int) ($_SESSION['id_usuario'] ?? 0));
        $cadastrosProfissionaisLivres = bo_cadastros_profissionais_livres($conn);
    } catch (Throwable $erroFuncionarios) {
        error_log('ONE FIT: falha ao carregar funcionários. Código: ' . $erroFuncionarios->getCode());
        $funcionarios = [];
        $cadastrosProfissionaisLivres = [];
        $funcionariosErro = true;
    }

    // Telas "Pagamentos", "Cashbacks" e "Categorias"
    $pagamentos = bo_carregar_pagamentos($conn);
    ['resumo' => $cashbackResumo, 'transacoes' => $cashbackTransacoes, 'clientes' => $cashbackClientes] = bo_carregar_cashbacks($conn, true);
    $categorias = bo_carregar_categorias($conn);
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

    // Tela "Vendas Marketplace" (visão agregada de todos os vendedores)
    $admVendasProdutos = bo_carregar_produtos_vendedor($conn, null);
    $admVendas = bo_carregar_vendas_vendedor($conn, null);
    $admDevolucoes = bo_carregar_devolucoes($conn);
    $transportadoras = bo_carregar_transportadoras($conn);

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
    // Tela "Modalidades"
    $modalidadesAdm = bo_carregar_modalidades($conn);
    $modalidadesOptions = array_map(static fn(array $m): string => $m['nome'], $modalidadesAdm);

    // Tela "Professores" (seção "profissionais"; includes/professores.php): contas com
    // cargo professor e cadastro profissional vinculado. Falha de leitura deixa a
    // tela sem dados e sem nenhuma ação de gravação.
    require_once __DIR__ . '/professores.php';
    $professores = [];
    $professoresMigracao = false;
    $professoresPendencias = ['semCadastro' => 0, 'semConta' => 0];
    $professoresErro = false;
    try {
        $professoresMigracao = bo_professores_migracao($conn);
        $professores = bo_professores_listar($conn);
        $professoresPendencias = bo_professores_pendencias($conn);
    } catch (Throwable $erroProfessores) {
        error_log('ONE FIT: falha ao carregar professores. Código: ' . $erroProfessores->getCode());
        $professores = [];
        $professoresErro = true;
    }
    $admDashboard['profissionaisAtivos'] = count(array_filter($professores, static fn(array $p): bool => $p['conta'] === 'ativo'));
    $admDashboard['profissionaisAgendaveis'] = count(array_filter($professores, static fn(array $p): bool => $p['agenda']['recebe']));
    $admDashboard['profissionaisIndisponiveis'] = count($professores) - $admDashboard['profissionaisAgendaveis'];
}

/* =======================================================================
 * PERFIL: PROFISSIONAL
 * ===================================================================== */
// Meu perfil, Agenda e Treino: components/section-profissional.php lê os próprios
// dados (vínculos, horários, agendamentos e fichas dos alunos vinculados). O painel
// do professor não tem mais Dashboard, Histórico, Meu cashback nem Minhas compras.

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
        'status' => onefitTraduzir($statusLabel[$matriculaAtual['status'] ?? ''] ?? '—'),
        'documento' => $usuarioBanco['cpf'],
        'telefone' => $usuarioBanco['celular'],
        'dataCadastro' => $usuarioBanco['data_cadastro'] ? onefitData('d/m/Y', strtotime($usuarioBanco['data_cadastro'] ?? '')) : '—',
        'nascimento' => $usuarioBanco['data_nascimento'] ? onefitData('d/m/Y', strtotime($usuarioBanco['data_nascimento'])) : '—',
        'altura' => (float) $usuarioBanco['altura'],
        'peso' => (float) $usuarioBanco['peso'],
        'objetivo' => $usuarioBanco['objetivo'] ?: '',
        'valorContratado' => (float) ($matriculaAtual['valor_contratado'] ?? 0),
    ];

    // Tela "Perfil": testemunho enviado pelo aluno (card "Seu testemunho").
    $conn->query(
        "CREATE TABLE IF NOT EXISTS testemunhos (
            id_testemunho INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_usuario INT NULL,
            nome_exibido VARCHAR(120) NULL,
            tempo_exibido VARCHAR(60) NULL,
            texto VARCHAR(500) NOT NULL,
            aprovacao ENUM('pendente','aprovado','reprovado') NOT NULL DEFAULT 'pendente',
            visibilidade ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_testemunho),
            UNIQUE KEY uk_testemunho_usuario (id_usuario),
            CONSTRAINT fk_testemunho_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $alunoTestemunho = null;
    $stmt = $conn->prepare('SELECT texto, aprovacao FROM testemunhos WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $alunoTestemunho = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

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
            'data' => onefitData('d/m/Y H:i', strtotime($dataRef)),
            'descricao' => onefitTraduzir('Mensalidade {plano}', ['{plano}' => $row['plano'] ?? '']),
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
            'data' => onefitData('d/m/Y', strtotime($row['data_criacao'])),
            'tipo' => $row['tipo'],
            'descricao' => $row['descricao'],
            'valor' => (float) $row['valor'],
        ];
    }
    $stmt->close();

    // Tela "Minhas compras"
    [$alunoPedidos, $alunoPedidosHistorico] = bo_carregar_pedidos($conn, $idUsuarioLogado);

    // Tela "Treino": ficha persistida do aluno autenticado.
    $alunoTreino = bo_treino_carregar($conn, $idUsuarioLogado);

    // Tela "Minha agenda": calendário de horários disponíveis cadastrados
    // pelos profissionais (disponibilidade_profissional) + agendamentos já
    // confirmados do aluno (agendamento). Navegação de mês/dia é só por
    // querystring (?section=agenda&mes=AAAA-MM&dia=AAAA-MM-DD), sem JS.
    $conn->query(
        "CREATE TABLE IF NOT EXISTS disponibilidade_profissional (
            id_disponibilidade INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_profissional INT NOT NULL,
            modalidade VARCHAR(100) NOT NULL,
            data_evento DATE NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fim TIME NOT NULL,
            local VARCHAR(120) DEFAULT NULL,
            status ENUM('disponivel','ocupado','cancelado') NOT NULL DEFAULT 'disponivel',
            data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_disponibilidade),
            KEY idx_disponibilidade_profissional (id_profissional, data_evento, hora_inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $conn->query(
        "CREATE TABLE IF NOT EXISTS profissional_aluno (
            id_vinculo INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_profissional INT NOT NULL,
            id_aluno INT NOT NULL,
            status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
            observacao VARCHAR(255) DEFAULT NULL,
            data_vinculo TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id_vinculo),
            UNIQUE KEY uk_profissional_aluno (id_profissional, id_aluno),
            KEY idx_profissional_aluno_aluno (id_aluno)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $alunoAgendaMesRef = (isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['mes']))
        ? (string) $_GET['mes'] . '-01' : date('Y-m-01');
    $alunoAgendaMesTs = strtotime($alunoAgendaMesRef) ?: strtotime(date('Y-m-01'));
    $alunoAgendaMes = date('Y-m', $alunoAgendaMesTs);
    $alunoAgendaDiaSelecionado = (isset($_GET['dia']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['dia']))
        ? (string) $_GET['dia'] : null;

    $inicioMes = date('Y-m-01', $alunoAgendaMesTs);
    $fimMes = date('Y-m-t', $alunoAgendaMesTs);

    require_once __DIR__ . '/agenda-service.php';
    $agendaMonthSlots = bo_agenda_slots($conn, $inicioMes, $fimMes);
    $alunoAgendaDiasDisponiveis = array_fill_keys(array_column($agendaMonthSlots, 'data_evento'), true);

    // Agendamentos do aluno dentro do mês exibido, agrupados por dia, para
    // mostrar o preview do evento direto na célula do calendário.
    $alunoAgendaEventosPorDia = [];
    $stmt = $conn->prepare(
        "SELECT a.tipo, a.data_evento, a.hora_inicio, p.nome AS profissional, p.especialidade
         FROM agendamento a
         LEFT JOIN cadastro_profissional p ON p.id_profissional = a.id_profissional
         WHERE a.id_usuario = ? AND a.status IN ('agendado', 'confirmado') AND a.data_evento BETWEEN ? AND ?
         ORDER BY a.data_evento, a.hora_inicio"
    );
    $stmt->bind_param('iss', $idUsuarioLogado, $inicioMes, $fimMes);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $alunoAgendaEventosPorDia[$row['data_evento']][] = [
            'titulo' => $row['especialidade'] ?: $row['profissional'] ?: ucfirst($row['tipo']),
            'hora' => substr($row['hora_inicio'], 0, 5),
            'tipo' => $row['tipo'],
        ];
    }
    $stmt->close();

    $alunoAgendaSlots = array_values(array_filter($agendaMonthSlots,
        static fn($slot) => $slot['data_evento'] === $alunoAgendaDiaSelecionado));

    $alunoAgendaMeusAgendamentos = [];
    $stmt = $conn->prepare(
        "SELECT a.id_agendamento, a.titulo, a.tipo, a.data_evento, a.hora_inicio, a.status, p.nome AS profissional
         FROM agendamento a
         LEFT JOIN cadastro_profissional p ON p.id_profissional = a.id_profissional
         WHERE a.id_usuario = ?
         ORDER BY a.data_evento, a.hora_inicio"
    );
    $stmt->bind_param('i', $idUsuarioLogado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $alunoAgendaMeusAgendamentos[] = $row;
    }
    $stmt->close();
}

/* =======================================================================
 * PERFIL: VENDEDOR
 * ===================================================================== */
if ($perfilLogado === 'vendedor') {
    $idUsuarioLogado = (int) $_SESSION['id_usuario'];

    $vendedorProdutos = bo_carregar_produtos_vendedor($conn, $idUsuarioLogado);
    $vendedorProdutosResumo = [
        'total' => count($vendedorProdutos),
        'disponiveis' => count(array_filter($vendedorProdutos, static fn(array $p): bool => $p['status'] === 'disponivel')),
        'indisponiveis' => count(array_filter($vendedorProdutos, static fn(array $p): bool => $p['status'] === 'indisponivel')),
    ];
    $vendedorVendas = bo_carregar_vendas_vendedor($conn, $idUsuarioLogado);
    // "Categorias" e "Cashback": só consulta. Criar, editar e excluir categorias
    // ou lançamentos de cashback continua exclusivo do admin.
    $categorias = bo_carregar_categorias($conn, true);
    $categoriasAtivasOptions = array_column($categorias, 'nome');
    // Sem o nome dos clientes: o vendedor continua vendo só o ID de cada lançamento.
    ['resumo' => $cashbackResumo, 'transacoes' => $cashbackTransacoes, 'clientes' => $cashbackClientes] = bo_carregar_cashbacks($conn);
    // Só para exibir o nome da transportadora escolhida em cada venda — o
    // cadastro de transportadoras em si é exclusivo do admin (globais).
    $transportadorasAtivas = array_values(array_filter(
        bo_carregar_transportadoras($conn),
        static fn(array $t): bool => $t['status'] === 'ativo'
    ));
}

/* =======================================================================
 * PERFIL: RECEPÇÃO
 * Alunos, Pagamentos, Modalidades, Cashback dos clientes e Pedidos do
 * Marketplace: só consulta (as rotas de gravação continuam exclusivas do
 * admin e do vendedor). O cadastro facial lê os próprios dados em
 * components/section-fotos-faciais.php.
 * ===================================================================== */
if ($perfilLogado === 'recepcionista') {
    $usuarios = bo_carregar_alunos($conn);
    $pagamentos = bo_carregar_pagamentos($conn);
    ['resumo' => $cashbackResumo, 'transacoes' => $cashbackTransacoes, 'clientes' => $cashbackClientes] = bo_carregar_cashbacks($conn, true);
    $modalidadesAdm = bo_carregar_modalidades($conn);
    $recepcaoPedidos = bo_carregar_pedidos_consulta($conn);
}

/**
 * Catálogo de produtos filtrado por vendedor (id_vendedor = usuarios.id_usuario
 * do dono). Passe null para trazer todos os vendedores (visão agregada do
 * admin na tela "Vendas Marketplace"). Mesmo shape de $produtos em
 * "Produtos" do admin, com o nome do vendedor a mais.
 */
function bo_carregar_produtos_vendedor(mysqli $conn, ?int $idVendedor): array
{
    $sql = "SELECT p.id_produto, p.nome, p.categoria, p.preco, p.desconto, p.cashback_valor, p.estoque, p.status, p.imagem, p.descricao,
                   COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
            FROM produtos p
            LEFT JOIN usuarios v ON v.id_usuario = p.id_vendedor"
        . ($idVendedor !== null ? ' WHERE p.id_vendedor = ?' : '')
        . ' ORDER BY p.nome';
    $stmt = $conn->prepare($sql);
    if ($idVendedor !== null) {
        $stmt->bind_param('i', $idVendedor);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $produtos = [];
    while ($row = $res->fetch_assoc()) {
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
            'vendedor' => $row['vendedor_nome'],
        ];
    }
    $stmt->close();

    return $produtos;
}

/**
 * Vendas (itens de pedido) de um vendedor, com produto, comprador,
 * transportadora e status de logística. Passe null para trazer as vendas
 * de todos os vendedores (visão agregada do admin).
 */
function bo_carregar_vendas_vendedor(mysqli $conn, ?int $idVendedor): array
{
    $sql = "SELECT pi.id_item, pi.id_pedido, pi.quantidade, pi.subtotal, pi.valor_frete, pi.status_logistica, pi.codigo_rastreio,
                   pr.nome AS produto_nome, u.nome AS comprador_nome, pe.data_pedido, pe.status AS status_pedido,
                   t.nome AS transportadora_nome, COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
            FROM pedido_item pi
            JOIN pedido pe ON pe.id_pedido = pi.id_pedido
            JOIN produtos pr ON pr.id_produto = pi.id_produto
            JOIN usuarios u ON u.id_usuario = pe.id_usuario
            LEFT JOIN transportadoras t ON t.id_transportadora = pi.id_transportadora
            LEFT JOIN usuarios v ON v.id_usuario = pi.id_vendedor"
        . ($idVendedor !== null ? ' WHERE pi.id_vendedor = ?' : ((int) ($_GET['pedido'] ?? 0) > 0 ? ' WHERE pe.id_pedido = ?' : ''))
        . ' ORDER BY pe.data_pedido DESC';
    $stmt = $conn->prepare($sql);
    if ($idVendedor !== null) {
        $stmt->bind_param('i', $idVendedor);
    } elseif ((int) ($_GET['pedido'] ?? 0) > 0) {
        $pedidoFiltro = (int) $_GET['pedido'];
        $stmt->bind_param('i', $pedidoFiltro);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $statusLogisticaLabel = ['aguardando' => 'Aguardando', 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => 'Devolvido', 'extraviado' => 'Extraviado'];

    $vendas = [];
    while ($row = $res->fetch_assoc()) {
        $vendas[] = [
            'id' => (int) $row['id_item'],
            'idPedido' => (int) $row['id_pedido'],
            'produto' => $row['produto_nome'],
            'vendedor' => $row['vendedor_nome'],
            'comprador' => $row['comprador_nome'],
            'quantidade' => (int) $row['quantidade'],
            'valor' => (float) $row['subtotal'],
            'valorFrete' => (float) $row['valor_frete'],
            'transportadora' => $row['transportadora_nome'] ?? '—',
            'statusPedido' => $row['status_pedido'],
            'statusLogistica' => $row['status_logistica'],
            'statusLogisticaLabel' => onefitTraduzir($statusLogisticaLabel[$row['status_logistica']] ?? ucfirst($row['status_logistica'])),
            'codigoRastreio' => $row['codigo_rastreio'],
            'data' => onefitData('d/m/Y H:i', strtotime($row['data_pedido'])),
        ];
    }
    $stmt->close();

    return $vendas;
}

/**
 * Solicitações de devolução de pedidos (tabela `pedido_devolucao`), com
 * dados do pedido e do comprador — usada na aba "Devoluções" de Vendas
 * Marketplace (admin).
 */
function bo_carregar_devolucoes(mysqli $conn): array
{
    $sql = "SELECT d.id_devolucao, d.id_pedido, d.motivo, d.observacao, d.status,
                   d.data_solicitacao, d.data_analise, d.data_conclusao,
                   pe.valor_total, pe.status AS status_pedido, u.nome AS comprador_nome
            FROM pedido_devolucao d
            JOIN pedido pe ON pe.id_pedido = d.id_pedido
            JOIN usuarios u ON u.id_usuario = pe.id_usuario
            ORDER BY d.data_solicitacao DESC";
    $res = $conn->query($sql);

    $devolucoes = [];
    while ($row = $res->fetch_assoc()) {
        $devolucoes[] = [
            'id' => (int) $row['id_devolucao'],
            'idPedido' => (int) $row['id_pedido'],
            'transacao' => 'TRX-' . str_pad((string) $row['id_pedido'], 4, '0', STR_PAD_LEFT),
            'comprador' => $row['comprador_nome'],
            'valor' => (float) $row['valor_total'],
            'motivo' => $row['motivo'],
            'observacao' => $row['observacao'],
            'status' => $row['status'],
            'dataSolicitacao' => onefitData('d/m/Y H:i', strtotime($row['data_solicitacao'])),
            'dataAnalise' => $row['data_analise'] ? onefitData('d/m/Y H:i', strtotime($row['data_analise'])) : null,
            'dataConclusao' => $row['data_conclusao'] ? onefitData('d/m/Y H:i', strtotime($row['data_conclusao'])) : null,
        ];
    }

    return $devolucoes;
}

/**
 * Transportadoras globais (cadastradas pelo admin) com suas faixas de CEP.
 */
function bo_carregar_transportadoras(mysqli $conn): array
{
    $transportadoras = [];
    $res = $conn->query('SELECT id_transportadora, nome, tipo, status FROM transportadoras ORDER BY nome');
    while ($row = $res->fetch_assoc()) {
        $transportadoras[(int) $row['id_transportadora']] = [
            'id' => (int) $row['id_transportadora'],
            'nome' => $row['nome'],
            'tipo' => $row['tipo'],
            'status' => $row['status'],
            'faixas' => [],
        ];
    }

    $resFaixas = $conn->query('SELECT id_faixa, id_transportadora, cep_inicial, cep_final, valor_frete, prazo_dias FROM faixas_cep_frete ORDER BY cep_inicial');
    while ($row = $resFaixas->fetch_assoc()) {
        $idTransportadora = (int) $row['id_transportadora'];
        if (isset($transportadoras[$idTransportadora])) {
            $transportadoras[$idTransportadora]['faixas'][] = [
                'id' => (int) $row['id_faixa'],
                'cepInicial' => $row['cep_inicial'],
                'cepFinal' => $row['cep_final'],
                'valorFrete' => (float) $row['valor_frete'],
                'prazoDias' => (int) $row['prazo_dias'],
            ];
        }
    }

    return array_values($transportadoras);
}
