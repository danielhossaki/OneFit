<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php';
require $_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php';
require $_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php';

header('Content-Type: application/json; charset=utf-8');

function paymentResponse(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paymentResponse(405, ['ok' => false, 'message' => 'Método não permitido.']);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    paymentResponse(400, ['ok' => false, 'message' => 'Dados do pagamento inválidos.']);
}

$csrf = (string) ($payload['csrf_token'] ?? '');
$paymentToken = (string) ($payload['payment_token'] ?? '');
if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
    paymentResponse(403, ['ok' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.']);
}
if ($paymentToken === '' || !hash_equals((string) ($_SESSION['payment_token'] ?? ''), $paymentToken)) {
    paymentResponse(409, ['ok' => false, 'message' => 'Este pagamento já foi enviado. Atualize a página para tentar novamente.']);
}

$forma = strtolower(trim((string) ($payload['forma_pagamento'] ?? '')));
if (!in_array($forma, ['pix', 'credito', 'debito'], true)) {
    paymentResponse(422, ['ok' => false, 'message' => 'Selecione uma forma de pagamento válida.']);
}

if ($forma !== 'pix') {
    $numero = preg_replace('/\D+/', '', (string) ($payload['numero_cartao'] ?? ''));
    $validade = trim((string) ($payload['validade'] ?? ''));
    $cvv = preg_replace('/\D+/', '', (string) ($payload['cvv'] ?? ''));
    if (strlen($numero) < 13 || strlen($numero) > 19 || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $validade) || strlen($cvv) < 3 || strlen($cvv) > 4) {
        paymentResponse(422, ['ok' => false, 'message' => 'Confira o número do cartão, a validade e o CVV.']);
    }
}

$userId = (int) $_SESSION['id_usuario'];
$conn->begin_transaction();

try {
    $stmt = $conn->prepare(
        'SELECT m.id_matricula, m.id_plano, m.valor_contratado, m.data_fim, pl.nome, pl.duracao_dias, pl.cashback_percentual
         FROM matricula m
         JOIN cadastro_planos pl ON pl.id_plano = m.id_plano
         WHERE m.id_usuario = ?
         ORDER BY m.data_matricula DESC, m.id_matricula DESC LIMIT 1 FOR UPDATE'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $matricula = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$matricula || (float) $matricula['valor_contratado'] <= 0) {
        throw new DomainException('Você não possui um plano válido para pagamento.');
    }

    $matriculaId = (int) $matricula['id_matricula'];
    $valor = (float) $matricula['valor_contratado'];
    $agora = date('Y-m-d H:i:s');
    $hoje = date('Y-m-d');
    $duracao = max(1, (int) $matricula['duracao_dias']);
    $baseRenovacao = !empty($matricula['data_fim']) && $matricula['data_fim'] > $hoje ? $matricula['data_fim'] : $hoje;
    $fim = date('Y-m-d', strtotime($baseRenovacao . ' +' . $duracao . ' days'));
    $codigo = 'SIM-' . strtoupper(bin2hex(random_bytes(8)));

    $stmt = $conn->prepare(
        "INSERT INTO pagamento (id_matricula, valor, data_vencimento, data_pagamento, forma_pagamento, status, codigo_transacao)
         VALUES (?, ?, ?, ?, ?, 'aprovado', ?)"
    );
    $stmt->bind_param('idssss', $matriculaId, $valor, $hoje, $agora, $forma, $codigo);
    $stmt->execute();
    $pagamentoId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("UPDATE matricula SET status = 'ativa', data_inicio = ?, data_fim = ? WHERE id_matricula = ? AND id_usuario = ?");
    $stmt->bind_param('ssii', $hoje, $fim, $matriculaId, $userId);
    $stmt->execute();
    $stmt->close();

    $cashback = round($valor * ((float) $matricula['cashback_percentual'] / 100), 2);
    if ($cashback > 0) {
        $descricaoCashback = 'Cashback do pagamento do plano ' . $matricula['nome'];
        $stmt = $conn->prepare(
            "INSERT INTO cashback (id_usuario, id_pagamento, valor, tipo, origem, descricao, status, data_criacao)
             VALUES (?, ?, ?, 'credito', 'mensalidade', ?, 'disponivel', ?)"
        );
        $stmt->bind_param('iidss', $userId, $pagamentoId, $cashback, $descricaoCashback, $agora);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    unset($_SESSION['payment_token']);

    $formas = ['pix' => 'PIX', 'credito' => 'Crédito', 'debito' => 'Débito'];
    paymentResponse(201, [
        'ok' => true,
        'message' => 'Pagamento simulado com sucesso!',
        'payment' => [
            'data' => date('d/m/Y H:i', strtotime($agora)),
            'descricao' => 'Pagamento do plano ' . $matricula['nome'],
            'tipo' => 'Pagamento',
            'forma_pagamento' => $formas[$forma],
            'status' => 'Aprovado',
            'valor' => $valor,
            'cashback' => $cashback,
        ],
    ]);
} catch (DomainException $exception) {
    $conn->rollback();
    paymentResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable $exception) {
    $conn->rollback();
    error_log(sprintf('ONE FIT payment error for user %d: %s', $userId, $exception->getMessage()));
    paymentResponse(500, ['ok' => false, 'message' => 'Não foi possível processar o pagamento. Tente novamente.']);
}
