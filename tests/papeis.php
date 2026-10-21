<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Regras centrais de cargos (config/papeis.php) e bloqueio de caminhos
// alternativos. Sem banco de dados: só funções puras e leitura de arquivos.
require __DIR__ . '/../config/papeis.php';

$checks = 0;
function papeisCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

// --- Política de cada cargo -------------------------------------------------
$politicas = onefitPoliticasPapeis();
papeisCheck(array_keys($politicas) === ['aluno', 'diretor', 'admin', 'vendedor', 'professor', 'profissional', 'recepcionista', 'faxineira'], 'Cargos definidos (mais o valor legado profissional)');
$esperado = [
    // papel => [painel, opera_cadastro_facial, catraca]
    'aluno' => ['aluno', false, 'matricula'],
    'diretor' => ['admin', true, 'conta_ativa'],
    'admin' => ['admin', true, 'conta_ativa'],
    'vendedor' => ['vendedor', false, 'conta_ativa'],
    'professor' => ['profissional', false, 'cadastro_profissional'],
    'profissional' => ['profissional', false, 'cadastro_profissional'],
    'recepcionista' => ['recepcionista', true, 'conta_ativa'],
    'faxineira' => [null, false, 'conta_ativa'],
];
foreach ($esperado as $papel => [$painel, $operaFacial, $catraca]) {
    papeisCheck(onefitPainelDoPapel($papel) === $painel, "Painel de $papel");
    papeisCheck(onefitPapelOperaCadastroFacial($papel) === $operaFacial, "Cadastro facial de $papel");
    papeisCheck($politicas[$papel]['catraca'] === $catraca, "Regra da catraca de $papel");
}
papeisCheck($politicas['faxineira']['login'] === false, 'Faxineira não faz login');
papeisCheck(onefitPapeisCom('opera_cadastro_facial') === ['diretor', 'admin', 'recepcionista'], 'Só diretor, admin e recepcionista operam o cadastro facial');
// Todo painel da política tem menu (dashboard.js) e seções próprias (dashboard.php).
$dashboardJs = file_get_contents(__DIR__ . '/../assets/js/dashboard.js');
$dashboardPhp = file_get_contents(__DIR__ . '/../pages/dashboard/dashboard.php');
foreach (array_unique(array_filter(array_column($politicas, 'painel'))) as $painelPolitica) {
    papeisCheck(preg_match('/^ {4}' . preg_quote($painelPolitica, '/') . ': \{\r?$/m', $dashboardJs) === 1, "Painel $painelPolitica tem menu em dashboard.js");
    papeisCheck(str_contains($dashboardPhp, "\$perfilLogado === '$painelPolitica'"), "Painel $painelPolitica tem seções em dashboard.php");
}
papeisCheck(!in_array('profissional', onefitPapeisCom('opera_cadastro_facial'), true), 'Professor não opera o cadastro facial');

// --- Hierarquia: diretor > admin > demais cargos -------------------------------
papeisCheck(onefitPapelPermitido('diretor', ['admin']), 'Diretor cumpre o que exige admin (rotas do painel administrativo)');
papeisCheck(!onefitPapelPermitido('admin', ['diretor']), 'Admin não cumpre o que exige diretor');
papeisCheck(onefitPapelPermitido('diretor', ['diretor']) && onefitPapelPermitido('admin', ['admin']), 'Cada um cumpre o próprio cargo');
foreach (['aluno', 'professor', 'profissional', 'vendedor', 'recepcionista', 'faxineira', '', null, 'Diretor', 'DIRETOR', ' diretor', 'superadmin'] as $outro) {
    papeisCheck(!onefitPapelPermitido($outro, ['admin']) && !onefitPapelPermitido($outro, ['diretor']), 'Sem poderes administrativos: ' . var_export($outro, true));
    papeisCheck(!onefitPapelDiretor($outro) && !onefitPapelAdministrativo($outro), 'Não é cargo administrativo: ' . var_export($outro, true));
}
papeisCheck(onefitPapelDiretor('diretor') && !onefitPapelDiretor('admin'), 'Só o cargo diretor é diretor');
papeisCheck(onefitPapelAdministrativo('diretor') && onefitPapelAdministrativo('admin'), 'Admin e diretor são cargos administrativos');
papeisCheck(onefitPapelPermitido('vendedor', ['admin', 'vendedor']) && !onefitPapelPermitido('vendedor', ['diretor']), 'Inclusão não vale ao contrário');

// --- Funcionários: o que cada nível atribui e gerencia -------------------------
papeisCheck(onefitCargosAtribuiveisPorAdmin() === ['vendedor', 'professor', 'recepcionista', 'faxineira'], 'Cargos atribuíveis por admin comum');
papeisCheck(onefitCargosAtribuiveis('admin') === onefitCargosAtribuiveisPorAdmin(), 'Admin atribui só os cargos comuns');
papeisCheck(onefitCargosAtribuiveis('diretor') === ['diretor', 'admin', 'vendedor', 'professor', 'recepcionista', 'faxineira'], 'Diretor atribui também admin e diretor');
foreach (['aluno', 'professor', 'profissional', 'vendedor', 'recepcionista', 'faxineira', '', null, 'gerente'] as $semNivel) {
    papeisCheck(onefitCargosAtribuiveis($semNivel) === [], 'Não atribui cargo nenhum: ' . var_export($semNivel, true));
}
foreach (['admin', 'diretor', 'aluno', 'profissional', '', null, 'Professor', 'superadmin'] as $naoAtribuivel) {
    papeisCheck(!in_array($naoAtribuivel, onefitCargosAtribuiveisPorAdmin(), true), 'Não atribuível por admin: ' . var_export($naoAtribuivel, true));
}
papeisCheck(!in_array('aluno', onefitCargosAtribuiveis('diretor'), true) && !in_array('profissional', onefitCargosAtribuiveis('diretor'), true), 'Nem o diretor atribui aluno ou o legado profissional');
papeisCheck(onefitFuncionarioGerenciavelPorAdmin('profissional') && onefitPodeGerenciarConta('diretor', 'profissional'), 'Conta legada profissional continua gerenciável como professor');
foreach (['admin', 'diretor', 'aluno', '', null, 'gerente'] as $naoGerenciavel) {
    papeisCheck(!onefitFuncionarioGerenciavelPorAdmin($naoGerenciavel) && !onefitPodeGerenciarConta('admin', $naoGerenciavel), 'Não gerenciável por admin comum: ' . var_export($naoGerenciavel, true));
}
papeisCheck(onefitPodeGerenciarConta('diretor', 'admin') && onefitPodeGerenciarConta('diretor', 'diretor'), 'Diretor gerencia contas admin e diretor');
papeisCheck(!onefitPodeGerenciarConta('diretor', 'aluno') && !onefitPodeGerenciarConta('diretor', 'gerente'), 'Diretor não gerencia aluno nem cargo desconhecido nesta tela');
foreach (['recepcionista', 'vendedor', 'professor', 'aluno', null] as $operadorComum) {
    papeisCheck(!onefitPodeGerenciarConta($operadorComum, 'vendedor'), 'Sem gestão de funcionários: ' . var_export($operadorComum, true));
}
papeisCheck(onefitPapeisFuncionarios() === ['vendedor', 'professor', 'recepcionista', 'faxineira', 'profissional', 'admin', 'diretor'], 'Cargos listados na tela Funcionários');
$catraca = [
    // [cargo, status, cadastro profissional ativo?, liberado, motivo]
    ['vendedor', 'ativo', false, true, 'ok'],
    ['recepcionista', 'ativo', false, true, 'ok'],
    ['faxineira', 'ativo', false, true, 'ok'],
    ['admin', 'ativo', false, true, 'ok'],
    ['diretor', 'ativo', false, true, 'ok'],
    ['diretor', 'inativo', false, false, 'conta_inativa'],
    ['faxineira', 'inativo', false, false, 'conta_inativa'],
    ['vendedor', 'bloqueado', false, false, 'conta_inativa'],
    ['professor', 'ativo', true, true, 'ok'],
    ['professor', 'ativo', false, false, 'cadastro_profissional_inativo'],
    ['profissional', 'ativo', false, false, 'cadastro_profissional_inativo'],
    ['professor', 'inativo', true, false, 'conta_inativa'],
    ['aluno', 'ativo', false, false, 'requer_matricula'],
    ['gerente', 'ativo', true, false, 'cargo_desconhecido'],
    [null, 'ativo', true, false, 'cargo_desconhecido'],
];
foreach ($catraca as [$cargo, $status, $cadastroAtivo, $liberado, $motivo]) {
    papeisCheck(onefitSituacaoCatracaFuncionario($cargo, $status, $cadastroAtivo) === ['liberado' => $liberado, 'motivo' => $motivo], 'Catraca prevista: ' . var_export($cargo, true) . "/$status");
}

// --- Cadastro facial: operador x pessoa (D5; contas administrativas só pelo diretor) ---
$conta = static fn(?string $cargo, string $status = 'ativo'): array => ['tipo_usuario' => $cargo, 'status' => $status];
$naoAdmins = ['aluno', 'professor', 'profissional', 'vendedor', 'recepcionista', 'faxineira'];
foreach (['diretor', 'admin', 'recepcionista'] as $operador) {
    foreach ($naoAdmins as $pessoa) {
        papeisCheck(onefitAvaliarCadastroFacial($conta($operador), $conta($pessoa)) === ['permitido' => true, 'motivo' => 'ok'], "Facial: $operador fotografa $pessoa ativo");
        papeisCheck(onefitAvaliarCadastroFacial($conta($operador), $conta($pessoa, 'inativo'))['motivo'] === 'pessoa_inativa', "Facial: $operador recusa $pessoa inativo");
        papeisCheck(onefitAvaliarCadastroFacial($conta($operador), $conta($pessoa, 'bloqueado'))['motivo'] === 'pessoa_inativa', "Facial: $operador recusa $pessoa bloqueado");
    }
    papeisCheck(onefitAvaliarCadastroFacial($conta($operador), null)['motivo'] === 'pessoa_inexistente', "Facial: $operador recusa pessoa inexistente");
    foreach ([null, '', 'gerente', 'Admin', 'Diretor'] as $desconhecido) {
        papeisCheck(onefitAvaliarCadastroFacial($conta($operador), $conta($desconhecido))['motivo'] === 'cargo_desconhecido', "Facial: $operador recusa cargo " . var_export($desconhecido, true));
    }
}
// Contas administrativas (inclusive a do próprio operador): só um diretor ativo.
foreach (['admin', 'diretor'] as $administrativa) {
    papeisCheck(onefitAvaliarCadastroFacial($conta('diretor'), $conta($administrativa))['permitido'], "Facial: diretor fotografa $administrativa");
    papeisCheck(onefitAvaliarCadastroFacial($conta('admin'), $conta($administrativa))['motivo'] === 'conta_admin_restrita', "Facial: admin não fotografa $administrativa");
    papeisCheck(onefitAvaliarCadastroFacial($conta('recepcionista'), $conta($administrativa))['motivo'] === 'conta_admin_restrita', "Facial: recepcionista não fotografa $administrativa");
    papeisCheck(onefitAvaliarCadastroFacial($conta('diretor', 'inativo'), $conta($administrativa))['motivo'] === 'operador_sem_permissao', "Facial: diretor inativo não fotografa $administrativa");
}
foreach (['professor', 'profissional', 'aluno', 'vendedor', 'faxineira', 'gerente', '', null] as $semPermissao) {
    papeisCheck(onefitAvaliarCadastroFacial($conta($semPermissao), $conta('aluno'))['motivo'] === 'operador_sem_permissao', 'Facial: ' . var_export($semPermissao, true) . ' não opera');
}
foreach (['inativo', 'bloqueado'] as $statusOperador) {
    papeisCheck(onefitAvaliarCadastroFacial($conta('admin', $statusOperador), $conta('aluno'))['motivo'] === 'operador_sem_permissao', "Facial: admin $statusOperador não opera");
    papeisCheck(onefitAvaliarCadastroFacial($conta('recepcionista', $statusOperador), $conta('recepcionista'))['motivo'] === 'operador_sem_permissao', "Facial: recepcionista $statusOperador não opera");
}
papeisCheck(onefitAvaliarCadastroFacial(null, $conta('aluno'))['motivo'] === 'operador_sem_permissao', 'Facial: sem operador');
// A foto não muda a regra da catraca nem dá login: faxineira continua sem painel.
papeisCheck(onefitPainelDoPapel('faxineira') === null, 'Facial não concede painel');
// Painel da recepção: só cadastro facial e configurações; nenhuma operação de admin.
papeisCheck(onefitPainelDoPapel('recepcionista') === 'recepcionista' && !onefitPapelPermitido('recepcionista', ['admin']) && !onefitFuncionarioGerenciavelPorAdmin('admin'), 'Recepção tem painel próprio, sem poderes de admin');

// --- Notificação de compra: rótulo pela política, sem "não é profissional, então é aluno" ---
require_once __DIR__ . '/../config/notificacoes.php';
$rotulos = ['aluno' => 'Aluno', 'professor' => 'Profissional', 'profissional' => 'Profissional',
    'admin' => null, 'vendedor' => null, 'recepcionista' => null, 'faxineira' => null, '' => null, 'gerente' => null];
foreach ($rotulos as $cargo => $rotulo) {
    papeisCheck(notificacaoTipoComprador($cargo) === $rotulo, "Rótulo do comprador: '$cargo'");
}
papeisCheck(notificacaoTipoComprador(null) === null, 'Rótulo do comprador: nulo');

// --- Cargo desconhecido nunca vira aluno ------------------------------------
foreach ([null, '', ' ', 'Aluno', 'ADMIN', ' admin', 'aluno ', 'superadmin', '0', 'root', 'funcionario'] as $desconhecido) {
    $rotulo = var_export($desconhecido, true);
    papeisCheck(onefitPolitica($desconhecido) === null, "Política inexistente para $rotulo");
    papeisCheck(onefitPapelCanonico($desconhecido) === null, "Canônico nulo para $rotulo");
    papeisCheck(onefitPainelDoPapel($desconhecido) === null, "Sem painel (nem de aluno) para $rotulo");
    papeisCheck(!onefitPapelPermitido($desconhecido, ['aluno']), "Não é aluno: $rotulo");
    papeisCheck(!onefitPapelPermitido($desconhecido, array_keys($politicas)), "Sem permissão alguma: $rotulo");
    papeisCheck(!onefitPapelOperaCadastroFacial($desconhecido), "Sem cadastro facial: $rotulo");
}

// --- Comparação de cargos (profissional = professor) ------------------------
papeisCheck(onefitPapelCanonico('profissional') === 'professor', 'Legado mapeia para professor');
papeisCheck(onefitPapelPermitido('profissional', ['professor']) && onefitPapelPermitido('professor', ['profissional']), 'profissional e professor são o mesmo cargo');
papeisCheck(onefitPapelPermitido('admin', ['admin']) && !onefitPapelPermitido('admin', ['aluno', 'vendedor']), 'Lista de cargos permitidos');
papeisCheck(!onefitPapelPermitido('aluno', []), 'Lista vazia não permite nada');
papeisCheck(!onefitPapelPermitido('aluno', ['', 'xyz']), 'Itens desconhecidos na lista não permitem nada');

// --- Avaliação da sessão (sem efeitos colaterais) ----------------------------
$casos = [
    // [linha do banco, cargo na sessão, válida?, motivo, cargo resultante]
    [null, 'admin', false, 'conta_inexistente', null],
    [['tipo_usuario' => 'admin', 'status' => 'inativo'], 'admin', false, 'conta_inativa', null],
    [['tipo_usuario' => 'admin', 'status' => 'bloqueado'], 'admin', false, 'conta_inativa', null],
    [['tipo_usuario' => 'admin', 'status' => null], 'admin', false, 'conta_inativa', null],
    [['tipo_usuario' => '', 'status' => 'ativo'], 'aluno', false, 'cargo_desconhecido', null],
    [['tipo_usuario' => null, 'status' => 'ativo'], 'aluno', false, 'cargo_desconhecido', null],
    [['tipo_usuario' => 'gerente', 'status' => 'ativo'], 'aluno', false, 'cargo_desconhecido', null],
    [['tipo_usuario' => 'faxineira', 'status' => 'ativo'], 'faxineira', false, 'sem_acesso_ao_painel', null],
    [['tipo_usuario' => 'recepcionista', 'status' => 'ativo'], 'recepcionista', true, 'ok', 'recepcionista'],
    [['tipo_usuario' => 'recepcionista', 'status' => 'bloqueado'], 'recepcionista', false, 'conta_inativa', null],
    [['tipo_usuario' => 'aluno', 'status' => 'ativo'], 'aluno', true, 'ok', 'aluno'],
    [['tipo_usuario' => 'aluno', 'status' => 'ativo'], 'admin', true, 'cargo_alterado', 'aluno'],
    [['tipo_usuario' => 'admin', 'status' => 'ativo'], 'aluno', true, 'cargo_alterado', 'admin'],
    [['tipo_usuario' => 'professor', 'status' => 'ativo'], 'profissional', true, 'cargo_alterado', 'professor'],
    [['tipo_usuario' => 'vendedor', 'status' => 'ativo'], null, true, 'cargo_alterado', 'vendedor'],
];
foreach ($casos as $i => [$linha, $sessao, $valida, $motivo, $papel]) {
    $avaliacao = onefitAvaliarSessao($linha, $sessao);
    papeisCheck($avaliacao === ['valida' => $valida, 'motivo' => $motivo, 'papel' => $papel], "Avaliação de sessão, caso $i");
}

// --- Caminhos alternativos bloqueados (leitura do código) --------------------
$raiz = dirname(__DIR__);
$ler = static fn(string $arquivo): string => (string) file_get_contents($raiz . '/' . $arquivo);

// Nenhum código da aplicação grava usuarios.tipo_usuario por UPDATE.
$gravacoesDeCargo = [];
foreach (['pages', 'config', 'components', 'database'] as $pasta) {
    if (!is_dir($raiz . '/' . $pasta)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/' . $pasta, FilesystemIterator::SKIP_DOTS)) as $arquivo) {
        if ($arquivo->getExtension() !== 'php') {
            continue;
        }
        // Só a cláusula SET grava; tipo_usuario no WHERE é proteção, não gravação.
        preg_match_all('/UPDATE\s+`?usuarios`?\s+SET\s+((?:(?!\bWHERE\b)[^;])*)/is', (string) file_get_contents($arquivo->getPathname()), $sets);
        foreach ($sets[1] as $set) {
            if (preg_match('/\btipo_usuario\s*=/i', $set)) {
                $gravacoesDeCargo[] = str_replace('\\', '/', substr($arquivo->getPathname(), strlen($raiz) + 1));
                break;
            }
        }
    }
}
// Fluxos autorizados: a tela Funcionários (operador e alvo conferidos com trava) e a
// recuperação do diretor pela linha de comando (só admin ativo → diretor, só sem diretor ativo).
sort($gravacoesDeCargo);
papeisCheck($gravacoesDeCargo === ['database/diretor-recuperar.php', 'pages/dashboard/includes/funcionarios.php'], 'Gravação de cargo fora do fluxo autorizado: ' . implode(', ', $gravacoesDeCargo));
$recuperacao = $ler('database/diretor-recuperar.php');
papeisCheck(str_starts_with($recuperacao, "<?php") && str_contains($recuperacao, "if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }"), 'Recuperação do diretor só pela linha de comando');
papeisCheck(str_contains($recuperacao, 'onefitContarDiretoresAtivos($conn, true) > 0') && str_contains($recuperacao, "\$alvo['tipo_usuario'] !== 'admin'")
    && str_contains($recuperacao, "UPDATE usuarios SET tipo_usuario = 'diretor' WHERE id_usuario = ? AND tipo_usuario = 'admin' AND status = 'ativo'"), 'Recuperação: só sem diretor ativo e só de admin ativo para diretor');
papeisCheck(str_contains($recuperacao, "'PROMOVER-' . \$id") && str_contains($recuperacao, "onefitEnv('DB_NAME')"), 'Recuperação exige confirmação e o banco do .env');

foreach (['pages/dashboard/funcionalidades/permissoes.php', 'pages/dashboard/funcionalidades/funcoes.php'] as $handler) {
    papeisCheck(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE)\b/i', $ler($handler)), "$handler não grava no banco");
    papeisCheck(str_contains($ler($handler), "bo_redirect('dashboard')"), "$handler recusa e redireciona");
}

$dashboardJs = $ler('assets/js/dashboard.js');
papeisCheck(!str_contains($dashboardJs, "key: 'permissoes'") && !str_contains($dashboardJs, "key: 'funcoes'"), 'Menu sem Permissões e Funções');
preg_match('/\n    profissional: \{(.*?)\n    \},/s', $dashboardJs, $menuProfissional);
papeisCheck(isset($menuProfissional[1]) && !str_contains($menuProfissional[1], 'cadastro-facial'), 'Menu do professor sem cadastro facial');
preg_match('/\n    admin: \{(.*?)\n    \},/s', $dashboardJs, $menuAdmin);
papeisCheck(isset($menuAdmin[1]) && str_contains($menuAdmin[1], "key: 'cadastro-facial'"), 'Menu do admin mantém cadastro facial');

$sectionAdmin = $ler('pages/dashboard/components/section-admin.php');
papeisCheck(!str_contains($sectionAdmin, 'data-section="permissoes"') && !str_contains($sectionAdmin, 'data-section="funcoes"'), 'Seções Permissões e Funções fora do HTML');
papeisCheck(!str_contains($ler('pages/dashboard/funcionalidades/confirmar-exclusao.php'), "'permissoes' =>"), 'Confirmação de exclusão não aceita permissões');
papeisCheck(!str_contains($ler('pages/dashboard/includes/admin-forms.php'), "bo_form_action('permissoes.php')"), 'Nenhum formulário aponta para permissões');

$dashboardPhp = $ler('pages/dashboard/dashboard.php');
papeisCheck(!preg_match('/\$perfilLogado\s*=\s*[\'"]aluno[\'"]/', $dashboardPhp), 'Painel não converte cargo desconhecido em aluno');
papeisCheck(str_contains($dashboardPhp, 'onefitPainelDoPapel('), 'Painel usa a política central');
papeisCheck(str_contains($ler('config/conn.php'), 'onefitSessaoRevalidar($conn);'), 'Conexão revalida a sessão');
papeisCheck(str_contains($ler('pages/login/login.php'), 'onefitPainelDoPapel($usuario[\'tipo_usuario\'])'), 'Login aplica a política');
foreach (['pages/auth/senha/processar-recuperacao.php', 'pages/auth/email/reenviar-verificacao.php'] as $fluxo) {
    papeisCheck(str_contains($ler($fluxo), "onefitPainelDoPapel(\$usuario['tipo_usuario']) !== null"), "$fluxo só envia para quem pode entrar");
}

// Scripts de teste/depuração nunca rodam pela web.
foreach (array_merge(glob($raiz . '/tests/*.php'), [$raiz . '/config/x.php']) as $script) {
    papeisCheck(str_contains((string) file_get_contents($script), "PHP_SAPI !== 'cli'"), 'Trava de linha de comando em ' . basename($script));
}

$htaccess = $ler('.htaccess');
papeisCheck(str_contains($htaccess, '<FilesMatch "^\.">'), '.htaccess bloqueia arquivos ocultos');
preg_match_all('/^RedirectMatch 403 (\S+)\s*$/m', $htaccess, $regrasHt);
papeisCheck(count($regrasHt[1]) === 4, 'Quatro regras RedirectMatch 403');
foreach ($regrasHt[1] as $regra) {
    papeisCheck(str_starts_with($regra, '(?i)'), "Regra sem diferenciar maiúsculas: $regra");
}
// Mesmo PCRE do Apache: caminhos sensíveis bloqueados, rotas legítimas livres.
$bloqueado = static function (string $caminho) use ($regrasHt): bool {
    if (str_starts_with(basename($caminho), '.')) {
        return true; // <FilesMatch "^\.">
    }
    foreach ($regrasHt[1] as $regra) {
        if (preg_match('#' . $regra . '#', $caminho)) {
            return true;
        }
    }
    return false;
};
foreach ([
    '/AN25/OneFit/.env', '/AN25/OneFit/.git/HEAD', '/AN25/OneFit/ENV~1', '/an25/onefit/ENV~1', '/an25/onefit/GIT~1/HEAD',
    '/AN25/OneFit/pages/errors/storage/logs/404.log', '/an25/onefit/PAGES/ERRORS/STORAGE/LOGS/404.LOG', '/AN25/OneFit/pages/errors/storage/',
    '/AN25/OneFit/config/x.php', '/AN25/OneFit/Config/countries.json', '/AN25/OneFit/docs/i18n.md', '/AN25/OneFit/vendor/autoload.php',
    '/AN25/OneFit/tests/checkout.php', '/AN25/OneFit/database/migrate-interface.php',
] as $sensivel) {
    papeisCheck($bloqueado($sensivel), "Bloqueado: $sensivel");
}
foreach ([
    '/AN25/OneFit/', '/AN25/OneFit/index.php', '/AN25/OneFit/config/logout.php', '/AN25/OneFit/pages/errors/404.php',
    '/AN25/OneFit/pages/login/login.php', '/AN25/OneFit/pages/dashboard/dashboard.php', '/AN25/OneFit/pages/dashboard/actions/fotos-faciais.php',
    '/AN25/OneFit/pages/marketplace/marketplace.php', '/AN25/OneFit/pages/carrinho/carrinho.php', '/AN25/OneFit/pages/auth/senha/esqueci-senha.php',
    '/AN25/OneFit/assets/css/dashboard.css', '/AN25/OneFit/assets/js/dashboard.js', '/AN25/OneFit/assets/img/uploads/perfil/foto.png',
] as $legitimo) {
    papeisCheck(!$bloqueado($legitimo), "Liberado: $legitimo");
}
papeisCheck(in_array('/pages/errors/storage/logs/404.log', array_map('trim', file($raiz . '/.gitignore')), true), '.gitignore usa o caminho real do 404.log');

// --- Traduções das mensagens novas ------------------------------------------
$novas = [
    'Sua sessão foi encerrada. Entre novamente para continuar.',
    'Esta conta não tem acesso ao painel. Fale com a administração da {marca}.',
    'As telas Permissões e Funções foram desativadas e não alteram mais cargos.',
    'Esta tela gerencia apenas alunos. Funcionários e administradores ficam na tela Funcionários.',
];
foreach (onefitIdiomas() as $idioma) {
    $catalogo = require $raiz . '/config/locales/' . $idioma . '.php';
    foreach ($novas as $texto) {
        papeisCheck(isset($catalogo[$texto]) && $catalogo[$texto] !== '', "Tradução $idioma: $texto");
        if ($idioma !== 'pt-BR') {
            papeisCheck($catalogo[$texto] !== $texto, "Texto traduzido em $idioma: $texto");
        }
    }
}

echo "Papéis: $checks verificações passaram.\n";
