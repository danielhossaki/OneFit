Arquivos completos da atualização do perfil do aluno
==================================================

As alterações já estão aplicadas no projeto. Para copiá-las para outra instalação, substitua ou crie cada arquivo no caminho indicado, relativo à raiz `OneFit`. O documento `entrega-perfil-aluno.md` reúne o conteúdo completo de cada arquivo, incluindo CSS, JavaScript, modal e backend.

Arquivos e responsabilidades:

- `pages/dashboard/components/section-configuracoes.php`: direciona somente o aluno ao novo componente; mantém os demais perfis e preferências existentes.
- `pages/dashboard/components/student-profile.php`: resumo, foto, cards e botão de edição.
- `pages/dashboard/components/modal-student-profile.php`: formulário completo de edição com os campos pessoais existentes, altura, peso e foto.
- `assets/css/student-profile.css`: estilo escuro e dourado, layout responsivo e rolagem do modal, limitado aos componentes do aluno.
- `assets/js/student-profile.js`: cálculo instantâneo do IMC, validação, prévia e envio da foto.
- `pages/dashboard/includes/aluno-profile.php`: normalização, cálculo PHP, classificação e validação do upload.
- `pages/dashboard/actions/update-profile.php`: grava os dados ou apenas a foto do aluno autenticado usando a conexão, sessão, CSRF e UPDATE existentes.
- `pages/dashboard/actions/_shared.php`: após uma atualização do aluno, retorna à seção Meu perfil; mantém os outros redirecionamentos.

Banco de dados: não executar migração. A consulta ao banco confirmou `usuarios.altura DECIMAL(5,2)`, `usuarios.peso DECIMAL(5,2)` e `usuarios.foto VARCHAR(255)`. Altura e peso são normalizados para duas casas decimais, em conformidade com as colunas. IMC e classificação são calculados no PHP e não recebem colunas duplicadas.

Fotos: JPG/JPEG, PNG e WEBP, até 3 MB e 40 megapixels. O backend verifica o upload recebido, a extensão, o MIME e as dimensões antes de usar o gravador existente. O arquivo fica em `assets/img/uploads/perfil/`, com nome aleatório; o caminho é persistido em `usuarios.foto`. A nova foto substitui a referência anterior na conta. Os arquivos antigos permanecem no diretório. Não é necessário alterar as configurações de upload já existentes no projeto.

Salvar envia um POST e redireciona para Meu perfil, recarregando os dados do banco com o modal fechado. Campos vazios geram IMC “Não informado”; valores inválidos são recusados pelo backend. A classificação usa as faixas para adultos solicitadas e o IMC aparece com uma casa decimal.

Validação executada:

```text
php tests/student-profile.php --schema
node tests/student-profile.js
node --check assets/js/student-profile.js
php -l (em cada arquivo PHP alterado ou criado)
git diff --check
```

Os testes verificam limites de classificação, vírgula/ponto, valores vazios e inválidos, recálculo por eventos de input, rejeição de arquivo inválido, escape de HTML, componentes únicos e isolamento dos quatro perfis. A consulta de estrutura do banco é somente leitura. Não foi realizado teste de gravação/upload por uma sessão autenticada no navegador, nem inspeção visual em desktop/celular. O anexo recebido continha apenas texto, sem as imagens citadas; o layout segue o esquema textual e os estilos existentes.


## pages/dashboard/components/section-configuracoes.php

```php
<?php if ($perfilLogado === 'aluno'): ?>
<?php require __DIR__ . '/student-profile.php'; ?>
<?php else: ?>
<section class="bo-content-section" id="boProfileSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-circle"></i> Conta</span><h1>Meu perfil</h1><p>Consulte e atualize os dados da sua conta.</p></div></div>
    <div class="bo-settings-card bo-profile-settings">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person"></i></span><div><h2 id="boProfileName"><?php echo htmlspecialchars($usuarioDashboard['nome'], ENT_QUOTES, 'UTF-8'); ?></h2><p id="boProfileId">ID: #<?php echo str_pad((string) $_SESSION['id_usuario'], 4, '0', STR_PAD_LEFT); ?></p><p id="boProfileEmail"><?php echo htmlspecialchars($usuarioDashboard['email'] ?: 'Dados da conta ONE FIT', ENT_QUOTES, 'UTF-8'); ?></p><?php if ($usuarioDashboard['genero']): ?><p id="boProfileGender">Gênero: <?php echo htmlspecialchars(ucfirst($usuarioDashboard['genero']), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?></div></div>
        <div class="bo-actions">
            <button class="btn-bo-outline" type="button" data-bs-toggle="modal" data-bs-target="#modalSenhaAlterar"><i class="bi bi-key"></i> Alterar senha</button>
            <button class="btn-bo-gold" type="button" data-bs-toggle="modal" data-bs-target="#modalPerfilEditar"><i class="bi bi-pencil-square"></i> Editar perfil</button>
        </div>
    </div>
    <?php bo_modal_perfil_editar($usuarioDashboard, (int) $_SESSION['id_usuario']); ?>
    <?php bo_modal_senha_alterar(); ?>
</section>

<?php endif; ?>

<section class="bo-content-section" id="boSettingsSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-gear"></i> Preferências</span><h1>Configurações</h1><p>Personalize sua experiência e gerencie a segurança da conta.</p></div></div>
    <div class="bo-settings-stack">
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-circle-half"></i></span><div><h2>Tema da interface</h2><p>Escolha como o ONE FIT será exibido neste dispositivo.</p></div></div>
        <div class="bo-theme-choices" role="group" aria-label="Escolher tema">
            <button type="button" class="bo-theme-choice" data-bo-theme="light" aria-pressed="false"><i class="bi bi-sun"></i><span><strong>Tema claro</strong><small>Interface iluminada</small></span></button>
            <button type="button" class="bo-theme-choice" data-bo-theme="dark" aria-pressed="false"><i class="bi bi-moon-stars"></i><span><strong>Tema escuro</strong><small>Interface com menos brilho</small></span></button>
            <button type="button" class="bo-theme-choice" data-bo-theme="system" aria-pressed="false"><i class="bi bi-display"></i><span><strong>Usar tema do sistema</strong><small>Acompanha este dispositivo</small></span></button>
        </div>
    </div>

    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-bell"></i></span><div><h2>Notificações</h2><p>Escolha individualmente quais comunicações deseja receber.</p></div></div>
        <div class="bo-preference-list">
            <?php
            $opcoesNotificacao = [
                'lembretes_treino' => ['Lembretes de treino', 'bi-activity'],
                'avisos_agendamentos' => ['Avisos de agendamentos', 'bi-calendar-check'],
                'atualizacoes_compras' => ['Atualizações de compras', 'bi-bag-check'],
                'ofertas_novidades' => ['Ofertas e novidades', 'bi-stars'],
                'notificacoes_email' => ['Notificações por e-mail', 'bi-envelope'],
            ];
            foreach ($opcoesNotificacao as $chave => [$rotulo, $icone]):
                $idControle = 'preferencia-' . str_replace('_', '-', $chave);
            ?>
                <div class="bo-preference-row">
                    <label for="<?php echo $idControle; ?>"><i class="bi <?php echo $icone; ?>"></i><span><?php echo $rotulo; ?></span></label>
                    <input class="bo-toggle-input" type="checkbox" role="switch"
                        id="<?php echo $idControle; ?>" data-bo-preference="<?php echo $chave; ?>"
                        <?php echo !empty($preferenciasDashboard[$chave]) ? 'checked' : ''; ?>>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$preferenciasDisponiveis): ?>
            <p class="bo-settings-hint"><i class="bi bi-info-circle"></i> A migração de preferências deve ser aplicada para sincronizar estes dados com a conta.</p>
        <?php endif; ?>
    </div>

    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-shield-lock"></i></span><div><h2>Privacidade e segurança</h2><p>Proteja suas credenciais de acesso ao ONE FIT.</p></div></div>
        <div class="bo-settings-action-row">
            <div><strong>Senha da conta</strong><span>Use uma senha exclusiva com pelo menos 8 caracteres.</span></div>
            <button class="btn-bo-outline" type="button" data-bs-toggle="modal" data-bs-target="#modalSenhaAlterar"><i class="bi bi-key"></i> Alterar senha</button>
        </div>
    </div>

    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person-gear"></i></span><div><h2>Conta</h2><p>Gerencie sua sessão atual.</p></div></div>
        <div class="bo-settings-action-row">
            <div><strong>Sair da conta</strong><span>Encerre sua sessão neste dispositivo.</span></div>
            <a class="logout-button btn-bo-outline bo-logout-button" href="<?php echo BASE_URL; ?>config/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    </div>
</section>
```


## pages/dashboard/components/student-profile.php

```php
<?php
require_once __DIR__ . '/../includes/aluno-profile.php';
require_once __DIR__ . '/modal-student-profile.php';
$imcAluno = bo_aluno_imc($usuarioDashboard['altura'], $usuarioDashboard['peso']);
$fotoAluno = bo_aluno_foto_url($usuarioDashboard['foto']);
$resumoAluno = [
    ['Gênero', ucfirst($usuarioDashboard['genero'] ?: 'Não informado'), 'bi-person'],
    ['Altura', bo_aluno_medida($usuarioDashboard['altura'], 3) ? number_format((float) $usuarioDashboard['altura'], 2, ',', '.') . ' m' : 'Não informado', 'bi-rulers'],
    ['Peso', bo_aluno_medida($usuarioDashboard['peso'], 500) ? number_format((float) $usuarioDashboard['peso'], 1, ',', '.') . ' kg' : 'Não informado', 'bi-speedometer2'],
    ['IMC', $imcAluno['valor'] === null ? 'Não informado' : number_format($imcAluno['valor'], 1, ',', '.') . ' · ' . $imcAluno['classe'], 'bi-heart-pulse'],
];
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/student-profile.css">
<script defer src="<?php echo BASE_URL; ?>assets/js/student-profile.js"></script>
<section class="bo-content-section bo-student-profile" id="boProfileSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-circle"></i> Conta</span><h1>Meu perfil</h1><p>Consulte e atualize os dados da sua conta.</p></div></div>
    <div class="bo-settings-card bo-student-summary">
        <div class="bo-student-photo-column">
            <div class="bo-student-photo">
                <i class="bi bi-person" aria-hidden="true"></i>
                <?php if ($fotoAluno): ?><img src="<?php echo bo_val($fotoAluno); ?>" alt="Foto de <?php echo bo_val($usuarioDashboard['nome']); ?>" data-student-photo><?php endif; ?>
            </div>
            <form action="<?php echo bo_action_url('update-profile.php'); ?>" method="post" enctype="multipart/form-data" id="studentPhotoForm">
                <?php echo bo_csrf_field(); ?>
                <input type="hidden" name="acao" value="foto">
                <input type="file" name="foto_arquivo" id="studentQuickPhoto" accept="image/jpeg,image/png,image/webp" hidden>
                <button type="button" class="btn-bo-outline" id="studentChoosePhoto"><i class="bi bi-camera"></i> Alterar foto</button>
                <small>JPG, PNG ou WEBP · Até 3 MB</small>
                <span id="studentPhotoStatus" role="status"></span>
            </form>
        </div>
        <div class="bo-student-details">
            <h2 id="boProfileName"><?php echo bo_val($usuarioDashboard['nome']); ?></h2>
            <p id="boProfileEmail"><?php echo bo_val($usuarioDashboard['email']); ?></p>
            <p id="boProfileId">ID: #<?php echo str_pad((string) $_SESSION['id_usuario'], 4, '0', STR_PAD_LEFT); ?></p>
            <div class="bo-student-metrics">
                <?php foreach ($resumoAluno as [$label, $value, $icon]): ?>
                    <div class="bo-student-metric"><span><i class="bi <?php echo $icon; ?>"></i> <?php echo $label; ?></span><strong><?php echo bo_val($value); ?></strong></div>
                <?php endforeach; ?>
            </div>
            <div class="bo-actions bo-student-actions">
                <button class="btn-bo-gold" type="button" data-bs-toggle="modal" data-bs-target="#modalPerfilEditar"><i class="bi bi-pencil-square"></i> EDITAR PERFIL</button>
            </div>
        </div>
    </div>
    <?php bo_modal_aluno_editar($usuarioDashboard, (int) $_SESSION['id_usuario']); ?>
    <?php bo_modal_senha_alterar(); ?>
</section>
```


## pages/dashboard/components/modal-student-profile.php

```php
<?php
function bo_modal_aluno_editar(array $u, int $idUsuario): void
{
    ?>
    <div class="modal fade bo-modal bo-student-modal" id="modalPerfilEditar" tabindex="-1" aria-hidden="true" aria-labelledby="studentModalTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="studentModalTitle">Editar perfil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form method="POST" action="<?php echo bo_action_url('update-profile.php'); ?>" enctype="multipart/form-data">
                    <div class="modal-body row g-3">
                        <?php echo bo_csrf_field(); ?>
                        
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-id">ID do usuário</label>
                            <input id="student-id" type="text" class="form-control" value="#<?php echo str_pad((string) $idUsuario, 4, '0', STR_PAD_LEFT); ?>" readonly>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-documento">Documento (CPF)</label>
                            <input id="student-documento" type="text" class="form-control" name="documento" value="<?php echo bo_val($u['documento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-nome">Nome</label>
                            <input id="student-nome" type="text" class="form-control" name="nome" value="<?php echo bo_val($u['nome'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-email">E-mail</label>
                            <input id="student-email" type="email" class="form-control" name="email" value="<?php echo bo_val($u['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-telefone">Telefone/Celular</label>
                            <input id="student-telefone" type="text" class="form-control" name="telefone" value="<?php echo bo_val($u['telefone'] ?? ''); ?>" placeholder="DDD + número" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-nacionalidade">Nacionalidade</label>
                            <input id="student-nacionalidade" type="text" class="form-control" name="nacionalidade" value="<?php echo bo_val($u['nacionalidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-nascimento">Data de nascimento</label>
                            <input id="student-nascimento" type="date" class="form-control" name="nascimento" value="<?php echo bo_val($u['nascimento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-genero">Gênero</label>
                            <select id="student-genero" class="form-select" name="genero" required>
                                <option value="masculino" <?php echo ($u['genero'] ?? '') === 'masculino' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="feminino" <?php echo ($u['genero'] ?? '') === 'feminino' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="outro" <?php echo ($u['genero'] ?? '') === 'outro' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-estado">Estado (UF)</label>
                            <input id="student-estado" type="text" class="form-control" name="estado" maxlength="2" value="<?php echo bo_val($u['estado'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-endereco">Endereço</label>
                            <input id="student-endereco" type="text" class="form-control" name="endereco" value="<?php echo bo_val($u['endereco'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="student-cidade">Cidade</label>
                            <input id="student-cidade" type="text" class="form-control" name="cidade" value="<?php echo bo_val($u['cidade'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-altura">Altura (m)</label>
                            <input id="student-altura" type="text" inputmode="decimal" maxlength="6" class="form-control" name="altura" value="<?php echo bo_val($u['altura'] ?? ''); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="student-peso">Peso (kg)</label>
                            <input id="student-peso" type="text" inputmode="decimal" maxlength="7" class="form-control" name="peso" value="<?php echo bo_val($u['peso'] ?? ''); ?>">
                        </div>
                        <div class="col-12"><div class="bo-student-imc" aria-live="polite"><span>IMC calculado (adultos)</span><strong data-student-imc>Não informado</strong><span data-student-class></span></div></div>
                        <div class="col-12">
                            <label class="form-label" for="studentModalPhoto">Foto</label>
                            <input type="file" class="form-control" id="studentModalPhoto" name="foto_arquivo" accept="image/png,image/jpeg,image/webp">
                            <small>JPG, PNG ou WEBP · Até 3 MB</small>
                            <img class="bo-student-preview" data-student-preview alt="Prévia da nova foto" hidden>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn-bo-gold">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
```


## assets/css/student-profile.css

```css
/* Escopo exclusivo do resumo e modal do aluno. */
.bo-student-avatar { position: relative; overflow: hidden; padding: 0; }
.bo-student-avatar img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
.bo-student-profile, .bo-student-modal {
    --surface: #1e1a15;
    --surface-2: #241f18;
    --border: rgba(243, 237, 226, .12);
    --text: #f3ede2;
    --text-muted: #b0a896;
    --gold: #d4af37;
    color: var(--text);
}
.bo-student-summary { display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 36px; padding: 32px; background: var(--surface); }
.bo-student-photo { position: relative; width: 100%; aspect-ratio: 1; display: grid; place-items: center; overflow: hidden; border: 1px solid var(--border); border-radius: 18px; background: var(--surface-2); color: var(--gold); font-size: 76px; }
.bo-student-photo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.bo-student-photo-column form { display: grid; gap: 12px; margin-top: 16px; }
.bo-student-photo-column small, .bo-student-modal small { color: var(--text-muted); font-size: 12px; }
.bo-student-details { min-width: 0; }
.bo-student-details h2 { margin: 0 0 6px; font-size: 26px; font-weight: 800; overflow-wrap: anywhere; }
.bo-student-details p { color: var(--text-muted); margin-bottom: 6px; overflow-wrap: anywhere; }
.bo-student-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 24px; }
.bo-student-metric, .bo-student-imc { border: 1px solid var(--border); background: var(--surface-2); border-radius: 12px; padding: 16px; display: grid; gap: 8px; }
.bo-student-metric span, .bo-student-imc span { color: var(--text-muted); font-size: 13px; }
.bo-student-metric i { color: var(--gold); margin-right: 6px; }
.bo-student-metric strong { font-size: 16px; overflow-wrap: anywhere; }
.bo-student-actions { justify-content: flex-end; flex-wrap: wrap; margin-top: 24px; }
.bo-student-modal .modal-content { background: var(--surface); color: var(--text); max-height: calc(100dvh - 32px); }
.bo-student-modal form { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
.bo-student-modal .modal-body { overflow-y: auto; margin: 0; padding: 8px 12px 24px; }
.bo-student-modal .modal-header, .bo-student-modal .modal-footer { flex-shrink: 0; border-color: var(--border); }
.bo-student-modal .form-control, .bo-student-modal .form-select { min-width: 0; }
.bo-student-modal .form-control[readonly] { opacity: .7; }
html[data-theme] .bo-student-modal .btn-close { filter: invert(1) grayscale(1) brightness(1.6); }
.bo-student-imc strong { font-size: 24px; color: var(--gold); }
.bo-student-preview { display: block; width: 96px; height: 96px; object-fit: cover; border-radius: 12px; margin-top: 12px; }
.bo-student-preview[hidden] { display: none; }
@media (max-width: 575.98px) {
    .bo-student-summary { grid-template-columns: minmax(0, 1fr); padding: 20px; gap: 24px; }
    .bo-student-photo-column { width: 180px; justify-self: center; text-align: center; }
    .bo-student-metrics { grid-template-columns: minmax(0, 1fr); }
    .bo-student-actions > button { width: 100%; }
    .bo-student-modal .modal-content { max-height: calc(100dvh - 16px); }
}
```


## assets/js/student-profile.js

```js
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
```


## pages/dashboard/includes/aluno-profile.php

```php
<?php
/** Medidas e IMC derivados dos campos existentes em usuarios. */
function bo_aluno_medida($valor, float $maximo): ?float
{
    $texto = str_replace(',', '.', trim((string) $valor));
    if (!preg_match('/^\d+(?:\.\d+)?$/D', $texto)) return null;
    $numero = round((float) $texto, 2); // Mesma precisão DECIMAL(5,2) do banco.
    return is_finite($numero) && $numero > 0 && $numero <= $maximo ? $numero : null;
}

function bo_aluno_imc($altura, $peso): array
{
    $altura = bo_aluno_medida($altura, 3);
    $peso = bo_aluno_medida($peso, 500);
    if ($altura === null || $peso === null || $altura * $altura == 0) return ['valor' => null, 'classe' => 'Não informado'];
    $imc = $peso / ($altura * $altura);
    if (!is_finite($imc)) return ['valor' => null, 'classe' => 'Não informado'];
    $classe = match (true) {
        $imc < 18.5 => 'Abaixo do peso',
        $imc < 25 => 'Peso adequado',
        $imc < 30 => 'Sobrepeso',
        $imc < 35 => 'Obesidade grau I',
        $imc < 40 => 'Obesidade grau II',
        default => 'Obesidade grau III',
    };
    return ['valor' => $imc, 'classe' => $classe];
}

function bo_aluno_foto_url(?string $foto): string
{
    $foto = trim($foto ?? '');
    if (str_starts_with($foto, BASE_URL . 'assets/img/uploads/perfil/')) return $foto;
    return filter_var($foto, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($foto, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $foto : '';
}

/** Valida conteúdo, extensão e tamanho antes de usar o upload existente. */
function bo_aluno_upload(): ?string
{
    $arquivo = $_FILES['foto_arquivo'] ?? null;
    if (!$arquivo || ($arquivo['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
    if (!is_array($arquivo) || !isset($arquivo['error'], $arquivo['tmp_name'], $arquivo['name'], $arquivo['size']) ||
        !is_int($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK ||
        !is_string($arquivo['tmp_name']) || !is_string($arquivo['name']) || !is_uploaded_file($arquivo['tmp_name'])) {
        throw new RuntimeException('Não foi possível receber a foto. Selecione o arquivo novamente.');
    }
    $tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $info = @getimagesize($arquivo['tmp_name']);
    if ($arquivo['size'] <= 0 || $arquivo['size'] > 3 * 1024 * 1024 || !$info ||
        !isset($tipos[$ext]) || $tipos[$ext] !== mime_content_type($arquivo['tmp_name']) ||
        $tipos[$ext] !== ($info['mime'] ?? '') || $info[0] * $info[1] > 40000000) {
        throw new RuntimeException('Envie uma imagem JPG, PNG ou WEBP válida, de até 3 MB e 40 megapixels.');
    }
    $foto = bo_processar_upload_imagem('foto_arquivo', 'perfil');
    if ($foto === null) throw new RuntimeException('Não foi possível salvar a foto. Tente novamente.');
    return $foto;
}
```


## pages/dashboard/actions/update-profile.php

```php
<?php
/**
 * actions/update-profile.php
 * Handler da tela "Meu perfil" (todos os perfis): recebe o form real
 * postado pelo modal de includes/admin-forms.php (bo_modal_perfil_editar),
 * valida e grava em usuarios. Foto aceita upload de arquivo OU URL — o
 * upload, quando enviado, tem prioridade sobre a URL digitada.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$isAluno = ($_SESSION['tipo_usuario'] ?? '') === 'aluno';
if ($isAluno) {
    require_once __DIR__ . '/../includes/aluno-profile.php';
    $idAluno = (int) $_SESSION['id_usuario'];
    $consultaFoto = $conn->prepare('SELECT foto FROM usuarios WHERE id_usuario = ?');
    $consultaFoto->bind_param('i', $idAluno);
    $consultaFoto->execute();
    $perfilAtual = $consultaFoto->get_result()->fetch_assoc();
    $consultaFoto->close();
    if (!$perfilAtual) { bo_flash('error', 'Conta não encontrada.'); bo_redirect_perfil(); }
    if (bo_str('acao') === 'foto') {
        try {
            $novaFoto = bo_aluno_upload();
            if ($novaFoto === null) throw new RuntimeException('Selecione uma foto.');
            $salvarFoto = $conn->prepare('UPDATE usuarios SET foto = ? WHERE id_usuario = ?');
            $salvarFoto->bind_param('si', $novaFoto, $idAluno);
            $salvarFoto->execute();
            $salvarFoto->close();
            bo_flash('success', 'Foto atualizada com sucesso!');
        } catch (RuntimeException $erro) {
            bo_flash('error', $erro instanceof mysqli_sql_exception ? 'Não foi possível salvar a foto.' : $erro->getMessage());
        }
        bo_redirect_perfil();
    }
}

$nome = bo_str('nome');
$cpf = preg_replace('/\D/', '', bo_str('documento'));
$email = bo_str('email');
$celular = preg_replace('/\D/', '', bo_str('telefone'));
$nacionalidade = bo_str('nacionalidade');
$nascimento = bo_str('nascimento');
$genero = strtolower(bo_str('genero'));
$endereco = bo_str('endereco');
$cidade = bo_str('cidade');
$estado = strtoupper(bo_str('estado'));
$alturaStr = bo_str('altura');
$pesoStr = bo_str('peso');
$fotoAtual = bo_str('foto_atual');

$altura = $alturaStr === '' ? null : filter_var($alturaStr, FILTER_VALIDATE_FLOAT);
$peso = $pesoStr === '' ? null : filter_var($pesoStr, FILTER_VALIDATE_FLOAT);
if ($isAluno) {
    $altura = $alturaStr === '' ? null : (bo_aluno_medida($alturaStr, 3) ?? false);
    $peso = $pesoStr === '' ? null : (bo_aluno_medida($pesoStr, 500) ?? false);
    $fotoAtual = $perfilAtual['foto']; // Nunca confiar no caminho enviado pelo navegador.
}

if (!$nome || !$cpf || !$email || !$celular || !$nacionalidade || !$nascimento || !$genero || !$endereco || !$cidade || !$estado) {
    bo_flash('error', 'Preencha todos os campos obrigatórios.');
    bo_redirect_perfil();
}
if (strlen($cpf) !== 11) {
    bo_flash('error', 'Informe um CPF com 11 números.');
    bo_redirect_perfil();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bo_flash('error', 'Informe um e-mail válido.');
    bo_redirect_perfil();
}
if (!bo_valida_celular($celular)) {
    bo_flash('error', 'Informe um celular/telefone válido, com DDD (10 ou 11 números).');
    bo_redirect_perfil();
}
if (!in_array($genero, ['masculino', 'feminino', 'outro'], true)) {
    bo_flash('error', 'Selecione um gênero válido.');
    bo_redirect_perfil();
}
if (!preg_match('/^[A-Z]{2}$/', $estado)) {
    bo_flash('error', 'Informe o estado usando uma UF válida.');
    bo_redirect_perfil();
}

$dataNascimento = DateTime::createFromFormat('!Y-m-d', $nascimento);
$dataMinima = (new DateTime('today'))->modify('-12 years');
if (!$dataNascimento || $dataNascimento->format('Y-m-d') !== $nascimento || $dataNascimento > $dataMinima) {
    bo_flash('error', 'Para manter seu perfil na ONE FIT, você precisa ter pelo menos 12 anos.');
    bo_redirect_perfil();
}
if (($altura !== null && ($altura === false || $altura <= 0 || $altura > 3)) ||
    ($peso !== null && ($peso === false || $peso <= 0 || $peso > 500))) {
    bo_flash('error', 'Confira os valores de altura e peso.');
    bo_redirect_perfil();
}


$cidadeEstado = $cidade . '/' . $estado;
$idUsuario = (int) $_SESSION['id_usuario'];

$check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE (cpf = ? OR email = ?) AND id_usuario <> ? LIMIT 1');
$check->bind_param('ssi', $cpf, $email, $idUsuario);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    $check->close();
    bo_flash('error', 'O CPF ou e-mail informado já pertence a outra conta.');
    bo_redirect_perfil();
}
$check->close();

try {
    $fotoUpload = $isAluno ? bo_aluno_upload() : bo_processar_upload_imagem('foto_arquivo', 'perfil');
} catch (RuntimeException $erro) {
    bo_flash('error', $erro->getMessage());
    bo_redirect_perfil();
}
$foto = $fotoUpload ?? $fotoAtual;
if ($isAluno) $imcCalculado = bo_aluno_imc($altura, $peso); // Derivado, não persistido.

$stmt = $conn->prepare(
    'UPDATE usuarios SET nome = ?, nacionalidade = ?, data_nascimento = ?, genero = ?, cpf = ?,
     endereco = ?, cidade_estado = ?, email = ?, celular = ?, altura = ?, peso = ?, foto = ?
     WHERE id_usuario = ?'
);
$stmt->bind_param(
    'sssssssssddsi',
    $nome,
    $nacionalidade,
    $nascimento,
    $genero,
    $cpf,
    $endereco,
    $cidadeEstado,
    $email,
    $celular,
    $altura,
    $peso,
    $foto,
    $idUsuario
);
try {
    $stmt->execute();
} catch (mysqli_sql_exception $erro) {
    if (!$isAluno) throw $erro;
    bo_flash('error', 'Não foi possível atualizar o perfil. Confira os dados e tente novamente.');
    bo_redirect_perfil();
}
$stmt->close();

$_SESSION['nome'] = $nome;
$_SESSION['email'] = $email;
$_SESSION['genero'] = $genero;

bo_flash('success', 'Perfil atualizado com sucesso!');
bo_redirect_perfil();
```


## pages/dashboard/actions/_shared.php

```php
<?php
/**
 * actions/_shared.php
 * Bootstrap comum aos handlers desta pasta (perfil e senha do próprio
 * usuário logado — qualquer perfil, não só admin). Mesmo padrão de
 * funcionalidades/_shared.php: conexão, autenticação, CSRF e helpers de
 * redirecionamento com mensagem (flash), mas sem exigir tipo_usuario=admin.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require __DIR__ . '/../includes/helpers.php';

if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . BASE_URL . 'pages/login/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php');
    exit;
}

function bo_flash(string $type, string $text): void
{
    $_SESSION['bo_flash'] = ['type' => $type, 'text' => $text];
}

function bo_redirect_perfil(): never
{
    $section = ($_SESSION['tipo_usuario'] ?? '') === 'aluno' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'update-profile.php' ? 'perfil' : 'configuracoes';
    header('Location: ' . BASE_URL . 'pages/dashboard/dashboard.php?section=' . $section);
    exit;
}

function bo_check_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        bo_flash('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
        bo_redirect_perfil();
    }
}

function bo_str(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}
```


## pages/dashboard/components/header.php

```php
<?php
/**
 * components/header.php
 * Barra fixa no topo do backoffice. Contém:
 *  - botão hambúrguer (só aparece no mobile, abre/fecha a sidebar)
 *  - logo da ONE FIT
 *  - dropdown para alternar entre os perfis Administrador/Profissional/Aluno
 *    (usado para visualizar/testar as 3 telas sem precisar logar com 3
 *    contas diferentes; o item ativo é preenchido pelo JS em boRenderPerfilMenu())
 *  - avatar com a foto salva do aluno ou a inicial do perfil atual
 */
$fotoAvatarAluno = '';
if ($perfilLogado === 'aluno') {
    require_once __DIR__ . '/../includes/aluno-profile.php';
    $fotoAvatarAluno = bo_aluno_foto_url($usuarioDashboard['foto'] ?? null);
}
?>
<header class="bo-header">
    <div class="d-flex align-items-center gap-2">
        <button class="bo-sidebar-toggle d-lg-none" id="boSidebarToggle" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="bo-header-search-wrap" id="boHeaderSearchWrap">
            <label class="bo-header-search" for="boHeaderSearch">
                <i class="bi bi-search"></i>
                <input id="boHeaderSearch" type="search" placeholder="Pesquisar no painel" aria-label="Pesquisar no painel" autocomplete="off" aria-autocomplete="list" aria-controls="boSearchResults" aria-expanded="false">
                <kbd>Ctrl K</kbd>
            </label>
            <div class="bo-search-results" id="boSearchResults" role="listbox" aria-label="Resultados da pesquisa" hidden></div>
        </div>
    </div>

    <div class="bo-user">
        <div class="bo-user-menu-wrap" id="boUserMenuWrap">
            <button class="bo-avatar<?php echo $perfilLogado === 'aluno' ? ' bo-student-avatar' : ''; ?>" id="boAvatar" type="button" aria-label="Abrir menu do usuário" aria-expanded="false" aria-controls="boUserMenu">
                <?php echo htmlspecialchars(strtoupper(substr($_SESSION['nome'] ?? $perfilLogado, 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($fotoAvatarAluno): ?>
                    <img src="<?php echo htmlspecialchars($fotoAvatarAluno, ENT_QUOTES, 'UTF-8'); ?>" alt="" data-student-photo>
                <?php endif; ?>
            </button>
            <div class="bo-user-menu" id="boUserMenu" role="menu" aria-hidden="true">
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=perfil" role="menuitem"><i class="bi bi-person"></i> Editar perfil</a>
                <a href="<?php echo BASE_URL; ?>pages/dashboard/alterar-senha.php" role="menuitem"><i class="bi bi-key"></i> Alterar senha</a>
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=configuracoes" role="menuitem"><i class="bi bi-gear"></i> Configurações</a>
                <a href="<?php echo BASE_URL; ?>config/logout.php" role="menuitem" class="bo-user-menu-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</header>
```
