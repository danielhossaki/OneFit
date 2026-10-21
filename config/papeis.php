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
 *
 * Hierarquia administrativa: diretor > admin > demais cargos. O diretor é um
 * cargo próprio (usuarios.tipo_usuario = 'diretor', migração 20261020_diretor_v1)
 * que inclui tudo o que o admin pode fazer e, só ele, gere contas admin e
 * diretor. Nenhum cargo inclui o diretor.
 */

require_once __DIR__ . '/interface.php';

/**
 * Política de cada cargo.
 *   login                  pode autenticar no sistema
 *   painel                 chave do painel (BO_PERFIS / data-perfil); null = sem painel
 *   opera_cadastro_facial  pode operar a tela de cadastro facial
 *   catraca                regra de acesso físico (usada pela integração da catraca)
 *   atribuivel_por_admin   um administrador pode atribuir o cargo na tela Funcionários
 *   atribuivel_por_diretor um diretor pode atribuir o cargo na tela Funcionários
 *   inclui                 cargos cujas permissões este cargo também tem (só o diretor inclui o admin)
 */
function onefitPoliticasPapeis(): array
{
    $professor = ['login' => true, 'painel' => 'profissional', 'opera_cadastro_facial' => false, 'catraca' => 'cadastro_profissional', 'atribuivel_por_admin' => true, 'atribuivel_por_diretor' => true];
    return [
        'aluno' => ['login' => true, 'painel' => 'aluno', 'opera_cadastro_facial' => false, 'catraca' => 'matricula', 'atribuivel_por_admin' => false, 'atribuivel_por_diretor' => false],
        // Autoridade superior: o painel e as rotas do admin, mais a gestão de contas admin e diretor.
        'diretor' => ['login' => true, 'painel' => 'admin', 'opera_cadastro_facial' => true, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => false, 'atribuivel_por_diretor' => true, 'inclui' => ['admin']],
        // Operação cotidiana; contas admin e diretor só são criadas ou alteradas por um diretor.
        'admin' => ['login' => true, 'painel' => 'admin', 'opera_cadastro_facial' => true, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => false, 'atribuivel_por_diretor' => true],
        'vendedor' => ['login' => true, 'painel' => 'vendedor', 'opera_cadastro_facial' => false, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => true, 'atribuivel_por_diretor' => true],
        'professor' => $professor,
        // Valor legado gravado no banco: mesma política do professor, mas nunca atribuído a cadastros novos.
        'profissional' => ['atribuivel_por_admin' => false, 'atribuivel_por_diretor' => false] + $professor,
        // Painel da recepção: agenda, consulta, cadastro facial e configurações; gravações específicas têm rotas próprias e checagem no servidor.
        'recepcionista' => ['login' => true, 'painel' => 'recepcionista', 'opera_cadastro_facial' => true, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => true, 'atribuivel_por_diretor' => true],
        'faxineira' => ['login' => false, 'painel' => null, 'opera_cadastro_facial' => false, 'catraca' => 'conta_ativa', 'atribuivel_por_admin' => true, 'atribuivel_por_diretor' => true],
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

/**
 * O cargo está entre os permitidos, diretamente ou por inclusão (o diretor
 * cumpre exigências de 'admin'; o admin nunca cumpre as de 'diretor').
 */
function onefitPapelPermitido(?string $papel, array $permitidos): bool
{
    $canonico = onefitPapelCanonico($papel);
    if ($canonico === null) {
        return false;
    }
    $cumpre = array_merge([$canonico], onefitPolitica($canonico)['inclui'] ?? []);
    foreach ($permitidos as $permitido) {
        if (in_array(onefitPapelCanonico($permitido), $cumpre, true)) {
            return true;
        }
    }
    return false;
}

function onefitPapelDiretor(?string $papel): bool
{
    return onefitPapelCanonico($papel) === 'diretor';
}

/** Cargo administrativo (admin ou diretor): a conta só é gerida por um diretor. */
function onefitPapelAdministrativo(?string $papel): bool
{
    return onefitPapelPermitido($papel, ['admin']);
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
    return onefitPodeGerenciarConta('admin', $papel);
}

/**
 * Cargos que o operador pode atribuir na tela Funcionários, pelo cargo dele no
 * banco: diretor → os do admin, mais admin e diretor; admin → os comuns; demais → nenhum.
 */
function onefitCargosAtribuiveis(?string $papelOperador): array
{
    return match (onefitPapelCanonico($papelOperador)) {
        'diretor' => onefitPapeisCom('atribuivel_por_diretor'),
        'admin' => onefitPapeisCom('atribuivel_por_admin'),
        default => [],
    };
}

/** O operador pode gerenciar uma conta deste cargo (inclui o legado 'profissional' para quem gere professores). */
function onefitPodeGerenciarConta(?string $papelOperador, ?string $papelAlvo): bool
{
    $cargos = onefitCargosAtribuiveis($papelOperador);
    $alvo = onefitPapelCanonico($papelAlvo);
    return $alvo !== null && in_array($alvo, array_map('onefitPapelCanonico', $cargos), true);
}

/** Cargos listados na tela Funcionários: os gerenciáveis, o legado, admin e diretor (estes, só leitura para o admin). */
function onefitPapeisFuncionarios(): array
{
    return array_values(array_unique(array_merge(onefitCargosAtribuiveisPorAdmin(), ['profissional', 'admin', 'diretor'])));
}

/**
 * Conta de diretor ativa, lida do banco (não da sessão). Substitui a antiga
 * lista restrita de administradores (D2): a autoridade superior é o cargo diretor.
 */
function onefitDiretorAtivo(mysqli $db, int $idUsuario, bool $travar = false): bool
{
    $usuario = onefitBuscarSituacaoUsuario($db, $idUsuario, $travar);
    return $usuario !== null && $usuario['status'] === 'ativo' && onefitPapelDiretor($usuario['tipo_usuario']);
}

/** Diretores ativos; com $travar, trava essas linhas até o fim da transação. */
function onefitContarDiretoresAtivos(mysqli $db, bool $travar = false): int
{
    $resultado = $db->query("SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'diretor' AND status = 'ativo'" . ($travar ? ' FOR UPDATE' : ''));
    return $resultado->num_rows;
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
 * Cadastro facial (decisões D5 e D2/P6): quem cadastra ou substitui fotos de quem.
 * Recebe as linhas atuais do banco (tipo_usuario, status) do operador e da
 * pessoa fotografada; nunca o que a tela ou o formulário dizem.
 *   - operador: conta ativa de cargo que opera o cadastro facial (diretor, admin, recepcionista);
 *   - pessoa: conta ativa de cargo conhecido. A foto não libera a catraca, que segue a regra do cargo;
 *   - contas administrativas (admin e diretor, inclusive a do próprio operador): só um diretor
 *     (a autoridade que substituiu a lista restrita de D2). Admin e recepcionista, nunca;
 *   - recepcionista: a si mesma, outras recepcionistas e os demais cargos não administrativos.
 * Retorna ['permitido' => bool, 'motivo' => string].
 */
function onefitAvaliarCadastroFacial(?array $operador, ?array $pessoa): array
{
    if (!$operador || ($operador['status'] ?? null) !== 'ativo' || !onefitPapelOperaCadastroFacial($operador['tipo_usuario'] ?? null)) {
        return ['permitido' => false, 'motivo' => 'operador_sem_permissao'];
    }
    if (!$pessoa) {
        return ['permitido' => false, 'motivo' => 'pessoa_inexistente'];
    }
    if (($pessoa['status'] ?? null) !== 'ativo') {
        return ['permitido' => false, 'motivo' => 'pessoa_inativa'];
    }
    $papelPessoa = onefitPapelCanonico($pessoa['tipo_usuario'] ?? null);
    if ($papelPessoa === null) {
        return ['permitido' => false, 'motivo' => 'cargo_desconhecido'];
    }
    if (onefitPapelAdministrativo($papelPessoa) && !onefitPapelDiretor($operador['tipo_usuario'])) {
        return ['permitido' => false, 'motivo' => 'conta_admin_restrita'];
    }
    return ['permitido' => true, 'motivo' => 'ok'];
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
