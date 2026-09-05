<?php
/**
 * Área do profissional ligada ao banco.
 *
 * A regra desta área permanece neste arquivo para não exigir alterações nas
 * actions, no JavaScript global ou nos demais componentes do dashboard.
 */

$ofH = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$ofFeedback = null;
$ofIdUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$ofIdProfissional = 0;
$ofAlunosDisponiveis = [];
$ofAlunosVinculados = [];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Conexão com o banco indisponível.');
    }

    $stmt = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $ofCadastroProfissional = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ofCadastroProfissional) {
        throw new RuntimeException('O usuário conectado não possui cadastro de profissional.');
    }
    $ofIdProfissional = (int) $ofCadastroProfissional['id_profissional'];

    // Relações ausentes no dump original. IF NOT EXISTS torna a preparação
    // segura para ser executada novamente sem apagar ou duplicar dados.
    if (!$conn->query(
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
    )) {
        throw new RuntimeException('Não foi possível preparar os vínculos de alunos.');
    }

    if (!$conn->query(
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
    )) {
        throw new RuntimeException('Não foi possível preparar os horários disponíveis.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prof_action'])) {
        $ofTokenRecebido = (string) ($_POST['csrf_token'] ?? '');
        $ofTokenSessao = (string) ($_SESSION['csrf_token'] ?? '');
        if ($ofTokenSessao === '' || !hash_equals($ofTokenSessao, $ofTokenRecebido)) {
            throw new RuntimeException('A sessão expirou. Atualize a página e tente novamente.');
        }

        $ofAction = (string) $_POST['prof_action'];

        if ($ofAction === 'vincular_aluno') {
            $idAluno = filter_input(INPUT_POST, 'id_aluno', FILTER_VALIDATE_INT);
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            if (!$idAluno) {
                throw new RuntimeException('Selecione um aluno válido.');
            }

            $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'aluno' LIMIT 1");
            $stmt->bind_param('i', $idAluno);
            $stmt->execute();
            $alunoExiste = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$alunoExiste) {
                throw new RuntimeException('Aluno não encontrado.');
            }

            $stmt = $conn->prepare(
                "INSERT INTO profissional_aluno (id_profissional, id_aluno, status, observacao)
                 VALUES (?, ?, 'ativo', ?)
                 ON DUPLICATE KEY UPDATE status = 'ativo', observacao = VALUES(observacao)"
            );
            $stmt->bind_param('iis', $ofIdProfissional, $idAluno, $observacao);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível vincular o aluno.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Aluno vinculado com sucesso.'];
        } elseif ($ofAction === 'atualizar_vinculo') {
            $idVinculo = filter_input(INPUT_POST, 'id_vinculo', FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            if (!$idVinculo || !in_array($status, ['ativo', 'inativo'], true)) {
                throw new RuntimeException('Dados do vínculo inválidos.');
            }

            $stmt = $conn->prepare(
                'UPDATE profissional_aluno SET status = ?, observacao = ? WHERE id_vinculo = ? AND id_profissional = ?'
            );
            $stmt->bind_param('ssii', $status, $observacao, $idVinculo, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Vínculo não encontrado ou sem alterações.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Vínculo atualizado com sucesso.'];
        } elseif ($ofAction === 'excluir_vinculo') {
            $idVinculo = filter_input(INPUT_POST, 'id_vinculo', FILTER_VALIDATE_INT);
            if (!$idVinculo) {
                throw new RuntimeException('Vínculo inválido.');
            }

            $stmt = $conn->prepare('DELETE FROM profissional_aluno WHERE id_vinculo = ? AND id_profissional = ?');
            $stmt->bind_param('ii', $idVinculo, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Vínculo não encontrado.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Aluno removido da lista. O cadastro dele foi preservado.'];
        } elseif ($ofAction === 'criar_disponibilidade') {
            $modalidade = trim((string) ($_POST['modalidade'] ?? ''));
            $dataEvento = (string) ($_POST['data_evento'] ?? '');
            $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
            $horaFim = (string) ($_POST['hora_fim'] ?? '');
            $local = trim((string) ($_POST['local'] ?? ''));
            $dataValida = DateTime::createFromFormat('Y-m-d', $dataEvento);

            if ($modalidade === '' || !$dataValida || $dataValida->format('Y-m-d') !== $dataEvento
                || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
                || !preg_match('/^\d{2}:\d{2}$/', $horaFim)
                || $horaFim <= $horaInicio) {
                throw new RuntimeException('Preencha corretamente a modalidade, a data e os horários.');
            }

            $stmt = $conn->prepare(
                "SELECT id_disponibilidade FROM disponibilidade_profissional
                 WHERE id_profissional = ? AND data_evento = ? AND status = 'disponivel'
                   AND hora_inicio < ? AND hora_fim > ? LIMIT 1"
            );
            $stmt->bind_param('isss', $ofIdProfissional, $dataEvento, $horaFim, $horaInicio);
            $stmt->execute();
            $conflito = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($conflito) {
                throw new RuntimeException('Já existe um horário disponível nesse período.');
            }

            $stmt = $conn->prepare(
                "INSERT INTO disponibilidade_profissional
                    (id_profissional, modalidade, data_evento, hora_inicio, hora_fim, local, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'disponivel')"
            );
            $stmt->bind_param('isssss', $ofIdProfissional, $modalidade, $dataEvento, $horaInicio, $horaFim, $local);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível cadastrar o horário.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Horário disponível cadastrado.'];
        } elseif ($ofAction === 'remover_disponibilidade') {
            $idDisponibilidade = filter_input(INPUT_POST, 'id_disponibilidade', FILTER_VALIDATE_INT);
            if (!$idDisponibilidade) {
                throw new RuntimeException('Horário inválido.');
            }

            $stmt = $conn->prepare(
                "DELETE FROM disponibilidade_profissional
                 WHERE id_disponibilidade = ? AND id_profissional = ? AND status = 'disponivel'"
            );
            $stmt->bind_param('ii', $idDisponibilidade, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Horário não encontrado.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Horário disponível removido.'];
        } elseif ($ofAction === 'agendar') {
            $operacaoAgendamento = (string) ($_POST['operacao_agendamento'] ?? '');
            if ($operacaoAgendamento === '' || !isset($_SESSION['operacoes_agendamento'][$operacaoAgendamento])) {
                throw new RuntimeException('Formulário já utilizado ou expirado. Abra um novo agendamento.');
            }
            $idAluno = filter_input(INPUT_POST, 'id_aluno', FILTER_VALIDATE_INT);
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $tipo = (string) ($_POST['tipo'] ?? 'aula');
            $dataEvento = (string) ($_POST['data_evento'] ?? '');
            $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
            $horaFim = trim((string) ($_POST['hora_fim'] ?? ''));
            $local = trim((string) ($_POST['local'] ?? ''));
            $observacao = trim((string) ($_POST['observacao'] ?? ''));
            $tiposPermitidos = ['aula', 'personal', 'avaliacao', 'consulta', 'reuniao', 'outro'];
            $dataValida = DateTime::createFromFormat('Y-m-d', $dataEvento);

            if (!$idAluno || $titulo === '' || !in_array($tipo, $tiposPermitidos, true)
                || !$dataValida || $dataValida->format('Y-m-d') !== $dataEvento
                || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
                || ($horaFim !== '' && (!preg_match('/^\d{2}:\d{2}$/', $horaFim) || $horaFim <= $horaInicio))) {
                throw new RuntimeException('Preencha corretamente os dados do agendamento.');
            }

            $stmt = $conn->prepare(
                "SELECT id_vinculo FROM profissional_aluno
                 WHERE id_profissional = ? AND id_aluno = ? AND status = 'ativo' LIMIT 1"
            );
            $stmt->bind_param('ii', $ofIdProfissional, $idAluno);
            $stmt->execute();
            $vinculoAtivo = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$vinculoAtivo) {
                throw new RuntimeException('Selecione um aluno vinculado e ativo.');
            }

            $stmt = $conn->prepare(
                "SELECT id_agendamento FROM agendamento
                 WHERE id_profissional = ? AND data_evento = ? AND hora_inicio = ?
                   AND status NOT IN ('cancelado','concluido') LIMIT 1"
            );
            $stmt->bind_param('iss', $ofIdProfissional, $dataEvento, $horaInicio);
            $stmt->execute();
            $conflito = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($conflito) {
                throw new RuntimeException('Já existe um agendamento nesse horário.');
            }

            // Compatível com o dump original, em que id_agendamento não foi
            // marcado como AUTO_INCREMENT.
            $proximoId = 1;
            $resultadoId = $conn->query('SELECT COALESCE(MAX(id_agendamento), 0) + 1 AS proximo FROM agendamento');
            if ($resultadoId) {
                $proximoId = (int) ($resultadoId->fetch_assoc()['proximo'] ?? 1);
            }

            $stmt = $conn->prepare(
                "INSERT INTO agendamento
                    (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento,
                     hora_inicio, hora_fim, local, observacao, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), 'agendado')"
            );
            $stmt->bind_param(
                'iiisssssss',
                $proximoId,
                $idAluno,
                $ofIdProfissional,
                $titulo,
                $tipo,
                $dataEvento,
                $horaInicio,
                $horaFim,
                $local,
                $observacao
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível criar o agendamento.');
            }
            $stmt->close();
            unset($_SESSION['operacoes_agendamento'][$operacaoAgendamento]);
            // INSERT concluído em autocommit. A falha secundária não desfaz a aula.
            try {
                require_once __DIR__ . '/../../../config/notificacoes.php';
                criarNotificacao($idAluno, 'Agendamento criado',
                    'Seu agendamento foi marcado para ' . $dataValida->format('d/m/Y') . ' às ' . $horaInicio . '.', 'agendamento');
            } catch (Throwable $erroNotificacao) {
                error_log('ONE FIT: falha ao notificar agendamento criado #' . $proximoId . '; código ' . $erroNotificacao->getCode());
            }
            $ofFeedback = ['type' => 'success', 'message' => 'Agendamento criado com sucesso.'];
        } elseif ($ofAction === 'cancelar_agendamento') {
            $idAgendamento = filter_input(INPUT_POST, 'id_agendamento', FILTER_VALIDATE_INT);
            if (!$idAgendamento) {
                throw new RuntimeException('Agendamento inválido.');
            }

            // Destinatário e data vêm do agendamento pertencente ao profissional.
            $stmt = $conn->prepare('SELECT id_usuario, data_evento, hora_inicio FROM agendamento WHERE id_agendamento = ? AND id_profissional = ?');
            $stmt->bind_param('ii', $idAgendamento, $ofIdProfissional);
            $stmt->execute();
            $agendamentoCancelado = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$agendamentoCancelado) throw new RuntimeException('Agendamento não encontrado.');

            $stmt = $conn->prepare(
                "UPDATE agendamento SET status = 'cancelado'
                 WHERE id_agendamento = ? AND id_profissional = ? AND status IN ('agendado','confirmado')"
            );
            $stmt->bind_param('ii', $idAgendamento, $ofIdProfissional);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                throw new RuntimeException('Agendamento não encontrado ou já encerrado.');
            }
            $stmt->close();
            // affected_rows acima impede aviso em cancelamentos repetidos.
            try {
                require_once __DIR__ . '/../../../config/notificacoes.php';
                criarNotificacao((int) $agendamentoCancelado['id_usuario'], 'Agendamento cancelado',
                    'Seu agendamento de ' . date('d/m/Y', strtotime($agendamentoCancelado['data_evento']))
                    . ' às ' . substr($agendamentoCancelado['hora_inicio'], 0, 5) . ' foi cancelado.', 'agendamento');
            } catch (Throwable $erroNotificacao) {
                error_log('ONE FIT: falha ao notificar agendamento cancelado #' . $idAgendamento . '; código ' . $erroNotificacao->getCode());
            }
            $ofFeedback = ['type' => 'success', 'message' => 'Agendamento cancelado.'];
        } elseif ($ofAction === 'usar_cashback') {
            $valorTexto = str_replace(',', '.', trim((string) ($_POST['valor'] ?? '')));
            $valor = filter_var($valorTexto, FILTER_VALIDATE_FLOAT);
            if ($valor === false || $valor <= 0) {
                throw new RuntimeException('Informe um valor de cashback válido.');
            }

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END), 0) AS saldo
                 FROM cashback WHERE id_usuario = ? AND status != 'cancelado'"
            );
            $stmt->bind_param('i', $ofIdUsuario);
            $stmt->execute();
            $saldo = (float) ($stmt->get_result()->fetch_assoc()['saldo'] ?? 0);
            $stmt->close();
            if ($valor > $saldo) {
                throw new RuntimeException('Saldo de cashback insuficiente.');
            }

            $descricao = 'Uso de cashback pelo profissional';
            $stmt = $conn->prepare(
                "INSERT INTO cashback (id_usuario, id_pagamento, valor, tipo, origem, descricao, status)
                 VALUES (?, NULL, ?, 'debito', 'uso', ?, 'utilizado')"
            );
            $stmt->bind_param('ids', $ofIdUsuario, $valor, $descricao);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível utilizar o cashback.');
            }
            $stmt->close();
            $ofFeedback = ['type' => 'success', 'message' => 'Cashback utilizado com sucesso.'];
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    }

    // Recarrega os dados alteráveis para mostrar o resultado na mesma resposta.
    $stmt = $conn->prepare(
        "SELECT pa.id_vinculo, pa.id_aluno, pa.status, pa.observacao,
                u.nome, u.email, u.celular,
                COALESCE(cp.nome, 'Sem plano') AS plano,
                COALESCE(m.valor_contratado, 0) AS valor
         FROM profissional_aluno pa
         JOIN usuarios u ON u.id_usuario = pa.id_aluno
         LEFT JOIN matricula m ON m.id_matricula = (
            SELECT MAX(m2.id_matricula) FROM matricula m2 WHERE m2.id_usuario = u.id_usuario
         )
         LEFT JOIN cadastro_planos cp ON cp.id_plano = m.id_plano
         WHERE pa.id_profissional = ? ORDER BY u.nome"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $ofAlunosVinculados[] = $row;
    }
    $stmt->close();
    $profAlunos = $ofAlunosVinculados;

    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.nome, u.email
         FROM usuarios u
         WHERE u.tipo_usuario = 'aluno' AND u.status = 'ativo'
           AND NOT EXISTS (
             SELECT 1 FROM profissional_aluno pa
             WHERE pa.id_profissional = ? AND pa.id_aluno = u.id_usuario AND pa.status = 'ativo'
           )
         ORDER BY u.nome"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $ofAlunosDisponiveis[] = $row;
    }
    $stmt->close();

    $profAgendados = [];
    $stmt = $conn->prepare(
        "SELECT a.id_agendamento, a.titulo, a.tipo, a.data_evento, a.hora_inicio,
                a.hora_fim, a.local, a.observacao, a.status,
                u.id_usuario AS id_aluno, u.nome AS aluno, u.celular AS contato
         FROM agendamento a
         JOIN usuarios u ON u.id_usuario = a.id_usuario
         WHERE a.id_profissional = ? AND a.status IN ('agendado','confirmado')
         ORDER BY a.data_evento, a.hora_inicio"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $row['modalidade'] = $row['titulo'] ?: ucfirst($row['tipo']);
        $row['data'] = date('d/m/Y', strtotime($row['data_evento'])) . ' ' . substr($row['hora_inicio'], 0, 5);
        $profAgendados[] = $row;
    }
    $stmt->close();

    $profDisponiveis = [];
    $stmt = $conn->prepare(
        "SELECT id_disponibilidade, modalidade, data_evento, hora_inicio, hora_fim, local
         FROM disponibilidade_profissional
         WHERE id_profissional = ? AND status = 'disponivel'
         ORDER BY data_evento, hora_inicio"
    );
    $stmt->bind_param('i', $ofIdProfissional);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $row['data'] = date('d/m/Y', strtotime($row['data_evento']))
            . ' ' . substr($row['hora_inicio'], 0, 5)
            . ' às ' . substr($row['hora_fim'], 0, 5);
        $profDisponiveis[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END), 0) AS saldo
         FROM cashback WHERE id_usuario = ? AND status != 'cancelado'"
    );
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $profContrato['saldoCashback'] = (float) ($stmt->get_result()->fetch_assoc()['saldo'] ?? 0);
    $stmt->close();

    $profCashbackHistorico = [];
    $stmt = $conn->prepare(
        "SELECT data_criacao, descricao, valor, tipo FROM cashback
         WHERE id_usuario = ? AND status != 'cancelado' ORDER BY data_criacao DESC, id_cashback DESC"
    );
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
        $profCashbackHistorico[] = [
            'data' => date('d/m/Y', strtotime($row['data_criacao'])),
            'descricao' => $row['descricao'],
            'valor' => $row['tipo'] === 'debito' ? -(float) $row['valor'] : (float) $row['valor'],
        ];
    }
    $stmt->close();
} catch (Throwable $e) {
    $ofFeedback = ['type' => 'danger', 'message' => $e->getMessage()];
}

$ofCsrf = (string) ($_SESSION['csrf_token'] ?? '');
// Tokens por formulário permitem abas simultâneas e bloqueiam reenvios já concluídos.
$_SESSION['operacoes_agendamento'] = array_slice($_SESSION['operacoes_agendamento'] ?? [], -49, null, true);
$ofOperacaoAgendamento = bin2hex(random_bytes(16));
$_SESSION['operacoes_agendamento'][$ofOperacaoAgendamento] = true;
?>

<?php if ($ofFeedback): ?>
    <div class="alert alert-<?php echo $ofH($ofFeedback['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo $ofH($ofFeedback['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>

<!-- ===== PROFISSIONAL · Dashboard ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="dashboard">
    <div class="bo-page-title">
        <div><h1>Dashboard</h1><p>Resumo do seu contrato e saldo com a ONE FIT.</p></div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-4"><div class="bo-card">
            <div class="bo-card-label">Status de contrato</div>
            <div class="bo-card-value"><?php echo $ofH($profContrato['status'] ?? '—'); ?></div>
        </div></div>
        <div class="col-12 col-md-4"><div class="bo-card">
            <div class="bo-card-label">Validade de contrato</div>
            <div class="bo-card-value"><?php echo $ofH($profContrato['validade'] ?? '—'); ?></div>
        </div></div>
        <div class="col-12 col-md-4"><div class="bo-card">
            <div class="bo-card-label">Saldo de cashback</div>
            <div class="bo-card-value"><?php echo bo_money((float) ($profContrato['saldoCashback'] ?? 0)); ?></div>
        </div></div>
    </div>
</section>

<!-- ===== PROFISSIONAL · Histórico ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="historico">
    <div class="bo-page-title">
        <div><h1>Histórico</h1><p>Histórico de competências e valores recebidos.</p></div>
        <button type="button" class="btn-bo-outline" data-bo-export="profHistorico"><i class="bi bi-download"></i> Exportar</button>
    </div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profHistorico">
            <thead><tr><th>Competência</th><th>Valor</th><th>Tipo</th><th>Cashback</th></tr></thead>
            <tbody>
                <?php foreach (($profHistorico ?? []) as $h): ?>
                    <tr>
                        <td><?php echo $ofH($h['competencia'] ?? '—'); ?></td>
                        <td><?php echo bo_money((float) ($h['valor'] ?? 0)); ?></td>
                        <td><?php echo $ofH(ucfirst((string) ($h['tipo'] ?? '—'))); ?></td>
                        <td><?php echo bo_money((float) ($h['cashback'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profHistorico) ? '' : 'style="display:none"'; ?>><td colspan="4">Nenhum registro encontrado.</td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFISSIONAL · Alunos ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="alunos">
    <div class="bo-page-title">
        <div><h1>Alunos</h1><p>Cadastre, edite ou remova vínculos com alunos já registrados.</p></div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofAlunoModal" data-of-new-student>
            <i class="bi bi-plus-lg"></i> Adicionar aluno
        </button>
    </div>
    <div class="bo-filters">
        <input type="search" class="form-control" style="max-width:300px" placeholder="Buscar aluno" data-bo-filter="search" data-bo-target="profAlunos">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="profAlunos">
            <option value="">Todos os status</option><option value="ativo">Ativo</option><option value="inativo">Inativo</option>
        </select>
    </div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profAlunos">
            <thead><tr><th>Nome</th><th>Plano</th><th>Status</th><th>Valor</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($profAlunos as $a): ?>
                    <tr data-search="<?php echo $ofH(strtolower(($a['nome'] ?? '') . ' ' . ($a['email'] ?? '') . ' ' . ($a['plano'] ?? ''))); ?>"
                        data-status="<?php echo $ofH($a['status']); ?>">
                        <td>
                            <?php echo $ofH($a['nome']); ?>
                            <small class="d-block text-secondary"><?php echo $ofH($a['email'] ?? ''); ?></small>
                        </td>
                        <td><?php echo $ofH($a['plano'] ?? 'Sem plano'); ?></td>
                        <td><?php echo bo_badge($a['status'] === 'ativo'); ?></td>
                        <td><?php echo bo_money((float) ($a['valor'] ?? 0)); ?></td>
                        <td><div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar vínculo"
                                data-bs-toggle="modal" data-bs-target="#ofAlunoModal" data-of-edit-student
                                data-id="<?php echo (int) $a['id_vinculo']; ?>"
                                data-name="<?php echo $ofH($a['nome']); ?>"
                                data-status="<?php echo $ofH($a['status']); ?>"
                                data-observation="<?php echo $ofH($a['observacao'] ?? ''); ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" action="?section=alunos" class="d-inline" data-of-confirm="Remover este aluno da sua lista?">
                                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                                <input type="hidden" name="prof_action" value="excluir_vinculo">
                                <input type="hidden" name="id_vinculo" value="<?php echo (int) $a['id_vinculo']; ?>">
                                <button type="submit" class="btn-bo-icon danger" title="Excluir vínculo"><i class="bi bi-trash"></i></button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profAlunos) ? '' : 'style="display:none"'; ?>><td colspan="5">Nenhum aluno vinculado.</td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFISSIONAL · Agenda ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="agenda">
    <div class="bo-page-title">
        <div><h1>Agenda</h1><p>Cadastre horários disponíveis e gerencie os agendamentos.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bs-toggle="modal" data-bs-target="#ofDisponibilidadeModal"><i class="bi bi-calendar-plus"></i> Horário disponível</button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofAgendamentoModal"><i class="bi bi-plus-lg"></i> Agendar</button>
        </div>
    </div>
    <div class="bo-filters"><div class="bo-daterange">
        De <input type="date" class="form-control" data-of-date-from>
        até <input type="date" class="form-control" data-of-date-to>
    </div></div>

    <div class="bo-section-heading">Agendados</div>
    <div data-of-agenda-list>
        <?php foreach ($profAgendados as $ag): ?>
            <div class="bo-agenda-card" data-of-agenda-card data-date="<?php echo $ofH($ag['data_evento']); ?>">
                <div>
                    <div class="bo-agenda-title"><?php echo $ofH($ag['aluno']); ?> · <?php echo $ofH($ag['modalidade']); ?></div>
                    <div class="bo-agenda-sub">
                        <?php echo $ofH($ag['contato']); ?> · <?php echo $ofH($ag['data']); ?>
                        <?php if (!empty($ag['local'])): ?> · <?php echo $ofH($ag['local']); ?><?php endif; ?>
                    </div>
                </div>
                <form method="post" action="?section=agenda" data-of-confirm="Cancelar este agendamento?">
                    <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                    <input type="hidden" name="prof_action" value="cancelar_agendamento">
                    <input type="hidden" name="id_agendamento" value="<?php echo (int) $ag['id_agendamento']; ?>">
                    <button type="submit" class="btn-bo-icon danger" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (empty($profAgendados)): ?><p class="text-secondary">Nenhum agendamento ativo.</p><?php endif; ?>
    </div>

    <div class="bo-section-heading">Disponíveis</div>
    <div data-of-agenda-list>
        <?php foreach ($profDisponiveis as $d): ?>
            <div class="bo-agenda-card disponivel" data-of-agenda-card data-date="<?php echo $ofH($d['data_evento']); ?>">
                <div>
                    <div class="bo-agenda-title"><?php echo $ofH($d['modalidade']); ?></div>
                    <div class="bo-agenda-sub">
                        <?php echo $ofH($d['data']); ?><?php if (!empty($d['local'])): ?> · <?php echo $ofH($d['local']); ?><?php endif; ?>
                    </div>
                </div>
                <form method="post" action="?section=agenda" data-of-confirm="Remover este horário disponível?">
                    <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                    <input type="hidden" name="prof_action" value="remover_disponibilidade">
                    <input type="hidden" name="id_disponibilidade" value="<?php echo (int) $d['id_disponibilidade']; ?>">
                    <button type="submit" class="btn-bo-icon danger" title="Remover"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (empty($profDisponiveis)): ?><p class="text-secondary">Nenhum horário disponível cadastrado.</p><?php endif; ?>
    </div>
</section>

<!-- ===== PROFISSIONAL · Cashback ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="cashback">
    <div class="bo-page-title">
        <div><h1>Meu cashback</h1><p>Saldo disponível e histórico de créditos e débitos.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bo-export="profCashback"><i class="bi bi-download"></i> Exportar</button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofCashbackModal"><i class="bi bi-wallet2"></i> Utilizar cashback</button>
        </div>
    </div>
    <div class="row g-3 mb-3"><div class="col-12 col-md-4"><div class="bo-card">
        <div class="bo-card-label">Saldo total</div>
        <div class="bo-card-value"><?php echo bo_money((float) ($profContrato['saldoCashback'] ?? 0)); ?></div>
    </div></div></div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profCashback">
            <thead><tr><th>Data</th><th>Descrição</th><th>Valor</th></tr></thead>
            <tbody>
                <?php foreach ($profCashbackHistorico as $h): ?>
                    <tr><td><?php echo $ofH($h['data']); ?></td><td><?php echo $ofH($h['descricao']); ?></td><td><?php echo bo_money((float) $h['valor']); ?></td></tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profCashbackHistorico) ? '' : 'style="display:none"'; ?>><td colspan="3">Nenhum registro encontrado.</td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFISSIONAL · Compras ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="compras">
    <div class="bo-page-title"><div><h1>Minhas compras</h1><p>Acompanhamento e histórico dos seus pedidos.</p></div></div>
    <div class="row g-3 mb-3"><div class="col-12 col-md-4"><div class="bo-card">
        <div class="bo-card-label">Total de pedidos</div>
        <div class="bo-card-value"><?php echo count($profPedidos ?? []) + count($profPedidosHistorico ?? []); ?></div>
    </div></div></div>

    <div class="bo-section-heading">Acompanhamento de pedido</div>
    <div class="bo-table-wrap"><div class="table-responsive"><table class="bo-table">
        <thead><tr><th>ID transação</th><th>Produto</th><th>Quantidade</th><th>Valor</th><th>Status</th><th>Recebimento</th></tr></thead>
        <tbody>
            <?php foreach (($profPedidos ?? []) as $ped): ?>
                <tr>
                    <td><?php echo $ofH($ped['transacao']); ?></td>
                    <td><?php foreach ($ped['itens'] as $it): ?><div><?php echo (int) $it['quantidade']; ?>x <?php echo $ofH($it['produto']); ?> — <small>Vendido por: <?php echo $ofH($it['vendedor']); ?> · <?php echo $ofH($it['statusLogistica']); ?></small></div><?php endforeach; ?></td>
                    <td><?php echo array_sum(array_column($ped['itens'], 'quantidade')); ?></td><td><?php echo bo_money((float) $ped['valor']); ?></td>
                    <td><?php echo $ofH(ucfirst((string) $ped['status'])); ?></td>
                    <td>
                        <?php foreach ($ped['itens'] as $it): ?>
                            <?php if ($it['statusLogisticaBanco'] === 'despachado'): ?>
                                <form method="POST" action="<?php echo bo_form_action('meus-pedidos.php'); ?>" class="bo-inline-form">
                                    <?php echo bo_csrf_field(); ?>
                                    <?php echo bo_hidden('secao', 'compras'); ?>
                                    <?php echo bo_hidden('acao', 'confirmar-recebimento'); ?>
                                    <?php echo bo_hidden('id_item', $it['idItem']); ?>
                                    <button type="submit" class="btn-bo-outline btn-sm">Confirmar recebimento</button>
                                </form>
                            <?php elseif ($it['confirmadoRecebimento']): ?>
                                <small>Recebido em <?php echo $ofH($it['confirmadoRecebimentoEm']); ?></small>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($profPedidos)): ?><tr><td colspan="6">Nenhum pedido em andamento.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>

    <div class="bo-section-heading">Histórico de compra</div>
    <div class="bo-table-wrap"><div class="table-responsive"><table class="bo-table">
        <thead><tr><th>ID transação</th><th>Data/hora</th><th>Produto</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach (($profPedidosHistorico ?? []) as $ped): ?>
                <tr>
                    <td><?php echo $ofH($ped['transacao']); ?></td><td><?php echo $ofH($ped['data']); ?></td>
                    <td><?php foreach ($ped['itens'] as $it): ?><div><?php echo (int) $it['quantidade']; ?>x <?php echo $ofH($it['produto']); ?> — <small>Vendido por: <?php echo $ofH($it['vendedor']); ?></small></div><?php endforeach; ?></td>
                    <td><?php echo $ofH(ucfirst((string) $ped['status'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($profPedidosHistorico)): ?><tr><td colspan="4">Nenhuma compra no histórico.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</section>

<!-- Modal: vínculo de aluno -->
<div class="modal fade" id="ofAlunoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=alunos" id="ofAlunoForm">
            <div class="modal-header">
                <h5 class="modal-title" id="ofAlunoModalTitle">Adicionar aluno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="vincular_aluno" id="ofAlunoAction">
                <input type="hidden" name="id_vinculo" value="" id="ofAlunoVinculo">
                <div class="mb-3" id="ofAlunoSelectWrap">
                    <label class="form-label" for="ofAlunoSelect">Aluno</label>
                    <select class="form-select" name="id_aluno" id="ofAlunoSelect">
                        <option value="">Selecione</option>
                        <?php foreach ($ofAlunosDisponiveis as $aluno): ?>
                            <option value="<?php echo (int) $aluno['id_usuario']; ?>"><?php echo $ofH($aluno['nome'] . ' · ' . $aluno['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($ofAlunosDisponiveis)): ?><small class="text-secondary">Todos os alunos ativos já estão vinculados.</small><?php endif; ?>
                </div>
                <div class="mb-3 d-none" id="ofAlunoNameWrap">
                    <label class="form-label">Aluno</label><input type="text" class="form-control" id="ofAlunoName" readonly>
                </div>
                <div class="mb-3 d-none" id="ofAlunoStatusWrap">
                    <label class="form-label" for="ofAlunoStatus">Status do vínculo</label>
                    <select class="form-select" name="status" id="ofAlunoStatus"><option value="ativo">Ativo</option><option value="inativo">Inativo</option></select>
                </div>
                <div>
                    <label class="form-label" for="ofAlunoObservacao">Observação</label>
                    <textarea class="form-control" name="observacao" id="ofAlunoObservacao" rows="3" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">Salvar</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal: disponibilidade -->
<div class="modal fade" id="ofDisponibilidadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=agenda">
            <div class="modal-header"><h5 class="modal-title">Novo horário disponível</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="criar_disponibilidade">
                <div class="col-12"><label class="form-label">Modalidade</label><input class="form-control" name="modalidade" maxlength="100" required></div>
                <div class="col-12 col-md-6"><label class="form-label">Data</label><input type="date" class="form-control" name="data_evento" min="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Início</label><input type="time" class="form-control" name="hora_inicio" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Fim</label><input type="time" class="form-control" name="hora_fim" required></div>
                <div class="col-12"><label class="form-label">Local</label><input class="form-control" name="local" maxlength="120"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Cadastrar</button></div>
        </form>
    </div></div>
</div>

<!-- Modal: agendamento -->
<div class="modal fade" id="ofAgendamentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=agenda">
            <div class="modal-header"><h5 class="modal-title">Novo agendamento</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="agendar">
                <input type="hidden" name="operacao_agendamento" value="<?php echo $ofH($ofOperacaoAgendamento); ?>">
                <div class="col-12">
                    <label class="form-label">Aluno</label>
                    <select class="form-select" name="id_aluno" required>
                        <option value="">Selecione</option>
                        <?php foreach ($profAlunos as $a): ?><?php if ($a['status'] === 'ativo'): ?>
                            <option value="<?php echo (int) $a['id_aluno']; ?>"><?php echo $ofH($a['nome']); ?></option>
                        <?php endif; ?><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-8"><label class="form-label">Título</label><input class="form-control" name="titulo" maxlength="150" required></div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo"><option value="aula">Aula</option><option value="personal">Personal</option><option value="avaliacao">Avaliação</option><option value="consulta">Consulta</option><option value="reuniao">Reunião</option><option value="outro">Outro</option></select>
                </div>
                <div class="col-12 col-md-6"><label class="form-label">Data</label><input type="date" class="form-control" name="data_evento" min="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Início</label><input type="time" class="form-control" name="hora_inicio" required></div>
                <div class="col-6 col-md-3"><label class="form-label">Fim</label><input type="time" class="form-control" name="hora_fim"></div>
                <div class="col-12"><label class="form-label">Local</label><input class="form-control" name="local" maxlength="120"></div>
                <div class="col-12"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="2" maxlength="255"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Agendar</button></div>
        </form>
    </div></div>
</div>

<!-- Modal: cashback -->
<div class="modal fade" id="ofCashbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=cashback">
            <div class="modal-header"><h5 class="modal-title">Utilizar cashback</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="usar_cashback">
                <label class="form-label">Valor</label>
                <input type="number" class="form-control" name="valor" min="0.01" step="0.01"
                    max="<?php echo $ofH(number_format((float) ($profContrato['saldoCashback'] ?? 0), 2, '.', '')); ?>" required>
                <small class="text-secondary">Saldo disponível: <?php echo bo_money((float) ($profContrato['saldoCashback'] ?? 0)); ?></small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning">Confirmar uso</button></div>
        </form>
    </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-of-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.getAttribute('data-of-confirm'))) event.preventDefault();
        });
    });

    const alunoModal = document.getElementById('ofAlunoModal');
    const alunoAction = document.getElementById('ofAlunoAction');
    const alunoVinculo = document.getElementById('ofAlunoVinculo');
    const alunoSelect = document.getElementById('ofAlunoSelect');
    const alunoSelectWrap = document.getElementById('ofAlunoSelectWrap');
    const alunoNameWrap = document.getElementById('ofAlunoNameWrap');
    const alunoStatusWrap = document.getElementById('ofAlunoStatusWrap');
    const alunoName = document.getElementById('ofAlunoName');
    const alunoStatus = document.getElementById('ofAlunoStatus');
    const alunoObservacao = document.getElementById('ofAlunoObservacao');

    alunoModal?.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const editing = button?.hasAttribute('data-of-edit-student');
        document.getElementById('ofAlunoModalTitle').textContent = editing ? 'Editar vínculo' : 'Adicionar aluno';
        alunoAction.value = editing ? 'atualizar_vinculo' : 'vincular_aluno';
        alunoVinculo.value = editing ? button.dataset.id : '';
        alunoName.value = editing ? button.dataset.name : '';
        alunoStatus.value = editing ? button.dataset.status : 'ativo';
        alunoObservacao.value = editing ? button.dataset.observation : '';
        alunoSelect.value = '';
        alunoSelect.required = !editing;
        alunoSelectWrap.classList.toggle('d-none', editing);
        alunoNameWrap.classList.toggle('d-none', !editing);
        alunoStatusWrap.classList.toggle('d-none', !editing);
    });

    const from = document.querySelector('[data-of-date-from]');
    const to = document.querySelector('[data-of-date-to]');
    const filterAgenda = () => {
        document.querySelectorAll('[data-of-agenda-card]').forEach((card) => {
            const date = card.dataset.date || '';
            card.style.display = (!from.value || date >= from.value) && (!to.value || date <= to.value) ? '' : 'none';
        });
    };
    from?.addEventListener('change', filterAgenda);
    to?.addEventListener('change', filterAgenda);
});
</script>
