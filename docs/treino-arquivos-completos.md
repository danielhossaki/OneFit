# Treino semanal - arquivos completos

Implementado sobre a tela existente: dia no modal e na tabela, filtro por dia, ordem de segunda a domingo, persistencia, edicao do dia e exclusao individual. Mantidos tema, campos existentes, confirmacao para limpar a ficha inteira, token contra reenvio duplicado e isolamento por usuario.

## Banco de dados

A tabela treino_exercicio foi inspecionada: nao havia campo de dia. A migracao treino-dias-semana.sql ja foi aplicada neste ambiente. Registros antigos permanecem com NULL e aparecem como "Nao definido", depois de domingo em Todos os dias; ao editar, o aluno deve selecionar um dia. Nenhum exercicio foi criado automaticamente.

Em outra instalacao existente, execute apenas treino-dias-semana.sql uma vez antes de atualizar os arquivos. Para uma instalacao sem a tabela, execute apenas treino-exercicios.sql, que ja inclui o campo. Nao execute as duas migracoes em uma instalacao nova.

## Verificacao

24 verificacoes de banco real em tabelas temporarias passaram: CRUD, dias validos e invalidos, ordem semanal, mesmo exercicio em dias diferentes, edicao do dia, duplicidade por reenvio e isolamento. Teste de renderizacao PHP dos seis selects e seis colunas passou, assim como sintaxe PHP/JavaScript e git diff --check. Nao foi realizado teste visual em navegador.

## Arquivos completos

### pages/dashboard/includes/treino.php

```php
<?php
function bo_treino_dias(): array
{
    return ['segunda' => 'Segunda-feira', 'terca' => 'Terça-feira', 'quarta' => 'Quarta-feira',
        'quinta' => 'Quinta-feira', 'sexta' => 'Sexta-feira', 'sabado' => 'Sábado', 'domingo' => 'Domingo'];
}

function bo_treino_catalogo(): array
{
    return [
        'Peito' => ['Supino reto', 'Supino inclinado', 'Crucifixo', 'Crossover'],
        'Costas' => ['Puxada frontal', 'Remada baixa', 'Remada curvada', 'Pulldown'],
        'Ombros' => ['Desenvolvimento', 'Elevação lateral', 'Elevação frontal'],
        'Bíceps' => ['Rosca direta', 'Rosca alternada', 'Rosca martelo'],
        'Tríceps' => ['Tríceps pulley', 'Tríceps testa', 'Tríceps francês'],
        'Pernas' => ['Agachamento', 'Leg press', 'Cadeira extensora', 'Mesa flexora', 'Stiff', 'Panturrilha'],
        'Abdômen' => ['Abdominal tradicional', 'Prancha', 'Elevação de pernas'],
    ];
}

function bo_treino_carregar(mysqli $conn, int $usuario): array
{
    $stmt = $conn->prepare('SELECT id_exercicio AS id, nome, dia_semana, series, repeticoes, carga FROM treino_exercicio WHERE id_usuario = ? ORDER BY CASE dia_semana WHEN \'segunda\' THEN 1 WHEN \'terca\' THEN 2 WHEN \'quarta\' THEN 3 WHEN \'quinta\' THEN 4 WHEN \'sexta\' THEN 5 WHEN \'sabado\' THEN 6 WHEN \'domingo\' THEN 7 ELSE 8 END, id_exercicio');
    $stmt->bind_param('i', $usuario);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function bo_treino_alterar(mysqli $conn, int $usuario, array $dados): void
{
    if ($usuario <= 0) throw new DomainException('Entre novamente para acessar o treino.');
    $acao = $dados['acao'] ?? '';
    if (!in_array($acao, ['salvar', 'excluir', 'limpar'], true)) throw new DomainException('Ação inválida.');
    $id = filter_var($dados['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id === false || $id < 0 || ($acao === 'excluir' && !$id)) throw new DomainException('Exercício inválido.');
    if ($acao === 'limpar') {
        $stmt = $conn->prepare('DELETE FROM treino_exercicio WHERE id_usuario = ?');
        $stmt->bind_param('i', $usuario);
    } elseif ($acao === 'excluir') {
        $stmt = $conn->prepare('DELETE FROM treino_exercicio WHERE id_exercicio = ? AND id_usuario = ?');
        $stmt->bind_param('ii', $id, $usuario);
    } else {
        $dia = $dados['dia_semana'] ?? '';
        if (!is_string($dia) || !array_key_exists($dia, bo_treino_dias())) throw new DomainException('Selecione um dia da semana.');
        $nome = $dados['nome'] ?? '';
        if (!is_string($nome) || !in_array($nome, array_merge(...array_values(bo_treino_catalogo())), true)) {
            throw new DomainException('Selecione um exercício.');
        }
        $series = filter_var($dados['series'] ?? null, FILTER_VALIDATE_INT);
        $repeticoes = filter_var($dados['repeticoes'] ?? null, FILTER_VALIDATE_INT);
        $carga = filter_var($dados['carga'] ?? null, FILTER_VALIDATE_INT);
        if ($series === false || $series < 1 || $series > 10) throw new DomainException('Selecione de 1 a 10 séries.');
        if ($repeticoes === false || $repeticoes < 1 || $repeticoes > 50) throw new DomainException('Selecione de 1 a 50 repetições.');
        if ($carga === false || $carga < 0 || $carga > 300) throw new DomainException('Selecione uma carga de 0 a 300 kg.');
        if ($id) {
            $stmt = $conn->prepare('UPDATE treino_exercicio SET nome = ?, dia_semana = ?, series = ?, repeticoes = ?, carga = ? WHERE id_exercicio = ? AND id_usuario = ?');
            $stmt->bind_param('ssiiiii', $nome, $dia, $series, $repeticoes, $carga, $id, $usuario);
        } else {
            $token = $dados['token'] ?? '';
            if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) throw new DomainException('Reabra o formulário e tente novamente.');
            // A mesma requisição não pode criar duas linhas, mesmo após um retry.
            $stmt = $conn->prepare('INSERT INTO treino_exercicio (id_usuario, nome, dia_semana, series, repeticoes, carga, token_criacao) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_exercicio = id_exercicio');
            $stmt->bind_param('issiiis', $usuario, $nome, $dia, $series, $repeticoes, $carga, $token);
        }
    }
    $stmt->execute();
    $stmt->close();
}

```

### pages/dashboard/components/section-treino.php

```php
<section class="bo-content-section" data-perfil="aluno" data-section="treino" id="boTreino"
    data-endpoint="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/treino.php'); ?>"
    data-csrf="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="bo-page-title">
        <div><h1>Treino</h1><p>Monte e acompanhe sua ficha de treino.</p></div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-treino-limpar><i class="bi bi-eraser"></i> Limpar Treino</button>
            <button type="button" class="btn-bo-gold" data-treino-adicionar><i class="bi bi-plus-lg"></i> Adicionar Treino</button>
        </div>
    </div>
    <div class="bo-filters">
        <label for="boTreinoFiltro">Dia da semana</label>
        <select class="form-select" style="width: auto; max-width: 100%;" id="boTreinoFiltro" data-treino-filtro>
            <option value="">Todos os dias</option>
            <?php foreach (bo_treino_dias() as $valor => $dia): ?>
                <option value="<?php echo $valor; ?>"><?php echo $dia; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <p data-treino-aviso role="status" aria-live="polite" hidden></p>
    <div class="bo-table-wrap"><div class="table-responsive">
        <table class="bo-table"><thead><tr><th>Dia</th><th>Exercício</th><th>Séries</th><th>Repetições</th><th>Carga</th><th>Ações</th></tr></thead>
            <tbody data-treino-linhas>
                <?php foreach ($alunoTreino as $exercicio): ?>
                    <tr>
                        <td><?php echo bo_treino_dias()[$exercicio['dia_semana'] ?? ''] ?? 'Não definido'; ?></td>
                        <td><?php echo htmlspecialchars($exercicio['nome']); ?></td>
                        <td><?php echo (int) $exercicio['series']; ?></td>
                        <td><?php echo (int) $exercicio['repeticoes']; ?></td>
                        <td><?php echo (int) $exercicio['carga']; ?> kg</td>
                        <td><div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar" data-treino-editar="<?php echo (int) $exercicio['id']; ?>"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn-bo-icon danger" title="Excluir" data-treino-excluir="<?php echo (int) $exercicio['id']; ?>"><i class="bi bi-trash"></i></button>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$alunoTreino): ?><tr><td colspan="6">Nenhum exercício cadastrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div>
    <script type="application/json" data-treino-dados><?php echo bo_json($alunoTreino); ?></script>
</section>
<div class="modal fade bo-modal" id="boTreinoModal" tabindex="-1" aria-labelledby="boTreinoTitulo" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="boTreinoTitulo">Adicionar exercício</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <form id="boTreinoForm" class="row g-3">
                <input type="hidden" name="id" value="0">
                <input type="hidden" name="token">
                <div class="col-12">
                    <label class="form-label" for="boTreinoDia">Dia da semana</label>
                    <select class="form-select" name="dia_semana" id="boTreinoDia" required>
                        <option value="" disabled>Selecione um dia</option>
                        <?php foreach (bo_treino_dias() as $valor => $dia): ?>
                            <option value="<?php echo $valor; ?>" <?php echo $valor === 'segunda' ? 'selected' : ''; ?>><?php echo $dia; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="boTreinoNome">Exercício</label>
                    <select class="form-select" name="nome" id="boTreinoNome" required>
                        <option value="">Selecione um exercício</option>
                        <?php foreach (bo_treino_catalogo() as $grupo => $nomes): ?>
                            <optgroup label="<?php echo htmlspecialchars($grupo); ?>">
                                <?php foreach ($nomes as $nome): ?><option><?php echo htmlspecialchars($nome); ?></option><?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['series' => ['Séries', 1, 10, 3], 'repeticoes' => ['Repetições', 1, 50, 12], 'carga' => ['Carga (kg)', 0, 300, 0]] as $campo => [$label, $minimo, $maximo, $padrao]): ?>
                    <div class="col-12 col-sm-4">
                        <label class="form-label" for="boTreino-<?php echo $campo; ?>"><?php echo $label; ?></label>
                        <select class="form-select" name="<?php echo $campo; ?>" id="boTreino-<?php echo $campo; ?>" required>
                            <?php for ($valor = $minimo; $valor <= $maximo; $valor++): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $valor === $padrao ? 'selected' : ''; ?>><?php echo $valor . ($campo === 'carga' ? ' kg' : ''); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <p class="col-12 mb-0" data-treino-erro role="alert" hidden></p>
            </form>
        </div>
        <div class="modal-footer"><button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn-bo-gold" form="boTreinoForm">Salvar</button></div>
    </div></div>
</div>
<div class="modal fade bo-modal" id="boTreinoConfirmar" tabindex="-1" aria-labelledby="boTreinoConfirmarTitulo" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="boTreinoConfirmarTitulo">Limpar treino</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><p data-treino-pergunta></p><p data-treino-confirmar-erro role="alert" hidden></p></div>
        <div class="modal-footer"><button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn-bo-gold" data-treino-confirmar>Limpar treino</button></div>
    </div></div>
</div>
<script src="<?php echo BASE_URL; ?>assets/js/treino.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/treino.js'); ?>" defer></script>

```

### assets/js/treino.js

```js
document.addEventListener('DOMContentLoaded', () => {
    const section = document.getElementById('boTreino');
    if (!section) return;
    const form = document.getElementById('boTreinoForm');
    const modalElement = document.getElementById('boTreinoModal');
    const confirmElement = document.getElementById('boTreinoConfirmar');
    const modal = new bootstrap.Modal(modalElement);
    const confirmModal = new bootstrap.Modal(confirmElement);
    const error = form.querySelector('[data-treino-erro]');
    const confirmError = confirmElement.querySelector('[data-treino-confirmar-erro]');
    const notice = section.querySelector('[data-treino-aviso]');
    let exercises = JSON.parse(section.querySelector('[data-treino-dados]').textContent);
    const filter = section.querySelector('[data-treino-filtro]');
    const days = Object.fromEntries(Array.from(filter.options).filter(option => option.value).map(option => [option.value, option.textContent]));
    filter.addEventListener('change', render);
    let pending = null;
    let busy = false;

    function render() {
        const body = section.querySelector('[data-treino-linhas]');
        body.replaceChildren();
        const visible = exercises.filter(exercise => !filter.value || exercise.dia_semana === filter.value);
        visible.forEach(exercise => {
            const row = body.insertRow();
            [days[exercise.dia_semana] || 'Não definido', exercise.nome, exercise.series, exercise.repeticoes, `${exercise.carga} kg`].forEach(value => {
                row.insertCell().textContent = value;
            });
            const actions = document.createElement('div');
            actions.className = 'bo-table-actions';
            [['editar', 'Editar', 'bi-pencil'], ['excluir', 'Excluir', 'bi-trash']].forEach(([action, title, icon]) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn-bo-icon' + (action === 'excluir' ? ' danger' : '');
                button.title = title;
                button.setAttribute(`data-treino-${action}`, exercise.id);
                const symbol = document.createElement('i');
                symbol.className = `bi ${icon}`;
                button.append(symbol);
                actions.append(button);
            });
            row.insertCell().append(actions);
        });
        if (!visible.length) {
            const cell = body.insertRow().insertCell();
            cell.colSpan = 6;
            cell.textContent = filter.value ? 'Sem exercícios cadastrados neste dia.' : 'Nenhum exercício cadastrado.';
        }
    }

    async function send(data, errorElement, onSuccess) {
        if (busy) return;
        busy = true;
        errorElement.hidden = true;
        [modalElement, confirmElement, section].forEach(element => {
            element.querySelectorAll('button').forEach(button => { button.disabled = true; });
        });
        try {
            data.set('csrf_token', section.dataset.csrf);
            const response = await fetch(section.dataset.endpoint, { method: 'POST', body: data, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar o treino.');
            exercises = result.exercicios;
            render();
            onSuccess();
            notice.hidden = false;
            notice.textContent = 'Treino atualizado.';
        } catch (failure) {
            errorElement.textContent = failure instanceof SyntaxError ? 'Resposta inválida. Atualize a página e tente novamente.' : failure.message;
            errorElement.hidden = false;
        } finally {
            busy = false;
            [modalElement, confirmElement, section].forEach(element => {
                element.querySelectorAll('button').forEach(button => { button.disabled = false; });
            });
        }
    }

    [modalElement, confirmElement].forEach(element => {
        element.addEventListener('hide.bs.modal', event => { if (busy) event.preventDefault(); });
    });
    function open(exercise) {
        form.reset();
        error.hidden = true;
        form.elements.id.value = exercise ? exercise.id : 0;
        // Token por abertura; reaproveitado em retries após erro de rede.
        form.elements.token.value = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
        form.elements.dia_semana.value = exercise ? (exercise.dia_semana || '') : (filter.value || 'segunda');
        if (exercise) ['nome', 'series', 'repeticoes', 'carga'].forEach(key => { form.elements[key].value = exercise[key]; });
        document.getElementById('boTreinoTitulo').textContent = exercise ? 'Editar exercício' : 'Adicionar exercício';
        modal.show();
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (!form.reportValidity() || busy) return;
        const data = new FormData(form);
        data.set('acao', 'salvar');
        send(data, error, () => { busy = false; modal.hide(); });
    });
    section.addEventListener('click', event => {
        if (busy) return;
        if (event.target.closest('[data-treino-adicionar]')) { open(null); return; }
        const edit = event.target.closest('[data-treino-editar]');
        if (edit) { open(exercises.find(item => String(item.id) === edit.dataset.treinoEditar)); return; }
        const remove = event.target.closest('[data-treino-excluir]');
        const clear = event.target.closest('[data-treino-limpar]');
        if (!remove && !clear) return;
        pending = { acao: clear ? 'limpar' : 'excluir', id: clear ? '0' : remove.dataset.treinoExcluir };
        confirmError.hidden = true;
        document.getElementById('boTreinoConfirmarTitulo').textContent = clear ? 'Limpar treino' : 'Excluir exercício';
        confirmElement.querySelector('[data-treino-pergunta]').textContent = clear
            ? 'Tem certeza que deseja limpar todo o treino de todos os dias?' : 'Tem certeza que deseja excluir este exercício?';
        confirmElement.querySelector('[data-treino-confirmar]').textContent = clear ? 'Limpar treino' : 'Excluir';
        confirmModal.show();
    });
    confirmElement.querySelector('[data-treino-confirmar]').addEventListener('click', () => {
        if (!pending || busy) return;
        const data = new FormData();
        Object.entries(pending).forEach(([key, value]) => data.set(key, value));
        send(data, confirmError, () => { busy = false; pending = null; confirmModal.hide(); });
    });
});

```

### database/migrations/treino-exercicios.sql

```sql
CREATE TABLE IF NOT EXISTS treino_exercicio (
    id_exercicio INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    dia_semana VARCHAR(20) NULL DEFAULT NULL,
    nome VARCHAR(100) NOT NULL,
    series TINYINT UNSIGNED NOT NULL,
    repeticoes TINYINT UNSIGNED NOT NULL,
    carga SMALLINT UNSIGNED NOT NULL,
    token_criacao CHAR(32) NOT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_exercicio),
    UNIQUE KEY uq_treino_envio (id_usuario, token_criacao),
    CONSTRAINT fk_treino_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

```

### database/migrations/treino-dias-semana.sql

```sql
-- Para bases existentes: executar uma vez, antes de atualizar os arquivos PHP.
-- Registros antigos ficam sem dia ate serem editados pelo aluno.
ALTER TABLE treino_exercicio ADD COLUMN dia_semana VARCHAR(20) NULL DEFAULT NULL AFTER nome;

```

### tests/treino.php

```php
<?php
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/dashboard/includes/treino.php';
// Isola todas as gravações em uma tabela temporária desta conexão.
$conn->query('CREATE TEMPORARY TABLE treino_schema LIKE treino_exercicio');
$conn->query('CREATE TEMPORARY TABLE treino_exercicio LIKE treino_schema');
$checks = 0;
function treino_check(bool $ok): void {
    global $checks;
    if (!$ok) throw new RuntimeException('Falha no teste de treino #' . ($checks + 1));
    $checks++;
}
$input = ['acao' => 'salvar', 'nome' => 'Supino reto', 'dia_semana' => 'sexta', 'series' => '4', 'repeticoes' => '12', 'carga' => '30', 'token' => bin2hex(random_bytes(16))];
bo_treino_alterar($conn, 1, $input);
bo_treino_alterar($conn, 1, $input);
$rows = bo_treino_carregar($conn, 1);
treino_check(count($rows) === 1);
treino_check($rows[0]['nome'] === 'Supino reto' && (int) $rows[0]['carga'] === 30);
treino_check((int) $rows[0]['series'] === 4 && (int) $rows[0]['repeticoes'] === 12);
treino_check(bo_treino_carregar($conn, 2) === []);
$id = $rows[0]['id'];
bo_treino_alterar($conn, 2, array_replace($input, ['id' => $id, 'carga' => 100]));
treino_check((int) bo_treino_carregar($conn, 1)[0]['carga'] === 30);
bo_treino_alterar($conn, 2, ['acao' => 'excluir', 'id' => $id]);
treino_check(count(bo_treino_carregar($conn, 1)) === 1);
bo_treino_alterar($conn, 1, array_replace($input, ['id' => $id, 'carga' => 0, 'series' => 10, 'repeticoes' => 50]));
treino_check((int) bo_treino_carregar($conn, 1)[0]['carga'] === 0);
foreach (['dia_semana' => 'invalido', 'nome' => '', 'series' => 11, 'repeticoes' => 0, 'carga' => 301, 'token' => 'invalido', 'id' => -1] as $key => $value) {
    $failed = false;
    try { bo_treino_alterar($conn, 1, array_replace($input, [$key => $value])); } catch (DomainException $e) { $failed = true; }
    treino_check($failed);
}
// Mesmo exercicio em dias diferentes, inseridos fora da ordem semanal.
foreach (array_reverse(array_keys(bo_treino_dias())) as $dia) {
    bo_treino_alterar($conn, 1, array_replace($input, ['dia_semana' => $dia, 'token' => bin2hex(random_bytes(16))]));
}
$semana = bo_treino_carregar($conn, 1);
treino_check(count($semana) === 8);
treino_check(array_values(array_unique(array_column($semana, 'dia_semana'))) === array_keys(bo_treino_dias()));
bo_treino_alterar($conn, 1, array_replace($input, ['id' => $id, 'dia_semana' => 'quarta']));
$editado = array_values(array_filter(bo_treino_carregar($conn, 1), fn($row) => $row['id'] === $id));
treino_check($editado[0]['dia_semana'] === 'quarta');
bo_treino_alterar($conn, 1, ['acao' => 'excluir', 'id' => $id]);
treino_check(count(bo_treino_carregar($conn, 1)) === 7);
foreach ([null, [], ''] as $diaInvalido) {
    $failed = false;
    try { bo_treino_alterar($conn, 1, array_replace($input, ['dia_semana' => $diaInvalido])); } catch (DomainException $e) { $failed = true; }
    treino_check($failed);
}
bo_treino_alterar($conn, 2, array_replace($input, ['token' => bin2hex(random_bytes(16))]));
bo_treino_alterar($conn, 1, ['acao' => 'limpar']);
treino_check(bo_treino_carregar($conn, 1) === []);
treino_check(count(bo_treino_carregar($conn, 2)) === 1);
$id2 = bo_treino_carregar($conn, 2)[0]['id'];
bo_treino_alterar($conn, 2, ['acao' => 'excluir', 'id' => $id2]);
treino_check(bo_treino_carregar($conn, 2) === []);
echo "OK: $checks verificações de CRUD, validação, duplicidade e isolamento; nenhuma ficha gravada na base de uso.\n";

```

### tests/treino-render.php

```php
<?php
require __DIR__ . '/../pages/dashboard/includes/treino.php';
define('BASE_URL', '/');
$_SESSION['csrf_token'] = 'teste';
function bo_json($value) { return json_encode($value); }
$alunoTreino = [['id' => 1, 'nome' => 'Supino reto', 'dia_semana' => 'terca', 'series' => 4, 'repeticoes' => 12, 'carga' => 30]];
ob_start();
require __DIR__ . '/../pages/dashboard/components/section-treino.php';
$html = ob_get_clean();
foreach ([substr_count($html, '<select ') === 6, substr_count($html, '<th>') === 6, strpos($html, '<td>Terça-feira</td>') !== false, strpos($html, 'value="domingo"') !== false] as $ok) {
    if (!$ok) throw new RuntimeException('Falha na renderizacao do treino.');
}
echo "OK: selects, colunas, dia persistido e domingo renderizados.\n";

```

