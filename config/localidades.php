<?php
function onefitEstados(): array {
    return ['AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará',
        'DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso',
        'MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná',
        'PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul',
        'RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins'];
}
function onefitPaises(): array {
    static $countries;
    return $countries ??= json_decode(file_get_contents(__DIR__ . '/countries.json'), true, 512, JSON_THROW_ON_ERROR);
}
/** Legacy values can be kept unchanged, but newly chosen values must be ISO 3166-1 alpha-2. */
function onefitNacionalidadeValida(string $value, string $stored): bool {
    return ($stored !== '' && hash_equals($stored, $value)) || array_key_exists($value, onefitPaises());
}
function onefitPaisLegado(string $value): ?string {
    if (isset(onefitPaises()[$value])) return $value;
    $normalized = mb_strtolower(trim($value), 'UTF-8');
    $aliases = ['brasileira'=>'BR','brasileiro'=>'BR','brazil'=>'BR','portuguesa'=>'PT','português'=>'PT','portugues'=>'PT',
        'argentina'=>'AR','argentino'=>'AR','estadunidense'=>'US','americana'=>'US','americano'=>'US','espanhola'=>'ES','espanhol'=>'ES'];
    if (isset($aliases[$normalized])) return $aliases[$normalized];
    foreach (onefitPaises() as $code => $name) if (mb_strtolower($name, 'UTF-8') === $normalized) return $code;
    return null;
}
function onefitSelectLocalidade(string $id, string $name, string $value, string $kind): void {
    $escape = static fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $items = $kind === 'state' ? onefitEstados() : onefitPaises();
    echo '<select class="form-select" required data-flag-select="' . $kind . '" id="' . $escape($id) . '" name="' . $escape($name) . '">';
    echo '<option value="">' . (function_exists('of_t') ? of_t('Selecione') : 'Selecione') . '</option>';
    if ($value !== '' && !isset($items[$value])) {
        $code = $kind === 'country' ? onefitPaisLegado($value) : null;
        echo '<option selected value="' . $escape($value) . '" data-country="' . $escape($code ?? '') . '">' . $escape($value) . '</option>';
    }
    foreach ($items as $code => $label) {
        echo '<option value="' . $code . '"' . ($value === $code ? ' selected' : '') . '>' . ($kind === 'country' && function_exists('of_t') ? of_t($label) : $escape($label)) . ' (' . $code . ')</option>';
    }
    echo '</select>';
}
