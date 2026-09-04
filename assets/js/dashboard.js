/* =========================================================================
   backoffice.js
   Toda a interatividade do painel: troca de perfil (admin/profissional/
   aluno), montagem dinâmica do menu lateral, abertura do modal de
   formulário genérico (cadastro/edição), filtros de tabela, cálculo de
   IMC, simulação de pagamento (Pix/cartão) e exportação de tabela em CSV.

   Depende de duas variáveis globais definidas ANTES deste arquivo, no
   próprio dashboard.php (porque vêm de dados do PHP):
     - BO_CATEGORIAS_OPTIONS  (nomes das categorias de produto)
     - BO_PLANOS_OPTIONS      (nomes dos planos cadastrados)
   ========================================================================= */

/* ---------- Perfis de acesso ----------
   Cada perfil define o rótulo mostrado no header e os itens do menu
   lateral (chave da seção + label + ícone Bootstrap Icons). A seção
   correspondente já existe como <section data-perfil="..." data-section="...">
   no HTML (ver components/section-*.php); se não existir, cai no
   fallback "Em construção" (ver boGoToSection). */
const BO_PERFIS = {
    admin: {
        label: 'Administrador',
        menus: [
            { key: 'dashboard', label: 'Visão Geral', icon: 'bi-speedometer2' },
            { key: 'usuarios', label: 'Usuários', icon: 'bi-people' },
            { key: 'permissoes', label: 'Permissões', icon: 'bi-shield-lock' },
            { key: 'funcoes', label: 'Funções', icon: 'bi-diagram-3' },
            { key: 'pagamentos', label: 'Pagamentos', icon: 'bi-credit-card' },
            { key: 'cashbacks', label: 'Cashbacks', icon: 'bi-wallet2' },
            { key: 'categorias', label: 'Categorias', icon: 'bi-tags' },
            { key: 'produtos', label: 'Produtos', icon: 'bi-box-seam' },
            { key: 'vendas', label: 'Vendas Marketplace', icon: 'bi-truck' },
            { key: 'planos', label: 'Cadastro de Planos', icon: 'bi-clipboard-check' },
            { key: 'profissionais', label: 'Profissionais', icon: 'bi-person-badge' },
            { key: 'modalidades', label: 'Modalidades', icon: 'bi-activity' },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    vendedor: {
        label: 'Vendedor',
        menus: [
            { key: 'vendas', label: 'Vendas Marketplace', icon: 'bi-truck' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    profissional: {
        label: 'Profissional',
        menus: [
            { key: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
            { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
            { key: 'alunos', label: 'Alunos', icon: 'bi-people' },
            { key: 'agenda', label: 'Agenda', icon: 'bi-calendar3' },
            { key: 'cashback', label: 'Meu cashback', icon: 'bi-wallet2' },
            { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
    aluno: {
        label: 'Aluno',
        menus: [
            { key: 'perfil', label: 'Perfil', icon: 'bi-person-circle' },
            { key: 'historico', label: 'Histórico', icon: 'bi-clock-history' },
            { key: 'cashback', label: 'Cashback', icon: 'bi-wallet2' },
            { key: 'compras', label: 'Minhas compras', icon: 'bi-bag-check' },
            { key: 'treino', label: 'Treino', icon: 'bi-lightning-charge' },
            { key: 'agenda', label: 'Minha agenda', icon: 'bi-calendar3' },
            { key: 'marketplace', label: 'Marketplace', icon: 'bi-shop', href: BO_MARKETPLACE_URL },
            { key: 'configuracoes', label: 'Configurações', icon: 'bi-gear' },
        ],
    },
};

// Estado atual da tela: qual perfil está sendo visualizado e qual seção do menu.
// boPerfilAtual começa no perfil REAL do usuário logado (BO_PERFIL_LOGADO,
// definido no <script> inline do dashboard.php a partir da sessão/tipo_usuario)
// — só o admin pode trocar isso depois, pelo dropdown do header.
let boPerfilAtual = (typeof BO_PERFIL_LOGADO !== 'undefined') ? BO_PERFIL_LOGADO : 'aluno';
let boSectionAtual = null; // definida no DOMContentLoaded, com base no 1º item do menu do perfil
let boFormModalInstance = null; // instância do Modal do Bootstrap (definida no DOMContentLoaded)

// Converte todos os códigos ISO 3166-1 em nomes de países no idioma do painel.
// O nome selecionado é armazenado como nacionalidade no perfil do usuário.
const BO_COUNTRY_CODES = `AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW`.split(' ');
const boRegionNames = typeof Intl.DisplayNames === 'function'
    ? new Intl.DisplayNames(['pt-BR'], { type: 'region' })
    : null;
const BO_NATIONALITY_OPTIONS = BO_COUNTRY_CODES
    .map((code) => boRegionNames ? boRegionNames.of(code) : code)
    .sort((a, b) => a.localeCompare(b, 'pt-BR'));

/* ---------- Esquemas do modal de formulário genérico ----------
   Cada chave corresponde ao 1º argumento passado em boOpenForm(schemaKey, ...)
   nos botões "onclick" do HTML. A lista de campos aqui é usada por
   boBuildField() para montar o formulário dinamicamente dentro do modal
   #boFormModal, sem precisar de um modal HTML diferente para cada tela. */
const BO_FORM_SCHEMAS = {
    alunoDoProfissionalForm: [
        { key: 'nome', label: 'Nome', type: 'text', col: 12 },
        { key: 'contato', label: 'Contato', type: 'text', col: 6 },
        { key: 'plano', label: 'Plano', type: 'text', col: 6 },
        { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
        { key: 'valor', label: 'Valor', type: 'number', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    agendaDisponivel: [
        { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
        { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
    ],
    agendaAgendar: [
        { key: 'aluno', label: 'Aluno', type: 'text', col: 12 },
        { key: 'data', label: 'Data/hora', type: 'text', placeholder: 'dd/mm/aaaa hh:mm', col: 6 },
        { key: 'modalidade', label: 'Modalidade', type: 'text', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    utilizarCashback: [
        { key: 'valor', label: 'Valor a utilizar', type: 'number', col: 12 },
    ],
    planoAlterar: [
        { key: 'plano', label: 'Novo plano', type: 'select', options: BO_PLANOS_OPTIONS, col: 12 },
    ],
    perfilEdit: [
        { key: 'nome', label: 'Nome', type: 'text', col: 6, required: true },
        { key: 'documento', label: 'Documento', type: 'text', col: 6, required: true },
        { key: 'email', label: 'E-mail', type: 'email', col: 6, required: true },
        { key: 'telefone', label: 'Telefone', type: 'text', col: 6, required: true },
        { key: 'nacionalidade', label: 'Nacionalidade', type: 'select', options: BO_NATIONALITY_OPTIONS, col: 6, required: true },
        { key: 'nascimento', label: 'Data de nascimento', type: 'date', col: 6, required: true },
        { key: 'genero', label: 'Gênero', type: 'select', options: ['masculino', 'feminino', 'outro'], optionLabels: ['Masculino', 'Feminino', 'Outro'], col: 6, required: true },
        { key: 'endereco', label: 'Endereço', type: 'text', col: 12, required: true },
        { key: 'cidade', label: 'Cidade', type: 'text', col: 6, required: true },
        { key: 'estado', label: 'Estado (UF)', type: 'text', col: 6, required: true },
        { key: 'altura', label: 'Altura (m)', type: 'number', col: 6, min: 0.5, max: 3, step: 0.01 },
        { key: 'peso', label: 'Peso (kg)', type: 'number', col: 6, min: 1, max: 500, step: 0.1 },
        { key: 'foto', label: 'URL da foto', type: 'url', col: 12 },
    ],
    treinoExercicio: [
        { key: 'nome', label: 'Exercício', type: 'text', col: 12 },
        { key: 'series', label: 'Séries', type: 'number', col: 4 },
        { key: 'repeticoes', label: 'Repetições', type: 'number', col: 4 },
        { key: 'carga', label: 'Carga (kg)', type: 'number', col: 4 },
    ],
};

/**
 * Cria o elemento de UM campo do formulário (label + input/select/textarea/
 * checklist/upload de imagem), de acordo com o "type" definido no schema
 * acima. É chamada uma vez por campo dentro de boOpenForm().
 */
function boBuildField(field) {
    const wrap = document.createElement('div');
    wrap.className = 'col-' + (field.col || 12);

    const label = document.createElement('label');
    label.className = 'form-label';
    label.textContent = field.label;
    wrap.appendChild(label);

    if (field.type === 'select') {
        const select = document.createElement('select');
        select.className = 'form-select';
        select.setAttribute('data-bo-field', field.key);
        field.options.forEach((opt, i) => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = (field.optionLabels && field.optionLabels[i]) || opt;
            select.appendChild(o);
        });
        wrap.appendChild(select);
    } else if (field.type === 'textarea') {
        const ta = document.createElement('textarea');
        ta.className = 'form-control';
        ta.rows = 3;
        ta.setAttribute('data-bo-field', field.key);
        wrap.appendChild(ta);
    } else if (field.type === 'checklist') {
        // Grupo de checkboxes (ex: permissões de uma função)
        const box = document.createElement('div');
        box.className = 'd-flex flex-wrap gap-3';
        field.options.forEach((opt) => {
            const id = 'chk_' + field.key + '_' + opt.replace(/\s+/g, '');
            const chkWrap = document.createElement('div');
            chkWrap.className = 'form-check';
            chkWrap.innerHTML = `<input class="form-check-input" type="checkbox" id="${id}" value="${opt}" data-bo-checklist="${field.key}"><label class="form-check-label" for="${id}">${opt}</label>`;
            box.appendChild(chkWrap);
        });
        wrap.appendChild(box);
    } else if (field.type === 'image') {
        // Campo de imagem: aceita tanto uma URL digitada quanto upload de
        // arquivo local (convertido para base64 e mostrado na pré-visualização)
        const url = document.createElement('input');
        url.type = 'text';
        url.className = 'form-control mb-2';
        url.placeholder = 'URL da imagem';
        url.setAttribute('data-bo-field', field.key);
        wrap.appendChild(url);

        const file = document.createElement('input');
        file.type = 'file';
        file.accept = 'image/*';
        file.className = 'form-control mb-2';
        wrap.appendChild(file);

        const preview = document.createElement('img');
        preview.setAttribute('data-bo-preview', field.key);
        wrap.appendChild(preview);

        url.addEventListener('input', () => {
            if (url.value) {
                preview.src = url.value;
                preview.style.display = 'block';
            }
        });
        file.addEventListener('change', () => {
            const f = file.files[0];
            if (f) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    url.value = ''; // upload tem prioridade sobre a URL digitada
                };
                reader.readAsDataURL(f);
            }
        });
    } else {
        // text / email / date / number (padrão)
        const input = document.createElement('input');
        input.type = field.type;
        input.className = 'form-control';
        input.setAttribute('data-bo-field', field.key);
        if (field.placeholder) input.placeholder = field.placeholder;
        if (field.readonly) input.readOnly = true;
        wrap.appendChild(input);
    }

    const control = wrap.querySelector(`[data-bo-field="${field.key}"]`);
    if (control) {
        if (field.required) control.required = true;
        if (field.min !== undefined) control.min = field.min;
        if (field.max !== undefined) control.max = field.max;
        if (field.step !== undefined) control.step = field.step;
    }

    return wrap;
}

/**
 * Abre o modal genérico de formulário (#boFormModal), monta os campos
 * do schema indicado, preenche com "values" (quando for edição) e
 * prepara o botão "Salvar". Chamada pelos botões "Novo X" / "Editar" no HTML.
 *
 * @param {string} schemaKey  chave em BO_FORM_SCHEMAS (ex: 'produtoForm')
 * @param {string} title      título mostrado no cabeçalho do modal
 * @param {object} values     valores já existentes (edição) ou {} (novo)
 * @param {object} options    { doubleConfirm: true } exige clicar 2x em
 *                             "Salvar" antes de confirmar (usado em ações
 *                             sensíveis, ex: permissões)
 */
function boOpenForm(schemaKey, title, values, options) {
    values = values || {};
    options = options || {};

    const form = document.getElementById('boFormModalForm');
    form.innerHTML = '';
    document.getElementById('boFormModalTitle').textContent = title;

    const fields = BO_FORM_SCHEMAS[schemaKey] || [];
    fields.forEach((field) => form.appendChild(boBuildField(field)));

    // Preenche os campos recém-criados com os valores atuais do registro
    fields.forEach((field) => {
        if (field.type === 'checklist') {
            const selected = (values[field.key] || '').split(',').map((s) => s.trim());
            form.querySelectorAll(`[data-bo-checklist="${field.key}"]`).forEach((chk) => {
                chk.checked = selected.includes(chk.value);
            });
            return;
        }
        const el = form.querySelector(`[data-bo-field="${field.key}"]`);
        if (el && values[field.key] !== undefined) {
            // Preserva valores antigos que ainda não façam parte da lista atual.
            if (field.type === 'select' && values[field.key] && !Array.from(el.options).some((option) => option.value === values[field.key])) {
                el.add(new Option(values[field.key], values[field.key]));
            }
            el.value = values[field.key];
        }
        if (field.type === 'image' && values[field.key]) {
            const preview = form.querySelector(`[data-bo-preview="${field.key}"]`);
            if (preview) {
                preview.src = values[field.key];
                preview.style.display = 'block';
            }
        }
    });

    // Recria o botão "Salvar" a cada abertura para não acumular listeners antigos
    const oldSaveBtn = document.getElementById('boFormModalSave');
    const saveBtn = oldSaveBtn.cloneNode(true);
    oldSaveBtn.parentNode.replaceChild(saveBtn, oldSaveBtn);
    saveBtn.textContent = 'Salvar';

    let confirmStep = 0;
    saveBtn.addEventListener('click', async () => {
        if (options.doubleConfirm && confirmStep === 0) {
            confirmStep = 1;
            saveBtn.textContent = 'Clique novamente para confirmar';
            return;
        }

        if (!form.reportValidity()) return;

        if (schemaKey === 'perfilEdit') {
            const profileValues = {};
            fields.forEach((field) => {
                const input = form.querySelector(`[data-bo-field="${field.key}"]`);
                profileValues[field.key] = input ? input.value.trim() : '';
            });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';

            try {
                const response = await fetch(BO_PROFILE_UPDATE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...profileValues, csrf_token: BO_CSRF_TOKEN }),
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Não foi possível atualizar o perfil.');

                Object.assign(BO_CURRENT_USER, profileValues);
                document.getElementById('boProfileName').textContent = profileValues.nome;
                document.getElementById('boProfileEmail').textContent = profileValues.email;
                const gender = document.getElementById('boProfileGender');
                if (gender) gender.textContent = `Gênero: ${profileValues.genero.charAt(0).toUpperCase()}${profileValues.genero.slice(1)}`;
                document.getElementById('boAvatar').textContent = profileValues.nome.charAt(0).toUpperCase();

                boFormModalInstance.hide();
                boToast(result.message);
            } catch (error) {
                boToast(error.message);
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar';
            }
            return;
        }

        boFormModalInstance.hide();
        boToast('Alterações salvas.');
    });

    boFormModalInstance.show();
}

/**
 * Mostra um aviso flutuante (toast) no canto inferior direito por ~2,5s.
 */
function boToast(msg) {
    const toast = document.getElementById('boToast');
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(window._boToastTimer);
    window._boToastTimer = setTimeout(() => toast.classList.remove('show'), 2500);
}

/**
 * Reconstrói os itens do menu lateral (#boNav) de acordo com o perfil
 * ativo (boPerfilAtual), marcando o item da seção atual como "active".
 */
function boRenderSidebar() {
    const nav = document.getElementById('boNav');
    nav.innerHTML = '';
    BO_PERFIS[boPerfilAtual].menus.forEach((item) => {
        const itemElement = document.createElement(item.href ? 'a' : 'button');
        if (item.href) {
            itemElement.href = item.href;
        } else {
            itemElement.type = 'button';
            itemElement.addEventListener('click', () => boGoToSection(item.key));
        }
        itemElement.className = 'bo-nav-item' + (item.key === boSectionAtual ? ' active' : '');
        itemElement.setAttribute('data-section', item.key);
        itemElement.innerHTML = `<i class="bi ${item.icon}"></i><span>${item.label}</span>`;
        nav.appendChild(itemElement);
    });
}

/**
 * Reconstrói o dropdown "Administrador / Profissional / Aluno" do header,
 * marcando o perfil ativo. (Esse seletor existe só para navegar entre as
 * 3 visões durante o desenvolvimento/testes do backoffice.)
 */
function boRenderPerfilMenu() {
    // Só o admin vê/usa o seletor de perfil (ver header.php) — pra qualquer
    // outro perfil, #boPerfilMenu existe só como placeholder vazio (.d-none).
    if (!BO_IS_ADMIN) return;

    const menu = document.getElementById('boPerfilMenu');
    menu.innerHTML = '';
    Object.keys(BO_PERFIS).forEach((key) => {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'dropdown-item' + (key === boPerfilAtual ? ' active' : '');
        link.setAttribute('data-perfil', key);
        link.textContent = BO_PERFIS[key].label;
        li.appendChild(link);
        menu.appendChild(li);
    });
}

/* ---------- Perfil e busca global ---------- */
const BO_SEARCH_ALIASES = {
    dashboard: ['início', 'inicio', 'visão geral', 'resumo'],
    perfil: ['meu perfil', 'conta', 'dados cadastrais', 'editar perfil'],
    historico: ['histórico', 'historico', 'pagamentos', 'movimentações', 'movimentacoes'],
    cashback: ['saldo', 'benefícios', 'beneficios'],
    compras: ['minhas compras', 'pedidos', 'compras', 'histórico de compras'],
    treino: ['treinos', 'exercícios', 'exercicios', 'ficha'],
    agenda: ['agenda', 'agendamentos', 'horários', 'horarios'],
    configuracoes: ['configurações', 'configuracoes', 'ajustes', 'tema', 'conta'],
    profissionais: ['profissionais', 'equipe', 'personal trainer', 'nutricionista'],
    usuarios: ['usuários', 'usuarios', 'alunos'],
    planos: ['planos', 'assinaturas'],
    pagamentos: ['pagamentos', 'financeiro'],
};

let boSearchItems = [];
let boSearchActiveIndex = -1;
let boSearchDebounceTimer = null;

function boNormalizeSearch(value) {
    return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function boCloseSearch() {
    const results = document.getElementById('boSearchResults');
    const input = document.getElementById('boHeaderSearch');
    if (!results || !input) return;
    results.hidden = true;
    results.innerHTML = '';
    input.setAttribute('aria-expanded', 'false');
    clearTimeout(boSearchDebounceTimer);
    boSearchItems = [];
    boSearchActiveIndex = -1;
}

function boGetSearchPages() {
    const pages = BO_PERFIS[boPerfilAtual].menus.map((item) => ({
        type: 'page',
        key: item.key,
        href: item.href,
        title: item.label,
        subtitle: item.href ? 'Abrir Marketplace' : 'Página do painel',
        icon: item.icon,
        terms: [item.label, ...(BO_SEARCH_ALIASES[item.key] || [])],
    }));

    if (!pages.some((page) => page.key === 'perfil')) {
        pages.unshift({
            type: 'page', key: 'perfil', title: 'Meu perfil', subtitle: 'Dados da sua conta', icon: 'bi-person-circle',
            terms: ['perfil', ...(BO_SEARCH_ALIASES.perfil || [])],
        });
    }

    return pages;
}

function boBuildSearchResult(result, index) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'bo-search-result' + (index === boSearchActiveIndex ? ' is-active' : '');
    button.setAttribute('role', 'option');
    button.setAttribute('aria-selected', index === boSearchActiveIndex ? 'true' : 'false');
    button.innerHTML = `<i class="bi ${result.icon}"></i><span><strong></strong><small></small></span>`;
    button.querySelector('strong').textContent = result.title;
    button.querySelector('small').textContent = result.subtitle;
    button.addEventListener('click', () => boOpenSearchResult(result));
    return button;
}

function boRenderSearch(query) {
    const resultsBox = document.getElementById('boSearchResults');
    const input = document.getElementById('boHeaderSearch');
    if (!resultsBox || !input) return;

    const term = boNormalizeSearch(query);
    if (!term) {
        boCloseSearch();
        return;
    }

    const pages = boGetSearchPages().filter((page) => boNormalizeSearch(page.terms.join(' ')).includes(term));
    const professionals = (typeof BO_PROFISSIONAIS_SEARCH !== 'undefined' ? BO_PROFISSIONAIS_SEARCH : [])
        .filter((professional) => boNormalizeSearch(`${professional.nome} ${professional.funcao} ${professional.especialidade}`).includes(term))
        .map((professional) => ({
            type: 'professional', id: professional.id, title: professional.nome,
            subtitle: professional.especialidade || professional.funcao, icon: 'bi-person-badge',
        }));

    boSearchItems = [...professionals, ...pages];
    boSearchActiveIndex = boSearchItems.length ? 0 : -1;
    resultsBox.innerHTML = '';

    const appendGroup = (label, group, offset) => {
        if (!group.length) return;
        const heading = document.createElement('span');
        heading.className = 'bo-search-group-label';
        heading.textContent = label;
        resultsBox.appendChild(heading);
        group.forEach((item, index) => resultsBox.appendChild(boBuildSearchResult(item, offset + index)));
    };

    if (boSearchItems.length) {
        appendGroup('Profissionais', professionals, 0);
        appendGroup('Páginas', pages, professionals.length);
    } else {
        const empty = document.createElement('span');
        empty.className = 'bo-search-empty';
        empty.textContent = 'Nenhum resultado encontrado';
        resultsBox.appendChild(empty);
    }

    resultsBox.hidden = false;
    input.setAttribute('aria-expanded', 'true');
}

function boOpenSearchResult(result) {
    if (result.href) {
        window.location.assign(result.href);
    } else if (result.type === 'professional') {
        boShowProfessional(result.id);
    } else {
        boGoToSection(result.key);
    }
    document.getElementById('boHeaderSearch').value = '';
    boCloseSearch();
}

function boOpenProfileEdit() {
    boOpenForm('perfilEdit', 'Editar perfil', typeof BO_CURRENT_USER !== 'undefined' ? BO_CURRENT_USER : {});
}

function boShowProfessional(id, updateRoute = true) {
    const professional = (typeof BO_PROFISSIONAIS_SEARCH !== 'undefined' ? BO_PROFISSIONAIS_SEARCH : [])
        .find((item) => Number(item.id) === Number(id));
    if (!professional) {
        boGoToSection(BO_PERFIS[boPerfilAtual].menus[0].key, false);
        return false;
    }

    let section = document.getElementById('boProfessionalProfileSection');
    if (!section) {
        section = document.createElement('section');
        section.id = 'boProfessionalProfileSection';
        section.className = 'bo-content-section';
        document.querySelector('.bo-main').appendChild(section);
    }
    section.innerHTML = '<div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-badge"></i> Profissional</span><h1></h1><p></p></div></div><div class="bo-settings-card bo-profile-settings"><div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person-workspace"></i></span><div><h2></h2><p></p></div></div></div>';
    section.querySelector('.bo-page-title h1').textContent = professional.nome;
    section.querySelector('.bo-page-title p').textContent = 'Perfil profissional disponível no painel ONE FIT.';
    section.querySelector('.bo-settings-heading h2').textContent = professional.funcao;
    section.querySelector('.bo-settings-heading p').textContent = professional.especialidade || professional.funcao;

    document.querySelectorAll('.bo-content-section').forEach((item) => item.classList.remove('active'));
    section.classList.add('active');
    boSectionAtual = 'profissional';
    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => btn.classList.remove('active'));
    document.getElementById('boSidebar').classList.remove('active');
    document.getElementById('boSidebarBackdrop').classList.remove('active');

    if (updateRoute) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', 'profissional');
        url.searchParams.set('profissional', professional.id);
        window.history.pushState({}, '', url);
    }
    return true;
}

/**
 * Troca a seção visível dentro do perfil atual. Procura uma
 * <section data-perfil="X" data-section="Y"> já pronta no HTML; se não
 * existir, mostra a seção de fallback "Em construção" com o título certo.
 */
function boGoToSection(sectionKey, updateRoute = true) {
    const isSpecialSection = sectionKey === 'configuracoes' || sectionKey === 'perfil';
    if (!isSpecialSection && !BO_PERFIS[boPerfilAtual].menus.some((item) => item.key === sectionKey)) return;
    boSectionAtual = sectionKey;

    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => {
        btn.classList.toggle('active', btn.getAttribute('data-section') === sectionKey);
    });

    const prebuilt = sectionKey === 'configuracoes'
        ? document.getElementById('boSettingsSection')
        : sectionKey === 'perfil'
            ? document.getElementById('boProfileSection')
            : document.querySelector(`.bo-content-section[data-perfil="${boPerfilAtual}"][data-section="${sectionKey}"]`);

    document.querySelectorAll('.bo-content-section').forEach((section) => section.classList.remove('active'));

    if (prebuilt) {
        prebuilt.classList.add('active');
    } else {
        const item = BO_PERFIS[boPerfilAtual].menus.find((m) => m.key === sectionKey);
        document.getElementById('boStubTitle').textContent = item ? item.label : '';
        document.getElementById('boStubDesc').textContent = 'Esta tela ainda será detalhada para o perfil ' + BO_PERFIS[boPerfilAtual].label + '.';
        document.getElementById('boStubIcon').className = 'bi ' + (item ? item.icon : 'bi-hourglass-split');
        document.getElementById('boStubSection').classList.add('active');
    }

    // Fecha a sidebar mobile ao navegar (não faz nada se já estiver fechada/desktop)
    document.getElementById('boSidebar').classList.remove('active');
    document.getElementById('boSidebarBackdrop').classList.remove('active');

    if (updateRoute) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', sectionKey);
        url.searchParams.delete('profissional');
        window.history.pushState({}, '', url);
    }
}

/**
 * Troca o perfil ativo (admin/profissional/aluno), atualiza header,
 * remonta o menu lateral e abre a primeira seção do novo perfil.
 */
function boTrocarPerfil(perfilKey) {
    // Segunda camada de proteção: mesmo que alguém force a chamada dessa
    // função pelo console do navegador, só o admin consegue trocar de perfil.
    // A proteção "de verdade" é o servidor só mandar o HTML das seções que
    // o tipo_usuario da sessão tem direito a ver (ver dashboard.php).
    if (!BO_IS_ADMIN) return;
    if (!BO_PERFIS[perfilKey] || perfilKey === boPerfilAtual) return;

    boPerfilAtual = perfilKey;
    document.getElementById('boPerfilLabel').textContent = BO_PERFIS[perfilKey].label;
    document.getElementById('boAvatar').textContent = BO_PERFIS[perfilKey].label.charAt(0);

    boRenderSidebar();
    boRenderPerfilMenu();
    boGoToSection(BO_PERFIS[perfilKey].menus[0].key);
}

/* ---------- Inicialização geral (menu, sidebar mobile, filtros, ações de tabela) ---------- */
document.addEventListener('DOMContentLoaded', () => {
    boFormModalInstance = new bootstrap.Modal(document.getElementById('boFormModal'));

    boRenderSidebar();
    boRenderPerfilMenu();
    // A primeira seção depende do perfil: admin/profissional começam em
    // "dashboard", mas o Aluno não tem essa chave — o dele é "perfil".
    // Por isso pegamos sempre o primeiro item do MENU DO PERFIL ATUAL,
    // em vez de um valor fixo.
    const routeParams = new URLSearchParams(window.location.search);
    const routeSection = routeParams.get('section');
    const routeProfessional = routeParams.get('profissional');
    if (routeSection === 'profissional' && routeProfessional) {
        boShowProfessional(routeProfessional, false);
    } else {
        const initialSection = (routeSection === 'perfil' || routeSection === 'configuracoes' || BO_PERFIS[boPerfilAtual].menus.some((item) => item.key === routeSection))
            ? routeSection
            : BO_PERFIS[boPerfilAtual].menus[0].key;
        boGoToSection(initialSection, false);
    }

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('onefit-theme', theme); } catch (e) { /* armazenamento indisponível */ }
        try { document.cookie = 'onefit_theme=' + theme + '; path=/; max-age=31536000'; } catch (e) { /* cookie indisponível */ }
        document.querySelectorAll('[data-bo-theme]').forEach((button) => {
            button.classList.toggle('active', button.getAttribute('data-bo-theme') === theme);
        });
    };

    let savedTheme = 'dark';
    try { savedTheme = localStorage.getItem('onefit-theme') || 'dark'; } catch (e) { /* usa o padrão escuro */ }
    applyTheme(savedTheme === 'light' ? 'light' : 'dark');
    document.querySelectorAll('[data-bo-theme]').forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.getAttribute('data-bo-theme')));
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            document.getElementById('boHeaderSearch')?.focus();
        }
    });

    const searchInput = document.getElementById('boHeaderSearch');
    const searchWrap = document.getElementById('boHeaderSearchWrap');
    searchInput.addEventListener('input', () => {
        clearTimeout(boSearchDebounceTimer);
        boSearchDebounceTimer = setTimeout(() => boRenderSearch(searchInput.value), 180);
    });
    searchInput.addEventListener('search', () => {
        if (!searchInput.value) boCloseSearch();
    });
    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            boCloseSearch();
            searchInput.blur();
            return;
        }
        if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key) || !boSearchItems.length) return;
        event.preventDefault();
        if (event.key === 'Enter') {
            boOpenSearchResult(boSearchItems[boSearchActiveIndex < 0 ? 0 : boSearchActiveIndex]);
            return;
        }
        const nextIndex = event.key === 'ArrowDown'
            ? (boSearchActiveIndex + 1) % boSearchItems.length
            : (boSearchActiveIndex - 1 + boSearchItems.length) % boSearchItems.length;
        boRenderSearch(searchInput.value);
        // boRenderSearch seleciona o primeiro resultado por padrão; restaura
        // a seleção escolhida pelo teclado para manter a navegação previsível.
        boSearchActiveIndex = nextIndex;
        document.querySelectorAll('.bo-search-result').forEach((button, index) => {
            const active = index === boSearchActiveIndex;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    });

    const avatar = document.getElementById('boAvatar');
    const userMenu = document.getElementById('boUserMenu');
    const userMenuWrap = document.getElementById('boUserMenuWrap');
    const closeUserMenu = () => {
        userMenu.classList.remove('is-open');
        userMenu.setAttribute('aria-hidden', 'true');
        avatar.setAttribute('aria-expanded', 'false');
    };
    avatar.addEventListener('click', () => {
        const willOpen = !userMenu.classList.contains('is-open');
        userMenu.classList.toggle('is-open', willOpen);
        userMenu.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
        avatar.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (!searchWrap.contains(event.target)) boCloseSearch();
        if (!userMenuWrap.contains(event.target)) closeUserMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeUserMenu();
    });

    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('section') === 'profissional' && params.get('profissional')) {
            boShowProfessional(params.get('profissional'), false);
        } else {
            boGoToSection(params.get('section') || BO_PERFIS[boPerfilAtual].menus[0].key, false);
        }
    });

    // Clique num item do dropdown de perfil (header) -> troca de perfil.
    // #boPerfilMenu só existe quando o seletor de perfil está no header
    // (hoje BO_IS_ADMIN é sempre false, então o elemento nem é renderizado).
    const perfilMenuEl = document.getElementById('boPerfilMenu');
    if (perfilMenuEl) {
        perfilMenuEl.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-perfil]');
            if (!link) return;
            event.preventDefault();
            boTrocarPerfil(link.getAttribute('data-perfil'));
        });
    }

    // Botão hambúrguer (mobile) abre/fecha a sidebar; clicar fora também fecha
    const sidebar = document.getElementById('boSidebar');
    const backdrop = document.getElementById('boSidebarBackdrop');
    const toggle = document.getElementById('boSidebarToggle');

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    });
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('active');
        backdrop.classList.remove('active');
    });

    /* ---- Filtros de tabela ----
       Para cada <table data-bo-table="X">, procura os inputs/selects com
       data-bo-target="X" (busca, status, tipo, data-de, data-até) e
       esconde/mostra as linhas conforme os atributos data-* de cada <tr>
       (data-search, data-status, data-type, data-date) definidos no PHP. */
    document.querySelectorAll('[data-bo-table]').forEach((table) => {
        const filterId = table.getAttribute('data-bo-table');
        const searchInput = document.querySelector(`[data-bo-filter="search"][data-bo-target="${filterId}"]`);
        const statusSelect = document.querySelector(`[data-bo-filter="status"][data-bo-target="${filterId}"]`);
        const typeSelect = document.querySelector(`[data-bo-filter="type"][data-bo-target="${filterId}"]`);
        const dateFrom = document.querySelector(`[data-bo-filter="date-from"][data-bo-target="${filterId}"]`);
        const dateTo = document.querySelector(`[data-bo-filter="date-to"][data-bo-target="${filterId}"]`);
        const emptyRow = table.querySelector('.bo-empty-row');

        const applyFilters = () => {
            const term = (searchInput ? searchInput.value : '').trim().toLowerCase();
            const status = statusSelect ? statusSelect.value : '';
            const type = typeSelect ? typeSelect.value : '';
            const from = dateFrom ? dateFrom.value : '';
            const to = dateTo ? dateTo.value : '';
            let visibleCount = 0;

            table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => {
                const haystack = row.getAttribute('data-search') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const rowType = row.getAttribute('data-type') || '';
                const rowDate = row.getAttribute('data-date') || '';

                const matchesTerm = term === '' || haystack.toLowerCase().includes(term);
                const matchesStatus = status === '' || rowStatus === status;
                const matchesType = type === '' || rowType === type;
                const matchesFrom = from === '' || rowDate === '' || rowDate >= from;
                const matchesTo = to === '' || rowDate === '' || rowDate <= to;

                const visible = matchesTerm && matchesStatus && matchesType && matchesFrom && matchesTo;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount += 1;
            });

            if (emptyRow) emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        };

        [searchInput, statusSelect, typeSelect, dateFrom, dateTo].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });
    });

    /* ---- Ações de tabela (delegadas no <body> pois as linhas são dinâmicas) ---- */
    document.body.addEventListener('click', (event) => {

        // Pausar/Ativar — usado só pelas telas sem persistência real (ex:
        // agenda do profissional). No admin isso agora é um <form> em PHP
        // (ver includes/admin-forms.php), sem passar por aqui.
        const toggleBtn = event.target.closest('[data-bo-action="toggle-status"]');
        if (toggleBtn) {
            const row = toggleBtn.closest('tr');
            const badge = row.querySelector('.bo-badge');
            const active = badge.classList.contains('bo-badge-active');
            const onLabel = toggleBtn.getAttribute('data-on') || 'Ativo';
            const offLabel = toggleBtn.getAttribute('data-off') || 'Inativo';

            badge.classList.toggle('bo-badge-active', !active);
            badge.classList.toggle('bo-badge-inactive', active);
            badge.textContent = active ? offLabel : onLabel;
            row.setAttribute('data-status', active ? 'inativo' : 'ativo');
            toggleBtn.innerHTML = `<i class="bi ${active ? 'bi-play-circle' : 'bi-pause-circle'}"></i>`;
            toggleBtn.title = active ? 'Ativar' : 'Pausar/Inativar';
        }

        // Excluir — usado só pelas telas sem persistência real (ex: ficha de
        // treino do aluno). No admin isso agora é um modal Bootstrap centralizado
        // (ver bo_botao_excluir/bo_modal_confirmar_exclusao em includes/admin-forms.php).
        const deleteBtn = event.target.closest('[data-bo-action="delete"]');
        if (deleteBtn) {
            const label = deleteBtn.getAttribute('data-bo-name') || 'este registro';
            if (window.confirm(`Tem certeza que deseja excluir ${label}?`)) {
                deleteBtn.closest('tr').remove();
                boToast('Registro excluído.');
            }
        }

        // Limpar tabela inteira (ex: "Limpar Treino")
        const clearBtn = event.target.closest('[data-bo-action="clear-table"]');
        if (clearBtn) {
            const tableSel = clearBtn.getAttribute('data-bo-target-table');
            const table = document.querySelector(`[data-bo-table="${tableSel}"]`);
            if (table && window.confirm('Tem certeza que deseja limpar todos os itens?')) {
                table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((row) => row.remove());
                const emptyRow = table.querySelector('.bo-empty-row');
                if (emptyRow) emptyRow.style.display = '';
                boToast('Lista limpa.');
            }
        }

        // Remover card de agenda (horário agendado ou disponível)
        const removeCardBtn = event.target.closest('[data-bo-remove-card]');
        if (removeCardBtn) {
            if (window.confirm('Remover este horário?')) {
                removeCardBtn.closest('.bo-agenda-card').remove();
            }
        }

        // Exportar tabela visível para CSV
        const exportBtn = event.target.closest('[data-bo-export]');
        if (exportBtn) {
            boExportTableCsv(exportBtn.getAttribute('data-bo-export'));
        }
    });
});

/* ---------- Cálculo de IMC (tela "Perfil" do aluno) ---------- */
function boCalcularIMC() {
    const altura = parseFloat(document.getElementById('imcAltura').value);
    const peso = parseFloat(document.getElementById('imcPeso').value);
    const resultado = document.getElementById('imcResultado');

    if (!altura || !peso) {
        resultado.textContent = 'Informe altura e peso.';
        return;
    }

    const imc = peso / (altura * altura);
    let status = 'Normal';
    if (imc < 18.5) status = 'Abaixo do peso';
    else if (imc >= 25 && imc < 30) status = 'Sobrepeso';
    else if (imc >= 30) status = 'Obesidade';

    resultado.textContent = imc.toFixed(1) + ' · ' + status;
}

/* ---------- Modal "Pagar plano" (Pix simulado / cartão) ---------- */
document.addEventListener('DOMContentLoaded', () => {
    const painelPix = document.getElementById('painelPix');
    const painelCartao = document.getElementById('painelCartao');
    const metodoPix = document.getElementById('metodoPix');

    // Alterna entre o painel de Pix e o painel de cartão conforme o método escolhido
    document.querySelectorAll('input[name="metodoPagamento"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const isPix = metodoPix.checked;
            painelPix.style.display = isPix ? 'block' : 'none';
            painelCartao.style.display = isPix ? 'none' : 'block';
        });
    });

    // "Gerar QR Code" apenas revela o bloco simulado (não gera Pix real)
    const btnGerarQr = document.getElementById('btnGerarQr');
    if (btnGerarQr) {
        btnGerarQr.addEventListener('click', () => {
            document.getElementById('pixResultado').style.display = 'block';
        });
    }

    // Copia o código "copia e cola" do Pix para a área de transferência
    const btnCopiarPix = document.getElementById('btnCopiarPix');
    if (btnCopiarPix) {
        btnCopiarPix.addEventListener('click', () => {
            const campo = document.getElementById('pixCopiaCola');
            campo.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(campo.value).then(() => boToast('Código Pix copiado.'));
            } else {
                document.execCommand('copy');
                boToast('Código Pix copiado.');
            }
        });
    }

    // "Pagar" apenas fecha o modal e mostra o toast (pagamento simulado)
    const btnPagar = document.getElementById('btnPagar');
    if (btnPagar) {
        btnPagar.addEventListener('click', () => {
            const modalEl = document.getElementById('modalPagarPlano');
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            boToast('Pagamento simulado com sucesso!');
        });
    }
});

/**
 * Exporta as linhas visíveis (não filtradas) de uma tabela para um
 * arquivo .csv baixado pelo navegador.
 */
function boExportTableCsv(tableId) {
    const table = document.querySelector(`[data-bo-table="${tableId}"]`);
    if (!table) return;

    const rows = [];
    table.querySelectorAll('thead tr').forEach((tr) => {
        const cols = Array.from(tr.querySelectorAll('th')).map((th) => `"${th.textContent.trim()}"`);
        rows.push(cols.join(';'));
    });
    table.querySelectorAll('tbody tr:not(.bo-empty-row)').forEach((tr) => {
        if (tr.style.display === 'none') return; // não exporta linhas escondidas pelo filtro
        const cols = Array.from(tr.querySelectorAll('td')).map((td) => `"${td.textContent.trim().replace(/\s+/g, ' ')}"`);
        rows.push(cols.join(';'));
    });

    const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = tableId + '.csv';
    link.click();
    boToast('Exportação gerada.');
}
