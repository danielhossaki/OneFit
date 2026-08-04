<head>
    <style>
        :root {
            --bg: #14110E;
            --bg2: #14110e83;
            --surface: #1E1A15;
            --surface-2: #241F18;
            --text: #F3EDE2;
            --text-muted: #B0A896;
            --gold: #D4AF37;
            --gold-bright: #F4C430;
            --gold-dim: #8A6B21;
            --bronze: #A8763F;
            --border: rgba(243, 237, 226, 0.12);
            --shadow: rgba(0, 0, 0, 0.45);
            --overlay: rgba(20, 17, 14, 0.72);
        }

        html[data-theme="light"] {
            --bg: #F7F4EE;
            --bg2: #F7F4EE83;
            --surface: #FFFFFF;
            --surface-1: #f4e2b40d;
            --surface-2: #d4cab5;
            --text: #1A1613;
            --text-muted: #6B6255;
            --gold: #B8892B;
            --gold-bright: #8A6414;
            --gold-dim: #D8BE72;
            --bronze: #7A4E2D;
            --border: rgba(26, 22, 19, 0.12);
            --shadow: rgba(26, 22, 19, 0.12);
            --overlay: rgba(247, 244, 238, 0.82);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ---------- Header ---------- */
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: var(--bg2);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
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

        .nav-links {
            display: flex;
            gap: 36px;
            align-items: center;
        }

        .nav-links a {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.25s ease;
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
            color: var(--text);
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
            background: var(--surface-2);
            border: 1px solid var(--border);
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
            border-color: var(--border);
            color: var(--text);
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
            color: var(--text);
            font-size: 30px;
            cursor: pointer;
        }

        @media (max-width:992px) {

            .nav {
                padding: 15px 25px;
            }

            .menu-toggle {
                display: block;
            }

            .nav-links {
                position: fixed;
                top: 0;
                right: -100%;
                width: min(280px, 80vw);
                height: 100vh;

                background: var(--surface);

                backdrop-filter: blur(25px);

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
                border-top: 1px solid var(--border);
            }

            .nav-mobile-actions .btn {
                width: 100%;
            }

        }
    </style>
</head>

<nav class="nav" >

    <div class="logo">
        <img src="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" alt="Logo One Fit" class="logo-mark">
    </div>

    <div class="nav-links" id="navLinks">
        <a href="#estrutura">Estrutura</a>
        <a href="#modalidades">Treinos</a>
        <a href="#planos">Planos</a>
        <a href="#depoimentos">Alunos</a>
        <a href="#contato">Contato</a>

        <!-- Ações visíveis só quando o menu mobile está aberto -->
        <div class="nav-mobile-actions">
            <a href="<?php echo BASE_URL; ?>pages/login/login.php" class="btn btn-outline">Entrar</a>
            <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php" class="btn btn-gold">Matricule-se</a>
        </div>
    </div>

    <div class="nav-right">

        <button class="theme-toggle" id="themeToggle">
            <span class="knob">
                <svg id="toggleIcon" viewBox="0 0 24 24" fill="none" stroke="#1A1613" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 3v1M12 20v1M4.2 4.2l.7.7M19.1 19.1l.7.7M3 12h1M20 12h1M4.2 19.8l.7-.7M19.1 4.9l.7-.7" />
                    <circle cx="12" cy="12" r="4.5" />
                </svg>
            </span>
        </button>

        <a href="<?php echo BASE_URL; ?>pages/login/login.php" class="btn btn-outline">Entrar</a>

        <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php" class="btn btn-gold">Matricule-se</a>

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