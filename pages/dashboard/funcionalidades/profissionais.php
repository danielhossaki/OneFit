<?php
/**
 * funcionalidades/profissionais.php
 * Handler da tela "Professores" do admin (seção e rota "profissionais",
 * mantidas por compatibilidade). As regras ficam em includes/professores.php;
 * aqui entram só autenticação (via _shared.php: admin ou diretor com sessão
 * revalidada no banco e método POST), CSRF e a mensagem de retorno.
 *
 * Professores são cadastrados só na tela Funcionários: esta rota não cria nem
 * exclui cadastros profissionais (a exclusão apagaria o vínculo de agendamentos
 * e alunos, que não têm chave estrangeira).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();
require __DIR__ . '/../includes/professores.php';

$acao = bo_str('acao');
$id = (int) bo_str('id');
$idOperador = (int) $_SESSION['id_usuario'];

try {
    switch ($acao) {
        case 'disponibilidade':
            $disponivel = bo_str('disponivel');
            bo_professor_alterar_disponibilidade($conn, $idOperador, $id, $disponivel);
            bo_flash('success', $disponivel === '1'
                ? 'Professor disponível para novos agendamentos.'
                : 'Professor indisponível para novos agendamentos. Os agendamentos já marcados foram mantidos.');
            break;
        case 'update':
            foreach (['especialidade', 'documento', 'descricao'] as $campoObrigatorio) {
                if (!array_key_exists($campoObrigatorio, $_POST)) {
                    throw new FuncionarioRecusado('O formulário de dados profissionais está incompleto. Nenhuma alteração foi feita.');
                }
            }
            // Só um upload novo troca a foto; o caminho atual nunca vem do formulário.
            bo_professor_atualizar_dados($conn, $idOperador, $id, $_POST, bo_processar_upload_imagem('foto_arquivo', 'profissionais'));
            bo_flash('success', 'Dados profissionais atualizados.');
            break;
        case 'create':
            bo_flash('error', 'Professores são cadastrados na tela Funcionários, com o cargo Professor.');
            break;
        case 'delete':
            bo_flash('error', 'Cadastros profissionais não são excluídos. Para tirar o professor da agenda, marque-o como indisponível.');
            break;
        default:
            bo_flash('error', 'Ação inválida.');
    }
} catch (FuncionarioRecusado $recusa) {
    bo_flash('error', $recusa->getMessage());
} catch (Throwable $erro) {
    error_log('ONE FIT: falha na tela de professores. Código: ' . $erro->getCode());
    bo_flash('error', 'Não foi possível concluir a operação. Nada foi alterado.');
}

bo_redirect('profissionais');
