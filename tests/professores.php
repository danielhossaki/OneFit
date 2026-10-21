<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Tela Professores e regra única da agenda (pages/dashboard/includes/professores.php e
// agenda-service.php) contra o banco descartável onefit_interface_test_*, preparado por
// tests/funcionarios-preparar.ps1. Nenhum e-mail é enviado.
//   php tests/professores.php --antes   antes da migração 20261021_professores_v1 (o preparar roda)
//   php tests/professores.php           depois da migração
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
define('BASE_URL', 'http://127.0.0.1:8765/AN25/OneFit/');
require __DIR__ . '/../pages/dashboard/includes/agenda-service.php';
onefitEnv('APP_URL'); // carrega o .env agora para poder neutralizar o SMTP em seguida
foreach (['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'] as $variavel) {
    putenv($variavel . '=');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
$antes = in_array('--antes', $argv, true);
if (!bo_funcionarios_migracao($db)['ok']) {
    throw new RuntimeException('Rode antes tests/funcionarios-preparar.ps1 (migração de funcionários no banco de teste).');
}
if (bo_professores_migracao($db) === $antes) {
    throw new RuntimeException($antes ? 'A migração de professores já foi aplicada neste banco.' : 'Rode antes tests/funcionarios-preparar.ps1 (migração de professores no banco de teste).');
}
$_SESSION = [];

$checks = 0;
function pCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
/** Exige uma recusa de regra de negócio (com um trecho da mensagem). */
function pRecusa(callable $acao, string $trecho, string $message): void
{
    try {
        $acao();
    } catch (FuncionarioRecusado $recusa) {
        pCheck($trecho === '' || str_contains($recusa->getMessage(), $trecho), $message . ' (mensagem: ' . $recusa->getMessage() . ')');
        return;
    }
    pCheck(false, $message . ' (nenhuma recusa)');
}
function pContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function pCpf(int $n): string
{
    $base = sprintf('%09d', $n);
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $base[$i] * (($posicao + 1) - $i);
        }
        $base .= ((10 * $soma) % 11) % 10;
    }
    return $base;
}
function pCadastros(mysqli $db, int $idUsuario): array
{
    return $db->query("SELECT * FROM cadastro_profissional WHERE id_usuario = $idUsuario ORDER BY id_profissional")->fetch_all(MYSQLI_ASSOC);
}
function pCadastro(mysqli $db, int $idProfissional): ?array
{
    return $db->query("SELECT * FROM cadastro_profissional WHERE id_profissional = $idProfissional")->fetch_assoc() ?: null;
}
function pConta(mysqli $db, int $idUsuario): array
{
    return $db->query("SELECT * FROM usuarios WHERE id_usuario = $idUsuario")->fetch_assoc();
}
function pListado(mysqli $db, int $idProfissional): ?array
{
    foreach (bo_professores_listar($db) as $linha) {
        if ($linha['id'] === $idProfissional) {
            return $linha;
        }
    }
    return null;
}
function pAgendavel(mysqli $db, int $idProfissional): bool
{
    return in_array($idProfissional, array_map('intval', array_column(bo_professores_agendaveis($db), 'id_profissional')), true);
}
function pSlots(mysqli $db, int $idProfissional, string $data): array
{
    return array_values(array_filter(bo_agenda_slots($db, $data, $data), static fn(array $s): bool => (int) $s['id_profissional'] === $idProfissional));
}
function pCatraca(mysqli $db, int $idUsuario): array
{
    foreach (bo_funcionarios_listar($db, 'admin', 1) as $f) {
        if ($f['id'] === $idUsuario) {
            return $f['catraca'];
        }
    }
    return ['liberado' => false, 'motivo' => 'ausente'];
}

$run = random_int(100000, 899999);
$seq = 0;
$entrada = static function (string $cargo, array $extra = []) use ($run, &$seq): array {
    $seq++;
    return $extra + [
        'nome' => "Professor Teste $run $seq", 'email' => "prof{$run}x{$seq}@example.test", 'cpf' => pCpf($run * 100 + 50 + $seq),
        'celular' => '11987654321', 'nascimento' => '1988-07-15', 'genero' => 'outro', 'nacionalidade' => 'BR',
        'endereco' => 'Rua dos Professores, 7', 'cidade' => 'São Paulo', 'estado' => 'SP', 'cargo' => $cargo,
    ];
};
$enviar = static fn(string $email, string $nome, array $tokens): bool => true;
$ADMIN = 1;
$novoProfessor = static function (array $extra = []) use ($db, $entrada, $enviar, $ADMIN): array {
    $dados = $entrada('professor', $extra + ['cadastro_modo' => 'novo', 'especialidade' => 'Musculação']);
    $id = bo_funcionario_criar($db, $ADMIN, $dados, $enviar)['id'];
    $cadastros = pCadastros($db, $id);
    return ['usuario' => $id, 'cadastro' => (int) ($cadastros[0]['id_profissional'] ?? 0), 'dados' => $dados];
};
// Próximo dia com horários simulados (domingo não tem).
$dia = new DateTimeImmutable('tomorrow', new DateTimeZone('America/Sao_Paulo'));
while ((int) $dia->format('w') === 0) {
    $dia = $dia->modify('+1 day');
}
$data = $dia->format('Y-m-d');

// =============================================================================================
// Antes da migração: a regra anterior continua (todos disponíveis) e nada grava disponibilidade.
// =============================================================================================
if ($antes) {
    $p = $novoProfessor();
    pCheck(count(pCadastros($db, $p['usuario'])) === 1, 'Sem a migração: cadastro de professor cria um cadastro profissional');
    $linha = pListado($db, $p['cadastro']);
    pCheck($linha !== null && $linha['disponivel'] && $linha['agenda']['recebe'] && $linha['idUsuario'] === $p['usuario'], 'Sem a migração: professor listado e disponível (regra anterior)');
    pCheck(pAgendavel($db, $p['cadastro']) && pSlots($db, $p['cadastro'], $data) !== [], 'Sem a migração: professor elegível continua na agenda');
    $cadastroAntes = pCadastro($db, $p['cadastro']);
    pRecusa(static fn() => bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0'), 'migração de disponibilidade', 'Sem a migração: disponibilidade não é gravada');
    pCheck(pCadastro($db, $p['cadastro']) === $cadastroAntes, 'Recusa não altera o cadastro');
    bo_professor_atualizar_dados($db, $ADMIN, $p['cadastro'], ['especialidade' => 'Pilates', 'documento' => 'CREF 123', 'descricao' => 'Antes da migração', 'cadastro_status' => 'ativo']);
    pCheck(pCadastro($db, $p['cadastro'])['especialidade'] === 'Pilates', 'Sem a migração: dados profissionais continuam editáveis');
    $vendedor = bo_funcionario_criar($db, $ADMIN, $entrada('vendedor'), $enviar)['id'];
    pCheck(!array_filter(bo_professores_listar($db), static fn(array $l): bool => $l['idUsuario'] === $vendedor), 'Sem a migração: vendedor fora da lista');
    // Cadastro antigo sem conta (criado pela tela antiga): a migração precisa preservá-lo.
    $db->query("INSERT INTO cadastro_profissional (nome, email, especialidade, status) VALUES ('Cadastro antigo $run', 'antigo{$run}@example.test', 'Yoga', 'ativo')");
    $antigo = (int) $db->insert_id;
    pCheck(!pAgendavel($db, $antigo) && pListado($db, $antigo) === null && bo_professores_pendencias($db)['semConta'] >= 1, 'Cadastro sem conta fica fora da lista e da agenda');
    echo "Professores (antes da migração): $checks verificações passaram.\n";
    exit;
}

// =============================================================================================
// Depois da migração.
// =============================================================================================
$auditoriaAntes = pContar($db, "SELECT COUNT(*) FROM auditoria_eventos WHERE categoria = 'professor'");

// --- Regras puras ---------------------------------------------------------------------------
pCheck(bo_professores_cargos() === ['professor', 'profissional'], 'Cargos de professor: professor e o legado profissional');
pCheck(bo_professor_situacao_agenda('ativo', 'ativo', true) === ['recebe' => true, 'motivo' => 'ok'], 'Tudo ativo e disponível: recebe');
pCheck(bo_professor_situacao_agenda('inativo', 'ativo', true)['motivo'] === 'conta_inativa'
    && bo_professor_situacao_agenda('bloqueado', 'ativo', true)['motivo'] === 'conta_inativa', 'Conta inativa ou bloqueada: não recebe');
pCheck(bo_professor_situacao_agenda('ativo', 'inativo', true)['motivo'] === 'cadastro_inativo', 'Cadastro inativo: não recebe');
pCheck(bo_professor_situacao_agenda('ativo', 'ativo', false)['motivo'] === 'indisponivel', 'Indisponível: não recebe');
pCheck(bo_professor_foto('https://example.test/a.jpg', null) === 'https://example.test/a.jpg' && bo_professor_foto('javascript:alert(1)', null) === ''
    && bo_professor_foto('', null) === '', 'Foto: só URL http/https');

// --- 1 e 2. Cadastro de professor em Funcionários aparece em Professores ---------------------
$p = $novoProfessor();
$cadastros = pCadastros($db, $p['usuario']);
pCheck(count($cadastros) === 1 && (int) $cadastros[0]['disponivel_agendamento'] === 1 && $cadastros[0]['status'] === 'ativo', 'Professor novo: um cadastro, ativo e disponível');
pCheck($cadastros[0]['nome'] === $p['dados']['nome'] && $cadastros[0]['email'] === $p['dados']['email'] && $cadastros[0]['especialidade'] === 'Musculação', 'Cadastro com os dados da conta');
$linha = pListado($db, $p['cadastro']);
pCheck($linha !== null && $linha['idUsuario'] === $p['usuario'] && $linha['nome'] === $p['dados']['nome'] && $linha['conta'] === 'ativo'
    && $linha['cadastro'] === 'ativo' && $linha['disponivel'] && $linha['agenda']['recebe'] && $linha['especialidade'] === 'Musculação', 'Aparece na tela Professores sem novo cadastro');
pCheck(pAgendavel($db, $p['cadastro']) && pSlots($db, $p['cadastro'], $data) !== [] && bo_professor_agendavel($db, $p['cadastro']), 'Aparece nos horários da agenda');
$legado = array_values(array_filter(bo_professores_listar($db), static fn(array $l): bool => $l['idUsuario'] === 4));
pCheck(count($legado) === 1, 'Conta com o valor legado profissional também é listada');

// --- 4. Outros cargos não aparecem -----------------------------------------------------------
$outros = [];
foreach (['vendedor', 'recepcionista', 'faxineira'] as $cargo) {
    $outros[$cargo] = bo_funcionario_criar($db, $ADMIN, $entrada($cargo), $enviar)['id'];
}
$listados = array_column(bo_professores_listar($db), 'idUsuario');
pCheck(!array_intersect($outros, $listados), 'Vendedor, recepcionista e faxineira fora da lista');
pCheck(pContar($db, 'SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario IN (' . implode(',', $outros) . ')') === 0, 'Outros cargos não ganham cadastro profissional');
// Cadastro antigo ligado a quem não é professor: nunca entra na lista nem na agenda.
$db->query("INSERT INTO cadastro_profissional (id_usuario, nome, email, status) VALUES ({$outros['vendedor']}, 'Vendedor com cadastro', 'vendcad{$run}@example.test', 'ativo')");
$cadastroVendedor = (int) $db->insert_id;
pCheck(pListado($db, $cadastroVendedor) === null && !pAgendavel($db, $cadastroVendedor) && !bo_professor_agendavel($db, $cadastroVendedor), 'Cadastro de vendedor fora da lista e da agenda');
pRecusa(static fn() => bo_professor_alterar_disponibilidade($db, $ADMIN, $cadastroVendedor, '0'), 'Professor não encontrado', 'Disponibilidade de quem não é professor: recusada');
$db->query("INSERT INTO cadastro_profissional (nome, email, status) VALUES ('Sem conta $run', 'semconta{$run}@example.test', 'ativo')");
$semConta = (int) $db->insert_id;
pRecusa(static fn() => bo_professor_alterar_disponibilidade($db, $ADMIN, $semConta, '0'), 'Professor não encontrado', 'Disponibilidade de cadastro sem conta: recusada');
pRecusa(static fn() => bo_professor_alterar_disponibilidade($db, $ADMIN, 0, '0'), 'Professor não encontrado', 'Professor inexistente: recusado');
pCheck((int) pCadastro($db, $cadastroVendedor)['disponivel_agendamento'] === 1 && (int) pCadastro($db, $semConta)['disponivel_agendamento'] === 1, 'Recusas não gravam nada');

// --- 3. Sem duplicação -------------------------------------------------------------------------
pRecusa(static fn() => bo_funcionario_vincular_cadastro($db, $ADMIN, $p['usuario'], ['cadastro_modo' => 'novo']), 'já tem um cadastro', 'Segundo vínculo para o mesmo professor: recusado');
try {
    $db->query("INSERT INTO cadastro_profissional (id_usuario, nome, email) VALUES ({$p['usuario']}, 'Duplicado', 'dup{$run}@example.test')");
    pCheck(false, 'O banco deveria recusar segundo cadastro da mesma conta');
} catch (mysqli_sql_exception $erro) {
    pCheck($erro->getCode() === 1062, 'Chave única recusa segundo cadastro da mesma conta');
}
// Vincular um cadastro existente (sem conta) reaproveita o ID e os dados profissionais.
$db->query("INSERT INTO cadastro_profissional (nome, email, especialidade, registro_profissional, status) VALUES ('Cadastro livre $run', 'livre{$run}@example.test', 'Natação', 'CREF 9', 'ativo')");
$livre = (int) $db->insert_id;
$vinculado = bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['cadastro_modo' => 'existente', 'id_profissional' => (string) $livre]), $enviar)['id'];
$cadastrosVinculado = pCadastros($db, $vinculado);
pCheck(count($cadastrosVinculado) === 1 && (int) $cadastrosVinculado[0]['id_profissional'] === $livre && $cadastrosVinculado[0]['especialidade'] === 'Natação'
    && $cadastrosVinculado[0]['registro_profissional'] === 'CREF 9', 'Cadastro existente reaproveitado com o mesmo ID e dados');
pCheck(pListado($db, $livre) !== null && pAgendavel($db, $livre), 'Cadastro vinculado aparece em Professores e na agenda');

// --- 5. Edição: dados sincronizados, um cadastro só ----------------------------------------------
$edicao = ['nome' => "Professor Editado $run", 'email' => "editado{$run}@example.test", 'celular' => '21987650000', 'cadastro_modo' => 'novo'] + $p['dados'];
bo_funcionario_atualizar($db, $ADMIN, $p['usuario'], $edicao);
$cadastros = pCadastros($db, $p['usuario']);
pCheck(count($cadastros) === 1 && (int) $cadastros[0]['id_profissional'] === $p['cadastro'], 'Edição mantém o mesmo cadastro (sem duplicar)');
pCheck($cadastros[0]['nome'] === $edicao['nome'] && $cadastros[0]['email'] === $edicao['email'] && $cadastros[0]['celular'] === '21987650000', 'Nome, e-mail e celular sincronizados no cadastro');
pCheck($cadastros[0]['especialidade'] === 'Musculação' && $cadastros[0]['status'] === 'ativo' && (int) $cadastros[0]['disponivel_agendamento'] === 1, 'Dados profissionais preservados na edição');
$p['dados'] = $edicao;
$contaAntes = pConta($db, $p['usuario']);
pRecusa(static fn() => bo_funcionario_atualizar($db, $ADMIN, $p['usuario'], ['email' => "livre{$run}@example.test"] + $edicao), 'outro cadastro profissional', 'E-mail de outro cadastro profissional: edição recusada');
pCheck(pConta($db, $p['usuario']) === $contaAntes && pCadastros($db, $p['usuario']) === $cadastros, 'Recusa desfaz a edição da conta e do cadastro');
// Cargo muda e volta: o cadastro é preservado e reaproveitado, com a disponibilidade anterior.
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
bo_funcionario_atualizar($db, $ADMIN, $p['usuario'], ['cargo' => 'recepcionista'] + $edicao);
pCheck(pListado($db, $p['cadastro']) === null && !pAgendavel($db, $p['cadastro']) && count(pCadastros($db, $p['usuario'])) === 1, 'Professor que vira recepcionista sai da lista; cadastro preservado');
bo_funcionario_atualizar($db, $ADMIN, $p['usuario'], $edicao);
$cadastros = pCadastros($db, $p['usuario']);
pCheck(count($cadastros) === 1 && (int) $cadastros[0]['id_profissional'] === $p['cadastro'] && (int) $cadastros[0]['disponivel_agendamento'] === 0, 'De volta a professor: mesmo cadastro e disponibilidade anterior');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '1');

// --- 6. Disponibilidade ------------------------------------------------------------------------
$contaAntes = pConta($db, $p['usuario']);
$catracaAntes = pCatraca($db, $p['usuario']);
$db->query("INSERT INTO disponibilidade_profissional (id_profissional, modalidade, data_evento, hora_inicio, hora_fim, local, status)
    VALUES ({$p['cadastro']}, 'Avaliação física', '$data', '20:00:00', '21:00:00', 'Sala 1', 'disponivel')");
$horarioBanco = (int) $db->insert_id;
pCheck(in_array('db:' . $horarioBanco, array_column(pSlots($db, $p['cadastro'], $data), 'key'), true), 'Horário cadastrado pelo professor aparece quando disponível');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
pCheck((int) pCadastro($db, $p['cadastro'])['disponivel_agendamento'] === 0, 'Indisponível gravado');
pCheck(!pAgendavel($db, $p['cadastro']) && pSlots($db, $p['cadastro'], $data) === [] && !bo_professor_agendavel($db, $p['cadastro']), 'Indisponível: fora dos horários simulados e cadastrados e da reconferência');
$linha = pListado($db, $p['cadastro']);
pCheck($linha !== null && !$linha['disponivel'] && $linha['agenda']['motivo'] === 'indisponivel' && $linha['conta'] === 'ativo', 'Continua listado, como indisponível, com a conta ativa');
pCheck(pConta($db, $p['usuario']) === $contaAntes && pCadastro($db, $p['cadastro'])['status'] === 'ativo' && pCatraca($db, $p['usuario']) === $catracaAntes && $catracaAntes['liberado'],
    'Disponibilidade não muda conta, cargo, cadastro nem catraca');
$evento = $db->query("SELECT * FROM auditoria_eventos WHERE categoria = 'professor' AND acao = 'disponibilidade' AND id_alvo = {$p['usuario']} ORDER BY id_evento DESC LIMIT 1")->fetch_assoc();
$detalhes = json_decode((string) ($evento['detalhes'] ?? ''), true);
pCheck($evento && (int) $evento['id_operador'] === $ADMIN && $detalhes === ['cadastro_profissional' => $p['cadastro'], 'de' => 1, 'para' => 0], 'Auditoria da disponibilidade');
foreach (['2', '', 'sim', ' 1', 'true'] as $invalido) {
    pRecusa(static fn() => bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], $invalido), 'Disponibilidade inválida', "Valor inválido recusado: '$invalido'");
}
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '1');
pCheck(pAgendavel($db, $p['cadastro']) && in_array('db:' . $horarioBanco, array_column(pSlots($db, $p['cadastro'], $data), 'key'), true), 'Disponível de novo: volta à agenda');

// --- 9. Agendamentos existentes preservados ------------------------------------------------------
$idAgendamento = pContar($db, 'SELECT COALESCE(MAX(id_agendamento), 0) + 1 FROM agendamento');
$db->query("INSERT INTO agendamento (id_agendamento, id_usuario, id_profissional, titulo, tipo, data_evento, hora_inicio, hora_fim, local, status)
    VALUES ($idAgendamento, 3, {$p['cadastro']}, 'Personal trainer', 'personal', '$data', '19:00:00', '20:00:00', 'Sala 2', 'confirmado')");
$db->query("UPDATE disponibilidade_profissional SET status = 'ocupado' WHERE id_disponibilidade = $horarioBanco");
$agendamentoAntes = $db->query("SELECT * FROM agendamento WHERE id_agendamento = $idAgendamento")->fetch_assoc();
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
pCheck($db->query("SELECT * FROM agendamento WHERE id_agendamento = $idAgendamento")->fetch_assoc() === $agendamentoAntes
    && $db->query("SELECT status FROM disponibilidade_profissional WHERE id_disponibilidade = $horarioBanco")->fetch_row()[0] === 'ocupado', 'Agendamento e horário reservado preservados ao ficar indisponível');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '1');

// --- Criação central de agendamentos (aluno e professor usam o mesmo caminho) -------------------
$agendamentoBase = ['id_usuario' => 3, 'id_profissional' => $p['cadastro'], 'titulo' => 'Avaliação', 'tipo' => 'avaliacao', 'data_evento' => $data,
    'hora_inicio' => '06:00:00', 'hora_fim' => '', 'local' => '', 'observacao' => '', 'status' => 'agendado'];
$contarDoProfessor = static fn(): int => pContar($db, "SELECT COUNT(*) FROM agendamento WHERE id_profissional = {$p['cadastro']}");
try {
    bo_agenda_inserir($db, $agendamentoBase);
    pCheck(false, 'bo_agenda_inserir fora da transação deveria falhar');
} catch (LogicException $erro) {
    pCheck(true, 'bo_agenda_inserir só roda dentro de bo_agenda_transacao');
}
try {
    bo_agenda_transacao($db, static fn() => bo_agenda_inserir($db, ['status' => 'cancelado'] + $agendamentoBase));
    pCheck(false, 'Situação inicial inválida deveria falhar');
} catch (LogicException $erro) {
    pCheck(true, 'Situação inicial só agendado ou confirmado');
}
$antesCentral = $contarDoProfessor();
$novoId = bo_agenda_transacao($db, static fn(): int => bo_agenda_inserir($db, ['observacao' => 'Trazer tênis'] + $agendamentoBase));
$criado = $db->query("SELECT * FROM agendamento WHERE id_agendamento = $novoId")->fetch_assoc();
pCheck($contarDoProfessor() === $antesCentral + 1 && $criado['status'] === 'agendado' && $criado['hora_fim'] === null && $criado['local'] === null
    && $criado['observacao'] === 'Trazer tênis' && (int) $criado['id_usuario'] === 3, 'Professor disponível: agendamento criado pelo serviço central');
$antesCentral = $contarDoProfessor();
try {
    bo_agenda_transacao($db, static function () use ($db, $agendamentoBase): void {
        bo_agenda_inserir($db, ['hora_inicio' => '06:30:00'] + $agendamentoBase);
        throw new RuntimeException('falha depois de inserir');
    });
} catch (RuntimeException $erro) {
}
pCheck($contarDoProfessor() === $antesCentral, 'Erro na mesma transação desfaz o agendamento');
$recusar = static function (string $rotulo) use ($db, $agendamentoBase, $contarDoProfessor): void {
    $antes = $contarDoProfessor();
    try {
        bo_agenda_transacao($db, static fn() => bo_agenda_inserir($db, ['hora_inicio' => '07:00:00'] + $agendamentoBase));
        pCheck(false, "$rotulo: deveria recusar");
    } catch (AgendamentoRecusado $recusa) {
        pCheck($contarDoProfessor() === $antes && str_contains($recusa->getMessage(), 'não está disponível'), "$rotulo: agendamento recusado, nada gravado");
    }
};
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
$recusar('Indisponível');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '1');
$db->query("UPDATE cadastro_profissional SET status = 'inativo' WHERE id_profissional = {$p['cadastro']}");
$recusar('Cadastro profissional inativo');
$db->query("UPDATE cadastro_profissional SET status = 'ativo' WHERE id_profissional = {$p['cadastro']}");
bo_funcionario_alterar_status($db, $ADMIN, $p['usuario'], 'inativo');
$recusar('Conta inativa');
bo_funcionario_alterar_status($db, $ADMIN, $p['usuario'], 'ativo');
pCheck(bo_professor_situacao_por_id($db, $p['cadastro']) === ['recebe' => true, 'motivo' => 'ok'] && bo_professor_situacao_por_id($db, $cadastroVendedor) === null, 'Situação para avisos de tela');

// --- 11. Concorrência: disponibilidade x reserva (duas conexões, espera de trava curta) ----------
$db2 = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db2->set_charset('utf8mb4');
$db->query('SET SESSION innodb_lock_wait_timeout = 1');
$db2->query('SET SESSION innodb_lock_wait_timeout = 1');
// a) Reserva em andamento (conexão 2 já reconferiu o professor): a troca de disponibilidade espera.
$db2->begin_transaction();
pCheck(bo_professor_agendavel($db2, $p['cadastro']), 'Conexão 2 reconfere o professor dentro da reserva');
try {
    bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
    pCheck(false, 'A troca de disponibilidade não pode passar por cima de uma reserva em andamento');
} catch (mysqli_sql_exception $erro) {
    pCheck($erro->getCode() === 1205 && (int) pCadastro($db, $p['cadastro'])['disponivel_agendamento'] === 1, 'Troca espera a reserva (trava) e não grava nada');
}
$db2->commit();
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
pCheck((int) pCadastro($db, $p['cadastro'])['disponivel_agendamento'] === 0, 'Terminada a reserva, a troca passa');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '1');
// b) Troca para indisponível em andamento (conexão 2, sem commit): a reserva espera e não usa o valor antigo.
$db2->begin_transaction();
$db2->query("SELECT id_usuario FROM usuarios WHERE id_usuario = {$p['usuario']} FOR UPDATE");
$db2->query("UPDATE cadastro_profissional SET disponivel_agendamento = 0 WHERE id_profissional = {$p['cadastro']}");
$antes = $contarDoProfessor();
try {
    bo_agenda_transacao($db, static fn() => bo_agenda_inserir($db, ['hora_inicio' => '07:30:00'] + $agendamentoBase));
    pCheck(false, 'A reserva não pode passar por cima de uma troca de disponibilidade em andamento');
} catch (mysqli_sql_exception $erro) {
    pCheck($erro->getCode() === 1205 && $contarDoProfessor() === $antes, 'Reserva espera a troca em andamento e não grava nada');
}
$db2->commit();
$recusar('Depois da troca confirmada');
$db2->query("UPDATE cadastro_profissional SET disponivel_agendamento = 1 WHERE id_profissional = {$p['cadastro']}");
// c) A trava nomeada serializa todas as reservas: com ela ocupada, nenhuma outra grava.
$db2->query("SELECT GET_LOCK('" . BO_AGENDA_TRAVA . "', 0)");
$antes = $contarDoProfessor();
$inicio = microtime(true);
try {
    bo_agenda_transacao($db, static fn() => bo_agenda_inserir($db, ['hora_inicio' => '08:00:00'] + $agendamentoBase));
    pCheck(false, 'Reserva com a trava ocupada deveria falhar');
} catch (AgendamentoRecusado $recusa) {
    pCheck(str_contains($recusa->getMessage(), 'Agenda ocupada') && $contarDoProfessor() === $antes && microtime(true) - $inicio >= BO_AGENDA_TRAVA_SEGUNDOS - 1, 'Trava ocupada: reserva recusada após a espera, nada gravado');
}
$db2->query("SELECT RELEASE_LOCK('" . BO_AGENDA_TRAVA . "')");
pCheck(bo_agenda_em_transacao() === 0, 'Nenhuma transação de agenda ficou aberta');
$db->query('SET SESSION innodb_lock_wait_timeout = 50');
$db2->close();

// --- 5. Desativação e reativação da conta -------------------------------------------------------
bo_funcionario_alterar_status($db, $ADMIN, $p['usuario'], 'inativo');
$linha = pListado($db, $p['cadastro']);
pCheck($linha['conta'] === 'inativo' && $linha['agenda']['motivo'] === 'conta_inativa' && $linha['disponivel'] && !pAgendavel($db, $p['cadastro'])
    && pSlots($db, $p['cadastro'], $data) === [], 'Conta inativa: fora da agenda mesmo disponível');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '0');
pCheck(pConta($db, $p['usuario'])['status'] === 'inativo', 'Disponibilidade não reativa a conta');
bo_funcionario_alterar_status($db, $ADMIN, $p['usuario'], 'ativo');
pCheck(!pAgendavel($db, $p['cadastro']) && pListado($db, $p['cadastro'])['agenda']['motivo'] === 'indisponivel', 'Reativada: respeita a indisponibilidade definida antes');
bo_professor_alterar_disponibilidade($db, $ADMIN, $p['cadastro'], '1');
pCheck(pAgendavel($db, $p['cadastro']), 'Reativada e disponível: volta à agenda');

// --- 10. Permissões: só admin e diretor ativos -------------------------------------------------
$operadoresRecusados = [3 => 'aluno', 4 => 'professor (legado)', 5 => 'vendedor', 6 => 'admin inativo', $outros['recepcionista'] => 'recepcionista', $outros['faxineira'] => 'faxineira', $p['usuario'] => 'o próprio professor'];
$cadastroAntes = pCadastro($db, $p['cadastro']);
foreach ($operadoresRecusados as $operador => $rotulo) {
    pRecusa(static fn() => bo_professor_alterar_disponibilidade($db, $operador, $p['cadastro'], '0'), 'permissão', "Disponibilidade por $rotulo: recusada");
    pRecusa(static fn() => bo_professor_atualizar_dados($db, $operador, $p['cadastro'], ['cadastro_status' => 'inativo']), 'permissão', "Dados profissionais por $rotulo: recusados");
}
pCheck(pCadastro($db, $p['cadastro']) === $cadastroAntes, 'Recusas de permissão não gravam nada');
// Diretor (cargo acima do admin) também gerencia a disponibilidade.
$diretor = bo_funcionario_criar($db, $ADMIN, $entrada('vendedor'), $enviar)['id'];
$db->query("UPDATE usuarios SET tipo_usuario = 'diretor' WHERE id_usuario = $diretor");
bo_professor_alterar_disponibilidade($db, $diretor, $p['cadastro'], '0');
pCheck((int) pCadastro($db, $p['cadastro'])['disponivel_agendamento'] === 0, 'Diretor altera a disponibilidade');
bo_professor_alterar_disponibilidade($db, $diretor, $p['cadastro'], '1');
$db->query("UPDATE usuarios SET tipo_usuario = 'vendedor', status = 'inativo' WHERE id_usuario = $diretor");

// --- Dados profissionais ------------------------------------------------------------------------
$modalidade = "Modalidade Teste $run";
$db->query('INSERT INTO modalidades (id_modalidade, nome) SELECT COALESCE(MAX(id_modalidade), 0) + 1, ' . "'$modalidade'" . ' FROM modalidades');
$nomeCadastro = pCadastro($db, $p['cadastro'])['nome'];
$db->query("UPDATE cadastro_profissional SET status = 'inativo' WHERE id_profissional = {$p['cadastro']}");
bo_professor_atualizar_dados($db, $ADMIN, $p['cadastro'], [
    'especialidade' => '  Treino   funcional ', 'documento' => 'CREF 000123-G/SP', 'descricao' => 'Turmas da manhã.', 'cadastro_status' => 'ativo',
    'modalidades' => [$modalidade, 'Modalidade inexistente'], 'nome' => 'Nome forjado', 'email' => 'forjado@example.test', 'status' => 'inativo', 'disponivel' => '0',
]);
$cadastro = pCadastro($db, $p['cadastro']);
pCheck($cadastro['especialidade'] === 'Treino funcional' && $cadastro['registro_profissional'] === 'CREF 000123-G/SP' && $cadastro['descricao'] === 'Turmas da manhã.'
    && $cadastro['modalidades'] === $modalidade, 'Dados profissionais gravados; só modalidades cadastradas');
pCheck($cadastro['nome'] === $nomeCadastro && $cadastro['email'] === $edicao['email'] && $cadastro['status'] === 'inativo' && (int) $cadastro['disponivel_agendamento'] === 1,
    'Nome, e-mail, status legado do cadastro e disponibilidade não mudam por esse formulário');
pCheck($cadastro['foto'] === null, 'Sem upload, a foto não muda');
$db->query("UPDATE cadastro_profissional SET status = 'ativo' WHERE id_profissional = {$p['cadastro']}");
bo_professor_atualizar_dados($db, $ADMIN, $p['cadastro'], ['especialidade' => 'Treino funcional', 'cadastro_status' => 'ativo'], BASE_URL . 'assets/img/uploads/profissionais/teste.webp');
pCheck(pCadastro($db, $p['cadastro'])['foto'] === BASE_URL . 'assets/img/uploads/profissionais/teste.webp' && pListado($db, $p['cadastro'])['foto'] === BASE_URL . 'assets/img/uploads/profissionais/teste.webp', 'Upload novo troca a foto exibida');
$cadastroAntesStatus = pCadastro($db, $p['cadastro']);
bo_professor_atualizar_dados($db, $ADMIN, $p['cadastro'], [
    'especialidade' => $cadastroAntesStatus['especialidade'],
    'documento' => $cadastroAntesStatus['registro_profissional'],
    'descricao' => $cadastroAntesStatus['descricao'],
    'modalidades' => explode(', ', (string) $cadastroAntesStatus['modalidades']),
    'cadastro_status' => 'pendente',
]);
pCheck(pCadastro($db, $p['cadastro'])['status'] === 'ativo', 'Tentativa de alterar o status legado pelo formulário: ignorada');
pRecusa(static fn() => bo_professor_atualizar_dados($db, $ADMIN, $p['cadastro'], ['cadastro_status' => 'ativo', 'especialidade' => str_repeat('a', 101)]), '100 caracteres', 'Especialidade longa: recusada');
pRecusa(static fn() => bo_professor_atualizar_dados($db, $ADMIN, $cadastroVendedor, ['cadastro_status' => 'ativo']), 'Professor não encontrado', 'Dados de cadastro que não é de professor: recusados');
// Cadastro inativo: sai da agenda e a catraca bloqueia (regra existente), sem mexer na conta.
$db->query("UPDATE cadastro_profissional SET status = 'inativo' WHERE id_profissional = {$p['cadastro']}");
pCheck(!pAgendavel($db, $p['cadastro']) && pListado($db, $p['cadastro'])['agenda']['motivo'] === 'cadastro_inativo' && !pCatraca($db, $p['usuario'])['liberado']
    && pConta($db, $p['usuario'])['status'] === 'ativo', 'Cadastro inativo: fora da agenda e da catraca, conta intacta');
$db->query("UPDATE cadastro_profissional SET status = 'ativo' WHERE id_profissional = {$p['cadastro']}");
pCheck(pAgendavel($db, $p['cadastro']), 'Cadastro reativado: volta à agenda');
$editados = pContar($db, "SELECT COUNT(*) FROM auditoria_eventos WHERE categoria = 'professor' AND acao = 'editar_cadastro' AND id_alvo = {$p['usuario']}");
pCheck($editados === 2, "Auditoria das edições efetivas de dados profissionais ($editados)");
$eventoTexto = $db->query("SELECT detalhes FROM auditoria_eventos WHERE categoria = 'professor' AND id_alvo = {$p['usuario']}")->fetch_all();
pCheck(!array_filter($eventoTexto, static fn(array $l): bool => str_contains((string) $l[0], 'Turmas da manhã') || str_contains((string) $l[0], 'teste.webp')), 'Auditoria não copia descrição nem foto');
pCheck(pContar($db, "SELECT COUNT(*) FROM auditoria_eventos WHERE categoria = 'professor'") > $auditoriaAntes, 'Eventos de professor registrados');

echo "Professores: $checks verificações passaram.\n";
