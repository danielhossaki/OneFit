-- Permite distinguir a forma escolhida no pagamento simulado do dashboard.
-- Não há armazenamento de número do cartão, validade ou CVV.
ALTER TABLE pagamento
    MODIFY forma_pagamento ENUM('pix', 'cartao', 'credito', 'debito') NOT NULL;
