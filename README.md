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
│   │   ├── matricula.css
│   │   ├── dashboard.css     # backoffice/dashboard (usa as variáveis de tema)
│   │   ├── carrinho.css      # carrinho/checkout (usa as variáveis de tema)
│   │   ├── marketplace.css
│   │   └── interface.css     # paleta dos 5 temas de cor + combobox de bandeiras
│   ├── img/
│   │   ├── logo/
│   │   │   ├── logo_onefit.webp
│   │   │   ├── logo_purplefit.webp
│   │   │   ├── logo_bloodfit.webp
│   │   │   ├── logo_skyfit.webp
│   │   │   └── logo_naturefit.webp
│   │   └── flags/
│   │       ├── countries/    # bandeiras de países (SVG, 1 por ISO alpha-2)
│   │       └── states/       # bandeiras dos estados brasileiros (SVG)
│   ├── videos/
│   │   ├── video-cadastro.mp4
│   │   └── video-login.mp4
│   └── js/
│       ├── home.js
│       ├── login.js
│       ├── matricula.js
│       └── interface.js      # combobox acessível de país/estado + troca de logo/nome por tema
├── components/
│   ├── footer.php
│   └── navbar.php
├── config/
│   ├── conn.php           # conexão com o banco de dados
│   ├── parametros.php     # constantes/parâmetros globais (ex: BASE_URL)
│   ├── interface.php      # temas de cor, mapeamento tema→nome/logo, i18n
│   └── localidades.php    # lista de países/estados e validação de nacionalidade
├── pages/
│   ├── dashboard/
│   │   └── dashboard.php
│   ├── carrinho/
│   ├── marketplace/
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

O visual segue uma identidade escura, remetendo a academia de alta performance, com:
- Tipografia impactante (`Big Shoulders Display`) para títulos
- Efeito "shine" dourado em destaques de texto
- Marquee infinito com os equipamentos disponíveis
- Cards de planos com plano "featured" em destaque
- Depoimentos com avatar e citação

A cor de destaque exata (dourado por padrão) é configurável pelo admin — veja a seção **Temas de cor e identidade visual** abaixo.

---

## 🎨 Temas de cor e identidade visual

O dashboard/backoffice (`pages/dashboard`, `pages/carrinho`, `pages/marketplace` etc.) suporta 5 temas de cor, escolhidos pelo admin em **Configurações → Tema de cores do site** e aplicados globalmente para todos os usuários:

| Tema       | Nome da marca | Arquivo da logo          |
|------------|---------------|---------------------------|
| `dourado`  | One Fit       | `logo_onefit.webp`        |
| `roxo`     | Purple Fit    | `logo_purplefit.webp`     |
| `vermelho` | Blood Fit     | `logo_bloodfit.webp`      |
| `azul`     | Sky Fit       | `logo_skyfit.webp`        |
| `verde`    | Nature Fit    | `logo_naturefit.webp`     |

Esse mapeamento (tema → nome + logo) tem **uma única fonte de verdade**: a função `onefitIdentidades()` em `config/interface.php`. Nada mais no PHP ou no JavaScript duplica essa lista — o front-end recebe os mesmos dados via um `<script type="application/json">` emitido por `onefitInterfaceHead()`, lido por `assets/js/interface.js` como `window.OneFit.brands`.

**Como o tema global é selecionado e aplicado:**
1. O admin escolhe um tema no formulário de Configurações (`pages/dashboard/components/section-configuracoes.php`), que envia para `pages/dashboard/actions/interface.php`.
2. O valor é validado contra `onefitTemas()` (as chaves de `onefitIdentidades()`) e gravado em `configuracoes_site.chave = 'tema_cores'`.
3. Em toda página, `onefitCarregarInterface()` (chamada a partir de `config/conn.php`) lê esse valor e define `$GLOBALS['onefitTemaGlobal']` (com fallback para `'dourado'` se a migração/config ainda não existir).
4. As páginas renderizam `<html data-site-theme="<?= $GLOBALS['onefitTemaGlobal'] ?>">` — é esse atributo que o CSS usa para aplicar a paleta certa, sem esperar JavaScript (evita "piscar" o tema errado no carregamento).
5. `onefitLogo()`/`onefitNomeMarca()` (e o JS equivalente, para trocas ao vivo na tela de configurações) sempre derivam da mesma função `onefitMarca()`, com fallback automático para o logo/nome do tema `dourado` caso o arquivo de logo mapeado não exista em disco.

**Como os backgrounds são controlados por variáveis CSS** (arquivo `assets/css/interface.css`):
- Cada tema define só `--theme-hue` (matiz) e as variáveis `--accent*`; todo o resto do fundo/superfícies é calculado a partir de `--theme-hue` com fórmulas `hsl()` compartilhadas — isso é o que garante que um tema novo só precise de uma matiz e 4 cores de destaque para ficar completo, sem duplicar 10+ variáveis por tema.
- Variáveis mínimas que cada tema participa (via a fórmula comum): `--theme-bg` (fundo geral), `--theme-secondary` (fundo secundário), `--theme-sidebar`, `--theme-header`, `--theme-surface` (cards), `--theme-elevated` (modais/dropdowns/menus flutuantes), `--theme-border`, `--theme-text`, `--theme-muted`, `--theme-hover`, `--theme-active`, `--theme-focus`. Aliases como `--bg`, `--surface`, `--bo-surface`, `--sidebar-bg`, `--header-bg` etc. apontam para essas mesmas variáveis — os componentes usam os aliases, nunca cor fixa.
- Existe também um alternador claro/escuro independente (`data-theme="light|dark"`, cookie `onefit_theme`, ver `assets/js/dashboard.js`) — ele redefine as mesmas variáveis com luminosidade alta em vez de baixa, mantendo a matiz do tema escolhido.
- **Regra de ouro ao estilizar um componente novo**: nunca usar hex fixo para fundo/borda/texto de sidebar, header, card, modal, dropdown, tabela, input ou menu flutuante — sempre os aliases `var(--bo-*)`/`var(--theme-*)`. Cores literais só são aceitáveis para estados semânticos que não podem ser confundidos com o destaque do tema (ex.: vermelho de erro/perigo, verde de sucesso, azul informativo do cashback, verde oficial do ícone do WhatsApp) — veja `--bo-danger`, `--bo-success` e `--bo-blue`.

**Como adicionar um novo tema:**
1. Escolha uma matiz (`--theme-hue`, 0–360) e defina um bloco `html[data-site-theme="nome"] { --theme-hue: N; --accent: ...; --accent-bright: ...; --accent-dim: ...; --accent-rgb: ...; --accent-pale: ...; }` em `assets/css/interface.css` (dourado/azul/verde/vermelho/roxo já servem de exemplo). Repita para a variante `[data-theme="light"]` se quiser um claro dedicado.
2. Adicione a entrada em `onefitIdentidades()` (`config/interface.php`) com `name` (nome de marca) e `logo` (nome do arquivo em `assets/img/logo/`).
3. Coloque o arquivo de logo em `assets/img/logo/`. Se o arquivo não existir, o sistema já cai automaticamente no logo/nome do tema `dourado` (nenhuma tela quebra).
4. Adicione um `.of-swatch[data-color="nome"] { background: #suacor; }` em `interface.css` (é só o "bolinha" de prévia no formulário de Configurações).
5. Não é preciso mexer em nenhum outro arquivo — a lista de temas usada no formulário admin, no JSON exposto ao JS e na validação do servidor vêm todas de `onefitTemas()`.

**Como adicionar ou substituir uma logo:** salve o `.webp` em `assets/img/logo/` com o nome exato já mapeado em `onefitIdentidades()` (ou atualize o mapeamento se o nome do arquivo mudar). Não é necessário mexer em nenhuma página — todo `<img data-brand-logo>`/`<a data-brand-logo>` do projeto (navbar, sidebar, header do carrinho/marketplace, telas de login/matrícula/erros) já lê o caminho a partir de `onefitLogo()`.

---

## 🚩 Seletor de país/estado com bandeira

Os campos de nacionalidade (perfil do usuário) e estado (UF, endereço/matrícula) usam um componente próprio (`config/localidades.php` + `assets/js/interface.js` + `assets/css/interface.css`), sem nenhuma dependência externa:

- O PHP renderiza um `<select data-flag-select="country|state">` nativo e completo (funciona sozinho, sem JavaScript, inclusive por teclado).
- Com JavaScript, `assets/js/interface.js` troca visualmente o `<select>` por um combobox acessível (`role="combobox"`/`role="listbox"`, aceita busca por texto, setas, Enter/Esc) que mostra a bandeira como `<img>` real de um SVG local, à esquerda do nome do país/estado por extenso — nunca emoji (que em alguns SO/fontes, como no Windows, pode cair para o texto "BR" em vez do desenho da bandeira) e nunca um serviço externo.
- As bandeiras ficam em `assets/img/flags/countries/{iso}.svg` (249 países, um arquivo por código ISO 3166-1 alpha-2, ex.: `br.svg`) e `assets/img/flags/states/{uf}.svg` (27 UFs). Se uma imagem falhar ao carregar em algum ambiente (asset ausente/bloqueado), o combobox mostra um selo estilizado com o código (`.of-flag-fallback`) em vez de texto solto sem estilo.
- O valor enviado/gravado no formulário continua sendo sempre o `<select>` nativo (o combobox só decora visualmente) — o backend recebe o código ISO normalmente, e um país já salvo (inclusive valores legados em texto livre, ex. "brasileira") continua sendo reconhecido e preselecionado (`onefitPaisLegado()`).
- Testado em Chrome desktop e mobile; a base do `<select>` nativo garante funcionamento em qualquer navegador mesmo sem JS.

**Limitações conhecidas:**
- `tests/interface.php` inclui uma checagem de hash SHA-256 das bandeiras de estado contra `assets/img/flags/states/sources.json`; no ambiente atual, 7 UFs (`AC, AL, MG, PA, SC, SP, TO`) têm hash diferente do manifesto (o SVG em disco é válido e renderiza normalmente — é uma divergência de proveniência/manifesto, não um problema visual). Vale revisar/atualizar o manifesto separadamente.
- O combobox de país/estado é JS puro, sem biblioteca (Select2/Choices/TomSelect) — qualquer melhoria futura deve manter esse padrão em vez de introduzir uma dependência nova.

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