<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PixRepositorio.php';

class CobrancaPixRepositorio extends PixRepositorio
{
    public function tentativa(int $usuario, int $cobranca): array
    {
        $this->usuario($usuario); // Consistent lock order across start/send/reconcile.
        $c = $this->um("SELECT * FROM cobrancas WHERE id_cobranca=? AND id_usuario=? AND origem IN ('matricula','marketplace') FOR UPDATE", [$cobranca,$usuario]);
        if (!$c) throw new PagamentoException();
        $p = $this->um("SELECT * FROM pagamentos WHERE id_cobranca=? AND integracao='orders_pix' ORDER BY id_pagamento DESC LIMIT 1 FOR UPDATE", [$cobranca]);
        if (!$p) throw new PagamentoException();
        return ['c'=>$c,'p'=>$p];
    }
}
