<?php
/**
 * Área do professor ligada ao banco: Agenda e Treino (Meu perfil e
 * Configurações vêm de section-configuracoes.php).
 *
 * As ações (POST com prof_action) são tratadas aqui, depois que dashboard.php
 * confirmou no banco que a sessão é de um professor. Cada uma age só sobre
 * dados do próprio cadastro profissional: vínculos, horários, agendamentos e
 * exercícios acrescentados à ficha de alunos com vínculo ativo.
 */

$ofH = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

require_once __DIR__ . '/../includes/agenda-service.php';

$ofFeedback = null;
$ofIdUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$ofIdProfissional = 0;
// Situação na agenda (conta, cadastro e disponibilidade definida pela administração), só para avisos.
$ofSituacaoAgenda = ['recebe' => false, 'motivo' => 'sem_cadastro'];
$ofMotivosBloqueio = [
    'indisponivel' => 'A administração marcou você como indisponível para novos agendamentos. Os agendamentos já marcados continuam valendo.',
    'cadastro_inativo' => 'Seu cadastro profissional está inativo: novos agendamentos estão bloqueados. Os agendamentos já marcados continuam valendo.',
    'conta_inativa' => 'Sua conta está inativa: novos agendamentos estão bloqueados.',
];
$ofAlunosDisponiveis = [];
$ofAlunosVinculados = [];
$profAlunos = [];
$profAgendados = [];
$profDisponiveis = [];
// Ficha de treino de cada aluno com vínculo ativo, por id do aluno.
$ofTreinos = [];

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Conexão com o banco indisponível.');
    }

    // Só o vínculo explícito (id_usuario), feito pelo admin na tela
    // Funcionários. Abrir esta página nunca liga cadastros por e-mail.
    $stmt = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $ofIdUsuario);
    $stmt->execute();
    $ofCadastroProfissional = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ofCadastroProfissional) {
        $ofFeedback = ['type' => 'warning', 'message' => 'Esta conta ainda não tem cadastro profissional vinculado. Peça ao administrador para vinculá-lo na tela Funcionários.'];
        $ofIdProfissional = 0;
    } else {
        $ofIdProfissional = (int) $ofCadastroProfissional['id_profissional'];
        $ofSituacaoAgenda = bo_professor_situacao_por_id($conn, $ofIdProfissional) ?? $ofSituacaoAgenda;
    }

    if ($ofIdProfissional === 0) {
        // Sem cadastro profissional: nenhuma ação e nenhum dado de alunos.
    } else {

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

            // Mesma trava, transação e regra do professor da agenda do aluno
            // (bo_agenda_transacao + bo_agenda_inserir): indisponível, cadastro
            // inativo ou conta inativa não recebem agendamento por aqui também.
            try {
                $proximoId = bo_agenda_transacao($conn, static function () use ($conn, $ofIdProfissional, $idAluno, $titulo, $tipo, $dataEvento, $horaInicio, $horaFim, $local, $observacao): int {
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

                    return bo_agenda_inserir($conn, ['id_usuario' => $idAluno, 'id_profissional' => $ofIdProfissional, 'titulo' => $titulo, 'tipo' => $tipo,
                        'data_evento' => $dataEvento, 'hora_inicio' => $horaInicio, 'hora_fim' => $horaFim, 'local' => $local,
                        'observacao' => $observacao, 'status' => 'agendado']);
                });
            } catch (AgendamentoRecusado $recusa) {
                // Recusa pela regra do professor: explica o motivo com a situação atual.
                $ofSituacaoAgenda = bo_professor_situacao_por_id($conn, $ofIdProfissional) ?? $ofSituacaoAgenda;
                throw isset($ofMotivosBloqueio[$ofSituacaoAgenda['motivo']]) ? new RuntimeException($ofMotivosBloqueio[$ofSituacaoAgenda['motivo']]) : $recusa;
            }
            unset($_SESSION['operacoes_agendamento'][$operacaoAgendamento]);
            // Agendamento já confirmado no banco. A falha secundária não desfaz a aula.
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
                    'Seu agendamento de ' . onefitData('d/m/Y', strtotime($agendamentoCancelado['data_evento']))
                    . ' às ' . substr($agendamentoCancelado['hora_inicio'], 0, 5) . ' foi cancelado.', 'agendamento');
            } catch (Throwable $erroNotificacao) {
                error_log('ONE FIT: falha ao notificar agendamento cancelado #' . $idAgendamento . '; código ' . $erroNotificacao->getCode());
            }
            $ofFeedback = ['type' => 'success', 'message' => 'Agendamento cancelado.'];
        } elseif ($ofAction === 'adicionar_treino') {
            // Só para aluno ativo com vínculo ativo com este professor, conferido no
            // banco a cada envio. O professor só acrescenta exercícios: editar,
            // excluir e limpar a ficha continuam com o próprio aluno.
            $idAluno = filter_input(INPUT_POST, 'id_aluno', FILTER_VALIDATE_INT);
            if (!$idAluno) {
                throw new RuntimeException('Selecione um aluno vinculado e ativo.');
            }
            $stmt = $conn->prepare(
                "SELECT pa.id_vinculo FROM profissional_aluno pa
                 JOIN usuarios u ON u.id_usuario = pa.id_aluno
                 WHERE pa.id_profissional = ? AND pa.id_aluno = ? AND pa.status = 'ativo'
                   AND u.tipo_usuario = 'aluno' AND u.status = 'ativo' LIMIT 1"
            );
            $stmt->bind_param('ii', $ofIdProfissional, $idAluno);
            $stmt->execute();
            $vinculoAtivo = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$vinculoAtivo) {
                throw new RuntimeException('Selecione um aluno vinculado e ativo.');
            }
            try {
                // Mesma validação e mesma proteção contra envio repetido (token) da ficha do aluno.
                bo_treino_alterar($conn, $idAluno, [
                    'acao' => 'salvar',
                    'id' => 0,
                    'token' => $_POST['token'] ?? '',
                    'dia_semana' => $_POST['dia_semana'] ?? '',
                    'nome' => $_POST['nome'] ?? '',
                    'series' => $_POST['series'] ?? null,
                    'repeticoes' => $_POST['repeticoes'] ?? null,
                    'carga' => $_POST['carga'] ?? null,
                ]);
            } catch (DomainException $erroTreino) {
                throw new RuntimeException($erroTreino->getMessage());
            }
            $ofFeedback = ['type' => 'success', 'message' => 'Exercício adicionado ao treino do aluno.'];
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    }

    // Recarrega os dados alteráveis para mostrar o resultado na mesma resposta.
    $stmt = $conn->prepare(
        "SELECT pa.id_vinculo, pa.id_aluno, pa.status, pa.observacao,
                u.nome, u.email, u.celular,
                COALESCE(cp.nome, 'Sem plano') AS plano
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
    foreach ($ofAlunosVinculados as $vinculo) {
        if ($vinculo['status'] === 'ativo') {
            $ofTreinos[(int) $vinculo['id_aluno']] = bo_treino_carregar($conn, (int) $vinculo['id_aluno']);
        }
    }

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
        $row['data'] = onefitData('d/m/Y', strtotime($row['data_evento'])) . ' ' . substr($row['hora_inicio'], 0, 5);
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
        $row['data'] = onefitData('d/m/Y', strtotime($row['data_evento']))
            . ' ' . substr($row['hora_inicio'], 0, 5)
            . ' às ' . substr($row['hora_fim'], 0, 5);
        $profDisponiveis[] = $row;
    }
    $stmt->close();
    }
} catch (Throwable $e) {
    // Mensagens próprias (RuntimeException) aparecem; erro de banco não expõe SQL nem tabelas.
    $ehMensagemPropria = $e instanceof RuntimeException && !($e instanceof mysqli_sql_exception);
    if (!$ehMensagemPropria) {
        error_log('ONE FIT: falha no painel do profissional. Código: ' . $e->getCode());
    }
    $ofFeedback = ['type' => 'danger', 'message' => $ehMensagemPropria ? $e->getMessage() : 'Não foi possível concluir a operação. Tente novamente.'];
}

$ofCsrf = (string) ($_SESSION['csrf_token'] ?? '');
// Tokens por formulário permitem abas simultâneas e bloqueiam reenvios já concluídos.
$_SESSION['operacoes_agendamento'] = array_slice($_SESSION['operacoes_agendamento'] ?? [], -49, null, true);
$ofOperacaoAgendamento = bin2hex(random_bytes(16));
$_SESSION['operacoes_agendamento'][$ofOperacaoAgendamento] = true;
?>

<?php if ($ofFeedback): ?>
    <div class="alert alert-<?php echo $ofH($ofFeedback['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo of_t($ofFeedback['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo of_t('Fechar'); ?>"></button>
    </div>
<?php endif; ?>

<!-- ===== PROFESSOR · Treino (alunos vinculados e ficha de cada um) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="treino">
    <div class="bo-page-title">
        <div><h1><?php echo of_t('Treino'); ?></h1><p><?php echo of_t('Consulte os seus alunos e adicione exercícios à ficha de treino de cada um.'); ?></p></div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofAlunoModal" data-of-new-student>
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Adicionar aluno'); ?>
        </button>
    </div>
    <div class="bo-filters">
        <input type="search" class="form-control" style="max-width:300px" placeholder="<?php echo of_t('Buscar aluno'); ?>" data-bo-filter="search" data-bo-target="profAlunos">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="profAlunos">
            <option value=""><?php echo of_t('Todos os status'); ?></option><option value="ativo"><?php echo of_t('Ativo'); ?></option><option value="inativo"><?php echo of_t('Inativo'); ?></option>
        </select>
    </div>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table" data-bo-table="profAlunos">
            <thead><tr><th><?php echo of_t('Nome'); ?></th><th><?php echo of_t('Plano'); ?></th><th><?php echo of_t('Status'); ?></th><th><?php echo of_t('Exercícios'); ?></th><th><?php echo of_t('Ações'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($profAlunos as $a): ?>
                    <tr data-search="<?php echo $ofH(strtolower(($a['nome'] ?? '') . ' ' . ($a['email'] ?? '') . ' ' . ($a['plano'] ?? ''))); ?>"
                        data-status="<?php echo $ofH($a['status']); ?>">
                        <td>
                            <?php echo $ofH($a['nome']); ?>
                            <small class="d-block text-secondary"><?php echo $ofH($a['email'] ?? ''); ?></small>
                        </td>
                        <td><?php echo $ofH($a['plano'] ?? onefitTraduzir('Sem plano')); ?></td>
                        <td><?php echo bo_badge($a['status'] === 'ativo'); ?></td>
                        <td><?php echo $a['status'] === 'ativo' ? count($ofTreinos[(int) $a['id_aluno']] ?? []) : '—'; ?></td>
                        <td><div class="bo-table-actions">
                            <?php if ($a['status'] === 'ativo'): ?>
                                <button type="button" class="btn-bo-icon" title="<?php echo of_t('Treino do aluno'); ?>"
                                    data-bs-toggle="modal" data-bs-target="#ofTreinoModal<?php echo (int) $a['id_aluno']; ?>">
                                    <i class="bi bi-lightning-charge"></i>
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar vínculo'); ?>"
                                data-bs-toggle="modal" data-bs-target="#ofAlunoModal" data-of-edit-student
                                data-id="<?php echo (int) $a['id_vinculo']; ?>"
                                data-name="<?php echo $ofH($a['nome']); ?>"
                                data-status="<?php echo $ofH($a['status']); ?>"
                                data-observation="<?php echo $ofH($a['observacao'] ?? ''); ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" action="?section=treino" class="d-inline" data-of-confirm="<?php echo of_t('Remover este aluno da sua lista?'); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                                <input type="hidden" name="prof_action" value="excluir_vinculo">
                                <input type="hidden" name="id_vinculo" value="<?php echo (int) $a['id_vinculo']; ?>">
                                <button type="submit" class="btn-bo-icon danger" title="<?php echo of_t('Excluir vínculo'); ?>"><i class="bi bi-trash"></i></button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bo-empty-row" <?php echo empty($profAlunos) ? '' : 'style="display:none"'; ?>><td colspan="5"><?php echo of_t('Nenhum aluno vinculado.'); ?></td></tr>
            </tbody>
        </table>
    </div></div>
</section>

<!-- ===== PROFESSOR · Agenda ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="agenda">
    <div class="bo-page-title">
        <div><h1><?php echo of_t('Agenda'); ?></h1><p><?php echo of_t('Cadastre horários disponíveis e gerencie os agendamentos.'); ?></p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bs-toggle="modal" data-bs-target="#ofDisponibilidadeModal"><i class="bi bi-calendar-plus"></i> <?php echo of_t('Horário disponível'); ?></button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#ofAgendamentoModal" <?php echo $ofSituacaoAgenda['recebe'] ? '' : 'disabled'; ?>><i class="bi bi-plus-lg"></i> <?php echo of_t('Agendar'); ?></button>
        </div>
    </div>
    <?php if ($ofIdProfissional > 0 && isset($ofMotivosBloqueio[$ofSituacaoAgenda['motivo']])): ?>
        <!-- Só aviso: quem decide é o servidor (bo_agenda_inserir), em todo agendamento novo. -->
        <div class="bo-notice is-fixo" data-of-agenda-bloqueada>
            <i class="bi bi-calendar-x"></i>
            <div>
                <strong><?php echo of_t('Novos agendamentos bloqueados'); ?></strong>
                <span><?php echo of_t($ofMotivosBloqueio[$ofSituacaoAgenda['motivo']]); ?></span>
            </div>
        </div>
    <?php endif; ?>
    <div class="bo-filters"><div class="bo-daterange">
        <?php echo of_t('De'); ?> <input type="date" class="form-control" data-of-date-from>
        <?php echo of_t('até'); ?> <input type="date" class="form-control" data-of-date-to>
    </div></div>

    <div class="bo-section-heading"><?php echo of_t('Agendados'); ?></div>
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
                <form method="post" action="?section=agenda" data-of-confirm="<?php echo of_t('Cancelar este agendamento?'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                    <input type="hidden" name="prof_action" value="cancelar_agendamento">
                    <input type="hidden" name="id_agendamento" value="<?php echo (int) $ag['id_agendamento']; ?>">
                    <button type="submit" class="btn-bo-icon danger" title="<?php echo of_t('Cancelar'); ?>"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (empty($profAgendados)): ?><p class="text-secondary"><?php echo of_t('Nenhum agendamento ativo.'); ?></p><?php endif; ?>
    </div>

    <div class="bo-section-heading"><?php echo of_t('Disponíveis'); ?></div>
    <div data-of-agenda-list>
        <?php foreach ($profDisponiveis as $d): ?>
            <div class="bo-agenda-card disponivel" data-of-agenda-card data-date="<?php echo $ofH($d['data_evento']); ?>">
                <div>
                    <div class="bo-agenda-title"><?php echo $ofH($d['modalidade']); ?></div>
                    <div class="bo-agenda-sub">
                        <?php echo $ofH($d['data']); ?><?php if (!empty($d['local'])): ?> · <?php echo $ofH($d['local']); ?><?php endif; ?>
                    </div>
                </div>
                <form method="post" action="?section=agenda" data-of-confirm="<?php echo of_t('Remover este horário disponível?'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                    <input type="hidden" name="prof_action" value="remover_disponibilidade">
                    <input type="hidden" name="id_disponibilidade" value="<?php echo (int) $d['id_disponibilidade']; ?>">
                    <button type="submit" class="btn-bo-icon danger" title="<?php echo of_t('Remover'); ?>"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php if (empty($profDisponiveis)): ?><p class="text-secondary"><?php echo of_t('Nenhum horário disponível cadastrado.'); ?></p><?php endif; ?>
    </div>
</section>

<!-- Modal: vínculo de aluno -->
<div class="modal fade" id="ofAlunoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=treino" id="ofAlunoForm">
            <div class="modal-header">
                <h5 class="modal-title" id="ofAlunoModalTitle"><?php echo of_t('Adicionar aluno'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="vincular_aluno" id="ofAlunoAction">
                <input type="hidden" name="id_vinculo" value="" id="ofAlunoVinculo">
                <div class="mb-3" id="ofAlunoSelectWrap">
                    <label class="form-label" for="ofAlunoSelect"><?php echo of_t('Aluno'); ?></label>
                    <select class="form-select" name="id_aluno" id="ofAlunoSelect">
                        <option value=""><?php echo of_t('Selecione'); ?></option>
                        <?php foreach ($ofAlunosDisponiveis as $aluno): ?>
                            <option value="<?php echo (int) $aluno['id_usuario']; ?>"><?php echo $ofH($aluno['nome'] . ' · ' . $aluno['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($ofAlunosDisponiveis)): ?><small class="text-secondary"><?php echo of_t('Todos os alunos ativos já estão vinculados.'); ?></small><?php endif; ?>
                </div>
                <div class="mb-3 d-none" id="ofAlunoNameWrap">
                    <label class="form-label"><?php echo of_t('Aluno'); ?></label><input type="text" class="form-control" id="ofAlunoName" readonly>
                </div>
                <div class="mb-3 d-none" id="ofAlunoStatusWrap">
                    <label class="form-label" for="ofAlunoStatus"><?php echo of_t('Status do vínculo'); ?></label>
                    <select class="form-select" name="status" id="ofAlunoStatus"><option value="ativo"><?php echo of_t('Ativo'); ?></option><option value="inativo"><?php echo of_t('Inativo'); ?></option></select>
                </div>
                <div>
                    <label class="form-label" for="ofAlunoObservacao"><?php echo of_t('Observação'); ?></label>
                    <textarea class="form-control" name="observacao" id="ofAlunoObservacao" rows="3" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                <button type="submit" class="btn btn-warning"><?php echo of_t('Salvar'); ?></button>
            </div>
        </form>
    </div></div>
</div>

<!-- Modal: disponibilidade -->
<div class="modal fade" id="ofDisponibilidadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=agenda">
            <div class="modal-header"><h5 class="modal-title"><?php echo of_t('Novo horário disponível'); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="criar_disponibilidade">
                <div class="col-12"><label class="form-label"><?php echo of_t('Modalidade'); ?></label><input class="form-control" name="modalidade" maxlength="100" required></div>
                <div class="col-12 col-md-6"><label class="form-label"><?php echo of_t('Data'); ?></label><input type="date" class="form-control" name="data_evento" min="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label"><?php echo of_t('Início'); ?></label><input type="time" class="form-control" name="hora_inicio" required></div>
                <div class="col-6 col-md-3"><label class="form-label"><?php echo of_t('Fim'); ?></label><input type="time" class="form-control" name="hora_fim" required></div>
                <div class="col-12"><label class="form-label"><?php echo of_t('Local'); ?></label><input class="form-control" name="local" maxlength="120"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button><button type="submit" class="btn btn-warning"><?php echo of_t('Cadastrar'); ?></button></div>
        </form>
    </div></div>
</div>

<!-- Modal: agendamento -->
<div class="modal fade" id="ofAgendamentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="post" action="?section=agenda">
            <div class="modal-header"><h5 class="modal-title"><?php echo of_t('Novo agendamento'); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                <input type="hidden" name="prof_action" value="agendar">
                <input type="hidden" name="operacao_agendamento" value="<?php echo $ofH($ofOperacaoAgendamento); ?>">
                <div class="col-12">
                    <label class="form-label"><?php echo of_t('Aluno'); ?></label>
                    <select class="form-select" name="id_aluno" required>
                        <option value=""><?php echo of_t('Selecione'); ?></option>
                        <?php foreach ($profAlunos as $a): ?><?php if ($a['status'] === 'ativo'): ?>
                            <option value="<?php echo (int) $a['id_aluno']; ?>"><?php echo $ofH($a['nome']); ?></option>
                        <?php endif; ?><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-8"><label class="form-label"><?php echo of_t('Título'); ?></label><input class="form-control" name="titulo" maxlength="150" required></div>
                <div class="col-12 col-md-4">
                    <label class="form-label"><?php echo of_t('Tipo'); ?></label>
                    <select class="form-select" name="tipo"><option value="aula"><?php echo of_t('Aula'); ?></option><option value="personal"><?php echo of_t('Personal'); ?></option><option value="avaliacao"><?php echo of_t('Avaliação'); ?></option><option value="consulta"><?php echo of_t('Consulta'); ?></option><option value="reuniao"><?php echo of_t('Reunião'); ?></option><option value="outro"><?php echo of_t('Outro'); ?></option></select>
                </div>
                <div class="col-12 col-md-6"><label class="form-label"><?php echo of_t('Data'); ?></label><input type="date" class="form-control" name="data_evento" min="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label"><?php echo of_t('Início'); ?></label><input type="time" class="form-control" name="hora_inicio" required></div>
                <div class="col-6 col-md-3"><label class="form-label"><?php echo of_t('Fim'); ?></label><input type="time" class="form-control" name="hora_fim"></div>
                <div class="col-12"><label class="form-label"><?php echo of_t('Local'); ?></label><input class="form-control" name="local" maxlength="120"></div>
                <div class="col-12"><label class="form-label"><?php echo of_t('Observação'); ?></label><textarea class="form-control" name="observacao" rows="2" maxlength="255"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button><button type="submit" class="btn btn-warning" <?php echo $ofSituacaoAgenda['recebe'] ? '' : 'disabled'; ?>><?php echo of_t('Agendar'); ?></button></div>
        </form>
    </div></div>
</div>

<!-- Modais: ficha de treino de cada aluno com vínculo ativo (consulta e inclusão de exercício) -->
<?php foreach ($profAlunos as $a): ?>
    <?php if ($a['status'] !== 'ativo') continue; ?>
    <?php $ofIdAlunoTreino = (int) $a['id_aluno']; $ofFicha = $ofTreinos[$ofIdAlunoTreino] ?? []; ?>
    <div class="modal fade" id="ofTreinoModal<?php echo $ofIdAlunoTreino; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
            <form method="post" action="?section=treino">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo of_t('Treino de {nome}', ['{nome}' => $a['nome']]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo of_t('Fechar'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="bo-section-heading"><?php echo of_t('Ficha atual'); ?></div>
                    <div class="bo-table-wrap"><div class="table-responsive">
                        <table class="bo-table">
                            <thead><tr><th><?php echo of_t('Dia da semana'); ?></th><th><?php echo of_t('Exercício'); ?></th><th><?php echo of_t('Séries'); ?></th><th><?php echo of_t('Repetições'); ?></th><th><?php echo of_t('Carga'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($ofFicha as $exercicio): ?>
                                    <tr>
                                        <td><?php echo of_t(bo_treino_dias()[$exercicio['dia_semana']] ?? '—'); ?></td>
                                        <td><?php echo of_t($exercicio['nome']); ?></td>
                                        <td><?php echo (int) $exercicio['series']; ?></td>
                                        <td><?php echo (int) $exercicio['repeticoes']; ?></td>
                                        <td><?php echo (int) $exercicio['carga']; ?> kg</td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$ofFicha): ?><tr><td colspan="5"><?php echo of_t('Nenhum exercício na ficha.'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div></div>

                    <div class="bo-section-heading"><?php echo of_t('Adicionar exercício'); ?></div>
                    <div class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $ofH($ofCsrf); ?>">
                        <input type="hidden" name="prof_action" value="adicionar_treino">
                        <input type="hidden" name="id_aluno" value="<?php echo $ofIdAlunoTreino; ?>">
                        <?php // Token do envio: reenviar o mesmo formulário não duplica o exercício. ?>
                        <input type="hidden" name="token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="ofTreinoDia<?php echo $ofIdAlunoTreino; ?>"><?php echo of_t('Dia da semana'); ?></label>
                            <select class="form-select" name="dia_semana" id="ofTreinoDia<?php echo $ofIdAlunoTreino; ?>" required>
                                <?php foreach (bo_treino_dias() as $valor => $dia): ?>
                                    <option value="<?php echo $valor; ?>"><?php echo of_t($dia); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="ofTreinoNome<?php echo $ofIdAlunoTreino; ?>"><?php echo of_t('Exercício'); ?></label>
                            <select class="form-select" name="nome" id="ofTreinoNome<?php echo $ofIdAlunoTreino; ?>" required>
                                <option value=""><?php echo of_t('Selecione um exercício'); ?></option>
                                <?php foreach (bo_treino_catalogo() as $grupo => $nomes): ?>
                                    <optgroup label="<?php echo of_t($grupo); ?>">
                                        <?php foreach ($nomes as $nome): ?><option value="<?php echo $ofH($nome); ?>"><?php echo of_t($nome); ?></option><?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php foreach (['series' => ['Séries', 1, 10, 3], 'repeticoes' => ['Repetições', 1, 50, 12], 'carga' => ['Carga (kg)', 0, 300, 0]] as $campo => [$rotulo, $minimo, $maximo, $padrao]): ?>
                            <div class="col-12 col-sm-4">
                                <label class="form-label" for="ofTreino-<?php echo $campo . $ofIdAlunoTreino; ?>"><?php echo of_t($rotulo); ?></label>
                                <input type="number" class="form-control" name="<?php echo $campo; ?>" id="ofTreino-<?php echo $campo . $ofIdAlunoTreino; ?>"
                                    min="<?php echo $minimo; ?>" max="<?php echo $maximo; ?>" step="1" value="<?php echo $padrao; ?>" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo of_t('Cancelar'); ?></button>
                    <button type="submit" class="btn btn-warning"><?php echo of_t('Adicionar exercício'); ?></button>
                </div>
            </form>
        </div></div>
    </div>
<?php endforeach; ?>

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
        document.getElementById('ofAlunoModalTitle').textContent = editing ? ofT('Editar vínculo') : ofT('Adicionar aluno');
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
