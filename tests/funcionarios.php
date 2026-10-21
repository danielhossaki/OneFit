<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Regras da tela Funcionários (pages/dashboard/includes/funcionarios.php) contra o
// banco descartável onefit_interface_test_*, já migrado por
// tests/funcionarios-preparar.ps1. Nenhum e-mail é enviado: o envio é trocado por
// um registrador e as variáveis SMTP são zeradas como segunda proteção.
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/../pages/dashboard/includes/funcionarios.php';
onefitEnv('APP_URL'); // carrega o .env agora para poder neutralizar o SMTP em seguida
foreach (['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'] as $variavel) {
    putenv($variavel . '=');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
if (!bo_funcionarios_migracao($db)['ok']) {
    throw new RuntimeException('Rode antes tests/funcionarios-preparar.ps1 (migração no banco de teste).');
}

$checks = 0;
function fCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
/** Exige uma recusa de regra de negócio (opcionalmente com um trecho da mensagem). */
function fRecusa(callable $acao, string $trecho, string $message): void
{
    try {
        $acao();
    } catch (FuncionarioRecusado $recusa) {
        fCheck($trecho === '' || str_contains($recusa->getMessage(), $trecho), $message . ' (mensagem: ' . $recusa->getMessage() . ')');
        return;
    }
    fCheck(false, $message . ' (nenhuma recusa)');
}
function fContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function fCpf(int $n): string
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
function fUsuario(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE id_usuario = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

$run = random_int(100000, 899999);
$seq = 0;
$entrada = static function (string $cargo, array $extra = []) use ($run, &$seq): array {
    $seq++;
    return $extra + [
        'nome' => "Funcionária Teste $run $seq", 'email' => "func{$run}x{$seq}@example.test", 'cpf' => fCpf($run * 100 + $seq),
        'celular' => '11987654321', 'nascimento' => '1990-05-10', 'genero' => 'outro', 'nacionalidade' => 'BR',
        'endereco' => 'Rua de Teste, 10', 'cidade' => 'São Paulo', 'estado' => 'SP', 'cargo' => $cargo,
    ];
};
$enviados = [];
$enviar = static function (string $email, string $nome, array $tokens) use (&$enviados): bool {
    $enviados[] = ['email' => $email, 'tokens' => $tokens];
    return true;
};
$ADMIN = 1;
$auditoriaAntes = fContar($db, 'SELECT COUNT(*) FROM auditoria_eventos');

// --- Validação pura -----------------------------------------------------------
fCheck(bo_enum_valores("enum('aluno','profissional','admin')") === ['aluno', 'profissional', 'admin'], 'Leitura de ENUM');
fCheck(bo_enum_valores("varchar(20)") === [], 'Coluna que não é ENUM');
fCheck(bo_cpf_valido(fCpf(123456789)) && !bo_cpf_valido('12345678900') && !bo_cpf_valido('11111111111') && !bo_cpf_valido('123'), 'Validação de CPF');
$campos = ['nome', 'email', 'cpf', 'celular', 'nascimento', 'genero', 'nacionalidade', 'endereco', 'cidade', 'estado', 'cargo'];
foreach ($campos as $campo) {
    fRecusa(static fn() => bo_funcionario_dados([$campo => ''] + $entrada('vendedor')), 'Preencha', "Campo obrigatório: $campo");
}
$invalidos = [
    'email' => ['nao-e-email', 'Informe um e-mail válido.'],
    'cpf' => ['12345678900', 'CPF'],
    'celular' => ['1234', 'celular'],
    'nascimento' => [(new DateTime('today'))->modify('-10 years')->format('Y-m-d'), 'nascimento'],
    'genero' => ['x', 'gênero'],
    'nacionalidade' => ['ZZ', 'nacionalidade'],
    'estado' => ['XX', 'UF'],
    'nome' => ['Al', 'nome completo'],
];
foreach ($invalidos as $campo => [$valor, $trecho]) {
    fRecusa(static fn() => bo_funcionario_dados([$campo => $valor] + $entrada('vendedor')), $trecho, "Valor inválido recusado: $campo");
}

// --- Cadastro de cada cargo permitido ----------------------------------------
$criados = [];
$enviados = [];
foreach (['vendedor', 'recepcionista', 'faxineira'] as $cargo) {
    $dados = $entrada($cargo);
    $r = bo_funcionario_criar($db, $ADMIN, $dados, $enviar);
    $conta = fUsuario($db, $r['id']);
    fCheck($conta['tipo_usuario'] === $cargo && $conta['status'] === 'ativo' && (int) $conta['email_verificado'] === 0, "Cadastro de $cargo gravado com o cargo certo");
    fCheck($conta['email'] === $dados['email'] && $conta['cpf'] === $dados['cpf'] && $conta['cidade_estado'] === 'São Paulo/SP', "Dados de $cargo gravados");
    fCheck(password_get_info($conta['senha'])['algo'] !== null && !password_verify('', $conta['senha'])
        && !password_verify($dados['cpf'], $conta['senha']) && !password_verify($dados['email'], $conta['senha']) && !password_verify('123456', $conta['senha']), "Senha de $cargo aleatória e desconhecida");
    $criados[$cargo] = $r['id'];
    $esperado = $cargo === 'faxineira' ? 'sem_acesso' : 'enviado';
    fCheck($r['acesso'] === $esperado, "Acesso de $cargo: $esperado");
}
fCheck(count($enviados) === 2 && array_column($enviados, 'email') === [fUsuario($db, $criados['vendedor'])['email'], fUsuario($db, $criados['recepcionista'])['email']], 'Vendedor e recepcionista (com painel) recebem link; faxineira não');
fCheck(preg_match('/^[a-f0-9]{64}$/', $enviados[0]['tokens']['senha']) && preg_match('/^[a-f0-9]{64}$/', $enviados[0]['tokens']['verificacao']), 'Links de senha e de confirmação de e-mail gerados');
fCheck(fContar($db, 'SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = ' . $criados['faxineira']) === 0, 'faxineira sem link de acesso');
fCheck(fContar($db, 'SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = ' . $criados['recepcionista']) === 1, 'recepcionista com um link de acesso');
fCheck(onefitPainelDoPapel(fUsuario($db, $criados['faxineira'])['tipo_usuario']) === null, 'Faxineira sem acesso ao login pela política');
$tokenSenhaVendedor = hash('sha256', $enviados[0]['tokens']['senha']);
fCheck(fContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = {$criados['vendedor']} AND usado_em IS NULL AND token_hash = '$tokenSenhaVendedor'") === 1, 'Token guardado só como hash');

// Professor com cadastro profissional novo.
$enviados = [];
$dadosProf = $entrada('professor', ['cadastro_modo' => 'novo', 'especialidade' => 'Musculação']);
$r = bo_funcionario_criar($db, $ADMIN, $dadosProf, $enviar);
$criados['professor'] = $r['id'];
$cadastro = $db->query('SELECT * FROM cadastro_profissional WHERE id_usuario = ' . $r['id'])->fetch_all(MYSQLI_ASSOC);
fCheck(fUsuario($db, $r['id'])['tipo_usuario'] === 'professor' && $r['acesso'] === 'enviado', 'Professor gravado como professor (não como profissional)');
fCheck(count($cadastro) === 1 && $cadastro[0]['status'] === 'ativo' && $cadastro[0]['email'] === $dadosProf['email'] && $cadastro[0]['especialidade'] === 'Musculação', 'Professor ganhou um cadastro profissional novo vinculado');

// Nenhum funcionário ganha matrícula.
fCheck(fContar($db, 'SELECT COUNT(*) FROM matricula WHERE id_usuario IN (' . implode(',', $criados) . ')') === 0, 'Funcionários sem matrícula');

// --- Cargos inválidos e manipulação do cargo ---------------------------------
$totalUsuarios = fContar($db, 'SELECT COUNT(*) FROM usuarios');
foreach (['admin', 'diretor', 'aluno', 'profissional', '', 'superadmin', 'Admin', 'Diretor', 'VENDEDOR', "vendedor'", 'vendedor;admin'] as $cargoInvalido) {
    fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada($cargoInvalido), $enviar), $cargoInvalido === '' ? 'Preencha' : 'cargo permitido', 'Cargo recusado: ' . var_export($cargoInvalido, true));
}
fCheck(fContar($db, 'SELECT COUNT(*) FROM usuarios') === $totalUsuarios, 'Nenhuma conta criada com cargo inválido');
$extra = bo_funcionario_criar($db, $ADMIN, $entrada('vendedor', ['tipo_usuario' => 'admin', 'status' => 'bloqueado', 'id' => 1]), $enviar);
$contaExtra = fUsuario($db, $extra['id']);
fCheck($contaExtra['tipo_usuario'] === 'vendedor' && $contaExtra['status'] === 'ativo', 'Campos extras do formulário (tipo_usuario/status/id) são ignorados');

// --- E-mail e CPF duplicados -------------------------------------------------
$base = fUsuario($db, $criados['vendedor']);
$totalUsuarios = fContar($db, 'SELECT COUNT(*) FROM usuarios');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('vendedor', ['email' => $base['email']]), $enviar), 'Já existe', 'E-mail duplicado');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('vendedor', ['email' => strtoupper($base['email'])]), $enviar), 'Já existe', 'E-mail duplicado com maiúsculas');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('vendedor', ['cpf' => $base['cpf']]), $enviar), 'Já existe', 'CPF duplicado');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('faxineira', ['email' => 'fixture3@example.test']), $enviar), 'Já existe', 'E-mail de aluno existente não é reaproveitado');
fCheck(fContar($db, 'SELECT COUNT(*) FROM usuarios') === $totalUsuarios && fUsuario($db, 3)['tipo_usuario'] === 'aluno', 'Duplicados não criam nem alteram contas');

// --- Vínculo com cadastro_profissional ----------------------------------------
$emailLivre = "livre{$run}@example.test";
$db->query("INSERT INTO cadastro_profissional (nome, email, especialidade, registro_profissional, status) VALUES ('Cadastro Livre $run', '$emailLivre', 'Pilates', 'CREF-$run', 'inativo')");
$idLivre = (int) $db->insert_id;
$livreAntes = $db->query("SELECT * FROM cadastro_profissional WHERE id_profissional = $idLivre")->fetch_assoc();
// Mesmo e-mail, modo "novo": não vincula sozinho e nada é criado.
$totalUsuarios = fContar($db, 'SELECT COUNT(*) FROM usuarios');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['email' => $emailLivre, 'cadastro_modo' => 'novo']), $enviar), 'Vincular cadastro existente', 'Coincidência de e-mail não vincula automaticamente');
fCheck(fContar($db, 'SELECT COUNT(*) FROM usuarios') === $totalUsuarios && $db->query("SELECT id_usuario FROM cadastro_profissional WHERE id_profissional = $idLivre")->fetch_row()[0] === null, 'Recusa desfaz a conta e não toca no cadastro');
// Vínculo explícito de um cadastro livre (e-mail diferente da conta).
$r = bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['cadastro_modo' => 'existente', 'id_profissional' => (string) $idLivre]), $enviar);
$livreDepois = $db->query("SELECT * FROM cadastro_profissional WHERE id_profissional = $idLivre")->fetch_assoc();
fCheck((int) $livreDepois['id_usuario'] === $r['id'], 'Vínculo explícito ao cadastro escolhido');
unset($livreAntes['id_usuario'], $livreDepois['id_usuario']);
fCheck($livreAntes === $livreDepois, 'Dados do cadastro profissional preservados (só id_usuario muda)');
fCheck(onefitSituacaoCatracaFuncionario('professor', 'ativo', $livreDepois['status'] === 'ativo') === ['liberado' => false, 'motivo' => 'cadastro_profissional_inativo'], 'Cadastro inativo bloqueia a catraca do professor');
// Cadastro já vinculado a outra conta (professor 4 do banco de teste).
$idDoQuatro = (int) $db->query('SELECT id_profissional FROM cadastro_profissional WHERE id_usuario = 4 LIMIT 1')->fetch_row()[0];
$totalUsuarios = fContar($db, 'SELECT COUNT(*) FROM usuarios');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['cadastro_modo' => 'existente', 'id_profissional' => (string) $idDoQuatro]), $enviar), 'já está vinculado', 'Cadastro de outro professor não é tomado');
fCheck((int) $db->query("SELECT id_usuario FROM cadastro_profissional WHERE id_profissional = $idDoQuatro")->fetch_row()[0] === 4 && fContar($db, 'SELECT COUNT(*) FROM usuarios') === $totalUsuarios, 'Cadastro do professor 4 intacto');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['cadastro_modo' => 'existente', 'id_profissional' => '999999']), $enviar), 'não encontrado', 'Cadastro inexistente');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['cadastro_modo' => 'existente']), $enviar), 'Selecione o cadastro', 'Modo existente sem cadastro escolhido');
fRecusa(static fn() => bo_funcionario_criar($db, $ADMIN, $entrada('professor', ['cadastro_modo' => 'outro']), $enviar), 'vincular', 'Modo de vínculo inválido');

// Conta legada "profissional" sem cadastro: vínculo depois, pela ação própria.
$cpfLegado = fCpf($run * 100 + 90);
$db->query("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
            VALUES ('Legado $run', 'x', 'BR', '1985-01-01', 'outro', '$cpfLegado', 'Rua Velha, 1', 'Campinas/SP', 'legado{$run}@example.test', 1, '11987654320', 'profissional', 'ativo')");
$idLegado = (int) $db->insert_id;
$db->query("INSERT INTO cadastro_profissional (nome, email, status) VALUES ('Livre 2 $run', 'livre2{$run}@example.test', 'ativo')");
$idLivre2 = (int) $db->insert_id;
bo_funcionario_vincular_cadastro($db, $ADMIN, $idLegado, ['cadastro_modo' => 'existente', 'id_profissional' => (string) $idLivre2]);
fCheck((int) $db->query("SELECT id_usuario FROM cadastro_profissional WHERE id_profissional = $idLivre2")->fetch_row()[0] === $idLegado, 'Professor legado vinculado explicitamente');
fRecusa(static fn() => bo_funcionario_vincular_cadastro($db, $ADMIN, $idLegado, ['cadastro_modo' => 'novo']), 'já tem', 'Segundo cadastro para o mesmo professor recusado');
fRecusa(static fn() => bo_funcionario_vincular_cadastro($db, $ADMIN, $criados['vendedor'], ['cadastro_modo' => 'novo']), 'Só professores', 'Vendedor não recebe cadastro profissional');

// --- Edição ------------------------------------------------------------------
$idVend = $criados['vendedor'];
$atual = fUsuario($db, $idVend);
$formulario = static fn(array $conta, array $mudancas = []): array => $mudancas + [
    'nome' => $conta['nome'], 'email' => $conta['email'], 'cpf' => $conta['cpf'], 'celular' => $conta['celular'],
    'nascimento' => $conta['data_nascimento'], 'genero' => $conta['genero'], 'nacionalidade' => $conta['nacionalidade'],
    'endereco' => $conta['endereco'], 'cidade' => explode('/', $conta['cidade_estado'])[0], 'estado' => explode('/', $conta['cidade_estado'])[1],
    'cargo' => onefitPapelCanonico($conta['tipo_usuario']),
];
$r = bo_funcionario_atualizar($db, $ADMIN, $idVend, $formulario($atual, ['nome' => 'Nome Editado ' . $run, 'celular' => '1133334444']));
$editado = fUsuario($db, $idVend);
fCheck($editado['nome'] === 'Nome Editado ' . $run && $editado['celular'] === '1133334444' && !$r['email_alterado'] && $editado['senha'] === $atual['senha'], 'Edição de dados sem tocar na senha');
fCheck(fContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = $idVend AND usado_em IS NULL") === 1, 'Link pendente mantido quando o e-mail não muda');
$r = bo_funcionario_atualizar($db, $ADMIN, $idVend, $formulario($editado, ['email' => "novo{$run}@example.test"]));
fCheck($r['email_alterado'] && (int) fUsuario($db, $idVend)['email_verificado'] === 0, 'Novo e-mail exige nova confirmação');
fCheck(fContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = $idVend AND usado_em IS NULL") === 0
    && fContar($db, "SELECT COUNT(*) FROM verificacao_email_tokens WHERE usuario_id = $idVend AND usado_em IS NULL") === 0, 'Links enviados ao e-mail antigo cancelados');
fRecusa(static fn() => bo_funcionario_atualizar($db, $ADMIN, $idVend, $formulario(fUsuario($db, $idVend), ['email' => 'fixture3@example.test'])), 'Já existe', 'Edição não toma e-mail de outra conta');
foreach (['admin', 'diretor', 'aluno', 'profissional'] as $cargoProibido) {
    fRecusa(static fn() => bo_funcionario_atualizar($db, $ADMIN, $idVend, $formulario(fUsuario($db, $idVend), ['cargo' => $cargoProibido])), 'cargo permitido', "Promoção a $cargoProibido recusada");
}
fCheck(fUsuario($db, $idVend)['tipo_usuario'] === 'vendedor', 'Cargo do vendedor intacto após tentativas');
bo_funcionario_atualizar($db, $ADMIN, $idVend, $formulario(fUsuario($db, $idVend), ['cargo' => 'faxineira']));
fCheck(fUsuario($db, $idVend)['tipo_usuario'] === 'faxineira', 'Troca de cargo entre funcionários');
bo_funcionario_atualizar($db, $ADMIN, $idVend, $formulario(fUsuario($db, $idVend), ['cargo' => 'vendedor']));
bo_funcionario_atualizar($db, $ADMIN, $idLegado, $formulario(fUsuario($db, $idLegado), ['nome' => 'Legado Editado ' . $run]));
fCheck(fUsuario($db, $idLegado)['tipo_usuario'] === 'profissional', 'Conta legada continua profissional (sem migração escondida)');
// Quem vira professor precisa de cadastro: cria um novo vinculado.
bo_funcionario_atualizar($db, $ADMIN, $criados['recepcionista'], $formulario(fUsuario($db, $criados['recepcionista']), ['cargo' => 'professor', 'cadastro_modo' => 'novo']));
fCheck(fContar($db, 'SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario = ' . $criados['recepcionista']) === 1, 'Novo professor ganhou cadastro profissional');

// --- Situação (ativar/inativar) ----------------------------------------------
$idProf = $criados['professor'];
fCheck(fContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = $idProf AND usado_em IS NULL") === 1, 'Professor tem link pendente');
bo_funcionario_alterar_status($db, $ADMIN, $idProf, 'inativo');
fCheck(fUsuario($db, $idProf)['status'] === 'inativo' && fContar($db, "SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = $idProf AND usado_em IS NULL") === 0, 'Inativar cancela links pendentes');
fRecusa(static fn() => bo_funcionario_reenviar_acesso($db, $ADMIN, $idProf, $enviar), 'Ative a conta', 'Inativo não recebe link');
bo_funcionario_alterar_status($db, $ADMIN, $idProf, 'ativo');
fCheck(fUsuario($db, $idProf)['status'] === 'ativo', 'Reativação');
foreach (['bloqueado', 'ATIVO', '', 'excluido'] as $statusInvalido) {
    fRecusa(static fn() => bo_funcionario_alterar_status($db, $ADMIN, $idProf, $statusInvalido), 'Situação inválida', "Situação $statusInvalido recusada");
}

// --- Reenvio do link de acesso -----------------------------------------------
$enviados = [];
fCheck(bo_funcionario_reenviar_acesso($db, $ADMIN, $idProf, $enviar) === 'enviado' && count($enviados) === 1, 'Reenvio para professor ativo');
fCheck(bo_funcionario_reenviar_acesso($db, $ADMIN, $idProf, static fn(): bool => false) === 'falha_envio', 'Falha de envio informada');
fRecusa(static fn() => bo_funcionario_reenviar_acesso($db, $ADMIN, $criados['faxineira'], $enviar), 'não tem acesso ao painel', 'Faxineira nunca recebe link de acesso');

// --- Contas protegidas e operadores sem permissão -----------------------------
$admin2 = fUsuario($db, 2);
fRecusa(static fn() => bo_funcionario_atualizar($db, $ADMIN, 2, $formulario($admin2, ['cargo' => 'vendedor'])), 'por um Diretor', 'Conta admin não é editada (nem rebaixada) por admin');
fRecusa(static fn() => bo_funcionario_alterar_status($db, $ADMIN, 2, 'inativo'), 'por um Diretor', 'Conta admin não é inativada por admin');
fRecusa(static fn() => bo_funcionario_reenviar_acesso($db, $ADMIN, 2, $enviar), 'por um Diretor', 'Acesso de admin não é gerenciado por admin');
fRecusa(static fn() => bo_funcionario_alterar_status($db, $ADMIN, $ADMIN, 'inativo'), 'própria conta', 'Operador não altera a própria conta');
fRecusa(static fn() => bo_funcionario_alterar_status($db, $ADMIN, 3, 'inativo'), 'não é de um funcionário', 'Aluno não é gerenciado nesta tela');
fRecusa(static fn() => bo_funcionario_alterar_status($db, $ADMIN, 999999, 'inativo'), 'não encontrado', 'Conta inexistente');
fCheck(fUsuario($db, 2) === $admin2 && fUsuario($db, 3)['status'] === 'ativo', 'Contas protegidas intactas');
foreach ([3 => 'aluno', 4 => 'profissional', 5 => 'vendedor', 6 => 'admin inativo', 999999 => 'inexistente'] as $operador => $rotulo) {
    fRecusa(static fn() => bo_funcionario_criar($db, $operador, $entrada('vendedor'), $enviar), 'permissão', "Operador $rotulo não cadastra");
    fRecusa(static fn() => bo_funcionario_alterar_status($db, $operador, $idProf, 'inativo'), 'permissão', "Operador $rotulo não altera situação");
}
fCheck(fUsuario($db, $idProf)['status'] === 'ativo', 'Operadores sem permissão não alteraram nada');

// --- Auditoria ----------------------------------------------------------------
$eventos = $db->query("SELECT acao, id_operador, id_alvo, detalhes FROM auditoria_eventos WHERE id_evento > 0 ORDER BY id_evento")->fetch_all(MYSQLI_ASSOC);
fCheck(count($eventos) > $auditoriaAntes, 'Operações registradas na auditoria');
$acoes = array_count_values(array_column($eventos, 'acao'));
fCheck(isset($acoes['criar'], $acoes['editar'], $acoes['status'], $acoes['reenviar_acesso'], $acoes['vincular_cadastro']), 'Auditoria cobre criar, editar, status, reenvio e vínculo');
$textoAuditoria = implode("\n", array_column($eventos, 'detalhes'));
fCheck(!str_contains($textoAuditoria, '$2y$') && !preg_match('/[a-f0-9]{64}/', $textoAuditoria), 'Auditoria sem hash de senha nem token');
fCheck(!str_contains($textoAuditoria, $base['cpf']) && str_contains($textoAuditoria, '***.***.***-'), 'CPF mascarado na auditoria');

// --- Falha de banco e migração ausente (nega por padrão) ----------------------
$fechada = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$fechada->close();
$totalUsuarios = fContar($db, 'SELECT COUNT(*) FROM usuarios');
try {
    bo_funcionario_criar($fechada, $ADMIN, $entrada('vendedor'), $enviar);
    fCheck(false, 'Conexão fechada deveria falhar');
} catch (Throwable $erro) {
    fCheck(!($erro instanceof FuncionarioRecusado), 'Falha de banco não vira sucesso');
}
fCheck(fContar($db, 'SELECT COUNT(*) FROM usuarios') === $totalUsuarios, 'Falha de banco não grava nada');

$semMigracao = 'onefit_interface_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$db->query("CREATE DATABASE `$semMigracao` CHARACTER SET utf8mb4");
try {
    $velho = new mysqli('127.0.0.1', 'root', '', $semMigracao);
    $velho->query("CREATE TABLE usuarios (id_usuario INT AUTO_INCREMENT PRIMARY KEY, tipo_usuario ENUM('aluno','profissional','admin') DEFAULT 'aluno', status ENUM('ativo','inativo','bloqueado') DEFAULT 'ativo') ENGINE=InnoDB");
    $velho->query("INSERT INTO usuarios (id_usuario, tipo_usuario, status) VALUES (1, 'admin', 'ativo')");
    $estado = bo_funcionarios_migracao($velho);
    fCheck(!$estado['ok'] && in_array('auditoria_eventos', $estado['faltando'], true) && in_array('usuarios.tipo_usuario: faxineira', $estado['faltando'], true), 'Migração ausente detectada');
    fCheck(in_array('usuarios.tipo_usuario: vendedor', $estado['faltando'], true), 'ENUM sem vendedor (como o banco remoto) também bloqueia a tela');
    fRecusa(static fn() => bo_funcionario_criar($velho, 1, $entrada('vendedor'), $enviar), 'migração', 'Sem migração, nada é gravado');
    fCheck(fContar($velho, 'SELECT COUNT(*) FROM usuarios') === 1, 'Banco sem migração continua intacto');
    $velho->close();
} finally {
    $db->query("DROP DATABASE `$semMigracao`");
}

echo "Funcionários: $checks verificações passaram.\n";
