<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Confere as credenciais do .env depois de uma rotação. Não grava nada, não envia e-mail
// e nunca imprime valores: só nomes de chaves, OK/FALHA e códigos de erro.
//   (sem opção)        .env do projeto: banco (SELECT 1), SMTP (login sem envio),
//                      histórico do Git e variável da catraca
//   --env=ARQUIVO      usa outro arquivo no lugar do .env do projeto
//   --sem-rede         só as conferências locais (histórico e catraca)
//   --antigas          o arquivo tem as credenciais ANTIGAS: passa se banco e SMTP as recusarem
$opcoes = getopt('', ['env:', 'sem-rede', 'antigas']);
$raiz = dirname(__DIR__);
$arquivo = $opcoes['env'] ?? $raiz . '/.env';
$semRede = isset($opcoes['sem-rede']);
$antigas = isset($opcoes['antigas']);

$falhas = 0;
function credLinha(string $estado, string $texto): void
{
    global $falhas;
    if ($estado === 'FALHA') $falhas++;
    echo str_pad("[$estado]", 10), $texto, "\n";
}

// Mesma leitura de config/env.php, mas de qualquer arquivo e sem putenv().
function credLerEnv(string $arquivo): array
{
    $valores = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) continue;
        [$nome, $valor] = array_map('trim', explode('=', $linha, 2));
        if (strlen($valor) >= 2 && (($valor[0] === '"' && str_ends_with($valor, '"')) || ($valor[0] === "'" && str_ends_with($valor, "'")))) {
            $valor = substr($valor, 1, -1);
        }
        if ($nome !== '') $valores[$nome] = $valor;
    }
    return $valores;
}

if (!is_readable($arquivo)) {
    credLinha('FALHA', 'arquivo de configuração não encontrado ou sem leitura');
    exit(1);
}
$env = credLerEnv($arquivo);
echo 'Arquivo: ', $arquivo === $raiz . '/.env' ? '.env do projeto' : 'informado em --env', $antigas ? ' (credenciais antigas)' : '', "\n\n";

$faltando = array_filter(
    ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'],
    fn ($chave) => ($env[$chave] ?? '') === ''
);
credLinha($faltando ? 'FALHA' : 'OK', $faltando ? 'chaves vazias ou ausentes: ' . implode(', ', $faltando) : 'todas as chaves obrigatórias preenchidas');

// O valor vai para o git pela entrada padrão, nunca pela linha de comando.
function credNoHistorico(string $valor): ?bool
{
    $revisoes = preg_split('/\s+/', trim((string) shell_exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' rev-list --all')));
    if (!$revisoes || $revisoes[0] === '') return null;
    foreach (array_chunk($revisoes, 30) as $lote) {
        $processo = proc_open(
            array_merge(['git', '-C', dirname(__DIR__), 'grep', '-l', '-I', '-F', '-f', '-'], $lote),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tubos
        );
        if (!is_resource($processo)) return null;
        fwrite($tubos[0], $valor . "\n");
        fclose($tubos[0]);
        $saida = stream_get_contents($tubos[1]);
        fclose($tubos[1]);
        fclose($tubos[2]);
        proc_close($processo);
        if (trim($saida) !== '') return true;
    }
    return false;
}

if (!$antigas) {
    foreach (['DB_PASSWORD', 'MAIL_PASSWORD'] as $chave) {
        if (($env[$chave] ?? '') === '') continue;
        $exposta = credNoHistorico($env[$chave]);
        $semEspacos = str_replace(' ', '', $env[$chave]);
        if ($exposta === false && $semEspacos !== $env[$chave]) $exposta = credNoHistorico($semEspacos);
        if ($exposta === null) credLinha('--', "$chave: histórico do Git não verificado (git indisponível)");
        else credLinha($exposta ? 'FALHA' : 'OK', $exposta ? "$chave: o valor ainda é o que aparece no histórico do Git (não foi trocado)" : "$chave: valor não aparece em nenhum commit");
    }

    // A catraca (OneFit.Turnstile) lê a senha do banco de ONEFIT_MYSQL_PASSWORD.
    if (PHP_OS_FAMILY === 'Windows' && ($env['DB_PASSWORD'] ?? '') !== '') {
        $chaves = [
            'Machine' => 'HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment',
            'User' => 'HKCU\Environment',
        ];
        foreach ($chaves as $escopo => $registro) {
            $saida = (string) shell_exec('reg query ' . escapeshellarg($registro) . ' /v ONEFIT_MYSQL_PASSWORD 2>NUL');
            if (!preg_match('/^\s*ONEFIT_MYSQL_PASSWORD\s+REG_(?:EXPAND_)?SZ\s+(.*)$/mi', $saida, $m)) {
                credLinha('--', "ONEFIT_MYSQL_PASSWORD ($escopo): não definida");
                continue;
            }
            $igual = hash_equals($env['DB_PASSWORD'], rtrim($m[1], "\r"));
            credLinha($igual ? 'OK' : 'FALHA', "ONEFIT_MYSQL_PASSWORD ($escopo): " . ($igual ? 'igual a DB_PASSWORD' : 'DIFERENTE de DB_PASSWORD (a catraca não vai conectar)'));
            unset($m, $saida);
        }
    }
}

if ($semRede) {
    echo "\nBanco e SMTP não testados (--sem-rede).\n";
    exit($falhas ? 1 : 0);
}

// Banco: só conecta e executa SELECT 1 numa transação somente leitura.
mysqli_report(MYSQLI_REPORT_OFF);
$db = mysqli_init();
$db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
$conectou = @$db->real_connect($env['DB_HOST'] ?? '', $env['DB_USER'] ?? '', $env['DB_PASSWORD'] ?? '', $env['DB_NAME'] ?? '');
if ($conectou) {
    $ok = $db->query('START TRANSACTION READ ONLY') && ($r = $db->query('SELECT 1')) && (int) $r->fetch_row()[0] === 1;
    $db->query('ROLLBACK');
    $db->close();
    if ($antigas) credLinha('FALHA', 'Banco: as credenciais antigas AINDA funcionam (a senha não foi trocada no servidor)');
    else credLinha($ok ? 'OK' : 'FALHA', $ok ? 'Banco: conectou e respondeu SELECT 1 (somente leitura)' : 'Banco: conectou, mas a consulta de leitura falhou');
} else {
    $codigo = $db->connect_errno;
    $motivo = match (true) {
        $codigo === 1045 => 'usuário ou senha recusados',
        $codigo === 1044 => 'usuário sem permissão neste banco',
        in_array($codigo, [2002, 2003, 2005, 2006, 2013], true) => 'servidor inacessível',
        default => 'falha de conexão',
    };
    $recusada = $codigo === 1045;
    credLinha($antigas && $recusada ? 'OK' : 'FALHA', "Banco: $motivo (código $codigo)" . ($antigas && $recusada ? ' - credencial antiga revogada' : ''));
}

// SMTP: mesma configuração de config/mailer.php; só abre a sessão e autentica, sem remetente nem destinatário.
$autoload = $raiz . '/vendor/autoload.php';
if (!is_file($autoload)) {
    credLinha('FALHA', 'SMTP: dependências do Composer não encontradas');
} else {
    require_once $autoload;
    $porta = (int) ($env['MAIL_PORT'] ?? 587);
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = 0; // níveis acima de 0 imprimiriam o AUTH
    $mail->Timeout = 15;
    $mail->Host = $env['MAIL_HOST'] ?? '';
    $mail->Port = $porta;
    $mail->SMTPAuth = true;
    $mail->Username = $env['MAIL_USERNAME'] ?? '';
    $mail->Password = $env['MAIL_PASSWORD'] ?? '';
    $mail->SMTPSecure = $porta === 465
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    try {
        $autenticou = $mail->smtpConnect();
    } catch (Throwable $erro) {
        $autenticou = false;
    }
    $smtp = $mail->getSMTPInstance();
    $codigo = (string) ($smtp->getError()['smtp_code'] ?? '');
    $conectado = $smtp->connected();
    $mail->smtpClose();
    $mail->Password = '';
    if ($autenticou) {
        if ($antigas) credLinha('FALHA', 'SMTP: a senha antiga AINDA autentica (revogue a senha de app antiga)');
        else credLinha('OK', 'SMTP: conectou, abriu TLS e autenticou (nenhuma mensagem enviada)');
    } else {
        $recusada = in_array($codigo, ['535', '534'], true);
        $motivo = $recusada ? 'autenticação recusada' : ($conectado ? 'sessão recusada' : 'servidor inacessível ou falha de TLS');
        credLinha($antigas && $recusada ? 'OK' : 'FALHA', "SMTP: $motivo" . ($codigo !== '' ? " (código $codigo)" : '') . ($antigas && $recusada ? ' - senha antiga revogada' : ''));
    }
}

echo "\n", $falhas ? "$falhas verificação(ões) falharam.\n" : "Tudo certo.\n";
exit($falhas ? 1 : 0);
