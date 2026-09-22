/* Progressive enhancement: native selects remain usable without JavaScript. */
(() => {
    'use strict';
    const t = window.ofT || (text => text);
    const updateBrand = () => {
        const data = window.OneFit;
        if (!data?.brands) return;
        const brand = data.brands[document.documentElement.dataset.siteTheme] || data.brandFallback;
        // Mesma divisão de onefitBrandNameHtml() (config/interface.php): nome
        // do tema com reflexo branco (.brand-nome) + "Fit" na cor do tema
        // (.brand-fit), pra trocar de tema sem recarregar a página.
        document.querySelectorAll('[data-brand-name]').forEach(node => {
            const spaceIndex = brand.name.lastIndexOf(' ');
            const prefixo = spaceIndex === -1 ? '' : brand.name.slice(0, spaceIndex);
            const destaque = spaceIndex === -1 ? brand.name : brand.name.slice(spaceIndex + 1);
            const nome = document.createElement('span');
            nome.className = 'brand-nome';
            nome.textContent = prefixo;
            const fit = document.createElement('span');
            fit.className = 'brand-fit';
            fit.textContent = destaque;
            node.replaceChildren(nome, document.createTextNode(' '), fit);
        });
        document.querySelectorAll('[data-brand-logo]').forEach(node => {
            const url = data.base + 'assets/img/logo/' + brand.logo;
            if (node.tagName === 'IMG') {
                node.alt = 'Logo ' + brand.name;
                node.onerror = () => { node.onerror = null; node.src = data.base + 'assets/img/logo/' + data.brandFallback.logo; };
                node.src = url;
            } else node.href = url;
        });
    };
    updateBrand();
    new MutationObserver(updateBrand).observe(document.documentElement, { attributes: true, attributeFilter: ['data-site-theme'] });
    document.querySelectorAll('input[name="configuracao"][value="tema_cores"]').forEach(setting => {
        setting.form?.addEventListener('change', event => {
            if (event.target.name === 'valor' && window.OneFit?.brands[event.target.value]) {
                document.documentElement.dataset.siteTheme = event.target.value;
            }
        });
    });
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    document.querySelectorAll('select[data-flag-select]').forEach(select => {
        const wrap = document.createElement('div');
        wrap.className = 'of-select';
        const input = document.createElement('input');
        input.type = 'text'; input.autocomplete = 'off'; input.spellcheck = false;
        input.id = select.id + '-combobox'; input.className = 'of-select-input';
        input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        const label = document.querySelector(`label[for="${select.id}"]`);
        if (label) { if (!label.id) label.id = select.id + '-label'; input.setAttribute('aria-labelledby', label.id); label.htmlFor = input.id; }
        input.setAttribute('aria-required', String(select.required));
        const flag = document.createElement('span'); flag.className = 'of-select-flag'; flag.setAttribute('aria-hidden', 'true');
        const list = document.createElement('div'); list.id = select.id + '-listbox';
        list.setAttribute('role', 'listbox'); list.className = 'of-select-list'; list.hidden = true;
        input.setAttribute('aria-controls', list.id);
        const status = document.createElement('span'); status.className = 'of-sr-only'; status.setAttribute('role', 'status');
        select.before(wrap); wrap.append(flag, input, list, status, select);
        select.classList.add('of-native-select'); select.tabIndex = -1; select.setAttribute('aria-hidden', 'true');
        let active = -1, visible = [];
        const countryNames = typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames([window.OneFit?.locale || 'pt-BR'], { type: 'region' }) : null;
        const optionLabel = option => select.dataset.flagSelect === 'country' && /^[A-Z]{2}$/.test(option.value) && countryNames
            ? `${countryNames.of(option.value)} (${option.value})` : option.textContent;
        // Falha de rede/asset num ambiente específico não deve virar texto cru
        // solto ("BR"): os dois tipos (país e estado) caem no mesmo selo
        // estilizado (.of-flag-fallback), consistente entre si.
        const flagFallback = (target, code) => {
            const badge = document.createElement('span');
            badge.className = 'of-flag-fallback';
            badge.textContent = code;
            target.append(badge);
        };
        const decorate = (target, option) => {
            target.replaceChildren();
            if (!option?.value) return;
            if (select.dataset.flagSelect === 'state' && /^[A-Z]{2}$/.test(option.value)) {
                const code = option.value;
                const img = document.createElement('img'); img.src = window.OneFit.base + 'assets/img/flags/states/' + code.toLowerCase() + '.svg';
                img.alt = ''; img.width = 28; img.height = 20;
                img.addEventListener('error', () => { img.remove(); flagFallback(target, code); });
                target.append(img);
            } else if (select.dataset.flagSelect === 'country') {
                const code = option.dataset.country || option.value;
                if (/^[A-Z]{2}$/.test(code)) {
                    const img = document.createElement('img');
                    img.src = window.OneFit.base + 'assets/img/flags/countries/' + code.toLowerCase() + '.svg';
                    img.alt = ''; img.width = 28; img.height = 20;
                    img.addEventListener('error', () => { img.remove(); flagFallback(target, code); });
                    target.append(img);
                }
            }
        };
        const sync = () => {
            const selected = select.selectedOptions[0];
            input.value = selected?.value ? optionLabel(selected) : '';
            input.placeholder = t('Pesquisar e selecionar'); input.disabled = select.disabled;
            decorate(flag, selected);
        };
        const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; sync(); };
        const highlight = index => {
            active = index;
            [...list.children].forEach((row, i) => row.classList.toggle('is-active', i === active));
            if (list.children[active]) { input.setAttribute('aria-activedescendant', list.children[active].id); list.children[active].scrollIntoView({ block: 'nearest' }); }
            else input.removeAttribute('aria-activedescendant');
        };
        const choose = index => {
            if (!visible[index]) return;
            select.value = visible[index].value; select.setCustomValidity(''); input.setCustomValidity('');
            select.dispatchEvent(new Event('input', { bubbles: true })); select.dispatchEvent(new Event('change', { bubbles: true }));
            close(); input.focus();
        };
        const open = (query = '') => {
            if (select.disabled) return;
            visible = [...select.options].filter(o => o.value && !o.disabled && normalize(optionLabel(o)).includes(normalize(query)));
            list.replaceChildren();
            visible.forEach((option, index) => {
                const row = document.createElement('div'); row.id = list.id + '-' + index;
                row.setAttribute('role', 'option'); row.setAttribute('aria-selected', String(option.selected));
                const icon = document.createElement('span'); icon.setAttribute('aria-hidden', 'true'); decorate(icon, option);
                const text = document.createElement('span'); text.textContent = optionLabel(option); row.append(icon, text);
                row.addEventListener('pointerdown', event => { if (event.pointerType === 'mouse') event.preventDefault(); });
                row.addEventListener('click', () => choose(index)); list.append(row);
            });
            status.textContent = visible.length ? t('{n} opções disponíveis', { '{n}': visible.length }) : t('Nenhum resultado');
            list.hidden = false; input.setAttribute('aria-expanded', 'true'); highlight(-1);
        };
        input.addEventListener('click', () => { open(); input.select(); });
        input.addEventListener('input', () => open(input.value));
        input.addEventListener('keydown', event => {
            if (['ArrowDown','ArrowUp'].includes(event.key)) {
                event.preventDefault(); if (list.hidden) open();
                highlight(Math.max(0, Math.min(visible.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1))));
            } else if (event.key === 'Enter' && !list.hidden) { event.preventDefault(); choose(active < 0 && visible.length === 1 ? 0 : active); }
            else if (event.key === 'Escape' && !list.hidden) { event.preventDefault(); event.stopPropagation(); close(); }
            else if (event.key === 'Tab') close();
            else if (event.altKey && event.key === 'Home') { event.preventDefault(); open(); highlight(0); }
        });
        wrap.addEventListener('focusout', event => { if (!wrap.contains(event.relatedTarget)) setTimeout(() => { if (!wrap.contains(document.activeElement)) close(); }, 150); });
        document.addEventListener('pointerdown', event => { if (!wrap.contains(event.target)) close(); });
        select.addEventListener('change', sync);
        select.addEventListener('invalid', event => { event.preventDefault(); input.setCustomValidity(t('Selecione uma opção válida.')); input.focus(); input.reportValidity(); });
        select.addEventListener('focus', () => input.focus());
        select.form?.addEventListener('reset', () => setTimeout(close));
        new MutationObserver(() => { sync(); if (!list.hidden) open(input.value); }).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled','selected'] });
        sync();
    });
    const dialog = document.getElementById('studentPhotoLightbox');
    const trigger = document.querySelector('[data-photo-open]');
    const photoToggle = document.querySelector('[data-photo-menu-toggle]');
    const menu = document.getElementById('studentPhotoMenu');
    const closePhotoMenu = (restore = false) => {
        if (!menu || !photoToggle) return;
        menu.hidden = true;
        photoToggle.setAttribute('aria-expanded', 'false');
        if (restore) photoToggle.focus();
    };
    if (photoToggle && menu) {
        const items = () => [...menu.querySelectorAll('button:not(:disabled)')];
        photoToggle.addEventListener('click', () => {
            if (!menu.hidden) { closePhotoMenu(true); return; }
            const image = photoToggle.querySelector('img');
            if (trigger) trigger.disabled = !image || image.hidden || !image.complete || image.naturalWidth === 0;
            menu.hidden = false;
            photoToggle.setAttribute('aria-expanded', 'true');
            items()[0]?.focus();
        });
        menu.addEventListener('click', event => { if (!menu.hidden && event.target.closest('button')) closePhotoMenu(true); });
        menu.addEventListener('keydown', event => {
            const buttons = items();
            const index = buttons.indexOf(document.activeElement);
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                buttons[event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : buttons.length - 1)) % buttons.length]?.focus();
            }
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !menu.hidden) { event.preventDefault(); closePhotoMenu(true); }
        });
        document.addEventListener('pointerdown', event => {
            if (!menu.contains(event.target) && !photoToggle.contains(event.target)) closePhotoMenu();
        });
        document.addEventListener('focusin', event => {
            if (!menu.contains(event.target) && !photoToggle.contains(event.target)) closePhotoMenu();
        });
    }
    if (dialog && trigger && typeof dialog.showModal === 'function') {
        const image = photoToggle.querySelector('img');
        trigger.addEventListener('click', () => {
            closePhotoMenu();
            if (image && !image.hidden && image.complete && image.naturalWidth > 0) dialog.showModal();
        });
        dialog.querySelector('[data-photo-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Tab') { event.preventDefault(); dialog.querySelector('button').focus(); }
        });
        dialog.addEventListener('close', () => photoToggle.focus());
    }
})();
