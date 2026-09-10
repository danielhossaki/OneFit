<?php
/**
 * funcionalidades/pagar-plano.php
 * Processa o botão "Pagar" do modal components/modal-pagar-plano.php
 * (tela "Histórico" do aluno). Quita a parcela pendente mais antiga da
 * matrícula do aluno logado ou, se não houver nenhuma pendente, gera o
 * próximo ciclo já pago — sempre na tabela `pagamento`, a mesma lida
 * pela tela "Histórico" e pela aba "Pagamentos" do admin.
 */

$bo_papeis_permitidos = ['aluno'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$forma = bo_str('forma_pagamento');
if (!in_array($forma, ['pix', 'cartao'], true)) {
    $forma = 'pix';
}

$stmtM = $conn->prepare('SELECT id_matricula, status, valor_contratado FROM matricula WHERE id_usuario = ? ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1');
$stmtM->bind_param('i', $idUsuario);
$stmtM->execute();
$matricula = $stmtM->get_result()->fetch_assoc();
$stmtM->close();

if (!$matricula) {
    bo_flash('error', 'Nenhuma matrícula encontrada para o seu usuário.');
    bo_redirect('historico');
}

$idMatricula = (int) $matricula['id_matricula'];
$hoje = date('Y-m-d');

$stmtP = $conn->prepare("SELECT id_pagamento FROM pagamento WHERE id_matricula = ? AND status IN ('pendente', 'atrasado') ORDER BY data_vencimento ASC LIMIT 1");
$stmtP->bind_param('i', $idMatricula);
$stmtP->execute();
$pendente = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

if ($pendente) {
    $stmt = $conn->prepare("UPDATE pagamento SET status = 'aprovado', data_pagamento = NOW(), forma_pagamento = ? WHERE id_pagamento = ?");
    $stmt->bind_param('si', $forma, $pendente['id_pagamento']);
    $stmt->execute();
    $stmt->close();
} else {
    $valor = (float) $matricula['valor_contratado'];
    $stmt = $conn->prepare("INSERT INTO pagamento (id_matricula, valor, data_vencimento, data_pagamento, forma_pagamento, status) VALUES (?, ?, ?, NOW(), ?, 'aprovado')");
    $stmt->bind_param('idss', $idMatricula, $valor, $hoje, $forma);
    $stmt->execute();
    $stmt->close();
}

if ($matricula['status'] === 'pendente') {
    $stmt = $conn->prepare("UPDATE matricula SET status = 'ativa' WHERE id_matricula = ?");
    $stmt->bind_param('i', $idMatricula);
    $stmt->execute();
    $stmt->close();
}

bo_flash('success', 'Pagamento registrado com sucesso.');
bo_redirect('historico');
