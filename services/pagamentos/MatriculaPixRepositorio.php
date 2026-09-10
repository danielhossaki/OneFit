<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PixRepositorio.php';

final class MatriculaPixRepositorio extends PixRepositorio
{
    public function plano(int $id): array
    {
        $p = $this->um("SELECT id_plano,valor,duracao_dias FROM cadastro_planos WHERE id_plano=? AND status='ativo' LOCK IN SHARE MODE", [$id]);
        if (!$p || (int)$p['duracao_dias'] <= 0 || (int)$p['duracao_dias'] > 36500) throw new PagamentoException();
        return $p;
    }
    public function criar(int $usuario, array $plano): int
    {
        if ($this->um("SELECT id_matricula FROM matricula WHERE id_usuario=? AND id_plano=? AND status='ativa' LIMIT 1", [$usuario,$plano['id_plano']])) throw new PagamentoException();
        $this->sql("INSERT INTO matricula (id_usuario,id_plano,data_matricula,data_inicio,data_fim,status,valor_contratado,duracao_contratada_dias) VALUES (?,?,UTC_DATE(),NULL,NULL,'pendente',?,?)", [$usuario,$plano['id_plano'],$plano['valor'],$plano['duracao_dias']]);
        return (int)$this->db->insert_id;
    }
}
