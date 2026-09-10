<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;

/** Lista positiva: texto livre pode ecoar PII, mesmo em message/description.
 * Palavras desconhecidas e valores sao omitidos, nunca o corpo completo.
 */
final class DiagnosticoPagamento
{
    public static function texto(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $allowed = explode(' ', 'invalid valid missing required unsupported unknown not found allowed available enabled disabled cannot must be is are the a an and or of for in to from with without at least greater less than minimum maximum days date dates expiration expired format value parameter parameters field fields payment payments methods method types type excluded default preference preferences request bad error pix bank_transfer account_money credit_card debit_card prepaid_card ticket atm currency_id BRL quantity unit_price items title description external_reference expires expiration_date_from expiration_date_to date_of_expiration payment_methods excluded_payment_types excluded_payment_methods default_payment_method_id back_urls auto_return notification_url invalid_payment_methods invalid_payment_type invalid_payment_method bad_request invalid_expiration_date invalid_date_of_expiration invalid_access_token unauthorized forbidden internal_server_error');
        $tokens = preg_split('/\s+/', substr($value, 0, 800));
        return implode(' ', array_map(static function ($token) use ($allowed) {
            $word = trim($token, ".,:;()[]\"'");
            return in_array($word, $allowed, true) ? $word : '[omitido]';
        }, $tokens));
    }

    public static function extrair(array $body): array
    {
        $safe = [];
        foreach (['message','error'] as $key) if (isset($body[$key])) $safe[$key] = self::texto($body[$key]);
        foreach (array_slice(is_array($body['cause'] ?? null) ? $body['cause'] : [], 0, 10) as $cause) {
            if (!is_array($cause)) continue;
            $item = [];
            if (isset($cause['code'])) {
                $code = (string) $cause['code'];
                $item['code'] = preg_match('/^[0-9]{1,4}$/D', $code) ? $code : self::texto($code);
            }
            if (isset($cause['description'])) $item['description'] = self::texto($cause['description']);
            // data e opcional: somente campos tecnicos conhecidos, nenhum valor livre.
            if (is_array($cause['data'] ?? null)) {
                foreach (['field','path','parameter'] as $key) {
                    if (isset($cause['data'][$key])) {
                        $text = self::texto($cause['data'][$key]);
                        if ($text !== null && !str_contains($text, '[omitido]')) $item['data'][$key] = $text;
                    }
                }
            }
            $safe['cause'][] = $item;
        }
        return $safe;
    }
}
