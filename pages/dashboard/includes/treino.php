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
