const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function element(value = '') {
    return { value, files: [], handlers: {}, textContent: '', addEventListener(name, fn) { this.handlers[name] = fn; }, setCustomValidity(message) { this.validationMessage = message; } };
}
const fields = { altura: element('1,72'), peso: element('100'), foto_arquivo: element() };
const form = Object.assign(element(), { elements: fields });
const result = element(), classification = element(), preview = element();
const modal = Object.assign(element(), { querySelector(selector) { return { form, '[data-student-imc]': result, '[data-student-class]': classification, '[data-student-preview]': preview }[selector]; } });
const ids = Object.fromEntries(['studentQuickPhoto', 'studentPhotoStatus', 'studentChoosePhoto', 'studentPhotoForm'].map(id => [id, element()]));
const document = { querySelector: () => modal, querySelectorAll: () => [], getElementById: id => ids[id] };
vm.runInNewContext(fs.readFileSync('assets/js/student-profile.js', 'utf8'), { document, URL, Number, Math });
assert.equal(result.textContent, '33,8');
assert.equal(classification.textContent, 'Obesidade grau I');
for (const [weight, expected] of [['18,49', 'Abaixo do peso'], ['18.5', 'Peso adequado'], ['25', 'Sobrepeso'], ['30', 'Obesidade grau I'], ['35', 'Obesidade grau II'], ['40', 'Obesidade grau III']]) {
    fields.altura.value = '1'; fields.peso.value = weight; fields.peso.handlers.input();
    assert.equal(classification.textContent, expected);
}
for (const invalid of ['', '0', '-1', 'NaN', 'Infinity', '1.72abc', '1,2,3']) {
    fields.altura.value = invalid; fields.altura.handlers.input();
    assert.equal(result.textContent, 'Não informado');
}
ids.studentQuickPhoto.files = [{ name: 'foto.php', type: 'image/jpeg', size: 123 }];
ids.studentQuickPhoto.handlers.change();
assert.match(ids.studentPhotoStatus.textContent, /Selecione uma imagem/);
console.log('JavaScript: recálculo por input, limites, inválidos e rejeição de arquivo OK');
