<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Sidebar e tela de Cashback num navegador de verdade (Chrome ou Edge headless), na cópia
// sintética ligada ao banco descartável (tests/papeis-http.ps1 -Script painel-navegador-http.php,
// depois de tests/funcionarios-preparar.ps1). O painel de cada conta é salvo como página estática
// na própria cópia sintética, com um roteiro que clica nos menus e filtros e grava o resultado no
// DOM; o navegador devolve o DOM final (--dump-dom). Nada é enviado ao servidor pelo roteiro.
// Com ONEFIT_TESTE_FOTOS=<pasta>, também salva capturas de tela (desktop e celular) nessa pasta.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixtureRoot = sys_get_temp_dir() . '/onefit-interface-fixture';
$fixture = json_decode(file_get_contents($fixtureRoot . '/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
$webApp = $fixtureRoot . '/www/AN25/OneFit';
if (!is_file($webApp . '/.env') || !str_contains(file_get_contents($webApp . '/.env'), 'DB_HOST=127.0.0.1')) {
    throw new RuntimeException('A cópia sintética não aponta para o banco local.');
}
$navegador = null;
foreach ([getenv('ProgramFiles') . '/Google/Chrome/Application/chrome.exe', getenv('ProgramFiles(x86)') . '/Google/Chrome/Application/chrome.exe',
          getenv('ProgramFiles(x86)') . '/Microsoft/Edge/Application/msedge.exe', getenv('ProgramFiles') . '/Microsoft/Edge/Application/msedge.exe'] as $exe) {
    if (is_file($exe)) { $navegador = $exe; break; }
}
if ($navegador === null) {
    throw new RuntimeException('Chrome ou Edge não encontrado: este teste precisa de um navegador.');
}
require __DIR__ . '/http-navegador.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');

$checks = 0;
function pnCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function pnInserir(mysqli $db, string $tabela, array $dados): int
{
    $stmt = $db->prepare("INSERT INTO `$tabela` (`" . implode('`, `', array_keys($dados)) . '`) VALUES (' . implode(', ', array_fill(0, count($dados), '?')) . ')');
    $valores = array_values($dados);
    $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute();
    return (int) $stmt->insert_id;
}

// --- Contas e cashback próprios desta execução -----------------------------------------------
$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$criar = static function (string $cargo) use ($db, $run, &$seq, $senha): array {
    $seq++;
    $email = "pn{$run}x{$seq}@example.test";
    $nome = "Navegador $run $seq";
    $id = pnInserir($db, 'usuarios', ['nome' => $nome, 'senha' => $senha, 'nacionalidade' => 'BR', 'data_nascimento' => '1990-01-01', 'genero' => 'outro',
        'cpf' => sprintf('%011d', $run * 100 + $seq + 60), 'endereco' => 'Rua Teste, 5', 'cidade_estado' => 'Santos/SP', 'email' => $email, 'email_verificado' => 1,
        'celular' => '(11) 90000-0005', 'tipo_usuario' => $cargo, 'status' => 'ativo']);
    return ['id' => $id, 'email' => $email, 'nome' => $nome];
};
$contas = [];
foreach (['diretor', 'admin', 'recepcionista', 'vendedor', 'professor', 'aluno'] as $cargo) {
    $contas[$cargo] = $criar($cargo);
}
pnInserir($db, 'cadastro_profissional', ['id_usuario' => $contas['professor']['id'], 'nome' => $contas['professor']['nome'], 'email' => $contas['professor']['email'], 'status' => 'ativo']);
$clienteA = $criar('aluno');
$clienteB = $criar('aluno');
foreach ([[$clienteA, 'credito', 'uso', '10.00'], [$clienteA, 'debito', 'uso', '3.00'], [$clienteA, 'credito', 'mensalidade', '5.00'], [$clienteB, 'credito', 'uso', '7.00']] as [$cliente, $tipo, $origem, $valor]) {
    pnInserir($db, 'cashback', ['id_usuario' => $cliente['id'], 'valor' => $valor, 'tipo' => $tipo, 'origem' => $origem, 'descricao' => 'Lançamento manual pelo admin',
        'status' => $tipo === 'credito' ? 'disponivel' : 'utilizado']);
}
$linhasA = (int) $db->query("SELECT COUNT(*) FROM cashback WHERE id_usuario = {$clienteA['id']}")->fetch_row()[0];

// --- Roteiro executado no navegador ----------------------------------------------------------
// Erros de JavaScript são capturados desde o <head>; o roteiro roda depois do carregamento.
$capturaErros = '<script>window.__erros=[];addEventListener("error",e=>__erros.push(String(e.message)));addEventListener("unhandledrejection",e=>__erros.push(String(e.reason)));</script>';
$roteiro = <<<'JS'
<script>
addEventListener('load', () => setTimeout(() => {
    const q = new URLSearchParams(location.search);
    const r = { erros: window.__erros, perfil: BO_PERFIL_LOGADO, largura: innerWidth };
    const nav = document.getElementById('boNav');
    // Seção visível: data-section, ou o id das seções especiais (Meu perfil, Configurações).
    const ativa = () => { const s = document.querySelector('.bo-content-section.active'); return s ? (s.getAttribute('data-section') || s.id) : null; };
    const especiais = { perfil: 'boProfileSection', configuracoes: 'boSettingsSection' };
    const grupoDo = (chave) => nav.querySelector(`[data-section="${chave}"]`)?.closest('[data-bo-nav-grupo]') || null;
    const estadoGrupo = (el) => el ? { grupo: el.dataset.boNavGrupo, aberto: el.classList.contains('is-open'), temAtivo: el.classList.contains('has-active'),
        expandido: el.querySelector('.bo-nav-group-toggle').getAttribute('aria-expanded'), oculto: el.querySelector('.bo-nav-group-items').hidden } : null;
    const estrutura = () => [...nav.children].map((el) => el.matches('[data-bo-nav-grupo]')
        ? { grupo: el.dataset.boNavGrupo, rotulo: el.querySelector('.bo-nav-group-toggle span').textContent, icone: !!el.querySelector('.bo-nav-group-toggle i.bi:not(.bo-nav-group-caret)'),
            seta: !!el.querySelector('.bo-nav-group-caret'), aberto: el.classList.contains('is-open'), itens: [...el.querySelectorAll('.bo-nav-group-items [data-section]')].map((i) => i.dataset.section) }
        : { item: el.dataset.section });
    const visiveis = (tabela) => [...document.querySelectorAll(`[data-bo-table="${tabela}"] tbody tr:not(.bo-empty-row)`)].filter((tr) => tr.style.display !== 'none');
    const vazia = (tabela) => document.querySelector(`[data-bo-table="${tabela}"] .bo-empty-row`)?.style.display !== 'none';
    const digitar = (campo, valor) => { campo.value = valor; campo.dispatchEvent(new Event('input', { bubbles: true })); };

    if (q.get('modo') === 'foto') {
        // O Chrome headless não abre janela com menos de 500 px; zoom na raiz simula uma tela menor
        // (não há breakpoint entre 390 e 500 px, então as regras de celular são as mesmas).
        if (q.get('zoom')) document.documentElement.style.zoom = q.get('zoom');
        if (q.get('menu') === 'abrir') document.getElementById('boSidebarToggle').click();
        if (q.get('grupo')) nav.querySelector(`[data-bo-nav-grupo="${q.get('grupo')}"] .bo-nav-group-toggle`)?.click();
    } else {
        r.menu = BO_PERFIS[BO_PERFIL_LOGADO].menus.map((m) => m.key);
        r.links = BO_PERFIS[BO_PERFIL_LOGADO].menus.filter((m) => m.href).map((m) => m.key);
        r.estruturaInicial = estrutura();
        r.secaoInicial = ativa();
        r.secaoInicialEsperada = especiais[r.menu[0]] || r.menu[0];
        // Cada item da sidebar abre a sua seção; a categoria dele fica aberta e marcada.
        // Itens que são links (Marketplace do aluno) saem do painel e não são clicados.
        r.navegacao = r.menu.filter((chave) => !r.links.includes(chave)).map((chave) => {
            nav.querySelector(`[data-section="${chave}"]`).click();
            return { chave, secao: ativa(), esperada: especiais[chave] || chave, itemAtivo: nav.querySelector(`[data-section="${chave}"]`).classList.contains('active'),
                ativos: nav.querySelectorAll('.bo-nav-item.active').length, rota: new URLSearchParams(location.search).get('section'),
                grupo: estadoGrupo(grupoDo(chave)), gruposMarcados: nav.querySelectorAll('[data-bo-nav-grupo].has-active').length,
                gruposAbertos: nav.querySelectorAll('[data-bo-nav-grupo].is-open').length };
        });
        if (nav.querySelector('[data-bo-nav-grupo]')) {
            // Recolher e expandir não navega; o grupo da página aberta continua marcado.
            const primeiro = nav.querySelector('[data-bo-nav-grupo]');
            const item = primeiro.querySelector('[data-section]').dataset.section;
            nav.querySelector(`[data-section="${item}"]`).click();
            const rota = location.search;
            primeiro.querySelector('.bo-nav-group-toggle').click();
            r.recolhido = { ...estadoGrupo(primeiro), secao: ativa(), mesmaRota: location.search === rota, item };
            primeiro.querySelector('.bo-nav-group-toggle').click();
            r.reaberto = estadoGrupo(primeiro);
            const outro = [...nav.querySelectorAll('[data-bo-nav-grupo]')].find((g) => g !== primeiro);
            outro.querySelector('.bo-nav-group-toggle').click();
            r.outroAberto = { ...estadoGrupo(outro), secao: ativa(), mesmaRota: location.search === rota };
            outro.querySelector('.bo-nav-group-toggle').click();
            r.outroFechado = estadoGrupo(outro);
            // Pesquisa global encontra as páginas pelo nome da categoria.
            boRenderSearch(primeiro.querySelector('.bo-nav-group-toggle span').textContent);
            r.busca = [...document.querySelectorAll('#boSearchResults .bo-search-result strong')].map((s) => s.textContent);
            boCloseSearch();
        }
        // Celular: o botão de grupo não fecha a sidebar; escolher uma página fecha.
        if (innerWidth < 992) {
            document.getElementById('boSidebarToggle').click();
            const sidebar = document.getElementById('boSidebar');
            r.celular = { abriu: sidebar.classList.contains('active') };
            const grupo = nav.querySelector('[data-bo-nav-grupo]');
            if (grupo) {
                grupo.querySelector('.bo-nav-group-toggle').click();
                r.celular.continuaAbertaAposGrupo = sidebar.classList.contains('active');
            }
            const destino = (grupo || nav).querySelector('[data-section]:not([href])');
            destino.click();
            r.celular.fechouAoNavegar = !sidebar.classList.contains('active');
            r.celular.secao = ativa();
            r.celular.destino = especiais[destino.dataset.section] || destino.dataset.section;
        }
        // Cashback: "Ver histórico", "Limpar filtros" e busca de cliente.
        if (r.menu.includes('cashbacks')) {
            boGoToSection('cashbacks', false);
            const total = document.querySelectorAll('[data-bo-table="cashbacks"] tbody tr:not(.bo-empty-row)').length;
            const tipo = document.querySelector('[data-bo-filter="type"][data-bo-target="cashbacks"]');
            const usuario = document.querySelector('[data-bo-filter="user"][data-bo-target="cashbacks"]');
            const contador = document.querySelector('[data-bo-contagem="cashbacks"]');
            r.cashback = { total, contadorInicial: contador?.textContent.trim() };
            tipo.value = 'credito';
            tipo.dispatchEvent(new Event('change', { bubbles: true }));
            r.cashback.soCreditos = visiveis('cashbacks').every((tr) => tr.dataset.type === 'credito');
            const verHistorico = document.querySelector(`[data-bo-filtrar-usuario="${window.__teste.clienteA}"]`);
            r.cashback.temSaldoPorCliente = !!document.querySelector('[data-bo-table="cashbacksClientes"]');
            if (verHistorico) {
                verHistorico.click();
                const linhas = visiveis('cashbacks');
                r.cashback.historico = { linhas: linhas.length, todasDoCliente: linhas.every((tr) => tr.dataset.user === String(window.__teste.clienteA)),
                    tipoLimpo: tipo.value === '', usuario: usuario.value, contador: contador?.textContent.trim() };
            }
            document.querySelector('[data-bo-limpar-filtros="cashbacks"]').click();
            r.cashback.limpo = { linhas: visiveis('cashbacks').length, usuario: usuario.value, tipo: tipo.value, contador: contador?.textContent.trim() };
            digitar(usuario, '#' + String(window.__teste.clienteB).padStart(4, '0'));
            r.cashback.porIdFormatado = visiveis('cashbacks').map((tr) => tr.dataset.user);
            const buscaCliente = document.querySelector('[data-bo-filter="search"][data-bo-target="cashbacksClientes"]');
            if (buscaCliente) {
                digitar(buscaCliente, 'nenhum cliente com este nome');
                r.cashback.semResultado = { linhas: visiveis('cashbacksClientes').length, mensagem: vazia('cashbacksClientes') };
                digitar(buscaCliente, window.__teste.nomeA.toLowerCase());
                r.cashback.buscaNome = visiveis('cashbacksClientes').map((tr) => tr.dataset.user);
            }
        }
    }
    r.erros = window.__erros;
    const pre = document.createElement('pre');
    pre.id = 'boTesteResultado';
    pre.textContent = JSON.stringify(r);
    document.body.appendChild(pre);
}, 50));
</script>
JS;

$perfil = sys_get_temp_dir() . '/onefit-navegador-' . bin2hex(random_bytes(4));
$paginas = [];
$abrir = static function (string $pagina, string $query, int $largura, int $altura, ?string $foto = null) use ($navegador, $perfil, $base): string {
    $args = [$navegador, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions',
        '--user-data-dir=' . $perfil, "--window-size=$largura,$altura", '--virtual-time-budget=8000', '--hide-scrollbars'];
    $args[] = $foto !== null ? '--screenshot=' . $foto : '--dump-dom';
    $args[] = $base . $pagina . $query;
    $proc = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $saida = '';
    $limite = microtime(true) + 90;
    while (true) {
        // stderr também é lido, para o navegador nunca travar com o buffer cheio.
        $saida .= (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        if (!proc_get_status($proc)['running']) break;
        if (microtime(true) > $limite) { proc_terminate($proc); throw new RuntimeException('O navegador não respondeu.'); }
        usleep(100000);
    }
    $saida .= (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return $saida;
};
$resultado = static function (string $dom): array {
    if (!preg_match('#<pre id="boTesteResultado">(.*?)</pre>#s', $dom, $m)) {
        throw new RuntimeException('O roteiro não terminou no navegador.');
    }
    return json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true, 512, JSON_THROW_ON_ERROR);
};

try {
    foreach ($contas as $cargo => $conta) {
        $nav = new Navegador($base);
        pnCheck(str_contains($nav->entrar($conta['email'])['location'], 'pages/dashboard/dashboard.php'), "$cargo entra no painel");
        $html = $nav->req('GET', 'pages/dashboard/dashboard.php')['body'];
        $dados = '<script>window.__teste=' . json_encode(['clienteA' => $clienteA['id'], 'clienteB' => $clienteB['id'], 'nomeA' => $clienteA['nome']]) . ';</script>';
        $html = preg_replace('#<head>#', '<head>' . $capturaErros, $html, 1);
        $html = str_replace('</body>', $dados . $roteiro . '</body>', $html);
        $arquivo = "painel-teste-$run-$cargo.html";
        file_put_contents($webApp . '/' . $arquivo, $html);
        $paginas[$cargo] = $arquivo;
    }

    $grupos = ['clientes' => ['usuarios', 'pagamentos'], 'vendas' => ['vendas', 'produtos', 'categorias', 'cashbacks'], 'equipe' => ['funcionarios', 'profissionais']];
    $rotulosGrupos = ['clientes' => 'Clientes', 'vendas' => 'Vendas', 'equipe' => 'Equipe'];
    $estruturaAdmin = [['item' => 'dashboard'], ['grupo' => 'clientes'], ['grupo' => 'vendas'], ['grupo' => 'equipe'], ['item' => 'comentarios'],
        ['item' => 'planos'], ['item' => 'modalidades'], ['item' => 'cadastro-facial'], ['item' => 'configuracoes']];
    $grupoDoItem = [];
    foreach ($grupos as $grupo => $itens) foreach ($itens as $item) $grupoDoItem[$item] = $grupo;

    // Celular: 500 px é a menor janela que o Chrome headless abre; já está abaixo dos breakpoints
    // de celular do painel (560 e 991 px), os mesmos que valem para 360-430 px.
    foreach (['desktop' => [1440, 1000], 'celular' => [500, 900]] as $tela => [$largura, $altura]) {
        foreach ($paginas as $cargo => $arquivo) {
            $r = $resultado($abrir($arquivo, '', $largura, $altura));
            $rotulo = "$cargo ($tela)";
            pnCheck($r['erros'] === [], "$rotulo sem erros de JavaScript: " . implode(' | ', $r['erros']));
            pnCheck($r['secaoInicial'] === $r['secaoInicialEsperada'], "$rotulo abre na primeira página do menu ({$r['secaoInicial']})");
            // Cada item da sidebar abre a sua própria seção e fica sozinho em destaque.
            pnCheck(count($r['navegacao']) === count($r['menu']) - count($r['links']), "$rotulo: todos os itens do menu testados");
            foreach ($r['navegacao'] as $passo) {
                pnCheck($passo['itemAtivo'] && $passo['ativos'] === 1 && $passo['rota'] === $passo['chave'] && $passo['secao'] === $passo['esperada'],
                    "$rotulo: {$passo['chave']} abre a seção certa e fica destacado ({$passo['secao']})");
            }
            if (in_array($cargo, ['diretor', 'admin'], true)) {
                $estrutura = array_map(static fn(array $e): array => isset($e['grupo']) ? ['grupo' => $e['grupo']] : ['item' => $e['item']], $r['estruturaInicial']);
                pnCheck($estrutura === $estruturaAdmin, "$rotulo: sidebar com Visão Geral, Clientes, Vendas, Equipe, Comentários e os itens independentes");
                foreach ($r['estruturaInicial'] as $e) {
                    if (!isset($e['grupo'])) continue;
                    pnCheck($e['rotulo'] === $rotulosGrupos[$e['grupo']] && $e['itens'] === $grupos[$e['grupo']] && $e['icone'] && $e['seta'] && $e['aberto'] === false,
                        "$rotulo: categoria {$e['rotulo']} com ícone, seta e os itens certos, começa recolhida");
                }
                pnCheck(!str_contains(json_encode($r['estruturaInicial']), 'ngajamento'), "$rotulo: sem a categoria Engajamento");
                foreach ($r['navegacao'] as $passo) {
                    $grupo = $grupoDoItem[$passo['chave']] ?? null;
                    if ($grupo === null) {
                        pnCheck($passo['grupo'] === null && $passo['gruposMarcados'] === 0 && $passo['gruposAbertos'] === 0, "$rotulo: {$passo['chave']} fora das categorias, nenhuma marcada ou aberta");
                    } else {
                        $g = $passo['grupo'];
                        pnCheck($g['grupo'] === $grupo && $g['aberto'] && $g['temAtivo'] && $g['expandido'] === 'true' && $g['oculto'] === false
                            && $passo['gruposMarcados'] === 1 && $passo['gruposAbertos'] === 1, "$rotulo: {$passo['chave']} deixa só a categoria $grupo aberta e marcada");
                    }
                }
                pnCheck($r['recolhido']['aberto'] === false && $r['recolhido']['oculto'] === true && $r['recolhido']['expandido'] === 'false' && $r['recolhido']['temAtivo']
                    && $r['recolhido']['secao'] === $r['recolhido']['item'] && $r['recolhido']['mesmaRota'], "$rotulo: recolher a categoria não navega e ela continua marcada");
                pnCheck($r['reaberto']['aberto'] && $r['reaberto']['oculto'] === false, "$rotulo: a categoria volta a expandir");
                pnCheck($r['outroAberto']['aberto'] && !$r['outroAberto']['temAtivo'] && $r['outroAberto']['secao'] === $r['recolhido']['item'] && $r['outroAberto']['mesmaRota']
                    && $r['outroFechado']['aberto'] === false, "$rotulo: abrir e fechar outra categoria não muda a página");
                pnCheck(in_array('Alunos', $r['busca'], true) && in_array('Pagamentos', $r['busca'], true), "$rotulo: a pesquisa encontra as páginas pelo nome da categoria");
            } else {
                pnCheck(array_column($r['estruturaInicial'], 'item') === $r['menu'] && !array_filter($r['estruturaInicial'], static fn(array $e): bool => isset($e['grupo'])),
                    "$rotulo: sidebar sem categorias, na ordem de sempre");
            }
            if ($tela === 'celular') {
                pnCheck($r['celular']['abriu'] && $r['celular']['fechouAoNavegar'] && $r['celular']['secao'] === $r['celular']['destino']
                    && ($r['celular']['continuaAbertaAposGrupo'] ?? true), "$rotulo: sidebar do celular abre, a categoria não a fecha e escolher a página fecha");
            }
            if (isset($r['cashback'])) {
                $c = $r['cashback'];
                pnCheck($c['contadorInicial'] === (string) $c['total'] && $c['soCreditos'], "$rotulo: contagem das movimentações e filtro por tipo");
                pnCheck($c['limpo']['linhas'] === $c['total'] && $c['limpo']['usuario'] === '' && $c['limpo']['tipo'] === '' && $c['limpo']['contador'] === (string) $c['total'], "$rotulo: \"Limpar filtros\" mostra tudo de novo");
                pnCheck($c['porIdFormatado'] !== [] && array_unique($c['porIdFormatado']) === [(string) $clienteB['id']], "$rotulo: filtro por ID aceita o formato #0000");
                $comClientes = in_array($cargo, ['diretor', 'admin', 'recepcionista'], true);
                pnCheck($c['temSaldoPorCliente'] === $comClientes, "$rotulo: saldo por cliente " . ($comClientes ? 'presente' : 'ausente'));
                if ($comClientes) {
                    pnCheck($c['historico']['linhas'] === $linhasA && $c['historico']['todasDoCliente'] && $c['historico']['tipoLimpo']
                        && $c['historico']['usuario'] === (string) $clienteA['id'] && $c['historico']['contador'] === "$linhasA de {$c['total']}", "$rotulo: \"Ver histórico\" mostra só as movimentações do cliente");
                    pnCheck($c['semResultado']['linhas'] === 0 && $c['semResultado']['mensagem'] && $c['buscaNome'] === [(string) $clienteA['id']], "$rotulo: busca de cliente sem resultado e por nome");
                }
            }
        }
    }

    // Capturas de tela para conferência visual (opcional).
    $fotos = getenv('ONEFIT_TESTE_FOTOS');
    if ($fotos && is_dir($fotos)) {
        // As de celular saem com 500 px de largura e zoom equivalente a uma tela de 390 px.
        foreach ([['diretor', '?section=cashbacks&modo=foto', 1440, 2200, 'cashback-desktop'], ['diretor', '?section=cashbacks&modo=foto&zoom=1.282', 500, 4100, 'cashback-celular'],
                  ['diretor', '?section=produtos&modo=foto', 1440, 1000, 'sidebar-desktop'], ['diretor', '?section=cashbacks&modo=foto&menu=abrir&grupo=equipe&zoom=1.282', 500, 1080, 'sidebar-celular'],
                  ['recepcionista', '?section=cashbacks&modo=foto', 1440, 2000, 'cashback-recepcao'], ['vendedor', '?section=cashbacks&modo=foto', 1440, 1600, 'cashback-vendedor']] as [$cargo, $query, $largura, $altura, $nome]) {
            $abrir($paginas[$cargo], $query, $largura, $altura, $fotos . DIRECTORY_SEPARATOR . $nome . '.png');
            pnCheck(is_file($fotos . DIRECTORY_SEPARATOR . $nome . '.png'), "Captura $nome");
        }
    }
} finally {
    foreach ($paginas as $arquivo) {
        @unlink($webApp . '/' . $arquivo);
    }
    if (is_dir($perfil)) {
        exec('cmd /c rmdir /s /q ' . escapeshellarg($perfil));
    }
}
echo "Sidebar e Cashback no navegador ({$navegador}): $checks verificações passaram.\n";
