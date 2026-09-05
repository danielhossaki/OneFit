-- Confirmação de recebimento pelo comprador: registra que o próprio cliente
-- confirmou ter recebido o item (distinto de um admin/vendedor apenas marcar
-- o status logístico como "entregue" manualmente).
ALTER TABLE pedido_item
  ADD COLUMN confirmado_recebimento TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN confirmado_recebimento_em DATETIME NULL DEFAULT NULL;
