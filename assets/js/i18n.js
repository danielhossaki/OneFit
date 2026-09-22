(() => {
    'use strict';
    const data = JSON.parse(document.getElementById('onefit-interface-data').textContent);
    window.OneFit = data;
    window.ofT = (text, values = {}) => {
        if (Object.values(data.brands).some(brand => brand.name === text)) return text;
        const brand = data.brands[document.documentElement.dataset.siteTheme] || data.brandFallback;
        const params = { '{marca}': brand.name, ...values };
        // A single replacement pass keeps interpolated user data intact.
        return (data.messages[text] ?? text).replace(/\{[^{}]+\}/g, key =>
            Object.prototype.hasOwnProperty.call(params, key) ? String(params[key]) : key);
    };
    // Browser validation follows the application language, not the browser UI language.
    // Preserve custom messages supplied by feature-specific validators.
    const localizedValidity = new WeakMap();
    const clearLocalizedValidity = field => {
        if (localizedValidity.has(field)) {
            if (field.validationMessage === localizedValidity.get(field)) field.setCustomValidity('');
            localizedValidity.delete(field);
        }
    };
    document.addEventListener('invalid', event => {
        const field = event.target;
        if (typeof field.setCustomValidity !== 'function') return;
        clearLocalizedValidity(field);
        const validity = field.validity;
        if (validity.customError) return;
        let message = '';
        if (validity.valueMissing) message = ofT('Preencha este campo.');
        else if (validity.typeMismatch && field.type === 'email') message = ofT('Digite um e-mail válido.');
        else if (validity.tooShort) message = ofT('Mínimo de {n} caracteres.', { '{n}': field.minLength });
        else if (validity.tooLong) message = ofT('Máximo de {n} caracteres.', { '{n}': field.maxLength });
        else if (validity.rangeUnderflow) message = ofT('O valor mínimo é {n}.', { '{n}': field.min });
        else if (validity.rangeOverflow) message = ofT('O valor máximo é {n}.', { '{n}': field.max });
        else if (!validity.valid) message = ofT('Verifique este campo.');
        if (message) { field.setCustomValidity(message); localizedValidity.set(field, message); }
    }, true);
    document.addEventListener('reset', event => { for (const field of event.target.elements || []) clearLocalizedValidity(field); }, true);
    for (const name of ['input', 'change']) document.addEventListener(name, event => clearLocalizedValidity(event.target), true);
})();
