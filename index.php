<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/pages/dashboard/includes/aluno-profile.php');

// WhatsApp da Home: substitua pelo número real com 55 + DDD + número, somente dígitos.
$whatsappNumero = '5512996908833';
$whatsappMensagem = 'Olá! Gostaria de saber mais sobre a OneFit.';
$whatsappUrl = 'https://wa.me/' . $whatsappNumero . '?text=' . rawurlencode($whatsappMensagem);

// Mensagem exclusiva do botão "Agendar aula experimental".
$whatsappMensagemAula = 'Olá! Vim pelo site da OneFit e gostaria de agendar uma aula experimental. Poderia me passar mais informações?';
$whatsappUrlAula = 'https://wa.me/' . $whatsappNumero . '?text=' . rawurlencode($whatsappMensagemAula);

// Endereço usado no mapa da seção "Localização".
$enderecoMapa = 'Jardim das Indústrias, São José dos Campos - SP';
$mapaUrl = 'https://www.google.com/maps?q=' . rawurlencode($enderecoMapa) . '&output=embed';

/* Ícones das modalidades (chave = coluna `modalidades.icone`) — mesmo
 * conjunto oferecido no select da tela admin (bo_icones_modalidade_options
 * em pages/dashboard/includes/admin-forms.php). Mantém o SVG idêntico ao
 * que já existia fixo nesta seção antes de ficar dinâmica. */
function onefit_icone_modalidade(string $icone): string
{
    $icones = [
        'musculacao' => '<path d="M4 12h2M18 12h2M6 9v6M18 9v6M8 12h8" stroke-linecap="round" />',
        'crosstraining' => '<path d="M12 3l2 5-2 2-2-2 2-5zM12 21l-2-5 2-2 2 2-2 5zM3 12l5-2 2 2-2 2-5-2zM21 12l-5 2-2-2 2-2 5 2z" />',
        'funcional' => '<path d="M12 2a5 5 0 015 5c0 3-2 4-2 7v3H9v-3c0-3-2-4-2-7a5 5 0 015-5z" />',
        'spinning' => '<circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" stroke-linecap="round" />',
        'boxe' => '<circle cx="12" cy="6" r="2" /><path d="M6 21l3-7 3 2 3-2 3 7M9 14l-2-6h10l-2 6" stroke-linecap="round" stroke-linejoin="round" />',
        'mobilidade' => '<path d="M12 3c-3 3-3 7 0 9 3-2 3-6 0-9zM7 14c0 4 2 7 5 7s5-3 5-7" stroke-linecap="round" stroke-linejoin="round" />',
        'generico' => '<path d="M12 2l2.4 6.9H21l-5.6 4.3 2.1 7L12 16l-5.5 4.2 2.1-7L3 8.9h6.6z" stroke-linejoin="round" />',
    ];
    return $icones[$icone] ?? $icones['generico'];
}

/* Modalidades cadastradas no backoffice (aba Modalidades), exibidas na
 * seção "#modalidades" logo abaixo. */
$modalidades = [];
if ($r = $conn->query("SELECT nome, descricao, icone FROM modalidades WHERE status = 'ativo' ORDER BY id_modalidade")) {
    while ($row = $r->fetch_assoc()) {
        $modalidades[] = [
            'nome' => $row['nome'],
            'descricao' => $row['descricao'],
            'icone' => $row['icone'],
        ];
    }
}

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

/* "Há quanto tempo" o aluno está na ONE FIT, calculado a partir de
 * usuarios.data_cadastro, usado no rodapé de cada card de testemunho. */
function onefit_tempo_aluno(string $dataCadastro): string
{
    $inicio = new DateTime($dataCadastro);
    $agora = new DateTime();
    $diff = $inicio->diff($agora);
    $meses = $diff->y * 12 + $diff->m;
    if ($meses >= 12) {
        $anos = intdiv($meses, 12);
        return onefitTraduzir($anos === 1 ? '{n} ano' : '{n} anos', ['{n}' => $anos]);
    }
    if ($meses >= 1) {
        return onefitTraduzir($meses === 1 ? '{n} mês' : '{n} meses', ['{n}' => $meses]);
    }
    return onefitTraduzir('poucos dias');
}

/* Comentários enviados pelos alunos (card "Comente aqui" no backoffice) e
 * aprovados/ativados pelo admin (aba "Comentários"), exibidos na seção
 * "#depoimentos" logo abaixo — mais os comentários "avulsos" sem conta de
 * aluno (id_usuario NULL, com nome_exibido/tempo_exibido próprios), usados
 * como conteúdo inicial da home. Sem nenhum aprovado, cai no fallback fixo. */
$testemunhosHome = [];
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
if ($r = $conn->query(
    "SELECT t.texto, t.nome_exibido, t.tempo_exibido, u.nome, u.genero, u.data_cadastro, u.foto
     FROM testemunhos t LEFT JOIN usuarios u ON u.id_usuario = t.id_usuario
     WHERE t.aprovacao = 'aprovado' AND t.visibilidade = 'ativo'
     ORDER BY t.data_criacao DESC LIMIT 6"
)) {
    while ($row = $r->fetch_assoc()) {
        $testemunhosHome[] = [
            'texto' => $row['texto'],
            'nome' => $row['nome_exibido'] ?: $row['nome'],
            'role' => $row['tempo_exibido']
                ?: onefitTraduzir('Aluno há {tempo}', ['{tempo}' => onefit_tempo_aluno($row['data_cadastro'])]),
            'foto' => bo_aluno_foto_url($row['foto'] ?? null),
        ];
    }
}
if (!$testemunhosHome) {
    $testemunhosHome = [
        ['texto' => 'Entrei sem nunca ter pegado num peso na vida. Em oito meses, os professores me ensinaram tudo, sem pressa e sem julgamento.', 'nome' => 'Mariana Alvez', 'role' => 'Aluna há 8 meses', 'foto' => ''],
        ['texto' => 'O CrossTraining daqui é outro nível. Turmas pequenas, WOD sempre diferente, e o pessoal se ajuda muito entre si.', 'nome' => 'Rafael Souza', 'role' => 'Aluno há 2 anos', 'foto' => ''],
        ['texto' => 'Troquei três vezes de academia antes da {marca}. Aqui o acompanhamento é de verdade, não é só entregar uma ficha e sumir.', 'nome' => 'Gabriely Rocha', 'role' => 'Aluna há 1 ano', 'foto' => ''],
    ];
    foreach ($testemunhosHome as &$testemunhoExemplo) {
        $testemunhoExemplo['texto'] = onefitTraduzir($testemunhoExemplo['texto']);
        $testemunhoExemplo['role'] = onefitTraduzir($testemunhoExemplo['role']);
    }
    unset($testemunhoExemplo);
}
?>

<!DOCTYPE html>
<html lang="<?php echo onefitIdioma(); ?>" data-site-theme="<?php echo htmlspecialchars($GLOBALS['onefitTemaGlobal'] ?? 'dourado', ENT_QUOTES, 'UTF-8'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo of_t('{marca} · Treino de Alta Performance', ['{marca}' => onefitMarca()['name']]); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <!-- link da fonte -->
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <!-- link das animações -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <!-- link do css -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/css/home.css'); ?>">
  <!-- link do favicon -->
  <link rel="icon" data-brand-logo href="<?php echo onefitLogo(); ?>" type="image/x-icon">
<?php onefitInterfaceHead(); ?>
</head>

<body class="home-page">

  <header class="header">
    <?php include  __DIR__ . '/components/navbar.php'; ?>
  </header>

  <main id="conteudo">
  <section class="hero">
    <span class="eyebrow"><?php echo of_t('Treino de alta performance'); ?></span>
    <h1><?php echo of_t('TREINE PARA'); ?><br><?php echo of_t('SER O'); ?> <span class="shine"><?php echo of_t('UM'); ?></span></h1>
    <p class="lead"><?php echo of_t('Treine com propósito. Evolua com método. Mais perto da sua melhor versão, todos os dias.'); ?></p>
    <div class="hero-actions">
      <a href="#planos" class="btn btn-gold"><?php echo of_t('Comece hoje'); ?></a>
      <a href="#modalidades" class="btn btn-outline"><?php echo of_t('Ver modalidades'); ?></a>
    </div>
    <figure class="hero-media">
      <img src="https://images.unsplash.com/photo-1689877020200-403d8542d95d?q=85&w=1800&auto=format&fit=crop" alt="<?php echo of_t('Espaço de treino e equipamentos da academia'); ?>" width="1800" height="1000" fetchpriority="high">
      <figcaption><span><?php echo of_t('Estrutura'); ?></span><a href="#estrutura"><?php echo of_t('Conheça o espaço'); ?> <span aria-hidden="true">↗</span></a></figcaption>
    </figure>
  </section>

  <div class="equip-marquee" data-aos="fade-up">
    <div class="track">
      <div class="group">
        <span><?php echo of_t('HALTERES'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('BARRAS OLÍMPICAS'); ?></span><span class="sep">&#9670;</span>
        <span>KETTLEBELL</span><span class="sep">&#9670;</span>
        <span><?php echo of_t('CORDA NAVAL'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('ANILHAS'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('RACK DE AGACHAMENTO'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('PRANCHA ABDOMINAL'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('ESTEIRA'); ?></span><span class="sep">&#9670;</span>
      </div>
      <div class="group">
        <span><?php echo of_t('HALTERES'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('BARRAS OLÍMPICAS'); ?></span><span class="sep">&#9670;</span>
        <span>KETTLEBELL</span><span class="sep">&#9670;</span>
        <span><?php echo of_t('CORDA NAVAL'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('ANILHAS'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('RACK DE AGACHAMENTO'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('PRANCHA ABDOMINAL'); ?></span><span class="sep">&#9670;</span>
        <span><?php echo of_t('ESTEIRA'); ?></span><span class="sep">&#9670;</span>
      </div>
    </div>
  </div>

  <div class="stats" data-aos="fade-up">
    <div class="stat">
      <div class="num" data-target="500" data-suffix="+">0</div>
      <div class="label"><?php echo of_t('Alunos ativos'); ?></div>
    </div>

    <div class="stat">
      <div class="num" data-target="15">0</div>
      <div class="label"><?php echo of_t('Anos de história'); ?></div>
    </div>

    <div class="stat">
      <div class="num" data-target="20">0</div>
      <div class="label"><?php echo of_t('Modalidades'); ?></div>
    </div>

    <div class="stat">
      <div class="num"><?php echo of_t('05h—23h'); ?></div>
      <div class="label"><?php echo of_t('Todos os dias'); ?></div>
    </div>
  </div>

  <section class="block" id="estrutura" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag"><?php echo of_t('Estrutura'); ?></span>
          <h2><?php echo of_t('Equipamento'); ?><br><?php echo of_t('de verdade'); ?></h2>
        </div>
        <p><?php echo of_t('Halteres até 60kg, barras olímpicas, racks de agachamento e tudo que você precisa para treinar pesado sem fila de espera.'); ?></p>
      </div>
      <div class="equip-grid">

        <div class="equip-item" data-aos="fade-right" data-aos-delay="50">
          <img src="https://images.unsplash.com/photo-1637430308606-86576d8fef3c?q=80&w=800&auto=format&fit=crop" alt="<?php echo of_t('Sala de musculação'); ?>" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3><?php echo of_t('Sala de Musculação'); ?></h3>
            <p><?php echo of_t('Equipamentos completos, livres e guiados.'); ?></p>
          </div>
        </div>

        <div class="equip-item" data-aos="fade-right" data-aos-delay="100">
          <img src="https://images.unsplash.com/photo-1613845205719-8c87760ab728?q=80&w=800&auto=format&fit=crop" alt="<?php echo of_t('Treino de força com halteres'); ?>" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3><?php echo of_t('Treino de Força'); ?></h3>
            <p><?php echo of_t('Halteres até 60kg para todos os níveis.'); ?></p>
          </div>
        </div>

        <div class="equip-item" data-aos="fade-right" data-aos-delay="200">
          <img src="https://images.unsplash.com/photo-1734630341082-0fec0e10126c?q=80&w=800&auto=format&fit=crop" alt="<?php echo of_t('Área de halteres'); ?>" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3><?php echo of_t('Área de Halteres'); ?></h3>
            <p><?php echo of_t('Rack organizado, do leve ao pesado.'); ?></p>
          </div>
        </div>

        <div class="equip-item" data-aos="fade-right" data-aos-delay="350">
          <img src="https://images.unsplash.com/photo-1637870473618-8c9fa7d11f0a?q=80&w=800&auto=format&fit=crop" alt="<?php echo of_t('Estúdio de spinning'); ?>" loading="lazy">
          <div class="shade"></div>
          <span class="tagdot"></span>
          <div class="caption">
            <h3><?php echo of_t('Estúdio de Spinning'); ?></h3>
            <p><?php echo of_t('Bikes profissionais e aulas guiadas.'); ?></p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <section class="block" id="modalidades" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag"><?php echo of_t('Modalidades'); ?></span>
          <h2><?php echo of_t('Escolha sua'); ?><br><?php echo of_t('forma de treinar'); ?></h2>
        </div>
        <p><?php echo of_t('Vários caminhos, um mesmo objetivo: sair mais forte do que entrou. Todos com professores especialistas acompanhando cada série.'); ?></p>
      </div>
      <div class="mod-grid" data-aos="fade-up">

        <?php foreach ($modalidades as $i => $mod): ?>
        <div class="mod-card" data-aos="fade-up" data-aos-delay="<?php echo 50 + $i * 50; ?>">
          <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
              <?php echo onefit_icone_modalidade($mod['icone']); ?>
            </svg></div>
          <h3><?php echo htmlspecialchars(mb_strtoupper(mb_substr($mod['nome'], 0, 1)) . mb_substr($mod['nome'], 1)); ?></h3>
          <p><?php echo htmlspecialchars($mod['descricao']); ?></p>
        </div>
        <?php endforeach; ?>

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
          <span class="tag"><?php echo of_t('Planos'); ?></span>
          <h2><?php echo of_t('Invista no'); ?><br><?php echo of_t('seu progresso'); ?></h2>
        </div>
        <p><?php echo of_t('Sem taxa de matrícula em nenhum plano. Cancele ou pause quando quiser, sem burocracia.'); ?></p>
      </div>
      <div class="plans">

        <?php foreach ($planosAtivos as $i => $p): ?>
          <div data-featured-label="<?php echo of_t('Mais escolhido'); ?>" class="plan<?php echo $i === 1 ? ' featured' : ''; ?>" data-aos="fade-up" data-aos-delay="<?php echo 50 + $i * 50; ?>">
            <span class="plan-name"><?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
            <h3><?php echo htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="price">R$<?php echo number_format($p['valor'], 0, ',', '.'); ?><span><?php echo of_t('/mês'); ?></span></div>
            <div class="price-sub"><?php echo htmlspecialchars($p['descricao'], ENT_QUOTES, 'UTF-8'); ?></div>
            <ul>
              <?php foreach ($p['beneficios'] as $beneficio): ?>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12l4 4 10-10" />
                  </svg><?php echo htmlspecialchars($beneficio, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
            <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php" class="btn <?php echo $i === 1 ? 'btn-gold' : 'btn-outline'; ?>"><?php echo of_t('Escolher plano'); ?></a>
          </div>
        <?php endforeach; ?>

      </div>
    </div>
  </section>

  <section class="block" id="depoimentos" data-aos="fade-up">
    <div class="wrap">
      <div class="section-head">
        <div>
          <span class="tag"><?php echo of_t('Alunos'); ?></span>
          <h2><?php echo of_t('Quem treina,'); ?><br><?php echo of_t('confirma'); ?></h2>
        </div>
      </div>
      <div class="testimonials">
        <?php foreach ($testemunhosHome as $boIndex => $boTestemunho): ?>
          <div class="testi" data-aos="fade-up" data-aos-delay="<?php echo 100 + $boIndex * 100; ?>">
            <span class="quote-mark">"</span>
            <p><?php echo htmlspecialchars($boTestemunho['texto'], ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="who">
              <div class="avatar">
                <?php if (!empty($boTestemunho['foto'])): ?>
                  <img src="<?php echo htmlspecialchars($boTestemunho['foto'], ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de <?php echo htmlspecialchars($boTestemunho['nome'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                <?php endif; ?>
              </div>
              <div>
                <div class="name"><?php echo htmlspecialchars($boTestemunho['nome'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="role"><?php echo htmlspecialchars($boTestemunho['role'], ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="final-cta" id="contato" data-aos="fade-down">
    <span class="eyebrow"><?php echo of_t('Comece agora!'); ?></span>
    <h2><?php echo of_t('Sua primeira'); ?><br><?php echo of_t('aula é'); ?> <span class="shine" style="background:linear-gradient(100deg, var(--bronze) 0%, var(--gold) 25%, var(--gold-bright) 40%, var(--accent-pale, #fff8e1) 48%, var(--gold-bright) 56%, var(--gold) 70%, var(--bronze) 100%);background-size:260% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;"><?php echo of_t('grátis'); ?></span></h2>
    <p><?php echo of_t('Apareça, treine e sinta a diferença. Sem compromisso, sem cartão, sem letras miúdas.'); ?></p>
    <a href="<?php echo htmlspecialchars($whatsappUrlAula, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-gold" target="_blank" rel="noopener noreferrer"><?php echo of_t('Agendar aula experimental'); ?></a>
  </section>

  <section class="block map-section" id="localizacao" data-aos="fade-up">
    <div class="wrap section-head">
      <div>
        <span class="tag"><?php echo of_t('Localização'); ?></span>
        <h2><?php echo of_t('Venha treinar'); ?><br><?php echo of_t('perto de você'); ?></h2>
      </div>
      <p><?php echo of_t('Estamos no Jardim das Indústrias, em São José dos Campos — fácil acesso e estacionamento por perto.'); ?></p>
    </div>
    <div class="map-frame">
      <iframe
        src="<?php echo htmlspecialchars($mapaUrl, ENT_QUOTES, 'UTF-8'); ?>"
        width="100%" height="100%" style="border:0;" allowfullscreen=""
        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        title="<?php echo htmlspecialchars(of_t('Mapa - Jardim das Indústrias, São José dos Campos'), ENT_QUOTES, 'UTF-8'); ?>"></iframe>
    </div>
  </section>

  </main>
  <?php include __DIR__ . '/components/footer.php'; ?>

  <a class="whatsapp-float"
     href="<?php echo htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8'); ?>"
     target="_blank" rel="noopener noreferrer"
     aria-label="<?php echo of_t('Converse com a {marca} pelo WhatsApp (abre em nova aba)'); ?>">
    <span class="whatsapp-float-message" aria-hidden="true"><?php echo of_t('Fale conosco pelo WhatsApp!'); ?></span>
    <svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor" aria-hidden="true" focusable="false">
      <path d="M20.52 3.48A11.87 11.87 0 0 0 12.05 0C5.46 0 .1 5.36 .1 11.95c0 2.1.55 4.16 1.6 5.98L0 24l6.24-1.64a11.94 11.94 0 0 0 5.8 1.48h.01C18.64 23.84 24 18.48 24 11.89c0-3.19-1.24-6.18-3.48-8.41zM12.05 21.82a9.9 9.9 0 0 1-5.04-1.38l-.36-.21-3.73.98.99-3.64-.23-.37a9.86 9.86 0 0 1-1.51-5.25c0-5.48 4.46-9.94 9.95-9.94a9.87 9.87 0 0 1 7.03 2.92 9.87 9.87 0 0 1 2.91 7.03c0 5.48-4.46 9.94-9.94 9.94zm5.45-7.44c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.21 5.09 4.5.71.31 1.27.49 1.7.63.71.23 1.36.2 1.87.12.57-.08 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35z" />
    </svg>
  </a>

  <!-- Link para JavaScript -->
  <script src="<?php echo BASE_URL; ?>assets/js/home.js?v=<?php echo filemtime(__DIR__ . '/assets/js/home.js'); ?>"></script>

  <!-- Link para animações AOS JS -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- Animacão do AOS JS -->
  <script>
    if (window.AOS) {
    document.documentElement.classList.add('aos-initialized');
    AOS.init({
      duration: 800,
      once: true,
      offset: 60,
      disable: () => window.matchMedia('(prefers-reduced-motion: reduce)').matches
    });
    }
  </script>

</body>

</html>
