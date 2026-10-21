<?php
$bo_papeis_permitidos = ['recepcionista'];
require __DIR__ . '/_shared.php';
require_once __DIR__ . '/../includes/agenda-service.php';
require_once __DIR__ . '/../includes/funcionarios.php';
bo_check_csrf();

function bo_recepcao_agenda_aluno_ativo(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT id_usuario, nome FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'aluno' AND status = 'ativo' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $aluno = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $aluno ?: null;
}

function bo_recepcao_agenda_data_valida(string $valor): bool
{
    $tz = new DateTimeZone('America/Sao_Paulo');
    $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $tz);
    if (!$dia || $dia->format('Y-m-d') !== $valor) {
        return false;
    }
    $hoje = bo_agenda_now()->setTime(0, 0);
    return $dia >= $hoje && $dia <= $hoje->modify('+31 days');
}

$secao = bo_secao_atual();
$acao = bo_str('acao');

if ($acao === 'buscar_aluno') {
    $busca = trim((string) ($_POST['aluno_busca'] ?? ''));
    $digitos = preg_replace('/\D+/', '', $busca) ?? '';
    $resultados = [];
    unset($_SESSION['bo_recepcao_agenda_aluno'], $_SESSION['bo_recepcao_agenda_data']);
    if ($busca === '') {
        bo_flash('error', 'Informe o nome, CPF ou ID do aluno.');
    } elseif (strlen($digitos) === 11 && bo_cpf_valido($digitos)) {
        $stmt = $conn->prepare(
            "SELECT id_usuario, nome FROM usuarios
             WHERE tipo_usuario = 'aluno' AND status = 'ativo'
               AND REPLACE(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', ''), '/', '') = ?
             LIMIT 20"
        );
        $stmt->bind_param('s', $digitos);
        $stmt->execute();
        $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif (ctype_digit($busca)) {
        $id = (int) $busca;
        $stmt = $conn->prepare("SELECT id_usuario, nome FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'aluno' AND status = 'ativo'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $nome = '%' . $busca . '%';
        $stmt = $conn->prepare("SELECT id_usuario, nome FROM usuarios WHERE nome LIKE ? AND tipo_usuario = 'aluno' AND status = 'ativo' ORDER BY nome LIMIT 20");
        $stmt->bind_param('s', $nome);
        $stmt->execute();
        $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $_SESSION['bo_recepcao_agenda_resultados'] = array_map(
        static fn(array $aluno): array => ['id' => (int) $aluno['id_usuario'], 'nome' => (string) $aluno['nome']],
        $resultados
    );
    if ($busca !== '') {
        bo_flash($resultados ? 'success' : 'error', $resultados ? 'Selecione o aluno para continuar.' : 'Nenhum aluno ativo encontrado.');
    }
    bo_redirect($secao);
}

if ($acao === 'selecionar_aluno') {
    $id = (int) bo_str('id_aluno');
    $permitidos = array_column($_SESSION['bo_recepcao_agenda_resultados'] ?? [], 'id');
    $aluno = in_array($id, $permitidos, true) ? bo_recepcao_agenda_aluno_ativo($conn, $id) : null;
    if (!$aluno) {
        bo_flash('error', 'Selecione um aluno ativo encontrado na busca.');
        bo_redirect($secao);
    }
    $_SESSION['bo_recepcao_agenda_aluno'] = ['id' => (int) $aluno['id_usuario'], 'nome' => (string) $aluno['nome']];
    unset($_SESSION['bo_recepcao_agenda_data']);
    bo_flash('success', 'Aluno selecionado. Escolha a data para consultar os horários.');
    bo_redirect($secao);
}

if ($acao === 'limpar_aluno') {
    unset($_SESSION['bo_recepcao_agenda_resultados'], $_SESSION['bo_recepcao_agenda_aluno'], $_SESSION['bo_recepcao_agenda_data']);
    bo_flash('success', 'Consulta de agendamento limpa.');
    bo_redirect($secao);
}

$alunoSelecionado = $_SESSION['bo_recepcao_agenda_aluno'] ?? null;
$idAluno = is_array($alunoSelecionado) ? (int) ($alunoSelecionado['id'] ?? 0) : 0;
if (!$idAluno || !bo_recepcao_agenda_aluno_ativo($conn, $idAluno)) {
    unset($_SESSION['bo_recepcao_agenda_aluno'], $_SESSION['bo_recepcao_agenda_data']);
    bo_flash('error', 'O aluno não está mais ativo. Faça uma nova busca.');
    bo_redirect($secao);
}

if ($acao === 'horarios') {
    $data = bo_str('data_evento');
    if (!bo_recepcao_agenda_data_valida($data)) {
        bo_flash('error', 'Escolha uma data entre hoje e os próximos 31 dias.');
        bo_redirect($secao);
    }
    $_SESSION['bo_recepcao_agenda_data'] = $data;
    bo_redirect($secao);
}

if ($acao !== 'agendar') {
    bo_flash('error', 'Ação de agendamento inválida.');
    bo_redirect($secao);
}

$data = (string) ($_SESSION['bo_recepcao_agenda_data'] ?? '');
$slotKey = bo_str('slot');
if (!bo_recepcao_agenda_data_valida($data) || $slotKey === '') {
    bo_flash('error', 'Consulte novamente os horários disponíveis.');
    bo_redirect($secao);
}

$idOperador = (int) $_SESSION['id_usuario'];
try {
    $message = bo_agenda_transacao($conn, static function () use ($conn, $idAluno, $idOperador, $data, $slotKey): string {
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'aluno' AND status = 'ativo' FOR UPDATE");
        $stmt->bind_param('i', $idAluno);
        $stmt->execute();
        $alunoValido = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$alunoValido) {
            throw new AgendamentoRecusado('O aluno não está mais ativo.');
        }

        $slot = null;
        foreach (bo_agenda_slots($conn, $data, $data, $idAluno) as $opcao) {
            if (hash_equals((string) $opcao['key'], $slotKey)) {
                $slot = $opcao;
                break;
            }
        }
        if (!$slot) {
            throw new AgendamentoRecusado('Esse horário não está mais disponível. Consulte novamente.');
        }

        $idAgendamento = bo_agenda_inserir($conn, [
            'id_usuario' => $idAluno,
            'id_profissional' => (int) $slot['id_profissional'],
            'titulo' => (string) $slot['modalidade'],
            'tipo' => (string) $slot['tipo'],
            'data_evento' => (string) $slot['data_evento'],
            'hora_inicio' => (string) $slot['hora_inicio'],
            'hora_fim' => (string) ($slot['hora_fim'] ?? ''),
            'local' => (string) ($slot['local'] ?? ''),
            'observacao' => '',
            'status' => 'confirmado',
        ]);

        if (isset($slot['id_disponibilidade'])) {
            $stmt = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'ocupado' WHERE id_disponibilidade = ? AND status = 'disponivel'");
            $stmt->bind_param('i', $slot['id_disponibilidade']);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new AgendamentoRecusado('Esse horário acabou de ser reservado. Consulte novamente.');
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO profissional_aluno (id_profissional, id_aluno, status) VALUES (?, ?, 'ativo') ON DUPLICATE KEY UPDATE status = 'ativo'");
        $stmt->bind_param('ii', $slot['id_profissional'], $idAluno);
        $stmt->execute();
        $stmt->close();

        bo_auditoria_registrar($conn, 'criado_recepcao', $idOperador, $idAluno, [
            'id_agendamento' => $idAgendamento,
            'id_profissional' => (int) $slot['id_profissional'],
            'data_evento' => $slot['data_evento'],
            'hora_inicio' => $slot['hora_inicio'],
        ], 'agenda');

        return onefitTraduzir('Agendamento realizado para {aluno}: {data} às {hora} com {profissional}.', [
            '{aluno}' => (string) ($_SESSION['bo_recepcao_agenda_aluno']['nome'] ?? ''),
            '{data}' => onefitData('d/m/Y', strtotime((string) $slot['data_evento'])),
            '{hora}' => substr((string) $slot['hora_inicio'], 0, 5),
            '{profissional}' => (string) $slot['profissional'],
        ]);
    });
    unset($_SESSION['bo_recepcao_agenda_data']);
    bo_flash('success', $message);
} catch (Throwable $error) {
    if (!$error instanceof AgendamentoRecusado) {
        error_log('ONE FIT: falha no agendamento pela recepção. Código: ' . $error->getCode());
    }
    bo_flash('error', $error instanceof AgendamentoRecusado ? $error->getMessage() : 'Não foi possível salvar o agendamento. Tente novamente.');
}
bo_redirect($secao);
