-- MySQL 5.7. Run only through database/migrate-interface.php (backup + lock + journal).
ALTER TABLE preferencias_usuario ADD COLUMN idioma VARCHAR(5) NOT NULL DEFAULT 'pt-BR';

CREATE TABLE IF NOT EXISTS configuracoes_site (
    chave VARCHAR(64) NOT NULL PRIMARY KEY,
    valor VARCHAR(255) NOT NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificacoes_eventos (
    evento VARCHAR(96) NOT NULL,
    usuario_id INT NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evento, usuario_id),
    CONSTRAINT fk_notificacao_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
