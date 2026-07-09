<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ONE FIT — Treino de Alta Performance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="../assets/css/home.css">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
</head>

<body>

<header>
  <nav class="nav">
    <div class="logo">
      <img src="../assets/img/logo/logo.webp" alt="One Fit" class="logo-mark">
    </div>
    <div class="nav-links">
      <a href="#modalidades">Treinos</a>
      <a href="#planos">Planos</a>
      <a href="#depoimentos">Alunos</a>
      <a href="#contato">Contato</a>
    </div>
    <div class="nav-right">
      <button class="theme-toggle" id="themeToggle" aria-label="Alternar tema claro e escuro">
        <span class="knob">
          <svg id="toggleIcon" viewBox="0 0 24 24" fill="none" stroke="#1A1613" stroke-width="2.5" stroke-linecap="round">
            <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7"/>
            <circle cx="12" cy="12" r="4.5"/>
          </svg>
        </span>
      </button>
      <a href="#planos" class="btn btn-gold">Matricule-se</a>
    </div>
  </nav>
</header>

<section class="hero">
  <span class="eyebrow">Treino de alta performance</span>
  <h1>TREINE PARA<br>SER O <span class="shine">UM</span></h1>
  <p class="lead">Não existe segundo lugar no seu treino. Na ONE FIT você treina pesado, evolui com método e sai de cada aula um pouco mais perto da sua melhor versão.</p>
  <div class="hero-actions">
    <a href="#planos" class="btn btn-gold">Comece hoje</a>
    <a href="#modalidades" class="btn btn-outline">Ver modalidades</a>
  </div>
</section>

<div class="stats">
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

<section class="block" id="modalidades">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">Modalidades</span>
        <h2>Escolha sua<br>forma de treinar</h2>
      </div>
      <p>Seis caminhos, um mesmo objetivo: sair mais forte do que entrou. Todos com professores especialistas acompanhando cada série.</p>
    </div>
    <div class="mod-grid">

      <div class="mod-card">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 12h2M18 12h2M6 9v6M18 9v6M8 12h8" stroke-linecap="round"/></svg></div>
        <h3>Musculação</h3>
        <p>Piso completo com equipamentos livres e guiados, para hipertrofia, força e ajuste postural.</p>
      </div>

      <div class="mod-card">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3l2 5-2 2-2-2 2-5zM12 21l-2-5 2-2 2 2-2 5zM3 12l5-2 2 2-2 2-5-2zM21 12l-5 2-2-2 2-2 5 2z"/></svg></div>
        <h3>CrossTraining</h3>
        <p>Treino funcional de alta intensidade em turmas pequenas, com WOD novo todos os dias.</p>
      </div>

      <div class="mod-card">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2a5 5 0 015 5c0 3-2 4-2 7v3H9v-3c0-3-2-4-2-7a5 5 0 015-5z"/></svg></div>
        <h3>Funcional</h3>
        <p>Movimentos que imitam o dia a dia: força, equilíbrio e mobilidade trabalhados juntos.</p>
      </div>

      <div class="mod-card">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2" stroke-linecap="round"/></svg></div>
        <h3>Spinning</h3>
        <p>Aulas em ritmo guiado por música, foco em resistência cardiovascular e queima calórica.</p>
      </div>

      <div class="mod-card">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="6" r="2"/><path d="M6 21l3-7 3 2 3-2 3 7M9 14l-2-6h10l-2 6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <h3>Boxe</h3>
        <p>Técnica, potência e explosão em treinos de sacos e pads, com preparo físico incluso.</p>
      </div>

      <div class="mod-card">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3c-3 3-3 7 0 9 3-2 3-6 0-9zM7 14c0 4 2 7 5 7s5-3 5-7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <h3>Mobilidade &amp; Yoga</h3>
        <p>Recuperação ativa, alongamento guiado e respiração para equilibrar o treino pesado.</p>
      </div>

    </div>
  </div>
</section>

<div class="plate-divider">
  <div class="line"></div>
  <svg width="30" height="30" viewBox="0 0 30 30"><circle cx="15" cy="15" r="13" fill="none" stroke="var(--gold-dim)" stroke-width="2"/><circle cx="15" cy="15" r="4" fill="var(--gold-dim)"/></svg>
  <div class="line"></div>
</div>

<section class="block" id="planos">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">Planos</span>
        <h2>Invista no<br>seu progresso</h2>
      </div>
      <p>Sem taxa de matrícula em nenhum plano. Cancele ou pause quando quiser, sem burocracia.</p>
    </div>
    <div class="plans">

      <div class="plan">
        <span class="plan-name">Desafiante</span>
        <h3>Iniciante</h3>
        <div class="price">R$99<span>/mês</span></div>
        <div class="price-sub">Ideal para começar no seu ritmo</div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Acesso à musculação</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>2 modalidades por semana</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Avaliação física inicial</li>
        </ul>
        <a href="#contato" class="btn btn-outline">Escolher plano</a>
      </div>

      <div class="plan featured">
        <span class="plan-name">Campeão</span>
        <h3>Completo</h3>
        <div class="price">R$179<span>/mês</span></div>
        <div class="price-sub">O mais escolhido pelos alunos</div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Acesso ilimitado a todas as modalidades</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Treino personalizado mensal</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Avaliação física trimestral</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Acesso ao app de treino</li>
        </ul>
        <a href="#contato" class="btn btn-gold">Escolher plano</a>
      </div>

      <div class="plan">
        <span class="plan-name">Lenda</span>
        <h3>Elite</h3>
        <div class="price">R$289<span>/mês</span></div>
        <div class="price-sub">Para quem quer performance máxima</div>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Tudo do plano Completo</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>2 personal training por semana</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Acompanhamento nutricional</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l4 4 10-10"/></svg>Armário fixo reservado</li>
        </ul>
        <a href="#contato" class="btn btn-outline">Escolher plano</a>
      </div>

    </div>
  </div>
</section>

<section class="block" id="depoimentos" style="background:var(--surface); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">Alunos</span>
        <h2>Quem treina,<br>confirma</h2>
      </div>
    </div>
    <div class="testimonials">
      <div class="testi">
        <span class="quote-mark">"</span>
        <p>Entrei sem nunca ter pegado num peso na vida. Em oito meses, os professores me ensinaram tudo, sem pressa e sem julgamento.</p>
        <div class="who"><div class="avatar"></div><div><div class="name">Mariana Alves</div><div class="role">Aluna há 8 meses</div></div></div>
      </div>
      <div class="testi">
        <span class="quote-mark">"</span>
        <p>O CrossTraining daqui é outro nível. Turmas pequenas, WOD sempre diferente, e o pessoal se ajuda muito entre si.</p>
        <div class="who"><div class="avatar"></div><div><div class="name">Rafael Souza</div><div class="role">Aluno há 2 anos</div></div></div>
      </div>
      <div class="testi">
        <span class="quote-mark">"</span>
        <p>Troquei três academias antes da ONE FIT. Aqui o acompanhamento é de verdade, não é só entregar uma ficha e sumir.</p>
        <div class="who"><div class="avatar"></div><div><div class="name">Juliana Prado</div><div class="role">Aluna há 1 ano</div></div></div>
      </div>
    </div>
  </div>
</section>

<section class="final-cta" id="contato">
  <span class="eyebrow">Comece agora</span>
  <h2>Sua primeira<br>aula é <span class="shine" style="background:linear-gradient(100deg, var(--bronze) 0%, var(--gold) 25%, var(--gold-bright) 40%, #fff8e1 48%, var(--gold-bright) 56%, var(--gold) 70%, var(--bronze) 100%);background-size:260% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;">grátis</span></h2>
  <p>Apareça, treine e sinta a diferença. Sem compromisso, sem cartão, sem letras miúdas.</p>
  <a href="#" class="btn btn-gold">Agendar aula experimental</a>
</section>

<?php include "../components/footer.php"; ?>

<!-- Link para JavaScript -->
<script src="../assets/js/home.js"></script>

</body>

</html>