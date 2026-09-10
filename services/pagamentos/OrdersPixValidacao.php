<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PagamentoException.php';

final class OrdersPixValidacao
{
    public static function data(mixed $value, \DateTimeImmutable $agora): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D', $value)) throw new PagamentoException();
        // Provider can return nanoseconds; DATETIME(6)/PHP use microseconds.
        $value=preg_replace('/(\.\d{6})\d+/', '$1', $value);
        try { $date=new \DateTimeImmutable($value); } catch (\Throwable) { throw new PagamentoException(); }
        $errors=\DateTimeImmutable::getLastErrors();
        if (($errors!==false && ($errors['warning_count'] || $errors['error_count']))
            || $date < new \DateTimeImmutable('2000-01-01T00:00:00Z') || $date > $agora->modify('+5 minutes')) throw new PagamentoException();
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
    public static function vendedor(array $identity): string
    {
        if (($identity['test_user']??null)!==true || ($identity['pais']??null)!=='BR' || ($identity['site']??null)!=='MLB'
            || !is_string($identity['id']??null) || !preg_match('/^[1-9][0-9]{0,99}$/D',$identity['id'])) throw new PagamentoException();
        return $identity['id'];
    }
}
