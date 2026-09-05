<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../pages/dashboard/includes/aluno-profile.php';
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
foreach ([[18.49, 'Abaixo do peso'], [18.5, 'Peso adequado'], [24.99, 'Peso adequado'], [25, 'Sobrepeso'], [30, 'Obesidade grau I'], [35, 'Obesidade grau II'], [40, 'Obesidade grau III']] as [$peso, $classe]) {
    check(bo_aluno_imc(1, $peso)['classe'] === $classe, 'Limite de classificação: ' . $peso);
}
check(abs(bo_aluno_imc('1,72', '100')['valor'] - 33.802055) < .00001, 'Exemplo 100 / 1,72²');
check(bo_aluno_imc('1,72', '70,5') === bo_aluno_imc('1.72', '70.5'), 'Normalização decimal');
foreach (['', '0', '-1', 'NaN', 'Infinity', '1.72abc', '1,2,3', '1e999', '0.' . str_repeat('0', 170) . '1'] as $invalid) {
    check(bo_aluno_imc($invalid, 70)['valor'] === null, 'Altura inválida: ' . $invalid);
}
check(bo_aluno_imc(1.7, 501)['valor'] === null, 'Peso fora do limite');
check(bo_aluno_upload() === null, 'Upload opcional');
$_FILES['foto_arquivo'] = ['error' => UPLOAD_ERR_INI_SIZE];
try { bo_aluno_upload(); throw new LogicException('Upload inválido aceito'); }
catch (RuntimeException $expected) {}
echo "IMC: limites, decimais, entradas inválidas e erro de upload OK\n";
define('BASE_URL', 'http://localhost/AN25/OneFit/');
require __DIR__ . '/../pages/dashboard/includes/helpers.php';
require __DIR__ . '/../pages/dashboard/includes/admin-forms.php';
$_SESSION = ['id_usuario' => 123, 'csrf_token' => 'test'];
$usuarioDashboard = ['nome' => 'Aluno <teste>', 'email' => 'aluno@example.test', 'genero' => 'masculino', 'altura' => '1.72', 'peso' => '100', 'foto' => null];
$preferenciasDisponiveis = true;
$preferenciasDashboard = [];
foreach (['aluno', 'admin', 'profissional', 'vendedor'] as $perfilLogado) {
    ob_start();
    include __DIR__ . '/../pages/dashboard/components/section-configuracoes.php';
    $html = ob_get_clean();
    check(substr_count($html, 'id="boProfileSection"') === 1, 'Resumo único: ' . $perfilLogado);
    check(substr_count($html, 'id="modalPerfilEditar"') === 1, 'Modal único: ' . $perfilLogado);
    check(str_contains($html, 'Aluno &lt;teste&gt;'), 'Escape do nome');
    check(str_contains($html, 'student-profile.js') === ($perfilLogado === 'aluno'), 'Escopo do aluno');
    if ($perfilLogado === 'aluno') check(str_contains($html, '33,8 · Obesidade grau I'), 'IMC no resumo PHP');
}
echo "Renderização PHP e isolamento dos quatro perfis OK\n";
if (in_array('--schema', $argv, true)) {
    require __DIR__ . '/../config/conn.php';
    $result = $conn->query('SHOW COLUMNS FROM usuarios');
    $found = [];
    while ($column = $result->fetch_assoc()) {
        if (in_array($column['Field'], ['altura', 'peso', 'foto'], true)) {
            $found[] = $column['Field'];
            echo $column['Field'] . ': ' . $column['Type'] . "\n";
        }
    }
    check(count($found) === 3, 'Colunas existentes');
}
