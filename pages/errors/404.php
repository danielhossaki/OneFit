<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

// Garante que o servidor (e navegadores, bots, SEO) recebam o status correto.
// Sem isso a página "existe" para o Google como um 200 normal.
http_response_code(404);
 
// Log simples da URL que não foi encontrada, pra facilitar achar links quebrados.
// Se preferir salvar no banco em vez de arquivo, é só trocar o bloco abaixo
// por um INSERT usando a $conn que já vem de config/conn.php.
$logFile = __DIR__ . '/storage/logs/404.log';
$logDir  = dirname($logFile);
 
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
 
$logLine = sprintf(
    "[%s] URL: %s | Referer: %s | IP: %s\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REQUEST_URI'] ?? '-',
    $_SERVER['HTTP_REFERER'] ?? '-',
    $_SERVER['REMOTE_ADDR'] ?? '-'
);
 
@file_put_contents($logFile, $logLine, FILE_APPEND);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Página não encontrada | ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <!-- link da fonte -->
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <!-- link das animações -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <!-- link do css -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/errors.css">

  <!-- link do favicon -->
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body>
  <section class="error-page" data-aos="fade-up">
    <span class="eyebrow error-eyebrow">Série falhada</span>
 
    <div class="error-code" aria-label="Erro 404">
      <span class="digit">4</span><span class="plate-zero">
        <svg viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">
          <circle cx="15" cy="15" r="13" fill="none" stroke="var(--gold)" stroke-width="2.4" />
          <circle cx="15" cy="15" r="4" fill="var(--gold)" />
        </svg>
      </span><span class="digit">4</span>
    </div>
 
    <h2>Essa página falhou o levantamento</h2>
    <p class="lead">A página que você procura não existe, mudou de lugar ou foi removida. Sem excesso de carga — volte para o início e recomece o treino do jeito certo.</p>
 
    <div class="error-actions">
      <a href="<?php echo BASE_URL; ?>" class="btn btn-gold">Voltar para o início</a>
      <a href="<?php echo BASE_URL; ?>index.php#planos" class="btn btn-outline">Ver planos</a>
    </div>
 
    <div class="error-links">
      <a href="<?php echo BASE_URL; ?>index.php#modalidades">Modalidades</a>
      <span class="sep">&#9670;</span>
      <a href="<?php echo BASE_URL; ?>index.php#estrutura">Estrutura</a>
      <span class="sep">&#9670;</span>
      <a href="<?php echo BASE_URL; ?>index.php#contato">Contato</a>
    </div>
  </section>

  <!-- Link para JavaScript -->
  <script src="<?php echo BASE_URL; ?>assets/js/home.js"></script>

  <!-- Link para animações AOS JS -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- Animacão do AOS JS -->
  <script>
    AOS.init({
      duration: 800,
      once: true,
      offset: 100
    });
  </script>

</body>

</html>