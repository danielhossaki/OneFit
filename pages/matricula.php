<?php
include '../config/conn.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Matrícula — ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <!-- link da fonte -->
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <!-- link do css -->
  <link rel="stylesheet" href="../assets/css/home.css">
  <link rel="stylesheet" href="../assets/css/login.css">
  <link rel="stylesheet" href="../assets/css/matricula.css">
  <!-- link das animações -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <!-- link do favicon -->
  <link rel="icon" href="../assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body class="login-body">

  <main class="login-page matricula-page">

    <!-- Painel visual (some em telas pequenas) -->
    <section class="login-visual" data-aos="fade-right">
      <video autoplay muted loop playsinline>
        <source src="../assets/img/videos/video-cadastro.mp4" type="video/mp4">
        Seu navegador não suporta vídeos.
      </video>
      <div class="login-visual-overlay"></div>

      <div class="login-visual-content">
        <a href="../pages/home.php" class="login-logo">ONE<span>FIT</span></a>

        <div class="login-visual-text">
          <span class="eyebrow">Comece agora</span>
          <h2>SUA PRIMEIRA<br>AULA É GRÁTIS</h2>
        </div>
      </div>
    </section>

    <!-- Painel de preenchimento -->
    <section class="login-form-panel">
      <div class="login-form-wrap matricula-wrap" data-aos="fade-right" data-aos-delay="250">

        <a href="../index.php" class="login-logo login-logo-mobile">ONE<span>FIT</span></a>

        <span class="tag">Junte-se à ONE FIT</span>
        <h1>Matrícula</h1>
        <p class="login-subtitle" id="step-subtitle">Preencha seus dados para começar a treinar com a gente.</p>

        <!-- Progresso -->
        <div class="matricula-progress">
          <div class="progress-bar"><span id="progress-fill"></span></div>
          <div class="progress-steps">
            <span class="progress-step active" data-step-label="1" role="button" tabindex="-1">
              <i>01</i>Dados
            </span>
            <span class="progress-step" data-step-label="2" role="button" tabindex="-1">
              <i>02</i>Endereço
            </span>
            <span class="progress-step" data-step-label="3" role="button" tabindex="-1">
              <i>03</i>Plano
            </span>
            <span class="progress-step" data-step-label="4" role="button" tabindex="-1">
              <i>04</i>Pagamento
            </span>
          </div>
        </div>

        <form class="login-form matricula-form" action="#" method="POST" novalidate>

          <!-- ETAPA 1 — Dados pessoais -->
          <fieldset class="form-step active" data-step="1">

            <div class="field">
              <label for="nome">Nome completo</label>
              <input type="text" id="nome" name="nome" placeholder="Seu nome" required>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" inputmode="numeric" maxlength="14" required>
              </div>
              <div class="field">
                <label for="nascimento">Data de nascimento</label>
                <input type="date" id="nascimento" name="nascimento" required>
              </div>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" inputmode="numeric" maxlength="15" required>
              </div>
              <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
              </div>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="password">Senha</label>
                <div class="password-wrap">
                  <input type="password" id="password" name="password" placeholder="Mínimo de 8 caracteres" minlength="8" required>
                  <button type="button" class="toggle-password" aria-label="Mostrar senha" aria-pressed="false" data-target="password">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z" />
                      <circle cx="12" cy="12" r="3.2" />
                    </svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 3l18 18" />
                      <path d="M10.6 5.2A10.7 10.7 0 0112 5c7 0 10.5 7 10.5 7a15.6 15.6 0 01-3.4 4.3M6.5 6.6C3.4 8.5 1.5 12 1.5 12s3.5 7 10.5 7c1.4 0 2.7-.28 3.85-.75" />
                      <path d="M9.5 9.6a3.2 3.2 0 004.4 4.5" />
                    </svg>
                  </button>
                </div>
              </div>
              <div class="field">
                <label for="confirmar-senha">Confirmar senha</label>
                <div class="password-wrap">
                  <input type="password" id="confirmar-senha" name="confirmar_senha" placeholder="Repita sua senha" minlength="8" required>
                  <button type="button" class="toggle-password" aria-label="Mostrar senha" aria-pressed="false" data-target="confirmar-senha">
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z" />
                      <circle cx="12" cy="12" r="3.2" />
                    </svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 3l18 18" />
                      <path d="M10.6 5.2A10.7 10.7 0 0112 5c7 0 10.5 7 10.5 7a15.6 15.6 0 01-3.4 4.3M6.5 6.6C3.4 8.5 1.5 12 1.5 12s3.5 7 10.5 7c1.4 0 2.7-.28 3.85-.75" />
                      <path d="M9.5 9.6a3.2 3.2 0 004.4 4.5" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <div class="step-actions">
              <span></span>
              <button type="button" class="btn btn-gold" data-next>Continuar</button>
            </div>
          </fieldset>

          <!-- ETAPA 2 — Endereço -->
          <fieldset class="form-step" data-step="2">

            <div class="field-row cep-row">
              <div class="field">
                <label for="cep">CEP</label>
                <input type="text" id="cep" name="cep" placeholder="00000-000" inputmode="numeric" maxlength="9" required>
              </div>
              <button type="button" class="btn btn-outline btn-cep" id="buscar-cep">Buscar</button>
            </div>

            <div class="field">
              <label for="endereco">Endereço</label>
              <input type="text" id="endereco" name="endereco" placeholder="Rua, avenida..." required>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="numero">Número</label>
                <input type="text" id="numero" name="numero" placeholder="Nº" required>
              </div>
              <div class="field">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento" placeholder="Apto, bloco... (opcional)">
              </div>
            </div>

            <div class="field">
              <label for="bairro">Bairro</label>
              <input type="text" id="bairro" name="bairro" required>
            </div>

            <div class="field-row">
              <div class="field">
                <label for="cidade">Cidade</label>
                <input type="text" id="cidade" name="cidade" required>
              </div>
              <div class="field">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" required>
                  <option value="">UF</option>
                  <option>AC</option><option>AL</option><option>AP</option><option>AM</option>
                  <option>BA</option><option>CE</option><option>DF</option><option>ES</option>
                  <option>GO</option><option>MA</option><option>MT</option><option>MS</option>
                  <option>MG</option><option>PA</option><option>PB</option><option>PR</option>
                  <option>PE</option><option>PI</option><option>RJ</option><option>RN</option>
                  <option>RS</option><option>RO</option><option>RR</option><option>SC</option>
                  <option>SP</option><option>SE</option><option>TO</option>
                </select>
              </div>
            </div>

            <div class="step-actions">
              <button type="button" class="btn btn-outline" data-prev>Voltar</button>
              <button type="button" class="btn btn-gold" data-next>Continuar</button>
            </div>
          </fieldset>

          <!-- ETAPA 3 — Plano -->
          <fieldset class="form-step" data-step="3">

            <div class="plan-select">

              <label class="plan-option">
                <input type="radio" name="plano" value="iniciante" required>
                <span class="plan-option-body">
                  <span class="plan-option-head">
                    <span class="plan-option-name">Iniciante</span>
                    <span class="plan-option-price">R$99<i>/mês</i></span>
                  </span>
                  <span class="plan-option-desc">Acesso à musculação · 2 modalidades por semana</span>
                </span>
              </label>

              <label class="plan-option featured">
                <input type="radio" name="plano" value="completo" checked required>
                <span class="badge">Mais escolhido</span>
                <span class="plan-option-body">
                  <span class="plan-option-head">
                    <span class="plan-option-name">Completo</span>
                    <span class="plan-option-price">R$179<i>/mês</i></span>
                  </span>
                  <span class="plan-option-desc">Todas as modalidades · treino personalizado · app de treino</span>
                </span>
              </label>

              <label class="plan-option">
                <input type="radio" name="plano" value="elite" required>
                <span class="plan-option-body">
                  <span class="plan-option-head">
                    <span class="plan-option-name">Elite</span>
                    <span class="plan-option-price">R$289<i>/mês</i></span>
                  </span>
                  <span class="plan-option-desc">Personal training · nutricionista · armário fixo</span>
                </span>
              </label>

            </div>

            <div class="step-actions">
              <button type="button" class="btn btn-outline" data-prev>Voltar</button>
              <button type="button" class="btn btn-gold" data-next>Continuar</button>
            </div>
          </fieldset>

          <!-- ETAPA 4 — Pagamento -->
          <fieldset class="form-step" data-step="4">

            <div class="payment-tabs" role="tablist">
              <button type="button" class="payment-tab active" data-payment="cartao">Cartão de crédito</button>
              <button type="button" class="payment-tab" data-payment="pix">Pix</button>
              <button type="button" class="payment-tab" data-payment="boleto">Boleto</button>
            </div>

            <div class="payment-panel active" data-payment-panel="cartao">

              <div class="field">
                <label for="cartao-numero">Número do cartão</label>
                <input type="text" id="cartao-numero" name="cartao_numero" placeholder="0000 0000 0000 0000" inputmode="numeric" maxlength="19">
              </div>

              <div class="field">
                <label for="cartao-nome">Nome impresso no cartão</label>
                <input type="text" id="cartao-nome" name="cartao_nome" placeholder="Como está no cartão">
              </div>

              <div class="field-row">
                <div class="field">
                  <label for="cartao-validade">Validade</label>
                  <input type="text" id="cartao-validade" name="cartao_validade" placeholder="MM/AA" inputmode="numeric" maxlength="5">
                </div>
                <div class="field">
                  <label for="cartao-cvv">CVV</label>
                  <input type="text" id="cartao-cvv" name="cartao_cvv" placeholder="000" inputmode="numeric" maxlength="4">
                </div>
              </div>
            </div>

            <div class="payment-panel" data-payment-panel="pix">
              <p class="payment-note">O código Pix é gerado após a confirmação da matrícula e enviado para o seu e-mail, com validade de 30 minutos.</p>
            </div>

            <div class="payment-panel" data-payment-panel="boleto">
              <p class="payment-note">O boleto é gerado após a confirmação da matrícula, com vencimento em até 3 dias úteis.</p>
            </div>

            <label class="checkbox checkbox-terms">
              <input type="checkbox" name="termos" required>
              <span>Li e aceito os <a href="#">termos de uso</a> e a <a href="#">política de privacidade</a></span>
            </label>

            <div class="step-actions">
              <button type="button" class="btn btn-outline" data-prev>Voltar</button>
              <button type="submit" class="btn btn-gold">Confirmar matrícula</button>
            </div>
          </fieldset>

        </form>

        <p class="login-footer-text">Já tem uma conta? <a href="login.php">Entrar</a></p>

      </div>
    </section>

  </main>

  <!-- Link para JavaScript -->
  <script src="../assets/js/login.js"></script>
  <script src="../assets/js/matricula.js"></script>

  <!-- Link para animações AOS JS -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- Animacão do AOS JS -->
  <script>
    AOS.init({
      duration: 800,
      once: true,
      offset: 100
    });
  </script>

</body>

</html>
