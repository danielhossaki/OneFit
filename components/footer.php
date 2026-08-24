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

        body {
            background: var(--bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .wrap {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* ---------- Footer ---------- */
        footer {
            background-color: var(--bg2);
            backdrop-filter: blur(10px);
            border-top: 1px solid var(--border);
            padding: 64px 32px 32px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr 1.1fr 1fr;
            gap: 24px;
            margin-bottom: 56px;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .footer-link {
            font-size: 15px;
            color: var(--text);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .footer-link .arrow {
            font-size: 13px;
            color: var(--text-muted);
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .footer-link:hover {
            color: var(--gold-bright);
        }

        .footer-link:hover .arrow {
            color: var(--gold-bright);
            transform: translate(2px, -2px);
        }

        .footer-col.contact h4 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 18px;
            border: 1px solid var(--border);
            border-radius: 999px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            white-space: nowrap;
            width: fit-content;
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .contact-btn:hover {
            border-color: var(--gold);
            background: var(--surface);
        }

        .contact-btn .arrow {
            color: var(--gold-bright);
        }

        /* ---------- Social block ---------- */
        .social-block {
            text-align: center;
            padding: 8px 0 48px;
        }

        .social-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 28px;
        }

        .social-icons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            transition: border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .social-icons a:hover {
            border-color: var(--gold);
            color: var(--gold-bright);
            transform: translateY(-2px);
        }

        .social-icons svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        /* ---------- Footer bottom ---------- */
        .footer-bottom {
            text-align: center;
            justify-content: center;
            display: flex;
            padding-top: 28px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-muted);
        }

        .footer-bottom a {
            color: var(--text-muted);
            text-decoration: none;
        }

        .footer-bottom a:hover {
            color: var(--gold);
        }

        /* ---------- Mobile ---------- */
        @media (max-width: 860px) {
            .footer-grid {
                grid-template-columns: 1fr 1fr;
                row-gap: 40px;
                justify-content: center;
                align-items: center;
            }

            .footer-col {
                align-items: center;
                text-align: center;
            }

            .social-title {
                font-size: 16px;
                padding: 0 12px;
            }

            footer {
                padding: 48px 20px 24px;
            }
        }

        @media (max-width: 520px) {
            .footer-grid {
                grid-template-columns: 1fr;
                justify-content: center;
                align-items: center;
            }
        }
    </style>
</head>

<footer>
    <div class="wrap">
        <div class="footer-grid">

            <div class="footer-col">
                <a href="" class="footer-link">Sobre nós <span class="arrow">↗</span></a>
                <a href="<?php echo BASE_URL; ?>pages/matricula.php" class="footer-link">Matricule-se <span class="arrow">↗</span></a>
                <a href="#planos" class="footer-link">Conheça nossos planos <span class="arrow">↗</span></a>
            </div>

            <div class="footer-col">
                <a href="#" class="footer-link">Encontre nossa unidade <span class="arrow">↗</span></a>
                <a href="#" class="footer-link">Conheça nosso espaço <span class="arrow">↗</span></a>
                <a href="#" class="footer-link">Nossos profissionais <span class="arrow">↗</span></a>
            </div>

            <div class="footer-col">
                <a href="" class="footer-link">Termos de uso <span class="arrow">↗</span></a>
                <a href="" class="footer-link">Políticas de privacidade <span class="arrow">↗</span></a>
                <a href="https://mail.google.com/mail/u/0/#inbox?compose=CllgCJNstzFdDCFXGTcQrssZxrtrZCTkwNNMqszFVwlCrVvpKRpwjfrVTLqLgNBtQGQBKSqDbRg" class="footer-link">Ajuda e suporte <span class="arrow">↗</span></a>
            </div>

            <div class="footer-col contact">
                <h4>Entre em contato</h4>
                <a href="https://mail.google.com/mail/u/0/#inbox?compose=CllgCJNstzFdDCFXGTcQrssZxrtrZCTkwNNMqszFVwlCrVvpKRpwjfrVTLqLgNBtQGQBKSqDbRg" class="contact-btn">
                    Converse com a One... <span class="arrow">↗</span>
                </a>
            </div>

        </div>

        <div class="social-block">
            <div class="social-title">Visite nossas redes sociais</div>
            <div class="social-icons">
                <a href="#" aria-label="Facebook">
                    <svg viewBox="0 0 24 24"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.44 2.91h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94z"/></svg>
                </a>
                <a href="https://www.instagram.com/" aria-label="Instagram">
                    <svg viewBox="0 0 24 24"><path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.22.6 1.77 1.15.5.5.9 1.11 1.15 1.77.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 0 1-1.15 1.77 4.9 4.9 0 0 1-1.77 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.77-1.15 4.9 4.9 0 0 1-1.15-1.77c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.22 1.15-1.77A4.9 4.9 0 0 1 5.45.53C6.09.28 6.82.11 7.88.06 8.94.01 9.28 0 12 0zm0 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4zm5.2-8.4a1.17 1.17 0 1 1 0-2.34 1.17 1.17 0 0 1 0 2.34z"/></svg>
                </a>
                <a href="https://www.linkedin.com/" aria-label="LinkedIn">
                    <svg viewBox="0 0 24 24"><path d="M20.45 2H3.55A1.55 1.55 0 0 0 2 3.55v16.9A1.55 1.55 0 0 0 3.55 22h16.9A1.55 1.55 0 0 0 22 20.45V3.55A1.55 1.55 0 0 0 20.45 2zM8.34 18.6H5.67V9.99h2.67v8.61zM7 8.84a1.55 1.55 0 1 1 0-3.1 1.55 1.55 0 0 1 0 3.1zM18.6 18.6h-2.67v-4.5c0-1.07-.02-2.45-1.5-2.45-1.5 0-1.73 1.17-1.73 2.37v4.58H10.03V9.99h2.56v1.18h.04c.36-.67 1.23-1.38 2.53-1.38 2.7 0 3.2 1.78 3.2 4.1v4.71z"/></svg>
                </a>
                <a href="https://www.youtube.com/" aria-label="YouTube">
                    <svg viewBox="0 0 24 24"><path d="M23.5 6.19a3.02 3.02 0 0 0-2.12-2.14C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.38.55A3.02 3.02 0 0 0 .5 6.19 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.81 3.02 3.02 0 0 0 2.12 2.14C4.5 20.5 12 20.5 12 20.5s7.5 0 9.38-.55a3.02 3.02 0 0 0 2.12-2.14A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.81zM9.6 15.6V8.4l6.27 3.6-6.27 3.6z"/></svg>
                </a>
                <a href="https://www.tiktok.com/" aria-label="TikTok">
                    <svg viewBox="0 0 24 24"><path d="M16.6 2h-3.2v13.8a2.6 2.6 0 1 1-2.6-2.6c.24 0 .47.03.68.08V9.9a5.8 5.8 0 1 0 5.12 5.76V8.3a7.6 7.6 0 0 0 4.2 1.27V6.4a4.4 4.4 0 0 1-4.2-4.4z"/></svg>
                </a>
            </div>
        </div>

        <div class="footer-bottom">
            <span>© 2026 Desenvolvido por Grupo 1</span>
        </div>
    </div>
</footer>
