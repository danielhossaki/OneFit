<?php
/**
 * funcionalidades/enderecos.php
 * CRUD de endereços de entrega do usuário logado (aluno, profissional,
 * vendedor ou admin — qualquer um pode ter endereços salvos para compras
 * no marketplace). Diferente dos outros arquivos desta pasta, não exige
 * tipo_usuario === 'admin' (reaproveita só a checagem de sessão de
 * config/auth.php), e sempre volta para o carrinho, que é quem hoje
 * consome estes endereços.
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require __DIR__ . '/../includes/frete.php';

$idUsuario = (int) $_SESSION['id_usuario'];

function end_redirect(): never
{
    header('Location: ' . BASE_URL . 'pages/carrinho/carrinho.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    end_redirect();
}

$token = (string) ($_POST['csrf_token'] ?? '');
if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    end_redirect();
}

function end_str(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

$acao = end_str('acao');

switch ($acao) {
    case 'create':
    case 'update':
        $apelido = end_str('apelido');
        $cep = bo_normalizar_cep(end_str('cep'));
        $logradouro = end_str('logradouro');
        $numero = end_str('numero');
        $complemento = end_str('complemento');
        $bairro = end_str('bairro');
        $cidade = end_str('cidade');
        $uf = strtoupper(substr(end_str('uf'), 0, 2));

        if ($cep === '00000000' || $logradouro === '' || $numero === '' || $bairro === '' || $cidade === '' || $uf === '') {
            end_redirect();
        }

        if ($acao === 'create') {
            $stmt = $conn->prepare(
                'INSERT INTO enderecos_entrega (id_usuario, apelido, cep, logradouro, numero, complemento, bairro, cidade, uf)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('i' . str_repeat('s', 8), $idUsuario, $apelido, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $uf);
            $stmt->execute();
            $novoId = (int) $conn->insert_id;
            $stmt->close();

            // Primeiro endereço do usuário já nasce como principal.
            $stmtCount = $conn->prepare('SELECT COUNT(*) AS total FROM enderecos_entrega WHERE id_usuario = ?');
            $stmtCount->bind_param('i', $idUsuario);
            $stmtCount->execute();
            $total = (int) ($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtCount->close();
            if ($total <= 1) {
                $stmtPrincipal = $conn->prepare('UPDATE enderecos_entrega SET principal = 1 WHERE id_endereco = ?');
                $stmtPrincipal->bind_param('i', $novoId);
                $stmtPrincipal->execute();
                $stmtPrincipal->close();
            }
            $_SESSION['checkout_endereco_id'] = $novoId;
        } else {
            $idEndereco = (int) end_str('id');
            $stmt = $conn->prepare(
                'UPDATE enderecos_entrega SET apelido = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?
                 WHERE id_endereco = ? AND id_usuario = ?'
            );
            $stmt->bind_param('ssssssssii', $apelido, $cep, $logradouro, $numero, $complemento, $bairro, $cidade, $uf, $idEndereco, $idUsuario);
            $stmt->execute();
            $stmt->close();
        }
        break;

    case 'delete':
        $idEndereco = (int) end_str('id');
        $stmt = $conn->prepare('DELETE FROM enderecos_entrega WHERE id_endereco = ? AND id_usuario = ?');
        $stmt->bind_param('ii', $idEndereco, $idUsuario);
        $stmt->execute();
        $stmt->close();
        if (($_SESSION['checkout_endereco_id'] ?? null) === $idEndereco) {
            unset($_SESSION['checkout_endereco_id']);
        }
        break;

    case 'set-principal':
        $idEndereco = (int) end_str('id');
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE enderecos_entrega SET principal = 0 WHERE id_usuario = ?');
            $stmt->bind_param('i', $idUsuario);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE enderecos_entrega SET principal = 1 WHERE id_endereco = ? AND id_usuario = ?');
            $stmt->bind_param('ii', $idEndereco, $idUsuario);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
        }
        break;

    case 'selecionar':
        $idEndereco = (int) end_str('id');
        $stmt = $conn->prepare('SELECT id_endereco FROM enderecos_entrega WHERE id_endereco = ? AND id_usuario = ?');
        $stmt->bind_param('ii', $idEndereco, $idUsuario);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $_SESSION['checkout_endereco_id'] = $idEndereco;
        }
        $stmt->close();
        break;
}

end_redirect();
