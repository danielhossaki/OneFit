<?php

declare(strict_types=1);
ini_set('display_errors', '0');
session_start();
header('Cache-Control: no-store');
if (empty($_SESSION['id_usuario']) || (!empty($_SESSION['pagamento_matricula_sem_login']) && empty($_SESSION['matricula_wizard']['referencia']))) {
    header('Location: ../login/login.php');
    exit;
}
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$usuarioAutenticado = (int)$_SESSION['id_usuario'];
session_write_close();
try {
    require __DIR__ . '/../../config/conn.php';
    require __DIR__ . '/../../services/pagamentos/PixCheckout.php';
    $ref = (string)($_GET['r'] ?? '');
    $data = (new OneFit\Pagamentos\PixCheckout($conn))->dados($usuarioAutenticado, $ref);
} catch (Throwable) {
    http_response_code(404);
    exit('Pagamento indisponivel.');
}
function pixEscape(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pix · OneFit</title>
<link rel="stylesheet" href="../../assets/css/pix.css">
<main id="pix" data-ref="<?= pixEscape($ref) ?>" data-csrf="<?= pixEscape($csrf) ?>">
    <a href="../../index.php" class="brand">ONE<span>FIT</span></a>
    <p class="test">Ambiente de teste — saldo fictício</p>
    <h1>Pagamento Pix</h1>
    <section class="card summary">
        <h2>Resumo do pagamento</h2>
        <p><?= pixEscape($data['nome_plano'] ?? $data['origem']) ?></p>
        <?php if ($data['teste_local'] ?? false): ?>
            <p><?= pixEscape($data['nome_plano']) ?> — preço normal: R$ <?= pixEscape(str_replace('.', ',', $data['valor_plano'])) ?></p>
            <p>Teste técnico: o Mercado Pago simulará R$ 50,00. Nenhum dinheiro real será movimentado.</p>
            <p>Valor técnico simulado</p>
        <?php endif; ?>
        <strong class="value">R$ <?= pixEscape(str_replace('.', ',', $data['valor'])) ?></strong>
    </section>
    <p id="status" role="status" aria-live="polite">Consultando pagamento…</p>
    <section id="pending" class="card" hidden>
        <h2>Pague com Pix</h2>
        <img id="qr" alt="QR Code do pagamento Pix" hidden><label for="code">Pix copia e cola</label><textarea id="code" readonly rows="4"></textarea>
        <button id="copy" type="button">Copiar código Pix</button><a id="ticket" target="_blank" rel="noopener noreferrer" hidden>Abrir pagamento</a>
        <p>Vencimento: <time id="expiry">Aguardando provedor</time></p>
        <p>Mantenha esta página aberta enquanto confirmamos o pagamento.</p>
        <p id="missing" hidden>O QR ainda não está disponível. Consulte novamente o mesmo pagamento.</p>
        <button id="recover" type="button" hidden>Consultar pagamento novamente</button>
    </section>
    <section id="approved" class="card success" hidden><span class="success-icon" aria-hidden="true">✓</span>
        <h2>Pagamento aprovado</h2>
        <p><?= $data['origem'] === 'pedido' ? 'Seu pedido foi confirmado.' : 'Sua matrícula foi confirmada.' ?></p>
        <?php if ($data['origem'] !== 'pedido'): ?><p>Seu e-mail já foi verificado. Acesse sua conta pelo login.</p><a class="button" id="continue" href="../login/login.php">Continuar para o login</a><?php endif; ?>
    </section>
    <a id="retry" href="<?= $data['origem'] === 'pedido' ? '../carrinho/carrinho.php' : '../matricula/matricula.php' ?>" hidden>Tentar novamente</a>
    <script id="pix-initial" type="application/json">
        <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
    </script>
</main>
<script src="../../assets/js/pix.js" defer></script>

</html>