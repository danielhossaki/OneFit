<?php
/**
 * funcionalidades/testemunho.php
 * Salva/atualiza o testemunho do aluno logado (card "Seu testemunho" na
 * tela Perfil). Cada aluno tem no máximo um testemunho (uk_testemunho_usuario);
 * reenviar o texto some sempre volta o status para "pendente", já que o
 * conteúdo mudou e precisa passar pela aprovação do admin de novo.
 */

$bo_papeis_permitidos = ['aluno'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$texto = trim((string) ($_POST['texto'] ?? ''));

if ($texto === '') {
    bo_flash('error', 'Escreva seu comentário antes de salvar.');
    bo_redirect('perfil');
}
if (mb_strlen($texto) > 500) {
    $texto = mb_substr($texto, 0, 500);
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS testemunhos (
        id_testemunho INT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_usuario INT NULL,
        nome_exibido VARCHAR(120) NULL,
        tempo_exibido VARCHAR(60) NULL,
        texto VARCHAR(500) NOT NULL,
        aprovacao ENUM('pendente','aprovado','reprovado') NOT NULL DEFAULT 'pendente',
        visibilidade ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
        data_criacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_testemunho),
        UNIQUE KEY uk_testemunho_usuario (id_usuario),
        CONSTRAINT fk_testemunho_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$stmt = $conn->prepare(
    "INSERT INTO testemunhos (id_usuario, texto, aprovacao, visibilidade) VALUES (?, ?, 'pendente', 'ativo')
     ON DUPLICATE KEY UPDATE texto = VALUES(texto), aprovacao = 'pendente'"
);
$stmt->bind_param('is', $idUsuario, $texto);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Comentário enviado para aprovação. Obrigado por compartilhar!');
bo_redirect('perfil');
