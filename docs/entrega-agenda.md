# ONE FIT — entrega da agenda

Arquivos completos já aplicados ao projeto. Substitua cada arquivo pelo bloco correspondente, mantendo os caminhos. Não é necessária uma nova tabela.

A disponibilidade de demonstração usa os profissionais ativos cadastrados e oferece os seis atendimentos simulados. Não representa a habilitação real de cada profissional; antes do uso em produção, configure os serviços e a disponibilidade reais. Horários cadastrados têm prioridade por profissional e dia. Reservas e cancelamentos são persistidos nas tabelas existentes.

Validação: sintaxe PHP e JavaScript; disponibilidade futura, seis atendimentos, datas passadas e inválidas, domingo, bloqueio de reserva e liberação após cancelamento (teste em transação revertida). Não foi possível validar visualmente: nenhum navegador estava disponível.

## ARQUIVO: pages/dashboard/includes/agenda-service.php

CÓDIGO COMPLETO:

```php
<?php
/** Disponibilidade de demonstração; reservas usam as tabelas existentes. */
function bo_agenda_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
}

function bo_agenda_types(): array
{
    return ['Avaliação física' => 'avaliacao', 'Personal trainer' => 'personal',
        'Aula experimental' => 'aula', 'Consulta com nutricionista' => 'consulta',
        'Treino acompanhado' => 'personal', 'Reavaliação física' => 'avaliacao'];
}

function bo_agenda_slots(mysqli $conn, string $start, string $end): array
{
    $now = bo_agenda_now();
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $now->getTimezone());
    $last = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $now->getTimezone());
    if (!$first || !$last || $first->format('Y-m-d') !== $start || $last->format('Y-m-d') !== $end || $first > $last || $first->diff($last)->days > 31) return [];
    $professionals = $conn->query("SELECT id_profissional, nome, especialidade FROM cadastro_profissional WHERE status = 'ativo' ORDER BY nome")->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare("SELECT id_usuario, id_profissional, data_evento, hora_inicio, hora_fim FROM agendamento WHERE status <> 'cancelado' AND data_evento BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $stmt = $conn->prepare("SELECT d.*, p.nome AS profissional, p.especialidade FROM disponibilidade_profissional d JOIN cadastro_profissional p ON p.id_profissional = d.id_profissional WHERE p.status = 'ativo' AND d.data_evento BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $slots = [];
    $overrides = [];
    foreach ($existing as $slot) {
        $overrides[$slot['data_evento']][$slot['id_profissional']] = true;
        if ($slot['status'] !== 'disponivel') continue;
        $slot['key'] = 'db:' . $slot['id_disponibilidade'];
        $slot['tipo'] = bo_agenda_types()[$slot['modalidade']] ?? 'outro';
        $slots[] = $slot;
    }
    $hours = [1 => [8,9,10,14,15,17,18], 2 => [9,10,11,15,16,18], 3 => [8,10,14,16,19], 4 => [9,11,15,17,18], 5 => [8,9,14,16,18], 6 => [8,9,10,11], 0 => []];
    for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
        $date = $day->format('Y-m-d');
        if ($date < $now->format('Y-m-d')) continue;
        foreach ($professionals as $professional) {
            $id = (int) $professional['id_profissional'];
            if (isset($overrides[$date][$id])) continue;
            foreach (bo_agenda_types() as $label => $type) {
                foreach ($hours[(int) $day->format('w')] as $hour) {
                    $time = sprintf('%02d:00:00', $hour);
                    $slots[] = ['key' => 'sim:' . $date . ':' . $id . ':' . array_search($label, array_keys(bo_agenda_types()), true) . ':' . $hour,
                        'id_profissional' => $id, 'profissional' => $professional['nome'], 'especialidade' => $professional['especialidade'],
                        'modalidade' => $label, 'tipo' => $type, 'data_evento' => $date, 'hora_inicio' => $time,
                        'hora_fim' => sprintf('%02d:00:00', $hour + 1), 'local' => 'ONE FIT — simulação'];
                }
            }
        }
    }
    return array_values(array_filter($slots, static function ($slot) use ($now, $bookings) {
        if ($slot['data_evento'] . ' ' . $slot['hora_inicio'] <= $now->format('Y-m-d H:i:s')) return false;
        foreach ($bookings as $booking) {
            if ($booking['data_evento'] === $slot['data_evento'] &&
                ((int) $booking['id_profissional'] === (int) $slot['id_profissional'] || (int) $booking['id_usuario'] === (int) ($_SESSION['id_usuario'] ?? 0)) &&
                $booking['hora_inicio'] < $slot['hora_fim'] && ($booking['hora_fim'] ?: '23:59:59') > $slot['hora_inicio']) return false;
        }
        return true;
    }));
}

```

## ARQUIVO: pages/dashboard/includes/db-data.php

CÓDIGO COMPLETO:

```php
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
                   m.id_matricula, m.data_inicio, m.data_fim, m.id_plano, pl.nome AS plano_nome
            FROM usuarios u
            LEFT JOIN matricula m ON m.id_matricula = (
                SELECT id_matricula FROM matricula
                WHERE id_usuario = u.id_usuario
                ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1
            )
            LEFT JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
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
    $planosAtivosOptions = array_values(array_map(
        static fn(array $p): string => $p['nome'],
        array_filter($planos, static fn(array $p): bool => $p['status'] === 'ativo')
    ));

    // Tela "Modalidades"
    $modalidadesAdm = [];
    if ($r = $conn->query('SELECT id_modalidade, nome, descricao, icone, status FROM modalidades ORDER BY nome')) {
        while ($row = $r->fetch_assoc()) {
            $modalidadesAdm[] = [
                'id' => (int) $row['id_modalidade'],
                'nome' => $row['nome'],
                'descricao' => $row['descricao'],
                'icone' => $row['icone'],
                'status' => $row['status'],
            ];
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

    $categoriasAtivasOptions = [];
    if ($r = $conn->query("SELECT nome FROM categorias WHERE status = 'ativo' ORDER BY nome")) {
        while ($row = $r->fetch_assoc()) {
            $categoriasAtivasOptions[] = $row['nome'];
        }
    }

    $vendedorProdutos = bo_carregar_produtos_vendedor($conn, $idUsuarioLogado);
    $vendedorProdutosResumo = [
        'total' => count($vendedorProdutos),
        'disponiveis' => count(array_filter($vendedorProdutos, static fn(array $p): bool => $p['status'] === 'disponivel')),
        'indisponiveis' => count(array_filter($vendedorProdutos, static fn(array $p): bool => $p['status'] === 'indisponivel')),
    ];
    $vendedorVendas = bo_carregar_vendas_vendedor($conn, $idUsuarioLogado);
    // Só para exibir o nome da transportadora escolhida em cada venda — o
    // cadastro de transportadoras em si é exclusivo do admin (globais).
    $transportadorasAtivas = array_values(array_filter(
        bo_carregar_transportadoras($conn),
        static fn(array $t): bool => $t['status'] === 'ativo'
    ));
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
        . ($idVendedor !== null ? ' WHERE pi.id_vendedor = ?' : '')
        . ' ORDER BY pe.data_pedido DESC';
    $stmt = $conn->prepare($sql);
    if ($idVendedor !== null) {
        $stmt->bind_param('i', $idVendedor);
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
            'statusLogisticaLabel' => $statusLogisticaLabel[$row['status_logistica']] ?? ucfirst($row['status_logistica']),
            'codigoRastreio' => $row['codigo_rastreio'],
            'data' => date('d/m/Y H:i', strtotime($row['data_pedido'])),
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
            'dataSolicitacao' => date('d/m/Y H:i', strtotime($row['data_solicitacao'])),
            'dataAnalise' => $row['data_analise'] ? date('d/m/Y H:i', strtotime($row['data_analise'])) : null,
            'dataConclusao' => $row['data_conclusao'] ? date('d/m/Y H:i', strtotime($row['data_conclusao'])) : null,
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

```

## ARQUIVO: pages/dashboard/components/section-aluno.php

CÓDIGO COMPLETO:

```php
<?php
/**
 * components/section-aluno.php
 * Telas do perfil Aluno. Depende de includes/mock-data.php:
 *   $alunoPerfil, $alunoHistorico, $alunoCashbackHistorico, $alunoPedidos,
 *   $alunoPedidosHistorico, $alunoTreino, $alunoAgendaDisponiveis
 */
?>

<!-- ===== ALUNO · Perfil (dados cadastrais + avaliação física/IMC) ===== -->
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

    <!-- Bloco 1: dados cadastrais -->
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

    <!-- Bloco 2: avaliação física + cálculo de IMC (ver backoffice.js > boCalcularIMC) -->
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

<!-- ===== ALUNO · Histórico (pagamentos de mensalidade) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="historico">
    <div class="bo-page-title">
        <div>
            <h1>Histórico</h1>
            <p>Histórico de pagamentos e movimentações.</p>
        </div>
        <!-- Abre o modal fixo de pagamento (components/modal-pagar-plano.php) -->
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

<!-- ===== ALUNO · Cashback (saldo e extrato de crédito/débito) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="cashback">
    <div class="bo-page-title">
        <div>
            <h1>Cashback</h1>
            <p>Saldo disponível e histórico de movimentações.</p>
        </div>
        <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/marketplace/marketplace.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-bo-gold">
            <i class="bi bi-wallet2"></i> Usar Cashback
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo de cashback</div>
                <div class="bo-card-value"><?php echo bo_money($alunoCashbackSaldo); ?></div>
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

<!-- ===== ALUNO · Minhas compras (pedidos em andamento e histórico) ===== -->
<?php $perfilCompras = 'aluno'; $comprasPedidos = $alunoPedidos ?? []; $comprasHistorico = $alunoPedidosHistorico ?? []; require __DIR__ . '/section-compras.php'; ?>

<!-- ===== ALUNO · Treino (ficha de exercícios) ===== -->
<?php require __DIR__ . '/section-treino.php'; ?>

<!-- ===== ALUNO · Minha agenda (calendário + horários disponíveis com profissionais) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1>Minha agenda</h1>
            <p>Clique num dia com horários disponíveis para agendar com um profissional.</p>
        </div>
    </div>

    <?php
    $boAgendaMesTs = strtotime($alunoAgendaMes . '-01');
    $boAgendaMesAnterior = date('Y-m', strtotime('-1 month', $boAgendaMesTs));
    $boAgendaMesSeguinte = date('Y-m', strtotime('+1 month', $boAgendaMesTs));
    $boAgendaLinkBase = BASE_URL . 'pages/dashboard/dashboard.php?section=agenda';
    $boAgendaPrimeiroDiaSemana = (int) date('w', $boAgendaMesTs); // 0 (domingo) .. 6 (sábado)
    $boAgendaTotalDias = (int) date('t', $boAgendaMesTs);
    $boMesesPtBr = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
        7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];
    $boAgendaMesLabel = $boMesesPtBr[(int) date('n', $boAgendaMesTs)] . ' de ' . date('Y', $boAgendaMesTs);
    $boTiposAgendamento = ['aula' => 'Aula', 'personal' => 'Personal', 'avaliacao' => 'Avaliação física', 'consulta' => 'Consulta', 'reuniao' => 'Reunião', 'outro' => 'Agendamento'];
    ?>
    <div class="bo-calendar">
        <div class="bo-calendar-nav">
            <a href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $boAgendaMesAnterior, ENT_QUOTES, 'UTF-8'); ?>" class="bo-calendar-nav-btn" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></a>
            <span class="bo-calendar-mes-atual"><?php echo htmlspecialchars($boAgendaMesLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $boAgendaMesSeguinte, ENT_QUOTES, 'UTF-8'); ?>" class="bo-calendar-nav-btn" aria-label="Próximo mês"><i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="bo-calendar-grid">
            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $boDiaSemanaLabel): ?>
                <div class="bo-calendar-weekday"><?php echo $boDiaSemanaLabel; ?></div>
            <?php endforeach; ?>

            <?php for ($boEspaco = 0; $boEspaco < $boAgendaPrimeiroDiaSemana; $boEspaco++): ?>
                <div class="bo-calendar-day is-outro-mes"></div>
            <?php endfor; ?>

            <?php for ($boDiaNum = 1; $boDiaNum <= $boAgendaTotalDias; $boDiaNum++): ?>
                <?php
                $boDataStr = sprintf('%s-%02d', $alunoAgendaMes, $boDiaNum);
                $boTemVaga = isset($alunoAgendaDiasDisponiveis[$boDataStr]);
                $boEventosDoDia = $alunoAgendaEventosPorDia[$boDataStr] ?? [];
                $boSelecionado = $alunoAgendaDiaSelecionado === $boDataStr;
                $boClasses = 'bo-calendar-day' . ($boDataStr < bo_agenda_now()->format('Y-m-d') ? ' is-passado' : '') . ($boTemVaga ? ' is-disponivel' : '') . ($boSelecionado ? ' is-selecionado' : '');
                $boHref = $boAgendaLinkBase . '&mes=' . $alunoAgendaMes . ($boTemVaga ? '&dia=' . $boDataStr : '');
                $boTag = $boTemVaga ? 'a' : 'div';
                ?>
                <<?php echo $boTag; ?><?php if ($boTemVaga): ?> href="<?php echo htmlspecialchars($boHref, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?> class="<?php echo $boClasses; ?>">
                    <div class="bo-calendar-day-topo">
                        <span class="bo-calendar-day-num"><?php echo str_pad((string) $boDiaNum, 2, '0', STR_PAD_LEFT); ?></span>
                        <?php if ($boTemVaga): ?><span class="bo-calendar-badge-livre">Livre</span><?php endif; ?>
                        <?php if ($boEventosDoDia): ?><span class="bo-calendar-badge-evento"><?php echo count($boEventosDoDia); ?> evento(s)</span><?php endif; ?>
                    </div>
                    <?php foreach (array_slice($boEventosDoDia, 0, 2) as $boEvento): ?>
                        <div class="bo-calendar-evento-preview">
                            <strong><?php echo htmlspecialchars($boEvento['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo $boEvento['hora']; ?> · <?php echo htmlspecialchars(mb_strtoupper($boTiposAgendamento[$boEvento['tipo']] ?? $boEvento['tipo'], 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </<?php echo $boTag; ?>>
            <?php endfor; ?>
        </div>
        <p class="bo-calendar-legenda">Eventos incluídos automaticamente quando você agenda aulas ou avaliações.</p>
    </div>

    <?php if ($alunoAgendaDiaSelecionado): ?>
        <div class="bo-section-heading">Horários disponíveis em <?php echo date('d/m/Y', strtotime($alunoAgendaDiaSelecionado)); ?></div>
        <?php if (!$alunoAgendaSlots): ?>
            <p>Nenhum horário disponível nesse dia.</p>
        <?php else: ?>
            <form class="bo-calendar bo-agenda-booking bo-filters" method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda.php">
                <p>Horários de demonstração com profissionais cadastrados. A confirmação será salva na sua agenda.</p>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="secao" value="agenda">
                <input type="hidden" name="data_evento" value="<?php echo htmlspecialchars($alunoAgendaDiaSelecionado, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="bo-agenda-fields">
                    <label>Tipo de agendamento<select class="form-select" data-agenda-type required></select></label>
                    <label>Profissional<select class="form-select" data-agenda-professional required></select></label>
                </div>
                <fieldset><legend>Horários disponíveis</legend><div data-agenda-times class="bo-agenda-times"></div></fieldset>
                <p data-agenda-empty hidden>Nenhum horário disponível para esta seleção.</p>
                <a class="btn-bo-outline" href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $alunoAgendaMes, ENT_QUOTES, 'UTF-8'); ?>">Cancelar</a>
                <button class="btn-bo-gold" type="submit" disabled>Confirmar agendamento</button>
                <script type="application/json" data-agenda-slots><?php echo json_encode($alunoAgendaSlots, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
                <noscript>Ative o JavaScript para selecionar o atendimento e o horário.</noscript>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bo-section-heading">Meus agendamentos</div>
    <?php if (!$alunoAgendaMeusAgendamentos): ?>
        <p>Você ainda não tem agendamentos confirmados.</p>
    <?php endif; ?>
    <?php foreach ($alunoAgendaMeusAgendamentos as $boAg): ?>
        <div class="bo-agenda-card">
            <div>
                <div class="bo-agenda-title"><?php echo htmlspecialchars($boAg['titulo'] ?: ($boTiposAgendamento[$boAg['tipo']] ?? ucfirst($boAg['tipo'])), ENT_QUOTES, 'UTF-8'); ?><?php echo $boAg['profissional'] ? ' com ' . htmlspecialchars($boAg['profissional'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                <div class="bo-agenda-sub"><?php echo date('d/m/Y', strtotime($boAg['data_evento'])) . ' às ' . substr($boAg['hora_inicio'], 0, 5); ?></div>
            </div>
            <span>Status: <?php echo htmlspecialchars(['agendado' => 'Confirmado', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado', 'concluido' => 'Concluído', 'faltou' => 'Não compareceu'][$boAg['status']] ?? $boAg['status'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if (in_array($boAg['status'], ['agendado', 'confirmado'], true)): ?>
            <form data-agenda-cancel method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/agenda.php', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="secao" value="agenda">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id_agendamento" value="<?php echo (int) $boAg['id_agendamento']; ?>">
                <button type="button" data-cancel-open class="btn-bo-outline" style="padding:8px 16px;">Cancelar</button>
                <div data-cancel-confirm hidden>
                    <p>Tem certeza que deseja cancelar este agendamento?</p>
                    <button type="button" data-cancel-back class="btn-bo-outline">Voltar</button>
                    <button type="submit" class="btn-bo-gold">Confirmar cancelamento</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<script src="<?php echo BASE_URL; ?>assets/js/agenda.js" defer></script>

```

## ARQUIVO: pages/dashboard/funcionalidades/agenda.php

CÓDIGO COMPLETO:

```php
<?php
$bo_papeis_permitidos = ['aluno'];
require __DIR__ . '/_shared.php';
require_once __DIR__ . '/../includes/agenda-service.php';
bo_check_csrf();
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$locked = false;
try {
    // Serializa reservas e a geração de IDs do dump sem AUTO_INCREMENT.
    $locked = (int) $conn->query("SELECT GET_LOCK('onefit_agenda_reserva', 10) AS acquired")->fetch_assoc()['acquired'] === 1;
    if (!$locked) throw new RuntimeException('Agenda ocupada. Tente novamente.');
    $conn->begin_transaction();
    if (bo_str('acao') === 'cancelar') {
        $id = (int) bo_str('id_agendamento');
        $stmt = $conn->prepare("SELECT * FROM agendamento WHERE id_agendamento = ? AND id_usuario = ? FOR UPDATE");
        $stmt->bind_param('ii', $id, $idUsuario);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        if (!$booking || !in_array($booking['status'], ['agendado', 'confirmado'], true)) throw new RuntimeException('Este agendamento não pode ser cancelado.');
        $stmt = $conn->prepare("UPDATE agendamento SET status = 'cancelado' WHERE id_agendamento = ? AND id_usuario = ?");
        $stmt->bind_param('ii', $id, $idUsuario);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'disponivel' WHERE id_profissional = ? AND data_evento = ? AND hora_inicio = ? AND status = 'ocupado'");
        $stmt->bind_param('iss', $booking['id_profissional'], $booking['data_evento'], $booking['hora_inicio']);
        $stmt->execute();
        $message = 'Agendamento cancelado.';
    } else {
        $date = bo_str('data_evento');
        $key = bo_str('slot');
        // Aceita também os formulários antigos de horários cadastrados.
        if (!$key && (int) bo_str('id_disponibilidade')) {
            $id = (int) bo_str('id_disponibilidade');
            $stmt = $conn->prepare('SELECT data_evento FROM disponibilidade_profissional WHERE id_disponibilidade = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $date = $stmt->get_result()->fetch_assoc()['data_evento'] ?? '';
            $key = 'db:' . $id;
        }
        $slot = null;
        foreach (bo_agenda_slots($conn, $date, $date) as $candidate) {
            if ($candidate['key'] === $key) { $slot = $candidate; break; }
        }
        if (!$slot) throw new RuntimeException('Esse horário não está mais disponível. Escolha outro horário.');
        if (isset($slot['id_disponibilidade'])) {
            $stmt = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'ocupado' WHERE id_disponibilidade = ? AND status = 'disponivel'");
            $stmt->bind_param('i', $slot['id_disponibilidade']);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Esse horário acabou de ser reservado.');
        }
        $id = (int) $conn->query('SELECT COALESCE(MAX(id_agendamento), 0) + 1 AS id FROM agendamento')->fetch_assoc()['id'];
        $stmt = $conn->prepare("INSERT INTO agendamento (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento, hora_inicio, hora_fim, local, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmado')");
        $stmt->bind_param('iiissssss', $id, $idUsuario, $slot['id_profissional'], $slot['modalidade'], $slot['tipo'], $slot['data_evento'], $slot['hora_inicio'], $slot['hora_fim'], $slot['local']);
        $stmt->execute();
        $stmt = $conn->prepare("INSERT INTO profissional_aluno (id_profissional, id_aluno, status) VALUES (?, ?, 'ativo') ON DUPLICATE KEY UPDATE status = 'ativo'");
        $stmt->bind_param('ii', $slot['id_profissional'], $idUsuario);
        $stmt->execute();
        $message = 'Agendamento realizado com sucesso! Data: ' . date('d/m/Y', strtotime($date)) . ' • Horário: ' . substr($slot['hora_inicio'], 0, 5) . ' • Atendimento: ' . $slot['modalidade'] . ' • Profissional: ' . $slot['profissional'];
    }
    $conn->commit();
    bo_flash('success', $message);
} catch (Throwable $error) {
    $conn->rollback();
    bo_flash('error', $error instanceof RuntimeException && !($error instanceof mysqli_sql_exception) ? $error->getMessage() : 'Não foi possível salvar o agendamento. Tente novamente.');
} finally {
    if ($locked) $conn->query("SELECT RELEASE_LOCK('onefit_agenda_reserva')");
}
bo_redirect('agenda');

```

## ARQUIVO: assets/js/agenda.js

CÓDIGO COMPLETO:

```js
/* Seleção visual; disponibilidade e validação definitiva pertencem ao PHP. */
document.querySelectorAll('.bo-agenda-booking').forEach(form => {
    const slots = JSON.parse(form.querySelector('[data-agenda-slots]').textContent);
    const type = form.querySelector('[data-agenda-type]');
    const professional = form.querySelector('[data-agenda-professional]');
    const times = form.querySelector('[data-agenda-times]');
    const submit = form.querySelector('[type="submit"]');
    [...new Set(slots.map(slot => slot.modalidade))].forEach(label => type.add(new Option(label, label)));
    function renderTimes() {
        times.replaceChildren();
        submit.disabled = true;
        const available = slots.filter(slot => slot.modalidade === type.value && String(slot.id_profissional) === professional.value);
        available.sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio)).forEach(slot => {
            const label = document.createElement('label');
            label.className = 'bo-agenda-time';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'slot';
            radio.value = slot.key;
            radio.required = true;
            radio.addEventListener('change', () => { submit.disabled = false; });
            const text = document.createElement('span');
            text.textContent = slot.hora_inicio.slice(0, 5);
            label.append(radio, text);
            times.append(label);
        });
        form.querySelector('[data-agenda-empty]').hidden = available.length > 0;
    }
    function renderProfessionals() {
        professional.replaceChildren();
        const options = new Map();
        slots.filter(slot => slot.modalidade === type.value).forEach(slot => options.set(slot.id_profissional, slot.profissional));
        options.forEach((name, id) => professional.add(new Option(name, id)));
        renderTimes();
    }
    type.addEventListener('change', renderProfessionals);
    professional.addEventListener('change', renderTimes);
    form.addEventListener('submit', () => { submit.disabled = true; });
    renderProfessionals();
});
document.querySelectorAll('[data-agenda-cancel]').forEach(form => {
    const open = form.querySelector('[data-cancel-open]');
    const confirmation = form.querySelector('[data-cancel-confirm]');
    open.addEventListener('click', () => {
        open.hidden = true;
        confirmation.hidden = false;
        form.querySelector('[data-cancel-back]').focus();
    });
    form.querySelector('[data-cancel-back]').addEventListener('click', () => {
        confirmation.hidden = true;
        open.hidden = false;
        open.focus();
    });
});

```

## ARQUIVO: assets/css/dashboard.css

CÓDIGO COMPLETO:

```css
/* =========================================================================
   dashboard.css
   Estilos do painel da ONE FIT.
   
     1. Variáveis de tema (dourado escuro = padrão / claro = data-theme="light")
     2. Base (body)
     3. Header (topo fixo + busca)
     4. Sidebar (menu lateral + marca)
     5. Área principal / título de página / aviso (notice)
     6. Cards (indicador simples e "metric card" com ícone)
     7. Botões
     8. Filtros + Tabela
     9. Modais
    10. Tela "em construção" (stub) e painel vazio (empty panel)
    11. Listas simples
    12. Agenda
    13. Bloco de perfil (aluno) + IMC
    14. Toast
    15. Pagamento Pix
    16. Cartão de configurações (troca de tema)
    17. Backdrop mobile + visibilidade das seções
    18. Responsivo
    19. Calendário da agenda (aluno)
   ========================================================================= */

/* =========================================================================
   1. VARIÁVEIS DE TEMA
   ========================================================================= */

/* Tema padrão: dourado sobre fundo escuro */
:root {
    --bg: #0e0c0a;
    --surface: #1e1a15;
    --surface-2: #241f18;
    --border: rgba(243, 237, 226, 0.12);
    --text: #f3ede2;
    --text-muted: #b0a896;
    --gold: #d4af37;
    --gold-bright: #f4c430;
    --gold-dim: #8a6b21;
    --danger: #e42e2e;
    --success: #4caf50;

    --sidebar-w: 268px;
    --header-h: 76px;
    --header-bg: rgba(14, 12, 10, 0.94);
    --sidebar-bg: #12100e;
}

/* Tema claro: mesma identidade visual (dourado), mas em cima de fundo
   claro/branco — por isso o dourado fica mais escuro/saturado aqui
   (senão perde contraste em cima de branco), e não é só o dark invertido. */
html[data-theme="light"] {
    --bg: #f8f5f0;
    --surface: #fffdfa;
    --surface-2: #f5f0e8;
    --border: #e8dfd3;
    --text: #29251f;
    --text-muted: #766e63;
    --gold: #b98618;
    --gold-bright: #a87308;
    --gold-dim: #e4c98d;
    --danger: #c11d1d;
    --success: #2f8132;

    --header-bg: #fffdfa;
    --sidebar-bg: #fcf8f1;
}

/* No tema claro, o conteúdo fica leve e neutro, enquanto a navegação segue
   em cinza-grafite para criar a mesma hierarquia visual da referência. */
html[data-theme="light"] .bo-header { border-color: #383c43; }
html[data-theme="light"] .bo-header-search { background: #1d2025; border-color: #40454f; color: var(--gold); }
html[data-theme="light"] .bo-header-search input { color: #f5f6f7; }
html[data-theme="light"] .bo-header-search input::placeholder,
html[data-theme="light"] .bo-header-search kbd { color: #aeb4be; }
html[data-theme="light"] .bo-header-search kbd { background: #30343b; border-color: #484d56; }
html[data-theme="light"] .bo-sidebar { border-color: #383c43; }
html[data-theme="light"] .bo-side-brand { border-color: #383c43; }
html[data-theme="light"] .bo-nav-item { color: #bac0c9; }
html[data-theme="light"] .bo-nav-item:hover { background: #30343a; color: #ffffff; }
html[data-theme="light"] .bo-nav-item.active { color: #f5c76e; }
html[data-theme="light"] .bo-sidebar .bo-nav-item i { color: #b7bdc7; }
html[data-theme="light"] .bo-sidebar .bo-nav-item.active i { color: #f5c76e; }
html[data-theme="light"] .bo-sidebar-toggle,
html[data-theme="light"] .bo-user .btn-bo-outline { color: #f5f6f7; }
html[data-theme="light"] .bo-user .btn-bo-outline { border-color: #4a4e56; }
html[data-theme="light"] .bo-header .btn-bo-icon { background: #30343a; border-color: #4a4e56; color: #c8cdd4; }
html[data-theme="light"] .bo-header .btn-bo-icon:hover { border-color: var(--gold); color: var(--gold-bright); }

/* Ajustes visuais do tema claro: superfície quente e neutra, sem glow. */
html[data-theme="light"] body { background: var(--bg); }
html[data-theme="light"] .bo-header { border-color: var(--border); box-shadow: none; }
html[data-theme="light"] .bo-sidebar { border-color: #e5d4b2; }
html[data-theme="light"] .bo-side-brand { border-color: var(--border); }
html[data-theme="light"] .bo-header-search { background: #fffdfa; border-color: var(--border); color: var(--gold); }
html[data-theme="light"] .bo-header-search input { color: var(--text); }
html[data-theme="light"] .bo-header-search input::placeholder,
html[data-theme="light"] .bo-header-search kbd { color: var(--text-muted); }
html[data-theme="light"] .bo-header-search kbd { background: var(--surface-2); border-color: var(--border); }
html[data-theme="light"] .bo-header-search:focus-within { box-shadow: none; }
html[data-theme="light"] .bo-nav-item { color: #4b453d; }
html[data-theme="light"] .bo-nav-item:hover { background: #f6efe3; color: var(--text); }
html[data-theme="light"] .bo-nav-item.active { background: #fff7e7; border-color: #edcf91; color: var(--gold-bright); }
html[data-theme="light"] .bo-sidebar .bo-nav-item i { color: #8c6d31; }
html[data-theme="light"] .bo-sidebar .bo-nav-item.active i { color: var(--gold-bright); }
html[data-theme="light"] .bo-sidebar-toggle { color: var(--text); }
html[data-theme="light"] .bo-user .btn-bo-outline { background: #fffdfa; border-color: var(--border); color: var(--text); }
html[data-theme="light"] .bo-header .btn-bo-icon { background: #fffdfa; border-color: var(--border); color: #75581d; }
html[data-theme="light"] .bo-header .btn-bo-icon:hover { border-color: var(--gold); color: var(--gold-bright); }
html[data-theme="light"] .bo-metric-card,
html[data-theme="light"] .bo-data-panel,
html[data-theme="light"] .bo-settings-card,
html[data-theme="light"] .bo-card,
html[data-theme="light"] .bo-table-wrap,
html[data-theme="light"] .bo-profile-block { background: var(--surface); box-shadow: none; }
html[data-theme="light"] .bo-notice { background: #fff9ed; border-color: #efd9aa; }
html[data-theme="light"] .bo-theme-choice { background: #fffdfa; }
html[data-theme="light"] .bo-theme-choice:hover,
html[data-theme="light"] .bo-theme-choice.active { background: #fff9ed; }
html[data-theme="light"] .bo-toast { box-shadow: none; }
html[data-theme="light"] .bo-filters .form-control:focus,
html[data-theme="light"] .bo-filters .form-select:focus,
html[data-theme="light"] .bo-modal .form-control:focus,
html[data-theme="light"] .bo-modal .form-select:focus { box-shadow: none; }

/* =========================================================================
   2. BASE
   ========================================================================= */
* {
    box-sizing: border-box;
}

body {
    background: radial-gradient(circle at 78% -20%, rgba(212, 175, 55, 0.13), transparent 35%), var(--bg);
    color: var(--text);
    font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    min-height: 100vh;
}

/* =========================================================================
   3. HEADER
   Barra fixa no topo, deslocada para começar depois da sidebar (a sidebar
   ocupa a altura inteira da tela, ver bloco 4). Contém: botão de menu
   mobile, busca rápida e o seletor/rótulo de perfil (ver components/header.php).
   ========================================================================= */
.bo-header {
    position: fixed;
    top: 0;
    left: var(--sidebar-w);
    right: 0;
    height: var(--header-h);
    background: var(--header-bg);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border);
    z-index: 1030;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.bo-sidebar-toggle {
    background: none;
    border: none;
    color: var(--text);
    font-size: 22px;
    cursor: pointer;
    padding: 4px 8px;
}

/* Campo de busca rápida no header (opcional — só aparece se o HTML tiver
   um .bo-header-search; não é obrigatório usar em toda página) */
.bo-header-search {
    align-items: center;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--gold);
    display: flex;
    gap: 9px;
    height: 42px;
    padding: 0 9px 0 12px;
    width: min(350px, 40vw);
}

.bo-header-search-wrap {
    position: relative;
}

.bo-header-search input {
    background: transparent;
    border: 0;
    color: var(--text);
    font: inherit;
    font-size: 13px;
    min-width: 0;
    outline: 0;
    width: 100%;
}

.bo-header-search input::placeholder {
    color: var(--text-muted);
}

.bo-header-search:focus-within {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, .12);
}

.bo-header-search kbd {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 5px;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 700;
    padding: 3px 5px;
    white-space: nowrap;
}

.bo-search-results {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 16px 34px rgba(0, 0, 0, .24);
    left: 0;
    margin-top: 8px;
    max-height: min(390px, calc(100vh - 105px));
    min-width: min(350px, 78vw);
    overflow-y: auto;
    padding: 7px;
    position: absolute;
    top: 100%;
    transform: translateY(-5px);
    transition: opacity .16s ease, transform .16s ease;
    width: min(440px, 78vw);
    z-index: 1040;
}

.bo-search-results[hidden] {
    display: none;
}

.bo-search-group-label {
    color: var(--text-muted);
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    padding: 8px 10px 5px;
    text-transform: uppercase;
}

.bo-search-result {
    align-items: center;
    background: transparent;
    border: 0;
    border-radius: 8px;
    color: var(--text);
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px;
    text-align: left;
    width: 100%;
}

.bo-search-result:hover,
.bo-search-result.is-active {
    background: rgba(212, 175, 55, .11);
    color: var(--text);
}

.bo-search-result > i {
    color: var(--gold-bright);
    font-size: 17px;
    width: 19px;
}

.bo-search-result strong,
.bo-search-result small {
    display: block;
}

.bo-search-result strong {
    font-size: 13px;
}

.bo-search-result small,
.bo-search-empty {
    color: var(--text-muted);
    font-size: 12px;
}

.bo-search-empty {
    display: block;
    padding: 18px 10px;
    text-align: center;
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
    background: linear-gradient(135deg, var(--gold-bright), var(--gold-dim));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #14110e;
    border: 0;
    cursor: pointer;
    flex-shrink: 0;
}

.bo-avatar:focus-visible,
.bo-search-result:focus-visible {
    outline: 3px solid rgba(212, 175, 55, .42);
    outline-offset: 2px;
}

.bo-notifications-wrap {
    position: relative;
}

.bo-notifications-toggle {
    position: relative;
    font-size: 18px;
}

.bo-notifications-count {
    position: absolute;
    top: -5px;
    right: -6px;
    min-width: 19px;
    padding: 2px 4px;
    border-radius: 20px;
    background: #b42318;
    color: #fff;
    border: 2px solid var(--surface);
    font-size: 10px;
    line-height: 13px;
}

.bo-notifications-panel {
    position: absolute;
    top: calc(100% + 9px);
    right: -50px;
    width: min(360px, calc(100vw - 36px));
    max-height: calc(100dvh - var(--header-h) - 24px);
    overflow-y: auto;
    overscroll-behavior: contain;
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, .24);
    z-index: 1041;
    transform-origin: top right;
}

.bo-notifications-panel:not([hidden]) {
    animation: bo-notifications-enter .22s ease-out;
}

@keyframes bo-notifications-enter {
    from {
        opacity: 0;
        transform: translateY(-8px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .bo-notifications-panel:not([hidden]) {
        animation: none;
    }
}

.bo-notifications-heading {
    padding: 18px 18px 12px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.bo-notifications-heading h2 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
}

.bo-notifications-heading button {
    background: transparent;
    border: 0;
    color: var(--gold-bright);
    font: inherit;
    font-size: 12px;
    padding: 6px 8px;
    border-radius: 6px;
    cursor: pointer;
}

.bo-notifications-heading button:hover:not(:disabled) {
    background: var(--surface-2);
}

.bo-notifications-heading button:disabled {
    color: var(--text-muted);
    cursor: default;
}

.bo-notifications-heading button:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 3px;
}

.bo-notifications-feedback,
.bo-notifications-empty {
    padding: 0 18px 16px;
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
}

.bo-notifications-feedback {
    font-size: 11px;
    line-height: 1.5;
}

.bo-notifications-empty {
    padding: 28px 18px;
    text-align: center;
}

.bo-notifications-list {
    list-style: none;
    padding: 0 8px 8px;
    margin: 0;
}

.bo-notifications-item {
    position: relative;
    padding: 14px 14px 14px 28px;
    border-radius: 10px;
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.6;
}

.bo-notifications-item.is-unread {
    background: var(--surface-2);
}

.bo-notifications-item.is-unread::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 21px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--gold);
}

.bo-notifications-item p {
    margin: 0 0 6px;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.bo-notifications-item-title {
    display: block;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.bo-notifications-item-link {
    display: block;
    width: fit-content;
    margin-top: 8px;
    color: var(--gold-bright);
}

.bo-notifications-item-link:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 3px;
}

.bo-notifications-item time {
    font-size: 11px;
    color: var(--text-muted);
}

@media (max-width: 560px) {
    .bo-notifications-panel { right: -46px; }
    .bo-header-search { max-width: calc(100vw - 180px); }
}

.bo-user-menu-wrap {
    position: relative;
}

.bo-user-menu {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    box-shadow: 0 14px 28px rgba(0, 0, 0, .22);
    min-width: 175px;
    opacity: 0;
    overflow: hidden;
    padding: 5px;
    pointer-events: none;
    position: absolute;
    right: 0;
    top: calc(100% + 9px);
    transform: translateY(-5px);
    transition: opacity .16s ease, transform .16s ease;
    visibility: hidden;
    z-index: 1040;
}

.bo-user-menu.is-open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
    visibility: visible;
}

.bo-user-menu a {
    align-items: center;
    border-radius: 7px;
    color: var(--text);
    display: flex;
    font-size: 13px;
    gap: 9px;
    padding: 9px 10px;
    text-decoration: none;
}

.bo-user-menu a:hover {
    background: var(--surface-2);
    color: var(--gold-bright);
}

.bo-user-menu .bo-user-menu-logout:hover {
    color: var(--danger);
}

/* Dropdown "Administrador / Profissional / Aluno" no header — só existe
   (e só funciona) para o perfil admin; ver components/header.php e
   backoffice.js > boRenderPerfilMenu() */
.bo-perfil-menu.dropdown-menu {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 6px;
    min-width: 200px;
}

.bo-perfil-menu .dropdown-item {
    color: var(--text);
    border-radius: 6px;
    font-size: 14px;
    padding: 8px 12px;
}

.bo-perfil-menu .dropdown-item:hover {
    background: var(--surface-2);
    color: var(--gold-bright);
}

.bo-perfil-menu .dropdown-item.active {
    background: rgba(212, 175, 55, 0.15);
    color: var(--gold-bright);
}

/* =========================================================================
   4. SIDEBAR
   Ocupa a altura inteira da tela (inclusive atrás do header). Os itens de
   menu (.bo-nav-item) são montados dinamicamente pelo JS conforme o perfil
   ativo — ver backoffice.js > boRenderSidebar().
   ========================================================================= */
.bo-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: var(--sidebar-w);
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border);
    overflow-y: auto;
    padding: 22px 14px;
    z-index: 1025;
    transition: transform 0.3s ease;

    /* Esconde a barra de rolagem (Firefox / IE-Edge antigo) */
    scrollbar-width: none;
    -ms-overflow-style: none;
}

/* Esconde a barra de rolagem (Chrome / Edge / Safari) */
.bo-sidebar::-webkit-scrollbar {
    display: none;
}

/* Bloco de marca/logo no topo da sidebar (opcional — usar quando o logo
   ficar dentro do menu lateral em vez do header) */
.bo-side-brand {
    align-items: center;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    display: flex;
    gap: 10px;
    margin: -2px 0 20px;
    padding: 0 10px 20px;
    text-decoration: none;
}

.bo-side-brand img {
    height: 42px;
    object-fit: contain;
    width: 42px;
}

.bo-side-brand span {
    color: var(--gold);
    font-size: 16px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.bo-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    border-radius: 11px;
    color: var(--text-muted);
    text-decoration: none;
    margin-bottom: 5px;
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
    color: var(--gold-dim);
}

.bo-nav-item:hover {
    background: var(--surface-2);
    color: var(--text);
}

.bo-nav-item.active {
    background: linear-gradient(100deg, rgba(212, 175, 55, .20), rgba(212, 175, 55, .06));
    border-color: rgba(212, 175, 55, .4);
    color: var(--gold-bright);
}

.bo-nav-item.active i {
    color: var(--gold-bright);
}

/* =========================================================================
   5. ÁREA PRINCIPAL / TÍTULO DE PÁGINA / EYEBROW / NOTICE
   ========================================================================= */
.bo-main {
    margin-left: var(--sidebar-w);
    margin-top: var(--header-h);
    padding: 34px 38px 64px;
    min-height: calc(100vh - var(--header-h));
}

/* Cabeçalho de cada tela (título + descrição + botões de ação) */
.bo-page-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 26px;
    flex-wrap: wrap;
    gap: 12px;
}

.bo-page-title h1 {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.04em;
    text-transform: none;
    color: var(--text);
    margin: 0;
}

.bo-page-title p {
    color: var(--text-muted);
    font-size: 14px;
    max-width: 610px;
    margin: 4px 0 0;
}

.bo-page-title .bo-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Rótulo pequeno em destaque acima de um título (opcional) */
.bo-eyebrow {
    align-items: center;
    color: var(--gold);
    display: inline-flex;
    font-size: 12px;
    font-weight: 800;
    gap: 7px;
    letter-spacing: .08em;
    margin-bottom: 8px;
    text-transform: uppercase;
}

/* Aviso/banner de destaque (ex: "esta tela ainda usa dados de exemplo") */
.bo-notice {
    align-items: flex-start;
    background: linear-gradient(110deg, rgba(212, 175, 55, .13), rgba(212, 175, 55, .035));
    border: 1px solid rgba(212, 175, 55, .28);
    border-radius: 15px;
    display: flex;
    gap: 13px;
    margin-bottom: 18px;
    padding: 17px 19px;
    /* Some sozinho após alguns segundos: sem isso a mensagem fica presa no
       topo ao trocar de aba, já que o JS só alterna a section visível e
       nunca remove este bloco (ele fica fora das <section data-section>). */
    animation: bo-notice-fade 6s ease-in forwards;
    overflow: hidden;
}

@keyframes bo-notice-fade {
    0% { opacity: 1; max-height: 200px; margin-bottom: 18px; padding-top: 17px; padding-bottom: 17px; }
    80% { opacity: 1; max-height: 200px; margin-bottom: 18px; padding-top: 17px; padding-bottom: 17px; }
    100% { opacity: 0; max-height: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0; border-width: 0; pointer-events: none; }
}

.bo-notice>i {
    color: var(--gold-bright);
    font-size: 20px;
}

.bo-notice strong,
.bo-notice span {
    display: block;
}

.bo-notice strong {
    color: var(--text);
    font-size: 14px;
    margin-bottom: 3px;
}

.bo-notice span {
    color: var(--text-muted);
    font-size: 13px;
}

/* =========================================================================
   6. CARDS
   ========================================================================= */

/* Card simples de indicador (usado nos dashboards originais) */
.bo-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 22px;
    height: 100%;
}

.bo-card-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.bo-card-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 4px;
}

.bo-card-sub {
    font-size: 13px;
    color: var(--gold);
    font-weight: 600;
}

/* Grade de "metric cards" (versão com ícone, usada nos dashboards novos) */
.bo-metric-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 18px;
}

.bo-metric-card,
.bo-data-panel,
.bo-settings-card {
    background: color-mix(in srgb, var(--surface) 96%, transparent);
    border: 1px solid var(--border);
    border-radius: 16px;
}

.bo-metric-card {
    align-items: flex-start;
    display: flex;
    gap: 13px;
    min-height: 142px;
    padding: 20px;
}

.bo-metric-icon {
    align-items: center;
    background: rgba(212, 175, 55, .13);
    border: 1px solid rgba(212, 175, 55, .16);
    border-radius: 11px;
    color: var(--gold-bright);
    display: inline-flex;
    flex: 0 0 42px;
    font-size: 18px;
    height: 42px;
    justify-content: center;
    width: 42px;
}

.bo-metric-card .bo-card-label {
    display: block;
    margin: 2px 0 8px;
}

.bo-metric-card strong {
    color: var(--text);
    display: block;
    font-size: 27px;
    line-height: 1;
}

.bo-metric-card small {
    color: var(--text-muted);
    display: block;
    font-size: 11px;
    margin-top: 10px;
}

/* =========================================================================
   7. BOTÕES
   ========================================================================= */
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
    background: linear-gradient(120deg, var(--gold-dim), var(--gold) 45%, var(--gold-bright) 60%, var(--gold) 75%, var(--gold-dim));
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

.logout-button{
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    font-weight: 600;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: rgba(189, 5, 5, 0.301) !important;
    color: var(--text);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
    text-decoration: none;
}

.logout-button:hover{
    background: rgba(114, 3, 3, 0.301) !important;
    
}

.btn-bo-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    font-weight: 600;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
    text-decoration: none;
}

.btn-bo-outline:hover {
    border-color: var(--gold);
    color: var(--gold-bright);
}

.btn-bo-icon {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease;
}

.btn-bo-icon:hover {
    border-color: var(--gold);
    color: var(--gold-bright);
}

.btn-bo-icon.danger:hover {
    border-color: var(--danger);
    color: var(--danger);
}

/* =========================================================================
   8. FILTROS + TABELA
   ========================================================================= */
.bo-filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 18px;
}

.bo-filters .form-control,
.bo-filters .form-select {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
}

.bo-filters .form-control::placeholder {
    color: var(--text-muted);
}

.bo-filters .form-control:focus,
.bo-filters .form-select:focus {
    background: var(--surface-2);
    border-color: var(--gold);
    color: var(--text);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

.bo-filters .bo-daterange {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 13px;
}

.bo-table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
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
    color: var(--text-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border);
    padding: 14px 16px;
    text-align: left;
    white-space: nowrap;
}

.bo-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    vertical-align: middle;
}

.bo-table tbody tr:last-child td {
    border-bottom: none;
}

.bo-table tbody tr:hover {
    background: var(--surface-2);
}

/* Miniatura de imagem (foto de produto/profissional) dentro da tabela */
.bo-thumb {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    font-size: 16px;
    overflow: hidden;
}

.bo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Badge de status (ativo/inativo, disponível/indisponível) — ver helpers.php > bo_badge() */
.bo-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.bo-badge-active {
    background: rgba(76, 175, 80, 0.15);
    color: var(--success);
}

.bo-badge-inactive {
    background: rgba(228, 87, 46, 0.15);
    color: var(--danger);
}

.bo-table-actions {
    display: flex;
    gap: 8px;
}

.bo-inline-form {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 160px;
}

.bo-nav-tabs {
    border-bottom-color: var(--border);
    margin-bottom: 18px;
}

.bo-nav-tabs .nav-link {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    font-weight: 700;
}

.bo-nav-tabs .nav-link:hover {
    border-color: var(--border);
    color: var(--text);
}

.bo-nav-tabs .nav-link.active {
    background: var(--surface);
    border-color: var(--border) var(--border) var(--surface);
    color: var(--gold-bright);
}

/* Linha "nenhum resultado encontrado", mostrada pelo JS quando o filtro zera a lista */
.bo-empty-row td {
    text-align: center;
    padding: 40px 16px;
    color: var(--text-muted);
}

/* =========================================================================
   9. MODAIS
   ========================================================================= */
.bo-modal .modal-content {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 14px;
}

.bo-modal .modal-header,
.bo-modal .modal-footer {
    border-color: var(--border);
}

.bo-modal .form-label {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 600;
}

.bo-modal .form-control,
.bo-modal .form-select {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
}

.bo-modal .form-control:focus,
.bo-modal .form-select:focus {
    background: var(--surface-2);
    border-color: var(--gold);
    color: var(--text);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

.bo-modal .btn-close {
    filter: invert(1) grayscale(1) brightness(1.6);
}

/* No tema claro o botão de fechar do Bootstrap já nasce escuro — não
   precisa do filtro de inverter cor (que foi pensado pro tema escuro) */
html[data-theme="light"] .bo-modal .btn-close {
    filter: none;
}

/* Modal do perfil profissional: permanece escuro mesmo quando o painel usa
   o tema claro. O :has() limita estas variáveis às páginas que realmente
   carregaram section-profissional.php; outros perfis não são afetados. */
body:has(.bo-content-section[data-perfil="profissional"]) #boFormModal {
    --surface: #1e1a15;
    --surface-2: #241f18;
    --border: rgba(243, 237, 226, 0.12);
    --text: #f3ede2;
    --text-muted: #b0a896;
    --gold: #d4af37;
    --gold-bright: #f4c430;
    --gold-dim: #8a6b21;
}

body:has(.bo-content-section[data-perfil="profissional"]) #boFormModal .btn-close {
    filter: invert(1) grayscale(1) brightness(1.6);
}

/* Pré-visualização de imagem nos campos do tipo "image" (upload/URL) */
.bo-modal img[data-bo-preview] {
    max-height: 140px;
    border-radius: 8px;
    border: 1px solid var(--border);
    display: none;
    object-fit: cover;
}

/* =========================================================================
   10. TELA "EM CONSTRUÇÃO" (stub) + PAINEL VAZIO (empty panel)
   ========================================================================= */
.bo-stub {
    background: var(--surface);
    border: 1px dashed var(--border);
    border-radius: 14px;
    padding: 60px 32px;
    text-align: center;
}

.bo-stub i {
    font-size: 40px;
    color: var(--gold);
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
    color: var(--text-muted);
    max-width: 420px;
    margin: 0 auto;
}

/* Painel vazio "genérico" (mesma ideia do stub, mas dentro de um
   .bo-data-panel — usado quando uma tabela/lista de dados reais ainda
   não tem nenhum registro) */
.bo-data-panel {
    min-height: 270px;
    padding: 28px;
}

.bo-empty-panel {
    align-items: center;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
    height: 100%;
}

.bo-empty-panel>i {
    color: var(--gold);
    font-size: 34px;
    margin-bottom: 13px;
}

.bo-empty-panel h2 {
    color: var(--text);
    font-size: 17px;
    font-weight: 800;
    margin: 0 0 7px;
}

.bo-empty-panel p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
    max-width: 440px;
}

/* =========================================================================
   11. LISTAS SIMPLES (usadas em "Funções" e "Categorias" — sem tabela, só linhas)
   ========================================================================= */
.bo-list {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
}

.bo-list-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
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
    color: var(--text-muted);
    margin-top: 2px;
}

/* =========================================================================
   12. AGENDA (cards de horário agendado/disponível — profissional e aluno)
   ========================================================================= */
.bo-agenda-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

/* Borda tracejada para diferenciar horários "disponíveis" dos já agendados */
.bo-agenda-card.disponivel {
    border-style: dashed;
}

.bo-agenda-card .bo-agenda-title {
    font-weight: 700;
    font-size: 14px;
}

.bo-agenda-card .bo-agenda-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Título pequeno usado para separar grupos dentro de uma mesma seção
   (ex: "Agendados" / "Disponíveis" na tela de Agenda) */
.bo-section-heading {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin: 24px 0 12px;
    font-weight: 700;
}

.bo-section-heading:first-child {
    margin-top: 0;
}

/* =========================================================================
   13. PERFIL DO ALUNO (bloco de dados cadastrais + bloco de avaliação física/IMC)
   ========================================================================= */
.bo-profile-block {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}

.bo-profile-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    gap: 12px;
}

.bo-profile-row:last-child {
    border-bottom: none;
}

.bo-profile-row span:first-child {
    color: var(--text-muted);
}

.bo-imc-box {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

/* =========================================================================
   14. TOAST (aviso flutuante no canto inferior direito)
   ========================================================================= */
.bo-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--surface);
    border: 1px solid var(--gold);
    color: var(--text);
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

/* =========================================================================
   15. PAGAMENTO PIX (QR code simulado + campo "copia e cola")
   ========================================================================= */
.bo-pix-box {
    background: var(--surface-2);
    border: 1px dashed var(--border);
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
        repeating-linear-gradient(45deg, var(--gold-dim) 0 8px, transparent 8px 16px),
        var(--surface);
    border: 1px solid var(--border);
}

/* =========================================================================
   16. CARTÃO DE CONFIGURAÇÕES (troca de tema claro/escuro)
   ========================================================================= */
.bo-settings-card {
    max-width: 760px;
    padding: 26px;
}

.bo-settings-stack {
    display: grid;
    gap: 18px;
    max-width: 760px;
    width: 100%;
}

.bo-settings-stack .bo-settings-card {
    max-width: none;
}

@media (min-width: 992px) and (max-width: 1199px) {
    .bo-settings-stack {
        max-width: 900px;
    }
}

@media (min-width: 1200px) {
    .bo-settings-stack {
        max-width: 1080px;
    }
}

.bo-settings-heading {
    align-items: center;
    display: flex;
    gap: 14px;
    margin-bottom: 22px;
}

.bo-settings-heading h2 {
    color: var(--text);
    font-size: 16px;
    font-weight: 800;
    margin: 0 0 4px;
}

.bo-settings-heading p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
}

.bo-theme-choices {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.bo-theme-choice {
    align-items: center;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    display: flex;
    gap: 12px;
    padding: 16px;
    text-align: left;
    transition: border-color .2s, background .2s;
}

.bo-theme-choice:hover,
.bo-theme-choice.active {
    background: rgba(212, 175, 55, .09);
    border-color: var(--gold);
}

.bo-theme-choice:focus-visible,
.bo-toggle-input:focus-visible {
    outline: 3px solid rgba(212, 175, 55, .35);
    outline-offset: 3px;
}

.bo-theme-choice:disabled,
.bo-toggle-input:disabled {
    cursor: wait;
    opacity: .65;
}

.bo-theme-choice>i:first-child {
    color: var(--gold-bright);
    font-size: 20px;
}

.bo-theme-choice span {
    flex: 1;
}

.bo-theme-choice strong,
.bo-theme-choice small {
    display: block;
}

.bo-theme-choice strong {
    font-size: 14px;
}

.bo-theme-choice small {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 2px;
}

.bo-settings-logout,
.bo-settings-action-row {
    align-items: center;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 16px;
    justify-content: space-between;
    margin-top: 23px;
    padding-top: 20px;
}

.bo-settings-logout strong,
.bo-settings-logout span,
.bo-settings-action-row strong,
.bo-settings-action-row span {
    display: block;
}

.bo-settings-logout strong,
.bo-settings-action-row strong {
    color: var(--text);
    font-size: 14px;
}

.bo-settings-logout span,
.bo-settings-action-row span {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 3px;
}

.bo-preference-list {
    display: grid;
}

.bo-preference-row {
    align-items: center;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    min-height: 58px;
    transition: opacity .2s;
}

.bo-preference-row:first-child {
    border-top: 0;
}

.bo-preference-row label {
    align-items: center;
    color: var(--text);
    cursor: pointer;
    display: flex;
    font-size: 14px;
    gap: 11px;
}

.bo-preference-row label i {
    color: var(--gold-bright);
    font-size: 17px;
}

.bo-preference-row.is-loading {
    opacity: .6;
}

.bo-toggle-input {
    appearance: none;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 999px;
    cursor: pointer;
    flex: 0 0 auto;
    height: 26px;
    margin: 0;
    position: relative;
    transition: background .2s, border-color .2s;
    width: 46px;
}

.bo-toggle-input::after {
    background: var(--text-muted);
    border-radius: 50%;
    content: '';
    height: 18px;
    left: 3px;
    position: absolute;
    top: 3px;
    transition: transform .2s, background .2s;
    width: 18px;
}

.bo-toggle-input:checked {
    background: rgba(212, 175, 55, .24);
    border-color: var(--gold);
}

.bo-toggle-input:checked::after {
    background: var(--gold-bright);
    transform: translateX(20px);
}

.bo-settings-hint {
    color: var(--text-muted);
    font-size: 12px;
    margin: 16px 0 0;
}

.bo-logout-button:hover {
    border-color: var(--danger);
    color: var(--danger);
}

/* =========================================================================
   17. BACKDROP MOBILE + VISIBILIDADE DAS SEÇÕES
   ========================================================================= */

/* Camada escura atrás da sidebar quando aberta no mobile (fecha ao clicar fora) */
.bo-sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1024;
}

/* Cada "página" do backoffice é uma <section data-perfil="..." data-section="...">
   escondida por padrão; o JS mostra só a seção ativa trocando essa classe */
.bo-content-section {
    display: none;
}

.bo-content-section.active {
    display: block;
}

/* =========================================================================
   18. RESPONSIVO
   ========================================================================= */
@media (max-width: 1199px) {
    .bo-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 991px) {

    .bo-theme-choices {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    /* Sidebar vira um painel deslizante (off-canvas) em telas menores */
    .bo-sidebar {
        transform: translateX(-100%);
    }

    .bo-sidebar.active {
        transform: translateX(0);
    }

    .bo-sidebar-backdrop.active {
        display: block;
    }

    .bo-header {
        left: 0;
        padding: 0 18px;
    }

    .bo-main {
        margin-left: 0;
        padding: 26px 18px 48px;
    }
}

@media (max-width: 560px) {

    /* Esconde o texto "One Fit · Backoffice" no header, mantém só a logo */
    .bo-logo span {
        display: none;
    }

    .bo-header-search {
        width: min(260px, 50vw);
    }

    .bo-metric-grid,
    .bo-theme-choices {
        grid-template-columns: 1fr;
    }

    .bo-metric-card {
        min-height: 112px;
    }

    .bo-header-settings {
        display: none;
    }

    .bo-user {
        gap: 8px;
    }

    .bo-search-results {
        left: min(-52px, -18vw);
        width: min(340px, calc(100vw - 28px));
    }

    .bo-settings-logout {
        align-items: flex-start;
        flex-direction: column;
    }

    .bo-settings-action-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .bo-settings-action-row .btn-bo-outline,
    .bo-settings-action-row .logout-button {
        justify-content: center;
        width: 100%;
    }

    .btn-bo-outline {
        padding: 8px 11px;
    }
}

/* Checklist de modalidades no cadastro de profissional */
.bo-checklist {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface-2);
}

.bo-checklist-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 500;
    color: var(--text);
    cursor: pointer;
}

.bo-checklist-item input {
    accent-color: var(--gold);
    cursor: pointer;
}

[data-bo-compras] .bo-compra-aguardando { color: #d8b45a; background: rgba(216, 180, 90, .12); }
[data-bo-compras] .bo-compra-preparando { color: #d8b45a; background: rgba(216, 180, 90, .12); }
[data-bo-compras] .bo-compra-despachado { color: #6fa8dc; background: rgba(111, 168, 220, .12); }
[data-bo-compras] .bo-compra-entregue { color: #55ba88; background: rgba(85, 186, 136, .12); }
[data-bo-compras] .bo-compra-cancelado { color: #df8585; background: rgba(223, 133, 133, .12); }
[data-bo-compras] .bo-compra-devolvido { color: #b19bd9; background: rgba(177, 155, 217, .12); }
[data-bo-compras] .bo-compra-extraviado { color: #df8585; background: rgba(223, 133, 133, .12); }

/* =========================================================================
   19. CALENDÁRIO DA AGENDA (aluno) — navegação de mês/dia via link (GET),
   sem JS: cada célula é um <a> para dashboard.php?section=agenda&mes=...&dia=...
   ========================================================================= */
.bo-calendar-day.is-passado { opacity: .4; }
.bo-agenda-fields { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
.bo-agenda-fields label { flex: 1 1 220px; }
.bo-agenda-fields select { width: 100%; margin-top: 8px; }
.bo-agenda-booking { display: block; }
.bo-agenda-booking fieldset { border: 0; padding: 0; margin: 16px 0; }
.bo-agenda-booking legend { font-size: 14px; }
.bo-agenda-times { display: flex; flex-wrap: wrap; gap: 8px; }
.bo-agenda-time { position: relative; cursor: pointer; }
.bo-agenda-time input { position: absolute; opacity: 0; width: 100%; height: 100%; }
.bo-agenda-time span { display: block; padding: 8px 16px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-2); }
.bo-agenda-time input:checked + span { background: var(--gold); color: #1a1509; }
.bo-agenda-time input:focus-visible + span { outline: 2px solid var(--gold); outline-offset: 3px; }
.bo-agenda-booking button:disabled { opacity: .45; cursor: not-allowed; }
.bo-calendar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 20px;
}

.bo-calendar-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 16px;
}

.bo-calendar-nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--gold);
    color: #1a1509;
    text-decoration: none;
    font-size: 14px;
    flex-shrink: 0;
    transition: background .15s ease;
}

.bo-calendar-nav-btn:hover {
    background: var(--gold-bright);
}

.bo-calendar-mes-atual {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 6px 20px;
    font-size: 14px;
    font-weight: 700;
    text-transform: capitalize;
    min-width: 190px;
    text-align: center;
}

.bo-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
}

.bo-calendar-weekday {
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
    color: var(--gold-bright);
    padding-bottom: 4px;
}

.bo-calendar-day {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 84px;
    padding: 8px;
    border-radius: 12px;
    border: 1px solid var(--border);
    color: var(--text);
    text-decoration: none;
    background: var(--surface-2);
    overflow: hidden;
}

.bo-calendar-day.is-outro-mes {
    visibility: hidden;
}

.bo-calendar-day.is-disponivel {
    cursor: pointer;
}

.bo-calendar-day.is-disponivel:hover {
    border-color: var(--gold);
    background: rgba(212, 175, 55, .1);
}

.bo-calendar-day.is-selecionado {
    border-color: var(--gold);
    background: rgba(212, 175, 55, .16);
}

.bo-calendar-day-topo {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.bo-calendar-day-num {
    font-size: 15px;
    font-weight: 800;
    color: var(--gold-bright);
    margin-right: auto;
}

.bo-calendar-badge-livre,
.bo-calendar-badge-evento {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .02em;
    padding: 2px 8px;
    border-radius: 999px;
    white-space: nowrap;
}

.bo-calendar-badge-livre {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-muted);
}

.bo-calendar-badge-evento {
    background: var(--gold);
    color: #1a1509;
}

.bo-calendar-evento-preview {
    display: flex;
    flex-direction: column;
    gap: 1px;
    line-height: 1.2;
}

.bo-calendar-evento-preview strong {
    font-size: 12px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bo-calendar-evento-preview span {
    font-size: 10px;
    color: var(--text-muted);
}

.bo-calendar-legenda {
    margin: 14px 2px 0;
    font-size: 12px;
    color: var(--text-muted);
}

.bo-slot-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bo-slot-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
    flex-wrap: wrap;
}

```
