-- Preferências individuais do painel ONE FIT.
-- Seguro para reaplicação: não altera nem remove estruturas existentes.
CREATE TABLE IF NOT EXISTS preferencias_usuario (
    id_usuario INT NOT NULL,
    tema ENUM('light', 'dark', 'system') NOT NULL DEFAULT 'dark',
    lembretes_treino TINYINT(1) NOT NULL DEFAULT 1,
    avisos_agendamentos TINYINT(1) NOT NULL DEFAULT 1,
    atualizacoes_compras TINYINT(1) NOT NULL DEFAULT 1,
    ofertas_novidades TINYINT(1) NOT NULL DEFAULT 1,
    notificacoes_email TINYINT(1) NOT NULL DEFAULT 1,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    CONSTRAINT fk_preferencias_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
