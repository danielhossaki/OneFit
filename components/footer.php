<head>
    <style>
        /* ---------- Footer ---------- */
        footer {
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

<body>
</body>
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

<script>
    document.addEventListener("DOMContentLoaded", () => {
    fetch("components/footer.php")
        .then(response => {
            if (!response.ok) {
                throw new Error("Não foi possível carregar o footer.");
            }
            return response.text();
        })
        .then(data => {
            document.getElementById("footer").innerHTML = data;
        })
        .catch(error => console.error(error));
});
</script>