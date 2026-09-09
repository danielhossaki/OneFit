<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../dashboard/includes/frete.php';
require_once __DIR__ . '/../../config/env.php';

function cart_pix_local(): bool
{
    // Usa a configuração da aplicação, nunca o Host enviado pelo navegador.
    $host = strtolower((string) parse_url(onefitEnv('APP_URL', 'http://localhost/AN25/OneFit/'), PHP_URL_HOST));
    return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

function cart_frete_local_pendente(array $itensPorVendedor): array
{
    // SIMULAÇÃO ACADÊMICA: sem cotação, sem transportadora fictícia e sem
    // cobrança de frete. A data de recebimento continua não definida.
    $grupos = [];
    foreach ($itensPorVendedor as $id => $dados) {
        $grupos[$id] = ['id_transportadora' => null, 'valor_frete' => 0.0];
    }
    return ['porVendedor' => $grupos, 'total' => 0.0];
}

/**
 * Agrupa os itens do carrinho por vendedor (id_vendedor NULL = produto
 * legado "ONE FIT", tratado como grupo 0) e aplica a transportadora
 * escolhida (tipo de entrega) a cada grupo. Se nenhuma transportadora foi
 * escolhida ainda (ou a escolhida não cobre mais o CEP), cai para a opção
 * mais barata disponível. Retorna null quando não há nenhuma transportadora
 * que cubra o CEP informado.
 */
function cart_calcular_fretes(mysqli $conn, array $itensPorVendedor, string $cep, ?int $idTransportadoraEscolhida = null): ?array
{
    $opcoes = bo_listar_opcoes_frete($conn, $cep);
    if (empty($opcoes)) {
        return null;
    }

    $opcaoEscolhida = $opcoes[0];
    if ($idTransportadoraEscolhida !== null) {
        foreach ($opcoes as $opcao) {
            if ($opcao['id_transportadora'] === $idTransportadoraEscolhida) {
                $opcaoEscolhida = $opcao;
                break;
            }
        }
    }

    $fretes = [];
    $totalFrete = 0.0;
    foreach ($itensPorVendedor as $idVendedor => $dados) {
        $fretes[$idVendedor] = $opcaoEscolhida + ['vendedorNome' => $dados['nome']];
        $totalFrete += $opcaoEscolhida['valor_frete'];
    }
    return ['porVendedor' => $fretes, 'total' => round($totalFrete, 2), 'opcoes' => $opcoes, 'escolhida' => $opcaoEscolhida['id_transportadora']];
}

function cart_gravar_compra(mysqli $conn, int $idUsuario, array $carrinho, int $idEndereco, ?int $idTransportadoraEscolhida, array $post): array
{
    if (empty($carrinho)) {
        throw new DomainException('Seu carrinho está vazio.');
    }

    // Endereço de entrega: precisa ter sido escolhido/salvo antes (etapa
    // "checkout-endereco") e pertencer ao próprio usuário — nunca confia
    // em id_endereco_entrega vindo direto do POST.

    $stmtEndereco = $conn->prepare('SELECT * FROM enderecos_entrega WHERE id_endereco = ? AND id_usuario = ?');
    $stmtEndereco->bind_param('ii', $idEndereco, $idUsuario);
    $stmtEndereco->execute();
    $endereco = $stmtEndereco->get_result()->fetch_assoc();
    $stmtEndereco->close();
    if (!$endereco) {
        throw new DomainException('Escolha um endereço de entrega válido.');
    }

    $formaPagamento = $post['forma_pagamento'] ?? 'pix';
    // SIMULAÇÃO LOCAL/ACADÊMICA: finalizar PIX confirma a ação do usuário
    // para este projeto. Não há consulta bancária, webhook ou API PIX aqui.
    // Persistimos forma_pagamento='pix'; o pedido logístico nasce aguardando.
    if (!in_array($formaPagamento, ['pix', 'cartao'], true)) {
        throw new DomainException('Escolha uma forma de pagamento válida.');
    }
    $cashbackSolicitado = $post['cashback_usado'] ?? 0;
    if (!is_numeric($cashbackSolicitado) || !is_finite((float) $cashbackSolicitado) || (float) $cashbackSolicitado < 0) {
        throw new DomainException('Informe um valor de cashback válido.');
    }

    $ids = array_map('intval', array_keys($carrinho));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $conn->begin_transaction();
    try {
        // Serializa checkouts do mesmo comprador, inclusive em sessoes distintas.
        $usuario = $conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? FOR UPDATE');
        $usuario->bind_param('i', $idUsuario);
        $usuario->execute();
        $existeUsuario = $usuario->get_result()->fetch_assoc();
        $usuario->close();
        if (!$existeUsuario) throw new DomainException('Entre novamente para finalizar a compra.');
        // Saldo real de cashback do usuário (créditos - débitos, ignorando cancelados).
        $stmtSaldo = $conn->prepare("SELECT SUM(CASE WHEN tipo = 'credito' THEN valor ELSE -valor END) AS saldo FROM cashback WHERE id_usuario = ? AND status != 'cancelado'");
        $stmtSaldo->bind_param('i', $idUsuario);
        $stmtSaldo->execute();
        $saldoAtual = (float) ($stmtSaldo->get_result()->fetch_assoc()['saldo'] ?? 0);
        $stmtSaldo->close();

        // SELECT ... FOR UPDATE dentro da transação: trava as linhas dos produtos
        // do carrinho até o commit/rollback, evitando que duas finalizações
        // concorrentes vendam mais unidades do que o estoque realmente permite.
        $stmt = $conn->prepare("SELECT id_produto, id_vendedor, preco, desconto, cashback_valor, estoque, status FROM produtos WHERE id_produto IN ($placeholders) FOR UPDATE");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $produtosBanco = [];
        while ($row = $res->fetch_assoc()) {
            $produtosBanco[(int) $row['id_produto']] = $row;
        }
        $stmt->close();

        $itens = [];
        $totalCompra = 0.0;
        $cashbackGanho = 0.0;
        // Agrupado por vendedor (chave 0 = produto legado "ONE FIT", sem dono)
        // para calcular o frete de cada loja separadamente.
        $itensPorVendedor = [];
        foreach ($carrinho as $produtoId => $quantidade) {
            if (!isset($produtosBanco[$produtoId]) || $produtosBanco[$produtoId]['status'] !== 'ativo') {
                throw new DomainException('Um produto ficou indisponível. Revise o carrinho.');
            }
            $p = $produtosBanco[$produtoId];
            $quantidade = filter_var($quantidade, FILTER_VALIDATE_INT);
            // Estoque insuficiente: rejeita a compra inteira em vez de reduzir a
            // quantidade em silêncio, para o cliente não pagar/receber menos do
            // que via no carrinho nem finalizar um pedido maior que o estoque real.
            if ($quantidade === false || $quantidade <= 0 || $quantidade > (int) $p['estoque']) {
                throw new DomainException('Estoque insuficiente. Revise as quantidades do carrinho.');
            }
            if ((float) $p['preco'] < 0 || (float) $p['desconto'] < 0 || (float) $p['desconto'] > 100) {
                throw new DomainException('Um produto está com preço inválido. Revise o carrinho.');
            }
            $valorFinal = $p['desconto'] > 0
                ? round((float) $p['preco'] * (1 - (float) $p['desconto'] / 100), 2)
                : (float) $p['preco'];
            $subtotal = round($valorFinal * $quantidade, 2);

            $cashbackUnitario = (float) $p['cashback_valor'];
            $idVendedor = (int) ($p['id_vendedor'] ?? 0);

            $itens[] = [
                'id' => $produtoId,
                'idVendedor' => $idVendedor,
                'quantidade' => $quantidade,
                'precoUnitario' => $valorFinal,
                'subtotal' => $subtotal,
                'cashbackUnitario' => $cashbackUnitario,
            ];
            $itensPorVendedor[$idVendedor]['nome'] = $idVendedor > 0 ? 'Loja' : 'ONE FIT';
            $totalCompra += $subtotal;
            // Cashback do produto é um valor fixo em R$ por unidade (não mais %).
            $cashbackGanho += round($cashbackUnitario * $quantidade, 2);
        }

        if (empty($itens)) {
            throw new DomainException('Nenhum produto disponível no carrinho.');
        }

        $fretes = cart_calcular_fretes($conn, $itensPorVendedor, $endereco['cep'], $idTransportadoraEscolhida);
        if ($fretes === null && $formaPagamento === 'pix' && cart_pix_local()) {
            $fretes = cart_frete_local_pendente($itensPorVendedor);
        }
        if ($fretes === null) {
            throw new DomainException('Nenhuma entrega disponível para o CEP selecionado.');
        }
        $totalCompra = round($totalCompra + $fretes['total'], 2);

        $cashbackUsado = round(max(0, min((float) ($post['cashback_usado'] ?? 0), $saldoAtual, $totalCompra)), 2);

        $stmtPedido = $conn->prepare(
            'INSERT INTO pedido (id_usuario, id_endereco_entrega, valor_total, forma_pagamento, status, data_pedido,
                endereco_cep, endereco_logradouro, endereco_numero, endereco_complemento, endereco_bairro, endereco_cidade, endereco_uf)
             VALUES (?, ?, ?, ?, "aguardando", NOW(), ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtPedido->bind_param(
            'iidssssssss',
            $idUsuario,
            $idEndereco,
            $totalCompra,
            $formaPagamento,
            $endereco['cep'],
            $endereco['logradouro'],
            $endereco['numero'],
            $endereco['complemento'],
            $endereco['bairro'],
            $endereco['cidade'],
            $endereco['uf']
        );
        $stmtPedido->execute();
        $idPedido = (int) $conn->insert_id;
        $stmtPedido->close();

        $stmtItem = $conn->prepare(
            'INSERT INTO pedido_item (id_pedido, id_produto, id_vendedor, quantidade, preco_unitario, subtotal, id_transportadora, valor_frete)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtEstoque = $conn->prepare('UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ?');
        // O frete é cobrado uma vez por vendedor: para não duplicar o valor
        // ao somar pedido_item.valor_frete, ele é lançado só no primeiro
        // item de cada grupo de vendedor (os demais itens do mesmo grupo
        // ficam com valor_frete = 0).
        $vendedorJaCobrado = [];
        foreach ($itens as $item) {
            $idVendedorItem = $item['idVendedor'];
            $idVendedorSql = $idVendedorItem > 0 ? $idVendedorItem : null;
            $idTransportadoraItem = $fretes['porVendedor'][$idVendedorItem]['id_transportadora'];
            $valorFreteItem = 0.0;
            if (empty($vendedorJaCobrado[$idVendedorItem])) {
                $valorFreteItem = $fretes['porVendedor'][$idVendedorItem]['valor_frete'];
                $vendedorJaCobrado[$idVendedorItem] = true;
            }

            $stmtItem->bind_param(
                'iiiiddid',
                $idPedido,
                $item['id'],
                $idVendedorSql,
                $item['quantidade'],
                $item['precoUnitario'],
                $item['subtotal'],
                $idTransportadoraItem,
                $valorFreteItem
            );
            $stmtItem->execute();
            $stmtEstoque->bind_param('ii', $item['quantidade'], $item['id']);
            $stmtEstoque->execute();
        }
        $stmtItem->close();
        $stmtEstoque->close();

        if ($cashbackUsado > 0) {
            $descUso = 'Uso de cashback no pedido #' . $idPedido;
            $stmtCbUso = $conn->prepare('INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, "debito", "uso", ?, "utilizado", NOW())');
            $stmtCbUso->bind_param('ids', $idUsuario, $cashbackUsado, $descUso);
            $stmtCbUso->execute();
            $stmtCbUso->close();
        }

        if ($cashbackGanho > 0) {
            $descGanho = 'Cashback do pedido #' . $idPedido;
            $stmtCbGanho = $conn->prepare('INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, "credito", "produto", ?, "disponivel", NOW())');
            $stmtCbGanho->bind_param('ids', $idUsuario, $cashbackGanho, $descGanho);
            $stmtCbGanho->execute();
            $stmtCbGanho->close();
        }

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return [
        'id' => $idPedido,
        'transacao' => 'TRX-' . str_pad((string) $idPedido, 4, '0', STR_PAD_LEFT),
        'total' => $totalCompra,
        'frete' => $fretes['total'],
        'cashbackUsado' => $cashbackUsado,
        'cashbackGanho' => $cashbackGanho,
        'formaPagamento' => $formaPagamento,
    ];
}

/** O chamador mantem o lock da sessao PHP durante toda a finalizacao. */
function cart_processar_checkout(mysqli $conn, array &$sessao, array $post): array
{
    $idUsuario = (int) ($sessao['id_usuario'] ?? 0);
    if ($idUsuario <= 0) throw new DomainException('Entre novamente para finalizar a compra.');
    $csrf = $post['csrf_token'] ?? '';
    if (!is_string($csrf) || $csrf === '' || !hash_equals($sessao['csrf_token'] ?? '', $csrf)) {
        throw new DomainException('Sua sessão expirou. Atualize a página e tente novamente.');
    }
    $token = $post['checkout_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($sessao['checkout_token'] ?? '', $token)) {
        throw new DomainException('Este checkout expirou ou já foi enviado. Revise o carrinho e tente novamente.');
    }
    $pedido = cart_gravar_compra($conn, $idUsuario, $sessao['carrinho'] ?? [],
        (int) ($sessao['checkout_endereco_id'] ?? 0),
        isset($sessao['checkout_transportadora_id']) ? (int) $sessao['checkout_transportadora_id'] : null, $post);
    // Consome o token e limpa o carrinho SOMENTE depois do COMMIT.
    $sessao['carrinho'] = [];
    unset($sessao['checkout_endereco_id'], $sessao['checkout_transportadora_id'], $sessao['checkout_token']);
    $sessao['ultimo_pedido'] = $pedido;
    return $pedido;
}
