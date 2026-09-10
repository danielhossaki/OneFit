document.addEventListener('DOMContentLoaded', () => {
    const section = document.getElementById('boTreino');
    if (!section) return;
    const form = document.getElementById('boTreinoForm');
    const modalElement = document.getElementById('boTreinoModal');
    const confirmElement = document.getElementById('boTreinoConfirmar');
    const modal = new bootstrap.Modal(modalElement);
    const confirmModal = new bootstrap.Modal(confirmElement);
    const error = form.querySelector('[data-treino-erro]');
    const confirmError = confirmElement.querySelector('[data-treino-confirmar-erro]');
    const notice = section.querySelector('[data-treino-aviso]');
    let exercises = JSON.parse(section.querySelector('[data-treino-dados]').textContent);
    const filter = section.querySelector('[data-treino-filtro]');
    const days = Object.fromEntries(Array.from(filter.options).filter(option => option.value).map(option => [option.value, option.textContent]));
    filter.addEventListener('change', render);
    let pending = null;
    let busy = false;

    function render() {
        const body = section.querySelector('[data-treino-linhas]');
        body.replaceChildren();
        const visible = exercises.filter(exercise => !filter.value || exercise.dia_semana === filter.value);
        visible.forEach(exercise => {
            const row = body.insertRow();
            [days[exercise.dia_semana] || 'Não definido', exercise.nome, exercise.series, exercise.repeticoes, `${exercise.carga} kg`].forEach(value => {
                row.insertCell().textContent = value;
            });
            const actions = document.createElement('div');
            actions.className = 'bo-table-actions';
            [['editar', 'Editar', 'bi-pencil'], ['excluir', 'Excluir', 'bi-trash']].forEach(([action, title, icon]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn-bo-icon' + (action === 'excluir' ? ' danger' : '');
                button.title = title;
                button.setAttribute(`data-treino-${action}`, exercise.id);
                const symbol = document.createElement('i');
                symbol.className = `bi ${icon}`;
                button.append(symbol);
                actions.append(button);
            });
            row.insertCell().append(actions);
        });
        if (!visible.length) {
            const cell = body.insertRow().insertCell();
            cell.colSpan = 6;
            cell.textContent = filter.value ? 'Sem exercícios cadastrados neste dia.' : 'Nenhum exercício cadastrado.';
        }
    }

    async function send(data, errorElement, onSuccess) {
        if (busy) return;
        busy = true;
        errorElement.hidden = true;
        [modalElement, confirmElement, section].forEach(element => {
            element.querySelectorAll('button').forEach(button => { button.disabled = true; });
        });
        try {
            data.set('csrf_token', section.dataset.csrf);
            const response = await fetch(section.dataset.endpoint, { method: 'POST', body: data, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar o treino.');
            exercises = result.exercicios;
            render();
            onSuccess();
            notice.hidden = false;
            notice.textContent = 'Treino atualizado.';
        } catch (failure) {
            errorElement.textContent = failure instanceof SyntaxError ? 'Resposta inválida. Atualize a página e tente novamente.' : failure.message;
            errorElement.hidden = false;
        } finally {
            busy = false;
            [modalElement, confirmElement, section].forEach(element => {
                element.querySelectorAll('button').forEach(button => { button.disabled = false; });
            });
        }
    }

    [modalElement, confirmElement].forEach(element => {
        element.addEventListener('hide.bs.modal', event => { if (busy) event.preventDefault(); });
    });
    function open(exercise) {
        form.reset();
        error.hidden = true;
        form.elements.id.value = exercise ? exercise.id : 0;
        // Token por abertura; reaproveitado em retries após erro de rede.
        form.elements.token.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
        form.elements.dia_semana.value = exercise ? (exercise.dia_semana || '') : (filter.value || 'segunda');
        if (exercise) ['nome', 'series', 'repeticoes', 'carga'].forEach(key => { form.elements[key].value = exercise[key]; });
        document.getElementById('boTreinoTitulo').textContent = exercise ? 'Editar exercício' : 'Adicionar exercício';
        modal.show();
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity() || busy) return;
        const data = new FormData(form);
        data.set('acao', 'salvar');
        send(data, error, () => { busy = false; modal.hide(); });
    });
    section.addEventListener('click', event => {
        if (busy) return;
        if (event.target.closest('[data-treino-adicionar]')) { open(null); return; }
        const edit = event.target.closest('[data-treino-editar]');
        if (edit) { open(exercises.find(item => String(item.id) === edit.dataset.treinoEditar)); return; }
        const remove = event.target.closest('[data-treino-excluir]');
        const clear = event.target.closest('[data-treino-limpar]');
        if (!remove && !clear) return;
        pending = { acao: clear ? 'limpar' : 'excluir', id: clear ? '0' : remove.dataset.treinoExcluir };
        confirmError.hidden = true;
        document.getElementById('boTreinoConfirmarTitulo').textContent = clear ? 'Limpar treino' : 'Excluir exercício';
        confirmElement.querySelector('[data-treino-pergunta]').textContent = clear
            ? 'Tem certeza que deseja limpar todo o treino de todos os dias?' : 'Tem certeza que deseja excluir este exercício?';
        confirmElement.querySelector('[data-treino-confirmar]').textContent = clear ? 'Limpar treino' : 'Excluir';
        confirmModal.show();
    });
    confirmElement.querySelector('[data-treino-confirmar]').addEventListener('click', () => {
        if (!pending || busy) return;
        const data = new FormData();
        Object.entries(pending).forEach(([key, value]) => data.set(key, value));
        send(data, confirmError, () => { busy = false; pending = null; confirmModal.hide(); });
    });
});
