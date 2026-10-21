<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Hierarquia diretor > admin nas regras da tela Funcionários e do cadastro facial, contra o
// banco descartável onefit_interface_test_* (tests/funcionarios-preparar.ps1 aplica antes as
// migrações de funcionários e do diretor). Nenhum e-mail é enviado.
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
require __DIR__ . '/../pages/dashboard/includes/funcionarios.php';
require __DIR__ . '/../pages/dashboard/includes/fotos-faciais.php';
onefitEnv('APP_URL');
foreach (['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'] as $variavel) {
    putenv($variavel . '=');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
if (!bo_funcionarios_migracao($db)['ok'] || !bo_funcionarios_enum_aceita($db, 'diretor')) {
    throw new RuntimeException('Rode antes tests/funcionarios-preparar.ps1 (migrações no banco de teste).');
}
$db->query("CREATE TABLE IF NOT EXISTS usuarios_fotos_faciais (
    id_foto_facial INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    imagem_blob MEDIUMBLOB NULL,
    KEY idx_usuario (id_usuario),
    CONSTRAINT fk_fotos_faciais_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$checks = 0;
function dCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
function dRecusa(callable $acao, string $trecho, string $message, string $classe = FuncionarioRecusado::class): void
{
    try {
        $acao();
    } catch (Throwable $recusa) {
        dCheck($recusa instanceof $classe && ($trecho === '' || str_contains($recusa->getMessage(), $trecho)), $message . ' (' . get_class($recusa) . ': ' . $recusa->getMessage() . ')');
        return;
    }
    dCheck(false, $message . ' (nenhuma recusa)');
}
function dContar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}
function dCpf(int $n): string
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
function dUsuario(mysqli $db, int $id): array
{
    return $db->query("SELECT * FROM usuarios WHERE id_usuario = $id")->fetch_assoc();
}
function dJpeg(): string
{
    $img = imagecreatetruecolor(48, 48);
    imagefill($img, 0, 0, imagecolorallocate($img, 40, 90, 160));
    ob_start();
    imagejpeg($img);
    return ob_get_clean();
}

$run = random_int(100000, 899999);
$seq = 0;
$senha = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$conta = static function (string $cargo, string $status = 'ativo') use ($db, $run, &$seq, $senha): int {
    $seq++;
    $nome = "Hierarquia $run $seq";
    $email = "dir{$run}x{$seq}@example.test";
    $cpf = dCpf($run * 100 + $seq);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status)
        VALUES (?, ?, 'BR', '1990-05-10', 'outro', ?, 'Rua de Teste, 10', 'São Paulo/SP', ?, 1, '11987654321', ?, ?)");
    $stmt->bind_param('ssssss', $nome, $senha, $cpf, $email, $cargo, $status);
    $stmt->execute();
    return (int) $stmt->insert_id;
};
$entrada = static function (string $cargo, array $extra = []) use ($run, &$seq): array {
    $seq++;
    return $extra + [
        'nome' => "Nova Conta $run $seq", 'email' => "nova{$run}x{$seq}@example.test", 'cpf' => dCpf($run * 100 + $seq),
        'celular' => '11987654321', 'nascimento' => '1990-05-10', 'genero' => 'outro', 'nacionalidade' => 'BR',
        'endereco' => 'Rua de Teste, 10', 'cidade' => 'São Paulo', 'estado' => 'SP', 'cargo' => $cargo,
    ];
};
$formulario = static fn(array $c, array $mudancas = []): array => $mudancas + [
    'nome' => $c['nome'], 'email' => $c['email'], 'cpf' => $c['cpf'], 'celular' => $c['celular'],
    'nascimento' => $c['data_nascimento'], 'genero' => $c['genero'], 'nacionalidade' => $c['nacionalidade'],
    'endereco' => $c['endereco'], 'cidade' => explode('/', $c['cidade_estado'])[0], 'estado' => explode('/', $c['cidade_estado'])[1],
    'cargo' => onefitPapelCanonico($c['tipo_usuario']),
];
$enviados = [];
$enviar = static function (string $email, string $nome, array $tokens) use (&$enviados): bool {
    $enviados[] = ['email' => $email, 'tokens' => $tokens];
    return true;
};
$auditoria = static fn(): int => dContar($db, "SELECT COUNT(*) FROM auditoria_eventos WHERE categoria = 'funcionario'");

$diretorA = $conta('diretor');
$diretorB = $conta('diretor');
$diretorInativo = $conta('diretor', 'inativo');
$adminX = $conta('admin');
$adminY = $conta('admin');
$vendedor = $conta('vendedor');
$aluno = $conta('aluno');

// --- Administrador: operação comum, nunca contas administrativas --------------------------
$protegidas = [$diretorA => dUsuario($db, $diretorA), $diretorB => dUsuario($db, $diretorB), $adminY => dUsuario($db, $adminY)];
$eventosAntes = $auditoria();
foreach (['admin', 'diretor'] as $cargo) {
    dRecusa(static fn() => bo_funcionario_criar($db, $adminX, $entrada($cargo), $enviar), 'cargo permitido', "Admin não cria conta $cargo");
    dRecusa(static fn() => bo_funcionario_atualizar($db, $adminX, $vendedor, $formulario(dUsuario($db, $vendedor), ['cargo' => $cargo])), 'cargo permitido', "Admin não promove vendedor a $cargo");
}
dRecusa(static fn() => bo_funcionario_atualizar($db, $adminX, $adminX, $formulario(dUsuario($db, $adminX), ['cargo' => 'diretor'])), 'própria conta', 'Admin não se promove a diretor');
foreach ([$adminY => 'admin', $diretorA => 'diretor'] as $alvo => $rotulo) {
    dRecusa(static fn() => bo_funcionario_atualizar($db, $adminX, $alvo, $formulario(dUsuario($db, $alvo), ['email' => "trocado{$run}{$alvo}@example.test"])), 'por um Diretor', "Admin não altera o e-mail de $rotulo");
    dRecusa(static fn() => bo_funcionario_atualizar($db, $adminX, $alvo, $formulario(dUsuario($db, $alvo), ['cargo' => 'vendedor'])), 'por um Diretor', "Admin não rebaixa $rotulo");
    dRecusa(static fn() => bo_funcionario_alterar_status($db, $adminX, $alvo, 'inativo'), 'por um Diretor', "Admin não desativa $rotulo");
    dRecusa(static fn() => bo_funcionario_reenviar_acesso($db, $adminX, $alvo, $enviar), 'por um Diretor', "Admin não inicia redefinição de senha de $rotulo");
}
foreach ($protegidas as $id => $antes) {
    dCheck(dUsuario($db, $id) === $antes, "Conta administrativa $id intacta depois das tentativas do admin");
}
dCheck($auditoria() === $eventosAntes && $enviados === [], 'Recusas não gravam auditoria nem enviam links');
$v = bo_funcionario_atualizar($db, $adminX, $vendedor, $formulario(dUsuario($db, $vendedor), ['nome' => "Vendedor Editado $run"]));
dCheck(dUsuario($db, $vendedor)['nome'] === "Vendedor Editado $run", 'Admin continua gerenciando funcionários comuns');

// --- Diretor: operações exclusivas ----------------------------------------------------------
$novoAdmin = bo_funcionario_criar($db, $diretorA, $entrada('admin'), $enviar);
dCheck(dUsuario($db, $novoAdmin['id'])['tipo_usuario'] === 'admin' && $novoAdmin['acesso'] === 'enviado', 'Diretor cria administrador (com link de primeira senha)');
$novoDiretor = bo_funcionario_criar($db, $diretorA, $entrada('diretor'), $enviar);
dCheck(dUsuario($db, $novoDiretor['id'])['tipo_usuario'] === 'diretor', 'Diretor cria diretor');
dCheck(dContar($db, "SELECT COUNT(*) FROM auditoria_eventos WHERE acao = 'criar' AND id_operador = $diretorA AND id_alvo = {$novoAdmin['id']} AND detalhes LIKE '%\"cargo\":\"admin\"%'") === 1, 'Criação de administrador auditada com o operador');
$r = bo_funcionario_atualizar($db, $diretorA, $adminY, $formulario(dUsuario($db, $adminY), ['email' => "y{$run}@example.test"]));
dCheck($r['email_alterado'] && dUsuario($db, $adminY)['email'] === "y{$run}@example.test" && (int) dUsuario($db, $adminY)['email_verificado'] === 0, 'Diretor altera o e-mail de administrador (nova confirmação exigida)');
$enviados = [];
dCheck(bo_funcionario_reenviar_acesso($db, $diretorA, $adminX, $enviar) === 'enviado' && count($enviados) === 1 && preg_match('/^[a-f0-9]{64}$/', $enviados[0]['tokens']['senha']), 'Diretor inicia a redefinição de senha de administrador (link de uso único)');
bo_funcionario_atualizar($db, $diretorA, $adminY, $formulario(dUsuario($db, $adminY), ['cargo' => 'diretor']));
dCheck(dUsuario($db, $adminY)['tipo_usuario'] === 'diretor', 'Diretor promove administrador a diretor');
dCheck(dContar($db, "SELECT COUNT(*) FROM auditoria_eventos WHERE acao = 'editar' AND id_operador = $diretorA AND id_alvo = $adminY AND detalhes LIKE '%\"para\":\"diretor\"%'") === 1, 'Promoção auditada');
bo_funcionario_atualizar($db, $diretorA, $diretorB, $formulario(dUsuario($db, $diretorB), ['cargo' => 'admin']));
dCheck(dUsuario($db, $diretorB)['tipo_usuario'] === 'admin', 'Diretor rebaixa outro diretor (há outro diretor ativo)');
bo_funcionario_alterar_status($db, $diretorA, $novoDiretor['id'], 'inativo');
dCheck(dUsuario($db, $novoDiretor['id'])['status'] === 'inativo', 'Diretor desativa outro diretor');
bo_funcionario_alterar_status($db, $diretorA, $novoDiretor['id'], 'ativo');
dCheck(dUsuario($db, $novoDiretor['id'])['status'] === 'ativo', 'Diretor reativa diretor');
bo_funcionario_alterar_status($db, $diretorA, $novoAdmin['id'], 'inativo');
dCheck(dUsuario($db, $novoAdmin['id'])['status'] === 'inativo', 'Diretor desativa administrador');
// O ex-diretor perde a autoridade na hora: o cargo vem do banco, não da sessão.
dRecusa(static fn() => bo_funcionario_alterar_status($db, $diretorB, $adminY, 'inativo'), 'por um Diretor', 'Diretor rebaixado não gere mais diretores');
dRecusa(static fn() => bo_funcionario_criar($db, $diretorInativo, $entrada('admin'), $enviar), 'permissão', 'Diretor inativo não opera');
dRecusa(static fn() => bo_funcionario_atualizar($db, $diretorA, $aluno, $formulario(dUsuario($db, $aluno), ['cargo' => 'admin'])), 'não é de um funcionário gerenciável', 'Nem o diretor promove aluno pela tela de funcionários');
dRecusa(static fn() => bo_funcionario_criar($db, $diretorA, $entrada('aluno'), $enviar), 'cargo permitido', 'Diretor não cria aluno pela tela de funcionários');

// --- Nunca sem diretor ativo ------------------------------------------------------------------
$ativos = static fn(): array => array_column($db->query("SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'diretor' AND status = 'ativo' ORDER BY id_usuario")->fetch_all(), 0);
foreach ($ativos() as $outro) {
    if ((int) $outro !== $diretorA) {
        bo_funcionario_alterar_status($db, $diretorA, (int) $outro, 'inativo');
    }
}
dCheck($ativos() === [(string) $diretorA], 'Só o diretor A continua ativo');
dRecusa(static fn() => bo_funcionario_alterar_status($db, $diretorA, $diretorA, 'inativo'), 'própria conta', 'O último diretor não se desativa');
dRecusa(static fn() => bo_funcionario_atualizar($db, $diretorA, $diretorA, $formulario(dUsuario($db, $diretorA), ['cargo' => 'admin'])), 'própria conta', 'O último diretor não se rebaixa');
// A regra vale mesmo para um caminho que não passe pela checagem de conta própria.
$db->begin_transaction();
$db->query("UPDATE usuarios SET status = 'inativo' WHERE tipo_usuario = 'diretor'");
dRecusa(static fn() => bo_funcionarios_exigir_diretor_ativo($db), 'pelo menos um Diretor', 'Sem diretor ativo, a operação é recusada');
$db->rollback();
dCheck($ativos() === [(string) $diretorA], 'Nada mudou depois da recusa');
dCheck(onefitDiretorAtivo($db, $diretorA) && !onefitDiretorAtivo($db, $adminX) && !onefitDiretorAtivo($db, $diretorInativo) && !onefitDiretorAtivo($db, 999999999), 'Diretor ativo conferido no banco');

// --- Cadastro facial de contas administrativas: só o diretor --------------------------------
$listaDiretor = array_column(bo_fotos_faciais_listar($db, $diretorA, (string) $run), 'id');
$listaAdmin = array_column(bo_fotos_faciais_listar($db, $adminX, (string) $run), 'id');
dCheck(in_array($adminX, $listaDiretor, true) && in_array($diretorA, $listaDiretor, true), 'Diretor lista contas admin e diretor para o cadastro facial');
dCheck(!in_array($adminY, $listaAdmin, true) && !in_array($diretorA, $listaAdmin, true) && !in_array($adminX, $listaAdmin, true) && in_array($vendedor, $listaAdmin, true), 'Admin só lista contas não administrativas');
$foto = bo_foto_facial_cadastrar($db, $diretorA, $adminX, dJpeg(), false);
dCheck($foto['fotos_ativas'] === 1, 'Diretor cadastra foto de administrador');
dRecusa(static fn() => bo_foto_facial_cadastrar($db, $adminX, $diretorA, dJpeg(), false), '', 'Admin não cadastra foto de diretor', FotoFacialRecusada::class);
dRecusa(static fn() => bo_foto_facial_cadastrar($db, $adminX, $adminX, dJpeg(), false), '', 'Admin não cadastra a própria foto', FotoFacialRecusada::class);
dCheck(dContar($db, "SELECT COUNT(*) FROM usuarios_fotos_faciais WHERE id_usuario IN ($diretorA, $adminX) AND ativo = 1") === 1, 'Só a foto autorizada foi gravada');

echo "Hierarquia diretor > admin: $checks verificações passaram.\n";
