# Treino - arquivos completos

Implementado CRUD persistente e restrito ao aluno da sessao. Modal com exercicios por grupo muscular, series de 1 a 10, repeticoes de 1 a 50 e cargas inteiras de 0 a 300 kg. A tabela atualiza depois da resposta do servidor. Excluir e limpar exigem confirmacao; token unico impede duplicacao de um mesmo envio. Mantidas as classes e o tema existentes.

A estrutura do banco foi inspecionada e nao havia tabela de treino. A migracao abaixo ja foi aplicada neste ambiente, sem inserir fichas de exemplo. Em outra instalacao, execute a migracao antes de copiar os arquivos PHP. Cada aluno tem uma ficha atual representada pelas suas linhas em treino_exercicio.

Validacao: 16 testes de SQL real em tabela temporaria para CRUD, limites, reenvio e isolamento; renderizacao dos quatro selects; sintaxe PHP e JavaScript. Nao foi realizado teste visual/login em navegador.

SQL NECESSÁRIO:

```sql
CREATE TABLE IF NOT EXISTS treino_exercicio (
    id_exercicio INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    series TINYINT UNSIGNED NOT NULL,
    repeticoes TINYINT UNSIGNED NOT NULL,
    carga SMALLINT UNSIGNED NOT NULL,
    token_criacao CHAR(32) NOT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_exercicio),
    UNIQUE KEY uq_treino_envio (id_usuario, token_criacao),
    CONSTRAINT fk_treino_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

```

ARQUIVO:
pages/dashboard/includes/db-data.php

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

    // Tela "Treino": ficha persistida do aluno autenticado.
    $alunoTreino = bo_treino_carregar($conn, $idUsuarioLogado);

    // Tela "Minha agenda" — não há tabela de horários "disponíveis" distinta dos agendamentos
    $alunoAgendaDisponiveis = [];
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
                   pr.nome AS produto_nome, u.nome AS comprador_nome, pe.data_pedido,
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

ARQUIVO:
pages/dashboard/components/section-aluno.php

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
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Usar cashback", {})'>
            <i class="bi bi-wallet2"></i> Usar Cashback
        </button>
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

<!-- ===== ALUNO · Minha agenda (horários de avaliação física disponíveis) ===== -->
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

```

ARQUIVO:
pages/dashboard/includes/treino.php

CÓDIGO COMPLETO:

```php
<?php
function bo_treino_catalogo(): array
{
    return [
        'Peito' => ['Supino reto', 'Supino inclinado', 'Crucifixo', 'Crossover'],
        'Costas' => ['Puxada frontal', 'Remada baixa', 'Remada curvada', 'Pulldown'],
        'Ombros' => ['Desenvolvimento', 'Elevação lateral', 'Elevação frontal'],
        'Bíceps' => ['Rosca direta', 'Rosca alternada', 'Rosca martelo'],
        'Tríceps' => ['Tríceps pulley', 'Tríceps testa', 'Tríceps francês'],
        'Pernas' => ['Agachamento', 'Leg press', 'Cadeira extensora', 'Mesa flexora', 'Stiff', 'Panturrilha'],
        'Abdômen' => ['Abdominal tradicional', 'Prancha', 'Elevação de pernas'],
    ];
}

function bo_treino_carregar(mysqli $conn, int $usuario): array
{
    $stmt = $conn->prepare('SELECT id_exercicio AS id, nome, series, repeticoes, carga FROM treino_exercicio WHERE id_usuario = ? ORDER BY id_exercicio');
    $stmt->bind_param('i', $usuario);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function bo_treino_alterar(mysqli $conn, int $usuario, array $dados): void
{
    if ($usuario <= 0) throw new DomainException('Entre novamente para acessar o treino.');
    $acao = $dados['acao'] ?? '';
    if (!in_array($acao, ['salvar', 'excluir', 'limpar'], true)) throw new DomainException('Ação inválida.');
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id === false || $id < 0 || ($acao === 'excluir' && !$id)) throw new DomainException('Exercício inválido.');
    if ($acao === 'limpar') {
        $stmt = $conn->prepare('DELETE FROM treino_exercicio WHERE id_usuario = ?');
        $stmt->bind_param('i', $usuario);
    } elseif ($acao === 'excluir') {
        $stmt = $conn->prepare('DELETE FROM treino_exercicio WHERE id_exercicio = ? AND id_usuario = ?');
        $stmt->bind_param('ii', $id, $usuario);
    } else {
        $nome = $dados['nome'] ?? '';
        if (!is_string($nome) || !in_array($nome, array_merge(...array_values(bo_treino_catalogo())), true)) {
            throw new DomainException('Selecione um exercício.');
        }
        $series = filter_var($dados['series'] ?? null, FILTER_VALIDATE_INT);
        $repeticoes = filter_var($dados['repeticoes'] ?? null, FILTER_VALIDATE_INT);
        $carga = filter_var($dados['carga'] ?? null, FILTER_VALIDATE_INT);
        if ($series === false || $series < 1 || $series > 10) throw new DomainException('Selecione de 1 a 10 séries.');
        if ($repeticoes === false || $repeticoes < 1 || $repeticoes > 50) throw new DomainException('Selecione de 1 a 50 repetições.');
        if ($carga === false || $carga < 0 || $carga > 300) throw new DomainException('Selecione uma carga de 0 a 300 kg.');
        if ($id) {
            $stmt = $conn->prepare('UPDATE treino_exercicio SET nome = ?, series = ?, repeticoes = ?, carga = ? WHERE id_exercicio = ? AND id_usuario = ?');
            $stmt->bind_param('siiiii', $nome, $series, $repeticoes, $carga, $id, $usuario);
        } else {
            $token = $dados['token'] ?? '';
            if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) throw new DomainException('Reabra o formulário e tente novamente.');
            // A mesma requisição não pode criar duas linhas, mesmo após um retry.
            $stmt = $conn->prepare('INSERT INTO treino_exercicio (id_usuario, nome, series, repeticoes, carga, token_criacao) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_exercicio = id_exercicio');
            $stmt->bind_param('isiiis', $usuario, $nome, $series, $repeticoes, $carga, $token);
        }
    }
    $stmt->execute();
    $stmt->close();
}

```

ARQUIVO:
pages/dashboard/components/section-treino.php

CÓDIGO COMPLETO:

```php
<section class="bo-content-section" data-perfil="aluno" data-section="treino" id="boTreino"
    data-endpoint="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/treino.php'); ?>"
    data-csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="bo-page-title">
        <div><h1>Treino</h1><p>Monte e acompanhe sua ficha de treino.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-treino-limpar><i class="bi bi-eraser"></i> Limpar Treino</button>
            <button type="button" class="btn-bo-gold" data-treino-adicionar><i class="bi bi-plus-lg"></i> Adicionar Treino</button>
        </div>
    </div>
    <p data-treino-aviso role="status" aria-live="polite" hidden></p>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table"><thead><tr><th>Exercício</th><th>Séries</th><th>Repetições</th><th>Carga</th><th>Ações</th></tr></thead>
            <tbody data-treino-linhas>
                <?php foreach ($alunoTreino as $exercicio): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($exercicio['nome']); ?></td>
                        <td><?php echo (int) $exercicio['series']; ?></td>
                        <td><?php echo (int) $exercicio['repeticoes']; ?></td>
                        <td><?php echo (int) $exercicio['carga']; ?> kg</td>
                        <td><div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar" data-treino-editar="<?php echo (int) $exercicio['id']; ?>"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn-bo-icon danger" title="Excluir" data-treino-excluir="<?php echo (int) $exercicio['id']; ?>"><i class="bi bi-trash"></i></button>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$alunoTreino): ?><tr><td colspan="5">Nenhum exercício cadastrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
    <script type="application/json" data-treino-dados><?php echo bo_json($alunoTreino); ?></script>
</section>
<div class="modal fade bo-modal" id="boTreinoModal" tabindex="-1" aria-labelledby="boTreinoTitulo" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="boTreinoTitulo">Adicionar exercício</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <form id="boTreinoForm" class="row g-3">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="token">
                <div class="col-12">
                    <label class="form-label" for="boTreinoNome">Exercício</label>
                    <select class="form-select" name="nome" id="boTreinoNome" required>
                        <option value="">Selecione um exercício</option>
                        <?php foreach (bo_treino_catalogo() as $grupo => $nomes): ?>
                            <optgroup label="<?php echo htmlspecialchars($grupo); ?>">
                                <?php foreach ($nomes as $nome): ?><option><?php echo htmlspecialchars($nome); ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['series' => ['Séries', 1, 10, 3], 'repeticoes' => ['Repetições', 1, 50, 12], 'carga' => ['Carga (kg)', 0, 300, 0]] as $campo => [$label, $minimo, $maximo, $padrao]): ?>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="boTreino-<?php echo $campo; ?>"><?php echo $label; ?></label>
                        <select class="form-select" name="<?php echo $campo; ?>" id="boTreino-<?php echo $campo; ?>" required>
                            <?php for ($valor = $minimo; $valor <= $maximo; $valor++): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $valor === $padrao ? 'selected' : ''; ?>><?php echo $valor . ($campo === 'carga' ? ' kg' : ''); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <p class="col-12 mb-0" data-treino-erro role="alert" hidden></p>
            </form>
        </div>
        <div class="modal-footer"><button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn-bo-gold" form="boTreinoForm">Salvar</button></div>
    </div></div>
</div>
<div class="modal fade bo-modal" id="boTreinoConfirmar" tabindex="-1" aria-labelledby="boTreinoConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="boTreinoConfirmarTitulo">Limpar treino</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><p data-treino-pergunta></p><p data-treino-confirmar-erro role="alert" hidden></p></div>
        <div class="modal-footer"><button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn-bo-gold" data-treino-confirmar>Limpar treino</button></div>
    </div></div>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/treino.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/treino.js'); ?>" defer></script>

```

ARQUIVO:
pages/dashboard/funcionalidades/treino.php

CÓDIGO COMPLETO:

```php
<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['id_usuario']) || ($_SESSION['tipo_usuario'] ?? '') !== 'aluno') {
    http_response_code(403);
    echo json_encode(['error' => 'Entre como aluno para acessar o treino.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sua sessão expirou. Atualize a página.']);
    exit;
}
try {
    require __DIR__ . '/../../../config/conn.php';
    require __DIR__ . '/../includes/treino.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    bo_treino_alterar($conn, (int) $_SESSION['id_usuario'], $_POST);
    echo json_encode(['ok' => true, 'exercicios' => bo_treino_carregar($conn, (int) $_SESSION['id_usuario'])], JSON_THROW_ON_ERROR);
} catch (DomainException $erro) {
    http_response_code(422);
    echo json_encode(['error' => $erro->getMessage()]);
} catch (Throwable $erro) {
    error_log('ONE FIT treino: código ' . $erro->getCode());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível salvar o treino. Tente novamente.']);
}

```

ARQUIVO:
assets/js/treino.js

CÓDIGO COMPLETO:

```js
document.addEventListener('DOMContentLoaded', () => {
    const section = document.getElementById('boTreino');
    if (!section) return;
    const form = document.getElementById('boTreinoForm');
    const modalElement = document.getElementById('boTreinoModal');
    const confirmElement = document.getElementById('boTreinoConfirmar');
    const modal = new bootstrap.Modal(modalElement);
    const confirmModal = new bootstrap.Modal(confirmElement);
    const error = form.querySelector('[data-treino-erro]');
    const confirmError = confirmElement.querySelector('[data-treino-confirmar-erro]');
    const notice = section.querySelector('[data-treino-aviso]');
    let exercises = JSON.parse(section.querySelector('[data-treino-dados]').textContent);
    let pending = null;
    let busy = false;

    function render() {
        const body = section.querySelector('[data-treino-linhas]');
        body.replaceChildren();
        exercises.forEach(exercise => {
            const row = body.insertRow();
            [exercise.nome, exercise.series, exercise.repeticoes, `${exercise.carga} kg`].forEach(value => {
                row.insertCell().textContent = value;
            });
            const actions = document.createElement('div');
            actions.className = 'bo-table-actions';
            [['editar', 'Editar', 'bi-pencil'], ['excluir', 'Excluir', 'bi-trash']].forEach(([action, title, icon]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn-bo-icon' + (action === 'excluir' ? ' danger' : '');
                button.title = title;
                button.setAttribute(`data-treino-${action}`, exercise.id);
                const symbol = document.createElement('i');
                symbol.className = `bi ${icon}`;
                button.append(symbol);
                actions.append(button);
            });
            row.insertCell().append(actions);
        });
        if (!exercises.length) {
            const cell = body.insertRow().insertCell();
            cell.colSpan = 5;
            cell.textContent = 'Nenhum exercício cadastrado.';
        }
    }

    async function send(data, errorElement, onSuccess) {
        if (busy) return;
        busy = true;
        errorElement.hidden = true;
        [modalElement, confirmElement, section].forEach(element => {
            element.querySelectorAll('button').forEach(button => { button.disabled = true; });
        });
        try {
            data.set('csrf_token', section.dataset.csrf);
            const response = await fetch(section.dataset.endpoint, { method: 'POST', body: data, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar o treino.');
            exercises = result.exercicios;
            render();
            onSuccess();
            notice.hidden = false;
            notice.textContent = 'Treino atualizado.';
        } catch (failure) {
            errorElement.textContent = failure instanceof SyntaxError ? 'Resposta inválida. Atualize a página e tente novamente.' : failure.message;
            errorElement.hidden = false;
        } finally {
            busy = false;
            [modalElement, confirmElement, section].forEach(element => {
                element.querySelectorAll('button').forEach(button => { button.disabled = false; });
            });
        }
    }

    [modalElement, confirmElement].forEach(element => {
        element.addEventListener('hide.bs.modal', event => { if (busy) event.preventDefault(); });
    });
    function open(exercise) {
        form.reset();
        error.hidden = true;
        form.elements.id.value = exercise ? exercise.id : 0;
        // Token por abertura; reaproveitado em retries após erro de rede.
        form.elements.token.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
        if (exercise) ['nome', 'series', 'repeticoes', 'carga'].forEach(key => { form.elements[key].value = exercise[key]; });
        document.getElementById('boTreinoTitulo').textContent = exercise ? 'Editar exercício' : 'Adicionar exercício';
        modal.show();
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity() || busy) return;
        const data = new FormData(form);
        data.set('acao', 'salvar');
        send(data, error, () => { busy = false; modal.hide(); });
    });
    section.addEventListener('click', event => {
        if (busy) return;
        if (event.target.closest('[data-treino-adicionar]')) { open(null); return; }
        const edit = event.target.closest('[data-treino-editar]');
        if (edit) { open(exercises.find(item => String(item.id) === edit.dataset.treinoEditar)); return; }
        const remove = event.target.closest('[data-treino-excluir]');
        const clear = event.target.closest('[data-treino-limpar]');
        if (!remove && !clear) return;
        pending = { acao: clear ? 'limpar' : 'excluir', id: clear ? '0' : remove.dataset.treinoExcluir };
        confirmError.hidden = true;
        document.getElementById('boTreinoConfirmarTitulo').textContent = clear ? 'Limpar treino' : 'Excluir exercício';
        confirmElement.querySelector('[data-treino-pergunta]').textContent = clear
            ? 'Tem certeza que deseja limpar todo o treino?' : 'Tem certeza que deseja excluir este exercício?';
        confirmElement.querySelector('[data-treino-confirmar]').textContent = clear ? 'Limpar treino' : 'Excluir';
        confirmModal.show();
    });
    confirmElement.querySelector('[data-treino-confirmar]').addEventListener('click', () => {
        if (!pending || busy) return;
        const data = new FormData();
        Object.entries(pending).forEach(([key, value]) => data.set(key, value));
        send(data, confirmError, () => { busy = false; pending = null; confirmModal.hide(); });
    });
});

```

ARQUIVO:
tests/treino.php

CÓDIGO COMPLETO:

```php
<?php
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/dashboard/includes/treino.php';
// Isola todas as gravações em uma tabela temporária desta conexão.
$conn->query('CREATE TEMPORARY TABLE treino_schema LIKE treino_exercicio');
$conn->query('CREATE TEMPORARY TABLE treino_exercicio LIKE treino_schema');
$checks = 0;
function treino_check(bool $ok): void {
    global $checks;
    if (!$ok) throw new RuntimeException('Falha no teste de treino #' . ($checks + 1));
    $checks++;
}
$input = ['acao' => 'salvar', 'nome' => 'Supino reto', 'series' => '4', 'repeticoes' => '12', 'carga' => '30', 'token' => bin2hex(random_bytes(16))];
bo_treino_alterar($conn, 1, $input);
bo_treino_alterar($conn, 1, $input);
$rows = bo_treino_carregar($conn, 1);
treino_check(count($rows) === 1);
treino_check($rows[0]['nome'] === 'Supino reto' && (int) $rows[0]['carga'] === 30);
treino_check((int) $rows[0]['series'] === 4 && (int) $rows[0]['repeticoes'] === 12);
treino_check(bo_treino_carregar($conn, 2) === []);
$id = $rows[0]['id'];
bo_treino_alterar($conn, 2, array_replace($input, ['id' => $id, 'carga' => 100]));
treino_check((int) bo_treino_carregar($conn, 1)[0]['carga'] === 30);
bo_treino_alterar($conn, 2, ['acao' => 'excluir', 'id' => $id]);
treino_check(count(bo_treino_carregar($conn, 1)) === 1);
bo_treino_alterar($conn, 1, array_replace($input, ['id' => $id, 'carga' => 0, 'series' => 10, 'repeticoes' => 50]));
treino_check((int) bo_treino_carregar($conn, 1)[0]['carga'] === 0);
foreach (['nome' => '', 'series' => 11, 'repeticoes' => 0, 'carga' => 301, 'token' => 'invalido', 'id' => -1] as $key => $value) {
    $failed = false;
    try { bo_treino_alterar($conn, 1, array_replace($input, [$key => $value])); } catch (DomainException $e) { $failed = true; }
    treino_check($failed);
}
bo_treino_alterar($conn, 2, array_replace($input, ['token' => bin2hex(random_bytes(16))]));
bo_treino_alterar($conn, 1, ['acao' => 'limpar']);
treino_check(bo_treino_carregar($conn, 1) === []);
treino_check(count(bo_treino_carregar($conn, 2)) === 1);
$id2 = bo_treino_carregar($conn, 2)[0]['id'];
bo_treino_alterar($conn, 2, ['acao' => 'excluir', 'id' => $id2]);
treino_check(bo_treino_carregar($conn, 2) === []);
echo "OK: $checks verificações de CRUD, validação, duplicidade e isolamento; nenhuma ficha gravada na base de uso.\n";

```

