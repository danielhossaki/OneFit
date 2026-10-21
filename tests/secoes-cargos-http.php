<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Seções e permissões por cargo via HTTP real, na cópia sintética ligada ao banco descartável
// (tests/papeis-http.ps1 -Script secoes-cargos-http.php, depois de tests/funcionarios-preparar.ps1):
//  - menu (BO_PERFIS em assets/js/dashboard.js) e seções enviadas no HTML, exatamente as do cargo;
//  - telas de consulta (recepção: Alunos, Pagamentos, Modalidades, Cashback; vendedor: Categorias,
//    Cashback) sem botões, formulários nem modais de gravação, e as rotas de gravação recusadas;
//  - agendamento da recepção com busca de aluno, horários revalidados e auditoria do operador;
//  - professor: adiciona treino só a aluno ativo com vínculo ativo; o resto é recusado no servidor;
//  - vendedor: só os próprios produtos;
//  - admin e diretor continuam com as telas e as gravações de antes.
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

$checks = 0;
function scCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function scValor(mysqli $db, string $sql): ?string
{
    $linha = $db->query($sql)->fetch_row();
    return $linha === null ? null : (string) $linha[0];
}
function scCpf(int $n): string
{
    $cpf = sprintf('%09d', $n);
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
        }
        $cpf .= ((10 * $soma) % 11) % 10;
    }
    return $cpf;
}
/** "perfil:seção" de cada <section data-section> enviada no HTML. */
function scSecoes(string $html): array
{
    preg_match_all('/<section\b([^>]*)>/s', $html, $m);
    $secoes = [];
    foreach ($m[1] as $atributos) {
        if (preg_match('/\bdata-section="([a-z-]+)"/', $atributos, $s)) {
            preg_match('/\bdata-perfil="([a-z]*)"/', $atributos, $p);
            $secoes[] = ($p[1] ?? '?') . ':' . $s[1];
        }
    }
    sort($secoes);
    return array_values(array_unique($secoes));
}
/** HTML de uma seção (da abertura da <section> até o primeiro </section>). */
function scSecao(string $html, string $perfil, string $secao): string
{
    $padrao = '#<section\b[^>]*data-perfil="' . $perfil . '"[^>]*data-section="' . preg_quote($secao, '#') . '"[^>]*>.*?</section>#s';
    return preg_match($padrao, $html, $m) ? $m[0] : '';
}
/** Alerta de retorno do painel do professor: "tipo: texto" ('' quando não há alerta). */
function scAlerta(string $html): string
{
    return preg_match('#<div class="alert alert-([a-z]+)[^"]*"[^>]*>(.*?)<button#s', $html, $m) ? $m[1] . ': ' . trim(strip_tags($m[2])) : '';
}
function scFlash(string $html): string
{
    return preg_match('#<div class="bo-notice(?: [^"]*)?"[^>]*>.*?<span>(.*?)</span>#s', $html, $m)
        ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8')) : '';
}
/** Itens do menu de cada painel em BO_PERFIS (chave => [chaves dos itens]). */
function scMenus(string $js): array
{
    if (!preg_match('/const BO_PERFIS = \{(.*?)\n\};/s', $js, $m)) {
        throw new RuntimeException('BO_PERFIS não encontrado em dashboard.js');
    }
    preg_match_all('/^\s{4}([a-z]+): \{\s*label:.*?menus: \[(.*?)\],/sm', $m[1], $perfis, PREG_SET_ORDER);
    $menus = [];
    foreach ($perfis as [, $perfil, $itens]) {
        preg_match_all("/key: '([a-z-]+)'/", $itens, $chaves);
        $menus[$perfil] = $chaves[1];
    }
    return $menus;
}
// Tabelas que nenhuma recusa deste teste pode alterar.
$retrato = static fn(): array => $db->query('CHECKSUM TABLE usuarios, matricula, pagamento, cashback, categorias, modalidades, produtos, cadastro_planos, treino_exercicio, profissional_aluno, agendamento')->fetch_all();

// --- Contas e dados próprios desta execução ------------------------------------------------
$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$criar = static function (string $cargo, string $status = 'ativo') use ($db, $run, &$seq, $senha): array {
    $seq++;
    $email = "sc{$run}x{$seq}@example.test";
    $nome = "Secoes Http $run $seq";
    $cpf = sprintf('%011d', $run * 100 + $seq + 40);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-01-01', 'outro', ?, 'Rua Teste, 4', 'Santos/SP', ?, 1, '(11) 90000-0004', ?, ?)");
    $stmt->bind_param('ssssss', $nome, $senha, $cpf, $email, $cargo, $status);
    $stmt->execute();
    return ['id' => (int) $stmt->insert_id, 'email' => $email, 'nome' => $nome];
};
$contas = [];
foreach (['recepcionista', 'professor', 'professor2' => 'professor', 'vendedor', 'vendedor2' => 'vendedor', 'admin', 'diretor', 'aluno'] as $chave => $cargo) {
    $contas[is_int($chave) ? $cargo : $chave] = $criar($cargo);
}
$alunoVinculado = $criar('aluno');
$alunoSemVinculo = $criar('aluno');
$alunoVinculoInativo = $criar('aluno');
$alunoContaInativa = $criar('aluno', 'inativo');

// Cadastro profissional dos dois professores e vínculos do primeiro (como a tela Funcionários e o painel fazem).
$cadastro = [];
foreach (['professor', 'professor2'] as $chave) {
    $stmt = $db->prepare("INSERT INTO cadastro_profissional (id_usuario, nome, email, celular, especialidade, status) VALUES (?, ?, ?, '(11) 90000-0004', 'Musculação', 'ativo')");
    $stmt->bind_param('iss', $contas[$chave]['id'], $contas[$chave]['nome'], $contas[$chave]['email']);
    $stmt->execute();
    $cadastro[$chave] = (int) $stmt->insert_id;
}
foreach ([[$alunoVinculado, 'ativo'], [$alunoVinculoInativo, 'inativo'], [$alunoContaInativa, 'ativo']] as [$aluno, $status]) {
    $stmt = $db->prepare('INSERT INTO profissional_aluno (id_profissional, id_aluno, status) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $cadastro['professor'], $aluno['id'], $status);
    $stmt->execute();
}
// Um exercício já na ficha do aluno vinculado (lançado por ele mesmo).
$db->query("INSERT INTO treino_exercicio (id_usuario, nome, dia_semana, series, repeticoes, carga, token_criacao)
    VALUES ({$alunoVinculado['id']}, 'Agachamento', 'segunda', 3, 10, 20, '" . bin2hex(random_bytes(16)) . "')");
$idExercicioDoAluno = (int) $db->insert_id;
// Um produto de cada vendedor.
$produtos = [];
foreach (['vendedor', 'vendedor2'] as $chave) {
    $nome = "Produto $chave $run";
    $stmt = $db->prepare("INSERT INTO produtos (id_vendedor, nome, preco, estoque, categoria, cashback_valor, status, desconto) VALUES (?, ?, 50.00, 5, NULL, 0, 'ativo', 0)");
    $stmt->bind_param('is', $contas[$chave]['id'], $nome);
    $stmt->execute();
    $produtos[$chave] = ['id' => (int) $stmt->insert_id, 'nome' => $nome];
}
$cashbackDoAluno = "Cashback do teste $run";
$stmt = $db->prepare("INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status) VALUES (?, 7.50, 'credito', 'mensalidade', ?, 'disponivel')");
$stmt->bind_param('is', $contas['aluno']['id'], $cashbackDoAluno);
$stmt->execute();
$idCashback = (int) $stmt->insert_id;

$nav = [];
foreach ($contas as $chave => $conta) {
    $nav[$chave] = new Navegador($base);
    scCheck(str_contains($nav[$chave]->entrar($conta['email'])['location'], 'pages/dashboard/dashboard.php'), "$chave entra no painel");
}
$painel = static fn(string $chave): string => $nav[$chave]->req('GET', 'pages/dashboard/dashboard.php')['body'];
$idRecepcaoAgendamento = 0;
$idRecepcaoAuditoria = 0;
$idProfissionalAgenda = 0;
$linkRecepcaoAnterior = false;
$cpfAnteriorConsulta = null;
$professor2DisponivelAnterior = null;

try {
    // --- Menu e seções enviadas: exatamente as do cargo -----------------------------------------
    $menus = scMenus(file_get_contents(__DIR__ . '/../assets/js/dashboard.js'));
    $esperado = [
        'recepcionista' => ['painel' => 'recepcionista',
                'menu' => ['agenda', 'usuarios', 'pagamentos', 'cadastro-facial', 'modalidades', 'cashbacks', 'marketplace', 'configuracoes'],
            // Marketplace é uma seção: "Comprar produtos" (link para a loja) e a consulta de pedidos.
                'secoes' => ['agenda', 'cadastro-facial', 'cashbacks', 'marketplace', 'modalidades', 'pagamentos', 'usuarios']],
        'professor' => ['painel' => 'profissional',
            'menu' => ['perfil', 'agenda', 'treino', 'configuracoes'],
            'secoes' => ['agenda', 'treino']],
        'vendedor' => ['painel' => 'vendedor',
            'menu' => ['vendas', 'produtos', 'categorias', 'cashbacks', 'configuracoes'],
            'secoes' => ['cashbacks', 'categorias', 'produtos', 'vendas']],
    ];
    $secoesAdmin = ['cadastro-facial', 'cashbacks', 'categorias', 'comentarios', 'dashboard', 'funcionarios', 'modalidades', 'pagamentos', 'planos', 'produtos', 'profissionais', 'usuarios', 'vendas'];
    foreach ($esperado as $chave => ['painel' => $perfil, 'menu' => $menu, 'secoes' => $secoes]) {
        scCheck(($menus[$perfil] ?? null) === $menu, "Menu do $chave: " . implode(', ', $menus[$perfil] ?? []));
        $html = $painel($chave);
        scCheck(str_contains($html, 'const BO_PERFIL_LOGADO = "' . $perfil . '"'), "$chave usa o painel $perfil");
        $recebidas = scSecoes($html);
        $previstas = array_map(static fn(string $s): string => "$perfil:$s", $secoes);
        sort($previstas);
        scCheck($recebidas === $previstas, "Seções enviadas ao $chave: " . implode(', ', $recebidas));
        // Cada item do menu que não é seção especial tem a sua seção, e vice-versa.
        $doMenu = array_values(array_diff($menu, ['perfil', 'configuracoes']));
        sort($doMenu);
        scCheck($doMenu === $secoes, "Menu e seções do $chave coincidem");
        scCheck(str_contains($html, 'id="boProfileSection"') && str_contains($html, 'id="boSettingsSection"'), "$chave tem Meu perfil e Configurações");
        // Pedir pela URL a seção de outro cargo não traz a seção.
        foreach (['funcionarios', 'planos', 'profissionais', 'comentarios', 'dashboard', 'historico', 'compras'] as $alheia) {
            $r = $nav[$chave]->req('GET', "pages/dashboard/dashboard.php?section=$alheia");
            scCheck($r['status'] === 200 && !str_contains($r['body'], 'data-section="' . $alheia . '"'), "$chave não recebe a seção $alheia pela URL");
        }
    }
    scCheck(in_array('marketplace', $menus['recepcionista'], true) && !in_array('marketplace', $menus['profissional'], true), 'Marketplace no menu da recepção, fora do menu do professor');
    $dashboardAdmin = scSecao($painel('admin'), 'admin', 'dashboard');
    $dashboardDiretor = scSecao($painel('diretor'), 'admin', 'dashboard');
    foreach (['Alunos ativos', 'Matrículas vigentes', 'Pagamentos aprovados no mês', 'Cashback concedido no mês',
        'Professores não agendáveis', 'Agendamentos nos próximos 7 dias', 'Acessos bloqueados'] as $indicador) {
        scCheck(str_contains($dashboardAdmin, $indicador) && str_contains($dashboardDiretor, $indicador), "Indicador $indicador disponível para admin e diretor");
    }
    preg_match('#<div class="bo-card">\s*<div class="bo-card-label">Alunos ativos</div>\s*<div class="bo-card-value">(\d+)</div>#', $dashboardAdmin, $alunosAtivosCard);
    $alunosAtivosDb = (int) scValor($db, "SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'aluno' AND status = 'ativo'");
    scCheck((int) ($alunosAtivosCard[1] ?? -1) === $alunosAtivosDb, 'Indicador de alunos ativos corresponde ao banco sintético');
    // Sidebar do admin (diretor e administrador): os mesmos itens de sempre, agrupados em categorias.
    // Os grupos só existem nesse painel; o menu dos demais cargos continua uma lista simples.
    $js = file_get_contents(__DIR__ . '/../assets/js/dashboard.js');
    preg_match('/const BO_PERFIS = \{(.*?)\n\};/s', $js, $blocoPerfis);
    preg_match_all('/^\s{4}([a-z]+): \{\s*label:(.*?)menus: \[(.*?)\],/sm', $blocoPerfis[1], $perfisJs, PREG_SET_ORDER);
    $grupoDe = [];
    foreach ($perfisJs as [, $perfilJs, $cabecalho, $itensJs]) {
        preg_match_all("/\{ key: '([a-z-]+)'[^}]*?(?:grupo: '([a-z]+)')?\s*\}/", $itensJs, $itensGrupo, PREG_SET_ORDER);
        $grupoDe[$perfilJs] = array_combine(array_column($itensGrupo, 1), array_map(static fn(array $i): string => $i[2] ?? '', $itensGrupo));
        scCheck(($perfilJs === 'admin') === str_contains($cabecalho, 'grupos:'), "Grupos de menu só no painel admin ($perfilJs)");
        if ($perfilJs !== 'admin') {
            scCheck(array_filter($grupoDe[$perfilJs]) === [], "Menu do $perfilJs sem itens agrupados");
        }
    }
    $menuAdmin = $grupoDe['admin'] ?? [];
    $semConfig = array_values(array_diff(array_keys($menuAdmin), ['configuracoes']));
    sort($semConfig);
    scCheck(count($menuAdmin) === 14 && $semConfig === $secoesAdmin, 'Menu do admin com os mesmos 14 itens, um por seção (' . implode(', ', array_keys($menuAdmin)) . ')');
    scCheck(array_key_first($menuAdmin) === 'dashboard', 'Visão Geral continua sendo a primeira página do admin');
    // Ordem e grupos exatos da sidebar do admin.
    $gruposEsperados = ['dashboard' => '', 'usuarios' => 'clientes', 'pagamentos' => 'clientes', 'vendas' => 'vendas', 'produtos' => 'vendas', 'categorias' => 'vendas',
        'cashbacks' => 'vendas', 'funcionarios' => 'equipe', 'profissionais' => 'equipe', 'comentarios' => '', 'planos' => '', 'modalidades' => '',
        'cadastro-facial' => '', 'configuracoes' => ''];
    scCheck($menuAdmin === $gruposEsperados, 'Admin: ordem e grupos da sidebar (' . json_encode($menuAdmin) . ')');
    scCheck(preg_match_all("/^\s{12}([a-z]+): \{ label:/m", explode('menus: [', $blocoPerfis[1], 2)[0], $chavesGrupos) === 3 && $chavesGrupos[1] === ['clientes', 'vendas', 'equipe'],
        'Admin: só os grupos Clientes, Vendas e Equipe');
    scCheck(!str_contains($js, 'engajamento') && !str_contains($js, "'Engajamento'"), 'Nenhuma referência ao antigo grupo Engajamento');
    foreach (['admin', 'diretor'] as $chave) {
        $html = $painel($chave);
        $recebidas = array_map(static fn(string $s): string => substr($s, strlen('admin:')), scSecoes($html));
        scCheck($recebidas === $secoesAdmin, "Seções do $chave inalteradas: " . implode(', ', $recebidas));
        foreach (['#modalUsuarioNovo', '#modalPagamentoNovo', '#modalCashbackLancar', '#modalCategoriaNova', '#modalModalidadeNova', '#modalProdutoNovo', '#modalFuncionarioNovo'] as $modal) {
            scCheck(str_contains($html, 'data-bs-target="' . $modal . '"'), "$chave mantém o botão $modal");
        }
        scCheck(!str_contains($html, 'data-bo-somente-consulta'), "$chave não tem telas só de consulta");
    }
    scCheck(scSecoes($painel('aluno')) === ['aluno:agenda', 'aluno:cashback', 'aluno:compras', 'aluno:historico', 'aluno:treino'], 'Seções do aluno inalteradas');

    // --- Telas só de consulta: nenhum controle de gravação --------------------------------------
    $consulta = ['recepcionista' => ['recepcionista', ['usuarios', 'pagamentos', 'modalidades', 'cashbacks']], 'vendedor' => ['vendedor', ['categorias', 'cashbacks']]];
    foreach ($consulta as $chave => [$perfil, $secoes]) {
        $html = $painel($chave);
        foreach ($secoes as $secao) {
            $tela = scSecao($html, $perfil, $secao);
            scCheck($tela !== '' && str_contains($tela, 'data-bo-somente-consulta'), "$chave: $secao marcada como somente consulta");
            $telaSemConsultaCpf = $tela;
            if ($chave === 'recepcionista' && $secao === 'cashbacks') {
                preg_match_all('/<form\b/i', $tela, $formulariosCpf);
                scCheck(count($formulariosCpf[0]) === 1 && str_contains($tela, 'cashbacks-consulta.php'), 'Recepção tem somente o formulário autorizado de busca por CPF');
                $telaSemConsultaCpf = preg_replace('#<form\b[^>]*action="[^"]*cashbacks-consulta\.php"[^>]*>.*?</form>#s', '', $tela) ?? $tela;
            }
            scCheck(!preg_match('/<form\b|data-bs-toggle="modal"|funcionalidades\/|btn-bo-gold|data-bo-acao|bi-trash|bi-pencil/', $telaSemConsultaCpf), "$chave: $secao sem formulário, modal ou botão de gravação");
        }
        scCheck(!preg_match('/id="modal(Usuario|Pagamento|Cashback|Categoria|Modalidade)[A-Za-z]*\d*"/', $html), "$chave: nenhum modal de gravação dessas telas no HTML");
    }
    $recepcao = $painel('recepcionista');
    scCheck(str_contains(scSecao($recepcao, 'recepcionista', 'usuarios'), $contas['aluno']['email']), 'Recepção vê o aluno na tela Alunos');
    scCheck(str_contains(scSecao($recepcao, 'recepcionista', 'cashbacks'), $cashbackDoAluno), 'Recepção vê os lançamentos de cashback');
    scCheck(str_contains(scSecao($painel('vendedor'), 'vendedor', 'cashbacks'), $cashbackDoAluno), 'Vendedor vê os lançamentos de cashback');
    scCheck(str_contains(scSecao($recepcao, 'recepcionista', 'agenda'), 'Buscar aluno'), 'Recepção recebe a seção de agendamentos');

    // Consulta de cashback por CPF: o valor só é enviado por POST e não volta no HTML/URL.
    $cpfConsulta = scCpf($run * 100 + 97);
    $cpfAnteriorConsulta = scValor($db, "SELECT cpf FROM usuarios WHERE id_usuario = {$contas['aluno']['id']}");
    $stmt = $db->prepare('UPDATE usuarios SET cpf = ? WHERE id_usuario = ?');
    $stmt->bind_param('si', $cpfConsulta, $contas['aluno']['id']);
    $stmt->execute();
    $stmt->close();
    $csrfRecepcao = $nav['recepcionista']->csrf();
    $r = $nav['recepcionista']->req('POST', 'pages/dashboard/funcionalidades/cashbacks-consulta.php', [
        'csrf_token' => $csrfRecepcao, 'secao' => 'cashbacks', 'cpf' => $cpfConsulta,
    ]);
    $cashbackConsulta = $nav['recepcionista']->req('GET', 'pages/dashboard/dashboard.php?section=cashbacks');
    preg_match('#<div class="bo-cashback-cpf-result" role="status">(.*?)</div>#s', $cashbackConsulta['body'], $resultadoCpf);
    scCheck($r['status'] === 302 && !str_contains($r['location'], $cpfConsulta)
        && str_contains($resultadoCpf[1] ?? '', htmlspecialchars($contas['aluno']['nome']))
        && !str_contains($resultadoCpf[1] ?? '', $cpfConsulta), 'Recepção consulta cashback pelo CPF sem expor o CPF no URL ou resultado');
    $semPermissao = $nav['vendedor']->req('POST', 'pages/dashboard/funcionalidades/cashbacks-consulta.php', [
        'csrf_token' => $nav['vendedor']->csrf(), 'secao' => 'cashbacks', 'cpf' => $cpfConsulta,
    ]);
    scCheck($semPermissao['status'] === 302, 'Vendedor não acessa a busca de cashback por CPF');
    $nav['recepcionista']->req('POST', 'pages/dashboard/funcionalidades/cashbacks-consulta.php', [
        'csrf_token' => $csrfRecepcao, 'secao' => 'cashbacks', 'acao' => 'limpar',
    ]);

    // Agendamento da recepção: busca, confirma a identidade do aluno, vê professor indisponível,
    // consulta slots novamente no servidor e registra o operador na auditoria.
    $dataAgenda = new DateTimeImmutable('tomorrow', new DateTimeZone('America/Sao_Paulo'));
    while ((int) $dataAgenda->format('w') === 0) {
        $dataAgenda = $dataAgenda->modify('+1 day');
    }
    $dataAgenda = $dataAgenda->format('Y-m-d');
    $urlAgendaRecepcao = 'pages/dashboard/funcionalidades/agenda-recepcao.php';
    $navRecepcao = $nav['recepcionista'];
    $csrfRecepcao = $navRecepcao->csrf();
    $linkRecepcaoAnterior = scValor($db, "SELECT COUNT(*) FROM profissional_aluno WHERE id_profissional = {$cadastro['professor']} AND id_aluno = {$contas['aluno']['id']}") === '1';
    $r = $navRecepcao->req('POST', $urlAgendaRecepcao, [
        'csrf_token' => $csrfRecepcao, 'secao' => 'agenda', 'acao' => 'buscar_aluno', 'aluno_busca' => (string) $contas['aluno']['id'],
    ]);
    $agendaTela = $navRecepcao->req('GET', 'pages/dashboard/dashboard.php?section=agenda');
    scCheck($r['status'] === 302 && str_contains($agendaTela['body'], htmlspecialchars($contas['aluno']['nome'])), 'Recepção busca aluno ativo por ID');
    $navRecepcao->req('POST', $urlAgendaRecepcao, [
        'csrf_token' => $csrfRecepcao, 'secao' => 'agenda', 'acao' => 'selecionar_aluno', 'id_aluno' => (string) $contas['vendedor']['id'],
    ]);
    $agendaTela = $navRecepcao->req('GET', 'pages/dashboard/dashboard.php?section=agenda');
    scCheck(!str_contains(scSecao($agendaTela['body'], 'recepcionista', 'agenda'), 'Aluno selecionado'), 'Seleção forjada de conta que não é aluno não é aceita');
    $navRecepcao->req('POST', $urlAgendaRecepcao, [
        'csrf_token' => $csrfRecepcao, 'secao' => 'agenda', 'acao' => 'selecionar_aluno', 'id_aluno' => (string) $contas['aluno']['id'],
    ]);
    $professor2DisponivelAnterior = (int) scValor($db, "SELECT disponivel_agendamento FROM cadastro_profissional WHERE id_profissional = {$cadastro['professor2']}");
    $db->query("UPDATE cadastro_profissional SET disponivel_agendamento = 0 WHERE id_profissional = {$cadastro['professor2']}");
    $navRecepcao->req('POST', $urlAgendaRecepcao, [
        'csrf_token' => $csrfRecepcao, 'secao' => 'agenda', 'acao' => 'horarios', 'data_evento' => $dataAgenda,
    ]);
    $agendaTela = $navRecepcao->req('GET', 'pages/dashboard/dashboard.php?section=agenda');
    $agendaSecao = scSecao($agendaTela['body'], 'recepcionista', 'agenda');
    scCheck(str_contains($agendaSecao, $contas['professor2']['nome']) && str_contains($agendaSecao, 'Agenda pausada')
        && str_contains($agendaSecao, 'name="slot"'), 'Recepção vê professor indisponível e horários elegíveis');
    preg_match('/<option value="(sim:[^"]+)"/', $agendaSecao, $slotRecepcao);
    scCheck(!empty($slotRecepcao[1]), 'Agenda fornece horário futuro ao aluno selecionado');
    $idProfissionalAgenda = (int) (explode(':', $slotRecepcao[1])[2] ?? 0);
    $reservaRecepcao = $navRecepcao->req('POST', $urlAgendaRecepcao, [
        'csrf_token' => $csrfRecepcao, 'secao' => 'agenda', 'acao' => 'agendar', 'slot' => $slotRecepcao[1],
    ]);
    $paginaReservaRecepcao = $navRecepcao->req('GET', 'pages/dashboard/dashboard.php?section=agenda');
    $idRecepcaoAgendamento = (int) scValor($db, "SELECT id_agendamento FROM agendamento WHERE id_usuario = {$contas['aluno']['id']} AND id_profissional = $idProfissionalAgenda AND data_evento = '$dataAgenda' ORDER BY id_agendamento DESC LIMIT 1");
    $idRecepcaoAuditoria = (int) scValor($db, "SELECT id_evento FROM auditoria_eventos WHERE categoria = 'agenda' AND acao = 'criado_recepcao' AND id_operador = {$contas['recepcionista']['id']} AND id_alvo = {$contas['aluno']['id']} ORDER BY id_evento DESC LIMIT 1");
    $agendamentoRecepcao = $db->query("SELECT status FROM agendamento WHERE id_agendamento = $idRecepcaoAgendamento")->fetch_row()[0] ?? '';
    $auditoriaRecepcao = $db->query("SELECT COUNT(*) FROM auditoria_eventos WHERE id_evento = $idRecepcaoAuditoria")->fetch_row()[0] ?? 0;
    scCheck($idRecepcaoAgendamento > 0 && $agendamentoRecepcao === 'confirmado'
        && $idRecepcaoAuditoria > 0 && (int) $auditoriaRecepcao === 1,
        'Agendamento confirmado e operador registrado na auditoria (POST ' . $reservaRecepcao['status'] . '; flash: ' . scFlash($paginaReservaRecepcao['body']) . ')');
    $antesDuplicado = scValor($db, "SELECT COUNT(*) FROM agendamento WHERE id_usuario = {$contas['aluno']['id']} AND id_profissional = $idProfissionalAgenda AND data_evento = '$dataAgenda'");
    $navRecepcao->req('POST', $urlAgendaRecepcao, [
        'csrf_token' => $csrfRecepcao, 'secao' => 'agenda', 'acao' => 'agendar', 'slot' => $slotRecepcao[1],
    ]);
    scCheck(scValor($db, "SELECT COUNT(*) FROM agendamento WHERE id_usuario = {$contas['aluno']['id']} AND id_profissional = $idProfissionalAgenda AND data_evento = '$dataAgenda'") === $antesDuplicado,
        'Reenvio de slot não cria agendamento duplicado');
    $db->query("UPDATE cadastro_profissional SET disponivel_agendamento = 1 WHERE id_profissional = {$cadastro['professor2']}");
    $categoriasVendedor = scSecao($painel('vendedor'), 'vendedor', 'categorias');
    foreach ($db->query('SELECT nome, status FROM categorias')->fetch_all(MYSQLI_ASSOC) as $categoria) {
        scCheck(str_contains($categoriasVendedor, htmlspecialchars($categoria['nome'])) === ($categoria['status'] === 'ativo'), "Vendedor vê só categorias ativas ({$categoria['nome']})");
    }

    // Requisições diretas às rotas de gravação dessas telas: recusadas na entrada, nada muda.
    $tentativas = [
        'usuarios.php' => [['acao' => 'create', 'nome' => 'Forjado', 'email' => "forjado{$run}@example.test", 'cpf' => '52998224725', 'senha' => 'Forjado-123', 'celular' => '11999999999', 'nascimento' => '1990-01-01', 'genero' => 'outro', 'nacionalidade' => 'BR', 'estado' => 'SP', 'endereco' => 'R', 'cidade' => 'S'],
            ['acao' => 'update', 'id' => (string) $contas['aluno']['id'], 'nome' => 'Forjado', 'email' => $contas['aluno']['email'], 'cpf' => '1', 'status' => 'inativo'],
            ['acao' => 'toggle-status', 'id' => (string) $contas['aluno']['id']], ['acao' => 'delete', 'id' => (string) $contas['aluno']['id']]],
        'pagamentos.php' => [['acao' => 'create', 'data' => '2026-01-01', 'tipo' => 'PIX', 'valor' => '10', 'usuarioId' => (string) $contas['aluno']['id']], ['acao' => 'delete', 'id' => '1']],
        'cashbacks.php' => [['acao' => 'create', 'data' => '2026-01-01', 'tipo' => 'credito', 'valor' => '99', 'usuarioId' => (string) $contas['aluno']['id']],
            ['acao' => 'massa', 'data' => '2026-01-01', 'valor' => '1', 'alvo' => 'Todos'], ['acao' => 'delete', 'id' => (string) $idCashback]],
        'modalidades.php' => [['acao' => 'create', 'nome' => "Forjada $run", 'descricao' => 'x', 'icone' => 'generico'], ['acao' => 'toggle-status', 'id' => '1'], ['acao' => 'delete', 'id' => '1']],
        'categorias.php' => [['acao' => 'create', 'nome' => "Forjada $run"], ['acao' => 'update', 'id' => '1', 'nome' => 'Forjada'], ['acao' => 'delete', 'id' => '1']],
    ];
    foreach (['recepcionista', 'vendedor', 'professor'] as $chave) {
        $csrf = $nav[$chave]->csrf();
        $antes = $retrato();
        foreach ($tentativas as $rota => $lista) {
            foreach ($lista as $campos) {
                $r = $nav[$chave]->req('POST', "pages/dashboard/funcionalidades/$rota", ['csrf_token' => $csrf, 'secao' => 'usuarios'] + $campos);
                scCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), "$rota ({$campos['acao']}) por $chave: recusado na entrada");
            }
        }
        scCheck($retrato() === $antes, "Recusas ao $chave não alteram o banco");
    }
    $antes = $retrato();
    $r = $nav['vendedor']->req('POST', 'pages/dashboard/funcionalidades/agenda-recepcao.php', [
        'csrf_token' => $nav['vendedor']->csrf(), 'secao' => 'agenda', 'acao' => 'agendar', 'slot' => 'sim:invalid',
    ]);
    scCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), 'Vendedor não acessa agendamentos da recepção');
    scCheck($retrato() === $antes, 'Recusa do agendamento da recepção não altera o banco');

    // --- Professor: treino só de aluno com vínculo ativo -----------------------------------------
    $professor = $nav['professor'];
    $html = $painel('professor');
    $treinoTela = scSecao($html, 'profissional', 'treino');
    scCheck(str_contains($treinoTela, $alunoVinculado['email']) && str_contains($html, 'id="ofTreinoModal' . $alunoVinculado['id'] . '"'), 'Professor vê o aluno vinculado e a ficha dele');
    scCheck(!str_contains($html, 'id="ofTreinoModal' . $alunoVinculoInativo['id'] . '"') && !str_contains($html, 'id="ofTreinoModal' . $alunoSemVinculo['id'] . '"'), 'Sem ficha para vínculo inativo ou aluno sem vínculo');
    $fichaModal = preg_match('#id="ofTreinoModal' . $alunoVinculado['id'] . '".*?</table>#s', $html, $m) ? $m[0] : '';
    scCheck(str_contains($fichaModal, '<td>Agachamento</td>') && str_contains($fichaModal, '20 kg'), 'Ficha atual mostra o exercício do aluno');
    scCheck(!str_contains($html, 'name="prof_action" value="usar_cashback"') && !str_contains($html, 'data-bo-compras'), 'Professor sem cashback e sem compras no painel');
    $csrf = $professor->csrf();
    $contarFicha = static fn(int $idAluno): int => (int) scValor($db, "SELECT COUNT(*) FROM treino_exercicio WHERE id_usuario = $idAluno");
    $exercicio = static fn(int $idAluno, array $extra = []): array => $extra + ['csrf_token' => $csrf, 'prof_action' => 'adicionar_treino', 'id_aluno' => (string) $idAluno,
        'token' => bin2hex(random_bytes(16)), 'dia_semana' => 'quarta', 'nome' => 'Supino reto', 'series' => '4', 'repeticoes' => '12', 'carga' => '30'];
    $antesFicha = $contarFicha($alunoVinculado['id']);
    $envio = $exercicio($alunoVinculado['id']);
    $r = $professor->req('POST', 'pages/dashboard/dashboard.php?section=treino', $envio);
    scCheck($r['status'] === 200 && scAlerta($r['body']) === 'success: Exercício adicionado ao treino do aluno.', 'Professor adiciona treino ao aluno vinculado');
    scCheck($contarFicha($alunoVinculado['id']) === $antesFicha + 1, 'Exercício gravado na ficha do aluno');
    scCheck(scValor($db, "SELECT CONCAT(nome, '/', dia_semana, '/', series, '/', repeticoes, '/', carga) FROM treino_exercicio WHERE id_usuario = {$alunoVinculado['id']} ORDER BY id_exercicio DESC LIMIT 1") === 'Supino reto/quarta/4/12/30', 'Exercício com os valores enviados');
    $professor->req('POST', 'pages/dashboard/dashboard.php?section=treino', $envio);
    scCheck($contarFicha($alunoVinculado['id']) === $antesFicha + 1, 'Reenviar o mesmo formulário não duplica o exercício');
    // O aluno vê o exercício na própria ficha.
    $alunoNav = new Navegador($base);
    $alunoNav->entrar($alunoVinculado['email']);
    scCheck(str_contains(scSecao($alunoNav->req('GET', 'pages/dashboard/dashboard.php?section=treino')['body'], 'aluno', 'treino'), 'Supino reto'), 'O aluno vê o exercício na própria ficha');

    $antes = $retrato();
    $recusas = [
        'aluno sem vínculo' => $exercicio($alunoSemVinculo['id']),
        'vínculo inativo' => $exercicio($alunoVinculoInativo['id']),
        'conta do aluno inativa' => $exercicio($alunoContaInativa['id']),
        'conta que não é aluno' => $exercicio($contas['vendedor']['id']),
        'exercício fora do catálogo' => $exercicio($alunoVinculado['id'], ['nome' => 'Exercício inventado']),
        'séries fora do limite' => $exercicio($alunoVinculado['id'], ['series' => '99']),
        'sem token de envio' => $exercicio($alunoVinculado['id'], ['token' => '']),
        'sem CSRF' => $exercicio($alunoVinculado['id'], ['csrf_token' => '']),
    ];
    foreach ($recusas as $rotulo => $campos) {
        $r = $professor->req('POST', 'pages/dashboard/dashboard.php?section=treino', $campos);
        $alerta = scAlerta($r['body']);
        scCheck($r['status'] === 200 && str_starts_with($alerta, 'danger: '), "Treino recusado: $rotulo ($alerta)");
    }
    // Outro professor, sem vínculo com o aluno: recusado.
    $r = $nav['professor2']->req('POST', 'pages/dashboard/dashboard.php?section=treino', ['csrf_token' => $nav['professor2']->csrf()] + $exercicio($alunoVinculado['id']));
    scCheck(scAlerta($r['body']) === 'danger: Selecione um aluno vinculado e ativo.', 'Professor sem vínculo não adiciona treino ao aluno de outro professor');
    // Ações removidas do painel do professor e rotas de outros cargos.
    $r = $professor->req('POST', 'pages/dashboard/dashboard.php?section=treino', ['csrf_token' => $csrf, 'prof_action' => 'usar_cashback', 'valor' => '1']);
    scCheck(scAlerta($r['body']) === 'danger: Ação inválida.', 'Uso de cashback pelo professor: ação recusada');
    scCheck($professor->req('POST', 'pages/dashboard/funcionalidades/treino.php', ['csrf_token' => $csrf, 'acao' => 'salvar', 'id_usuario' => (string) $alunoVinculado['id']])['status'] === 403, 'Rota de treino do aluno recusa o professor');
    $r = $professor->req('POST', 'pages/dashboard/funcionalidades/meus-pedidos.php', ['csrf_token' => $csrf, 'acao' => 'confirmar-recebimento', 'id_item' => '1', 'secao' => 'compras']);
    scCheck($r['status'] === 302 && str_ends_with($r['location'], 'pages/dashboard/dashboard.php'), 'Minhas compras (ações): professor recusado na entrada');
    scCheck($professor->req('GET', 'pages/dashboard/funcionalidades/buscar-compras.php')['status'] === 403, 'Minhas compras (busca): professor recebe 403');
    scCheck($nav['aluno']->req('GET', 'pages/dashboard/funcionalidades/buscar-compras.php')['status'] === 200, 'Controle: o aluno continua buscando as próprias compras');
    scCheck($retrato() === $antes, 'Recusas ao professor não alteram o banco');
    // Campos forjados (acao, id, outro cadastro profissional) não viram edição nem exclusão: só inclui.
    $antesFicha = $contarFicha($alunoVinculado['id']);
    $professor->req('POST', 'pages/dashboard/dashboard.php?section=treino', $exercicio($alunoVinculado['id'], ['acao' => 'excluir', 'id' => (string) $idExercicioDoAluno, 'id_profissional' => (string) $cadastro['professor2']]));
    scCheck($contarFicha($alunoVinculado['id']) === $antesFicha + 1 && scValor($db, "SELECT nome FROM treino_exercicio WHERE id_exercicio = $idExercicioDoAluno") === 'Agachamento',
        'acao/id forjados: o professor só acrescenta, e o exercício do aluno continua');
    // Agenda e Meu perfil continuam funcionando.
    $horario = ['csrf_token' => $csrf, 'prof_action' => 'criar_disponibilidade', 'modalidade' => "Funcional $run", 'data_evento' => date('Y-m-d', strtotime('+3 days')), 'hora_inicio' => '07:00', 'hora_fim' => '08:00', 'local' => 'Sala 1'];
    $r = $professor->req('POST', 'pages/dashboard/dashboard.php?section=agenda', $horario);
    $idHorario = (int) scValor($db, "SELECT id_disponibilidade FROM disponibilidade_profissional WHERE id_profissional = {$cadastro['professor']} AND modalidade = 'Funcional $run'");
    scCheck(scAlerta($r['body']) === 'success: Horário disponível cadastrado.' && $idHorario > 0, 'Agenda: professor cadastra horário disponível');
    $r = $professor->req('POST', 'pages/dashboard/dashboard.php?section=agenda', ['csrf_token' => $csrf, 'prof_action' => 'remover_disponibilidade', 'id_disponibilidade' => (string) $idHorario]);
    scCheck(scAlerta($r['body']) === 'success: Horário disponível removido.' && scValor($db, "SELECT COUNT(*) FROM disponibilidade_profissional WHERE id_disponibilidade = $idHorario") === '0', 'Agenda: professor remove o horário');
    scCheck(str_contains($html, 'id="modalPerfilEditar"') && str_contains($html, 'pages/dashboard/actions/update-profile.php'), 'Meu perfil: formulário de edição dos próprios dados');

    // --- Vendedor: só os próprios produtos -------------------------------------------------------
    $vendedor = $nav['vendedor'];
    $html = $painel('vendedor');
    $telaProdutos = scSecao($html, 'vendedor', 'produtos');
    scCheck(str_contains($telaProdutos, $produtos['vendedor']['nome']) && !str_contains($html, $produtos['vendedor2']['nome']), 'Vendedor vê só os próprios produtos');
    scCheck(str_contains($telaProdutos, 'data-bs-target="#modalProdutoNovo"') && str_contains($html, 'pages/marketplace/marketplace.php'), 'Vendedor cadastra produtos e abre o Marketplace');
    $csrf = $vendedor->csrf();
    $linhaProduto = static fn(int $id): string => (string) scValor($db, "SELECT CONCAT_WS('|', id_vendedor, nome, preco, estoque, cashback_valor, status, IFNULL(desconto, '')) FROM produtos WHERE id_produto = $id");
    $alheio = $produtos['vendedor2']['id'];
    $antesAlheio = $linhaProduto($alheio);
    foreach ([['acao' => 'update', 'id' => (string) $alheio, 'nome' => 'Tomado', 'preco' => '1', 'estoque' => '0', 'cashback' => '9'],
              ['acao' => 'toggle-status', 'id' => (string) $alheio], ['acao' => 'delete', 'id' => (string) $alheio]] as $campos) {
        $vendedor->req('POST', 'pages/dashboard/funcionalidades/produtos.php', ['csrf_token' => $csrf, 'secao' => 'produtos'] + $campos);
        scCheck($linhaProduto($alheio) === $antesAlheio, "Vendedor não altera produto de outro vendedor ({$campos['acao']})");
    }
    $r = $vendedor->req('POST', 'pages/dashboard/funcionalidades/produtos.php', ['csrf_token' => $csrf, 'secao' => 'produtos', 'acao' => 'update',
        'id' => (string) $produtos['vendedor']['id'], 'nome' => $produtos['vendedor']['nome'] . ' (editado)', 'preco' => '55', 'estoque' => '4', 'desconto' => '0', 'cashback' => '0']);
    scCheck(str_contains($r['location'], 'section=produtos') && scValor($db, "SELECT nome FROM produtos WHERE id_produto = {$produtos['vendedor']['id']}") === $produtos['vendedor']['nome'] . ' (editado)', 'Vendedor edita o próprio produto e volta para Produtos');
    $nomeNovo = "Produto novo $run";
    $vendedor->req('POST', 'pages/dashboard/funcionalidades/produtos.php', ['csrf_token' => $csrf, 'secao' => 'produtos', 'acao' => 'create', 'nome' => $nomeNovo, 'preco' => '20', 'estoque' => '1', 'desconto' => '0', 'cashback' => '0', 'id_vendedor' => (string) $contas['vendedor2']['id']]);
    scCheck(scValor($db, 'SELECT id_vendedor FROM produtos WHERE nome = ' . "'" . $db->real_escape_string($nomeNovo) . "'") === (string) $contas['vendedor']['id'], 'Produto novo nasce do vendedor que cadastrou (id_vendedor forjado ignorado)');

    // --- Admin e diretor continuam gravando --------------------------------------------------------
    foreach (['admin', 'diretor'] as $chave) {
        $nomeModalidade = "Modalidade $chave $run";
        $r = $nav[$chave]->req('POST', 'pages/dashboard/funcionalidades/modalidades.php', ['csrf_token' => $nav[$chave]->csrf(), 'secao' => 'modalidades', 'acao' => 'create', 'nome' => $nomeModalidade, 'descricao' => 'Teste', 'icone' => 'generico']);
        $idModalidade = (int) scValor($db, "SELECT id_modalidade FROM modalidades WHERE nome = '" . $db->real_escape_string($nomeModalidade) . "'");
        scCheck($r['status'] === 302 && $idModalidade > 0, "$chave cria modalidade");
        $nav[$chave]->req('POST', 'pages/dashboard/funcionalidades/modalidades.php', ['csrf_token' => $nav[$chave]->csrf(), 'secao' => 'modalidades', 'acao' => 'delete', 'id' => (string) $idModalidade]);
        scCheck(scValor($db, "SELECT COUNT(*) FROM modalidades WHERE id_modalidade = $idModalidade") === '0', "$chave exclui a modalidade criada");
    }
} finally {
    // Remove o que este teste criou fora das contas (as contas ficam, como nos demais testes HTTP).
    if (!empty($idRecepcaoAgendamento)) {
        $db->query("DELETE FROM agendamento WHERE id_agendamento = $idRecepcaoAgendamento");
        if (empty($linkRecepcaoAnterior)) {
            $db->query("DELETE FROM profissional_aluno WHERE id_profissional = $idProfissionalAgenda AND id_aluno = {$contas['aluno']['id']}");
        }
    }
    if (!empty($idRecepcaoAuditoria)) {
        $db->query("DELETE FROM auditoria_eventos WHERE id_evento = $idRecepcaoAuditoria");
    }
    if ($cpfAnteriorConsulta !== null) {
        $stmt = $db->prepare('UPDATE usuarios SET cpf = ? WHERE id_usuario = ?');
        $stmt->bind_param('si', $cpfAnteriorConsulta, $contas['aluno']['id']);
        $stmt->execute();
    }
    if ($professor2DisponivelAnterior !== null) {
        $db->query("UPDATE cadastro_profissional SET disponivel_agendamento = $professor2DisponivelAnterior WHERE id_profissional = {$cadastro['professor2']}");
    }
    $db->query("DELETE FROM produtos WHERE nome LIKE '% $run%' OR nome LIKE '%$run (editado)'");
    $db->query("DELETE FROM cashback WHERE descricao = '" . $db->real_escape_string($cashbackDoAluno) . "'");
}
echo "Seções e permissões por cargo via HTTP: $checks verificações passaram.\n";
