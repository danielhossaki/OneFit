// ==========================================================
// MATRÍCULA — wizard de etapas com validação inline
// ==========================================================

(() => {
  const form = document.querySelector('.matricula-form');
  if (!form) return;

  const steps = Array.from(form.querySelectorAll('.form-step'));
  const progressFill = document.getElementById('progress-fill');
  const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
  const subtitle = document.getElementById('step-subtitle');
  const total = steps.length;

  const subtitles = {
    1: 'Preencha seus dados para começar a treinar com a gente.',
    2: 'Precisamos do seu endereço para emitir sua matrícula.',
    3: 'Escolha o plano que mais combina com seu objetivo.',
    4: 'Falta pouco — escolha como prefere pagar.',
  };

  let current = 1;
  let maxReached = 1;

  // ---------- Navegação entre etapas ----------
  function goToStep(n, { focus = true } = {}) {
    steps.forEach((step) => {
      step.classList.toggle('active', Number(step.dataset.step) === n);
    });

    maxReached = Math.max(maxReached, n);

    progressSteps.forEach((el) => {
      const stepNum = Number(el.dataset.stepLabel);
      el.classList.toggle('active', stepNum === n);
      el.classList.toggle('done', stepNum < maxReached);
      el.tabIndex = stepNum < maxReached ? 0 : -1;
    });

    progressFill.style.width = `${(n / total) * 100}%`;
    subtitle.textContent = subtitles[n] || '';
    current = n;

    if (focus) {
      const activeStep = steps.find((s) => Number(s.dataset.step) === n);
      const firstField = activeStep?.querySelector('input, select');
      if (firstField && window.innerWidth > 860) {
        // Só rouba o foco em telas maiores, pra não abrir teclado no mobile sem o usuário pedir
        firstField.focus({ preventScroll: true });
      }
    }

    const wrap = document.querySelector('.matricula-wrap');
    if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ---------- Mensagens de validação customizadas ----------
  function messageFor(input) {
    const v = input.validity;
    if (v.valueMissing) return 'Preencha este campo.';
    if (v.typeMismatch && input.type === 'email') return 'Digite um e-mail válido.';
    if (v.tooShort) return `Mínimo de ${input.minLength} caracteres.`;
    if (v.patternMismatch) return 'Formato inválido.';
    return 'Verifique este campo.';
  }

  function setFieldState(input, valid, customMessage) {
    const field = input.closest('.field');
    if (!field) return;

    let msgEl = field.querySelector('.field-message');
    if (!msgEl) {
      msgEl = document.createElement('span');
      msgEl.className = 'field-message';
      field.appendChild(msgEl);
    }

    field.classList.toggle('invalid', !valid);
    field.classList.toggle('valid', valid && input.value.trim().length > 0);
    msgEl.textContent = valid ? '' : (customMessage || messageFor(input));
  }

  function validateField(input) {
    // Ignora campos de pagamento de abas inativas
    const panel = input.closest('.payment-panel');
    if (panel && !panel.classList.contains('active')) return true;

    const valid = input.checkValidity();
    setFieldState(input, valid);
    return valid;
  }

  // Validação em tempo real ao sair do campo, e ao digitar depois de já ter sido marcado inválido
  form.querySelectorAll('input, select').forEach((input) => {
    input.addEventListener('blur', () => validateField(input));
    input.addEventListener('input', () => {
      const field = input.closest('.field');
      if (field?.classList.contains('invalid')) validateField(input);
    });
  });

  function validateStep(n) {
    const step = steps.find((s) => Number(s.dataset.step) === n);
    if (!step) return true;

    let valid = true;

    step.querySelectorAll('input, select').forEach((input) => {
      if (!validateField(input)) valid = false;
    });

    // Confirmação de senha (etapa 1)
    if (n === 1) {
      const senha = document.getElementById('password');
      const confirmar = document.getElementById('confirmar-senha');
      if (senha.value && confirmar.value && senha.value !== confirmar.value) {
        setFieldState(confirmar, false, 'As senhas não coincidem.');
        valid = false;
      }
    }

    // Plano (etapa 3): garante feedback visual mesmo com radios "required" nativos
    if (n === 3) {
      const planWrap = step.querySelector('.plan-select');
      const checked = step.querySelector('input[name="plano"]:checked');
      planWrap.classList.toggle('invalid', !checked);
      if (!checked) valid = false;
    }

    // Foca o primeiro campo inválido pra guiar o usuário
    if (!valid) {
      const firstInvalid = step.querySelector('.field.invalid input, .field.invalid select');
      firstInvalid?.focus({ preventScroll: false });
    }

    return valid;
  }

  form.querySelectorAll('[data-next]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!validateStep(current)) return;
      if (current < total) goToStep(current + 1);
    });
  });

  form.querySelectorAll('[data-prev]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (current > 1) goToStep(current - 1, { focus: false });
    });
  });

  // Progresso clicável — só permite voltar a etapas já concluídas
  progressSteps.forEach((el) => {
    el.addEventListener('click', () => {
      const stepNum = Number(el.dataset.stepLabel);
      if (el.classList.contains('done') && stepNum < current) {
        goToStep(stepNum, { focus: false });
      }
    });
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        el.click();
      }
    });
  });

  // Enter avança de etapa em vez de submeter o formulário direto
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (e.target.tagName === 'TEXTAREA') return;

    if (current < total) {
      e.preventDefault();
      const nextBtn = steps
        .find((s) => Number(s.dataset.step) === current)
        .querySelector('[data-next]');
      nextBtn?.click();
    }
  });

  form.addEventListener('submit', (e) => {
    if (!validateStep(current)) {
      e.preventDefault();
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn?.classList.add('is-loading');
  });

  // ---------- Seleção visual do plano ----------
  const planOptions = Array.from(form.querySelectorAll('.plan-option'));
  function refreshPlanSelection() {
    planOptions.forEach((opt) => {
      const input = opt.querySelector('input[type="radio"]');
      opt.classList.toggle('is-checked', input.checked);
    });
    form.querySelector('.plan-select')?.classList.remove('invalid');
  }
  planOptions.forEach((opt) => {
    opt.querySelector('input').addEventListener('change', refreshPlanSelection);
  });
  refreshPlanSelection();

  // ---------- Abas de pagamento ----------
  const paymentTabs = Array.from(form.querySelectorAll('.payment-tab'));
  const paymentPanels = Array.from(form.querySelectorAll('.payment-panel'));

  paymentTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.payment;

      paymentTabs.forEach((t) => t.classList.toggle('active', t === tab));
      paymentPanels.forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.paymentPanel === target);
      });
    });
  });

  // ---------- Máscaras ----------
  const onlyDigits = (v) => v.replace(/\D/g, '');

  function mask(id, formatter, maxDigits) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
      const digits = onlyDigits(el.value).slice(0, maxDigits);
      el.value = formatter(digits);
    });
  }

  mask('cpf', (d) => d
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2'), 11);

  mask('telefone', (d) => {
    if (d.length <= 10) {
      return d
        .replace(/(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{4})(\d)/, '$1-$2');
    }
    return d
      .replace(/(\d{2})(\d)/, '($1) $2')
      .replace(/(\d{5})(\d)/, '$1-$2');
  }, 11);

  mask('cep', (d) => d.replace(/(\d{5})(\d)/, '$1-$2'), 8);

  mask('cartao-numero', (d) => d.replace(/(\d{4})(?=\d)/g, '$1 '), 16);

  mask('cartao-validade', (d) => d.replace(/(\d{2})(\d)/, '$1/$2'), 4);

  mask('cartao-cvv', (d) => d, 4);

  // ---------- Busca de CEP (ViaCEP) ----------
  const buscarCepBtn = document.getElementById('buscar-cep');
  if (buscarCepBtn) {
    buscarCepBtn.addEventListener('click', async () => {
      const cepInput = document.getElementById('cep');
      const cep = onlyDigits(cepInput.value);

      if (cep.length !== 8) {
        setFieldState(cepInput, false, 'Digite um CEP válido.');
        cepInput.focus();
        return;
      }

      buscarCepBtn.classList.add('is-loading');
      buscarCepBtn.disabled = true;

      try {
        const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await res.json();

        if (data.erro) {
          setFieldState(cepInput, false, 'CEP não encontrado.');
        } else {
          setFieldState(cepInput, true);
          document.getElementById('endereco').value = data.logradouro || '';
          document.getElementById('bairro').value = data.bairro || '';
          document.getElementById('cidade').value = data.localidade || '';
          document.getElementById('estado').value = data.uf || '';
          document.getElementById('numero').focus();
        }
      } catch (err) {
        // Sem conexão ou serviço fora do ar: usuário preenche manualmente
      } finally {
        buscarCepBtn.classList.remove('is-loading');
        buscarCepBtn.disabled = false;
      }
    });
  }

  goToStep(1, { focus: false });
})();
