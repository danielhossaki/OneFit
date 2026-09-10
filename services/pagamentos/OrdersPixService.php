<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;

require_once __DIR__ . '/PagamentoException.php';
require_once __DIR__ . '/OrdersPixValidacao.php';

/** Orders/Pix isolado, sem banco e sem dependencia do fluxo Checkout Pro. */
final class OrdersPixService
{
    public static function decimal(int $centavos): string
    {
        if ($centavos <= 0 || $centavos > 999999999999) throw new \InvalidArgumentException('Valor invalido.');
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }

    /** Preparacao apenas de teste. 5000 e fixture oficial, nao minimo comercial.
     * O envelope deve ser persistido/reutilizado futuramente, sem regenerar UUID.
     */
    public function prepararTesteOficial(): array { return $this->prepararTeste(5000); }

    public function prepararTeste(int $centavos): array
    {
        $valor = self::decimal($centavos);
        return ['ambiente'=>'testing', 'moeda'=>'BRL', 'valor_centavos'=>$centavos,
            'idempotencia'=>self::uuidV4(), 'payload'=>[
                'type'=>'online', 'processing_mode'=>'automatic',
                'external_reference'=>bin2hex(random_bytes(16)), 'total_amount'=>$valor,
                'payer'=>['email'=>'test_user_br@testuser.com', 'first_name'=>'APRO'],
                'transactions'=>['payments'=>[['amount'=>$valor,
                    'payment_method'=>['id'=>'pix','type'=>'bank_transfer']]]],
            ]];
        // Orders usa country_code BRA da conta; nao inventar currency_id no payload.
    }

    public static function statusInterno(string $status, ?string $detail): string
    {
        return match ($status) {
            'created' => 'criado',
            'action_required', 'processing', 'pending', 'in_process' => 'pendente',
            'processed', 'approved' => in_array($detail, ['accredited', 'partially_refunded'], true) ? 'aprovado' : 'pendente',
            'failed', 'rejected' => 'recusado',
            'canceled', 'cancelled' => 'cancelado',
            'expired' => 'expirado',
            'refunded' => 'estornado',
            default => throw new PagamentoException(),
        };
    }

    /** Retorna somente campos necessarios. QR/URL sao sensiveis: nao logar o array. */
    public static function normalizar(#[\SensitiveParameter] array $data, ?\DateTimeImmutable $agora = null): array
    {
        $agora ??= new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        $receiver=$data['user_id']??null;
        if (is_int($receiver)) $receiver=(string)$receiver;
        if (!is_string($receiver)||!preg_match('/^[1-9][0-9]{0,99}$/D',$receiver)) throw new PagamentoException();
        $created=OrdersPixValidacao::data($data['created_date']??null,$agora);
        $updated=OrdersPixValidacao::data($data['last_updated_date']??null,$agora);
        if ($updated<$created) throw new PagamentoException();
        $payments = $data['transactions']['payments'] ?? [];
        if (count($payments) !== 1) throw new PagamentoException();
        $p = $payments[0]; $method = $p['payment_method'] ?? [];
        foreach ([$data['id']??'', $p['id']??''] as $id) {
            if (!is_string($id) || !preg_match('/^(ORD|PAY)[A-Z0-9]{1,60}$/D', $id)) throw new PagamentoException();
        }
        $amount = $data['total_amount'] ?? '';
        if (!is_string($amount) || !preg_match('/^[0-9]{1,10}\.[0-9]{2}$/D', $amount)
            || $amount !== ($p['amount'] ?? null) || !in_array($data['country_code'] ?? '', ['BRA','BR'], true)
            || (isset($data['currency']) && $data['currency']!=='BRL')
            || ($method['id'] ?? '') !== 'pix' || ($method['type'] ?? '') !== 'bank_transfer'
            || !preg_match('/^[a-f0-9]{32}$/D', $data['external_reference'] ?? '')) throw new PagamentoException();
        [$inteiro,$fracao] = explode('.', $amount);
        $centavos = (int)$inteiro * 100 + (int)$fracao;
        self::decimal($centavos);
        $status = $data['status'] ?? ''; $ps = $p['status'] ?? '';
        $detail = $data['status_detail'] ?? null; $pd = $p['status_detail'] ?? null;
        foreach ([$detail,$pd] as $d) if ($d !== null && (!is_string($d)||!preg_match('/^[a-z_]{1,80}$/D',$d))) throw new PagamentoException();
        $url = $method['ticket_url'] ?? null;
        if ($url !== null && $url !== '') {
            $parts = parse_url($url);
            if (!filter_var($url,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'
                || !in_array($parts['host']??'', ['www.mercadopago.com.br','mercadopago.com.br'],true)
                || isset($parts['user'])||isset($parts['pass'])) throw new PagamentoException();
        }
        $qr = $method['qr_code'] ?? null; $base64 = $method['qr_code_base64'] ?? null;
        if (($qr!==null&&!is_string($qr))||($base64!==null&&!is_string($base64))) throw new PagamentoException();
        $expiration = $p['date_of_expiration'] ?? $p['expiration_time'] ?? $data['expiration_time'] ?? null;
        if ($expiration!==null&&(!is_string($expiration)||!preg_match('/^[0-9PTYMDHSZ:.+\-]{1,80}$/D',$expiration))) throw new PagamentoException();
        return ['recebedor_id'=>$receiver,'provedor_criado_em'=>$created,'provedor_atualizado_em'=>$updated,
            'id'=>$data['id'], 'pagamento_id'=>$p['id'], 'status'=>$status,'status_detail'=>$detail,
            'status_interno'=>self::statusInterno($status,$detail), 'pagamento_status'=>$ps,
            'pagamento_status_detail'=>$pd,'pagamento_status_interno'=>self::statusInterno($ps,$pd),
            'valor_centavos'=>$centavos,'valor'=>$amount,'moeda'=>'BRL',
            'external_reference'=>$data['external_reference'], 'ticket_url'=>$url,
            'qr_code'=>$qr,'qr_code_base64'=>$base64,'expiracao'=>$expiration,
            'live_mode'=>is_bool($data['live_mode']??null)?$data['live_mode']:null,'ambiente'=>'testing'];
    }
}
