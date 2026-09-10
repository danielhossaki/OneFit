-- OneFit Pix integrado / MySQL 5.7 InnoDB. Executar uma vez apos backup.
-- DDL possui commits implicitos. Sem backfill, alteracao ou importacao do legado.
ALTER TABLE pagamentos
 ADD COLUMN pix_qr TEXT NULL,
 ADD COLUMN pix_qr_base64 MEDIUMTEXT NULL,
 ADD COLUMN pix_ticket_url VARCHAR(2048) NULL,
 ADD COLUMN provedor_criado_em DATETIME(6) NULL,
 ADD COLUMN provedor_atualizado_em DATETIME(6) NULL;

ALTER TABLE cobrancas
 ADD COLUMN cashback_ganho DECIMAL(12,2) NOT NULL DEFAULT 0.00;

ALTER TABLE cashback
 ADD COLUMN id_cobranca BIGINT UNSIGNED NULL,
 ADD UNIQUE KEY uq_cashback_cobranca_tipo (id_cobranca, tipo),
 ADD CONSTRAINT fk_cashback_cobranca FOREIGN KEY (id_cobranca)
 REFERENCES cobrancas(id_cobranca) ON DELETE RESTRICT ON UPDATE RESTRICT;

CREATE TABLE pix_reservas (
 id_cobranca BIGINT UNSIGNED NOT NULL,
 id_produto INT NOT NULL,
 quantidade INT UNSIGNED NOT NULL,
 estado ENUM('ativa','consumida','liberada') NOT NULL DEFAULT 'ativa',
 expira_em DATETIME(6) NOT NULL,
 PRIMARY KEY(id_cobranca,id_produto),
 KEY ix_pix_reserva_produto (id_produto,estado),
 KEY ix_pix_reserva_expira (estado,expira_em),
 CONSTRAINT fk_pix_reserva_cobranca FOREIGN KEY(id_cobranca) REFERENCES cobrancas(id_cobranca) ON DELETE RESTRICT,
 CONSTRAINT fk_pix_reserva_produto FOREIGN KEY(id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pix_notificacoes (
 id_cobranca BIGINT UNSIGNED NOT NULL,
 id_usuario INT NOT NULL,
 tipo ENUM('info','compra') NOT NULL,
 entregue_em DATETIME(6) NULL,
 PRIMARY KEY(id_cobranca,id_usuario,tipo),
 CONSTRAINT fk_pix_aviso_cobranca FOREIGN KEY(id_cobranca) REFERENCES cobrancas(id_cobranca) ON DELETE RESTRICT,
 CONSTRAINT fk_pix_aviso_usuario FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Allowlist do teste integrado: somente fixtures explicitamente registradas
-- podem chamar o provedor em testing. Nenhuma conta existente e inserida aqui.
CREATE TABLE pix_testes_tecnicos (
 id_usuario INT NOT NULL,
 id_plano INT NULL,
 id_produto INT NULL,
 PRIMARY KEY(id_usuario),
 CONSTRAINT fk_pix_teste_usuario FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
 CONSTRAINT fk_pix_teste_plano FOREIGN KEY(id_plano) REFERENCES cadastro_planos(id_plano) ON DELETE RESTRICT,
 CONSTRAINT fk_pix_teste_produto FOREIGN KEY(id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
