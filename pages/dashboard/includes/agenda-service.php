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
                        'modalidade' => $label, 'modalidade_label' => onefitTraduzir($label), 'tipo' => $type, 'data_evento' => $date, 'hora_inicio' => $time,
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
