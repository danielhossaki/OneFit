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
