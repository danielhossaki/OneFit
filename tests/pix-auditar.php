<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--somente-leitura'){http_response_code(404);exit(1);}
ini_set('display_errors','0');ini_set('log_errors','0');
try{
    $path=$argv[2]??'';
    if(hash_file('sha256',$path)!=='a714a7959792548e9bb7cacc9e0e12a3ad38f3f83e682b52769dfb18cbd53c6f')throw new RuntimeException();
    $backup=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    require __DIR__.'/../config/conn.php';$counts=[];
    foreach($backup as $table=>$b){
        if(!preg_match('/^[a-z_]+$/D',$table)||!preg_match('/^[a-z_]+$/D',$b['pk']))throw new RuntimeException();
        $rows=$conn->query("SELECT * FROM `$table` ORDER BY `{$b['pk']}`")->fetch_all(MYSQLI_ASSOC);
        $counts[$table]=['antes'=>count($b['rows']),'depois'=>count($rows)];
        if(count($b['rows'])!==count($rows))throw new RuntimeException();
        foreach($b['rows'] as $i=>$old)if(array_intersect_key($rows[$i],$old)!==$old)throw new RuntimeException();
    }
    $legacy=$conn->query('SHOW CREATE TABLE pagamento')->fetch_row()[1];if($legacy!==$backup['pagamento']['ddl'])throw new RuntimeException();
    $columns=['pagamentos'=>['pix_qr'=>['text','YES',null],'pix_qr_base64'=>['mediumtext','YES',null],'pix_ticket_url'=>['varchar(2048)','YES',null],'provedor_criado_em'=>['datetime(6)','YES',null],'provedor_atualizado_em'=>['datetime(6)','YES',null]],'cobrancas'=>['cashback_ganho'=>['decimal(12,2)','NO','0.00']],'cashback'=>['id_cobranca'=>['bigint(20) unsigned','YES',null]]];
    foreach($columns as $table=>$expected){$actual=[];foreach($conn->query("SHOW COLUMNS FROM `$table`")->fetch_all(MYSQLI_ASSOC) as $c)$actual[$c['Field']]=[$c['Type'],$c['Null'],$c['Default']];foreach($expected as $k=>$v)if(($actual[$k]??null)!==$v)throw new RuntimeException();}
    foreach(['pix_reservas'=>['id_cobranca','id_produto'],'pix_notificacoes'=>['id_cobranca','id_usuario','tipo'],'pix_testes_tecnicos'=>['id_usuario']] as $table=>$primary){
        if((int)$conn->query("SELECT COUNT(*) FROM `$table`")->fetch_row()[0]!==0)throw new RuntimeException();
        $pk=$conn->query("SHOW INDEX FROM `$table` WHERE Key_name='PRIMARY'")->fetch_all(MYSQLI_ASSOC);if(array_column($pk,'Column_name')!==$primary)throw new RuntimeException();
        $ddl=$conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];if(!str_contains($ddl,'ENGINE=InnoDB'))throw new RuntimeException();
    }
    $fk=$conn->query("SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('fk_cashback_cobranca','fk_pix_reserva_cobranca','fk_pix_reserva_produto','fk_pix_aviso_cobranca','fk_pix_aviso_usuario','fk_pix_teste_usuario','fk_pix_teste_plano','fk_pix_teste_produto')")->fetch_all(MYSQLI_ASSOC);
    if(count($fk)!==8)throw new RuntimeException();foreach($fk as $f)if($f['DELETE_RULE']!=='RESTRICT'||$f['UPDATE_RULE']!=='RESTRICT')throw new RuntimeException();
    $unique=$conn->query("SHOW INDEX FROM cashback WHERE Key_name='uq_cashback_cobranca_tipo'")->fetch_all(MYSQLI_ASSOC);if(array_column($unique,'Column_name')!==['id_cobranca','tipo']||(int)$unique[0]['Non_unique']!==0)throw new RuntimeException();
    echo json_encode(['backup_sha256'=>hash_file('sha256',$path),'dados_antigos_iguais'=>true,'legado_estrutura_igual'=>true,'schema_aditivo_validado'=>true,'contagens'=>$counts,'somente_leitura'=>true]),PHP_EOL;
}catch(Throwable){echo '{"auditoria":"falhou_sem_expor_dados"}',PHP_EOL;exit(1);}
