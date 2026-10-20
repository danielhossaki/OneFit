<?php

require_once __DIR__ . '/../../../config/parametros.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function fotos_faciais_responder(bool $ok, string $mensagem, int $status = 200, array $dados = []): never
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $mensagem] + $dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['id_usuario'])) {
    fotos_faciais_responder(false, onefitTraduzir('Sua sessão expirou. Entre novamente.'), 401);
}

try {
    require_once __DIR__ . '/../../../config/conn.php';
    // conn.php revalidou a sessão; a permissão do operador é conferida no banco
    // pela política central (config/papeis.php), nunca pelo que a tela exibe.
    $idOperador = (int) ($_SESSION['id_usuario'] ?? 0);
    if ($idOperador <= 0) {
        fotos_faciais_responder(false, onefitTraduzir('Sua sessão expirou. Entre novamente.'), 401);
    }
    if (!onefitExigirPapel($conn, $idOperador, onefitPapeisCom('opera_cadastro_facial'))) {
        fotos_faciais_responder(false, onefitTraduzir('Você não tem permissão para gerenciar fotos faciais.'), 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $termo = trim((string) ($_GET['q'] ?? ''));
        $termo = mb_substr($termo, 0, 100, 'UTF-8');
        $busca = '%' . $termo . '%';
        $stmt = $conn->prepare(
            "SELECT u.id_usuario, u.nome,
                    u.tipo_usuario,
                    EXISTS (
                        SELECT 1 FROM usuarios_fotos_faciais f
                        WHERE f.id_usuario = u.id_usuario AND f.ativo = 1
                          AND f.imagem_blob IS NOT NULL
                    ) AS foto_cadastrada
             FROM usuarios u
             WHERE u.tipo_usuario IN ('aluno', 'profissional', 'admin')
               AND u.status = 'ativo'
               AND (? = '' OR u.nome LIKE ? OR u.cpf LIKE ?
                    OR CAST(u.id_usuario AS CHAR) LIKE ?)
             ORDER BY u.nome, u.id_usuario"
        );
        $stmt->bind_param('ssss', $termo, $busca, $busca, $busca);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $pessoas = [];
        while ($row = $resultado->fetch_assoc()) {
            $pessoas[] = [
                'id' => (int) $row['id_usuario'],
                'nome' => $row['nome'],
                'tipo_usuario' => $row['tipo_usuario'],
                'foto_cadastrada' => (bool) $row['foto_cadastrada'],
            ];
        }
        $stmt->close();
        fotos_faciais_responder(true, '', 200, ['pessoas' => $pessoas]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        fotos_faciais_responder(false, onefitTraduzir('Método não permitido.'), 405);
    }

    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || $token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        fotos_faciais_responder(false, onefitTraduzir('Sua sessão expirou. Atualize a página e tente novamente.'), 403);
    }

    $idUsuarioSelecionado = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
    if (!$idUsuarioSelecionado || $idUsuarioSelecionado < 1) {
        fotos_faciais_responder(false, onefitTraduzir('Selecione uma pessoa válida.'), 422);
    }

    $arquivo = $_FILES['imagem'] ?? null;
    if (!is_array($arquivo) || !isset($arquivo['error'], $arquivo['tmp_name'], $arquivo['size'])) {
        fotos_faciais_responder(false, onefitTraduzir('Envie uma foto para continuar.'), 422);
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $status = in_array($arquivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 422;
        fotos_faciais_responder(false, onefitTraduzir('Não foi possível receber a imagem. Tente capturá-la novamente.'), $status);
    }
    $limiteImagem = 5 * 1024 * 1024;
    if ((int) $arquivo['size'] < 1 || (int) $arquivo['size'] > $limiteImagem || !is_uploaded_file($arquivo['tmp_name'])) {
        fotos_faciais_responder(false, onefitTraduzir('A imagem está vazia, excede 5 MB ou não foi enviada corretamente.'), 422);
    }

    $imagem = file_get_contents($arquivo['tmp_name']);
    if ($imagem === false || strlen($imagem) > $limiteImagem) {
        fotos_faciais_responder(false, onefitTraduzir('Não foi possível ler a imagem enviada.'), 422);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($imagem);
    $infoImagem = getimagesizefromstring($imagem);
    $tiposAceitos = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG];
    if (!is_array($infoImagem)
        || !isset($tiposAceitos[$mime])
        || $infoImagem[2] !== $tiposAceitos[$mime]
        || $infoImagem[0] < 1
        || $infoImagem[1] < 1
        || $infoImagem[0] > 4096
        || $infoImagem[1] > 4096
        || $infoImagem[0] * $infoImagem[1] > 16777216) {
        fotos_faciais_responder(false, onefitTraduzir('Formato de imagem inválido. Envie JPEG ou PNG de até 16 megapixels.'), 422);
    }
    if (!function_exists('imagecreatefromstring')) {
        error_log('ONE FIT: extensão GD indisponível para validar imagens faciais.');
        fotos_faciais_responder(false, onefitTraduzir('A validação de imagens está indisponível no servidor. Avise o administrador.'), 503);
    }
    $imagemDecodificada = @imagecreatefromstring($imagem);
    if ($imagemDecodificada === false) {
        fotos_faciais_responder(false, onefitTraduzir('A imagem não pôde ser decodificada. Capture outra foto.'), 422);
    }
    imagedestroy($imagemDecodificada);

    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start facial photo transaction.');
    }

    $stmt = $conn->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE id_usuario = ?
           AND tipo_usuario IN ('aluno', 'profissional', 'admin')
           AND status = 'ativo'
         LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $idUsuarioSelecionado);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not verify selected facial photo subject.');
    }
    $cadastro = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cadastro) {
        $conn->rollback();
        fotos_faciais_responder(false, onefitTraduzir('Pessoa ativa não encontrada ou não permitida para cadastro facial.'), 404);
    }
    $idUsuario = (int) $cadastro['id_usuario'];

    $stmt = $conn->prepare('UPDATE usuarios_fotos_faciais SET ativo = 0 WHERE id_usuario = ? AND ativo = 1');
    $stmt->bind_param('i', $idUsuario);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not update previous facial photos.');
    }
    $stmt->close();

    $blobVazio = '';
    $stmt = $conn->prepare('INSERT INTO usuarios_fotos_faciais (id_usuario, imagem_blob, ativo) VALUES (?, ?, 1)');
    $stmt->bind_param('ib', $idUsuario, $blobVazio);
    if (!$stmt->send_long_data(1, $imagem) || !$stmt->execute()) {
        throw new RuntimeException('Could not store facial photo bytes.');
    }
    $idFotoFacial = (int) $stmt->insert_id;
    $stmt->close();

    if (!$conn->commit()) {
        throw new RuntimeException('Could not commit facial photo transaction.');
    }
    fotos_faciais_responder(true, onefitTraduzir('Foto facial cadastrada com sucesso.'), 200, [
        'foto_cadastrada' => true,
        'id_foto_facial' => $idFotoFacial,
    ]);
} catch (Throwable $erro) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $erroRollback) {
            error_log('ONE FIT: falha ao reverter gravação de foto facial.');
        }
    }
    error_log('ONE FIT: falha ao processar cadastro facial. Código: ' . $erro->getCode());
    fotos_faciais_responder(false, onefitTraduzir('Não foi possível processar a operação. Tente novamente.'), 500);
}
