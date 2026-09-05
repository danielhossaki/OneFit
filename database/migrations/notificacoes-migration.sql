-- Notificações individuais do dashboard. Não remove dados existentes.
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'info',
    link VARCHAR(2048) DEFAULT NULL,
    lida_em DATETIME DEFAULT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_notificacoes_usuario_data (usuario_id, criada_em, id),
    INDEX idx_notificacoes_usuario_leitura (usuario_id, lida_em),
    CONSTRAINT fk_notificacoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
