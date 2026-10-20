<?php
/**
 * funcionalidades/funcionarios.php
 * Handler da tela "Funcionários" do admin. As regras ficam em
 * includes/funcionarios.php; aqui entram só autenticação (via _shared.php:
 * admin com sessão revalidada no banco e método POST), CSRF e a mensagem
 * de retorno. Erros inesperados não revelam detalhes e não gravam nada.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();
require __DIR__ . '/../includes/funcionarios.php';

$acao = bo_str('acao');
$id = (int) bo_str('id');
$idOperador = (int) $_SESSION['id_usuario'];

// Frases completas: o painel traduz a mensagem inteira pelo catálogo.
$mensagensCadastro = [
    'enviado' => 'Funcionário cadastrado. O link para definir a senha foi enviado por e-mail.',
    'falha_envio' => 'Funcionário cadastrado, mas o e-mail com o link de acesso não pôde ser enviado. Use "Enviar link de acesso" para tentar de novo.',
    'sem_acesso' => 'Funcionário cadastrado. Este cargo não tem acesso ao painel, então nenhum link de acesso foi enviado.',
];
$mensagensReenvio = [
    'enviado' => 'Link de acesso enviado por e-mail.',
    'falha_envio' => 'O e-mail com o link de acesso não pôde ser enviado. Tente novamente mais tarde.',
];

try {
    switch ($acao) {
        case 'create':
            $resultado = bo_funcionario_criar($conn, $idOperador, $_POST);
            bo_flash($resultado['acesso'] === 'falha_envio' ? 'error' : 'success', $mensagensCadastro[$resultado['acesso']]);
            break;
        case 'update':
            $resultado = bo_funcionario_atualizar($conn, $idOperador, $id, $_POST);
            bo_flash('success', $resultado['email_alterado']
                ? 'Funcionário atualizado. Como o e-mail mudou, a pessoa precisará confirmar o novo endereço.'
                : 'Funcionário atualizado.');
            break;
        case 'status':
            $novoStatus = bo_str('status');
            bo_funcionario_alterar_status($conn, $idOperador, $id, $novoStatus);
            bo_flash('success', $novoStatus === 'ativo' ? 'Funcionário ativado.' : 'Funcionário inativado.');
            break;
        case 'vincular-cadastro':
            bo_funcionario_vincular_cadastro($conn, $idOperador, $id, $_POST);
            bo_flash('success', 'Cadastro profissional vinculado.');
            break;
        case 'reenviar-acesso':
            $resultado = bo_funcionario_reenviar_acesso($conn, $idOperador, $id);
            bo_flash($resultado === 'enviado' ? 'success' : 'error', $mensagensReenvio[$resultado]);
            break;
        default:
            bo_flash('error', 'Ação inválida.');
    }
} catch (FuncionarioRecusado $recusa) {
    bo_flash('error', $recusa->getMessage());
} catch (Throwable $erro) {
    error_log('ONE FIT: falha na tela de funcionários. Código: ' . $erro->getCode());
    bo_flash('error', 'Não foi possível concluir a operação. Nada foi alterado.');
}

bo_redirect('funcionarios');
