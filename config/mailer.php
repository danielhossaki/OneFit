<?php

require_once __DIR__ . '/env.php';

function onefitAppUrl(): string
{
    $fallback = defined('BASE_URL') ? BASE_URL : 'http://localhost/AN25/OneFit/';
    return rtrim(onefitEnv('APP_URL', $fallback) ?: $fallback, '/');
}

function onefitEnviarEmail(string $destinatario, string $nomeDestinatario, string $assunto, string $html): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('OneFit mailer indisponível: dependências do Composer não encontradas.');
        return false;
    }

    require_once $autoload;

    $host = onefitEnv('MAIL_HOST');
    $porta = (int) onefitEnv('MAIL_PORT', '587');
    $usuario = onefitEnv('MAIL_USERNAME');
    $senha = onefitEnv('MAIL_PASSWORD');
    $remetente = onefitEnv('MAIL_FROM_ADDRESS');
    $nomeRemetente = onefitEnv('MAIL_FROM_NAME', 'OneFit');

    if (!$host || !$porta || !$usuario || !$senha || !$remetente) {
        error_log('OneFit mailer indisponível: configuração SMTP incompleta.');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $porta;
        $mail->SMTPAuth = true;
        $mail->Username = $usuario;
        $mail->Password = $senha;
        $mail->SMTPSecure = $porta === 465
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($remetente, $nomeRemetente);
        $mail->addAddress($destinatario, $nomeDestinatario);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
        $mail->send();
        return true;
    } catch (Throwable $erro) {
        error_log('Falha segura ao enviar e-mail da OneFit.');
        return false;
    }
}

function onefitTemplateEmail(string $nome, string $texto, string $textoBotao, string $link, string $rodape): string
{
    $nomeSeguro = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $textoSeguro = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $botaoSeguro = htmlspecialchars($textoBotao, ENT_QUOTES, 'UTF-8');
    $linkSeguro = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $rodapeSeguro = htmlspecialchars($rodape, ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html><html lang="pt-BR"><body style="margin:0;background:#171411;font-family:Arial,sans-serif;color:#f3ede2">'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td align="center" style="padding:32px 16px">'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;background:#211d19;border:1px solid #403730;border-radius:12px">'
        . '<tr><td style="padding:36px"><div style="font-size:28px;font-weight:800;letter-spacing:1px">ONE<span style="color:#d6bc75">FIT</span></div>'
        . '<h1 style="margin:28px 0 12px;font-size:28px">Olá, ' . $nomeSeguro . '!</h1>'
        . '<p style="margin:0 0 28px;line-height:1.7;color:#c9c0b7">' . $textoSeguro . '</p>'
        . '<p style="margin:0 0 28px"><a href="' . $linkSeguro . '" style="display:inline-block;padding:14px 20px;border-radius:6px;background:#d6bc75;color:#1a1613;text-decoration:none;font-weight:700">' . $botaoSeguro . '</a></p>'
        . '<p style="margin:0;color:#a79b90;font-size:13px;line-height:1.6">' . $rodapeSeguro . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function onefitEnviarVerificacaoEmail(string $email, string $nome, string $token): bool
{
    $link = onefitAppUrl() . '/pages/auth/email/verificar-email.php?token=' . rawurlencode($token);
    $html = onefitTemplateEmail(
        $nome,
        'Seu cadastro na OneFit foi realizado. Para confirmar seu endereço de e-mail, clique no botão abaixo.',
        'Confirmar meu e-mail',
        $link,
        'Este link expira em 24 horas. Se você não realizou este cadastro, ignore este e-mail.'
    );

    return onefitEnviarEmail($email, $nome, 'Confirme seu e-mail - OneFit', $html);
}

function onefitEnviarRedefinicaoSenha(string $email, string $nome, string $token): bool
{
    $link = onefitAppUrl() . '/pages/auth/senha/redefinir-senha.php?token=' . rawurlencode($token);
    $html = onefitTemplateEmail(
        $nome,
        'Recebemos uma solicitação para redefinir a senha da sua conta.',
        'Redefinir minha senha',
        $link,
        'Este link expira em 30 minutos. Se você não solicitou a alteração, ignore este e-mail. Sua senha permanecerá a mesma.'
    );

    return onefitEnviarEmail($email, $nome, 'Redefinição de senha - OneFit', $html);
}
