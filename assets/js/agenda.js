/* Seleção visual; disponibilidade e validação definitiva pertencem ao PHP. */
document.querySelectorAll('.bo-agenda-booking').forEach(form => {
    const slots = JSON.parse(form.querySelector('[data-agenda-slots]').textContent);
    const type = form.querySelector('[data-agenda-type]');
    const professional = form.querySelector('[data-agenda-professional]');
    const times = form.querySelector('[data-agenda-times]');
    const submit = form.querySelector('[type="submit"]');
    [...new Set(slots.map(slot => slot.modalidade))].forEach(label => type.add(new Option(label, label)));
    function renderTimes() {
        times.replaceChildren();
        submit.disabled = true;
        const available = slots.filter(slot => slot.modalidade === type.value && String(slot.id_profissional) === professional.value);
        available.sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio)).forEach(slot => {
            const label = document.createElement('label');
            label.className = 'bo-agenda-time';
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'slot';
            radio.value = slot.key;
            radio.required = true;
            radio.addEventListener('change', () => { submit.disabled = false; });
            const text = document.createElement('span');
            text.textContent = slot.hora_inicio.slice(0, 5);
            label.append(radio, text);
            times.append(label);
        });
        form.querySelector('[data-agenda-empty]').hidden = available.length > 0;
    }
    function renderProfessionals() {
        professional.replaceChildren();
        const options = new Map();
        slots.filter(slot => slot.modalidade === type.value).forEach(slot => options.set(slot.id_profissional, slot.profissional));
        options.forEach((name, id) => professional.add(new Option(name, id)));
        renderTimes();
    }
    type.addEventListener('change', renderProfessionals);
    professional.addEventListener('change', renderTimes);
    form.addEventListener('submit', () => { submit.disabled = true; });
    renderProfessionals();
});
document.querySelectorAll('[data-agenda-cancel]').forEach(form => {
    const open = form.querySelector('[data-cancel-open]');
    const confirmation = form.querySelector('[data-cancel-confirm]');
    open.addEventListener('click', () => {
        open.hidden = true;
        confirmation.hidden = false;
        form.querySelector('[data-cancel-back]').focus();
    });
    form.querySelector('[data-cancel-back]').addEventListener('click', () => {
        confirmation.hidden = true;
        open.hidden = false;
        open.focus();
    });
});
