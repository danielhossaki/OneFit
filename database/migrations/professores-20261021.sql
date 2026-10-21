-- Professores · disponibilidade para agendamentos (versão 20261021_professores_v1)
--
-- Aplicar somente com database/migrate-professores.php, que antes:
--   1. exige a migração 20261020_funcionarios_v1;
--   2. recusa se alguma conta tiver mais de um cadastro profissional;
--   3. faz backup verificado da tabela cadastro_profissional (fora do
--      repositório e da raiz pública);
-- e depois confere que nenhum dado existente mudou.
--
-- disponivel_agendamento: só novos agendamentos de alunos. Separado de
-- cadastro_profissional.status (que também libera a catraca do professor) e
-- de usuarios.status (a conta). O padrão 1 mantém a regra anterior: todo
-- cadastro existente continua disponível.
-- uk_cadastro_profissional_usuario: no máximo um cadastro por conta (vários
-- NULL continuam permitidos para cadastros antigos ainda sem conta).
ALTER TABLE cadastro_profissional
  ADD COLUMN disponivel_agendamento TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Disponivel para novos agendamentos de alunos (independe da conta e da catraca)' AFTER status,
  ADD UNIQUE KEY uk_cadastro_profissional_usuario (id_usuario);
