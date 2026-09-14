<?php
/** Shared presentation settings. No writes or schema changes during requests. */
function onefitIdiomas(): array { return ['pt-BR', 'en', 'es']; }
function onefitIdentidades(): array {
    return [
        'dourado' => ['name' => 'One Fit', 'logo' => 'logo_onefit.webp'],
        'azul' => ['name' => 'Sky Fit', 'logo' => 'logo_skyfit.webp'],
        'verde' => ['name' => 'Nature Fit', 'logo' => 'logo_naturefit.webp'],
        'vermelho' => ['name' => 'Blood Fit', 'logo' => 'logo_bloodfit.webp'],
        'roxo' => ['name' => 'Purple Fit', 'logo' => 'logo_purplefit.webp'],
    ];
}
function onefitTemas(): array { return array_keys(onefitIdentidades()); }
function onefitMarca(?string $theme = null): array {
    $brands = onefitIdentidades();
    $brand = $brands[$theme ?? ($GLOBALS['onefitTemaGlobal'] ?? 'dourado')] ?? $brands['dourado'];
    if (!is_file(__DIR__ . '/../assets/img/logo/' . $brand['logo'])) $brand['logo'] = $brands['dourado']['logo'];
    return $brand;
}
function onefitLogo(): string {
    return htmlspecialchars(BASE_URL . 'assets/img/logo/' . onefitMarca()['logo'], ENT_QUOTES, 'UTF-8');
}
function onefitNomeMarca(): string { return htmlspecialchars(onefitMarca()['name'], ENT_QUOTES, 'UTF-8'); }
/* Separa o nome da marca do tema em duas partes na última palavra (sempre
   "Fit" nos temas atuais): ['prefixo' => 'One', 'destaque' => 'Fit']. */
function onefitMarcaPartes(): array {
    $nome = trim(onefitMarca()['name']);
    $pos = strrpos($nome, ' ');
    return [
        'prefixo' => $pos === false ? '' : substr($nome, 0, $pos),
        'destaque' => $pos === false ? $nome : substr($nome, $pos + 1),
    ];
}
/* Wordmark em texto (sem logo em imagem, sem espaço entre as partes): usado
   no link de voltar para a home das telas de login/matrícula/recuperação de
   senha — o CSS de .login-logo já deixa tudo em uppercase e pinta o <span>
   de dourado. */
function onefitWordmarkHtml(): string {
    $partes = onefitMarcaPartes();
    return htmlspecialchars($partes['prefixo'], ENT_QUOTES, 'UTF-8') . '<span>' . htmlspecialchars($partes['destaque'], ENT_QUOTES, 'UTF-8') . '</span>';
}
/* Nome da marca com espaço entre as partes: prefixo com efeito "reflexo"
   branco (.brand-nome) e a última palavra na cor de destaque do tema
   (.brand-fit) — usado onde já existia [data-brand-name] (navbar, sidebar
   do dashboard, marketplace). Ver assets/js/interface.js: updateBrand()
   gera a mesma estrutura ao trocar de tema sem recarregar a página. */
function onefitBrandNameHtml(): string {
    $partes = onefitMarcaPartes();
    return '<span class="brand-nome">' . htmlspecialchars($partes['prefixo'], ENT_QUOTES, 'UTF-8') . '</span> '
        . '<span class="brand-fit">' . htmlspecialchars($partes['destaque'], ENT_QUOTES, 'UTF-8') . '</span>';
}
function onefitIdioma(): string {
    $value = $_SESSION['idioma'] ?? 'pt-BR';
    return in_array($value, onefitIdiomas(), true) ? $value : 'pt-BR';
}
function onefitTraduzir(string $text, array $values = [], ?string $locale = null): string {
    static $catalogs = [];
    $locale = $locale === null ? onefitIdioma() : (in_array($locale, onefitIdiomas(), true) ? $locale : 'pt-BR');
    if (!isset($catalogs[$locale])) $catalogs[$locale] = require __DIR__ . '/locales/' . $locale . '.php';
    return strtr($catalogs[$locale][$text] ?? $text, $values);
}
function of_t(string $text, array $values = []): string {
    return htmlspecialchars(onefitTraduzir($text, $values), ENT_QUOTES, 'UTF-8');
}
function onefitCarregarInterface(mysqli $db): void {
    $GLOBALS['onefitTemaGlobal'] = 'dourado';
    try {
        $row = $db->query("SELECT valor FROM configuracoes_site WHERE chave = 'tema_cores'")->fetch_assoc();
        if (in_array($row['valor'] ?? '', onefitTemas(), true)) $GLOBALS['onefitTemaGlobal'] = $row['valor'];
    } catch (Throwable $e) { /* Migration not installed: existing gold theme. */ }
    if (!empty($_SESSION['id_usuario'])) {
        try {
            $stmt = $db->prepare('SELECT idioma FROM preferencias_usuario WHERE id_usuario = ?');
            $stmt->bind_param('i', $_SESSION['id_usuario']);
            $stmt->execute();
            $value = $stmt->get_result()->fetch_assoc()['idioma'] ?? 'pt-BR';
            $_SESSION['idioma'] = in_array($value, onefitIdiomas(), true) ? $value : 'pt-BR';
            $stmt->close();
        } catch (Throwable $e) { $_SESSION['idioma'] = 'pt-BR'; }
    }
}
function onefitAdminAutorizado(mysqli $db, int $id): bool {
    $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'admin' AND status = 'ativo'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}
function onefitInterfaceHead(): void {
    $base = htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8');
    $version = filemtime(__DIR__ . '/../assets/css/interface.css');
    echo '<link rel="stylesheet" href="' . $base . 'assets/css/interface.css?v=' . $version . '">';
    $locale = onefitIdioma();
    $catalog = require __DIR__ . '/locales/' . $locale . '.php';
    $data = ['locale' => $locale, 'messages' => $catalog, 'base' => BASE_URL,
        'brands' => array_combine(onefitTemas(), array_map('onefitMarca', onefitTemas())),
        'brandFallback' => onefitMarca('dourado')];
    echo '<script id="onefit-interface-data" type="application/json">' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . '</script>';
    echo '<script src="' . $base . 'assets/js/i18n.js?v=' . filemtime(__DIR__ . '/../assets/js/i18n.js') . '"></script>';
    echo '<script defer src="' . $base . 'assets/js/interface.js?v=' . filemtime(__DIR__ . '/../assets/js/interface.js') . '"></script>';
}
