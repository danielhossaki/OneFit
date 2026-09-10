<?php
/**
 * funcionalidades/modalidades.php
 * CRUD da tela "Modalidades" do admin (tabela `modalidades`, usada como
 * checklist no cadastro de profissionais). Mesmo padrão de categorias.php.
 */

require __DIR__ . '/_shared.php';
require __DIR__ . '/../includes/admin-forms.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

const MODALIDADE_ICONE_PADRAO = 'generico';

function modalidade_icone_valido(string $icone): string
{
    $valido = array_key_exists($icone, bo_icones_modalidade_options());
    return $valido ? $icone : MODALIDADE_ICONE_PADRAO;
}

if ($acao === 'toggle-status') {
    if (!$id) {
        bo_flash('error', 'Modalidade inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare("UPDATE modalidades SET status = IF(status = 'ativo', 'inativo', 'ativo') WHERE id_modalidade = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Status da modalidade atualizado.');
    bo_redirect($secao);
}

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Modalidade inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('DELETE FROM modalidades WHERE id_modalidade = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Modalidade excluída.');
    bo_redirect($secao);
}

$nome = bo_str('nome');
$descricao = bo_str('descricao');
$icone = modalidade_icone_valido(bo_str('icone'));
if (!$nome) {
    bo_flash('error', 'Informe o nome da modalidade.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Modalidade inválida.');
        bo_redirect($secao);
    }
    $stmtOld = $conn->prepare('SELECT nome FROM modalidades WHERE id_modalidade = ?');
    $stmtOld->bind_param('i', $id);
    $stmtOld->execute();
    $old = $stmtOld->get_result()->fetch_assoc();
    $stmtOld->close();

    $stmt = $conn->prepare('UPDATE modalidades SET nome = ?, descricao = ?, icone = ? WHERE id_modalidade = ?');
    $stmt->bind_param('sssi', $nome, $descricao, $icone, $id);
    try {
        $stmt->execute();
    } catch (\Throwable $e) {
        bo_flash('error', 'Já existe uma modalidade com este nome.');
        bo_redirect($secao);
    }
    $stmt->close();

    // Propaga o novo nome para os profissionais que já ministravam essa modalidade
    // (coluna cadastro_profissional.modalidades é uma string separada por vírgula).
    if ($old && $old['nome'] !== $nome) {
        $res = $conn->query("SELECT id_profissional, modalidades FROM cadastro_profissional WHERE FIND_IN_SET('" . $conn->real_escape_string($old['nome']) . "', modalidades)");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $lista = array_map('trim', explode(',', $row['modalidades']));
                $lista = array_map(static fn($m) => $m === $old['nome'] ? $nome : $m, $lista);
                $novaLista = implode(', ', $lista);
                $updProf = $conn->prepare('UPDATE cadastro_profissional SET modalidades = ? WHERE id_profissional = ?');
                $updProf->bind_param('si', $novaLista, $row['id_profissional']);
                $updProf->execute();
                $updProf->close();
            }
        }
    }

    bo_flash('success', 'Modalidade atualizada.');
    bo_redirect($secao);
}

// A tabela `modalidades` não tem AUTO_INCREMENT na chave primária: calcula o
// próximo ID manualmente antes de gravar.
$novoId = 1;
if ($r = $conn->query('SELECT COALESCE(MAX(id_modalidade), 0) + 1 AS proximo FROM modalidades')) {
    $novoId = (int) $r->fetch_assoc()['proximo'];
}

$stmt = $conn->prepare('INSERT INTO modalidades (id_modalidade, nome, descricao, icone) VALUES (?, ?, ?, ?)');
$stmt->bind_param('isss', $novoId, $nome, $descricao, $icone);
try {
    $stmt->execute();
} catch (\Throwable $e) {
    bo_flash('error', 'Já existe uma modalidade com este nome.');
    bo_redirect($secao);
}
$stmt->close();

bo_flash('success', 'Modalidade criada.');
bo_redirect($secao);
