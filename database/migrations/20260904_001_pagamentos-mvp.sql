-- OneFit | 20260904_001 | MVP central de pagamentos | MySQL 5.7 / InnoDB
-- PRIMEIRO FLUXO: matricula, Pix, Checkout Pro, testing, uma conta recebedora.
-- Arquivo para revisao e execucao futura controlada. Nao importa dados legados.
-- Precondicoes: tabelas existentes conforme esquema inspecionado; IDs INT signed.
-- Confirmar ausencia das novas tabelas/colunas/indices antes da execucao unica.
-- Nao usar IF NOT EXISTS para ocultar divergencias ou execucao parcial.
-- DDL causa commits implicitos no MySQL 5.7: START TRANSACTION nao torna esta
-- migracao atomica. Em falha parcial, inspecionar o esquema antes de retomar.
-- Datas antigas (inclusive 0000-00-00), status e valores nao sao corrigidos.
-- Se o sql_mode futuro rejeitar datas antigas ao alterar a tabela, interromper
-- e planejar a conciliacao; nao desativar validacoes para forcar a migracao.

ALTER TABLE matricula
    MODIFY COLUMN data_inicio DATE NULL DEFAULT NULL,
    MODIFY COLUMN data_fim DATE NULL DEFAULT NULL,
    ADD COLUMN duracao_contratada_dias INT NULL DEFAULT NULL,
    ADD UNIQUE KEY uq_matricula_usuario (id_matricula, id_usuario);

-- Apenas indice de suporte a FK futura; nenhum dado ou fluxo de pedido muda.
ALTER TABLE pedido
    ADD UNIQUE KEY uq_pedido_usuario (id_pedido, id_usuario);

-- MySQL 5.7 ignora CHECK. O servico devera validar, em toda criacao/alteracao:
-- * matricula => id_matricula preenchido e id_pedido NULL; marketplace => inverso;
-- * valores nao negativos; valor_cobrar = valor_bruto - desconto - cashback;
-- * valor_cobrar > 0 no Pix do MVP; moeda BRL; duracao contratada positiva;
-- * chave_negocio estavel por obrigacao/competencia, reutilizada em reenvios.
-- As FKs compostas garantem que o registro relacionado pertence ao usuario.
CREATE TABLE cobrancas (
    id_cobranca BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    origem ENUM('matricula', 'marketplace') NOT NULL,
    id_matricula INT NULL DEFAULT NULL,
    id_pedido INT NULL DEFAULT NULL,
    chave_negocio VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    valor_bruto DECIMAL(12,2) NOT NULL,
    desconto_aplicado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cashback_aplicado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_cobrar DECIMAL(12,2) NOT NULL,
    moeda ENUM('BRL') NOT NULL DEFAULT 'BRL',
    status ENUM('aberta', 'liquidada', 'cancelada', 'expirada')
        NOT NULL DEFAULT 'aberta',
    criada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    atualizada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    expira_em DATETIME(6) NULL DEFAULT NULL,
    encerrada_em DATETIME(6) NULL DEFAULT NULL,
    PRIMARY KEY (id_cobranca),
    UNIQUE KEY uq_cobranca_negocio (origem, chave_negocio),
    KEY ix_cobranca_usuario_data (id_usuario, criada_em),
    KEY ix_cobranca_matricula_usuario (id_matricula, id_usuario),
    KEY ix_cobranca_pedido_usuario (id_pedido, id_usuario),
    KEY ix_cobranca_expiracao (status, expira_em),
    CONSTRAINT fk_cobranca_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_cobranca_matricula_usuario
        FOREIGN KEY (id_matricula, id_usuario)
        REFERENCES matricula (id_matricula, id_usuario)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_cobranca_pedido_usuario FOREIGN KEY (id_pedido, id_usuario)
        REFERENCES pedido (id_pedido, id_usuario)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referencia e idempotencia: gerar no servidor com bin2hex(random_bytes(16)),
-- sem dados pessoais. Reutilizar a chave de idempotencia na mesma operacao.
-- Nunca gerar novas tentativas cegamente apos timeout: primeiro conciliar.
-- Uma preferencia pode ter varios pagamentos externos; nao e UNIQUE.
-- IDs externos NULL permitem registrar a intencao antes da chamada ao provedor.
-- Uma conta recebedora apenas: ampliar o escopo da unicidade antes de multi-conta.
CREATE TABLE pagamentos (
    id_pagamento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cobranca BIGINT UNSIGNED NOT NULL,
    provedor ENUM('mercado_pago') NOT NULL DEFAULT 'mercado_pago',
    ambiente ENUM('testing') NOT NULL DEFAULT 'testing',
    id_externo VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    preferencia_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    referencia_externa CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    chave_idempotencia CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    moeda ENUM('BRL') NOT NULL DEFAULT 'BRL',
    meio_pagamento VARCHAR(40) NULL DEFAULT NULL,
    status_interno ENUM(
        'criado', 'pendente', 'aprovado', 'recusado',
        'cancelado', 'expirado', 'estornado'
    ) NOT NULL DEFAULT 'criado',
    status_provedor VARCHAR(60) NULL DEFAULT NULL,
    status_detalhe VARCHAR(120) NULL DEFAULT NULL,
    valor_estornado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    aprovado_em DATETIME(6) NULL DEFAULT NULL,
    expira_em DATETIME(6) NULL DEFAULT NULL,
    ultima_consulta_em DATETIME(6) NULL DEFAULT NULL,
    PRIMARY KEY (id_pagamento),
    UNIQUE KEY uq_pagamento_referencia (referencia_externa),
    UNIQUE KEY uq_pagamento_idempotencia (chave_idempotencia),
    UNIQUE KEY uq_pagamento_externo (provedor, ambiente, id_externo),
    KEY ix_pagamento_cobranca (id_cobranca),
    KEY ix_pagamento_preferencia (preferencia_id),
    KEY ix_pagamento_expiracao (status_interno, expira_em),
    KEY ix_pagamento_conciliacao (status_interno, ultima_consulta_em),
    CONSTRAINT fk_pagamento_cobranca FOREIGN KEY (id_cobranca)
        REFERENCES cobrancas (id_cobranca) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotencia de negocio no MVP, sem tabela extra de liquidacoes:
-- bloquear cobranca e matricula com SELECT ... FOR UPDATE na mesma transacao;
-- validar API, valor, moeda, referencia e conta antes de liquidar;
-- somente a primeira transicao valida aplica ativacao e notificacao info.
-- Novos pagamentos aprovados da mesma cobranca sao auditados como excecao,
-- sem repetir efeitos. Nao reabrir cobranca liquidada apos estorno.
-- Status financeiro do pagamento permanece separado do status da cobranca.
-- Datas novas em UTC; vigencia comeca na data de aprovacao no fuso do negocio.
-- Restringir o checkout a Pix no servico; ENUM testing nao substitui validar
-- credenciais, conta e ambiente retornado pela API. Nunca aprovar por redirect.

-- Somente metadados sanitizados: nao armazenar payload completo, tokens,
-- headers de autorizacao, dados de cartao ou dados pessoais em nenhum campo.
-- Chave SHA-256 hexadecimal com escopo de fonte/ambiente/evento, calculada
-- pelo backend. Nao deduplicar somente por payment_id: ha varios eventos.
-- Na conciliacao, identificar a operacao; retries reutilizam sua chave.
-- Persistir eventos webhook autenticados antes de confirmar recebimento.
-- Pagamento nullable permite evento recebido antes do vinculo local.
-- Falha deve atualizar o mesmo evento e agendar proxima tentativa.
-- O worker usa bloqueio transacional e recupera processamento abandonado.
CREATE TABLE pagamento_eventos (
    id_evento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pagamento BIGINT UNSIGNED NULL DEFAULT NULL,
    chave_evento CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    fonte ENUM('webhook', 'conciliacao') NOT NULL,
    tipo VARCHAR(60) NOT NULL,
    recurso_externo VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status_anterior VARCHAR(30) NULL DEFAULT NULL,
    status_novo VARCHAR(30) NULL DEFAULT NULL,
    processamento ENUM('recebido', 'processando', 'processado', 'erro', 'ignorado')
        NOT NULL DEFAULT 'recebido',
    tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    codigo_erro VARCHAR(80) NULL DEFAULT NULL,
    recebido_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    ultima_tentativa_em DATETIME(6) NULL DEFAULT NULL,
    proxima_tentativa_em DATETIME(6) NULL DEFAULT NULL,
    processado_em DATETIME(6) NULL DEFAULT NULL,
    PRIMARY KEY (id_evento),
    UNIQUE KEY uq_evento_chave (chave_evento),
    KEY ix_evento_pagamento_data (id_pagamento, recebido_em),
    KEY ix_evento_fila (processamento, proxima_tentativa_em),
    KEY ix_evento_recuperacao (processamento, ultima_tentativa_em),
    CONSTRAINT fk_evento_pagamento FOREIGN KEY (id_pagamento)
        REFERENCES pagamentos (id_pagamento) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nenhum INSERT/UPDATE/DELETE de dados, backfill, ativacao ou importacao.
-- A tabela singular pagamento e seus relacionamentos permanecem intactos.
-- Marketplace: apenas vinculo preparado; reservas, cashback e checkout futuro
-- dependem de outra etapa e nao devem ser habilitados por esta migracao.
