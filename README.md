# ONE FIT — Treino de Alta Performance

Site institucional em **PHP** para uma academia/estúdio de treino, com landing page (hero, modalidades, planos, depoimentos, CTA final), área de **login**, **matrícula** e **dashboard** do aluno.

> "Treine para ser o **UM**." — não existe segundo lugar no seu treino.

---

## ✨ Funcionalidades

- **Landing page** completa: hero animado, marquee de equipamentos, contadores de estatísticas, seção de estrutura/fotos, grid de modalidades, planos de assinatura, depoimentos de alunos e CTA de aula experimental.
- **Autenticação**: tela de login (`pages/login`) com vídeo de apoio.
- **Matrícula**: fluxo de cadastro de novos alunos (`pages/matricula`) com vídeo de apoio.
- **Dashboard** do aluno (`pages/dashboard`).
- **Componentes reutilizáveis**: navbar e footer centralizados em `components/`.
- **Animações on-scroll** com [AOS (Animate On Scroll)](https://michalsnik.github.io/aos/).
- Tipografia customizada via Google Fonts: `Big Shoulders Display`, `Manrope` e `JetBrains Mono`.

---

## 🗂️ Estrutura do projeto

```
onefit/
├── assets/
│   ├── css/
│   │   ├── home.css
│   │   ├── login.css
│   │   └── matricula.css
│   ├── img/
│   │   └── logo/
│   │       └── logo.webp
│   ├── videos/
│   │   ├── video-cadastro.mp4
│   │   └── video-login.mp4
│   └── js/
│       ├── home.js
│       ├── login.js
│       └── matricula.js
├── components/
│   ├── footer.php
│   └── navbar.php
├── config/
│   ├── conn.php          # conexão com o banco de dados
│   └── parametros.php    # constantes/parâmetros globais (ex: BASE_URL)
├── pages/
│   ├── dashboard/
│   │   └── dashboard.php
│   ├── errors/
│   ├── login/
│   │   └── login.php
│   └── matricula/
│       └── matricula.php
├── uploads/               # arquivos enviados pelos usuários
├── .env                   # variáveis de ambiente (não versionar)
├── .gitattributes
├── .htaccess
└── index.php              # página inicial
```

---

## 🛠️ Tecnologias utilizadas

| Camada          | Tecnologia                                   |
|-----------------|-----------------------------------------------|
| Back-end        | PHP                                            |
| Banco de dados  | MySQL/MariaDB (via `config/conn.php`)          |
| Front-end       | HTML5, CSS3, JavaScript                        |
| Animações       | [AOS.js](https://unpkg.com/aos@2.3.4/dist/aos.js) |
| Fontes          | Google Fonts (Big Shoulders Display, Manrope, JetBrains Mono) |
| Servidor web    | Apache (`.htaccess`)                           |

---

## ⚙️ Pré-requisitos

- PHP 7.4+ (recomendado 8.x)
- Servidor Apache (ou similar) com suporte a `.htaccess`
- MySQL/MariaDB
- Extensão `mysqli` ou `PDO` habilitada no PHP

---

## 🚀 Como rodar localmente

1. **Clone o repositório**
   ```bash
   git clone <url-do-repositorio>
   cd onefit
   ```

2. **Configure as variáveis de ambiente**

   Crie um arquivo `.env` na raiz do projeto (caso não exista) com as credenciais do banco:
   ```env
   DB_HOST=localhost
   DB_NAME=onefit
   DB_USER=root
   DB_PASS=senha
   BASE_URL=http://localhost/onefit/
   ```

3. **Configure o `config/parametros.php`**

   Garanta que a constante `BASE_URL` aponte corretamente para a raiz do projeto no seu ambiente local.

4. **Configure a conexão com o banco (`config/conn.php`)**

   Ajuste host, usuário, senha e nome do banco conforme seu ambiente.

5. **Importe o banco de dados**

   Crie o banco `onefit` (ou o nome definido) e importe o dump SQL do projeto, se houver.

6. **Suba o servidor**

   Usando o servidor embutido do PHP:
   ```bash
   php -S localhost:8000
   ```
   Ou configure um Virtual Host no Apache/XAMPP/WAMP apontando para a pasta do projeto.

7. **Acesse no navegador**
   ```
   http://localhost:8000
   ```

---

## 📄 Páginas principais

| Rota                              | Descrição                              |
|------------------------------------|-----------------------------------------|
| `/index.php`                       | Landing page (home)                     |
| `/pages/login/login.php`           | Login do aluno                          |
| `/pages/matricula/matricula.php`   | Formulário de matrícula                 |
| `/pages/dashboard/dashboard.php`   | Painel do aluno logado                  |
| `/pages/errors/`                   | Páginas de erro (404, etc.)             |

---

## 🎨 Design

O visual segue uma identidade escura e dourada ("gold/bronze"), remetendo a academia de alta performance, com:
- Tipografia impactante (`Big Shoulders Display`) para títulos
- Efeito "shine" dourado em destaques de texto
- Marquee infinito com os equipamentos disponíveis
- Cards de planos com plano "featured" em destaque
- Depoimentos com avatar e citação

---

## 📌 Próximos passos sugeridos

- [ ] Adicionar testes automatizados
- [ ] Documentar o schema do banco de dados
- [ ] Adicionar tela de recuperação de senha
- [ ] Internacionalização (i18n) caso necessário
- [ ] CI/CD para deploy automático

---

## 📝 Licença

MIT License

Copyright (c) 2026 Daniel Hossaki

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

## 👤 Créditos

Desenvolvido por **Grupo 1** para **ONE FIT**.