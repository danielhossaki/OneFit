// Alterna a visibilidade da senha e atualiza os atributos de acessibilidade.
document.querySelectorAll('.toggle-password').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    if (!input) return;

    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';

    btn.setAttribute('aria-pressed', String(isHidden));
    btn.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');
  });
});

// Converte os dados enviados pelo PHP em uma notificação acessível.
function showFormMessage() {
  const messageText = document.body?.dataset.formMessage;
  if (!messageText || document.querySelector('.form-msg')) return;

  const allowedTypes = ['erro', 'sucesso'];
  const requestedType = document.body.dataset.formMessageType;
  const messageType = allowedTypes.includes(requestedType) ? requestedType : 'erro';

  const message = document.createElement('div');
  message.className = `form-msg form-msg-${messageType}`;
  message.setAttribute('role', 'alert');
  message.setAttribute('aria-live', 'assertive');

  const icon = document.createElement('span');
  icon.className = 'form-msg-icon';
  icon.setAttribute('aria-hidden', 'true');

  const text = document.createElement('span');
  text.textContent = messageText;

  message.append(icon, text);
  document.body.prepend(message);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', showFormMessage);
} else {
  showFormMessage();
}
