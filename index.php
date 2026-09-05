<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

// WhatsApp da Home: substitua pelo número real com 55 + DDD + número, somente dígitos.
$whatsappNumero = '55XXXXXXXXXXX';
$whatsappMensagem = 'Olá! Gostaria de saber mais sobre a OneFit.';
$whatsappUrl = 'https://wa.me/' . $whatsappNumero . '?text=' . rawurlencode($whatsappMensagem);

// Mensagem exclusiva do botão "Agendar aula experimental".
$whatsappMensagemAula = 'Olá! Vim pelo site da OneFit e gostaria de agendar uma aula experimental. Poderia me passar mais informações?';
$whatsappUrlAula = 'https://wa.me/' . $whatsappNumero . '?text=' . rawurlencode($whatsappMensagemAula);

/* Planos ativos cadastrados no backoffice (Cadastro de Planos), exibidos
 * na seção "#planos" logo abaixo. */
$planosAtivos = [];
if ($r = $conn->query("SELECT nome, valor, descricao, beneficios FROM cadastro_planos WHERE status = 'ativo' ORDER BY valor")) {
    while ($row = $r->fetch_assoc()) {
        $planosAtivos[] = [
            'nome' => $row['nome'],
            'valor' => (float) $row['valor'],
            'descricao' => $row['descricao'],
            'beneficios' => array_values(array_filter(array_map('trim', explode("\n", (string) $row['beneficios'])))),
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ONE FIT · Treino de Alta Performance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <!-- link da fonte -->
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <!-- link das animações -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <!-- link do css -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <!-- link do favicon -->
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body>

  <header class="header" data-aos="fade-down" data-aos-delay="50">
    <?php include  __DIR__ . '/components/navbar.php'; ?>
  </header>

  <section class="hero" data-aos="fade-up">
    <span class="eyebrow">Treino de alta performance</span>
    <h1>TREINE PARA<br>SER O <span class="shine">UM</span></h1>
    <p class="lead">Não existe segundo lugar no seu treino. Na ONE FIT você treina pesado, evolui com método e sai de cada aula um pouco mais perto da sua melhor versão.</p>
    <div class="hero-actions">
      <a href="#planos" class="btn btn-gold">Comece hoje</a>
      <a href="#modalidades" class="btn btn-outline">Ver modalidades</a>
    </div>
  </section>

  <div class="equip-marquee" data-aos="fade-up">
    <div class="track">
      <div class="group">
        <span>HALTERES</span><span class="sep">&#9670;</span>
        <span>BARRAS OLÍMPICAS</span><span class="sep">&#9670;</span>
        <span>KETTLEBELL</span><span class="sep">&#9670;</span>
        <span>CORDA NAVAL</span><span class="sep">&#9670;</span>
        <span>ANILHAS</span><span class="sep">&#9670;</span>
        <span>RACK DE AGACHAMENTO</span><span class="sep">&#9670;</span>
        <span>PRANCHA ABDOMINAL</span><span class="sep">&#9670;</span>
        <span>ESTEIRA</span><span class="sep">&#9670;</span>
      </div>
      <div class="group">
        <span>HALTERES</span><span class="sep">&#9670;</span>
        <span>BARRAS OLÍMPICAS</span><span class="sep">&#9670;</span>
        <span>KETTLEBELL</span><span class="sep">&#9670;</span>
        <span>CORDA NAVAL</span><span class="sep">&#9670;</span>
        <span>ANILHAS</span><span class="sep">&#9670;</span>
        <span>RACK DE AGACHAMENTO</span><span class="sep">&#9670;</span>
        <span>PRANCHA ABDOMINAL</span><span class="sep">&#9670;</span>
        <span>ESTEIRA</span><span class="sep">&#9670;</span>
      </div>
    </div>
  </div>

  <div class="stats" data-aos="fade-up">
    <div class="stat">
      <div class="num" data-target="500" data-suffix="+">0</div>
      <div class="label">Alunos ativos</div>
    </div>

    <div class="stat">
      <div class="num" data-target="15">0</div>
      <div class="label">Anos de história</div>
    </div>

    <div class="stat">
      <div class="num" data-target="20">0</div>
      <div class="label">Modalidades</div>
    </div>

    <div class="stat">
      <div class="num">05h—23h</div>
      <div class="label">Todos os dias</div>
    </div>
  </div>

  <section class="block" id="estrutura" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag">Estrutura</span>
          <h2>Equipamento<br>de verdade</h2>
        </div>
        <p>Halteres até 60kg, barras olímpicas, racks de agachamento e tudo que você precisa para treinar pesado sem fila de espera.</p>
      </div>
      <div class="equip-grid">

        <div class="equip-item" data-aos="fade-right" data-aos-delay="50">
          <img src="https://images.unsplash.com/photo-1637430308606-86576d8fef3c?q=80&w=800&auto=format&fit=crop" alt="Sala de musculação" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3>Sala de Musculação</h3>
            <p>Equipamentos completos, livres e guiados.</p>
          </div>
        </div>

        <div class="equip-item" data-aos="fade-right" data-aos-delay="100">
          <img src="https://images.unsplash.com/photo-1613845205719-8c87760ab728?q=80&w=800&auto=format&fit=crop" alt="Treino de força com halteres" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3>Treino de Força</h3>
            <p>Halteres até 60kg para todos os níveis.</p>
          </div>
        </div>

        <div class="equip-item" data-aos="fade-right" data-aos-delay="200">
          <img src="https://images.unsplash.com/photo-1734630341082-0fec0e10126c?q=80&w=800&auto=format&fit=crop" alt="Área de halteres" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3>Área de Halteres</h3>
            <p>Rack organizado, do leve ao pesado.</p>
          </div>
        </div>

        <div class="equip-item" data-aos="fade-right" data-aos-delay="350">
          <img src="https://images.unsplash.com/photo-1637870473618-8c9fa7d11f0a?q=80&w=800&auto=format&fit=crop" alt="Estúdio de spinning" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3>Estúdio de Spinning</h3>
            <p>Bikes profissionais e aulas guiadas.</p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <section class="block" id="modalidades" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag">Modalidades</span>
          <h2>Escolha sua<br>forma de treinar</h2>
        </div>
        <p>Seis caminhos, um mesmo objetivo: sair mais forte do que entrou. Todos com professores especialistas acompanhando cada série.</p>
      </div>
      <div class="mod-grid" data-aos="fade-up">

        <div class="mod-card" data-aos="fade-up" data-aos-delay="50">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M4 12h2M18 12h2M6 9v6M18 9v6M8 12h8" stroke-linecap="round" />
            </svg></div>
          <h3>Musculação</h3>
          <p>Piso completo com equipamentos livres e guiados, para hipertrofia, força e ajuste postural.</p>
        </div>

        <div class="mod-card" data-aos="fade-up" data-aos-delay="150">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M12 3l2 5-2 2-2-2 2-5zM12 21l-2-5 2-2 2 2-2 5zM3 12l5-2 2 2-2 2-5-2zM21 12l-5 2-2-2 2-2 5 2z" />
            </svg></div>
          <h3>CrossTraining</h3>
          <p>Treino funcional de alta intensidade em turmas pequenas, com WOD novo todos os dias.</p>
        </div>

        <div class="mod-card" data-aos="fade-up" data-aos-delay="200">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M12 2a5 5 0 015 5c0 3-2 4-2 7v3H9v-3c0-3-2-4-2-7a5 5 0 015-5z" />
            </svg></div>
          <h3>Funcional</h3>
          <p>Movimentos que imitam o dia a dia: força, equilíbrio e mobilidade trabalhados juntos.</p>
        </div>

        <div class="mod-card" data-aos="fade-up" data-aos-delay="250">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <circle cx="12" cy="12" r="8" />
              <path d="M12 8v4l3 2" stroke-linecap="round" />
            </svg></div>
          <h3>Spinning</h3>
          <p>Aulas em ritmo guiado por música, foco em resistência cardiovascular e queima calórica.</p>
        </div>

        <div class="mod-card" data-aos="fade-up" data-aos-delay="300">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <circle cx="12" cy="6" r="2" />
              <path d="M6 21l3-7 3 2 3-2 3 7M9 14l-2-6h10l-2 6" stroke-linecap="round" stroke-linejoin="round" />
            </svg></div>
          <h3>Boxe</h3>
          <p>Técnica, potência e explosão em treinos de sacos e pads, com preparo físico incluso.</p>
        </div>

        <div class="mod-card" data-aos="fade-up" data-aos-delay="350">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M12 3c-3 3-3 7 0 9 3-2 3-6 0-9zM7 14c0 4 2 7 5 7s5-3 5-7" stroke-linecap="round" stroke-linejoin="round" />
            </svg></div>
          <h3>Mobilidade &amp; Yoga</h3>
          <p>Recuperação ativa, alongamento guiado e respiração para equilibrar o treino pesado.</p>
        </div>

      </div>
    </div>
  </section>

  <div class="plate-divider">
    <div class="line"></div>
    <svg width="30" height="30" viewBox="0 0 30 30">
      <circle cx="15" cy="15" r="13" fill="none" stroke="var(--gold-dim)" stroke-width="2" />
      <circle cx="15" cy="15" r="4" fill="var(--gold-dim)" />
    </svg>
    <div class="line"></div>
  </div>

  <section class="block" id="planos" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag">Planos</span>
          <h2>Invista no<br>seu progresso</h2>
        </div>
        <p>Sem taxa de matrícula em nenhum plano. Cancele ou pause quando quiser, sem burocracia.</p>
      </div>
      <div class="plans">

        <?php foreach ($planosAtivos as $i => $p): ?>
          <div class="plan<?php echo $i === 1 ? ' featured' : ''; ?>" data-aos="fade-up" data-aos-delay="<?php echo 50 + $i * 50; ?>">
            <span class="plan-name"><?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
            <h3><?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="price">R$<?php echo number_format($p['valor'], 0, ',', '.'); ?><span>/mês</span></div>
            <div class="price-sub"><?php echo htmlspecialchars($p['descricao'], ENT_QUOTES, 'UTF-8'); ?></div>
            <ul>
              <?php foreach ($p['beneficios'] as $beneficio): ?>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12l4 4 10-10" />
                  </svg><?php echo htmlspecialchars($beneficio, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
            <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php" class="btn <?php echo $i === 1 ? 'btn-gold' : 'btn-outline'; ?>">Escolher plano</a>
          </div>
        <?php endforeach; ?>

      </div>
    </div>
  </section>

  <section class="block" id="depoimentos" style="background:var(--surface); border-top:1px solid var(--border); border-bottom:1px solid var(--border);" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag">Alunos</span>
          <h2>Quem treina,<br>confirma</h2>
        </div>
      </div>
      <div class="testimonials">
        <div class="testi" data-aos="fade-up" data-aos-delay="100">
          <span class="quote-mark">"</span>
          <p>Entrei sem nunca ter pegado num peso na vida. Em oito meses, os professores me ensinaram tudo, sem pressa e sem julgamento.</p>
          <div class="who">
            <div class="avatar"></div>
            <div>
              <div class="name">Mariana Alvez</div>
              <div class="role">Aluna há 8 meses</div>
            </div>
          </div>
        </div>
        <div class="testi" data-aos="fade-up" data-aos-delay="200">
          <span class="quote-mark">"</span>
          <p>O CrossTraining daqui é outro nível. Turmas pequenas, WOD sempre diferente, e o pessoal se ajuda muito entre si.</p>
          <div class="who">
            <div class="avatar"></div>
            <div>
              <div class="name">Rafael Souza</div>
              <div class="role">Aluno há 2 anos</div>
            </div>
          </div>
        </div>
        <div class="testi" data-aos="fade-up" data-aos-delay="300">
          <span class="quote-mark">"</span>
          <p>Troquei três vezes de academia antes da ONE FIT. Aqui o acompanhamento é de verdade, não é só entregar uma ficha e sumir.</p>
          <div class="who">
            <div class="avatar"></div>
            <div>
              <div class="name">Gabriely Rocha</div>
              <div class="role">Aluna há 1 ano</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="final-cta" id="contato" data-aos="fade-down">
    <span class="eyebrow">Comece agora!</span>
    <h2>Sua primeira<br>aula é <span class="shine" style="background:linear-gradient(100deg, var(--bronze) 0%, var(--gold) 25%, var(--gold-bright) 40%, #fff8e1 48%, var(--gold-bright) 56%, var(--gold) 70%, var(--bronze) 100%);background-size:260% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;">grátis</span></h2>
    <p>Apareça, treine e sinta a diferença. Sem compromisso, sem cartão, sem letras miúdas.</p>
    <a href="<?php echo htmlspecialchars($whatsappUrlAula, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-gold" target="_blank" rel="noopener noreferrer">Agendar aula experimental</a>
  </section>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <a class="whatsapp-float"
     href="<?php echo htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8'); ?>"
     target="_blank" rel="noopener noreferrer"
     aria-label="Converse com a OneFit pelo WhatsApp (abre em nova aba)">
    <span class="whatsapp-float-message" aria-hidden="true">Fale conosco pelo WhatsApp!</span>
    <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor" aria-hidden="true" focusable="false">
      <path d="M20.52 3.48A11.87 11.87 0 0 0 12.05 0C5.46 0 .1 5.36 .1 11.95c0 2.1.55 4.16 1.6 5.98L0 24l6.24-1.64a11.94 11.94 0 0 0 5.8 1.48h.01C18.64 23.84 24 18.48 24 11.89c0-3.19-1.24-6.18-3.48-8.41zM12.05 21.82a9.9 9.9 0 0 1-5.04-1.38l-.36-.21-3.73.98.99-3.64-.23-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.48 4.46-9.94 9.95-9.94a9.87 9.87 0 0 1 7.03 2.92 9.87 9.87 0 0 1 2.91 7.03c0 5.48-4.46 9.94-9.94 9.94zm5.45-7.44c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z" />
    </svg>
  </a>

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
