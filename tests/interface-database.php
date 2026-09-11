<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$fixture = json_decode(file_get_contents(sys_get_temp_dir() . '/onefit-interface-fixture/database.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$/D', $fixture['database'])) throw new RuntimeException('Unsafe fixture target');
$conn = new mysqli('127.0.0.1','root','',$fixture['database']); $conn->set_charset('utf8mb4');
require __DIR__ . '/../config/notificacoes.php';
require __DIR__ . '/../pages/carrinho/checkout.php';
define('BASE_URL','http://127.0.0.1:8765/AN25/OneFit/');
$checks = 0;
function dbCheck(bool $value, string $message): void { global $checks; if (!$value) throw new RuntimeException($message); $checks++; }
$conn->query("UPDATE preferencias_usuario SET idioma='en' WHERE id_usuario=1");
$conn->query("UPDATE preferencias_usuario SET idioma='es' WHERE id_usuario=2");
$beforeUsers = $conn->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC);
foreach ([3=>1,4=>2] as $buyer=>$address) {
    $session = ['id_usuario'=>$buyer,'carrinho'=>[1=>1],'checkout_endereco_id'=>$address,'csrf_token'=>'fixture-csrf','checkout_token'=>'fixture-token'];
    $post = ['csrf_token'=>'fixture-csrf','checkout_token'=>'fixture-token','forma_pagamento'=>'pix'];
    $pedido = cart_processar_checkout($conn,$session,$post);
    dbCheck(notificarCompraBackoffice($conn,$pedido['id']) === 2,'Two authorized recipients');
    dbCheck(notificarCompraBackoffice($conn,$pedido['id']) === 0,'Persistent notification deduplication');
    $failed = false; try { cart_processar_checkout($conn,$session,$post); } catch (DomainException $e) { $failed = true; }
    dbCheck($failed,'Checkout replay blocked');
    dbCheck((int)$conn->query('SELECT COUNT(*) FROM pedido WHERE id_pedido=' . $pedido['id'])->fetch_row()[0] === 1,'Order committed');
}
dbCheck((int)$conn->query('SELECT COUNT(*) FROM notificacoes WHERE usuario_id NOT IN (1,2)')->fetch_row()[0] === 0,'No unauthorized notification');
dbCheck((int)$conn->query("SELECT COUNT(*) FROM notificacoes WHERE usuario_id=1 AND titulo='New marketplace order'")->fetch_row()[0] === 2,'English recipient');
dbCheck((int)$conn->query("SELECT COUNT(*) FROM notificacoes WHERE usuario_id=2 AND titulo='Nuevo pedido en el marketplace'")->fetch_row()[0] === 2,'Spanish recipient');
dbCheck((int)$conn->query("SELECT COUNT(*) FROM notificacoes WHERE mensagem LIKE '%Fixture%' OR mensagem LIKE '%example.test%'")->fetch_row()[0] === 0,'Minimal notification data');
$conn->query("UPDATE usuarios SET tipo_usuario='aluno' WHERE id_usuario=2");
$session = ['id_usuario'=>3,'carrinho'=>[1=>1],'checkout_endereco_id'=>1,'csrf_token'=>'x','checkout_token'=>'y'];
$pedido = cart_processar_checkout($conn,$session,['csrf_token'=>'x','checkout_token'=>'y']);
dbCheck(notificarCompraBackoffice($conn,$pedido['id']) === 1,'Revoked recipient excluded');
$conn->query("UPDATE usuarios SET tipo_usuario='admin' WHERE id_usuario=2");
// Force delivery failure after a committed purchase, then verify the order and retry.
$conn->query('RENAME TABLE notificacoes TO fixture_notificacoes_offline');
$failed = false;
try { notificarCompraBackoffice($conn,$pedido['id']); } catch (Throwable $e) { $failed = true; }
finally { $conn->query('RENAME TABLE fixture_notificacoes_offline TO notificacoes'); }
dbCheck($failed,'Notification failure simulated');
dbCheck((int)$conn->query('SELECT COUNT(*) FROM pedido WHERE id_pedido='.$pedido['id'])->fetch_row()[0] === 1,'Failure preserves purchase');
dbCheck(notificarCompraBackoffice($conn,$pedido['id']) === 1,'Retry after failure');
$afterUsers = $conn->query('SELECT * FROM usuarios ORDER BY id_usuario')->fetch_all(MYSQLI_ASSOC);
// Account 2 was deliberately edited for the revocation scenario; only its automatic timestamp may differ.
foreach ($afterUsers as $index => &$row) if ((int)$row['id_usuario'] === 2) $row['data_atualizacao'] = $beforeUsers[$index]['data_atualizacao'];
unset($row);
dbCheck($afterUsers === $beforeUsers,'Other user fields preserved');
onefitCarregarInterface($conn);
$_SESSION = ['id_usuario'=>1]; onefitCarregarInterface($conn); dbCheck(onefitIdioma()==='en','Language from database/new session');
$_SESSION = ['id_usuario'=>2]; onefitCarregarInterface($conn); dbCheck(onefitIdioma()==='es','Second user language');
$photoDir = sys_get_temp_dir() . '/onefit-interface-fixture/www/AN25/OneFit/assets/img/uploads/perfil';
if (!is_dir($photoDir)) mkdir($photoDir,0700,true);
file_put_contents($photoDir.'/fixture.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aEusAAAAASUVORK5CYII='));
$photo = BASE_URL . 'assets/img/uploads/perfil/fixture.png';
$stmt = $conn->prepare('UPDATE usuarios SET foto=? WHERE id_usuario=3'); $stmt->bind_param('s',$photo); $stmt->execute();
echo "Isolated database checks: $checks passed.\n";
