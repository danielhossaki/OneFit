<?php
/**
 * includes/professores.php
 * Tela "Professores" do admin (seção "profissionais") e a regra única de quem
 * recebe novos agendamentos dos alunos (includes/agenda-service.php).
 *
 * Três conceitos separados, cada um com a sua coluna:
 *  - conta (usuarios.status): tela Funcionários; vale para painel e catraca;
 *  - cadastro profissional (cadastro_profissional.status): estado legado,
 *    preservado para não mudar regras existentes de catraca e agenda;
 *  - disponibilidade (cadastro_profissional.disponivel_agendamento, migração
 *    20261021_professores_v1): só novos agendamentos de alunos. Não ativa
 *    conta, não dá permissão, não muda cargo, catraca nem agendamentos já feitos.
 *
 * O professor nasce na tela Funcionários (cargo professor + cadastro
 * profissional vinculado por id_usuario); esta tela nunca cria nem exclui
 * cadastros. Cada gravação roda numa transação, confere de novo no banco o
 * operador (admin ou diretor ativo) e o cargo do professor, e registra
 * auditoria. Nega por padrão: migração ausente ou dado inválido não gravam nada.
 */

require_once __DIR__ . '/funcionarios.php';

const BO_PROFESSORES_MIGRACAO = '20261021_professores_v1';

/** Valores de usuarios.tipo_usuario que são o cargo professor (inclui o legado 'profissional'). */
function bo_professores_cargos(): array
{
    return array_values(array_filter(array_keys(onefitPoliticasPapeis()), static fn(string $papel): bool => onefitPapelCanonico($papel) === 'professor'));
}

/** Lista SQL dos cargos de professor (constantes da política, escapadas mesmo assim). */
function bo_professores_cargos_sql(mysqli $conn): string
{
    return implode(', ', array_map(static fn(string $cargo): string => "'" . $conn->real_escape_string($cargo) . "'", bo_professores_cargos()));
}

/** Colunas atuais de cadastro_profissional ('modalidades' e 'disponivel_agendamento' dependem de migrações). */
function bo_cadastro_profissional_colunas(mysqli $conn): array
{
    return array_column($conn->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional'"
    )->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
}

/** A coluna de disponibilidade já existe (migração 20261021_professores_v1 aplicada). */
function bo_professores_migracao(mysqli $conn): bool
{
    return in_array('disponivel_agendamento', bo_cadastro_profissional_colunas($conn), true);
}

function bo_professores_exigir_migracao(mysqli $conn): void
{
    if (!bo_professores_migracao($conn)) {
        throw new FuncionarioRecusado('A migração de disponibilidade dos professores ainda não foi aplicada neste banco. Nenhuma alteração foi feita.');
    }
}

/**
 * Condição SQL de quem recebe NOVOS agendamentos de alunos, para
 * cadastro_profissional `cp` unido a usuarios `u` por cp.id_usuario: cargo
 * professor, conta ativa, cadastro profissional ativo e disponível. Sem a
 * migração, todo cadastro que cumpre o resto conta como disponível (a regra
 * anterior), para a agenda não parar enquanto a coluna não existe.
 */
function bo_professores_condicao_agenda(mysqli $conn): string
{
    return 'u.tipo_usuario IN (' . bo_professores_cargos_sql($conn) . ") AND u.status = 'ativo' AND cp.status = 'ativo'"
        . (bo_professores_migracao($conn) ? ' AND cp.disponivel_agendamento = 1' : '');
}

/** Professores que recebem novos agendamentos: id_profissional, nome, especialidade. */
function bo_professores_agendaveis(mysqli $conn): array
{
    return $conn->query(
        'SELECT cp.id_profissional, cp.nome, cp.especialidade
         FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario
         WHERE ' . bo_professores_condicao_agenda($conn) . '
         ORDER BY cp.nome, cp.id_profissional'
    )->fetch_all(MYSQLI_ASSOC);
}

/**
 * Reconfere, dentro da transação da reserva (bo_agenda_inserir), se o professor
 * ainda recebe agendamentos. Trava (compartilhado) a conta e depois o cadastro,
 * na mesma ordem das telas Funcionários e Professores: uma mudança de conta,
 * cadastro ou disponibilidade espera a reserva terminar, e a reserva espera a
 * mudança em andamento terminar (lê sempre o valor já confirmado).
 */
function bo_professor_agendavel(mysqli $conn, int $idProfissional): bool
{
    $stmt = $conn->prepare('SELECT id_usuario FROM cadastro_profissional WHERE id_profissional = ?');
    $stmt->bind_param('i', $idProfissional);
    $stmt->execute();
    $idUsuario = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    if ($idUsuario <= 0) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? LOCK IN SHARE MODE');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $stmt->get_result();
    $stmt->close();
    $stmt = $conn->prepare(
        'SELECT cp.id_profissional FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario
         WHERE cp.id_profissional = ? AND cp.id_usuario = ? AND ' . bo_professores_condicao_agenda($conn) . ' LOCK IN SHARE MODE'
    );
    $stmt->bind_param('ii', $idProfissional, $idUsuario);
    $stmt->execute();
    $agendavel = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $agendavel;
}

/**
 * Situação na agenda de um cadastro, sem trava, só para avisos de tela (o
 * servidor decide em bo_professor_agendavel). null: não é cadastro de professor.
 */
function bo_professor_situacao_por_id(mysqli $conn, int $idProfissional): ?array
{
    $disponivel = bo_professores_migracao($conn) ? 'cp.disponivel_agendamento' : '1';
    $stmt = $conn->prepare(
        "SELECT u.status AS conta, cp.status AS cadastro, $disponivel AS disponivel
         FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario
         WHERE cp.id_profissional = ? AND u.tipo_usuario IN (" . bo_professores_cargos_sql($conn) . ')'
    );
    $stmt->bind_param('i', $idProfissional);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ? bo_professor_situacao_agenda((string) $linha['conta'], (string) $linha['cadastro'], (int) $linha['disponivel'] === 1) : null;
}

/**
 * Situação na agenda: ['recebe' => bool, 'motivo' => ok|conta_inativa|cadastro_inativo|indisponivel].
 * Mesma ordem de prioridade da condição SQL; a conta vem primeiro.
 */
function bo_professor_situacao_agenda(string $conta, string $cadastro, bool $disponivel): array
{
    $motivo = match (true) {
        $conta !== 'ativo' => 'conta_inativa',
        $cadastro !== 'ativo' => 'cadastro_inativo',
        !$disponivel => 'indisponivel',
        default => 'ok',
    };
    return ['recebe' => $motivo === 'ok', 'motivo' => $motivo];
}

/** Foto exibida: a do cadastro profissional (URL http/https) ou, sem ela, a do perfil da conta. */
function bo_professor_foto(?string $fotoCadastro, ?string $fotoConta): string
{
    $foto = trim((string) $fotoCadastro);
    if ($foto !== '' && filter_var($foto, FILTER_VALIDATE_URL)
        && in_array(strtolower((string) parse_url($foto, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        return $foto;
    }
    return bo_foto_perfil_url($fotoConta);
}

/**
 * Lista da tela Professores: contas com cargo professor que têm cadastro
 * profissional vinculado. Contas de outros cargos nunca entram, mesmo com
 * um cadastro antigo ligado a elas.
 */
function bo_professores_listar(mysqli $conn): array
{
    $colunas = bo_cadastro_profissional_colunas($conn);
    $modalidades = in_array('modalidades', $colunas, true) ? 'cp.modalidades' : "''";
    $disponivel = in_array('disponivel_agendamento', $colunas, true) ? 'cp.disponivel_agendamento' : '1';
    $resultado = $conn->query(
        "SELECT cp.id_profissional, cp.id_usuario, u.nome, u.email, u.status AS conta_status, u.foto AS foto_conta,
                cp.especialidade, $modalidades AS modalidades, cp.registro_profissional, cp.descricao, cp.foto,
                cp.status AS cadastro_status, $disponivel AS disponivel_agendamento
         FROM cadastro_profissional cp JOIN usuarios u ON u.id_usuario = cp.id_usuario
         WHERE u.tipo_usuario IN (" . bo_professores_cargos_sql($conn) . ')
         ORDER BY u.nome, cp.id_profissional'
    );
    $lista = [];
    while ($row = $resultado->fetch_assoc()) {
        $disponivelAgenda = (int) $row['disponivel_agendamento'] === 1;
        $lista[] = [
            'id' => (int) $row['id_profissional'],
            'idUsuario' => (int) $row['id_usuario'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'conta' => (string) $row['conta_status'],
            'cadastro' => (string) $row['cadastro_status'],
            'disponivel' => $disponivelAgenda,
            'agenda' => bo_professor_situacao_agenda((string) $row['conta_status'], (string) $row['cadastro_status'], $disponivelAgenda),
            'especialidade' => (string) $row['especialidade'],
            'modalidades' => (string) $row['modalidades'],
            'documento' => (string) $row['registro_profissional'],
            'descricao' => (string) $row['descricao'],
            'foto' => bo_professor_foto($row['foto'], $row['foto_conta']),
        ];
    }
    return $lista;
}

/** Pendências que tiram alguém da lista: professor sem cadastro e cadastro antigo sem conta. */
function bo_professores_pendencias(mysqli $conn): array
{
    return [
        'semCadastro' => (int) $conn->query(
            'SELECT COUNT(*) FROM usuarios u WHERE u.tipo_usuario IN (' . bo_professores_cargos_sql($conn) . ')
             AND NOT EXISTS (SELECT 1 FROM cadastro_profissional cp WHERE cp.id_usuario = u.id_usuario)'
        )->fetch_row()[0],
        'semConta' => (int) $conn->query('SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IS NULL')->fetch_row()[0],
    ];
}

/**
 * Trava a conta e depois o cadastro do professor (a mesma ordem da reserva
 * na agenda) e devolve o cadastro. Só cadastros vinculados a uma conta com
 * cargo professor; nunca cadastros sem conta ou de outros cargos.
 */
function bo_professor_alvo(mysqli $conn, int $idProfissional): array
{
    if ($idProfissional <= 0) {
        throw new FuncionarioRecusado('Professor não encontrado.');
    }
    $stmt = $conn->prepare('SELECT id_usuario FROM cadastro_profissional WHERE id_profissional = ?');
    $stmt->bind_param('i', $idProfissional);
    $stmt->execute();
    $idUsuario = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    $stmt->close();
    $conta = $idUsuario > 0 ? onefitBuscarSituacaoUsuario($conn, $idUsuario, true) : null;
    if (!$conta || onefitPapelCanonico($conta['tipo_usuario']) !== 'professor') {
        throw new FuncionarioRecusado('Professor não encontrado. O cadastro de professores é feito na tela Funcionários.');
    }
    $colunas = bo_cadastro_profissional_colunas($conn);
    $extras = (in_array('modalidades', $colunas, true) ? ', modalidades' : '') . (in_array('disponivel_agendamento', $colunas, true) ? ', disponivel_agendamento' : '');
    $stmt = $conn->prepare("SELECT id_profissional, id_usuario, especialidade, registro_profissional, descricao, foto, status$extras
        FROM cadastro_profissional WHERE id_profissional = ? AND id_usuario = ? FOR UPDATE");
    $stmt->bind_param('ii', $idProfissional, $idUsuario);
    $stmt->execute();
    $cadastro = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cadastro) {
        throw new FuncionarioRecusado('Professor não encontrado.');
    }
    return $cadastro;
}

/** Liga ou desliga a disponibilidade para novos agendamentos (valor explícito, nunca alternância). */
function bo_professor_alterar_disponibilidade(mysqli $conn, int $idOperador, int $idProfissional, string $valor): void
{
    if (!in_array($valor, ['0', '1'], true)) {
        throw new FuncionarioRecusado('Disponibilidade inválida.');
    }
    $disponivel = (int) $valor;
    bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $idProfissional, $disponivel): void {
        bo_funcionarios_exigir_migracao($conn);
        bo_professores_exigir_migracao($conn);
        bo_funcionarios_exigir_operador($conn, $idOperador);
        $cadastro = bo_professor_alvo($conn, $idProfissional);
        $stmt = $conn->prepare('UPDATE cadastro_profissional SET disponivel_agendamento = ? WHERE id_profissional = ?');
        $stmt->bind_param('ii', $disponivel, $idProfissional);
        $stmt->execute();
        $stmt->close();
        bo_auditoria_registrar($conn, 'disponibilidade', $idOperador, (int) $cadastro['id_usuario'], [
            'cadastro_profissional' => $idProfissional, 'de' => (int) $cadastro['disponivel_agendamento'], 'para' => $disponivel,
        ], 'professor');
    });
}

/**
 * Normaliza os dados profissionais do formulário. Nome, e-mail e celular são
 * dados da conta (tela Funcionários) e não entram aqui. Modalidades: só as
 * cadastradas na tabela `modalidades`.
 */
function bo_professor_dados(mysqli $conn, array $entrada): array
{
    $texto = static fn(string $campo): string => trim(preg_replace('/\s+/u', ' ', (string) ($entrada[$campo] ?? '')) ?? '');
    $dados = [
        'especialidade' => $texto('especialidade'),
        'registro_profissional' => $texto('documento'),
        'descricao' => trim((string) ($entrada['descricao'] ?? '')),
    ];
    if (mb_strlen($dados['especialidade'], 'UTF-8') > 100 || mb_strlen($dados['registro_profissional'], 'UTF-8') > 100) {
        throw new FuncionarioRecusado('Especialidade e registro profissional podem ter até 100 caracteres.');
    }
    if (mb_strlen($dados['descricao'], 'UTF-8') > 2000) {
        throw new FuncionarioRecusado('A descrição pode ter até 2000 caracteres.');
    }
    $validas = array_column($conn->query('SELECT nome FROM modalidades ORDER BY nome')->fetch_all(MYSQLI_ASSOC), 'nome');
    $enviadas = array_values(array_unique(array_map('strval', (array) ($entrada['modalidades'] ?? []))));
    $dados['modalidades'] = implode(', ', array_values(array_intersect($validas, $enviadas)));
    if (strlen($dados['modalidades']) > 255) {
        throw new FuncionarioRecusado('Selecione menos modalidades.');
    }
    return $dados;
}

/**
 * Edita os dados profissionais (especialidade, modalidades, registro,
 * descrição e foto), preservando o status legado do cadastro.
 * $foto: URL de um upload novo ou null para manter a atual.
 */
function bo_professor_atualizar_dados(mysqli $conn, int $idOperador, int $idProfissional, array $entrada, ?string $foto = null): void
{
    $dados = bo_professor_dados($conn, $entrada);
    bo_funcionarios_transacao($conn, static function () use ($conn, $idOperador, $idProfissional, $dados, $foto): void {
        bo_funcionarios_exigir_migracao($conn);
        bo_funcionarios_exigir_operador($conn, $idOperador);
        $cadastro = bo_professor_alvo($conn, $idProfissional);
        $temModalidades = array_key_exists('modalidades', $cadastro);
        $novos = [
            'especialidade' => $dados['especialidade'] !== '' ? $dados['especialidade'] : null,
            'registro_profissional' => $dados['registro_profissional'] !== '' ? $dados['registro_profissional'] : null,
            'descricao' => $dados['descricao'] !== '' ? $dados['descricao'] : null,
            'foto' => $foto ?? $cadastro['foto'],
        ] + ($temModalidades ? ['modalidades' => $dados['modalidades'] !== '' ? $dados['modalidades'] : null] : []);
        $sets = implode(', ', array_map(static fn(string $coluna): string => "$coluna = ?", array_keys($novos)));
        $valores = array_values($novos);
        $valores[] = $idProfissional;
        $stmt = $conn->prepare("UPDATE cadastro_profissional SET $sets WHERE id_profissional = ?");
        $stmt->bind_param(str_repeat('s', count($novos)) . 'i', ...$valores);
        $stmt->execute();
        $stmt->close();

        $alteracoes = [];
        foreach ($novos as $campo => $valor) {
            if ((string) $cadastro[$campo] !== (string) $valor) {
                // Descrição e foto entram só como "alterado"; o resto, com os valores.
                $alteracoes[$campo] = in_array($campo, ['descricao', 'foto'], true) ? true : ['de' => $cadastro[$campo], 'para' => $valor];
            }
        }
        if ($alteracoes) {
            bo_auditoria_registrar($conn, 'editar_cadastro', $idOperador, (int) $cadastro['id_usuario'], [
                'cadastro_profissional' => $idProfissional, 'alteracoes' => $alteracoes,
            ], 'professor');
        }
    });
}
