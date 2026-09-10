-- OneFit | 20260908_002 | Persistencia Orders/Pix | SOMENTE PARA REVISAO
-- NAO EXECUTADA. Complementa 20260904_001; MySQL 5.7 / InnoDB.
-- Esquema confirmado por SHOW CREATE TABLE em 2026-09-08.
-- Apenas colunas e indices adicionais. Sem backfill ou alteracao do legado.
-- Antes de executar: backup validado, confirmar esquema e ausencia de todos
-- os nomes adicionados abaixo. Executar uma vez, com janela de manutencao.
-- DDL tem commits implicitos: ROLLBACK nao desfaz esta migracao.
-- Em falha parcial, parar e inspecionar; nao repetir o arquivo inteiro.
-- NULL nas novas colunas preserva a compatibilidade com Checkout Pro.

-- Slot de deduplicacao, calculado exclusivamente pelo servidor:
-- SHA-256 de uma representacao canonica/versionada de origem, usuario e plano.
-- Nao e a chave_negocio: esta permanece imutavel e identifica cada obrigacao.
-- A chave ativa so pode ser liberada numa transacao de encerramento controlado.
-- Timeout de HTTP ou expiracao de um bloqueio NAO libera este slot.
-- Liquidacao libera o slot somente junto da ativacao; o servico deve bloquear
-- o usuario e verificar matricula ativa antes de permitir outra contratacao.
-- UNIQUE permite varios NULL, preservando todas as tentativas encerradas.
ALTER TABLE cobrancas
    ADD COLUMN chave_tentativa_ativa CHAR(64)
        CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    ADD UNIQUE KEY uq_cobranca_tentativa_ativa (origem, chave_tentativa_ativa);

-- Order, pagamento e preferencia sao recursos diferentes:
-- order_id identifica ORD...; id_externo continua identificando o pagamento;
-- preferencia_id continua exclusivo do Checkout Pro, sem reaproveitamento.
-- Uma tentativa Orders/Pix representa uma Order com um pagamento neste MVP.
-- recebedor_esperado_id e um identificador tecnico, nunca token/CPF/email.
-- Deve ser fixado antes do envio a partir da configuracao confiavel da conta,
-- nunca aprendido da propria resposta que esta sendo validada ou do navegador.
-- Status da Order ficam separados dos status do pagamento ja existentes.
-- Campos de envio sao operacionais; nao aprovam nem recusam financeiramente.
ALTER TABLE pagamentos
    ADD COLUMN integracao ENUM('orders_pix') NULL DEFAULT NULL,
    ADD COLUMN idempotencia_uuid CHAR(36)
        CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    ADD COLUMN order_id VARCHAR(100)
        CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    ADD COLUMN recebedor_esperado_id VARCHAR(100)
        CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    ADD COLUMN order_status VARCHAR(60) NULL DEFAULT NULL,
    ADD COLUMN order_status_detalhe VARCHAR(120) NULL DEFAULT NULL,
    ADD COLUMN envio_estado ENUM('preparado', 'enviando', 'incerto', 'confirmado', 'erro')
        NULL DEFAULT NULL,
    ADD COLUMN envio_tentativas INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN envio_iniciado_em DATETIME(6) NULL DEFAULT NULL,
    ADD COLUMN envio_bloqueio_ate DATETIME(6) NULL DEFAULT NULL,
    ADD COLUMN envio_versao BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN envio_codigo_erro VARCHAR(80) NULL DEFAULT NULL,
    ADD COLUMN envio_http SMALLINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN envio_request_id VARCHAR(100)
        CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    ADD UNIQUE KEY uq_pagamento_uuid (idempotencia_uuid),
    ADD UNIQUE KEY uq_pagamento_order (provedor, ambiente, order_id),
    ADD KEY ix_pagamento_envio (envio_estado, envio_bloqueio_ate);

-- CONTRATO OBRIGATORIO DA IMPLEMENTACAO FUTURA (nao implementado aqui):
-- 1. MySQL 5.7 nao aplica CHECK. Validar no backend todos os campos Orders
--    obrigatorios, UUID v4 canonico, valores positivos, moeda BRL e testing.
--    Gerar UUID uma vez; idempotencia_uuid recebe sua representacao canonica.
--    chave_idempotencia recebe O MESMO UUID sem hifens (32 caracteres), mantendo
--    a coluna antiga obrigatoria e sua unicidade. Nunca gerar duas chaves.
--    A representacao compacta seria possivel sem nova coluna; a coluna explicita
--    evita misturar o contrato UUID do Orders com o hexadecimal do Checkout Pro.
-- 2. Transacao curta: bloquear usuario autenticado, consultar plano ativo/preco
--    DECIMAL, validar limites, verificar matricula ativa e slot existente.
--    Reutilizar tentativa ativa ou criar matricula pendente com datas NULL e
--    duracao positiva obtida do plano. Criar cobranca aberta (pendente) e
--    pagamento criado, com referencias aleatorias e recebedor esperado fixado.
--    Reutilizar matricula pendente somente apos validar propriedade/plano,
--    ausencia de liquidacao e snapshot; nao adotar matriculas legadas cegamente.
--    DECIMAL(10,2) da matricula limita o valor contratado; rejeitar excesso,
--    nunca truncar ou calcular com float. A camada de dominio utiliza centavos.
-- 3. Reclamar envio em transacao curta, incrementar envio_versao e tentativas,
--    registrar prazo do bloqueio e COMMIT antes de qualquer chamada HTTP.
--    Cliques concorrentes consultam/reutilizam; nao disparam outra Order.
--    Aplicar retorno somente se versao/estado ainda correspondem ao envio.
-- 4. Timeout/resposta perdida significa incerto, nunca recusado/cancelado.
--    Bloqueio vencido exige conciliacao, nao uma nova UUID. Sem order_id,
--    recuperacao deve manter chave e dados identicos e respeitar a garantia e
--    janela de idempotencia do provedor; se nao comprovavel, revisao manual.
--    Falha anterior ao envio pode permitir retry controlado da mesma tentativa.
--    Guardar somente codigo interno permitido, HTTP e request ID sanitizado.
-- 5. Conciliacao por interface de gateway: exigir recebedor, valor, moeda,
--    referencia, Order e pagamento coerentes com a tentativa persistida.
--    Ausencia de evidencia confiavel bloqueia ativacao. Validar data de aprovacao
--    do provedor; nao substituir pela data da consulta. UTC nas datas financeiras.
--    Nao usar a fixture sintetica APRO para ativar matriculas de usuarios reais.
-- 6. Na mesma transacao: bloquear cobranca/matricula/pagamento, deduplicar evento
--    pela chave unica existente em pagamento_eventos e aplicar apenas a primeira
--    liquidacao valida. Inicio na data de aprovacao no fuso do negocio; fim
--    calculado pela duracao contratada. Falhas fazem rollback dos efeitos.
--    Evento com erro e reprocessado na mesma linha. Aprovacao tardia de tentativa
--    antiga exige conciliacao da obrigacao, sem ativar novamente a matricula.
-- 7. Novas tentativas terminais preservam linhas e chaves anteriores. Nunca
--    reabrir uma cobranca liquidada. Toda consulta/alteracao exige propriedade.
--    QR, copia e cola, URL, payload completo e credenciais nao sao armazenados.
--    Campos de apresentacao poderao ser consultados futuramente sob autorizacao.
-- 8. Nenhuma alteracao em matricula, pagamento_eventos, pedido ou pagamento
--    singular e necessaria nesta proposta. As FKs existentes ficam intactas.
--    Sem notificacoes, formularios, webhooks, marketplace ou chamadas externas.
