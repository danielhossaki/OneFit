<?php

declare(strict_types=1);
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
session_start();
if (empty($_SESSION['id_usuario']) || (!empty($_SESSION['pagamento_matricula_sem_login']) && ($_POST['acao'] ?? '') !== 'matricula' && !in_array($_POST['acao'] ?? '', ['status', 'consultar'], true))) {
    http_response_code(401);
    echo '{"erro":"Autenticacao necessaria."}';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
require_once __DIR__ . '/../../services/pagamentos/PixCheckout.php';

use OneFit\Pagamentos\{MatriculaJaAtivaException, PixCheckout, PixSeguranca};

$pixEtapa = 'validacao';
try {
    try {
        PixSeguranca::csrf($_SESSION['csrf_token'] ?? null, $_POST['csrf_token'] ?? null);
    } catch (Throwable) {
        http_response_code(403);
        echo '{"erro":"Solicitacao invalida."}';
        exit;
    }
    $usuarioAutenticado = (int)$_SESSION['id_usuario'];
    $action = $_POST['acao'] ?? '';
    $cartHash = null;
    if ($action === 'matricula') {
        require_once __DIR__ . '/../../config/matricula-wizard.php';
        MatriculaWizard::confirmar($_SESSION, $_POST);
    }
    if (time() - ($_SESSION['pix_ultima_requisicao'] ?? 0) < 2) {
        http_response_code(429);
        header('Retry-After: 2');
        exit;
    }
    $_SESSION['pix_ultima_requisicao'] = time();
    $cart = $_SESSION['carrinho'] ?? [];
    $address = (int)($_SESSION['checkout_endereco_id'] ?? 0);
    $ship = (int)($_SESSION['checkout_transportadora_id'] ?? 0);
    // Serialize submissions within the authenticated session; polling releases it.
    if (in_array($action, ['status', 'consultar'], true)) session_write_close();
    require __DIR__ . '/../../config/conn.php';
    $service = new PixCheckout($conn);
    if (in_array($action, ['status', 'consultar'], true)) {
        $reference = (string)($_POST['referencia'] ?? '');
        $data = $service->dados($usuarioAutenticado, $reference, true, $action === 'consultar');
        if ($data['origem'] === 'pedido' && $data['status'] === 'aprovado') {
            session_start();
            if (($_SESSION['pix_pedido_referencia'] ?? '') === $reference && ($_SESSION['pix_carrinho_hash'] ?? '') === hash('sha256', json_encode($_SESSION['carrinho'] ?? []))) {
                $_SESSION['carrinho'] = [];
                unset($_SESSION['checkout_endereco_id'], $_SESSION['checkout_transportadora_id']);
            }
            session_write_close();
        }
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'matricula') {
        $pixEtapa = 'matricula_servico';
        $existing = $_SESSION['matricula_wizard']['referencia'] ?? null;
        if ($existing) {
            $service->dados($usuarioAutenticado, $existing);
            header('Location: pix.php?r=' . $existing, true, 303);
            exit;
        }
        $result = $service->matricula($usuarioAutenticado, (int)($_POST['id_plano'] ?? 0));
    } elseif ($action === 'pedido') {
        $cartHash = hash('sha256', json_encode($cart));
        if (isset($_SESSION['pix_pedido_referencia']) && ($_SESSION['pix_carrinho_hash'] ?? '') === $cartHash) {
            $previous = $service->dados($usuarioAutenticado, $_SESSION['pix_pedido_referencia']);
            if ($previous['status'] === 'aprovado') {
                header('Location: pix.php?r=' . $_SESSION['pix_pedido_referencia'], true, 303);
                exit;
            }
        }
        $result = $service->pedido($usuarioAutenticado, $cart, $address, $ship, (string)($_POST['cashback_usado'] ?? '0.00'));
    } else throw new RuntimeException();
    $ref = $service->referencia($usuarioAutenticado, $result['id_cobranca']);
    if ($action === 'matricula') $_SESSION['matricula_wizard']['referencia'] = $ref;
    if ($action === 'pedido') {
        $_SESSION['pix_pedido_referencia'] = $ref;
        $_SESSION['pix_carrinho_hash'] = $cartHash;
    }
    session_write_close();
    header('Location: pix.php?r=' . $ref, true, 303);
    exit;
} catch (MatriculaJaAtivaException) {
    http_response_code(409);
    echo '{"erro":"Voce ja possui uma matricula ativa neste plano. Escolha outro plano para continuar."}';
} catch (Throwable $erro) {
    error_log('OneFit Pix: falha controlada em ' . $pixEtapa . ' (' . basename($erro->getFile()) . ':' . $erro->getLine() . ' ' . get_class($erro) . ')');
    http_response_code(409);
    echo '{"erro":"Nao foi possivel processar o pagamento. Seus dados foram mantidos; tente retomar a solicitacao."}';
}
