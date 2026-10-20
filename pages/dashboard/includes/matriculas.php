<?php
/**
 * includes/matriculas.php
 * Ciclo de vida da matrícula ligado à cobrança (tabelas matricula e pagamento).
 *
 *  - Inscrição (pages/matricula/matricula.php): matrícula 'pendente' e cobrança
 *    'pendente'. Enquanto pendente, data_fim = data_inicio só para cumprir o NOT
 *    NULL da coluna (sem isso, um MySQL sem modo estrito grava 0000-00-00); quem
 *    decide o acesso é o status, e a catraca só aceita 'ativa'.
 *  - Confirmação: bo_matricula_confirmar_pagamento é a ÚNICA rotina que aprova
 *    uma cobrança pendente e ativa a matrícula. Só pode ser chamada por uma
 *    fonte confiável de confirmação (gateway/conciliação, ou confirmação manual
 *    quando essa regra for decidida), nunca por uma requisição do próprio aluno.
 *  - Vigência na ativação: data_inicio = data da confirmação (CURDATE do banco,
 *    a mesma data que a catraca usa) e data_fim = data_inicio + duracao_dias do
 *    plano. É a regra de usuarios.php ao atribuir plano e do fluxo PIX da branch
 *    pagamento1.
 *  - Idempotente: só a passagem da cobrança de pendente/atrasado para aprovado
 *    ativa; confirmar de novo não muda nada. Matrícula que não está 'pendente'
 *    (ativa, vencida, cancelada) não é reativada nem prorrogada: renovação e
 *    prorrogação dependem de decisão de negócio.
 *  - Cobrança recusada ou cancelada não ativa; valor divergente não é aprovado.
 *  - Nada é apagado; a ativação fica em auditoria_eventos com as datas anteriores.
 */
require_once __DIR__ . '/funcionarios.php';

/** Recusa esperada da confirmação; nada foi gravado. $motivo é um código estável. */
final class MatriculaRecusada extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct($motivo);
    }
}

/** Matrícula pendente da inscrição. Chamar dentro da transação de quem cria a conta. */
function bo_matricula_inserir_pendente(mysqli $conn, int $idUsuario, int $idPlano, float $valorContratado): int
{
    $stmt = $conn->prepare(
        "INSERT INTO matricula (id_usuario, id_plano, data_matricula, data_inicio, data_fim, status, valor_contratado)
         VALUES (?, ?, CURDATE(), CURDATE(), CURDATE(), 'pendente', ?)"
    );
    $stmt->bind_param('iid', $idUsuario, $idPlano, $valorContratado);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return $id;
}

/** Cobrança pendente da inscrição, sem dados de cartão. Retorna [id_pagamento, codigo_transacao]. */
function bo_pagamento_inserir_inscricao(mysqli $conn, int $idMatricula, float $valor, string $forma): array
{
    if (!in_array($forma, ['pix', 'cartao'], true)) {
        throw new InvalidArgumentException('Forma de pagamento inválida.');
    }
    $codigo = 'MAT-' . $idMatricula . '-' . strtoupper(bin2hex(random_bytes(6)));
    $vencimento = date('Y-m-d');
    $stmt = $conn->prepare(
        "INSERT INTO pagamento (id_matricula, valor, data_vencimento, forma_pagamento, status, codigo_transacao)
         VALUES (?, ?, ?, ?, 'pendente', ?)"
    );
    $stmt->bind_param('idsss', $idMatricula, $valor, $vencimento, $forma, $codigo);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return [$id, $codigo];
}

/**
 * Aprova a cobrança e ativa a matrícula pendente dela.
 * $valorConfirmado: valor informado pela fonte da confirmação; tem de ser igual ao cobrado.
 * $origem: identifica a fonte (ex.: 'gateway'), só para a auditoria.
 * Retorna ['resultado' => 'ativada'|'ja_confirmado', 'id_matricula', 'status', 'data_inicio', 'data_fim'].
 */
function bo_matricula_confirmar_pagamento(mysqli $conn, int $idPagamento, float $valorConfirmado, string $origem): array
{
    if (!preg_match('/^[a-z_]{3,30}$/D', $origem)) {
        throw new InvalidArgumentException('Origem da confirmação inválida.');
    }
    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start enrollment confirmation.');
    }
    try {
        $stmt = $conn->prepare('SELECT id_pagamento, id_matricula, valor, status FROM pagamento WHERE id_pagamento = ? FOR UPDATE');
        $stmt->bind_param('i', $idPagamento);
        $stmt->execute();
        $pagamento = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pagamento) {
            throw new MatriculaRecusada('pagamento_inexistente');
        }

        $stmt = $conn->prepare(
            'SELECT m.id_matricula, m.status, m.data_inicio, m.data_fim, u.status AS conta, c.duracao_dias
             FROM matricula m
             JOIN usuarios u ON u.id_usuario = m.id_usuario
             JOIN cadastro_planos c ON c.id_plano = m.id_plano
             WHERE m.id_matricula = ? FOR UPDATE'
        );
        $stmt->bind_param('i', $pagamento['id_matricula']);
        $stmt->execute();
        $matricula = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$matricula) {
            throw new MatriculaRecusada('matricula_inexistente');
        }

        // Mesma confirmação repetida (callback reenviado): nada muda.
        if ($pagamento['status'] === 'aprovado') {
            $conn->commit();
            return ['resultado' => 'ja_confirmado', 'id_matricula' => (int) $matricula['id_matricula'], 'status' => $matricula['status'],
                'data_inicio' => $matricula['data_inicio'], 'data_fim' => $matricula['data_fim']];
        }
        if (!in_array($pagamento['status'], ['pendente', 'atrasado'], true)) {
            throw new MatriculaRecusada('pagamento_' . $pagamento['status']);
        }
        if (round($valorConfirmado * 100) !== round((float) $pagamento['valor'] * 100)) {
            throw new MatriculaRecusada('valor_divergente');
        }
        if ($matricula['status'] !== 'pendente') {
            // Renovação ou prorrogação de matrícula ativa/vencida: regra ainda não definida.
            throw new MatriculaRecusada('matricula_nao_pendente');
        }
        if ($matricula['conta'] !== 'ativo') {
            throw new MatriculaRecusada('conta_inativa');
        }
        $duracao = (int) $matricula['duracao_dias'];
        if ($duracao < 1 || $duracao > 36500) {
            throw new MatriculaRecusada('plano_sem_duracao');
        }

        $stmt = $conn->prepare("UPDATE pagamento SET status = 'aprovado', data_pagamento = NOW() WHERE id_pagamento = ? AND status IN ('pendente', 'atrasado')");
        $stmt->bind_param('i', $idPagamento);
        $stmt->execute();
        $aprovou = $stmt->affected_rows === 1;
        $stmt->close();
        $stmt = $conn->prepare("UPDATE matricula SET status = 'ativa', data_inicio = CURDATE(), data_fim = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id_matricula = ? AND status = 'pendente'");
        $stmt->bind_param('ii', $duracao, $matricula['id_matricula']);
        $stmt->execute();
        $ativou = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$aprovou || !$ativou) {
            throw new RuntimeException('Enrollment changed during confirmation.');
        }

        $stmt = $conn->prepare('SELECT data_inicio, data_fim FROM matricula WHERE id_matricula = ?');
        $stmt->bind_param('i', $matricula['id_matricula']);
        $stmt->execute();
        $datas = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Sem operador: a confirmação vem da fonte registrada em 'origem'.
        bo_auditoria_registrar($conn, 'ativar', null, (int) $matricula['id_matricula'], [
            'id_pagamento' => $idPagamento,
            'origem' => $origem,
            'antes' => ['status' => 'pendente', 'data_inicio' => $matricula['data_inicio'], 'data_fim' => $matricula['data_fim']],
            'depois' => $datas,
        ], 'matricula');

        if (!$conn->commit()) {
            throw new RuntimeException('Could not commit enrollment confirmation.');
        }
        return ['resultado' => 'ativada', 'id_matricula' => (int) $matricula['id_matricula'], 'status' => 'ativa'] + $datas;
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
}
