// Controla as etapas, a validação e os campos auxiliares da matrícula.

(() => {
  const form = document.querySelector('.matricula-form');
  if (!form) return;

  const onlyDigits = (value) => String(value).replace(/\D/g, '');

  function isValidCpf(value) {
    const cpf = onlyDigits(value);
    if (!/^\d{11}$/.test(cpf) || /^(\d)\1{10}$/.test(cpf)) return false;

    for (let position = 9; position < 11; position += 1) {
      let sum = 0;
      for (let index = 0; index < position; index += 1) {
        sum += Number(cpf[index]) * ((position + 1) - index);
      }
      const digit = (sum * 10) % 11 % 10;
      if (Number(cpf[position]) !== digit) return false;
    }

    return true;
  }

  function isValidBirthDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const minimumDate = new Date(
      today.getFullYear() - 120,
      today.getMonth(),
      today.getDate()
    );

    return date.getFullYear() === year
      && date.getMonth() === month - 1
      && date.getDate() === day
      && date <= today
      && date >= minimumDate;
  }

  function customMessageFor(input) {
    if (!input.value.trim()) return '';

    if (input.id === 'cpf' && !isValidCpf(input.value)) {
      return 'Digite um CPF válido.';
    }

    if (input.id === 'telefone') {
      const digits = onlyDigits(input.value);
      if (digits.length < 10 || digits.length > 11) {
        return 'Digite um telefone com DDD válido.';
      }
    }

    if (input.id === 'nascimento' && !isValidBirthDate(input.value)) {
      return 'Informe uma data de nascimento válida.';
    }

    if (input.id === 'cidade' && input.dataset.citySelected !== input.value) {
      return 'Selecione uma cidade válida da lista.';
    }

    if (input.id === 'confirmar-senha') {
      const senha = document.getElementById('password');
      if (senha?.value && input.value !== senha.value) {
        return 'As senhas não coincidem.';
      }
    }

    return '';
  }

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

  // Exibe uma etapa e sincroniza o progresso, o subtítulo e o foco.
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
        // Evita abrir o teclado virtual automaticamente em dispositivos móveis.
        firstField.focus({ preventScroll: true });
      }
    }

    const wrap = document.querySelector('.matricula-wrap');
    if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // Traduz os estados da validação nativa em mensagens objetivas.
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
    // Campos ocultos de outra forma de pagamento não participam da validação.
    const panel = input.closest('.payment-panel');
    if (panel && !panel.classList.contains('active')) return true;

    const customMessage = customMessageFor(input);
    input.setCustomValidity(customMessage);
    const valid = input.checkValidity();
    setFieldState(input, valid, customMessage);
    return valid;
  }

  // Valida ao sair do campo e revalida durante a correção de um erro.
  form.querySelectorAll('input, select').forEach((input) => {
    input.addEventListener('blur', () => validateField(input));
    input.addEventListener('input', () => {
      const field = input.closest('.field');
      if (field?.classList.contains('invalid')) validateField(input);

      if (input.id === 'password') {
        const confirmar = document.getElementById('confirmar-senha');
        if (confirmar?.value) validateField(confirmar);
      }
    });
    input.addEventListener('change', () => validateField(input));
  });

  function validateStep(n) {
    const step = steps.find((s) => Number(s.dataset.step) === n);
    if (!step) return true;

    let valid = true;

    step.querySelectorAll('input, select').forEach((input) => {
      if (!validateField(input)) valid = false;
    });

    // Confere as duas senhas porque essa regra não existe na validação nativa.
    if (n === 1) {
      const senha = document.getElementById('password');
      const confirmar = document.getElementById('confirmar-senha');
      if (senha.value && confirmar.value && senha.value !== confirmar.value) {
        setFieldState(confirmar, false, 'As senhas não coincidem.');
        valid = false;
      }
    }

    // Aplica feedback visual ao grupo de planos quando nenhum está selecionado.
    if (n === 2) {
      const cidade = document.getElementById('cidade');
      const estado = document.getElementById('estado');
      if (estado?.value && cidade?.disabled) {
        setFieldState(cidade, false, cidade.dataset.cityError || 'Aguarde o carregamento das cidades.');
        valid = false;
      }
    }

    if (n === 3) {
      const planWrap = step.querySelector('.plan-select');
      const checked = step.querySelector('input[name="plano"]:checked');
      planWrap.classList.toggle('invalid', !checked);
      if (!checked) valid = false;
    }

    // Direciona o usuário ao primeiro campo que precisa de correção.
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

  // O indicador permite retornar apenas às etapas já alcançadas.
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

  // Enter avança no fluxo e só envia o formulário na última etapa.
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (e.target.tagName === 'TEXTAREA') return;
    if (e.target.id === 'cidade') return;

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

  // Mantém o destaque visual sincronizado com o plano selecionado.
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

  // Alterna o painel de pagamento e atualiza o valor enviado ao PHP.
  const paymentTabs = Array.from(form.querySelectorAll('.payment-tab'));
  const paymentPanels = Array.from(form.querySelectorAll('.payment-panel'));
  const paymentMethod = document.getElementById('forma-pagamento');

  paymentTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.payment;
      if (paymentMethod) paymentMethod.value = target;

      paymentTabs.forEach((t) => t.classList.toggle('active', t === tab));
      paymentPanels.forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.paymentPanel === target);
      });
    });
  });

  // Formata campos numéricos sem alterar os valores tratados pelo PHP.
  function mask(id, formatter, maxDigits) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
      const digits = onlyDigits(el.value).slice(0, maxDigits);
      el.value = formatter(digits);
      if (el.closest('.field')?.classList.contains('invalid')) validateField(el);
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

  const ibgeApi = 'https://servicodados.ibge.gov.br/api/v1/localidades';
  const estadoInput = document.getElementById('estado');
  const cidadeInput = document.getElementById('cidade');
  const cidadeSugestoes = document.getElementById('cidade-sugestoes');
  let cidades = [];
  let buscaCidadesController;
  let estadoPendenteDoCep = '';
  let cidadePendenteDoCep = '';

  const normalizarBusca = (value) => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('pt-BR')
    .trim();

  function fecharSugestoesCidade() {
    if (!cidadeSugestoes || !cidadeInput) return;
    cidadeSugestoes.hidden = true;
    cidadeSugestoes.replaceChildren();
    cidadeInput.setAttribute('aria-expanded', 'false');
  }

  function limparCidade(placeholder, { disabled = true, error = '' } = {}) {
    if (!cidadeInput) return;
    cidades = [];
    cidadeInput.value = '';
    cidadeInput.dataset.citySelected = '';
    cidadeInput.dataset.cityError = error;
    cidadeInput.placeholder = placeholder;
    cidadeInput.disabled = disabled;
    fecharSugestoesCidade();
    setFieldState(cidadeInput, true);
  }

  function selecionarCidade(nome) {
    if (!cidadeInput) return;
    cidadeInput.value = nome;
    cidadeInput.dataset.citySelected = nome;
    cidadeInput.dataset.cityError = '';
    fecharSugestoesCidade();
    validateField(cidadeInput);
  }

  function mostrarSugestoesCidade(termo = '') {
    if (!cidadeInput || !cidadeSugestoes || cidadeInput.disabled) return;

    const busca = normalizarBusca(termo);
    const resultados = cidades
      .filter((nome) => normalizarBusca(nome).includes(busca))
      .slice(0, 30);

    cidadeSugestoes.replaceChildren();
    if (!resultados.length) {
      const vazio = document.createElement('div');
      vazio.className = 'city-suggestion-empty';
      vazio.textContent = 'Nenhuma cidade encontrada.';
      cidadeSugestoes.appendChild(vazio);
    } else {
      resultados.forEach((nome) => {
        const opcao = document.createElement('button');
        opcao.type = 'button';
        opcao.className = 'city-suggestion';
        opcao.setAttribute('role', 'option');
        opcao.textContent = nome;
        opcao.addEventListener('click', () => selecionarCidade(nome));
        cidadeSugestoes.appendChild(opcao);
      });
    }

    cidadeSugestoes.hidden = false;
    cidadeInput.setAttribute('aria-expanded', 'true');
  }

  async function carregarCidades(uf) {
    if (!cidadeInput) return;
    buscaCidadesController?.abort();
    buscaCidadesController = new AbortController();
    limparCidade('Carregando cidades...', { disabled: true });

    try {
      const resposta = await fetch(
        `${ibgeApi}/estados/${encodeURIComponent(uf)}/municipios?orderBy=nome`,
        { signal: buscaCidadesController.signal }
      );
      if (!resposta.ok) throw new Error('Falha ao carregar municípios.');

      const municipios = await resposta.json();
      cidades = municipios
        .map((municipio) => municipio.nome)
        .filter(Boolean)
        .sort((a, b) => a.localeCompare(b, 'pt-BR'));

      cidadeInput.disabled = false;
      cidadeInput.placeholder = 'Digite ou selecione a cidade';
      cidadeInput.dataset.cityError = '';

      if (cidadePendenteDoCep && cidades.includes(cidadePendenteDoCep)) {
        selecionarCidade(cidadePendenteDoCep);
      }
      cidadePendenteDoCep = '';
    } catch (erro) {
      if (erro.name === 'AbortError') return;
      limparCidade('Não foi possível carregar as cidades', {
        disabled: true,
        error: 'Não foi possível carregar as cidades.',
      });
      setFieldState(cidadeInput, false, cidadeInput.dataset.cityError);
    }
  }

  function preencherEstados(estados) {
    if (!estadoInput) return;
    estadoInput.replaceChildren();

    const inicial = document.createElement('option');
    inicial.value = '';
    inicial.textContent = 'Selecione o estado';
    estadoInput.appendChild(inicial);

    estados.forEach((estado) => {
      const opcao = document.createElement('option');
      opcao.value = estado.sigla;
      opcao.textContent = `${estado.nome} (${estado.sigla})`;
      estadoInput.appendChild(opcao);
    });

    estadoInput.disabled = false;
  }

  async function carregarEstados() {
    if (!estadoInput) return;

    try {
      const resposta = await fetch(`${ibgeApi}/estados?orderBy=nome`);
      if (!resposta.ok) throw new Error('Falha ao carregar estados.');

      const estados = await resposta.json();
      preencherEstados(estados);

      if (estadoPendenteDoCep) {
        estadoInput.value = estadoPendenteDoCep;
        estadoPendenteDoCep = '';
        estadoInput.dispatchEvent(new CustomEvent('change', { detail: { fromCep: true } }));
      }
    } catch (erro) {
      estadoInput.replaceChildren();
      const falha = document.createElement('option');
      falha.value = '';
      falha.textContent = 'Não foi possível carregar estados';
      estadoInput.appendChild(falha);
      estadoInput.disabled = true;
      setFieldState(estadoInput, false, 'Não foi possível carregar os estados.');
    }
  }

  function selecionarLocalidadeDoCep(uf, cidade) {
    if (!estadoInput || !uf) return;

    cidadePendenteDoCep = cidade || '';
    if (estadoInput.disabled) {
      estadoPendenteDoCep = uf;
      return;
    }

    estadoInput.value = uf;
    estadoInput.dispatchEvent(new CustomEvent('change', { detail: { fromCep: true } }));
  }

  if (estadoInput && cidadeInput) {
    estadoInput.addEventListener('change', (event) => {
      const uf = estadoInput.value;
      if (!event.detail?.fromCep) cidadePendenteDoCep = '';
      if (!uf) {
        buscaCidadesController?.abort();
        limparCidade('Selecione primeiro um estado');
        return;
      }
      carregarCidades(uf);
    });

    cidadeInput.addEventListener('focus', () => mostrarSugestoesCidade(cidadeInput.value));
    cidadeInput.addEventListener('input', () => {
      cidadeInput.dataset.citySelected = '';
      if (cidadeInput.closest('.field')?.classList.contains('invalid')) validateField(cidadeInput);
      mostrarSugestoesCidade(cidadeInput.value);
    });
    cidadeInput.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') fecharSugestoesCidade();
    });

    document.addEventListener('click', (event) => {
      if (!event.target.closest('.city-combobox')) fecharSugestoesCidade();
    });

    carregarEstados();
  }

  // Consulta o ViaCEP e preenche os campos de endereço disponíveis.
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
          selecionarLocalidadeDoCep(data.uf || '', data.localidade || '');
          document.getElementById('numero').focus();
        }
      } catch (err) {
        // Mantém os campos liberados para preenchimento manual se a consulta falhar.
      } finally {
        buscarCepBtn.classList.remove('is-loading');
        buscarCepBtn.disabled = false;
      }
    });
  }

  goToStep(1, { focus: false });
})();
