-- Tabela que guarda os tokens de recuperação de senha.
-- Assume que já existe uma tabela `usuarios` com coluna `id` (INT) e,
-- para o reset funcionar, uma coluna de senha chamada `senha` (ajuste
-- em resetar_senha.php se o nome real for outro, ex: "password").

CREATE TABLE recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,   -- bin2hex(random_bytes(32)) = 64 caracteres
    expira_em DATETIME NOT NULL,          -- criado_em + 1 hora
    usado TINYINT(1) DEFAULT 0,           -- 0 = ainda válido, 1 = já usado/invalidado
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Índice extra: como toda consulta em processa_esqueci_senha.php e
-- resetar_senha.php filtra por (usuario_id, usado) ou por token, o UNIQUE
-- em `token` já cobre a busca do token. Se a tabela crescer muito, um
-- índice em (usuario_id, usado) acelera o passo de invalidar tokens antigos:
-- CREATE INDEX idx_recuperacao_usuario_usado ON recuperacao_senha (usuario_id, usado);
-- 


--- ESSE ESTÁ CIMPLETO-----------------------------------------------------------------------------------------------------------

-- Criar tabela de usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Criar tabela de recuperação de senha
CREATE TABLE recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expira_em DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Chave estrangeira com CASCADE
    CONSTRAINT fk_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Exemplo de inserção de usuário
INSERT INTO usuarios (nome, email, senha_hash)
VALUES ('João da Silva', 'joao@example.com', SHA2('minhasenha', 256));

-- Exemplo de inserção de token de recuperação
INSERT INTO recuperacao_senha (usuario_id, token, expira_em)
VALUES (1, UUID(), DATE_ADD(NOW(), INTERVAL 1 HOUR));
