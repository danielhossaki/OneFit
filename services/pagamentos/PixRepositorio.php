<?php

declare(strict_types=1);

namespace OneFit\Pagamentos;

require_once __DIR__ . '/PagamentoException.php';

class PixRepositorio
{
    public function __construct(public readonly \mysqli $db) {}
    public function sql(string $sql, array $args = []): \mysqli_stmt
    {
        $s = $this->db->prepare($sql);
        if ($args) $s->bind_param(str_repeat('s', count($args)), ...$args);
        $s->execute();
        return $s;
    }
    public function um(string $sql, array $args = []): ?array
    {
        return $this->sql($sql, $args)->get_result()->fetch_assoc();
    }
    public function transacao(\Closure $fn): mixed
    {
        $started = false;
        try {
            $this->db->begin_transaction();
            $started = true;
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (\Throwable) {
            if ($started) {
                try {
                    $this->db->rollback();
                } catch (\Throwable) {
                }
            }
            throw new PagamentoException();
        }
    }
    public function usuario(int $id): void
    {
        if ($id <= 0 || !$this->um("SELECT id_usuario FROM usuarios WHERE id_usuario=? AND status IN ('ativo','pendente_pagamento') FOR UPDATE", [$id])) throw new PagamentoException();
    }
}
