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

$isAluno = ($_SESSION['tipo_usuario'] ?? '') === 'aluno';
if ($isAluno) {
    require_once __DIR__ . '/../includes/aluno-profile.php';
    $idAluno = (int) $_SESSION['id_usuario'];
    $consultaFoto = $conn->prepare('SELECT foto FROM usuarios WHERE id_usuario = ?');
    $consultaFoto->bind_param('i', $idAluno);
    $consultaFoto->execute();
    $perfilAtual = $consultaFoto->get_result()->fetch_assoc();
    $consultaFoto->close();
    if (!$perfilAtual) { bo_flash('error', 'Conta não encontrada.'); bo_redirect_perfil(); }
    if (bo_str('acao') === 'foto') {
        try {
            $novaFoto = bo_aluno_upload();
            if ($novaFoto === null) throw new RuntimeException('Selecione uma foto.');
            $salvarFoto = $conn->prepare('UPDATE usuarios SET foto = ? WHERE id_usuario = ?');
            $salvarFoto->bind_param('si', $novaFoto, $idAluno);
            $salvarFoto->execute();
            $salvarFoto->close();
            bo_flash('success', 'Foto atualizada com sucesso!');
        } catch (RuntimeException $erro) {
            bo_flash('error', $erro instanceof mysqli_sql_exception ? 'Não foi possível salvar a foto.' : $erro->getMessage());
        }
        bo_redirect_perfil();
    }
}

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
$fotoAtual = bo_str('foto_atual');

$altura = $alturaStr === '' ? null : filter_var($alturaStr, FILTER_VALIDATE_FLOAT);
$peso = $pesoStr === '' ? null : filter_var($pesoStr, FILTER_VALIDATE_FLOAT);
if ($isAluno) {
    $altura = $alturaStr === '' ? null : (bo_aluno_medida($alturaStr, 3) ?? false);
    $peso = $pesoStr === '' ? null : (bo_aluno_medida($pesoStr, 500) ?? false);
    $fotoAtual = $perfilAtual['foto']; // Nunca confiar no caminho enviado pelo navegador.
}

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

try {
    $fotoUpload = $isAluno ? bo_aluno_upload() : bo_processar_upload_imagem('foto_arquivo', 'perfil');
} catch (RuntimeException $erro) {
    bo_flash('error', $erro->getMessage());
    bo_redirect_perfil();
}
$foto = $fotoUpload ?? $fotoAtual;
if ($isAluno) $imcCalculado = bo_aluno_imc($altura, $peso); // Derivado, não persistido.

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
try {
    $stmt->execute();
} catch (mysqli_sql_exception $erro) {
    if (!$isAluno) throw $erro;
    bo_flash('error', 'Não foi possível atualizar o perfil. Confira os dados e tente novamente.');
    bo_redirect_perfil();
}
$stmt->close();

$_SESSION['nome'] = $nome;
$_SESSION['email'] = $email;
$_SESSION['genero'] = $genero;

bo_flash('success', 'Perfil atualizado com sucesso!');
bo_redirect_perfil();
