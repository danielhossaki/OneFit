<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$root = dirname(__DIR__);
$schemaFile = __DIR__ . '/fixtures/interface-schema.json';
if (!is_file($schemaFile)) {
    require $root . '/config/conn.php';
    $schema = [];
    $tables = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetch_all(MYSQLI_ASSOC);
    foreach ($tables as $row) {
        $table = $row['TABLE_NAME'];
        $schema[$table] = $conn->query('SHOW CREATE TABLE `' . str_replace('`','``',$table) . '`')->fetch_row()[1];
    }
    $conn->close();
    if (!is_dir(dirname($schemaFile))) mkdir(dirname($schemaFile));
    file_put_contents($schemaFile, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
} else $schema = json_decode(file_get_contents($schemaFile), true, 512, JSON_THROW_ON_ERROR);
$db = new mysqli('127.0.0.1', 'root', '');
$name = 'onefit_interface_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4"); $db->select_db($name); $db->set_charset('utf8mb4');
$remaining = $schema;
while ($remaining) {
    $progress = false;
    foreach ($remaining as $table => $ddl) {
        preg_match_all('/REFERENCES `([^`]+)`/', $ddl, $matches);
        if (array_intersect($matches[1], array_keys($remaining))) continue;
        // Keep the schema but remove production AUTO_INCREMENT counters.
        $db->query(preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl));
        unset($remaining[$table]); $progress = true;
    }
    if (!$progress) throw new RuntimeException('Cyclic fixture schema');
}
$password = password_hash('Fixture-only-123!', PASSWORD_DEFAULT);
foreach ([1=>'admin',2=>'admin',3=>'aluno',4=>'profissional',5=>'aluno',6=>'admin'] as $id=>$role) {
    $status = $id === 6 ? 'inativo' : 'ativo'; $email = "fixture$id@example.test"; $cpf = str_pad((string) $id, 11, '0', STR_PAD_LEFT); $display = "Fixture $id";
    $stmt = $db->prepare("INSERT INTO usuarios (id_usuario,nome,senha,nacionalidade,data_nascimento,genero,cpf,endereco,cidade_estado,email,email_verificado,celular,tipo_usuario,status,altura,peso) VALUES (?,?,?,'brasileira','1990-01-01','outro',?,'Fixture street','São Paulo/SP',?,1,'11999999999',?,?,1.72,70)");
    $stmt->bind_param('issssss', $id,$display,$password,$cpf,$email,$role,$status);
    $stmt->execute(); $stmt->close();
}
$db->query("INSERT INTO cadastro_planos (id_plano,nome,valor,duracao_dias,status) VALUES (1,'Fixture plan',100,30,'ativo')");
$db->query("INSERT INTO matricula (id_matricula,id_usuario,id_plano,data_matricula,data_inicio,data_fim,status,valor_contratado) VALUES (1,3,1,CURDATE(),CURDATE(),DATE_ADD(CURDATE(), INTERVAL 30 DAY),'ativa',100)");
$db->query("INSERT INTO categorias (id_categoria,nome,status) VALUES (1,'Fixture category','ativo')");
$db->query("INSERT INTO produtos (id_produto,nome,descricao,preco,estoque,categoria,cashback_valor,status,desconto) VALUES (1,'Fixture product','User content must stay unchanged',25,100,'Fixture category',1,'ativo',0),(2,'Fixture second product','Untranslated',15,100,'Fixture category',1,'ativo',0)");
$db->query("INSERT INTO enderecos_entrega (id_endereco,id_usuario,cep,logradouro,numero,bairro,cidade,uf,principal) VALUES (1,3,'01001000','Fixture street','1','Fixture','São Paulo','SP',1),(2,4,'01001000','Fixture street','2','Fixture','São Paulo','SP',1)");
$db->query("INSERT INTO transportadoras (id_transportadora,nome,tipo,status) VALUES (1,'Fixture carrier','transportadora','ativo')");
$db->query("INSERT INTO faixas_cep_frete (id_faixa,id_transportadora,cep_inicial,cep_final,valor_frete,prazo_dias) VALUES (1,1,'00000000','99999999',5,3)");
$db->query("INSERT INTO preferencias_usuario (id_usuario) VALUES (1),(2),(3),(4),(5),(6)");
$temp = sys_get_temp_dir() . '/onefit-interface-fixture';
if (!is_dir($temp)) mkdir($temp, 0700, true);
file_put_contents($temp . '/database.json', json_encode(['database'=>$name, 'root'=>$root]));
echo 'Created isolated synthetic database: ' . $name . PHP_EOL;
