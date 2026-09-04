-- Migração não destrutiva: marketplace multi-vendedor, endereço de entrega e frete por CEP.
-- Produtos e itens de pedido existentes ficam com id_vendedor NULL (tratados na UI como "ONE FIT").

CREATE TABLE enderecos_entrega (
  id_endereco INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  apelido VARCHAR(60) NULL DEFAULT NULL,
  cep VARCHAR(9) NOT NULL,
  logradouro VARCHAR(150) NOT NULL,
  numero VARCHAR(20) NOT NULL,
  complemento VARCHAR(100) NULL DEFAULT NULL,
  bairro VARCHAR(100) NOT NULL,
  cidade VARCHAR(100) NOT NULL,
  uf CHAR(2) NOT NULL,
  principal TINYINT(1) NOT NULL DEFAULT 0,
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_endereco),
  KEY idx_enderecos_entrega_usuario (id_usuario),
  CONSTRAINT fk_enderecos_entrega_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transportadoras (
  id_transportadora INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  tipo ENUM('transportadora','correios','sedex','motoboy','outros') NOT NULL DEFAULT 'transportadora',
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_transportadora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faixas_cep_frete (
  id_faixa INT NOT NULL AUTO_INCREMENT,
  id_transportadora INT NOT NULL,
  cep_inicial CHAR(8) NOT NULL,
  cep_final CHAR(8) NOT NULL,
  valor_frete DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  prazo_dias SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_faixa),
  KEY idx_faixas_cep_frete_transportadora (id_transportadora),
  KEY idx_faixas_cep_frete_intervalo (cep_inicial, cep_final),
  CONSTRAINT fk_faixas_cep_frete_transportadora
    FOREIGN KEY (id_transportadora) REFERENCES transportadoras(id_transportadora) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE produtos
  ADD COLUMN id_vendedor INT NULL DEFAULT NULL AFTER id_produto,
  ADD KEY idx_produtos_vendedor (id_vendedor),
  ADD CONSTRAINT fk_produtos_vendedor
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL;

ALTER TABLE pedido
  ADD COLUMN id_endereco_entrega INT NULL DEFAULT NULL AFTER id_usuario,
  ADD COLUMN endereco_cep VARCHAR(9) NULL DEFAULT NULL,
  ADD COLUMN endereco_logradouro VARCHAR(150) NULL DEFAULT NULL,
  ADD COLUMN endereco_numero VARCHAR(20) NULL DEFAULT NULL,
  ADD COLUMN endereco_complemento VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN endereco_bairro VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN endereco_cidade VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN endereco_uf CHAR(2) NULL DEFAULT NULL,
  ADD KEY idx_pedido_endereco_entrega (id_endereco_entrega),
  ADD CONSTRAINT fk_pedido_endereco_entrega
    FOREIGN KEY (id_endereco_entrega) REFERENCES enderecos_entrega(id_endereco) ON DELETE SET NULL;

ALTER TABLE pedido_item
  ADD COLUMN id_vendedor INT NULL DEFAULT NULL AFTER id_produto,
  ADD COLUMN id_transportadora INT NULL DEFAULT NULL,
  ADD COLUMN valor_frete DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN status_logistica ENUM('aguardando','preparando','despachado','entregue','devolvido','extraviado') NOT NULL DEFAULT 'aguardando',
  ADD COLUMN codigo_rastreio VARCHAR(60) NULL DEFAULT NULL,
  ADD KEY idx_pedido_item_vendedor (id_vendedor),
  ADD KEY idx_pedido_item_transportadora (id_transportadora),
  ADD CONSTRAINT fk_pedido_item_vendedor
    FOREIGN KEY (id_vendedor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
  ADD CONSTRAINT fk_pedido_item_transportadora
    FOREIGN KEY (id_transportadora) REFERENCES transportadoras(id_transportadora) ON DELETE SET NULL;
