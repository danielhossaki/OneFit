<?php
/**
 * Disponibilidade de demonstração; reservas usam as tabelas existentes.
 * Só entram professores que recebem agendamentos (bo_professores_condicao_agenda:
 * cargo professor, conta ativa, cadastro profissional ativo e disponível).
 *
 * Todo agendamento novo, venha do aluno (funcionalidades/agenda.php) ou do
 * professor (components/section-profissional.php), passa por
 * bo_agenda_transacao() + bo_agenda_inserir(): a trava nomeada serializa as
 * reservas e a geração de IDs, e a regra do professor é reconferida com
 * bloqueio dentro da mesma transação que grava.
 */
require_once __DIR__ . '/professores.php';

/** Agendamento recusado por regra de negócio; a mensagem pode ser exibida. */
final class AgendamentoRecusado extends RuntimeException
{
}

const BO_AGENDA_TRAVA = 'onefit_agenda_reserva';
const BO_AGENDA_TRAVA_SEGUNDOS = 10;

/** Profundidade de bo_agenda_transacao() em andamento nesta requisição. */
function bo_agenda_em_transacao(int $delta = 0): int
{
    static $profundidade = 0;
    $profundidade += $delta;
    return $profundidade;
}

/**
 * Executa $operacao com a trava nomeada da agenda e numa transação; qualquer
 * erro desfaz tudo. É a única forma de criar agendamentos (bo_agenda_inserir).
 */
function bo_agenda_transacao(mysqli $conn, callable $operacao)
{
    $stmt = $conn->prepare('SELECT GET_LOCK(?, ?)');
    $trava = BO_AGENDA_TRAVA;
    $segundos = BO_AGENDA_TRAVA_SEGUNDOS;
    $stmt->bind_param('si', $trava, $segundos);
    $stmt->execute();
    $obtida = (int) $stmt->get_result()->fetch_row()[0] === 1;
    $stmt->close();
    if (!$obtida) {
        throw new AgendamentoRecusado('Agenda ocupada. Tente novamente.');
    }
    try {
        $conn->begin_transaction();
        bo_agenda_em_transacao(1);
        try {
            $resultado = $operacao();
            $conn->commit();
            return $resultado;
        } catch (Throwable $erro) {
            $conn->rollback();
            throw $erro;
        } finally {
            bo_agenda_em_transacao(-1);
        }
    } finally {
        $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->bind_param('s', $trava);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Grava um agendamento novo, só dentro de bo_agenda_transacao(). Antes,
 * reconfere com bloqueio que o professor recebe agendamentos (cargo, conta,
 * cadastro profissional e disponibilidade): quem chama não consegue pular a
 * regra. O ID vem de MAX + 1 (o dump original não tem AUTO_INCREMENT), sob a trava.
 * $a: id_usuario, id_profissional, titulo, tipo, data_evento, hora_inicio,
 *     hora_fim, local, observacao (opcionais vazios viram NULL), status.
 */
function bo_agenda_inserir(mysqli $conn, array $a): int
{
    if (bo_agenda_em_transacao() < 1) {
        throw new LogicException('bo_agenda_inserir() só roda dentro de bo_agenda_transacao().');
    }
    if (!in_array($a['status'] ?? '', ['agendado', 'confirmado'], true)) {
        throw new LogicException('Situação inicial de agendamento inválida.');
    }
    if (!bo_professor_agendavel($conn, (int) $a['id_profissional'])) {
        throw new AgendamentoRecusado('Este professor não está disponível para novos agendamentos.');
    }
    $id = (int) $conn->query('SELECT COALESCE(MAX(id_agendamento), 0) + 1 FROM agendamento')->fetch_row()[0];
    $campo = static fn(string $nome): string => trim((string) ($a[$nome] ?? ''));
    $idUsuario = (int) $a['id_usuario'];
    $idProfissional = (int) $a['id_profissional'];
    [$titulo, $tipo, $data, $inicio, $fim, $local, $observacao, $status] = array_map($campo, ['titulo', 'tipo', 'data_evento', 'hora_inicio', 'hora_fim', 'local', 'observacao', 'status']);
    $stmt = $conn->prepare(
        "INSERT INTO agendamento
            (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento, hora_inicio, hora_fim, local, observacao, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)"
    );
    $stmt->bind_param('iiissssssss', $id, $idUsuario, $idProfissional, $titulo, $tipo, $data, $inicio, $fim, $local, $observacao, $status);
    $stmt->execute();
    $stmt->close();
    return $id;
}

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

function bo_agenda_slots(mysqli $conn, string $start, string $end, ?int $idUsuario = null): array
{
    $idUsuarioConflitos = $idUsuario ?? (int) ($_SESSION['id_usuario'] ?? 0);
    $now = bo_agenda_now();
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $now->getTimezone());
    $last = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $now->getTimezone());
    if (!$first || !$last || $first->format('Y-m-d') !== $start || $last->format('Y-m-d') !== $end || $first > $last || $first->diff($last)->days > 31) return [];
    $professionals = bo_professores_agendaveis($conn);
    $stmt = $conn->prepare("SELECT id_usuario, id_profissional, data_evento, hora_inicio, hora_fim FROM agendamento WHERE status <> 'cancelado' AND data_evento BETWEEN ? AND ?");
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $stmt = $conn->prepare('SELECT d.*, cp.nome AS profissional, cp.especialidade FROM disponibilidade_profissional d
        JOIN cadastro_profissional cp ON cp.id_profissional = d.id_profissional JOIN usuarios u ON u.id_usuario = cp.id_usuario
        WHERE ' . bo_professores_condicao_agenda($conn) . ' AND d.data_evento BETWEEN ? AND ?');
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
    return array_values(array_filter($slots, static function ($slot) use ($now, $bookings, $idUsuarioConflitos) {
        if ($slot['data_evento'] . ' ' . $slot['hora_inicio'] <= $now->format('Y-m-d H:i:s')) return false;
        foreach ($bookings as $booking) {
            if ($booking['data_evento'] === $slot['data_evento'] &&
                ((int) $booking['id_profissional'] === (int) $slot['id_profissional'] || ($idUsuarioConflitos > 0 && (int) $booking['id_usuario'] === $idUsuarioConflitos)) &&
                $booking['hora_inicio'] < $slot['hora_fim'] && ($booking['hora_fim'] ?: '23:59:59') > $slot['hora_inicio']) return false;
        }
        return true;
    }));
}
