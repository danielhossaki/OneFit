(() => {
    'use strict';
    const data = JSON.parse(document.getElementById('onefit-interface-data').textContent);
    window.OneFit = data;
    window.ofT = (text, values = {}) => Object.entries(values).reduce(
        (result, [key, value]) => result.split(key).join(String(value)), data.messages[text] ?? text);
})();
