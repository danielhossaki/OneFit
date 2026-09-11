var ofT = globalThis.ofT || (text => text);
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
        modal.querySelector('[data-student-imc]').textContent = valid ? imc.toLocaleString(globalThis.OneFit?.locale || 'pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) : ofT('Não informado');
        modal.querySelector('[data-student-class]').textContent = !valid ? '' : imc < 18.5 ? ofT('Abaixo do peso') : imc < 25 ? ofT('Peso adequado') : imc < 30 ? ofT('Sobrepeso') : imc < 35 ? ofT('Obesidade grau I') : imc < 40 ? ofT('Obesidade grau II') : ofT('Obesidade grau III');
    };
    for (const name of ['altura', 'peso']) {
        form.elements[name].addEventListener('input', () => { form.elements[name].setCustomValidity(''); update(); });
    }
    form.addEventListener('submit', (event) => {
        for (const [name, max] of [['altura', 3], ['peso', 500]]) {
            const input = form.elements[name];
            input.setCustomValidity(input.value.trim() && medida(input.value, max) === null ? ofT('Informe um valor maior que zero e até {max}.', { '{max}': max }) : '');
        }
        if (!form.reportValidity()) event.preventDefault();
    });
    const validatePhoto = (input) => {
        const file = input.files[0];
        const valid = !file || (/\.(jpe?g|png|webp)$/i.test(file.name) && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size > 0 && file.size <= 3 * 1024 * 1024);
        input.setCustomValidity(valid ? '' : ofT('Selecione uma imagem JPG, PNG ou WEBP de até 3 MB.'));
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
    const photoToggle = document.querySelector('[data-photo-menu-toggle]');
    const photoMenu = document.getElementById('studentPhotoMenu');
    const closePhotoMenu = (returnFocus = false) => {
        if (!photoMenu || !photoToggle) return;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduced) {
            photoMenu.hidden = true;
            photoToggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) photoToggle.focus();
            return;
        }
        photoMenu.classList.add('is-closing');
        const finish = () => {
            photoMenu.hidden = true;
            photoMenu.classList.remove('is-closing');
            photoToggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) photoToggle.focus();
        };
        const onTransitionEnd = (event) => {
            if (event.target === photoMenu) {
                photoMenu.removeEventListener('transitionend', onTransitionEnd);
                finish();
            }
        };
        photoMenu.addEventListener('transitionend', onTransitionEnd, { once: true });
        setTimeout(() => {
            if (!photoMenu.hidden) finish();
        }, 220);
    };
    const openPhotoMenu = () => {
        if (!photoMenu || !photoToggle) return;
        photoMenu.hidden = false;
        photoMenu.classList.remove('is-closing');
        photoToggle.setAttribute('aria-expanded', 'true');
        const firstItem = photoMenu.querySelector('button');
        firstItem?.focus();
    };
    if (photoToggle && photoMenu) {
        photoToggle.addEventListener('click', () => {
            if (photoMenu.hidden) openPhotoMenu(); else closePhotoMenu(true);
        });
        photoMenu.addEventListener('click', (event) => {
            if (event.target.closest('button')) closePhotoMenu(true);
        });
        document.addEventListener('pointerdown', (event) => {
            if (!photoMenu.hidden && !photoToggle.contains(event.target) && !photoMenu.contains(event.target)) closePhotoMenu();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !photoMenu.hidden) closePhotoMenu(true);
        });
    }
    document.getElementById('studentChoosePhoto').addEventListener('click', () => quick.click());
    quick.addEventListener('change', () => {
        if (!quick.files.length) return;
        if (!validatePhoto(quick)) { status.textContent = quick.validationMessage; quick.value = ''; return; }
        status.textContent = ofT('Salvando foto…');
        document.getElementById('studentChoosePhoto').disabled = true;
        document.getElementById('studentPhotoForm').requestSubmit();
    });

    /* Contador de caracteres do card "Comente aqui" — só mostra quantos
     * caracteres já foram digitados; o limite em si já é aplicado pelo
     * maxlength="500" do textarea (funciona mesmo sem este JS). */
    const testemunhoTexto = document.querySelector('[data-testemunho-texto]');
    const testemunhoContador = document.querySelector('[data-testemunho-contador]');
    if (testemunhoTexto && testemunhoContador) {
        testemunhoTexto.addEventListener('input', () => {
            testemunhoContador.textContent = testemunhoTexto.value.length;
        });
    }
})();
