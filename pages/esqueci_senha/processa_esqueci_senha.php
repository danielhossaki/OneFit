<?php
session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
// Este arquivo assume que conn.php cria a variável $conn como mysqli:
//   $conn = new mysqli($host, $usuario, $senha, $banco);
// Se o nome da variável no seu conn.php for diferente, ajuste as
// chamadas $conn->prepare(...) abaixo.

// ── 1. Só processa se a requisição for POST ────────────────────────────────
// Impede que alguém acesse esse arquivo diretamente pelo navegador (GET)
// e dispare o fluxo sem passar pelo formulário.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/esqueci_senha/esqueci_senha.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

// ── 2. Validação do e-mail ──────────────────────────────────────────────────
// filter_var com FILTER_VALIDATE_EMAIL verifica o FORMATO do e-mail
// (tem @, domínio, etc). Isso não garante que o e-mail existe de verdade,
// só que a string tem uma cara de e-mail válida.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['esqueci_senha_msg'] = 'Informe um e-mail válido.';
    $_SESSION['esqueci_senha_tipo'] = 'erro';
    header('Location: esqueci_senha.php');
    exit;
}

// ── 3. Busca o usuário pelo e-mail ─────────────────────────────────────────
// Usamos "prepared statement" (prepare + bind_param) em vez de colocar
// $email direto dentro do SQL. Isso é o que evita SQL Injection: o valor
// digitado pelo usuário nunca é interpretado como parte do comando SQL,
// só como um dado.
$sql = "SELECT id FROM usuarios WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email); // 's' = o parâmetro é uma string
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc(); // null se não achar ninguém
$stmt->close();

// ── 4. Só gera token/e-mail se o usuário existir ───────────────────────────
// IMPORTANTE (segurança): mesmo que o e-mail NÃO exista, o usuário final
// vai ver a mesma mensagem de sucesso (passo 9). Se déssemos uma mensagem
// diferente para "e-mail não encontrado", qualquer pessoa poderia usar
// essa tela para descobrir quais e-mails estão cadastrados no sistema
// (isso se chama "user enumeration" e é considerado falha de segurança).
if ($usuario) {
    $usuario_id = $usuario['id'];

    // ── 5. Gera um token único e imprevisível ──────────────────────────────
    // random_bytes(32) gera 32 bytes criptograficamente aleatórios (seguros
    // de verdade, diferente de rand() ou mt_rand()). bin2hex converte esses
    // bytes em texto hexadecimal, resultando numa string de 64 caracteres.
    $token = bin2hex(random_bytes(32));

    // ── 6. Define a validade: 1 hora a partir de agora ─────────────────────
    $expira_em = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // ── 7. Invalida tokens antigos ainda não usados desse usuário ──────────
    // Boa prática: se o usuário pedir o link 3 vezes seguidas, só o mais
    // recente deve funcionar. Isso evita links antigos "esquecidos" por aí
    // continuarem válidos.
    $sqlInvalida = "UPDATE recuperacao_senha SET usado = 1 WHERE usuario_id = ? AND usado = 0";
    $stmtInvalida = $conn->prepare($sqlInvalida);
    $stmtInvalida->bind_param('i', $usuario_id); // 'i' = inteiro
    $stmtInvalida->execute();
    $stmtInvalida->close();

    // ── 8. Salva o novo token no banco ──────────────────────────────────────
    $sqlInsert = "INSERT INTO recuperacao_senha (usuario_id, token, expira_em) VALUES (?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param('iss', $usuario_id, $token, $expira_em);
    $stmtInsert->execute();
    $stmtInsert->close();

    // ── 9. Monta o link que o usuário usaria para redefinir a senha ────────
    $link = BASE_URL . 'pages/esqueci_senha/resetar_senha.php?token=' . $token;

    // ── 10. "Envio" do e-mail — SIMULADO por enquanto ──────────────────────
    // Você pediu pra não configurar envio de e-mail de verdade ainda, então,
    // em vez de mail(), estamos gravando o link num arquivo de log. Assim dá
    // pra testar o fluxo completo (gerar token -> abrir link -> trocar senha)
    // sem precisar de um servidor SMTP configurado.
    //
    // Quando quiser ativar o envio real, troque este bloco por PHPMailer
    // com SMTP — o mail() nativo do PHP costuma cair em spam ou nem sequer
    // ser aceito pelos provedores (Gmail, Outlook etc.) sem autenticação.
    $logDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    $logLinha = '[' . date('Y-m-d H:i:s') . '] Para: ' . $email . ' | Link: ' . $link . PHP_EOL;
    file_put_contents($logDir . '/recuperacao_senha.log', $logLinha, FILE_APPEND);
}

// ── 11. Mensagem final — SEMPRE a mesma, exista ou não o e-mail ───────────
$_SESSION['esqueci_senha_msg'] = 'Se este e-mail estiver cadastrado, você receberá as instruções de redefinição em instantes.';
$_SESSION['esqueci_senha_tipo'] = 'sucesso';
header('Location: esqueci_senha.php');
exit;
