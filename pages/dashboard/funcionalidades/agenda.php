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
