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
            { key: 'planos', label: 'Cadastro de Planos', icon: 'bi-clipboard-check' },
            { key: 'profissionais', label: 'Profissionais', icon: 'bi-person-badge' },
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
        ],
    },
};

// Estado atual da tela: qual perfil está sendo visualizado e qual seção do menu
let boPerfilAtual = 'admin';
let boSectionAtual = 'dashboard';
let boFormModalInstance = null; // instância do Modal do Bootstrap (definida no DOMContentLoaded)

/* ---------- Esquemas do modal de formulário genérico ----------
   Cada chave corresponde ao 1º argumento passado em boOpenForm(schemaKey, ...)
   nos botões "onclick" do HTML. A lista de campos aqui é usada por
   boBuildField() para montar o formulário dinamicamente dentro do modal
   #boFormModal, sem precisar de um modal HTML diferente para cada tela. */
const BO_FORM_SCHEMAS = {
    usuarioEdit: [
        { key: 'nome', label: 'Nome completo', type: 'text', col: 12 },
        { key: 'email', label: 'E-mail', type: 'email', col: 12 },
        { key: 'cpf', label: 'CPF', type: 'text', col: 6 },
        { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
        { key: 'matricula', label: 'Nº da matrícula', type: 'text', col: 6 },
        { key: 'dataInicial', label: 'Data inicial', type: 'date', col: 6 },
        { key: 'dataFinal', label: 'Final de contrato', type: 'date', col: 6 },
        { key: 'acesso', label: 'Acesso', type: 'select', options: ['Liberado', 'Bloqueado'], col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    permissaoNova: [
        { key: 'usuarioId', label: 'ID do usuário', type: 'text', col: 6 },
        { key: 'nome', label: 'Nome', type: 'text', col: 6 },
        { key: 'email', label: 'E-mail', type: 'email', col: 12 },
        { key: 'funcao', label: 'Tipo de função', type: 'select', options: ['Administrador', 'Gerente', 'Instrutor', 'Recepção'], col: 12 },
    ],
    funcaoForm: [
        { key: 'nome', label: 'Nome da função', type: 'text', col: 12 },
        { key: 'permissoes', label: 'Permissões de acesso', type: 'checklist', options: ['Usuários', 'Pagamentos', 'Cashbacks', 'Produtos', 'Planos', 'Profissionais', 'Alunos', 'Agenda'], col: 12 },
    ],
    pagamentoForm: [
        { key: 'data', label: 'Data', type: 'date', col: 6 },
        { key: 'tipo', label: 'Tipo', type: 'select', options: ['PIX', 'Dinheiro', 'Crédito', 'Débito'], col: 6 },
        { key: 'valor', label: 'Valor', type: 'number', col: 6 },
        { key: 'usuarioId', label: 'ID do usuário', type: 'text', col: 6 },
        { key: 'observacao', label: 'Observação', type: 'textarea', col: 12 },
    ],
    cashbackLancar: [
        { key: 'data', label: 'Data', type: 'date', col: 6 },
        { key: 'tipo', label: 'Tipo', type: 'select', options: ['credito', 'debito'], optionLabels: ['Crédito', 'Débito'], col: 6 },
        { key: 'valor', label: 'Valor', type: 'number', col: 6 },
        { key: 'usuarioId', label: 'ID do usuário', type: 'text', col: 6 },
    ],
    cashbackMassa: [
        { key: 'data', label: 'Data', type: 'date', col: 6 },
        { key: 'valor', label: 'Valor', type: 'number', col: 6 },
        { key: 'alvo', label: 'Alvo', type: 'select', options: ['Todos', 'Ativos'], col: 12 },
    ],
    categoriaForm: [
        { key: 'nome', label: 'Nome da categoria', type: 'text', col: 12 },
    ],
    produtoForm: [
        { key: 'nome', label: 'Nome do produto', type: 'text', col: 12 },
        { key: 'categoria', label: 'Categoria', type: 'select', options: BO_CATEGORIAS_OPTIONS, col: 6 },
        { key: 'preco', label: 'Preço', type: 'number', col: 6 },
        { key: 'desconto', label: 'Desconto (%)', type: 'number', col: 6 },
        { key: 'cashback', label: 'Cashback (%)', type: 'number', col: 6 },
        { key: 'estoque', label: 'Estoque', type: 'number', col: 6 },
        { key: 'imagem', label: 'Imagem do produto (upload ou URL)', type: 'image', col: 12 },
        { key: 'valorFinal', label: 'Valor final', type: 'text', col: 6, readonly: true },
        { key: 'descricao', label: 'Descrição', type: 'textarea', col: 12 },
    ],
    planoForm: [
        { key: 'nome', label: 'Nome do plano', type: 'text', col: 12 },
        { key: 'valor', label: 'Valor do plano', type: 'number', col: 6 },
        { key: 'ciclo', label: 'Ciclo', type: 'select', options: ['Mensal', 'Trimestral', 'Semestral', 'Anual'], col: 6 },
        { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
        { key: 'textoBotao', label: 'Texto do botão', type: 'text', col: 6 },
        { key: 'descricao', label: 'Descrição', type: 'textarea', col: 12 },
    ],
    profissionalForm: [
        { key: 'nome', label: 'Nome', type: 'text', col: 6 },
        { key: 'funcao', label: 'Função', type: 'text', col: 6 },
        { key: 'tituloCard', label: 'Título do card', type: 'text', col: 6 },
        { key: 'documento', label: 'Documento', type: 'text', col: 6 },
        { key: 'status', label: 'Status', type: 'select', options: ['ativo', 'inativo'], optionLabels: ['Ativo', 'Inativo'], col: 6 },
        { key: 'email', label: 'E-mail', type: 'email', col: 6 },
        { key: 'telefone', label: 'Telefone', type: 'text', col: 6 },
        { key: 'celular', label: 'Celular', type: 'text', col: 6 },
        { key: 'descricao', label: 'Descrição', type: 'textarea', col: 12 },
        { key: 'experiencia', label: 'Experiência', type: 'textarea', col: 12 },
        { key: 'foto', label: 'Foto (upload ou URL)', type: 'image', col: 12 },
        { key: 'observacaoInterna', label: 'Observação interna', type: 'textarea', col: 12 },
    ],
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
        { key: 'nome', label: 'Nome', type: 'text', col: 6 },
        { key: 'documento', label: 'Documento', type: 'text', col: 6 },
        { key: 'email', label: 'E-mail', type: 'email', col: 6 },
        { key: 'telefone', label: 'Telefone', type: 'text', col: 6 },
        { key: 'nacionalidade', label: 'Nacionalidade', type: 'text', col: 6 },
        { key: 'nascimento', label: 'Data de nascimento', type: 'date', col: 6 },
        { key: 'genero', label: 'Gênero', type: 'select', options: ['Masculino', 'Feminino'], col: 6 },
        { key: 'endereco', label: 'Endereço', type: 'text', col: 12 },
        { key: 'cidade', label: 'Cidade', type: 'text', col: 6 },
        { key: 'estado', label: 'Estado', type: 'text', col: 6 },
        { key: 'altura', label: 'Altura (m)', type: 'number', col: 6 },
        { key: 'peso', label: 'Peso (kg)', type: 'number', col: 6 },
        { key: 'foto', label: 'Foto (upload ou URL)', type: 'image', col: 12 },
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
        if (el && values[field.key] !== undefined) el.value = values[field.key];
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
    saveBtn.addEventListener('click', () => {
        if (options.doubleConfirm && confirmStep === 0) {
            confirmStep = 1;
            saveBtn.textContent = 'Clique novamente para confirmar';
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
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bo-nav-item' + (item.key === boSectionAtual ? ' active' : '');
        btn.setAttribute('data-section', item.key);
        btn.innerHTML = `<i class="bi ${item.icon}"></i><span>${item.label}</span>`;
        btn.addEventListener('click', () => boGoToSection(item.key));
        nav.appendChild(btn);
    });
}

/**
 * Reconstrói o dropdown "Administrador / Profissional / Aluno" do header,
 * marcando o perfil ativo. (Esse seletor existe só para navegar entre as
 * 3 visões durante o desenvolvimento/testes do backoffice.)
 */
function boRenderPerfilMenu() {
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

/**
 * Troca a seção visível dentro do perfil atual. Procura uma
 * <section data-perfil="X" data-section="Y"> já pronta no HTML; se não
 * existir, mostra a seção de fallback "Em construção" com o título certo.
 */
function boGoToSection(sectionKey) {
    boSectionAtual = sectionKey;

    document.querySelectorAll('#boNav .bo-nav-item').forEach((btn) => {
        btn.classList.toggle('active', btn.getAttribute('data-section') === sectionKey);
    });

    const prebuilt = document.querySelector(
        `.bo-content-section[data-perfil="${boPerfilAtual}"][data-section="${sectionKey}"]`
    );

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
}

/**
 * Troca o perfil ativo (admin/profissional/aluno), atualiza header,
 * remonta o menu lateral e abre a primeira seção do novo perfil.
 */
function boTrocarPerfil(perfilKey) {
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
    boGoToSection('dashboard');

    // Clique num item do dropdown de perfil (header) -> troca de perfil
    document.getElementById('boPerfilMenu').addEventListener('click', (event) => {
        const link = event.target.closest('a[data-perfil]');
        if (!link) return;
        event.preventDefault();
        boTrocarPerfil(link.getAttribute('data-perfil'));
    });

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

        // Pausar/Ativar (alterna o badge de status de uma linha, sem reload)
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

        // Excluir (pede confirmação e remove a linha da tabela)
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
