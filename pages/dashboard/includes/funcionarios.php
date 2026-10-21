<?php
/**
 * includes/funcionarios.php
 * Regras da tela "Funcionários" do admin: cadastro, edição, situação,
 * vínculo com cadastro_profissional e envio do link de acesso.
 *
 * Todos os funcionários ficam em `usuarios` (tipo_usuario). Cada operação:
 *  - roda numa transação e confere de novo, no banco e com trava, o cargo do
 *    operador (admin ou diretor ativo) e se ele pode gerenciar a conta alvo;
 *  - só aceita cargos atribuíveis pelo cargo do operador (config/papeis.php):
 *    o admin gere os cargos comuns; contas admin e diretor, só um diretor;
 *    nunca o que o formulário diz sobre a conta ou sobre quem opera;
 *  - nunca deixa o sistema sem um diretor ativo;
 *  - registra auditoria em `auditoria_eventos`, sem senha nem token;
 *  - nega por padrão: migração pendente, erro de banco ou dado inválido
 *    interrompem a operação sem gravar nada.
 */

require_once __DIR__ . '/../../../config/papeis.php';
require_once __DIR__ . '/../../../config/localidades.php';
require_once __DIR__ . '/../../../config/email-auth.php';
require_once __DIR__ . '/helpers.php';

/** Operação recusada por regra de negócio; a mensagem pode ser exibida ao operador. */
final class FuncionarioRecusado extends DomainException
{
}

/** Prazo do link de primeira senha: o mesmo da recuperação de senha (decisão P9 pendente). */
const BO_FUNCIONARIO_LINK_SENHA = '+30 minutes';
const BO_FUNCIONARIO_LINK_VERIFICACAO = '+24 hours';

function bo_funcionarios_rotulos_cargo(): array
{
    return [
        'professor' => 'Professor',
        'profissional' => 'Professor',
        'recepcionista' => 'Recepcionista',
        'vendedor' => 'Vendedor',
        'faxineira' => 'Faxineira',
        'admin' => 'Administrador',
        'diretor' => 'Diretor',
    ];
}

function bo_cpf_valido(string $cpf): bool
{
    if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($indice = 0; $indice < $posicao; $indice++) {
            $soma += (int) $cpf[$indice] * (($posicao + 1) - $indice);
        }
        if ((int) $cpf[$posicao] !== ((10 * $soma) % 11) % 10) {
            return false;
        }
    }
    return true;
}

/** Valores de um ENUM a partir de information_schema.COLUMNS.COLUMN_TYPE. */
function bo_enum_valores(string $tipoColuna): array
{
    if (!preg_match('/^enum\((.*)\)$/is', trim($tipoColuna), $corpo)) {
        return [];
    }
    preg_match_all("/'((?:[^']|'')*)'/", $corpo[1], $valores);
    return array_map(static fn(string $v): string => str_replace("''", "'", $v), $valores[1]);
}

/**
 * A tela só grava depois da migração da Etapa 2: os cargos novos precisam
 * existir no ENUM (senão o banco não guarda o cargo) e a auditoria precisa
 * da tabela própria. Retorna ['ok' => bool, 'faltando' => string[]].
 */
function bo_funcionarios_migracao(mysqli $conn): array
{
    $faltando = [];
    $tipo = $conn->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_row()[0] ?? '';
    $valores = bo_enum_valores((string) $tipo);
    foreach (onefitCargosAtribuiveisPorAdmin() as $cargo) {
        if (!in_array($cargo, $valores, true)) {
            $faltando[] = 'usuarios.tipo_usuario: ' . $cargo;
        }
    }
    $auditoria = (int) $conn->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditoria_eventos'"
    )->fetch_row()[0];
    if ($auditoria !== 1) {
        $faltando[] = 'auditoria_eventos';
    }
    return ['ok' => $faltando === [], 'faltando' => $faltando];
}

function bo_funcionarios_exigir_migracao(mysqli $conn): void
{
    if (!bo_funcionarios_migracao($conn)['ok']) {
        throw new FuncionarioRecusado('A migração de funcionários ainda não foi aplicada neste banco. Nenhuma alteração foi feita.');
    }
}

/** O ENUM do banco já aceita o cargo (diretor depende da migração 20261020_diretor_v1). */
function bo_funcionarios_enum_aceita(mysqli $conn, string $cargo): bool
{
    $tipo = $conn->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo_usuario'"
    )->fetch_row()[0] ?? '';
    return in_array($cargo, bo_enum_valores((string) $tipo), true);
}

/** Trava a conta do operador e devolve o cargo dele no banco: admin ou diretor ativo. */
function bo_funcionarios_exigir_operador(mysqli $conn, int $idOperador): string
{
    $operador = onefitBuscarSituacaoUsuario($conn, $idOperador, true);
    if (!$operador || $operador['status'] !== 'ativo' || !onefitPapelAdministrativo($operador['tipo_usuario'])) {
        throw new FuncionarioRecusado('Você não tem permissão para gerenciar funcionários.');
    }
    return onefitPapelCanonico($operador['tipo_usuario']);
}

/** O operador pode atribuir este cargo? Admin e diretor só por um diretor. */
function bo_funcionarios_exigir_cargo(mysqli $conn, string $papelOperador, string $cargo): void
{
    if (!in_array($cargo, onefitCargosAtribuiveis($papelOperador), true)) {
        throw new FuncionarioRecusado('Selecione um cargo permitido para o seu nível: só um Diretor atribui os cargos Administrador e Diretor.');
    }
    if (!bo_funcionarios_enum_aceita($conn, $cargo)) {
        throw new FuncionarioRecusado('A migração do cargo Diretor ainda não foi aplicada neste banco. Nenhuma alteração foi feita.');
    }
}

/** Depois da mudança, o sistema continua com pelo menos um diretor ativo (linhas travadas). */
function bo_funcionarios_exigir_diretor_ativo(mysqli $conn): void
{
    if (onefitContarDiretoresAtivos($conn, true) < 1) {
        throw new FuncionarioRecusado('O sistema precisa de pelo menos um Diretor ativo. Nada foi alterado.');
    }
}

/**
 * Normaliza e valida os dados pessoais e o cargo enviados pelo formulário.
 * $atual: linha da conta em edição (aceita nacionalidade legada já gravada).
 */
function bo_funcionario_dados(array $entrada, ?array $atual = null): array
{
    $texto = static fn(string $campo): string => trim(preg_replace('/\s+/u', ' ', (string) ($entrada[$campo] ?? '')) ?? '');
    $dados = [
        'nome' => $texto('nome'),
        'email' => mb_strtolower($texto('email'), 'UTF-8'),
        'cpf' => preg_replace('/\D/', '', (string) ($entrada['cpf'] ?? '')),
        'celular' => preg_replace('/\D/', '', (string) ($entrada['celular'] ?? '')),
        'nascimento' => $texto('nascimento'),
        'genero' => strtolower($texto('genero')),
        'nacionalidade' => $texto('nacionalidade'),
        'endereco' => $texto('endereco'),
        'cidade' => $texto('cidade'),
        'estado' => strtoupper($texto('estado')),
        'cargo' => $texto('cargo'),
    ];
    foreach ($dados as $valor) {
        if ($valor === '') {
            throw new FuncionarioRecusado('Preencha todos os campos obrigatórios.');
        }
    }
    // Cargo de funcionário conhecido; se o operador pode atribuí-lo é conferido na transação.
    if (!in_array($dados['cargo'], onefitCargosAtribuiveis('diretor'), true)) {
        throw new FuncionarioRecusado('Selecione um cargo permitido: professor, recepcionista, vendedor ou faxineira.');
    }
    if (mb_strlen($dados['nome'], 'UTF-8') < 3 || mb_strlen($dados['nome'], 'UTF-8') > 150) {
        throw new FuncionarioRecusado('Informe o nome completo (até 150 caracteres).');
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || strlen($dados['email']) > 150) {
        throw new FuncionarioRecusado('Informe um e-mail válido.');
    }
    if (!bo_cpf_valido($dados['cpf'])) {
        throw new FuncionarioRecusado('Informe um CPF válido.');
    }
    if (!bo_valida_celular($dados['celular'])) {
        throw new FuncionarioRecusado('Informe um celular/telefone válido, com DDD (10 ou 11 números).');
    }
    $nascimento = DateTime::createFromFormat('!Y-m-d', $dados['nascimento']);
    // 14 anos: idade mínima legal para trabalhar no Brasil (como aprendiz).
    if (!$nascimento || $nascimento->format('Y-m-d') !== $dados['nascimento']
        || $nascimento > (new DateTime('today'))->modify('-14 years')
        || $nascimento < (new DateTime('today'))->modify('-100 years')) {
        throw new FuncionarioRecusado('Informe uma data de nascimento válida (idade entre 14 e 100 anos).');
    }
    if (!in_array($dados['genero'], ['masculino', 'feminino', 'outro'], true)) {
        throw new FuncionarioRecusado('Selecione um gênero válido.');
    }
    if (!onefitNacionalidadeValida($dados['nacionalidade'], (string) ($atual['nacionalidade'] ?? ''))) {
        throw new FuncionarioRecusado('Selecione uma nacionalidade válida.');
    }
    if (!array_key_exists($dados['estado'], onefitEstados())) {
        throw new FuncionarioRecusado('Informe o estado usando uma UF válida.');
    }
    if (mb_strlen($dados['cidade'], 'UTF-8') > 100 || mb_strlen($dados['endereco'], 'UTF-8') > 255) {
        throw new FuncionarioRecusado('Cidade ou endereço muito longos.');
    }
    return $dados;
}

/** Como o professor será ligado a cadastro_profissional (só usado para professor). */
function bo_funcionario_vinculo_entrada(array $entrada): array
{
    $modo = (string) ($entrada['cadastro_modo'] ?? 'novo');
    if (!in_array($modo, ['novo', 'existente'], true)) {
        throw new FuncionarioRecusado('Escolha como vincular o cadastro profissional.');
    }
    $especialidade = trim(preg_replace('/\s+/u', ' ', (string) ($entrada['especialidade'] ?? '')) ?? '');
    if (mb_strlen($especialidade, 'UTF-8') > 100) {
        throw new FuncionarioRecusado('A especialidade pode ter até 100 caracteres.');
    }
    $idProfissional = filter_var($entrada['id_profissional'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($modo === 'existente' && !$idProfissional) {
        throw new FuncionarioRecusado('Selecione o cadastro profissional que será vinculado.');
    }
    return ['modo' => $modo, 'id_profissional' => $idProfissional ?: null, 'especialidade' => $especialidade];
}

/**
 * CPF e e-mail são únicos em usuarios; compara também CPFs antigos gravados
 * com máscara. Sem trava de leitura (varreria e travaria a tabela inteira):
 * a corrida entre dois cadastros é barrada pelas chaves UNIQUE (erro 1062).
 */
function bo_funcionario_exigir_unicos(mysqli $conn, string $email, string $cpf, ?int $ignorarId): void
{
    $ignorar = $ignorarId ?? 0;
    $stmt = $conn->prepare(
        "SELECT id_usuario FROM usuarios
         WHERE (LOWER(email) = ? OR cpf = ? OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?)
           AND id_usuario <> ?
         LIMIT 1"
    );
    $stmt->bind_param('sssi', $email, $cpf, $cpf, $ignorar);
    $stmt->execute();
    $existe = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existe) {
        throw new FuncionarioRecusado('Já existe uma conta com este e-mail ou CPF. Nenhuma conta existente é reaproveitada.');
    }
}

/** Executa uma gravação convertendo violação de chave única (1062) em recusa legível. */
function bo_funcionario_executar_unico(mysqli_stmt $stmt, string $mensagemDuplicado): void
{
    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $erro) {
        if ($erro->getCode() === 1062) {
            throw new FuncionarioRecusado($mensagemDuplicado);
        }
        throw $erro;
    }
}

/** Trava e devolve a conta alvo, que precisa ser gerenciável pelo cargo do operador. */
function bo_funcionario_alvo(mysqli $conn, int $idOperador, string $papelOperador, int $id): array
{
    if ($id <= 0) {
        throw new FuncionarioRecusado('Funcionário não encontrado.');
    }
    $stmt = $conn->prepare(
        'SELECT id_usuario, nome, email, cpf, celular, data_nascimento, genero, nacionalidade, endereco,
                cidade_estado, tipo_usuario, status, email_verificado
         FROM usuarios WHERE id_usuario = ? LIMIT 1 FOR UPDATE'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $alvo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$alvo) {
        throw new FuncionarioRecusado('Funcionário não encontrado.');
    }
    if ($id === $idOperador) {
        throw new FuncionarioRecusado('Você não pode alterar a sua própria conta nesta tela.');
    }
    if (onefitPapelAdministrativo($alvo['tipo_usuario']) && !onefitPapelDiretor($papelOperador)) {
        throw new FuncionarioRecusado('Contas de administrador e de diretor só podem ser alteradas por um Diretor.');
    }
    if (!onefitPodeGerenciarConta($papelOperador, $alvo['tipo_usuario'])) {
        throw new FuncionarioRecusado('Esta conta não é de um funcionário gerenciável nesta tela.');
    }
    return $alvo;
}

/** Auditoria sem senha, hash ou token. CPF entra mascarado. */
function bo_auditoria_registrar(mysqli $conn, string $acao, ?int $idOperador, ?int $idAlvo, array $detalhes, string $categoria = 'funcionario'): void
{
    $json = json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $stmt = $conn->prepare('INSERT INTO auditoria_eventos (categoria, acao, id_operador, id_alvo, detalhes) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssiis', $categoria, $acao, $idOperador, $idAlvo, $json);
    $stmt->execute();
    $stmt->close();
}

function bo_cpf_mascarado(string $cpf): string
{
    $digitos = preg_replace('/\D/', '', $cpf);
    return strlen($digitos) === 11 ? '***.***.***-' . substr($digitos, -2) : '***';
}

/** Invalida links pendentes (definição de senha e verificação) da conta. */
function bo_funcionario_invalidar_links(mysqli $conn, int $id): void
{
    foreach (['recuperacao_senha_tokens', 'verificacao_email_tokens'] as $tabela) {
        $stmt = $conn->prepare("UPDATE {$tabela} SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Cria os tokens de primeiro acesso, dentro da transação, só para cargos que
 * entram no painel. Faxineira (sem login) e cargos sem painel não recebem nada.
 */
function bo_funcionario_tokens_acesso(mysqli $conn, int $id, string $papel, bool $emailVerificado): ?array
{
    if (onefitPainelDoPapel($papel) === null) {
        return null;
    }
    return [
        'senha' => onefitCriarToken($conn, 'recuperacao_senha_tokens', $id, BO_FUNCIONARIO_LINK_SENHA),
        'verificacao' => $emailVerificado ? null : onefitCriarToken($conn, 'verificacao_email_tokens', $id, BO_FUNCIONARIO_LINK_VERIFICACAO),
    ];
}

/** Envio padrão dos links (depois do commit). Os testes injetam outro envio. */
function bo_funcionario_enviar_acesso(string $email, string $nome, array $tokens): bool
{
    require_once __DIR__ . '/../../../config/mailer.php';
    $enviado = onefitEnviarDefinicaoSenhaInicial($email, $nome, $tokens['senha']);
    if ($tokens['verificacao'] !== null) {
        $enviado = onefitEnviarVerificacaoEmail($email, $nome, $tokens['verificacao']) && $enviado;
    }
    return $enviado;
}

/**
 * Liga um professor a cadastro_profissional: vincula um cadastro livre
 * escolhido explicitamente ou cria um novo. Nunca liga por coincidência de
 * e-mail e nunca altera os demais dados de um cadastro existente.
 */
function bo_funcionario_vincular(mysqli $conn, int $idUsuario, array $conta, array $vinculo): int
{
    $stmt = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $jaVinculado = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($jaVinculado) {
        throw new FuncionarioRecusado('Este professor já tem um cadastro profissional vinculado.');
    }

    if ($vinculo['modo'] === 'existente') {
        $idProfissional = (int) $vinculo['id_profissional'];
        $stmt = $conn->prepare('SELECT id_profissional, id_usuario FROM cadastro_profissional WHERE id_profissional = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $idProfissional);
        $stmt->execute();
        $cadastro = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$cadastro) {
            throw new FuncionarioRecusado('Cadastro profissional não encontrado.');
        }
        if ($cadastro['id_usuario'] !== null) {
            throw new FuncionarioRecusado('Este cadastro profissional já está vinculado a outra conta.');
        }
        $stmt = $conn->prepare('UPDATE cadastro_profissional SET id_usuario = ? WHERE id_profissional = ? AND id_usuario IS NULL');
        $stmt->bind_param('ii', $idUsuario, $idProfissional);
        $stmt->execute();
        $alterados = $stmt->affected_rows;
        $stmt->close();
        if ($alterados !== 1) {
            throw new RuntimeException('Cadastro profissional não foi vinculado.');
        }
        return $idProfissional;
    }

    $stmt = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE email = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('s', $conta['email']);
    $stmt->execute();
    $mesmoEmail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($mesmoEmail) {
        throw new FuncionarioRecusado('Já existe um cadastro profissional com este e-mail. Para usá-lo, escolha "Vincular cadastro existente".');
    }
    $especialidade = $vinculo['especialidade'] !== '' ? $vinculo['especialidade'] : null;
    $stmt = $conn->prepare("INSERT INTO cadastro_profissional (id_usuario, nome, email, celular, especialidade, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
    $stmt->bind_param('issss', $idUsuario, $conta['nome'], $conta['email'], $conta['celular'], $especialidade);
    bo_funcionario_executar_unico($stmt, 'Já existe um cadastro profissional com este e-mail. Para usá-lo, escolha "Vincular cadastro existente".');
    $id = (int) $stmt->insert_id;
    $stmt->close();
    return $id;
}

/** Executa $operacao numa transação; qualquer erro desfaz tudo. */
function bo_funcionarios_transacao(mysqli $conn, callable $operacao)
{
    $conn->begin_transaction();
    try {
        $resultado = $operacao();
        $conn->commit();
        return $resultado;
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
}

/**
 * Cadastra um funcionário. Retorna ['id' => int, 'acesso' => 'enviado'|'falha_envio'|'sem_acesso'].
 * $enviar(string $email, string $nome, array $tokens): bool — padrão: e-mail real.
 */
function bo_funcionario_criar(mysqli $conn, int $idOperador, array $entrada, ?callable $enviar = null): array
{
    $dados = bo_funcionario_dados($entrada);
    $vinculo = $dados['cargo'] === 'professor' ? bo_funcionario_vinculo_entrada($entrada) : null;

    [$id, $tokens] = bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $dados, $vinculo): array {
        bo_funcionarios_exigir_migracao($conn);
        $papelOperador = bo_funcionarios_exigir_operador($conn, $idOperador);
        bo_funcionarios_exigir_cargo($conn, $papelOperador, $dados['cargo']);
        bo_funcionario_exigir_unicos($conn, $dados['email'], $dados['cpf'], null);

        // Senha aleatória que ninguém conhece; o funcionário define a dele pelo link.
        $senha = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $cidadeEstado = $dados['cidade'] . '/' . $dados['estado'];
        $stmt = $conn->prepare(
            "INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado,
                                   email, email_verificado, celular, tipo_usuario, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'ativo')"
        );
        $stmt->bind_param('sssssssssss', $dados['nome'], $senha, $dados['nacionalidade'], $dados['nascimento'], $dados['genero'],
            $dados['cpf'], $dados['endereco'], $cidadeEstado, $dados['email'], $dados['celular'], $dados['cargo']);
        bo_funcionario_executar_unico($stmt, 'Já existe uma conta com este e-mail ou CPF. Nenhuma conta existente é reaproveitada.');
        $id = (int) $stmt->insert_id;
        $stmt->close();

        // O ENUM nunca pode gravar outro valor (ex.: vazio) no lugar do cargo.
        $gravado = onefitBuscarSituacaoUsuario($conn, $id, true);
        if (!$gravado || $gravado['tipo_usuario'] !== $dados['cargo'] || $gravado['status'] !== 'ativo') {
            throw new RuntimeException('Cargo gravado difere do solicitado.');
        }

        $detalhes = ['cargo' => $dados['cargo'], 'email' => $dados['email'], 'cpf' => bo_cpf_mascarado($dados['cpf'])];
        if ($vinculo !== null) {
            $detalhes['cadastro_profissional'] = bo_funcionario_vincular($conn, $id, $dados, $vinculo);
            $detalhes['cadastro_modo'] = $vinculo['modo'];
        }
        $tokens = bo_funcionario_tokens_acesso($conn, $id, $dados['cargo'], false);
        $detalhes['link_acesso'] = $tokens !== null;
        bo_auditoria_registrar($conn, 'criar', $idOperador, $id, $detalhes);
        return [$id, $tokens];
    });

    if ($tokens === null) {
        return ['id' => $id, 'acesso' => 'sem_acesso'];
    }
    $enviado = ($enviar ?? 'bo_funcionario_enviar_acesso')($dados['email'], $dados['nome'], $tokens);
    return ['id' => $id, 'acesso' => $enviado ? 'enviado' : 'falha_envio'];
}

/** Edita dados e cargo de um funcionário gerenciável. Retorna ['email_alterado' => bool]. */
function bo_funcionario_atualizar(mysqli $conn, int $idOperador, int $id, array $entrada): array
{
    return bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $id, $entrada): array {
        bo_funcionarios_exigir_migracao($conn);
        $papelOperador = bo_funcionarios_exigir_operador($conn, $idOperador);
        $alvo = bo_funcionario_alvo($conn, $idOperador, $papelOperador, $id);
        $dados = bo_funcionario_dados($entrada, $alvo);
        if (onefitPapelCanonico($alvo['tipo_usuario']) !== $dados['cargo']) {
            bo_funcionarios_exigir_cargo($conn, $papelOperador, $dados['cargo']);
        }
        bo_funcionario_exigir_unicos($conn, $dados['email'], $dados['cpf'], $id);

        // Mantém o valor legado 'profissional' enquanto o cargo continuar sendo professor.
        $cargoGravar = onefitPapelCanonico($alvo['tipo_usuario']) === $dados['cargo'] ? $alvo['tipo_usuario'] : $dados['cargo'];
        $emailAlterado = mb_strtolower((string) $alvo['email'], 'UTF-8') !== $dados['email'];
        $cidadeEstado = $dados['cidade'] . '/' . $dados['estado'];
        $zerarVerificacao = $emailAlterado ? 1 : 0;
        $stmt = $conn->prepare(
            'UPDATE usuarios SET nome = ?, email = ?, cpf = ?, celular = ?, data_nascimento = ?, genero = ?, nacionalidade = ?,
                    endereco = ?, cidade_estado = ?, tipo_usuario = ?, email_verificado = IF(? = 1, 0, email_verificado)
             WHERE id_usuario = ? AND tipo_usuario = ?'
        );
        $stmt->bind_param('ssssssssssiis', $dados['nome'], $dados['email'], $dados['cpf'], $dados['celular'], $dados['nascimento'],
            $dados['genero'], $dados['nacionalidade'], $dados['endereco'], $cidadeEstado, $cargoGravar, $zerarVerificacao, $id, $alvo['tipo_usuario']);
        bo_funcionario_executar_unico($stmt, 'Já existe uma conta com este e-mail ou CPF. Nenhuma conta existente é reaproveitada.');
        $stmt->close();

        $gravado = onefitBuscarSituacaoUsuario($conn, $id, true);
        if (!$gravado || $gravado['tipo_usuario'] !== $cargoGravar) {
            throw new RuntimeException('Cargo gravado difere do solicitado.');
        }
        if (onefitPapelDiretor($alvo['tipo_usuario'])) {
            bo_funcionarios_exigir_diretor_ativo($conn);
        }
        // Link enviado ao e-mail antigo, ou para um cargo que deixou de entrar no painel, não vale mais.
        if ($emailAlterado || onefitPainelDoPapel($cargoGravar) === null) {
            bo_funcionario_invalidar_links($conn, $id);
        }

        $alteracoes = [];
        $antes = [
            'nome' => $alvo['nome'], 'email' => mb_strtolower((string) $alvo['email'], 'UTF-8'), 'cpf' => preg_replace('/\D/', '', (string) $alvo['cpf']),
            'celular' => $alvo['celular'], 'nascimento' => $alvo['data_nascimento'], 'genero' => $alvo['genero'],
            'nacionalidade' => $alvo['nacionalidade'], 'endereco' => $alvo['endereco'], 'cidade_estado' => $alvo['cidade_estado'], 'cargo' => $alvo['tipo_usuario'],
        ];
        $depois = [
            'nome' => $dados['nome'], 'email' => $dados['email'], 'cpf' => $dados['cpf'], 'celular' => $dados['celular'],
            'nascimento' => $dados['nascimento'], 'genero' => $dados['genero'], 'nacionalidade' => $dados['nacionalidade'],
            'endereco' => $dados['endereco'], 'cidade_estado' => $cidadeEstado, 'cargo' => $cargoGravar,
        ];
        foreach ($depois as $campo => $valor) {
            if ((string) $antes[$campo] !== (string) $valor) {
                $alteracoes[$campo] = $campo === 'cpf'
                    ? ['de' => bo_cpf_mascarado((string) $antes[$campo]), 'para' => bo_cpf_mascarado($valor)]
                    : ['de' => $antes[$campo], 'para' => $valor];
            }
        }

        // Quem passa a ser professor precisa de cadastro profissional vinculado.
        if ($dados['cargo'] === 'professor' && onefitPapelCanonico($alvo['tipo_usuario']) !== 'professor') {
            $stmt = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE id_usuario = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $temCadastro = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$temCadastro) {
                $alteracoes['cadastro_profissional'] = bo_funcionario_vincular($conn, $id, $dados, bo_funcionario_vinculo_entrada($entrada));
            }
        }

        bo_auditoria_registrar($conn, 'editar', $idOperador, $id, ['alteracoes' => $alteracoes]);
        return ['email_alterado' => $emailAlterado];
    });
}

/** Ativa ou inativa um funcionário gerenciável (valor explícito, nunca alternância). */
function bo_funcionario_alterar_status(mysqli $conn, int $idOperador, int $id, string $novoStatus): void
{
    if (!in_array($novoStatus, ['ativo', 'inativo'], true)) {
        throw new FuncionarioRecusado('Situação inválida.');
    }
    bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $id, $novoStatus): void {
        bo_funcionarios_exigir_migracao($conn);
        $papelOperador = bo_funcionarios_exigir_operador($conn, $idOperador);
        $alvo = bo_funcionario_alvo($conn, $idOperador, $papelOperador, $id);
        $stmt = $conn->prepare('UPDATE usuarios SET status = ? WHERE id_usuario = ? AND tipo_usuario = ?');
        $stmt->bind_param('sis', $novoStatus, $id, $alvo['tipo_usuario']);
        $stmt->execute();
        $stmt->close();
        if (onefitPapelDiretor($alvo['tipo_usuario'])) {
            bo_funcionarios_exigir_diretor_ativo($conn);
        }
        if ($novoStatus !== 'ativo') {
            bo_funcionario_invalidar_links($conn, $id);
        }
        bo_auditoria_registrar($conn, 'status', $idOperador, $id, ['de' => $alvo['status'], 'para' => $novoStatus]);
    });
}

/** Vincula um professor que ainda não tem cadastro profissional. */
function bo_funcionario_vincular_cadastro(mysqli $conn, int $idOperador, int $id, array $entrada): int
{
    $vinculo = bo_funcionario_vinculo_entrada($entrada);
    return bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $id, $vinculo): int {
        bo_funcionarios_exigir_migracao($conn);
        $papelOperador = bo_funcionarios_exigir_operador($conn, $idOperador);
        $alvo = bo_funcionario_alvo($conn, $idOperador, $papelOperador, $id);
        if (onefitPapelCanonico($alvo['tipo_usuario']) !== 'professor') {
            throw new FuncionarioRecusado('Só professores têm cadastro profissional.');
        }
        $idProfissional = bo_funcionario_vincular($conn, $id, $alvo, $vinculo);
        bo_auditoria_registrar($conn, 'vincular_cadastro', $idOperador, $id, ['cadastro_profissional' => $idProfissional, 'modo' => $vinculo['modo']]);
        return $idProfissional;
    });
}

/** Reenvia o link de primeiro acesso. Retorna 'enviado' ou 'falha_envio'. */
function bo_funcionario_reenviar_acesso(mysqli $conn, int $idOperador, int $id, ?callable $enviar = null): string
{
    [$alvo, $tokens] = bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $id): array {
        bo_funcionarios_exigir_migracao($conn);
        $papelOperador = bo_funcionarios_exigir_operador($conn, $idOperador);
        $alvo = bo_funcionario_alvo($conn, $idOperador, $papelOperador, $id);
        if ($alvo['status'] !== 'ativo') {
            throw new FuncionarioRecusado('Ative a conta antes de enviar o link de acesso.');
        }
        $tokens = bo_funcionario_tokens_acesso($conn, $id, $alvo['tipo_usuario'], (int) $alvo['email_verificado'] === 1);
        if ($tokens === null) {
            throw new FuncionarioRecusado('Este cargo não tem acesso ao painel; nenhum link de acesso é enviado.');
        }
        bo_auditoria_registrar($conn, 'reenviar_acesso', $idOperador, $id, ['verificacao' => $tokens['verificacao'] !== null]);
        return [$alvo, $tokens];
    });
    return ($enviar ?? 'bo_funcionario_enviar_acesso')($alvo['email'], $alvo['nome'], $tokens) ? 'enviado' : 'falha_envio';
}

/**
 * Lista para a tela Funcionários. $papelOperador (cargo da sessão, revalidado no banco) só decide
 * o que a tela mostra como editável; o servidor confere de novo em cada operação.
 */
function bo_funcionarios_listar(mysqli $conn, ?string $papelOperador = 'admin', int $idOperador = 0): array
{
    $papeis = onefitPapeisFuncionarios();
    $marcadores = implode(',', array_fill(0, count($papeis), '?'));
    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.nome, u.email, u.cpf, u.celular, u.data_nascimento, u.genero, u.nacionalidade,
                u.endereco, u.cidade_estado, u.tipo_usuario, u.status, u.email_verificado,
                (SELECT COUNT(*) FROM cadastro_profissional cp WHERE cp.id_usuario = u.id_usuario) AS cadastros,
                (SELECT COUNT(*) FROM cadastro_profissional cp WHERE cp.id_usuario = u.id_usuario AND cp.status = 'ativo') AS cadastros_ativos,
                (SELECT cp.especialidade FROM cadastro_profissional cp WHERE cp.id_usuario = u.id_usuario ORDER BY cp.id_profissional LIMIT 1) AS especialidade
         FROM usuarios u
         WHERE u.tipo_usuario IN ($marcadores)
         ORDER BY u.nome, u.id_usuario"
    );
    $stmt->bind_param(str_repeat('s', count($papeis)), ...$papeis);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $lista = [];
    while ($row = $resultado->fetch_assoc()) {
        $cidadeEstado = explode('/', (string) $row['cidade_estado'], 2);
        $catraca = onefitSituacaoCatracaFuncionario($row['tipo_usuario'], $row['status'], (int) $row['cadastros_ativos'] > 0);
        $lista[] = [
            'id' => (int) $row['id_usuario'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'cpf' => $row['cpf'],
            'celular' => $row['celular'],
            'nascimento' => $row['data_nascimento'],
            'genero' => $row['genero'],
            'nacionalidade' => $row['nacionalidade'],
            'endereco' => $row['endereco'],
            'cidade' => trim($cidadeEstado[0] ?? ''),
            'estado' => trim($cidadeEstado[1] ?? ''),
            'tipo' => $row['tipo_usuario'],
            'cargo' => onefitPapelCanonico($row['tipo_usuario']),
            'status' => $row['status'],
            'emailVerificado' => (int) $row['email_verificado'] === 1,
            'admin' => onefitPapelAdministrativo($row['tipo_usuario']),
            'gerenciavel' => onefitPodeGerenciarConta($papelOperador, $row['tipo_usuario']) && (int) $row['id_usuario'] !== $idOperador,
            'painel' => onefitPainelDoPapel($row['tipo_usuario']) !== null,
            'cadastros' => (int) $row['cadastros'],
            'cadastrosAtivos' => (int) $row['cadastros_ativos'],
            'especialidade' => $row['especialidade'],
            'catraca' => $catraca,
        ];
    }
    $stmt->close();
    return $lista;
}

/** Cadastros profissionais ainda sem conta, para vínculo explícito. */
function bo_cadastros_profissionais_livres(mysqli $conn): array
{
    $lista = [];
    $resultado = $conn->query('SELECT id_profissional, nome, email, especialidade, status FROM cadastro_profissional WHERE id_usuario IS NULL ORDER BY nome, id_profissional');
    while ($row = $resultado->fetch_assoc()) {
        $lista[] = [
            'id' => (int) $row['id_profissional'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'especialidade' => $row['especialidade'],
            'status' => $row['status'],
        ];
    }
    return $lista;
}
