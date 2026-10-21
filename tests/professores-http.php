<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Tela Professores e agenda do aluno via HTTP real, na cópia sintética ligada ao banco
// descartável (tests/papeis-http.ps1 -Script professores-http.php, depois de
// tests/funcionarios-preparar.ps1). A cópia não tem SMTP: nenhum e-mail sai.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/http-navegador.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
if ((int) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cadastro_profissional' AND COLUMN_NAME = 'disponivel_agendamento'")->fetch_row()[0] !== 1) {
    throw new RuntimeException('Rode antes tests/funcionarios-preparar.ps1 (migração de professores no banco de teste).');
}

$checks = 0;
function phCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function phContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function phCpf(int $n): string
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
/** Mensagem flash do painel (primeiro aviso dentro de <main>). */
function phFlash(string $html): string
{
    return preg_match('#<main class="bo-main">\s*<div class="bo-notice(?: [^"]*)?"[^>]*>\s*<i class="bi [a-z-]+"></i>\s*<div><span>(.*?)</span>#s', $html, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
}
/** HTML da seção do admin "profissionais" (tela Professores). */
function phSecaoProfessores(string $html): string
{
    return preg_match('#<section class="bo-content-section" data-perfil="admin" data-section="profissionais">(.*?)</section>#s', $html, $m) ? $m[1] : '';
}
/** Horários que a agenda do aluno entrega ao navegador para o dia selecionado. */
function phSlots(Navegador $nav, string $data): array
{
    $pagina = $nav->req('GET', 'pages/dashboard/dashboard.php?section=agenda&mes=' . substr($data, 0, 7) . '&dia=' . $data);
    if (!preg_match('#<script type="application/json" data-agenda-slots>\s*(.*?)\s*</script>#s', $pagina['body'], $m)) {
        return [];
    }
    return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
}
function phIdsSlots(array $slots): array
{
    return array_values(array_unique(array_map(static fn(array $s): int => (int) $s['id_profissional'], $slots)));
}
/** Busca de professores do cabeçalho (BO_PROFISSIONAIS_SEARCH). */
function phBusca(string $html): array
{
    return preg_match('#const BO_PROFISSIONAIS_SEARCH = (\[.*?\]);#s', $html, $m) ? array_column(json_decode($m[1], true, 512, JSON_THROW_ON_ERROR), 'nome') : [];
}
function phDisponivel(mysqli $db, int $idProfissional): int
{
    return (int) $db->query("SELECT disponivel_agendamento FROM cadastro_profissional WHERE id_profissional = $idProfissional")->fetch_row()[0];
}

$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
// Contas operadoras com senha conhecida (só neste banco descartável).
$criar = static function (string $cargo) use ($db, $run, &$seq, $senha): array {
    $seq++;
    $email = "ph{$run}x{$seq}@example.test";
    $nome = "Professores Http $run $seq";
    $cpf = phCpf($run * 100 + 70 + $seq);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 9', 'Santos/SP', ?, 1, '11900000009', ?, 'ativo')");
    $stmt->bind_param('sssss', $nome, $senha, $cpf, $email, $cargo);
    $stmt->execute();
    return ['id' => (int) $stmt->insert_id, 'email' => $email, 'nome' => $nome];
};
$formulario = static function (string $cargo, array $extra = []) use ($run, &$seq): array {
    $seq++;
    return $extra + [
        'acao' => 'create', 'secao' => 'funcionarios', 'nome' => "Professor Http $run $seq", 'email' => "profhttp{$run}x{$seq}@example.test",
        'cpf' => phCpf($run * 100 + 70 + $seq), 'celular' => '(11) 98765-4321', 'nascimento' => '1991-02-03', 'genero' => 'masculino',
        'nacionalidade' => 'BR', 'endereco' => 'Rua Http, 9', 'cidade' => 'Santos', 'estado' => 'SP', 'cargo' => $cargo,
    ];
};
$urlFuncionarios = 'pages/dashboard/funcionalidades/funcionarios.php';
$urlProfessores = 'pages/dashboard/funcionalidades/profissionais.php';
$urlAgenda = 'pages/dashboard/funcionalidades/agenda.php';
// Dois dias futuros com horários simulados (domingo não tem); as horas vêm da própria agenda.
$dia = new DateTimeImmutable('tomorrow', new DateTimeZone('America/Sao_Paulo'));
$dias = [];
while (count($dias) < 2) {
    if ((int) $dia->format('w') !== 0) {
        $dias[] = $dia->format('Y-m-d');
    }
    $dia = $dia->modify('+1 day');
}
[$data, $data2] = $dias;
$chaveSimulada = static fn(string $dataSlot, int $idProfissional, int $hora): string => "sim:$dataSlot:$idProfissional:0:$hora";

// --- 1. Tela Professores: sem cadastro independente --------------------------------------
$admin = new Navegador($base);
phCheck(str_contains($admin->entrar('fixture1@example.test')['location'], 'pages/dashboard/dashboard.php'), 'Admin entra');
$painel = $admin->req('GET', 'pages/dashboard/dashboard.php?section=profissionais')['body'];
$secao = phSecaoProfessores($painel);
phCheck($secao !== '' && str_contains($secao, '<h1>Professores</h1>'), 'Seção "profissionais" com o título Professores');
// (O catálogo de traduções embutido na página ainda traz a chave antiga; o que importa é a tela.)
phCheck(!str_contains($painel, 'modalProfissionalNovo') && !str_contains($secao, 'Novo Profissional') && !str_contains($secao, 'Novo profissional'), 'Sem o botão "Novo Profissional"');
phCheck(str_contains($secao, 'section=funcionarios') && str_contains($secao, 'data-bo-table="profissionais"')
    && str_contains($secao, 'data-bo-filter="search" data-bo-target="profissionais"') && str_contains($secao, 'data-bo-filter="type" data-bo-target="profissionais"')
    && str_contains($secao, 'data-bo-filter="status" data-bo-target="profissionais"'), 'Busca, filtros de disponibilidade e de conta, e link para Funcionários');
phCheck(!str_contains($secao, 'Migração de disponibilidade pendente'), 'Sem aviso de migração pendente');
$js = $admin->req('GET', 'assets/js/dashboard.js')['body'];
phCheck(str_contains($js, "{ key: 'profissionais', label: ofT('Professores'), icon: 'bi-person-badge', grupo: 'equipe' }"), 'Menu: Professores, mesma chave de rota');
$token = $admin->csrf();

// --- 2. Professor cadastrado em Funcionários aparece em Professores -------------------------
$professores = [];
foreach (['a', 'b'] as $chave) {
    $form = $formulario('professor', ['cadastro_modo' => 'novo', 'especialidade' => 'Pilates ' . strtoupper($chave)]);
    $r = $admin->req('POST', $urlFuncionarios, $form + ['csrf_token' => $token]);
    $stmt = $db->prepare('SELECT u.id_usuario, cp.id_profissional, cp.disponivel_agendamento, cp.status FROM usuarios u JOIN cadastro_profissional cp ON cp.id_usuario = u.id_usuario WHERE u.email = ?');
    $stmt->bind_param('s', $form['email']);
    $stmt->execute();
    $linhas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    phCheck($r['status'] === 302 && count($linhas) === 1 && (int) $linhas[0]['disponivel_agendamento'] === 1 && $linhas[0]['status'] === 'ativo', "Professor $chave cadastrado com um cadastro profissional disponível");
    $professores[$chave] = ['usuario' => (int) $linhas[0]['id_usuario'], 'cadastro' => (int) $linhas[0]['id_profissional'], 'nome' => $form['nome'], 'email' => $form['email'], 'form' => $form];
}
$vendedorForm = $formulario('vendedor');
$admin->req('POST', $urlFuncionarios, $vendedorForm + ['csrf_token' => $token]);
$secao = phSecaoProfessores($admin->req('GET', 'pages/dashboard/dashboard.php?section=profissionais')['body']);
foreach ($professores as $chave => $p) {
    phCheck(str_contains($secao, $p['nome']) && str_contains($secao, 'name="id" value="' . $p['cadastro'] . '"')
        && str_contains($secao, '#' . str_pad((string) $p['usuario'], 4, '0', STR_PAD_LEFT)), "Professor $chave listado com o ID da conta e o controle de disponibilidade");
}
phCheck(!str_contains($secao, $vendedorForm['nome']), 'Vendedor fora da tela Professores');
phCheck(substr_count($secao, 'role="switch" aria-checked="true"') >= 2, 'Interruptores ligados para os disponíveis');

// --- 3. Rotas antigas de cadastro e exclusão --------------------------------------------------
$antes = phContar($db, 'SELECT COUNT(*) FROM cadastro_profissional');
$r = $admin->req('POST', $urlProfessores, ['acao' => 'create', 'secao' => 'profissionais', 'nome' => 'Avulso', 'email' => "avulso{$run}@example.test", 'celular' => '11987654321', 'csrf_token' => $token]);
$flash = phFlash($admin->req('GET', 'pages/dashboard/dashboard.php?section=profissionais')['body']);
phCheck($r['status'] === 302 && phContar($db, 'SELECT COUNT(*) FROM cadastro_profissional') === $antes && str_contains($flash, 'tela Funcionários'), 'POST de cadastro avulso recusado: ' . $flash);
$r = $admin->req('POST', $urlProfessores, ['acao' => 'delete', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'csrf_token' => $token]);
phCheck(phContar($db, "SELECT COUNT(*) FROM cadastro_profissional WHERE id_profissional = {$professores['a']['cadastro']}") === 1, 'POST de exclusão recusado; cadastro preservado');
$r = $admin->req('GET', 'pages/dashboard/funcionalidades/confirmar-exclusao.php?recurso=profissionais&id=' . $professores['a']['cadastro'] . '&secao=profissionais');
phCheck($r['status'] === 302, 'Página de confirmação de exclusão não aceita mais professores');
$r = $admin->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['b']['cadastro'], 'disponivel' => '0']);
phCheck(phDisponivel($db, $professores['b']['cadastro']) === 1, 'Sem CSRF: nada gravado');

// --- 6. Admin deixa B indisponível ------------------------------------------------------------
$admin->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['b']['cadastro'], 'disponivel' => '0', 'csrf_token' => $token]);
$pagina = $admin->req('GET', 'pages/dashboard/dashboard.php?section=profissionais')['body'];
phCheck(phDisponivel($db, $professores['b']['cadastro']) === 0 && str_contains(phFlash($pagina), 'indisponível para novos agendamentos'), 'B indisponível, com confirmação');
phCheck(phContar($db, "SELECT COUNT(*) FROM usuarios WHERE id_usuario = {$professores['b']['usuario']} AND status = 'ativo' AND tipo_usuario = 'professor'") === 1, 'Conta e cargo de B intactos');
phCheck(str_contains(phSecaoProfessores($pagina), 'Fora da agenda: indisponível'), 'Tela mostra B fora da agenda');

// --- 7. Agenda do aluno: só professores disponíveis -------------------------------------------
$contaAluno = $criar('aluno');
$aluno = new Navegador($base);
phCheck(str_contains($aluno->entrar($contaAluno['email'])['location'], 'pages/dashboard/dashboard.php'), 'Aluno entra');
$slotsDia = phSlots($aluno, $data);
$ids = phIdsSlots($slotsDia);
// Horas oferecidas no dia (iguais para todo professor nos horários simulados).
$horas = array_values(array_unique(array_map(static fn(array $s): int => (int) substr($s['hora_inicio'], 0, 2),
    array_filter($slotsDia, static fn(array $s): bool => (int) $s['id_profissional'] === $professores['a']['cadastro'] && str_starts_with($s['key'], 'sim:')))));
sort($horas);
phCheck(count($horas) >= 2, 'Agenda do aluno com pelo menos dois horários no dia');
phCheck(in_array($professores['a']['cadastro'], $ids, true) && !in_array($professores['b']['cadastro'], $ids, true), 'Agenda do aluno: A aparece, B (indisponível) não');
$tokenAluno = $aluno->csrf();

// --- 8. Requisição direta com professor indisponível -----------------------------------------
$contarAgendamentos = static fn(int $idProfissional): int => phContar($db, "SELECT COUNT(*) FROM agendamento WHERE id_profissional = $idProfissional");
$reservar = static fn(string $chave, string $dataSlot) => $aluno->req('POST', $urlAgenda, ['secao' => 'agenda', 'data_evento' => $dataSlot, 'slot' => $chave, 'csrf_token' => $tokenAluno]);
$reservar($chaveSimulada($data, $professores['b']['cadastro'], $horas[0]), $data);
$flash = phFlash($aluno->req('GET', 'pages/dashboard/dashboard.php?section=agenda')['body']);
phCheck($contarAgendamentos($professores['b']['cadastro']) === 0 && str_contains($flash, 'não está mais disponível'), 'POST forjado com B indisponível: recusado (' . $flash . ')');
// Horário cadastrado pelo próprio professor (formulário antigo, por id_disponibilidade).
$db->query("INSERT INTO disponibilidade_profissional (id_profissional, modalidade, data_evento, hora_inicio, hora_fim, local, status)
    VALUES ({$professores['b']['cadastro']}, 'Avaliação física', '$data2', '20:00:00', '21:00:00', 'Sala 1', 'disponivel')");
$horarioB = (int) $db->insert_id;
$aluno->req('POST', $urlAgenda, ['secao' => 'agenda', 'id_disponibilidade' => (string) $horarioB, 'csrf_token' => $tokenAluno]);
phCheck($contarAgendamentos($professores['b']['cadastro']) === 0
    && $db->query("SELECT status FROM disponibilidade_profissional WHERE id_disponibilidade = $horarioB")->fetch_row()[0] === 'disponivel', 'Horário cadastrado de B indisponível: recusado, horário intacto');
phCheck(!in_array($professores['b']['cadastro'], phIdsSlots(phSlots($aluno, $data2)), true), 'Horário cadastrado de B fora da agenda');

// --- Disponível de novo: o mesmo pedido agenda ------------------------------------------------
$admin->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['b']['cadastro'], 'disponivel' => '1', 'csrf_token' => $token]);
phCheck(phDisponivel($db, $professores['b']['cadastro']) === 1 && in_array($professores['b']['cadastro'], phIdsSlots(phSlots($aluno, $data)), true), 'B disponível de novo: volta à agenda');
$reservar($chaveSimulada($data, $professores['b']['cadastro'], $horas[0]), $data);
$agendamento = $db->query("SELECT * FROM agendamento WHERE id_profissional = {$professores['b']['cadastro']} AND id_usuario = {$contaAluno['id']}")->fetch_assoc();
phCheck($agendamento && $agendamento['status'] === 'confirmado' && $agendamento['data_evento'] === $data && $agendamento['hora_inicio'] === sprintf('%02d:00:00', $horas[0]), 'Agendamento com B disponível criado');

// --- 9. Agendamento existente preservado quando B fica indisponível --------------------------
$admin->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['b']['cadastro'], 'disponivel' => '0', 'csrf_token' => $token]);
phCheck($db->query("SELECT * FROM agendamento WHERE id_agendamento = {$agendamento['id_agendamento']}")->fetch_assoc() === $agendamento, 'Agendamento de B intacto ao ficar indisponível');
$minhaAgenda = $aluno->req('GET', 'pages/dashboard/dashboard.php?section=agenda')['body'];
phCheck(str_contains($minhaAgenda, 'name="id_agendamento" value="' . $agendamento['id_agendamento'] . '"'), 'Aluno ainda vê o agendamento e pode cancelá-lo');
$aluno->req('POST', $urlAgenda, ['secao' => 'agenda', 'acao' => 'cancelar', 'id_agendamento' => $agendamento['id_agendamento'], 'csrf_token' => $tokenAluno]);
phCheck($db->query("SELECT status FROM agendamento WHERE id_agendamento = {$agendamento['id_agendamento']}")->fetch_row()[0] === 'cancelado', 'Cancelamento pelo aluno segue a regra atual');

// --- 5. Conta inativa sai da agenda, mesmo disponível; reativada volta -----------------------
$admin->req('POST', $urlFuncionarios, ['acao' => 'status', 'secao' => 'funcionarios', 'id' => (string) $professores['a']['usuario'], 'status' => 'inativo', 'csrf_token' => $token]);
phCheck(phDisponivel($db, $professores['a']['cadastro']) === 1 && !in_array($professores['a']['cadastro'], phIdsSlots(phSlots($aluno, $data)), true), 'A inativo: fora da agenda, disponibilidade preservada');
$reservar($chaveSimulada($data, $professores['a']['cadastro'], $horas[1]), $data);
phCheck($contarAgendamentos($professores['a']['cadastro']) === 0, 'POST forjado com conta inativa: recusado');
$secao = phSecaoProfessores($admin->req('GET', 'pages/dashboard/dashboard.php?section=profissionais')['body']);
phCheck(str_contains($secao, 'Fora da agenda: conta inativa'), 'Tela mostra conta inativa separada da disponibilidade');
$busca = phBusca($admin->req('GET', 'pages/dashboard/dashboard.php')['body']);
phCheck(!in_array($professores['a']['nome'], $busca, true), 'Busca do cabeçalho sem o professor inativo');
$admin->req('POST', $urlFuncionarios, ['acao' => 'status', 'secao' => 'funcionarios', 'id' => (string) $professores['a']['usuario'], 'status' => 'ativo', 'csrf_token' => $token]);
phCheck(in_array($professores['a']['cadastro'], phIdsSlots(phSlots($aluno, $data)), true), 'A reativado: volta à agenda');
$busca = phBusca($admin->req('GET', 'pages/dashboard/dashboard.php')['body']);
phCheck(in_array($professores['a']['nome'], $busca, true) && !in_array($vendedorForm['nome'], $busca, true), 'Busca do cabeçalho: professor ativo sim, vendedor não');

// --- Edição em Funcionários sincroniza o cadastro ----------------------------------------------
$novoNome = "Professor Renomeado $run";
$admin->req('POST', $urlFuncionarios, ['acao' => 'update', 'id' => (string) $professores['a']['usuario'], 'nome' => $novoNome] + $professores['a']['form'] + ['csrf_token' => $token]);
phCheck($db->query("SELECT nome FROM cadastro_profissional WHERE id_profissional = {$professores['a']['cadastro']}")->fetch_row()[0] === $novoNome
    && phContar($db, "SELECT COUNT(*) FROM cadastro_profissional WHERE id_usuario = {$professores['a']['usuario']}") === 1, 'Edição em Funcionários atualiza o nome do cadastro, sem duplicar');
$slotsA = array_values(array_filter(phSlots($aluno, $data), static fn(array $s): bool => (int) $s['id_profissional'] === $professores['a']['cadastro']));
phCheck($slotsA !== [] && $slotsA[0]['profissional'] === $novoNome, 'Agenda mostra o nome novo');

// --- Dados profissionais pela tela Professores ---------------------------------------------------
$admin->req('POST', $urlProfessores, ['acao' => 'update', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'especialidade' => 'Funcional',
    'documento' => 'CREF 77', 'descricao' => 'Manhãs', 'cadastro_status' => 'ativo', 'nome' => 'Forjado', 'email' => 'forjado@example.test', 'foto_atual' => 'javascript:alert(1)', 'csrf_token' => $token]);
$cadastroA = $db->query("SELECT * FROM cadastro_profissional WHERE id_profissional = {$professores['a']['cadastro']}")->fetch_assoc();
phCheck($cadastroA['especialidade'] === 'Funcional' && $cadastroA['registro_profissional'] === 'CREF 77' && $cadastroA['nome'] === $novoNome
    && $cadastroA['email'] === $professores['a']['email'] && $cadastroA['foto'] === null, 'Dados profissionais salvos; nome, e-mail e foto não vêm do formulário');

// --- 10. Permissões de cada cargo -----------------------------------------------------------------
$db->query("UPDATE usuarios SET senha = '" . $db->real_escape_string($senha) . "', email_verificado = 1 WHERE id_usuario = {$professores['a']['usuario']}");
$recusados = ['recepcionista' => $criar('recepcionista'), 'vendedor' => $criar('vendedor'), 'aluno' => $contaAluno,
    'professor' => ['email' => $professores['a']['email']], 'professor (legado)' => ['email' => 'fixture4@example.test'], 'admin inativo' => ['email' => 'fixture6@example.test']];
$antesTabela = $db->query('CHECKSUM TABLE cadastro_profissional, usuarios')->fetch_all();
foreach ($recusados as $rotulo => $conta) {
    $nav = $rotulo === 'aluno' ? $aluno : new Navegador($base);
    if ($rotulo !== 'aluno') {
        $login = $nav->entrar($conta['email']);
        if ($rotulo === 'admin inativo') {
            phCheck(!str_contains($login['location'], 'pages/dashboard/dashboard.php'), 'Admin inativo não entra');
            $r = $nav->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'disponivel' => '0']);
            phCheck($r['status'] === 302 && phDisponivel($db, $professores['a']['cadastro']) === 1, 'Sem sessão: disponibilidade não muda');
            continue;
        }
        phCheck(str_contains($login['location'], 'pages/dashboard/dashboard.php'), "$rotulo entra no painel");
    }
    $pagina = $nav->req('GET', 'pages/dashboard/dashboard.php')['body'];
    phCheck(!str_contains($pagina, 'data-perfil="admin" data-section="profissionais"') && !str_contains($pagina, 'value="disponibilidade"'), "$rotulo não recebe a tela Professores");
    $tokenConta = $nav->csrf();
    foreach ([['acao' => 'disponibilidade', 'disponivel' => '0'], ['acao' => 'update', 'cadastro_status' => 'inativo', 'especialidade' => 'Forjada']] as $acao) {
        $r = $nav->req('POST', $urlProfessores, $acao + ['secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'csrf_token' => $tokenConta]);
        phCheck($r['status'] === 302 && !str_contains($r['location'], 'section=profissionais'), "$rotulo: POST {$acao['acao']} recusado na rota");
    }
}
phCheck($db->query('CHECKSUM TABLE cadastro_profissional, usuarios')->fetch_all() === $antesTabela, 'Nenhuma recusa alterou cadastros ou contas');
// O próprio professor não tem controle de disponibilidade no painel dele.
$navProfessor = new Navegador($base);
$navProfessor->entrar($professores['a']['email']);
$painelProfessor = $navProfessor->req('GET', 'pages/dashboard/dashboard.php')['body'];
phCheck(!str_contains($painelProfessor, 'disponivel_agendamento') && !str_contains($painelProfessor, 'funcionalidades/profissionais.php'), 'Painel do professor sem controle de disponibilidade');
$painelAdmin = $admin->req('GET', 'pages/dashboard/dashboard.php?section=profissionais')['body'];
phCheck(!str_contains($painelAdmin, 'name="cadastro_status"'), 'Situação legada do cadastro não é editável na tela Professores');

// --- 6 e 7. Agendamento criado pelo próprio professor, no painel dele ---------------------------
$db->query("INSERT INTO profissional_aluno (id_profissional, id_aluno, status) VALUES ({$professores['a']['cadastro']}, {$contaAluno['id']}, 'ativo')
    ON DUPLICATE KEY UPDATE status = 'ativo'");
/** Abre a agenda do professor e devolve o HTML e o token de uso único do formulário de agendamento. */
$agendaProfessor = static function () use ($navProfessor): array {
    $html = $navProfessor->req('GET', 'pages/dashboard/dashboard.php?section=agenda')['body'];
    preg_match('/name="operacao_agendamento" value="([a-f0-9]{32})"/', $html, $op);
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $csrf);
    return ['html' => $html, 'operacao' => $op[1] ?? '', 'csrf' => $csrf[1] ?? ''];
};
$alertaProfessor = static fn(string $html): string => preg_match('#<div class="alert alert-([a-z]+)[^"]*"[^>]*>\s*(.*?)\s*<button#s', $html, $m) ? $m[1] . ': ' . trim(html_entity_decode(strip_tags($m[2]))) : '';
$agendarComoProfessor = static function (array $tela, string $hora, array $extra = []) use ($navProfessor, $contaAluno, $data2): array {
    $r = $navProfessor->req('POST', 'pages/dashboard/dashboard.php?section=agenda', $extra + ['csrf_token' => $tela['csrf'], 'prof_action' => 'agendar',
        'operacao_agendamento' => $tela['operacao'], 'id_aluno' => (string) $contaAluno['id'], 'titulo' => 'Treino do professor', 'tipo' => 'personal',
        'data_evento' => $data2, 'hora_inicio' => $hora, 'hora_fim' => '', 'local' => 'Sala 3', 'observacao' => '']);
    return $r;
};
$doProfessor = static fn(int $idProfissional): int => phContar($db, "SELECT COUNT(*) FROM agendamento WHERE id_profissional = $idProfissional AND titulo = 'Treino do professor'");
$tela = $agendaProfessor();
phCheck($tela['operacao'] !== '' && !str_contains($tela['html'], 'data-of-agenda-bloqueada'), 'Professor disponível: formulário de agendamento sem aviso de bloqueio');
$r = $agendarComoProfessor($tela, '06:15');
phCheck($doProfessor($professores['a']['cadastro']) === 1 && str_starts_with($alertaProfessor($r['body']), 'success'), 'Professor disponível agenda o próprio aluno: ' . $alertaProfessor($r['body']));
$criado = $db->query("SELECT status, id_usuario FROM agendamento WHERE id_profissional = {$professores['a']['cadastro']} AND titulo = 'Treino do professor'")->fetch_assoc();
phCheck($criado['status'] === 'agendado' && (int) $criado['id_usuario'] === $contaAluno['id'], 'Agendamento do professor gravado como agendado, para o aluno certo');
$r = $agendarComoProfessor($tela, '06:45');
phCheck($doProfessor($professores['a']['cadastro']) === 1 && str_contains($alertaProfessor($r['body']), 'já utilizado'), 'Reenvio do mesmo formulário: recusado');
// Indisponível: tela avisa e o servidor recusa, mesmo com formulário válido ou forjado.
$admin->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'disponivel' => '0', 'csrf_token' => $token]);
$tela = $agendaProfessor();
phCheck(str_contains($tela['html'], 'data-of-agenda-bloqueada') && str_contains($tela['html'], 'indisponível para novos agendamentos')
    && (bool) preg_match('/data-bs-target="#ofAgendamentoModal"\s+disabled/', $tela['html']), 'Professor indisponível: aviso na agenda e botão desabilitado');
$r = $agendarComoProfessor($tela, '07:15');
phCheck($doProfessor($professores['a']['cadastro']) === 1 && str_contains($alertaProfessor($r['body']), 'indisponível para novos agendamentos'), 'Professor indisponível: POST do próprio professor recusado (' . $alertaProfessor($r['body']) . ')');
$tela = $agendaProfessor();
$r = $agendarComoProfessor($tela, '07:45', ['id_profissional' => (string) $professores['b']['cadastro'], 'status' => 'confirmado']);
phCheck($doProfessor($professores['a']['cadastro']) === 1 && $doProfessor($professores['b']['cadastro']) === 0, 'POST forjado com id_profissional de outro professor: ignorado e recusado');
// Disponível, mas com o cadastro profissional inativo: também recusado.
$admin->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'disponivel' => '1', 'csrf_token' => $token]);
$cadastroAntesStatus = $db->query("SELECT * FROM cadastro_profissional WHERE id_profissional = {$professores['a']['cadastro']}")->fetch_assoc();
$modalidadesAntesStatus = array_filter(explode(', ', (string) ($cadastroAntesStatus['modalidades'] ?? '')));
$admin->req('POST', $urlProfessores, [
    'acao' => 'update', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'],
    'especialidade' => (string) ($cadastroAntesStatus['especialidade'] ?? ''),
    'documento' => (string) ($cadastroAntesStatus['registro_profissional'] ?? ''),
    'descricao' => (string) ($cadastroAntesStatus['descricao'] ?? ''),
    'modalidades' => $modalidadesAntesStatus, 'cadastro_status' => 'inativo', 'csrf_token' => $token,
]);
$cadastroDepoisStatus = $db->query("SELECT * FROM cadastro_profissional WHERE id_profissional = {$professores['a']['cadastro']}")->fetch_assoc();
phCheck($cadastroDepoisStatus === $cadastroAntesStatus, 'POST forjado não altera situação legada nem outros dados profissionais');
$db->query("UPDATE cadastro_profissional SET status = 'inativo' WHERE id_profissional = {$professores['a']['cadastro']}");
$tela = $agendaProfessor();
$r = $agendarComoProfessor($tela, '08:15');
phCheck($doProfessor($professores['a']['cadastro']) === 1 && str_contains($alertaProfessor($r['body']), 'cadastro profissional está inativo'), 'Cadastro inativo: POST do professor recusado');
$db->query("UPDATE cadastro_profissional SET status = 'ativo' WHERE id_profissional = {$professores['a']['cadastro']}");
$tela = $agendaProfessor();
$r = $agendarComoProfessor($tela, '08:45');
phCheck($doProfessor($professores['a']['cadastro']) === 2, 'Cadastro reativado e disponível: o professor volta a agendar');
phCheck($db->query("SELECT COUNT(*) FROM agendamento WHERE id_profissional = {$professores['a']['cadastro']} AND titulo = 'Treino do professor' AND status = 'agendado'")->fetch_row()[0] == 2, 'Agendamentos do professor preservados durante a indisponibilidade');

// Diretor também gerencia a disponibilidade.
$diretor = $criar('diretor');
$navDiretor = new Navegador($base);
phCheck(str_contains($navDiretor->entrar($diretor['email'])['location'], 'pages/dashboard/dashboard.php'), 'Diretor entra');
$navDiretor->req('POST', $urlProfessores, ['acao' => 'disponibilidade', 'secao' => 'profissionais', 'id' => (string) $professores['a']['cadastro'], 'disponivel' => '0', 'csrf_token' => $navDiretor->csrf()]);
phCheck(phDisponivel($db, $professores['a']['cadastro']) === 0, 'Diretor altera a disponibilidade');
$db->query("UPDATE usuarios SET tipo_usuario = 'admin', status = 'inativo' WHERE id_usuario = {$diretor['id']}");
$db->query("UPDATE cadastro_profissional SET disponivel_agendamento = 1 WHERE id_profissional = {$professores['a']['cadastro']}");

echo "Professores (HTTP): $checks verificações passaram.\n";
