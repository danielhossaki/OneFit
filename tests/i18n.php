<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/interface.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../pages/dashboard/includes/treino.php';
$checks = 0;
function i18nCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$catalogs = [];
foreach (onefitIdiomas() as $locale) $catalogs[$locale] = require __DIR__ . '/../config/locales/' . $locale . '.php';
$seen = [];
foreach (file(__DIR__ . '/../config/locales/messages.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $index => $row) {
    $parts = explode('|', $row);
    i18nCheck(count($parts) === 3 && !in_array('', $parts, true), 'Invalid catalogue row ' . ($index + 1));
    [$key, $en, $es] = $parts;
    i18nCheck(!isset($seen[$key]), 'Duplicate key: ' . $key);
    $seen[$key] = true;
    i18nCheck(mb_check_encoding($row, 'UTF-8'), 'Invalid UTF-8: ' . $key);
    $placeholders = static function ($text) { preg_match_all('/\{[^{}]+\}/u', $text, $matches); sort($matches[0]); return $matches[0]; };
    i18nCheck($placeholders($key) === $placeholders($en) && $placeholders($key) === $placeholders($es), 'Placeholder mismatch: ' . $key);
}
foreach ($catalogs as $locale => $catalog) {
    i18nCheck(array_keys($catalog) === array_keys($catalogs['pt-BR']), 'Locale key parity: ' . $locale);
    $_SESSION['idioma'] = $locale;
    foreach (onefitIdentidades() as $theme => $brand) {
        $GLOBALS['onefitTemaGlobal'] = $theme;
        i18nCheck(onefitTraduzir($brand['name']) === $brand['name'], 'Brand must stay intact');
        i18nCheck(str_contains(onefitTraduzir('Bem-vindo à {marca}'), $brand['name']), 'Active brand interpolation');
        i18nCheck(onefitTraduzir('Olá, {nome}!', ['{nome}' => 'Test {marca}']) === str_replace('{nome}', 'Test {marca}', $catalog['Olá, {nome}!']), 'Do not translate interpolated user data');
        $html = onefitTemplateEmail('<Alice>', 'Recebemos uma solicitação para redefinir a senha da sua conta.', 'Redefinir minha senha', 'https://example.test/?a=1&b=2', 'Confirmar meu e-mail');
        i18nCheck(str_contains($html, 'lang="' . $locale . '"') && str_contains($html, $brand['name']) && str_contains($html, '&lt;Alice&gt;'), 'Localized and escaped email');
    }
    foreach (array_merge(array_values(bo_treino_dias()), array_keys(bo_treino_catalogo()), ...array_values(bo_treino_catalogo())) as $label) {
        i18nCheck(isset($catalog[$label]), 'Exercise/day label missing: ' . $label);
    }
}
i18nCheck(onefitNormalizarIdioma('pt') === 'pt-BR', 'Portuguese alias');
i18nCheck(onefitNumero(1234.5, 2, 'en') === '1,234.50', 'English numbers');
i18nCheck(onefitNumero(1234.5, 2, 'es') === '1.234,50', 'Spanish numbers');
// Scan literal translation calls, including JS and embedded PHP/JS. Dynamic labels
// have explicit catalogue coverage above and are checked in browser tests.
$roots = ['pages', 'components', 'assets/js'];
$files = [__DIR__ . '/../index.php', __DIR__ . '/../config/interface.php', __DIR__ . '/../config/mailer.php', __DIR__ . '/../config/notificacoes.php'];
foreach ($roots as $root) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../' . $root)) as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) $files[] = $file->getPathname();
}
foreach ($files as $file) {
    $source = file_get_contents($file);
    preg_match_all('/\b(?:of_t|ofT|onefitTraduzir)\(\s*([\x27"])((?:\\\\.|(?!\1)[^\\\\])*?)\1/s', $source, $matches, PREG_SET_ORDER);
    // Flash/authentication messages are often stored before presentation.
    preg_match_all('/\$(?:erro|sucesso|mensagem|message)\s*=\s*([\x27"])((?:\\\\.|(?!\1)[^\\\\])*?)\1/s', $source, $storedMessages, PREG_SET_ORDER);
    $matches = array_merge($matches, array_filter($storedMessages, static fn($match) => $match[2] !== ''));
    foreach ($matches as $match) {
        $key = str_replace(["\\'", '\\"'], ["'", '"'], $match[2]);
        i18nCheck(isset($catalogs['pt-BR'][$key]), 'Missing key in ' . $file . ': ' . $key);
    }
}
echo "i18n: $checks checks passed; " . count($seen) . " keys; 3 locales; 5 brands.\n";
