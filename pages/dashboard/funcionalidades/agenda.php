<?php
/**
 * funcionalidades/agenda.php
 * Tela "Minha agenda" do aluno: confirma um agendamento a partir de um
 * horário disponível (disponibilidade_profissional) ou cancela um
 * agendamento próprio (agendamento). Vincula automaticamente o aluno ao
 * profissional (profissional_aluno), do mesmo jeito que o profissional já
 * faz manualmente em components/section-profissional.php.
 */

$bo_papeis_permitidos = ['aluno'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$secao = 'agenda';

if ($acao === 'cancelar') {
    $idAgendamento = (int) bo_str('id_agendamento');
    if (!$idAgendamento) {
        bo_flash('error', 'Agendamento inválido.');
        bo_redirect($secao);
    }

    $stmt = $conn->prepare('SELECT id_profissional, data_evento, hora_inicio FROM agendamento WHERE id_agendamento = ? AND id_usuario = ?');
    $stmt->bind_param('ii', $idAgendamento, $idUsuario);
    $stmt->execute();
    $agendamento = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$agendamento) {
        bo_flash('error', 'Agendamento não encontrado.');
        bo_redirect($secao);
    }

    $stmt = $conn->prepare("UPDATE agendamento SET status = 'cancelado' WHERE id_agendamento = ? AND id_usuario = ? AND status IN ('agendado','confirmado')");
    $stmt->bind_param('ii', $idAgendamento, $idUsuario);
    $stmt->execute();
    $stmt->close();

    // Libera o horário de volta para a lista de disponíveis, se ele ainda existir.
    $stmt = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'disponivel' WHERE id_profissional = ? AND data_evento = ? AND hora_inicio = ? AND status = 'ocupado'");
    $stmt->bind_param('iss', $agendamento['id_profissional'], $agendamento['data_evento'], $agendamento['hora_inicio']);
    $stmt->execute();
    $stmt->close();

    bo_flash('success', 'Agendamento cancelado.');
    bo_redirect($secao);
}

// Confirmar agendamento a partir de um horário disponível.
$idDisponibilidade = (int) bo_str('id_disponibilidade');
if (!$idDisponibilidade) {
    bo_flash('error', 'Selecione um horário válido.');
    bo_redirect($secao);
}

$stmt = $conn->prepare(
    "SELECT d.id_disponibilidade, d.id_profissional, d.modalidade, d.data_evento, d.hora_inicio, d.hora_fim, d.local
     FROM disponibilidade_profissional d WHERE d.id_disponibilidade = ? AND d.status = 'disponivel'"
);
$stmt->bind_param('i', $idDisponibilidade);
$stmt->execute();
$slot = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$slot) {
    bo_flash('error', 'Esse horário não está mais disponível.');
    bo_redirect($secao);
}

// Marca o horário como ocupado antes de inserir o agendamento (evita
// corrida entre dois alunos tentando o mesmo horário).
$stmt = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'ocupado' WHERE id_disponibilidade = ? AND status = 'disponivel'");
$stmt->bind_param('i', $idDisponibilidade);
$stmt->execute();
$conseguiuReservar = $stmt->affected_rows > 0;
$stmt->close();

if (!$conseguiuReservar) {
    bo_flash('error', 'Esse horário acabou de ser reservado por outra pessoa.');
    bo_redirect($secao);
}

// Compatível com o dump original, em que id_agendamento não é AUTO_INCREMENT.
$proximoId = 1;
$resultadoId = $conn->query('SELECT COALESCE(MAX(id_agendamento), 0) + 1 AS proximo FROM agendamento');
if ($resultadoId) {
    $proximoId = (int) ($resultadoId->fetch_assoc()['proximo'] ?? 1);
}

$stmt = $conn->prepare(
    "INSERT INTO agendamento (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento, hora_inicio, hora_fim, local, status)
     VALUES (?, ?, ?, ?, 'avaliacao', ?, ?, ?, ?, 'agendado')"
);
$stmt->bind_param(
    'iiisssss',
    $proximoId,
    $idUsuario,
    $slot['id_profissional'],
    $slot['modalidade'],
    $slot['data_evento'],
    $slot['hora_inicio'],
    $slot['hora_fim'],
    $slot['local']
);
if (!$stmt->execute()) {
    // Não deixa o horário travado como "ocupado" se o agendamento falhou.
    $rollback = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'disponivel' WHERE id_disponibilidade = ?");
    $rollback->bind_param('i', $idDisponibilidade);
    $rollback->execute();
    $rollback->close();
    $stmt->close();
    bo_flash('error', 'Não foi possível confirmar o agendamento.');
    bo_redirect($secao);
}
$stmt->close();

$stmt = $conn->prepare(
    "INSERT INTO profissional_aluno (id_profissional, id_aluno, status)
     VALUES (?, ?, 'ativo')
     ON DUPLICATE KEY UPDATE status = 'ativo'"
);
$stmt->bind_param('ii', $slot['id_profissional'], $idUsuario);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Agendamento confirmado com sucesso.');
bo_redirect($secao);
