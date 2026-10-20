<?php
// Script manual de teste de envio: nunca pode ser disparado pela web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once 'mailer.php';

$teste = onefitEnviarRedefinicaoSenha("dsiqueirasantos1@gmail.com", "Test User", "https://example.com/reset-password?token=abc123");
if ($teste) {
    echo "E-mail de redefinição de senha enviado com sucesso.";
} else {
    echo "Falha ao enviar o e-mail de redefinição de senha.";
}