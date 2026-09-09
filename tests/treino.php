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
$input = ['acao' => 'salvar', 'nome' => 'Supino reto', 'series' => '4', 'repeticoes' => '12', 'carga' => '30', 'token' => bin2hex(random_bytes(16))];
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
foreach (['nome' => '', 'series' => 11, 'repeticoes' => 0, 'carga' => 301, 'token' => 'invalido', 'id' => -1] as $key => $value) {
    $failed = false;
    try { bo_treino_alterar($conn, 1, array_replace($input, [$key => $value])); } catch (DomainException $e) { $failed = true; }
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
