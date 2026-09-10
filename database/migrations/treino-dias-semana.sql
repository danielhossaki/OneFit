-- Para bases existentes: executar uma vez, antes de atualizar os arquivos PHP.
-- Registros antigos ficam sem dia ate serem editados pelo aluno.
ALTER TABLE treino_exercicio ADD COLUMN dia_semana VARCHAR(20) NULL DEFAULT NULL AFTER nome;
