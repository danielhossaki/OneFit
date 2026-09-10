-- OneFit | Cadastro de matricula pendente de confirmacao e pagamento.
ALTER TABLE usuarios
    MODIFY COLUMN status ENUM('ativo','inativo','bloqueado','pendente_pagamento')
    NULL DEFAULT 'ativo';

CREATE TABLE matricula_cadastros_pendentes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL DEFAULT NULL,
    nome VARCHAR(255) NOT NULL,
    data_nascimento DATE NOT NULL,
    genero VARCHAR(30) NOT NULL,
    cpf VARCHAR(20) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    cidade_estado VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    celular VARCHAR(30) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    id_plano INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_matricula_pendente_token (token_hash),
    UNIQUE KEY uq_matricula_pendente_email (email),
    UNIQUE KEY uq_matricula_pendente_cpf (cpf),
    KEY ix_matricula_pendente_expiracao (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
