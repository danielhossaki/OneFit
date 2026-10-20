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
    require_once __DIR__ . '/../includes/fotos-faciais.php';
    // conn.php revalidou a sessão; operador e pessoa são conferidos no banco pela
    // política central (config/papeis.php) em cada operação, nunca pelo que a tela exibe.
    $idOperador = (int) ($_SESSION['id_usuario'] ?? 0);
    if ($idOperador <= 0) {
        fotos_faciais_responder(false, onefitTraduzir('Sua sessão expirou. Entre novamente.'), 401);
    }
    if (!onefitExigirPapel($conn, $idOperador, onefitPapeisCom('opera_cadastro_facial'))) {
        fotos_faciais_responder(false, onefitTraduzir('Você não tem permissão para gerenciar fotos faciais.'), 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        fotos_faciais_responder(true, '', 200, ['pessoas' => bo_fotos_faciais_listar($conn, $idOperador, (string) ($_GET['q'] ?? ''))]);
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
    if ((int) $arquivo['size'] < 1 || (int) $arquivo['size'] > BO_FOTOS_FACIAIS_MAX_BYTES || !is_uploaded_file($arquivo['tmp_name'])) {
        fotos_faciais_responder(false, onefitTraduzir('A imagem está vazia, excede 5 MB ou não foi enviada corretamente.'), 422);
    }
    $imagem = file_get_contents($arquivo['tmp_name']);
    if ($imagem === false) {
        fotos_faciais_responder(false, onefitTraduzir('Não foi possível ler a imagem enviada.'), 422);
    }

    // Só o valor exato '1' confirma a substituição da foto ativa mais antiga.
    $substituir = ($_POST['substituir_mais_antiga'] ?? '') === '1';
    $resultado = bo_foto_facial_cadastrar($conn, $idOperador, $idUsuarioSelecionado, $imagem, $substituir);
    fotos_faciais_responder(true, $resultado['desativadas']
        ? onefitTraduzir('Foto facial cadastrada. A foto ativa mais antiga foi desativada e mantida no histórico.')
        : onefitTraduzir('Foto facial cadastrada com sucesso.'), 200, [
        'foto_cadastrada' => true,
        'id_foto_facial' => $resultado['id_foto_facial'],
        'fotos_ativas' => $resultado['fotos_ativas'],
    ]);
} catch (FotoFacialRecusada $recusa) {
    fotos_faciais_responder(false, $recusa->getMessage(), $recusa->status, $recusa->dados);
} catch (Throwable $erro) {
    error_log('ONE FIT: falha ao processar cadastro facial. Código: ' . $erro->getCode());
    fotos_faciais_responder(false, onefitTraduzir('Não foi possível processar a operação. Tente novamente.'), 500);
}
