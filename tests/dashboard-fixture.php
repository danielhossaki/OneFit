<?php
// Creates synthetic data only; never connects to the application's configured database.
require __DIR__ . '/prepare-interface-fixture.php';
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true);
if (!str_starts_with($fixture['database'], 'onefit_interface_test_')) {
    throw new RuntimeException('Expected isolated fixture database');
}
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->query("ALTER TABLE usuarios MODIFY tipo_usuario ENUM('aluno','profissional','admin','vendedor') DEFAULT 'aluno'");
$db->query("UPDATE usuarios SET tipo_usuario='vendedor' WHERE id_usuario=5");
$db->query("INSERT INTO cadastro_profissional (id_usuario,nome,email,status) VALUES (4,'Profissional de teste','prof@example.test','ativo')");
// The archived fixture schema predates the optional names in testimonials.
foreach (['nome_exibido' => 'VARCHAR(120)', 'tempo_exibido' => 'VARCHAR(60)'] as $column => $type) {
    if (!$db->query("SHOW COLUMNS FROM testemunhos LIKE '$column'")->num_rows) {
        $db->query("ALTER TABLE testemunhos ADD COLUMN $column $type NULL");
    }
}
echo "Dashboard fixture: four roles ready.\n";