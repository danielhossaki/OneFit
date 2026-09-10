<?php

declare(strict_types=1);

namespace OneFit\Pagamentos;

require_once __DIR__ . '/MarketplacePixService.php';
require_once __DIR__ . '/OrdersPixGateway.php';

final class MatriculaJaAtivaException extends \RuntimeException {}

/** Request-scoped wrapper: never turns a real catalog price into R$50. */
final class PixGatewayTecnico implements OrdersPixGatewayInterface
{
    private ?array $identity = null;
    public function __construct(private OrdersPixGatewayInterface $gateway) {}
    public function consultarIdentidade(): array
    {
        return $this->identity ??= $this->gateway->consultarIdentidade();
    }
    public function criarOrder(array $p): array
    {
        if ($p['valor_centavos'] !== 5000) throw new PagamentoException();
        $p['pagador'] = PagadorPix::testing(true);
        $p['fixture_oficial'] = true;
        return $this->gateway->criarOrder($p);
    }
    public function consultarOrder(string $id): array
    {
        return $this->gateway->consultarOrder($id);
    }
}
final class PixCheckout
{
    private PixRepositorio $r;
    private OrdersPixGatewayInterface $gateway;
    public function __construct(\mysqli $db, ?OrdersPixGatewayInterface $gateway = null)
    {
        $this->r = new PixRepositorio($db);
        $this->gateway = new PixGatewayTecnico($gateway ?? new OrdersPixGateway());
    }
    public function tecnico(int $user): array
    {
        if (\onefitEnv('MERCADO_PAGO_ENVIRONMENT', 'testing') !== 'testing') throw new PagamentoException();
        $row = $this->r->um('SELECT id_plano,id_produto FROM pix_testes_tecnicos WHERE id_usuario=?', [$user]);
        if (!$row) throw new PagamentoException();
        return $row;
    }
    public function matricula(int $user, int $plan): array
    {
        $this->impedirMatriculaAtiva($user, $plan);
        if (PixTesteLocal::permitido()) return (new MatriculaPixService($this->r->db, $this->gateway, null, true, true))->iniciar($user, $plan);
        if (PHP_SAPI !== 'cli') throw new PagamentoException();
        $tech = $this->tecnico($user);
        if ((int)$tech['id_plano'] !== $plan) throw new PagamentoException();
        $this->exactPrice('cadastro_planos', 'id_plano', 'valor', $plan);
        return (new MatriculaPixService($this->r->db, $this->gateway, null, true))->iniciar($user, $plan);
    }
    private function impedirMatriculaAtiva(int $user, int $plan): void
    {
        if ($this->r->um("SELECT id_matricula FROM matricula WHERE id_usuario=? AND id_plano=? AND status='ativa' LIMIT 1", [$user, $plan])) throw new MatriculaJaAtivaException();
    }
    private function exactPrice(string $t, string $key, string $price, int $id): void
    {
        $p = $this->r->um("SELECT `$price` valor FROM `$t` WHERE `$key`=?", [$id]);
        if (!$p || $p['valor'] !== '50.00') throw new PagamentoException();
    }
    public function pedido(int $user, array $cart, int $address, int $ship, string $cashback): array
    {
        $tech = $this->tecnico($user);
        foreach ($cart as $pid => $qty) if ((int)$pid !== (int)$tech['id_produto']) throw new PagamentoException();
        // Totals remain entirely server-calculated; fixture POST refuses other amounts.
        return (new MarketplacePixService($this->r->db, $this->gateway, null, true))->iniciar($user, $cart, $address, $ship, $cashback);
    }
    public function referencia(int $user, int $charge): string
    {
        $r = $this->r->um('SELECT p.referencia_externa FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_cobranca=? AND c.id_usuario=?', [$charge, $user]);
        if (!$r) throw new PagamentoException();
        return $r['referencia_externa'];
    }
    public function dados(int $user, string $reference, bool $poll = false, bool $somenteConsulta = false): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $reference)) throw new PagamentoException();
        $row = $this->r->um('SELECT c.id_cobranca,c.origem,c.chave_negocio,c.valor_cobrar,m.valor_contratado valor_plano,pl.nome nome_plano,p.* FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca LEFT JOIN matricula m ON m.id_matricula=c.id_matricula LEFT JOIN cadastro_planos pl ON pl.id_plano=m.id_plano WHERE p.referencia_externa=? AND c.id_usuario=?', [$reference, $user]);
        if (!$row) throw new PagamentoException();
        if (PixTesteLocal::marcada($row)) PixTesteLocal::validar($row, $row);
        else $this->tecnico($user);
        if ($poll) {
            $service = new MatriculaPixService($this->r->db, $this->gateway, null, true);
            if ($somenteConsulta) $service->consultarConfiavel($user, (int)$row['id_cobranca']);
            else $service->recuperar($user, (int)$row['id_cobranca']);
            return $this->dados($user, $reference, false);
        }
        $visual = PixSeguranca::visual(['qr_code' => $row['pix_qr'], 'qr_code_base64' => $row['pix_qr_base64'], 'ticket_url' => $row['pix_ticket_url']]);
        return ['teste_local' => PixTesteLocal::marcada($row), 'valor_plano' => $row['valor_plano'], 'nome_plano' => $row['nome_plano'], 'origem' => $row['origem'] === 'marketplace' ? 'pedido' : 'matricula', 'valor' => $row['valor_cobrar'], 'status' => $row['status_interno'], 'vencimento' => $row['expira_em'] ? str_replace(' ', 'T', $row['expira_em']) . 'Z' : null, 'nova_tentativa' => in_array($row['status_interno'], ['recusado', 'cancelado', 'expirado'], true)] + $visual;
    }
}
