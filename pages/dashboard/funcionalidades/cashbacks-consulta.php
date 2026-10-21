<?php
$bo_papeis_permitidos = ['admin', 'recepcionista'];
require __DIR__ . '/_shared.php';
require_once __DIR__ . '/../includes/funcionarios.php';
bo_check_csrf();

$secao = bo_secao_atual();
$acao = bo_str('acao');

if ($acao === 'limpar') {
    unset($_SESSION['bo_cashback_cpf_resultado']);
    bo_flash('success', 'Consulta por CPF limpa.');
    bo_redirect($secao);
}

$cpf = preg_replace('/\D+/', '', (string) ($_POST['cpf'] ?? '')) ?? '';
if (!bo_cpf_valido($cpf)) {
    $_SESSION['bo_cashback_cpf_resultado'] = ['encontrado' => false];
    bo_flash('error', 'Informe um CPF válido com 11 números.');
    bo_redirect($secao);
}

$stmt = $conn->prepare(
    "SELECT id_usuario, nome FROM usuarios
     WHERE tipo_usuario = 'aluno'
       AND REPLACE(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', ''), '/', '') = ?
     LIMIT 1"
);
$stmt->bind_param('s', $cpf);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

$_SESSION['bo_cashback_cpf_resultado'] = $cliente
    ? ['encontrado' => true, 'id' => (int) $cliente['id_usuario'], 'nome' => (string) $cliente['nome']]
    : ['encontrado' => false];
bo_flash($cliente ? 'success' : 'error', $cliente ? 'Cliente localizado.' : 'Nenhum aluno encontrado com esse CPF.');
bo_redirect($secao);
