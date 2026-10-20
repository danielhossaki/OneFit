-- Etapa 2 · Funcionários (versão 20261020_funcionarios_v1)
--
-- Aplicar somente com database/migrate-funcionarios.php, que antes:
--   1. faz backup verificado da tabela usuarios (fora do repositório e da raiz pública);
--   2. acrescenta ao ENUM usuarios.tipo_usuario os cargos professor,
--      recepcionista e faxineira, ao final, sem remover nem reordenar valores
--      (comando gerado a partir do esquema real do banco de destino);
--   3. confere que nenhuma linha de usuarios mudou.
--
-- Tabela nova de auditoria. Sem chaves estrangeiras de propósito: o histórico
-- precisa sobreviver à exclusão de contas. Nunca guarda senha, hash ou token.
CREATE TABLE IF NOT EXISTS auditoria_eventos (
  id_evento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria VARCHAR(30) NOT NULL,
  acao VARCHAR(40) NOT NULL,
  id_operador INT NULL,
  id_alvo INT NULL,
  detalhes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_evento),
  KEY idx_auditoria_alvo (categoria, id_alvo, criado_em),
  KEY idx_auditoria_operador (id_operador, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
