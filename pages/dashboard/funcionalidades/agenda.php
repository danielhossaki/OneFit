<?php
$bo_papeis_permitidos = ['aluno'];
require __DIR__ . '/_shared.php';
require_once __DIR__ . '/../includes/agenda-service.php';
bo_check_csrf();
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
try {
    // Trava nomeada + transação (bo_agenda_transacao): serializa reservas e a geração
    // de IDs do dump sem AUTO_INCREMENT; qualquer erro desfaz tudo.
    $message = bo_agenda_transacao($conn, static function () use ($conn, $idUsuario): string {
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
            return 'Agendamento cancelado.';
        }
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
        // Só vale um horário que a própria agenda oferece (já sem professores indisponíveis).
        $slot = null;
        foreach (bo_agenda_slots($conn, $date, $date) as $candidate) {
            if ($candidate['key'] === $key) { $slot = $candidate; break; }
        }
        if (!$slot) throw new RuntimeException('Esse horário não está mais disponível. Escolha outro horário.');
        // bo_agenda_inserir reconfere o professor com bloqueio: fecha a corrida com uma
        // mudança de conta, cadastro ou disponibilidade feita neste instante.
        try {
            bo_agenda_inserir($conn, ['id_usuario' => $idUsuario, 'id_profissional' => $slot['id_profissional'], 'titulo' => $slot['modalidade'],
                'tipo' => $slot['tipo'], 'data_evento' => $slot['data_evento'], 'hora_inicio' => $slot['hora_inicio'], 'hora_fim' => $slot['hora_fim'],
                'local' => $slot['local'], 'status' => 'confirmado']);
        } catch (AgendamentoRecusado $recusa) {
            throw new RuntimeException('Esse horário não está mais disponível. Escolha outro horário.');
        }
        if (isset($slot['id_disponibilidade'])) {
            $stmt = $conn->prepare("UPDATE disponibilidade_profissional SET status = 'ocupado' WHERE id_disponibilidade = ? AND status = 'disponivel'");
            $stmt->bind_param('i', $slot['id_disponibilidade']);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Esse horário acabou de ser reservado.');
        }
        $stmt = $conn->prepare("INSERT INTO profissional_aluno (id_profissional, id_aluno, status) VALUES (?, ?, 'ativo') ON DUPLICATE KEY UPDATE status = 'ativo'");
        $stmt->bind_param('ii', $slot['id_profissional'], $idUsuario);
        $stmt->execute();
        return onefitTraduzir('Agendamento realizado com sucesso! Data: {data} • Horário: {hora} • Atendimento: {atendimento} • Profissional: {profissional}', [
            '{data}' => onefitData('d/m/Y', strtotime($date)),
            '{hora}' => substr($slot['hora_inicio'], 0, 5),
            '{atendimento}' => $slot['modalidade_label'] ?? $slot['modalidade'],
            '{profissional}' => $slot['profissional'],
        ]);
    });
    bo_flash('success', $message);
} catch (Throwable $error) {
    bo_flash('error', $error instanceof RuntimeException && !($error instanceof mysqli_sql_exception) ? $error->getMessage() : 'Não foi possível salvar o agendamento. Tente novamente.');
}
bo_redirect('agenda');
