CREATE TABLE IF NOT EXISTS treino_exercicio (
    id_exercicio INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    dia_semana VARCHAR(20) NULL DEFAULT NULL,
    nome VARCHAR(100) NOT NULL,
    series TINYINT UNSIGNED NOT NULL,
    repeticoes TINYINT UNSIGNED NOT NULL,
    carga SMALLINT UNSIGNED NOT NULL,
    token_criacao CHAR(32) NOT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_exercicio),
    UNIQUE KEY uq_treino_envio (id_usuario, token_criacao),
    CONSTRAINT fk_treino_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
