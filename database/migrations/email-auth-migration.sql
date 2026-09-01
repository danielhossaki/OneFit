-- Migração não destrutiva para autenticação por e-mail da OneFit.
-- Usuários existentes são marcados como verificados para não perderem acesso.
ALTER TABLE usuarios
  ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 1 AFTER email;

-- A partir daqui, novos usuários serão não verificados por padrão.
ALTER TABLE usuarios
  ALTER COLUMN email_verificado SET DEFAULT 0;

CREATE TABLE verificacao_email_tokens (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL DEFAULT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verificacao_email_token_hash (token_hash),
  KEY idx_verificacao_email_usuario (usuario_id),
  CONSTRAINT fk_verificacao_email_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE recuperacao_senha_tokens (
  id INT NOT NULL AUTO_INCREMENT,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL DEFAULT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recuperacao_senha_token_hash (token_hash),
  KEY idx_recuperacao_senha_usuario (usuario_id),
  CONSTRAINT fk_recuperacao_senha_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
