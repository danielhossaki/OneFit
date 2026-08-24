<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

header('Content-Type: application/json; charset=UTF-8');

function respond(int $status, bool $success, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Método não permitido.');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    respond(400, false, 'Dados inválidos.');
}

$csrfToken = (string) ($payload['csrf_token'] ?? '');
if (!$csrfToken || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    respond(403, false, 'Sua sessão expirou. Atualize a página e tente novamente.');
}

$nome = trim((string) ($payload['nome'] ?? ''));
$cpf = preg_replace('/\D/', '', (string) ($payload['documento'] ?? ''));
$email = trim((string) ($payload['email'] ?? ''));
$celular = preg_replace('/\D/', '', (string) ($payload['telefone'] ?? ''));
$nacionalidade = trim((string) ($payload['nacionalidade'] ?? ''));
$nascimento = trim((string) ($payload['nascimento'] ?? ''));
$genero = strtolower(trim((string) ($payload['genero'] ?? '')));
$endereco = trim((string) ($payload['endereco'] ?? ''));
$cidade = trim((string) ($payload['cidade'] ?? ''));
$estado = strtoupper(trim((string) ($payload['estado'] ?? '')));
$altura = $payload['altura'] === '' ? null : filter_var($payload['altura'] ?? null, FILTER_VALIDATE_FLOAT);
$peso = $payload['peso'] === '' ? null : filter_var($payload['peso'] ?? null, FILTER_VALIDATE_FLOAT);
$foto = trim((string) ($payload['foto'] ?? ''));

if (!$nome || !$cpf || !$email || !$celular || !$nacionalidade || !$nascimento || !$genero || !$endereco || !$cidade || !$estado) {
    respond(422, false, 'Preencha todos os campos obrigatórios.');
}
if (strlen($cpf) !== 11) {
    respond(422, false, 'Informe um CPF com 11 números.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Informe um e-mail válido.');
}
if (!in_array($genero, ['masculino', 'feminino', 'outro'], true)) {
    respond(422, false, 'Selecione um gênero válido.');
}
if (!preg_match('/^[A-Z]{2}$/', $estado)) {
    respond(422, false, 'Informe o estado usando uma UF válida.');
}

$dataNascimento = DateTime::createFromFormat('!Y-m-d', $nascimento);
$dataMinima = (new DateTime('today'))->modify('-12 years');
if (!$dataNascimento || $dataNascimento->format('Y-m-d') !== $nascimento || $dataNascimento > $dataMinima) {
    respond(422, false, 'Para manter seu perfil na ONE FIT, você precisa ter pelo menos 12 anos.');
}
if (($altura !== null && ($altura === false || $altura <= 0 || $altura > 3)) ||
    ($peso !== null && ($peso === false || $peso <= 0 || $peso > 500))) {
    respond(422, false, 'Confira os valores de altura e peso.');
}
if ($foto && (!filter_var($foto, FILTER_VALIDATE_URL) || strlen($foto) > 255)) {
    respond(422, false, 'Informe uma URL válida para a foto.');
}

$cidadeEstado = $cidade . '/' . $estado;
$idUsuario = (int) $_SESSION['id_usuario'];

try {
    $check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE (cpf = ? OR email = ?) AND id_usuario <> ? LIMIT 1');
    $check->bind_param('ssi', $cpf, $email, $idUsuario);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        respond(409, false, 'O CPF ou e-mail informado já pertence a outra conta.');
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
} catch (Throwable $erro) {
    respond(500, false, 'Não foi possível atualizar o perfil. Tente novamente.');
}

$_SESSION['nome'] = $nome;
$_SESSION['email'] = $email;
$_SESSION['genero'] = $genero;

respond(200, true, 'Perfil atualizado com sucesso!');
