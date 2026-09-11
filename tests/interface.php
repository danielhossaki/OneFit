<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/interface.php';
require __DIR__ . '/../config/localidades.php';
require __DIR__ . '/../pages/dashboard/includes/aluno-profile.php';
$checks = 0;
function interfaceCheck(bool $value, string $message): void { global $checks; if (!$value) throw new RuntimeException($message); $checks++; }
foreach (['en'=>'Save','es'=>'Guardar','pt-BR'=>'Salvar'] as $locale=>$expected) {
    $_SESSION['idioma'] = $locale;
    interfaceCheck(onefitTraduzir('Salvar') === $expected, 'Translation lookup');
    interfaceCheck(onefitTraduzir('UNTRANSLATED USER CONTENT') === 'UNTRANSLATED USER CONTENT', 'Portuguese/source fallback');
}
$_SESSION['idioma'] = '../../evil'; interfaceCheck(onefitIdioma() === 'pt-BR', 'Reject unknown locale');
$_SESSION['idioma'] = 'en'; interfaceCheck(onefitTraduzir('Salvar', [], 'unknown') === 'Salvar', 'Explicit unknown locale falls back to Portuguese');
interfaceCheck(count(onefitPaises()) === 249 && onefitPaises()['BR'] === 'Brasil', 'ISO countries');
interfaceCheck(count(onefitEstados()) === 27 && onefitEstados()['SP'] === 'São Paulo', 'State values');
interfaceCheck(onefitNacionalidadeValida('brasileira','brasileira'), 'Legacy kept');
interfaceCheck(onefitNacionalidadeValida('PT','brasileira'), 'ISO accepted');
interfaceCheck(!onefitNacionalidadeValida('inventada','brasileira'), 'Unknown country rejected');
interfaceCheck(!onefitNacionalidadeValida('XX','brasileira'), 'Unknown ISO rejected');
interfaceCheck(onefitPaisLegado('brasileira') === 'BR' && onefitPaisLegado('Portugal') === 'PT', 'Legacy flags');
define('BASE_URL', 'http://127.0.0.1:8765/AN25/OneFit/');
foreach (['https://example.test/photo.jpg', BASE_URL.'assets/img/uploads/perfil/../secret.jpg', BASE_URL.'assets/img/uploads/perfil/%2e%2e/secret.jpg', BASE_URL.'assets/img/uploads/perfil/photo.svg', BASE_URL.'assets/img/uploads/perfil/missing.png'] as $bad) interfaceCheck(bo_aluno_foto_ampliavel($bad) === '', 'Photo confinement');
$manifest = json_decode(ltrim(file_get_contents(__DIR__ . '/../assets/img/flags/states/sources.json'), "\xef\xbb\xbf"), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest as $flag) {
    $path = __DIR__ . '/../assets/img/flags/states/' . strtolower($flag['uf']) . '.svg';
    interfaceCheck(isset(onefitEstados()[$flag['uf']]) && strtolower($flag['sha256']) === hash_file('sha256',$path), 'Flag mapping/hash');
    $xml = new DOMDocument(); interfaceCheck($xml->load($path, LIBXML_NONET) && $xml->documentElement->localName === 'svg', 'SVG parses');
}
echo "Interface unit checks: $checks passed.\n";
