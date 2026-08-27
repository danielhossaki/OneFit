<?php

require_once __DIR__ . '/mailer.php';

function onefitTabelaTokenValida(string $tabela): bool
{
    return in_array($tabela, ['verificacao_email_tokens', 'recuperacao_senha_tokens'], true);
}

function onefitCriarToken(mysqli $conn, string $tabela, int $usuarioId, string $expiracao): string
{
    if (!onefitTabelaTokenValida($tabela)) {
        throw new InvalidArgumentException('Tabela de token inválida.');
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiraEm = (new DateTimeImmutable($expiracao))->format('Y-m-d H:i:s');

    $invalida = $conn->prepare("UPDATE {$tabela} SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL");
    $invalida->bind_param('i', $usuarioId);
    $invalida->execute();
    $invalida->close();

    $insere = $conn->prepare("INSERT INTO {$tabela} (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)");
    $insere->bind_param('iss', $usuarioId, $hash, $expiraEm);
    $insere->execute();
    $insere->close();

    return $token;
}

function onefitTokenFormatoValido(string $token): bool
{
    return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
}
