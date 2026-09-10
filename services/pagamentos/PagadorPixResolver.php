<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PagadorPix.php';
require_once __DIR__.'/PixRepositorio.php';

/** Internal repository dependency; never pass HTTP data as the source. */
final class PagadorPixResolver
{
    public function __construct(private PixRepositorio $usuarios) {}
    public function resolver(int $usuarioAutenticado, string $ambiente='testing'): PagadorPix
    {
        try {
            if($usuarioAutenticado<=0||!in_array($ambiente,['testing','production'],true))throw new PagamentoException();
            $u=$this->usuarios->um('SELECT id_usuario,email,email_verificado,status FROM usuarios WHERE id_usuario=?',[$usuarioAutenticado]);
            if(!$u||(int)$u['id_usuario']!==$usuarioAutenticado||!in_array($u['status'],['ativo','pendente_pagamento'],true)
                ||(int)$u['email_verificado']!==1||!is_string($u['email'])
                ||strlen($u['email'])>150||!filter_var($u['email'],FILTER_VALIDATE_EMAIL)) throw new PagamentoException();
            // Separate strategies: synthetic in testing; database email in production.
            // Resolving a production DTO does NOT authorize production API calls.
            return $ambiente==='testing'?PagadorPix::testing():PagadorPix::cadastro($u['email']);
        } catch(\Throwable) {throw new PagamentoException();}
    }
}
