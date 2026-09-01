<?php
/**
 * actions/update-profile.php
 * Handler da tela "Meu perfil" (todos os perfis): recebe o form real
 * postado pelo modal de includes/admin-forms.php (bo_modal_perfil_editar),
 * valida e grava em usuarios. Foto aceita upload de arquivo OU URL — o
 * upload, quando enviado, tem prioridade sobre a URL digitada.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$nome = bo_str('nome');
$cpf = preg_replace('/\D/', '', bo_str('documento'));
$email = bo_str('email');
$celular = preg_replace('/\D/', '', bo_str('telefone'));
$nacionalidade = bo_str('nacionalidade');
$nascimento = bo_str('nascimento');
$genero = strtolower(bo_str('genero'));
$endereco = bo_str('endereco');
$cidade = bo_str('cidade');
$estado = strtoupper(bo_str('estado'));
$alturaStr = bo_str('altura');
$pesoStr = bo_str('peso');
$fotoUrl = bo_str('foto');

$altura = $alturaStr === '' ? null : filter_var($alturaStr, FILTER_VALIDATE_FLOAT);
$peso = $pesoStr === '' ? null : filter_var($pesoStr, FILTER_VALIDATE_FLOAT);

if (!$nome || !$cpf || !$email || !$celular || !$nacionalidade || !$nascimento || !$genero || !$endereco || !$cidade || !$estado) {
    bo_flash('error', 'Preencha todos os campos obrigatórios.');
    bo_redirect_perfil();
}
if (strlen($cpf) !== 11) {
    bo_flash('error', 'Informe um CPF com 11 números.');
    bo_redirect_perfil();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bo_flash('error', 'Informe um e-mail válido.');
    bo_redirect_perfil();
}
if (!bo_valida_celular($celular)) {
    bo_flash('error', 'Informe um celular/telefone válido, com DDD (10 ou 11 números).');
    bo_redirect_perfil();
}
if (!in_array($genero, ['masculino', 'feminino', 'outro'], true)) {
    bo_flash('error', 'Selecione um gênero válido.');
    bo_redirect_perfil();
}
if (!preg_match('/^[A-Z]{2}$/', $estado)) {
    bo_flash('error', 'Informe o estado usando uma UF válida.');
    bo_redirect_perfil();
}

$dataNascimento = DateTime::createFromFormat('!Y-m-d', $nascimento);
$dataMinima = (new DateTime('today'))->modify('-12 years');
if (!$dataNascimento || $dataNascimento->format('Y-m-d') !== $nascimento || $dataNascimento > $dataMinima) {
    bo_flash('error', 'Para manter seu perfil na ONE FIT, você precisa ter pelo menos 12 anos.');
    bo_redirect_perfil();
}
if (($altura !== null && ($altura === false || $altura <= 0 || $altura > 3)) ||
    ($peso !== null && ($peso === false || $peso <= 0 || $peso > 500))) {
    bo_flash('error', 'Confira os valores de altura e peso.');
    bo_redirect_perfil();
}
if ($fotoUrl && (!filter_var($fotoUrl, FILTER_VALIDATE_URL) || strlen($fotoUrl) > 255)) {
    bo_flash('error', 'Informe uma URL válida para a foto.');
    bo_redirect_perfil();
}

$fotoUpload = bo_processar_upload_imagem('foto_arquivo', 'perfil');
$foto = $fotoUpload ?? $fotoUrl;

$cidadeEstado = $cidade . '/' . $estado;
$idUsuario = (int) $_SESSION['id_usuario'];

$check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE (cpf = ? OR email = ?) AND id_usuario <> ? LIMIT 1');
$check->bind_param('ssi', $cpf, $email, $idUsuario);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    $check->close();
    bo_flash('error', 'O CPF ou e-mail informado já pertence a outra conta.');
    bo_redirect_perfil();
}
$check->close();

$stmt = $conn->prepare(
    'UPDATE usuarios SET nome = ?, nacionalidade = ?, data_nascimento = ?, genero = ?, cpf = ?,
     endereco = ?, cidade_estado = ?, email = ?, celular = ?, altura = ?, peso = ?, foto = ?
     WHERE id_usuario = ?'
);
$stmt->bind_param(
    'sssssssssddsi',
    $nome,
    $nacionalidade,
    $nascimento,
    $genero,
    $cpf,
    $endereco,
    $cidadeEstado,
    $email,
    $celular,
    $altura,
    $peso,
    $foto,
    $idUsuario
);
$stmt->execute();
$stmt->close();

$_SESSION['nome'] = $nome;
$_SESSION['email'] = $email;
$_SESSION['genero'] = $genero;

bo_flash('success', 'Perfil atualizado com sucesso!');
bo_redirect_perfil();
