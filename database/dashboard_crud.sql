-- Recursos complementares do dashboard ONE FIT.
-- Execute este arquivo uma única vez no banco "onefit" após importar nw.sql.

CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_categoria),
    UNIQUE KEY uk_categorias_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS funcoes (
    id_funcao INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    permissoes TEXT NULL,
    PRIMARY KEY (id_funcao),
    UNIQUE KEY uk_funcoes_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuario_funcao (
    id_usuario_funcao INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_funcao INT NOT NULL,
    PRIMARY KEY (id_usuario_funcao),
    UNIQUE KEY uk_usuario_funcao (id_usuario, id_funcao),
    CONSTRAINT fk_uf_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_uf_funcao FOREIGN KEY (id_funcao) REFERENCES funcoes(id_funcao) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profissional_aluno (
    id_vinculo INT NOT NULL AUTO_INCREMENT,
    id_profissional INT NOT NULL,
    id_aluno INT NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_vinculo),
    UNIQUE KEY uk_profissional_aluno (id_profissional, id_aluno),
    CONSTRAINT fk_pa_profissional FOREIGN KEY (id_profissional) REFERENCES cadastro_profissional(id_profissional) ON DELETE CASCADE,
    CONSTRAINT fk_pa_aluno FOREIGN KEY (id_aluno) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS treino_exercicio (
    id_exercicio INT NOT NULL AUTO_INCREMENT,
    id_aluno INT NOT NULL,
    id_profissional INT NULL,
    nome VARCHAR(150) NOT NULL,
    series TINYINT UNSIGNED NOT NULL,
    repeticoes SMALLINT UNSIGNED NOT NULL,
    carga DECIMAL(7,2) NOT NULL DEFAULT 0,
    data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_exercicio),
    CONSTRAINT fk_te_aluno FOREIGN KEY (id_aluno) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_te_profissional FOREIGN KEY (id_profissional) REFERENCES cadastro_profissional(id_profissional) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
