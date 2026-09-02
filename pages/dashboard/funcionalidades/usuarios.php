<?php
/**
 * funcionalidades/usuarios.php
 * CRUD da tela "Usuários" do admin (tabela `usuarios`, e a matrícula mais
 * recente do usuário quando as datas de contrato são informadas).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'toggle-status') {
    if (!$id) {
        bo_flash('error', 'Usuário inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare("UPDATE usuarios SET status = IF(status = 'ativo', 'inativo', 'ativo') WHERE id_usuario = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Status do usuário atualizado.');
    bo_redirect($secao);
}

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Usuário inválido.');
        bo_redirect($secao);
    }
    try {
        $stmt = $conn->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        bo_flash('success', 'Usuário excluído.');
    } catch (\Throwable $e) {
        bo_flash('error', 'Não é possível excluir: este usuário possui matrícula, pagamentos ou outros registros vinculados.');
    }
    bo_redirect($secao);
}

$nome = bo_str('nome');
$email = bo_str('email');
$cpf = preg_replace('/\D/', '', bo_str('cpf'));
$statusForm = bo_str('status');
$acesso = bo_str('acesso');
$status = $acesso === 'Bloqueado' ? 'bloqueado' : ($statusForm === 'inativo' ? 'inativo' : 'ativo');
$dataInicial = bo_str('dataInicial');
$dataFinal = bo_str('dataFinal');

if (!$nome || !$email || strlen($cpf) !== 11) {
    bo_flash('error', 'Preencha nome, e-mail e um CPF válido (11 números).');
    bo_redirect($secao);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bo_flash('error', 'Informe um e-mail válido.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Usuário inválido.');
        bo_redirect($secao);
    }

    $check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE (cpf = ? OR email = ?) AND id_usuario <> ? LIMIT 1');
    $check->bind_param('ssi', $cpf, $email, $id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        bo_flash('error', 'O CPF ou e-mail informado já pertence a outro usuário.');
        bo_redirect($secao);
    }
    $check->close();

    $stmt = $conn->prepare('UPDATE usuarios SET nome = ?, email = ?, cpf = ?, status = ? WHERE id_usuario = ?');
    $stmt->bind_param('ssssi', $nome, $email, $cpf, $status, $id);
    $stmt->execute();
    $stmt->close();

    if ($dataInicial !== '' || $dataFinal !== '') {
        $stmtM = $conn->prepare('SELECT id_matricula FROM matricula WHERE id_usuario = ? ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1');
        $stmtM->bind_param('i', $id);
        $stmtM->execute();
        $mat = $stmtM->get_result()->fetch_assoc();
        $stmtM->close();
        if ($mat) {
            $stmtU = $conn->prepare('UPDATE matricula SET data_inicio = COALESCE(NULLIF(?, \'\'), data_inicio), data_fim = COALESCE(NULLIF(?, \'\'), data_fim) WHERE id_matricula = ?');
            $stmtU->bind_param('ssi', $dataInicial, $dataFinal, $mat['id_matricula']);
            $stmtU->execute();
            $stmtU->close();
        }
    }

    bo_flash('success', 'Usuário atualizado.');
    bo_redirect($secao);
}

// create
$senha = bo_str('senha');
$nascimento = bo_str('nascimento');
$genero = strtolower(bo_str('genero'));
$celular = preg_replace('/\D/', '', bo_str('celular'));
$nacionalidade = bo_str('nacionalidade');
$endereco = bo_str('endereco');
$cidade = bo_str('cidade');
$estado = strtoupper(bo_str('estado'));

if (!$senha || strlen($senha) < 6) {
    bo_flash('error', 'Defina uma senha com pelo menos 6 caracteres.');
    bo_redirect($secao);
}
if (!$nascimento || !$genero || !$celular || !$nacionalidade || !$endereco || !$cidade || !$estado) {
    bo_flash('error', 'Preencha nascimento, gênero, celular, nacionalidade, endereço, cidade e estado.');
    bo_redirect($secao);
}
if (!bo_valida_celular($celular)) {
    bo_flash('error', 'Informe um celular/telefone válido, com DDD (10 ou 11 números).');
    bo_redirect($secao);
}
if (!in_array($genero, ['masculino', 'feminino', 'outro'], true)) {
    bo_flash('error', 'Selecione um gênero válido.');
    bo_redirect($secao);
}

$check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE cpf = ? OR email = ? LIMIT 1');
$check->bind_param('ss', $cpf, $email);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    $check->close();
    bo_flash('error', 'Já existe um usuário com este CPF ou e-mail.');
    bo_redirect($secao);
}
$check->close();

$cidadeEstado = $cidade . '/' . $estado;
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    'INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, celular, tipo_usuario, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "aluno", ?)'
);
$stmt->bind_param(
    'sssssssssss',
    $nome,
    $senhaHash,
    $nacionalidade,
    $nascimento,
    $genero,
    $cpf,
    $endereco,
    $cidadeEstado,
    $email,
    $celular,
    $status
);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Usuário criado.');
bo_redirect($secao);
