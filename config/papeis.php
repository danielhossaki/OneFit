<?php
/**
 * config/papeis.php
 * Regras centrais dos cargos (usuarios.tipo_usuario) e revalidação da
 * sessão. Toda decisão de acesso por cargo passa por aqui.
 *
 * Negação por padrão: cargo vazio, NULL ou desconhecido não recebe nenhuma
 * permissão — em especial, nunca é tratado como aluno.
 *
 * Transição profissional → professor: o ENUM do banco ainda grava
 * 'profissional'. Até a migração, os dois valores têm a mesma política e o
 * painel continua sendo o de chave 'profissional' (seções e menus atuais).
 */

require_once __DIR__ . '/interface.php';

/**
 * Política de cada cargo.
 *   login                 pode autenticar no sistema
 *   painel                chave do painel (BO_PERFIS / data-perfil); null = sem painel
 *   opera_cadastro_facial pode operar a tela de cadastro facial
 *   catraca               regra de acesso físico (usada pela integração da catraca)
 *   atribuivel_por_admin  um administrador comum pode atribuir o cargo na tela Funcionários
 */
function onefitPoliticasPapeis(): array
{
    $professor = ['login' => true, 'painel' => 'profissional', 'opera_cadastro_facial' => false, 'catraca' => 'cadastro_profissional', 'atribuivel_por_admin' => true];
    return [
        'aluno' => ['login' => true, 'painel' => 'aluno', 'opera_cadastro_facial' => false, 'catraca' => 'matricula', 'atribuivel_por_admin' => false],
        // Contas admin só são criadas/alteradas por administradores da lista restrita (onefitAdminRestrito).
        'admin' => ['login' => true, 'painel' => 'admin', 'opera_cadastro_facial' => true, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => false],
        'vendedor' => ['login' => true, 'painel' => 'vendedor', 'opera_cadastro_facial' => false, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => true],
        'professor' => $professor,
        // Valor legado gravado no banco: mesma política do professor, mas nunca atribuído a cadastros novos.
        'profissional' => ['atribuivel_por_admin' => false] + $professor,
        // O painel da recepção ainda não existe; sem painel, a conta não entra.
        'recepcionista' => ['login' => true, 'painel' => null, 'opera_cadastro_facial' => true, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => true],
        'faxineira' => ['login' => false, 'painel' => null, 'opera_cadastro_facial' => false, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => true],
    ];
}

function onefitPolitica(?string $papel): ?array
{
    if ($papel === null || $papel === '') {
        return null;
    }
    return onefitPoliticasPapeis()[$papel] ?? null;
}

/** 'profissional' e 'professor' são o mesmo cargo; desconhecido vira null. */
function onefitPapelCanonico(?string $papel): ?string
{
    if (onefitPolitica($papel) === null) {
        return null;
    }
    return $papel === 'profissional' ? 'professor' : $papel;
}

function onefitPapelPermitido(?string $papel, array $permitidos): bool
{
    $canonico = onefitPapelCanonico($papel);
    if ($canonico === null) {
        return false;
    }
    foreach ($permitidos as $permitido) {
        if (onefitPapelCanonico($permitido) === $canonico) {
            return true;
        }
    }
    return false;
}

/** Painel que o cargo usa; null quando o cargo não entra no sistema. */
function onefitPainelDoPapel(?string $papel): ?string
{
    $politica = onefitPolitica($papel);
    return ($politica && $politica['login']) ? $politica['painel'] : null;
}

function onefitPapelOperaCadastroFacial(?string $papel): bool
{
    return (bool) (onefitPolitica($papel)['opera_cadastro_facial'] ?? false);
}

/** Cargos (valores do banco) cuja política tem $regra = true. */
function onefitPapeisCom(string $regra): array
{
    return array_keys(array_filter(onefitPoliticasPapeis(), static fn(array $politica): bool => ($politica[$regra] ?? false) === true));
}

/** Cargos que um administrador comum pode atribuir: professor, recepcionista, vendedor, faxineira. */
function onefitCargosAtribuiveisPorAdmin(): array
{
    return onefitPapeisCom('atribuivel_por_admin');
}

/** Conta de funcionário que um administrador comum pode gerenciar (inclui o legado 'profissional'). */
function onefitFuncionarioGerenciavelPorAdmin(?string $papel): bool
{
    return onefitPapelPermitido($papel, onefitCargosAtribuiveisPorAdmin());
}

/** Cargos listados na tela Funcionários: os gerenciáveis, o legado e admin (somente leitura). */
function onefitPapeisFuncionarios(): array
{
    return array_values(array_unique(array_merge(onefitCargosAtribuiveisPorAdmin(), ['profissional', 'admin'])));
}

/**
 * Administradores da lista restrita (decisão D2): os únicos que poderão criar,
 * promover ou alterar contas admin. A lista ainda não foi definida (pendência
 * P1), então ninguém é restrito e nenhuma tela altera contas admin.
 */
function onefitAdminRestrito(mysqli $db, int $idUsuario): bool
{
    return false;
}

/**
 * Situação prevista na catraca para um funcionário, pela regra do cargo.
 * Alunos dependem da matrícula e não são avaliados aqui.
 * Retorna ['liberado' => bool, 'motivo' => string].
 */
function onefitSituacaoCatracaFuncionario(?string $papel, ?string $status, bool $cadastroProfissionalAtivo): array
{
    $politica = onefitPolitica($papel);
    if ($politica === null) {
        return ['liberado' => false, 'motivo' => 'cargo_desconhecido'];
    }
    if ($status !== 'ativo') {
        return ['liberado' => false, 'motivo' => 'conta_inativa'];
    }
    return match ($politica['catraca']) {
        'conta_ativa' => ['liberado' => true, 'motivo' => 'ok'],
        'cadastro_profissional' => $cadastroProfissionalAtivo
            ? ['liberado' => true, 'motivo' => 'ok']
            : ['liberado' => false, 'motivo' => 'cadastro_profissional_inativo'],
        default => ['liberado' => false, 'motivo' => 'requer_matricula'],
    };
}

/**
 * Decide, sem efeitos colaterais, se uma sessão continua válida a partir da
 * linha atual do usuário no banco.
 * Retorna ['valida' => bool, 'motivo' => string, 'papel' => ?string].
 */
function onefitAvaliarSessao(?array $usuario, ?string $papelSessao): array
{
    if (!$usuario) {
        return ['valida' => false, 'motivo' => 'conta_inexistente', 'papel' => null];
    }
    if (($usuario['status'] ?? null) !== 'ativo') {
        return ['valida' => false, 'motivo' => 'conta_inativa', 'papel' => null];
    }
    $papel = $usuario['tipo_usuario'] ?? null;
    if (onefitPolitica($papel) === null) {
        return ['valida' => false, 'motivo' => 'cargo_desconhecido', 'papel' => null];
    }
    if (onefitPainelDoPapel($papel) === null) {
        return ['valida' => false, 'motivo' => 'sem_acesso_ao_painel', 'papel' => null];
    }
    return ['valida' => true, 'motivo' => $papel === $papelSessao ? 'ok' : 'cargo_alterado', 'papel' => $papel];
}

function onefitBuscarSituacaoUsuario(mysqli $db, int $idUsuario, bool $travar = false): ?array
{
    if ($idUsuario <= 0) {
        return null;
    }
    $stmt = $db->prepare('SELECT id_usuario, tipo_usuario, status FROM usuarios WHERE id_usuario = ? LIMIT 1' . ($travar ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $usuario ?: null;
}

/** Confere no banco (não na sessão) se a conta está ativa e tem um dos cargos. */
function onefitExigirPapel(mysqli $db, int $idUsuario, array $permitidos, bool $travar = false): bool
{
    $usuario = onefitBuscarSituacaoUsuario($db, $idUsuario, $travar);
    return $usuario !== null
        && $usuario['status'] === 'ativo'
        && onefitPapelPermitido($usuario['tipo_usuario'], $permitidos);
}

/** Remove a autenticação da sessão e deixa um aviso para a tela de login. */
function onefitSessaoEncerrar(): void
{
    $idioma = $_SESSION['idioma'] ?? null;
    $_SESSION = [];
    if ($idioma) {
        $_SESSION['idioma'] = $idioma;
    }
    $_SESSION['login_tipo'] = 'erro';
    $_SESSION['login_msg'] = onefitTraduzir('Sua sessão foi encerrada. Entre novamente para continuar.');
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Revalida a sessão a cada requisição (chamada por config/conn.php). Conta
 * apagada, inativa, bloqueada, com cargo desconhecido ou sem painel perde a
 * autenticação; mudança de cargo atualiza a sessão e troca o ID dela.
 * Falha na consulta também encerra a autenticação (negação por padrão).
 */
function onefitSessaoRevalidar(mysqli $db): void
{
    if (empty($_SESSION['id_usuario'])) {
        return;
    }
    try {
        $usuario = onefitBuscarSituacaoUsuario($db, (int) $_SESSION['id_usuario']);
        $avaliacao = onefitAvaliarSessao($usuario, $_SESSION['tipo_usuario'] ?? null);
    } catch (Throwable $erro) {
        error_log('ONE FIT: falha ao revalidar a sessão. Código: ' . $erro->getCode());
        $avaliacao = ['valida' => false, 'motivo' => 'falha_verificacao', 'papel' => null];
    }
    if (!$avaliacao['valida']) {
        onefitSessaoEncerrar();
        return;
    }
    if ($avaliacao['motivo'] === 'cargo_alterado') {
        $_SESSION['tipo_usuario'] = $avaliacao['papel'];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }
}
