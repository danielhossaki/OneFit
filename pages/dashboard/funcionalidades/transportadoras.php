<?php
/**
 * funcionalidades/transportadoras.php
 * CRUD de transportadoras e das faixas de CEP/frete (tabelas
 * `transportadoras` e `faixas_cep_frete`). Transportadoras são sempre
 * globais, cadastradas só pelo admin (_shared.php já exige admin por
 * padrão, nenhuma role extra liberada aqui).
 */

require __DIR__ . '/_shared.php';
require __DIR__ . '/../includes/frete.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'toggle-status') {
    if (!$id) {
        bo_flash('error', 'Transportadora inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare("UPDATE transportadoras SET status = IF(status = 'ativo', 'inativo', 'ativo') WHERE id_transportadora = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Status da transportadora atualizado.');
    bo_redirect($secao);
}

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Transportadora inválida.');
        bo_redirect($secao);
    }
    try {
        $stmt = $conn->prepare('DELETE FROM transportadoras WHERE id_transportadora = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        bo_flash('success', 'Transportadora excluída.');
    } catch (\Throwable $e) {
        bo_flash('error', 'Não é possível excluir: esta transportadora já tem vendas vinculadas. Inative-a em vez de excluir.');
    }
    bo_redirect($secao);
}

if ($acao === 'delete-faixa') {
    $idFaixa = (int) bo_str('id_faixa');
    if (!$idFaixa) {
        bo_flash('error', 'Faixa de CEP inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('DELETE FROM faixas_cep_frete WHERE id_faixa = ?');
    $stmt->bind_param('i', $idFaixa);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Faixa de CEP removida.');
    bo_redirect($secao);
}

if ($acao === 'create-faixa') {
    $idTransportadora = (int) bo_str('id_transportadora');
    $cepInicial = bo_normalizar_cep(bo_str('cep_inicial'));
    $cepFinal = bo_normalizar_cep(bo_str('cep_final'));
    $valorFrete = bo_num('valor_frete');
    $prazoDias = (int) bo_num('prazo_dias');

    if (!$idTransportadora || $cepInicial > $cepFinal || $valorFrete < 0) {
        bo_flash('error', 'Preencha a faixa de CEP corretamente (CEP inicial menor ou igual ao final).');
        bo_redirect($secao);
    }

    $stmt = $conn->prepare('INSERT INTO faixas_cep_frete (id_transportadora, cep_inicial, cep_final, valor_frete, prazo_dias) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issdi', $idTransportadora, $cepInicial, $cepFinal, $valorFrete, $prazoDias);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Faixa de CEP cadastrada.');
    bo_redirect($secao);
}

$nome = bo_str('nome');
$tipo = bo_str('tipo');
$tiposValidos = ['transportadora', 'correios', 'sedex', 'motoboy', 'outros'];

if (!$nome || !in_array($tipo, $tiposValidos, true)) {
    bo_flash('error', 'Preencha nome e tipo válidos.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Transportadora inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('UPDATE transportadoras SET nome = ?, tipo = ? WHERE id_transportadora = ?');
    $stmt->bind_param('ssi', $nome, $tipo, $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Transportadora atualizada.');
    bo_redirect($secao);
}

$stmt = $conn->prepare('INSERT INTO transportadoras (nome, tipo, status) VALUES (?, ?, "ativo")');
$stmt->bind_param('ss', $nome, $tipo);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Transportadora cadastrada.');
bo_redirect($secao);
