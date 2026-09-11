<?php
// Only the disposable localhost fixture; never load the application's configured connection.
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true);
if (!preg_match('/^onefit_interface_test_[a-zA-Z0-9_]+$/', $fixture['database'] ?? '')) exit(1);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$ids = [];
foreach ([3, 3, 1] as $user) {
    $db->query("INSERT INTO notificacoes (usuario_id,titulo,mensagem,tipo,criada_em) VALUES ($user,'Synthetic test','Synthetic test','info',UTC_TIMESTAMP())");
    $ids[] = $db->insert_id;
}
echo json_encode($ids);
