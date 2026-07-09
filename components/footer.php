
<head>
    <style>
        :root {
            --bg: #14110E;
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

        /* ---------- Footer ---------- */
        footer {
            background-color: var(--surface-1);
            border-top: 1px solid var(--border);
            padding: 56px 32px 32px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 48px;
        }

        .footer-grid h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--gold);
            margin-bottom: 18px;
        }

        .footer-grid p,
        .footer-grid a {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 2;
            display: block;
        }

        .footer-grid a:hover {
            color: var(--gold);
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 28px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 12px;
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 860px) {
            .nav-links {
                display: none;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat:nth-child(3) {
                border-left: none;
            }

            .stat {
                border-bottom: 1px solid var(--border);
            }

            .mod-grid {
                grid-template-columns: 1fr;
            }

            .plans {
                grid-template-columns: 1fr;
            }

            .testimonials {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }

            .hero {
                padding: 150px 20px 90px;
            }

            section.block {
                padding: 72px 20px;
            }
        }
    </style>
</head>

<footer>
    <div class="wrap">
        <div class="footer-grid">

            <div>
                <img src="../assets/img/logo/logo.webp" alt="One Fit" class="logo-mark">
                <p>Treino de alta performance. Disciplina, método e comunidade desde 2011.</p>
            </div>

            <div>
                <h4>Estúdio</h4>
                <a href="#modalidades">Treinos</a>
                <a href="#planos">Planos</a>
                <a href="#depoimentos">Alunos</a>
            </div>

            <div>
                <h4>Contato</h4>
                <p>Rua das Oficinas, 240</p>
                <p>São José dos Campos, SP</p>
                <p>(12) 99123-4567</p>
            </div>

            <div>
                <h4>Horário</h4>
                <p>Seg – Sex · 05h às 23h</p>
                <p>Sáb · 07h às 18h</p>
                <p>Dom · 08h às 12h</p>
            </div>

        </div>

        <div class="footer-bottom">
            <span>© 2026 One Fit Studio. Todos os direitos reservados.</span>
            <span class="mono">FEITO PARA SER O UM</span>
        </div>
    </div>
</footer>