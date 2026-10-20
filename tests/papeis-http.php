<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Requisições HTTP reais contra a cópia sintética do app (tests/papeis-http.ps1),
// ligada ao banco descartável onefit_interface_test_*. Confere login, painel,
// revalidação de sessão e os caminhos alternativos bloqueados nesta etapa.
$base = rtrim($argv[1] ?? '', '/') . '/';
if (!preg_match('#^http://127\.0\.0\.1:\d+/AN25/OneFit/$#', $base)) {
    throw new RuntimeException('Use apenas o servidor local da cópia sintética.');
}
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) {
    throw new RuntimeException('Unsafe fixture target');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');

$checks = 0;
function httpCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/** Cliente HTTP mínimo com cookies próprios (um "navegador" por pessoa). */
final class Navegador
{
    private array $cookies = [];

    public function __construct(private string $base) {}

    public function req(string $metodo, string $caminho, array $post = []): array
    {
        $ch = curl_init($this->base . $caminho);
        $cabecalhos = [];
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $nome => $valor) {
                $pares[] = $nome . '=' . $valor;
            }
            $cabecalhos[] = 'Cookie: ' . implode('; ', $pares);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $cabecalhos,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $resposta = curl_exec($ch);
        if ($resposta === false) {
            throw new RuntimeException('HTTP falhou: ' . curl_error($ch));
        }
        $tamanho = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headers = substr($resposta, 0, $tamanho);
        preg_match_all('/^Set-Cookie:\s*([^=;\s]+)=([^;\r\n]*)/mi', $headers, $novos, PREG_SET_ORDER);
        foreach ($novos as [, $nome, $valor]) {
            if ($valor === '' || $valor === 'deleted') {
                unset($this->cookies[$nome]);
            } else {
                $this->cookies[$nome] = $valor;
            }
        }
        preg_match('/^Location:\s*(\S+)/mi', $headers, $local);
        return ['status' => $status, 'location' => $local[1] ?? '', 'body' => substr($resposta, $tamanho)];
    }

    public function entrar(string $email, string $senha = 'Fixture-only-123!'): array
    {
        $this->req('GET', 'pages/login/login.php');
        return $this->req('POST', 'pages/login/login.php', ['email' => $email, 'password' => $senha]);
    }

    public function csrf(): string
    {
        $painel = $this->req('GET', 'pages/dashboard/dashboard.php');
        if (!preg_match('/const BO_CSRF_TOKEN = "([a-f0-9]{64})"/', $painel['body'], $m)) {
            throw new RuntimeException('Token CSRF não encontrado no painel.');
        }
        return $m[1];
    }
}

function situacao(mysqli $db, int $id): ?array
{
    return $db->query('SELECT tipo_usuario, status, nome, email FROM usuarios WHERE id_usuario = ' . $id)->fetch_assoc() ?: null;
}
function contar(mysqli $db, string $sql): int
{
    return (int) $db->query($sql)->fetch_row()[0];
}

// --- Preparação do banco descartável ----------------------------------------
$db->query("ALTER TABLE usuarios MODIFY tipo_usuario ENUM('aluno','profissional','admin','vendedor','professor','recepcionista','faxineira') DEFAULT 'aluno'");
$senha = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT IGNORE INTO usuarios (id_usuario,nome,senha,nacionalidade,data_nascimento,genero,cpf,endereco,cidade_estado,email,email_verificado,celular,tipo_usuario,status,altura,peso) VALUES (?,?,?,'brasileira','1990-01-01','outro',?,'Fixture street','São Paulo/SP',?,1,'11999999999',?,'ativo',1.72,70)");
foreach ([7 => 'faxineira', 8 => 'recepcionista'] as $id => $papel) {
    $nome = "Fixture $id";
    $cpf = str_pad((string) $id, 11, '0', STR_PAD_LEFT);
    $email = "fixture$id@example.test";
    $stmt->bind_param('isssss', $id, $nome, $senha, $cpf, $email, $papel);
    $stmt->execute();
}
$stmt->close();
$db->query("INSERT IGNORE INTO funcao (id_funcao, nome) VALUES (900, 'admin')");
$db->query("CREATE TABLE IF NOT EXISTS usuarios_fotos_faciais (
    id_foto_facial INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    data_cadastro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    imagem_blob MEDIUMBLOB NULL,
    CONSTRAINT fk_fixture_fotos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
) ENGINE=InnoDB");

$originais = [];
foreach (range(1, 8) as $id) {
    $originais[$id] = situacao($db, $id);
}
$restaurar = static function () use ($db, $originais): void {
    $stmt = $db->prepare('UPDATE usuarios SET tipo_usuario = ?, status = ?, nome = ?, email = ? WHERE id_usuario = ?');
    foreach ($originais as $id => $linha) {
        $stmt->bind_param('ssssi', $linha['tipo_usuario'], $linha['status'], $linha['nome'], $linha['email'], $id);
        $stmt->execute();
    }
    $stmt->close();
};

try {
    // --- Login ----------------------------------------------------------------
    $r = (new Navegador($base))->entrar('fixture6@example.test');
    httpCheck(str_ends_with($r['location'], 'login.php?msg=2'), 'Admin inativo não entra');
    foreach ([7 => 'faxineira', 8 => 'recepcionista (sem painel ainda)'] as $id => $rotulo) {
        $r = (new Navegador($base))->entrar("fixture$id@example.test");
        httpCheck(str_ends_with($r['location'], 'login.php?msg=6'), "$rotulo não entra no painel");
        $r = (new Navegador($base))->entrar("fixture$id@example.test", 'senha-errada');
        httpCheck(str_ends_with($r['location'], 'login.php?msg=1'), "$rotulo com senha errada recebe a mensagem comum");
    }

    $admin = new Navegador($base);
    httpCheck(str_contains($admin->entrar('fixture1@example.test')['location'], 'pages/dashboard/dashboard.php'), 'Admin entra');
    $painel = $admin->req('GET', 'pages/dashboard/dashboard.php');
    httpCheck($painel['status'] === 200 && str_contains($painel['body'], 'const BO_PERFIL_LOGADO = "admin"'), 'Painel do admin');
    httpCheck(str_contains($painel['body'], 'data-section="cadastro-facial"') && str_contains($painel['body'], 'assets/js/fotos-faciais.js'), 'Admin mantém o cadastro facial');
    httpCheck(!str_contains($painel['body'], 'data-section="permissoes"') && !str_contains($painel['body'], 'data-section="funcoes"'), 'Permissões e Funções fora do painel');
    $tokenAdmin = $admin->csrf();

    $professor = new Navegador($base);
    $professor->entrar('fixture4@example.test');
    $painel = $professor->req('GET', 'pages/dashboard/dashboard.php');
    httpCheck($painel['status'] === 200 && str_contains($painel['body'], 'const BO_PERFIL_LOGADO = "profissional"'), 'Painel do professor');
    httpCheck(!str_contains($painel['body'], 'data-section="cadastro-facial"') && !str_contains($painel['body'], 'fotos-faciais.js'), 'Professor sem cadastro facial no painel');
    $tokenProfessor = $professor->csrf();

    $aluno = new Navegador($base);
    $aluno->entrar('fixture3@example.test');
    $vendedor = new Navegador($base);
    $vendedor->entrar('fixture5@example.test');

    // --- Cadastro facial: permissão do operador conferida no servidor --------
    httpCheck((new Navegador($base))->req('GET', 'pages/dashboard/actions/fotos-faciais.php')['status'] === 401, 'Fotos faciais sem sessão: 401');
    $r = $admin->req('GET', 'pages/dashboard/actions/fotos-faciais.php');
    httpCheck($r['status'] === 200 && (json_decode($r['body'], true)['ok'] ?? false) === true, 'Admin lista pessoas');
    foreach (['professor' => $professor, 'aluno' => $aluno, 'vendedor' => $vendedor] as $rotulo => $nav) {
        httpCheck($nav->req('GET', 'pages/dashboard/actions/fotos-faciais.php')['status'] === 403, "$rotulo não lista pessoas");
    }
    $r = $professor->req('POST', 'pages/dashboard/actions/fotos-faciais.php', ['csrf_token' => $tokenProfessor, 'id_usuario' => 3]);
    httpCheck($r['status'] === 403, 'Professor não grava foto por POST direto');

    // --- Permissões e Funções não alteram mais cargos -------------------------
    $permissoesAntes = contar($db, 'SELECT COUNT(*) FROM permissoes');
    $funcoesAntes = contar($db, 'SELECT COUNT(*) FROM funcao');
    $r = $admin->req('POST', 'pages/dashboard/funcionalidades/permissoes.php', ['csrf_token' => $tokenAdmin, 'acao' => 'create', 'email' => 'fixture3@example.test', 'funcao' => 900, 'secao' => 'permissoes']);
    httpCheck($r['status'] === 302 && situacao($db, 3)['tipo_usuario'] === 'aluno', 'Permissões não promove aluno a admin');
    $admin->req('POST', 'pages/dashboard/funcionalidades/permissoes.php', ['csrf_token' => $tokenAdmin, 'acao' => 'delete', 'id' => 1, 'secao' => 'permissoes']);
    $admin->req('POST', 'pages/dashboard/funcionalidades/funcoes.php', ['csrf_token' => $tokenAdmin, 'acao' => 'create', 'nome' => 'superadmin', 'secao' => 'funcoes']);
    httpCheck(contar($db, 'SELECT COUNT(*) FROM permissoes') === $permissoesAntes && contar($db, 'SELECT COUNT(*) FROM funcao') === $funcoesAntes, 'Tabelas permissoes e funcao intactas');
    $r = $admin->req('GET', 'pages/dashboard/dashboard.php');
    httpCheck(str_contains($r['body'], 'As telas Permissões e Funções foram desativadas'), 'Aviso de tela desativada');

    // --- Usuários: contas de admin protegidas --------------------------------
    $tokenAdmin = $admin->csrf();
    $tentativas = [
        ['acao' => 'toggle-status', 'id' => 2],
        ['acao' => 'toggle-status', 'id' => 1],
        ['acao' => 'delete', 'id' => 2],
        ['acao' => 'update', 'id' => 2, 'nome' => 'Tomada', 'email' => 'tomada@example.test', 'cpf' => '52998224725', 'status' => 'inativo'],
    ];
    foreach ($tentativas as $tentativa) {
        $admin->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', $tentativa + ['csrf_token' => $tokenAdmin, 'secao' => 'usuarios']);
    }
    httpCheck(situacao($db, 1) === $originais[1] && situacao($db, 2) === $originais[2], 'Admin comum não altera contas de admin (nem a própria)');
    httpCheck(str_contains($admin->req('GET', 'pages/dashboard/dashboard.php')['body'], 'Contas de administrador não podem ser alteradas nesta tela.'), 'Aviso da proteção de admin');

    $tokenAdmin = $admin->csrf();
    $admin->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['csrf_token' => $tokenAdmin, 'acao' => 'toggle-status', 'id' => 5, 'secao' => 'usuarios']);
    httpCheck(situacao($db, 5)['status'] === 'inativo', 'Comportamento mantido: admin ainda inativa conta que não é de admin');
    $admin->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['csrf_token' => $tokenAdmin, 'acao' => 'toggle-status', 'id' => 5, 'secao' => 'usuarios']);
    httpCheck(situacao($db, 5)['status'] === 'ativo', 'Comportamento mantido: admin reativa conta que não é de admin');

    $r = $professor->req('POST', 'pages/dashboard/funcionalidades/usuarios.php', ['csrf_token' => $tokenProfessor, 'acao' => 'toggle-status', 'id' => 3, 'secao' => 'usuarios']);
    httpCheck($r['status'] === 302 && situacao($db, 3)['status'] === 'ativo', 'Professor não usa a tela de usuários');

    // --- Revalidação da sessão a cada requisição ------------------------------
    $db->query("UPDATE usuarios SET status = 'inativo' WHERE id_usuario = 3");
    $r = $aluno->req('GET', 'pages/dashboard/dashboard.php');
    httpCheck($r['status'] === 302 && str_contains($r['location'], 'pages/login/login.php'), 'Aluno inativado sai do painel na hora');
    httpCheck(str_contains($aluno->req('GET', 'pages/login/login.php')['body'], 'Sua sessão foi encerrada'), 'Aviso de sessão encerrada');
    $db->query("UPDATE usuarios SET status = 'ativo' WHERE id_usuario = 3");
    httpCheck($aluno->req('GET', 'pages/dashboard/dashboard.php')['status'] === 302, 'Sessão encerrada não volta sozinha');

    $outroAdmin = new Navegador($base);
    $outroAdmin->entrar('fixture2@example.test');
    $db->query("UPDATE usuarios SET tipo_usuario = 'aluno' WHERE id_usuario = 2");
    $r = $outroAdmin->req('GET', 'pages/dashboard/dashboard.php');
    httpCheck($r['status'] === 200 && str_contains($r['body'], 'const BO_PERFIL_LOGADO = "aluno"') && !str_contains($r['body'], 'data-section="usuarios"'), 'Admin rebaixado perde o painel de admin na hora');
    httpCheck($outroAdmin->req('GET', 'pages/dashboard/actions/fotos-faciais.php')['status'] === 403, 'Admin rebaixado perde o cadastro facial');
    $db->query("UPDATE usuarios SET tipo_usuario = 'faxineira' WHERE id_usuario = 2");
    httpCheck($outroAdmin->req('GET', 'pages/dashboard/dashboard.php')['status'] === 302, 'Conta que vira faxineira sai do painel');

    $db->query("UPDATE usuarios SET tipo_usuario = 'professor' WHERE id_usuario = 4");
    $r = $professor->req('GET', 'pages/dashboard/dashboard.php');
    httpCheck($r['status'] === 200 && str_contains($r['body'], 'const BO_PERFIL_LOGADO = "profissional"'), 'Valor novo professor usa o painel atual');

    // --- Recuperação de senha não revela a situação da conta ------------------
    $tokens = static fn(int $id): int => contar($db, 'SELECT COUNT(*) FROM recuperacao_senha_tokens WHERE usuario_id = ' . $id);
    $antes = array_map($tokens, [3 => 3, 6 => 6, 7 => 7, 8 => 8]);
    $respostas = [];
    foreach (['fixture3@example.test', 'fixture6@example.test', 'fixture7@example.test', 'fixture8@example.test', 'nao-existe@example.test'] as $email) {
        $nav = new Navegador($base);
        $r = $nav->req('POST', 'pages/auth/senha/processar-recuperacao.php', ['email' => $email]);
        $pagina = $nav->req('GET', 'pages/auth/senha/esqueci-senha.php');
        preg_match('/Solicitação recebida![^<]*/u', $pagina['body'], $mensagem);
        $respostas[] = [$r['status'], $r['location'], $mensagem[0] ?? ''];
    }
    httpCheck(count(array_unique(array_map('serialize', $respostas))) === 1 && $respostas[0][2] !== '', 'Mesma resposta para conta ativa, inativa, sem painel e inexistente');
    httpCheck($tokens(3) === $antes[3] + 1, 'Aluno ativo recebe link');
    httpCheck($tokens(6) === $antes[6] && $tokens(7) === $antes[7] && $tokens(8) === $antes[8], 'Inativo, faxineira e recepcionista sem painel não recebem link');
} finally {
    $restaurar();
}
foreach ($originais as $id => $linha) {
    httpCheck(situacao($db, $id) === $linha, "Banco descartável restaurado ($id)");
}

echo "Papéis via HTTP: $checks verificações passaram.\n";
