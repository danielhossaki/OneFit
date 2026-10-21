<?php
require_once __DIR__ . '/../../../config/interface.php';
/**
 * helpers.php
 * Funções utilitárias usadas nas telas do backoffice (dashboard.php e
 * nos components/*.php). Mantidas separadas para não misturar lógica
 * de exibição com a marcação HTML.
 */

/**
 * Gera um <span> de status (verde = ativo / vermelho = inativo).
 * Usado nas tabelas de usuários, produtos, planos, profissionais etc.
 *
 * @param bool   $isActive  true = badge "ativo", false = badge "inativo"
 * @param string $onLabel   texto exibido quando ativo
 * @param string $offLabel  texto exibido quando inativo
 */
function bo_badge($isActive, $onLabel = 'Ativo', $offLabel = 'Inativo')
{
    $cls = $isActive ? 'bo-badge-active' : 'bo-badge-inactive';
    $label = of_t($isActive ? $onLabel : $offLabel);
    return '<span class="bo-badge ' . $cls . '">' . $label . '</span>';
}

/**
 * Formata um valor numérico como moeda brasileira (R$ 1.234,56).
 */
function bo_money($v)
{
    return onefitMoeda((float) $v);
}

/**
 * Converte um array PHP em JSON seguro para ser colocado dentro de
 * atributos/tags HTML (ex: o <script type="application/json"> com os
 * dados do treino, consumido por assets/js/treino.js). As flags JSON_HEX_*
 * evitam que aspas/tags quebrem o HTML.
 */
function bo_json($data)
{
    return json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
}

/**
 * Valida um celular/telefone brasileiro já sem máscara (só dígitos):
 * fixo tem 10 dígitos (DDD + 8), celular tem 11 (DDD + 9, começando com 9).
 * Também confere se o DDD informado é um código válido (11 a 99).
 */
function bo_valida_celular(string $digitsOnly): bool
{
    $tamanho = strlen($digitsOnly);
    if (!in_array($tamanho, [10, 11], true)) {
        return false;
    }
    $ddd = (int) substr($digitsOnly, 0, 2);
    if ($ddd < 11 || $ddd > 99) {
        return false;
    }
    if ($tamanho === 11 && $digitsOnly[2] !== '9') {
        return false;
    }
    return true;
}

/**
 * Processa (se houver) o upload de uma imagem enviada em $_FILES[$inputName]
 * e move para assets/img/uploads/<subpasta>/, com nome único. Usado nos
 * campos "Foto/Imagem" que aceitam upload de arquivo OU URL — o upload,
 * quando presente, tem prioridade sobre a URL digitada.
 *
 * @return string|null URL pública do arquivo salvo, ou null se nenhum
 *                      arquivo válido foi enviado neste campo.
 */
function bo_processar_upload_imagem(string $inputName, string $subpasta): ?string
{
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $arquivo = $_FILES[$inputName];
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($arquivo['size'] > 3 * 1024 * 1024) {
        return null;
    }

    $extensoesPermitidas = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $mime = mime_content_type($arquivo['tmp_name']);
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!isset($extensoesPermitidas[$extensao]) || $extensoesPermitidas[$extensao] !== $mime) {
        return null;
    }

    $pastaFisica = $_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/img/uploads/' . $subpasta . '/';
    if (!is_dir($pastaFisica)) {
        mkdir($pastaFisica, 0755, true);
    }

    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
    if (!move_uploaded_file($arquivo['tmp_name'], $pastaFisica . $nomeArquivo)) {
        return null;
    }

    return BASE_URL . 'assets/img/uploads/' . $subpasta . '/' . $nomeArquivo;
}

function bo_foto_perfil_url(?string $foto): string
{
    $foto = trim($foto ?? '');
    if (str_starts_with($foto, BASE_URL . 'assets/img/uploads/perfil/')) {
        return bo_foto_perfil_ampliavel($foto);
    }
    return filter_var($foto, FILTER_VALIDATE_URL) &&
        in_array(strtolower(parse_url($foto, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
        ? $foto
        : '';
}

function bo_foto_perfil_ampliavel(?string $foto): string
{
    $prefix = BASE_URL . 'assets/img/uploads/perfil/';
    if (!is_string($foto) || !str_starts_with($foto, $prefix)) return '';
    $name = substr($foto, strlen($prefix));
    if (!preg_match('/^[a-zA-Z0-9_-]+\.(?:jpe?g|png|webp)$/iD', $name)) return '';
    $root = realpath(__DIR__ . '/../../../assets/img/uploads/perfil');
    $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $name) : false;
    if (!$file || dirname($file) !== $root || !is_file($file)) return '';
    $info = @getimagesize($file);
    return $info && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) ? $foto : '';
}

function bo_foto_perfil_upload(): ?string
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

/**
 * Selo "Somente consulta" no título das telas que o cargo só visualiza
 * (Alunos, Pagamentos, Modalidades e Cashback na recepção; Categorias e
 * Cashback no vendedor). A tela não traz botões nem formulários de gravação,
 * e as rotas de gravação recusam esses cargos no servidor.
 */
function bo_selo_somente_consulta(): string
{
    return '<span class="bo-eyebrow" data-bo-somente-consulta><i class="bi bi-eye"></i> ' . of_t('Somente consulta') . '</span>';
}
