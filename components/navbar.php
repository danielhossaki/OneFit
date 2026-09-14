<head>
    <style>
        :root {
            --shadow: rgba(0, 0, 0, 0.45);
        }

        html[data-theme="light"] {
            --shadow: rgba(26, 22, 19, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ---------- Header ---------- */
        /* Estilo "Apple" (vidro fosco escuro, tipo a navbar da apple.com):
           fundo escuro fixo + blur, igual em qualquer tema de cor e nos dois
           modos (claro/escuro) — por isso NÃO usa var(--bg)/var(--theme-*)
           aqui. Com fundo fixo, o texto (mais abaixo) também pode ser cor
           fixa clara, sempre com contraste garantido, em vez de depender do
           que está sendo rolado atrás do header (que mudava de claro pra
           escuro dependendo da seção/tema). */
        header {
            position: fixed;
            top: 0.5rem;
            left: 2.5%;
            right: 2.5%;
            z-index: 100;
            background: rgba(22, 22, 23, 0.72);
            backdrop-filter: saturate(180%) blur(20px);
            -webkit-backdrop-filter: saturate(180%) blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
            transition: background 0.4s ease, border-color 0.4s ease;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 32px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Acima do breakpoint mobile: logo + menu + botões viram um único
           bloco centralizado na tela, em vez de logo na ponta esquerda e
           botões na ponta direita. Tira o max-width daqui (o que sobra é o
           padding) — com max-width:1200px + gap entre os 3 grupos, o
           conteúdo passava da largura disponível e forçava um encolhimento
           desigual dos itens, o que deixava tudo puxado pra esquerda em vez
           de centralizado. Sem esse limite, o .nav ocupa a largura toda do
           header e o conteúdo (bem mais estreito) centraliza livremente
           dentro dela. No mobile/tablet (abaixo de 1300px) o menu vira
           painel lateral e some do fluxo normal, então lá mantém-se o
           space-between + max-width original (logo à esquerda, hambúrguer
           à direita) — ver o breakpoint irmão em @media (max-width:1299px)
           mais abaixo; os dois precisam ficar sincronizados. */
        @media (min-width: 1300px) {
            .nav {
                max-width: none;
                justify-content: space-around;
                gap: 40px;
            }
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-mark {
            height: 75px;
            width: auto;
            display: block;
        }

        /* Nome da marca: o nome do tema e "Fit" são coloridos separadamente
           por .brand-nome/.brand-fit (ver assets/css/interface.css), então
           aqui só ficam as regras de tipografia compartilhadas pelas duas
           partes. */
        .logo [data-brand-name] {
            font-family: 'Big Shoulders Display', sans-serif;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            line-height: 1;
            white-space: nowrap;
        }

        .nav-links {
            display: flex;
            gap: 36px;
            align-items: center;
        }

        .nav-links a {
            font-size: 13px;
            font-weight: 500;
            color: rgba(235, 235, 245, 0.68);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.2s ease;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0;
            height: 2px;
            background: var(--gold);
            transition: width 0.25s ease;
        }

        .nav-links a:hover {
            color: #fff;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .theme-toggle {
            width: 52px;
            height: 28px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            position: relative;
            cursor: pointer;
            flex-shrink: 0;
        }

        .theme-toggle .knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s cubic-bezier(.4, 0, .2, 1);
            box-shadow: 0 1px 4px var(--shadow);
        }

        html[data-theme="light"] .theme-toggle .knob {
            transform: translateX(24px);
        }

        .theme-toggle .knob svg {
            width: 13px;
            height: 13px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 26px;
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
        }

        .btn:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 3px;
        }

        .btn-gold {
            background: linear-gradient(120deg, var(--bronze), var(--gold) 45%, var(--gold-bright) 60%, var(--gold) 75%, var(--bronze));
            background-size: 250% 100%;
            color: var(--bg);
        }

        .btn-gold:hover {
            background-position: 100% 0;
            box-shadow: 0 8px 22px -8px var(--gold);
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border-color: rgba(255, 255, 255, 0.28);
            color: rgba(255, 255, 255, 0.92);
        }

        .btn-outline:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Ações que só existem dentro do painel mobile (escondidas no desktop) */
        .nav-mobile-actions {
            display: none;
        }

        /* ===== MENU HAMBURGUER ===== */

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.92);
            font-size: 30px;
            cursor: pointer;
        }

        @media (max-width:1299px) {

            .nav {
                padding: 15px 25px;
            }

            .menu-toggle {
                display: block;
            }

            .logo-mark {
                height: 56px;
            }

            .logo [data-brand-name] {
                font-size: 17px;
            }

            .nav-links {
                position: fixed;
                top: 0;
                right: -100%;
                width: min(280px, 80vw);
                height: 100vh;

                background: rgba(20, 20, 22, 0.86);

                backdrop-filter: saturate(180%) blur(24px);

                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;

                gap: 35px;

                transition: .35s;

                box-shadow: -10px 0 30px rgba(0, 0, 0, .25);

                z-index: 999;
            }

            .nav-links.active {
                right: 0;
            }

            .nav-links a {
                font-size: 18px;
            }

            /* Os botões somem da barra de topo... */
            .nav-right>.btn-gold,
            .nav-right>.btn-outline {
                display: none;
            }

            /* ...mas reaparecem dentro do painel do menu, então continuam acessíveis */
            .nav-mobile-actions {
                display: flex;
                flex-direction: column;
                gap: 14px;
                width: 200px;
                margin-top: 10px;
                padding-top: 30px;
                border-top: 1px solid rgba(255, 255, 255, 0.14);
            }

            .nav-mobile-actions .btn {
                width: 100%;
            }

        }

        /* Telas de celular bem estreitas: logo + toggle de tema + hambúrguer
           encostam um no outro (principalmente com nomes de marca mais
           longos, tipo "NATUREFIT"/"BLOODFIT") — encolhe um pouco mais e
           reduz os espaçamentos pra garantir que sempre caiba numa linha. */
        @media (max-width: 420px) {
            .nav {
                padding: 12px 16px;
            }

            .nav-right {
                gap: 10px;
            }

            .logo {
                gap: 6px;
            }

            .logo-mark {
                height: 40px;
            }

            .logo [data-brand-name] {
                font-size: 13px;
            }
        }
    </style>
</head>

<nav class="nav" >

    <div class="logo">
        <img data-brand-logo src="<?php echo onefitLogo(); ?>" alt="Logo <?php echo onefitNomeMarca(); ?>" class="logo-mark">
        <span data-brand-name><?php echo onefitBrandNameHtml(); ?></span>
    </div>

    <div class="nav-links" id="navLinks">
        <a href="#estrutura"><?php echo of_t('Estrutura'); ?></a>
        <a href="#modalidades"><?php echo of_t('Treinos'); ?></a>
        <a href="#planos"><?php echo of_t('Planos'); ?></a>
        <a href="#depoimentos"><?php echo of_t('Alunos'); ?></a>
        <a href="#localizacao"><?php echo of_t('Localização'); ?></a>

        <!-- Ações visíveis só quando o menu mobile está aberto -->
        <div class="nav-mobile-actions">
            <a href="<?php echo BASE_URL; ?>pages/login/login.php" class="btn btn-outline"><?php echo of_t('Entrar'); ?></a>
            <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php" class="btn btn-gold"><?php echo of_t('Matricule-se'); ?></a>
        </div>
    </div>

    <div class="nav-right">

        <button class="theme-toggle" id="themeToggle">
            <span class="knob">
                <svg id="toggleIcon" viewBox="0 0 24 24" fill="none" stroke="var(--accent-ink)" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7" />
                    <circle cx="12" cy="12" r="4.5" />
                </svg>
            </span>
        </button>

        <a href="<?php echo BASE_URL; ?>pages/login/login.php" class="btn btn-outline"><?php echo of_t('Entrar'); ?></a>

        <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php" class="btn btn-gold"><?php echo of_t('Matricule-se'); ?></a>

        <button class="menu-toggle" id="menuToggle">
            ☰
        </button>

    </div>

</nav>

<script>
    const root = document.documentElement;
    const toggle = document.getElementById('themeToggle');
    const icon = document.getElementById('toggleIcon');

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        if (theme === 'light') {
            icon.innerHTML = '<path d="M12 3.5a8.5 8.5 0 108.5 8.5A6.8 6.8 0 0112 3.5z"/>';
        } else {
            icon.innerHTML = '<path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7"/><circle cx="12" cy="12" r="4.5"/>';
        }
    }

    let currentTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    applyTheme(currentTheme);

    toggle.addEventListener('click', () => {
        currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(currentTheme);
    });




    const menu = document.getElementById("navLinks");
    const menuBtn = document.getElementById("menuToggle");

    menuBtn.addEventListener("click", () => {

        menu.classList.toggle("active");

        if (menu.classList.contains("active")) {
            menuBtn.innerHTML = "✕";
        } else {
            menuBtn.innerHTML = "☰";
        }

    });

    document.querySelectorAll(".nav-links a").forEach(link => {

        link.addEventListener("click", () => {

            menu.classList.remove("active");
            menuBtn.innerHTML = "☰";

        });

    });
</script>
