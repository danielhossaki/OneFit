/* IMC derivado; o PHP repete a validação e o cálculo ao salvar. */
(() => {
    'use strict';
    const modal = document.querySelector('.bo-student-modal');
    if (!modal) return;
    const form = modal.querySelector('form');
    const medida = (value, max) => {
        const text = value.trim().replace(',', '.');
        if (!/^\d+(?:\.\d+)?$/.test(text)) return null;
        const number = Math.round((Number(text) + Number.EPSILON) * 100) / 100;
        return Number.isFinite(number) && number > 0 && number <= max ? number : null;
    };
    const update = () => {
        const altura = medida(form.elements.altura.value, 3);
        const peso = medida(form.elements.peso.value, 500);
        const imc = altura && peso ? peso / (altura * altura) : NaN;
        const valid = Number.isFinite(imc);
        modal.querySelector('[data-student-imc]').textContent = valid ? imc.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : 'Não informado';
        modal.querySelector('[data-student-class]').textContent = !valid ? '' : imc < 18.5 ? 'Abaixo do peso' : imc < 25 ? 'Peso adequado' : imc < 30 ? 'Sobrepeso' : imc < 35 ? 'Obesidade grau I' : imc < 40 ? 'Obesidade grau II' : 'Obesidade grau III';
    };
    for (const name of ['altura', 'peso']) {
        form.elements[name].addEventListener('input', () => { form.elements[name].setCustomValidity(''); update(); });
    }
    form.addEventListener('submit', (event) => {
        for (const [name, max] of [['altura', 3], ['peso', 500]]) {
            const input = form.elements[name];
            input.setCustomValidity(input.value.trim() && medida(input.value, max) === null ? `Informe um valor maior que zero e até ${max}.` : '');
        }
        if (!form.reportValidity()) event.preventDefault();
    });
    const validatePhoto = (input) => {
        const file = input.files[0];
        const valid = !file || (/\.(jpe?g|png|webp)$/i.test(file.name) && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size > 0 && file.size <= 3 * 1024 * 1024);
        input.setCustomValidity(valid ? '' : 'Selecione uma imagem JPG, PNG ou WEBP de até 3 MB.');
        return valid;
    };
    let previewUrl;
    const preview = modal.querySelector('[data-student-preview]');
    form.elements.foto_arquivo.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        preview.hidden = true;
        if (!validatePhoto(form.elements.foto_arquivo)) { form.elements.foto_arquivo.reportValidity(); return; }
        const file = form.elements.foto_arquivo.files[0];
        if (file) { previewUrl = URL.createObjectURL(file); preview.src = previewUrl; preview.hidden = false; }
    });
    modal.addEventListener('hidden.bs.modal', () => {
        form.reset();
        for (const input of form.querySelectorAll('input')) input.setCustomValidity('');
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        preview.hidden = true;
        update();
    });
    modal.addEventListener('show.bs.modal', update);
    update();
    document.querySelectorAll('[data-student-photo]').forEach(img => {
        img.addEventListener('error', () => { img.hidden = true; });
        if (img.complete && !img.naturalWidth) img.hidden = true;
    });
    const quick = document.getElementById('studentQuickPhoto');
    const status = document.getElementById('studentPhotoStatus');
    document.getElementById('studentChoosePhoto').addEventListener('click', () => quick.click());
    quick.addEventListener('change', () => {
        if (!quick.files.length) return;
        if (!validatePhoto(quick)) { status.textContent = quick.validationMessage; quick.value = ''; return; }
        status.textContent = 'Salvando foto…';
        document.getElementById('studentChoosePhoto').disabled = true;
        document.getElementById('studentPhotoForm').requestSubmit();
    });
})();
