<?php
require_once __DIR__ . '/../../../config/interface.php';
/** Medidas e IMC derivados dos campos existentes em usuarios. */
function bo_aluno_medida($valor, float $maximo): ?float
{
    $texto = str_replace(',', '.', trim((string) $valor));
    if (!preg_match('/^\d+(?:\.\d+)?$/D', $texto)) return null;
    $numero = round((float) $texto, 2); // Mesma precisão DECIMAL(5,2) do banco.
    return is_finite($numero) && $numero > 0 && $numero <= $maximo ? $numero : null;
}

function bo_aluno_imc($altura, $peso): array
{
    $altura = bo_aluno_medida($altura, 3);
    $peso = bo_aluno_medida($peso, 500);
    if ($altura === null || $peso === null || $altura * $altura == 0) return ['valor' => null, 'classe' => onefitTraduzir('Não informado')];
    $imc = $peso / ($altura * $altura);
    if (!is_finite($imc)) return ['valor' => null, 'classe' => onefitTraduzir('Não informado')];
    $classe = match (true) {
        $imc < 18.5 => onefitTraduzir('Abaixo do peso'),
        $imc < 25 => onefitTraduzir('Peso adequado'),
        $imc < 30 => onefitTraduzir('Sobrepeso'),
        $imc < 35 => onefitTraduzir('Obesidade grau I'),
        $imc < 40 => onefitTraduzir('Obesidade grau II'),
        default => onefitTraduzir('Obesidade grau III'),
    };
    return ['valor' => $imc, 'classe' => $classe];
}

function bo_aluno_foto_url(?string $foto): string
{
    $foto = trim($foto ?? '');
    if (str_starts_with($foto, BASE_URL . 'assets/img/uploads/perfil/')) return bo_aluno_foto_ampliavel($foto);
    return filter_var($foto, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($foto, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $foto : '';
}

/** Only real images within the profile upload directory may be enlarged. */
function bo_aluno_foto_ampliavel(?string $foto): string
{
    $prefix = BASE_URL . 'assets/img/uploads/perfil/';
    if (!is_string($foto) || !str_starts_with($foto, $prefix)) return '';
    $name = substr($foto, strlen($prefix));
    if (!preg_match('/^[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$/iD', $name)) return '';
    $root = realpath(__DIR__ . '/../../../assets/img/uploads/perfil');
    $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;
    if (!$file || dirname($file) !== $root || !is_file($file)) return '';
    $info = @getimagesize($file);
    return $info && in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true) ? $foto : '';
}

/** Valida conteúdo, extensão e tamanho antes de usar o upload existente. */
function bo_aluno_upload(): ?string
{
    $arquivo = $_FILES['foto_arquivo'] ?? null;
    if (!$arquivo || ($arquivo['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
    if (!is_array($arquivo) || !isset($arquivo['error'], $arquivo['tmp_name'], $arquivo['name'], $arquivo['size']) ||
        !is_int($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK ||
        !is_string($arquivo['tmp_name']) || !is_string($arquivo['name']) || !is_uploaded_file($arquivo['tmp_name'])) {
        throw new RuntimeException('Não foi possível receber a foto. Selecione o arquivo novamente.');
    }
    $tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $info = @getimagesize($arquivo['tmp_name']);
    if ($arquivo['size'] <= 0 || $arquivo['size'] > 3 * 1024 * 1024 || !$info ||
        !isset($tipos[$ext]) || $tipos[$ext] !== mime_content_type($arquivo['tmp_name']) ||
        $tipos[$ext] !== ($info['mime'] ?? '') || $info[0] * $info[1] > 40000000) {
        throw new RuntimeException('Envie uma imagem JPG, PNG ou WEBP válida, de até 3 MB e 40 megapixels.');
    }
    $foto = bo_processar_upload_imagem('foto_arquivo', 'perfil');
    if ($foto === null) throw new RuntimeException('Não foi possível salvar a foto. Tente novamente.');
    return $foto;
}
