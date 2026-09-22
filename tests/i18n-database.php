<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/notificacoes.php';
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) throw new RuntimeException('Unsafe fixture target');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $fixture['database']);
$db->set_charset('utf8mb4');
$db->begin_transaction();
try {
    $db->query("INSERT INTO pedido (id_usuario, valor_total, forma_pagamento) VALUES (3, 1234.50, 'pix')");
    $id = (int) $db->insert_id;
    $event = 'marketplace.pedido.' . $id;
    $stmt = $db->prepare('INSERT IGNORE INTO notificacoes_eventos (evento, usuario_id) VALUES (?, 1)');
    $stmt->bind_param('s', $event); $stmt->execute(); $stmt->close();
    $item = ['tipo'=>'info', 'titulo'=>onefitTraduzir('Novo pedido no marketplace', [], 'en'), 'mensagem'=>'Original generated English message', 'link'=>'/AN25/OneFit/pages/dashboard/dashboard.php?section=vendas&pedido='.$id];
    foreach (onefitIdiomas() as $locale) {
        $_SESSION['idioma'] = $locale;
        $result = localizarNotificacaoSistema($db, 1, $item);
        if ($result['titulo'] !== onefitTraduzir('Novo pedido no marketplace') || $result['mensagem'] === $item['mensagem']) throw new RuntimeException('System event was not localized');
        $custom = $item; $custom['titulo'] = 'Texto livre — One Fit';
        if (localizarNotificacaoSistema($db, 1, $custom) !== $custom) throw new RuntimeException('Free-form notification changed');
        $custom = $item; $custom['link'] = null;
        if (localizarNotificacaoSistema($db, 1, $custom) !== $custom) throw new RuntimeException('Unidentified notification changed');
    }
    echo "i18n database: system notifications follow all 3 locales; free-form content is preserved.\n";
} finally { $db->rollback(); $db->close(); }
