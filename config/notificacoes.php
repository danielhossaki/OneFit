<?php
require_once __DIR__ . '/interface.php';
/** Helpers internos: carregar após config/conn.php. Não são endpoints públicos. */

function notificacaoLinkSeguro(?string $link): ?string
{
    if ($link === null || trim($link) === '') return null;
    $link = trim($link);
    // Apenas caminhos locais absolutos: impede javascript:, URLs externas e //host.
    if (strlen($link) > 2048 || !preg_match('~^/(?!/)~', $link)
        || preg_match('/[\\\\\x00-\x20]/', $link)) {
        throw new InvalidArgumentException('O link deve ser um caminho local iniciado por /.');
    }
    return $link;
}

/**
 * Cria um aviso interno e retorna seu ID, ou null quando a categoria está desativada.
 * $usuarioId deve vir da sessão ou do destinatário validado pelo backend, nunca
 * diretamente de um campo enviado pelo navegador. Este helper não envia e-mails.
 * Tipos: treino, agendamento, compra, oferta; info é um aviso geral sem categoria.
 * Eventos dessas categorias devem usar o tipo correspondente, não info.
 */
function criarNotificacao(int $usuarioId, string $titulo, string $mensagem, string $tipo = 'info', ?string $link = null): ?int
{
    global $conn;
    $titulo = trim($titulo);
    $mensagem = trim($mensagem);
    $tipo = trim($tipo);
    if ($usuarioId <= 0 || $titulo === '' || mb_strlen($titulo) > 150
        || $mensagem === '' || strlen($mensagem) > 65535
        || $tipo === '' || mb_strlen($tipo) > 50) {
        throw new InvalidArgumentException('Dados de notificação inválidos.');
    }
    $link = notificacaoLinkSeguro($link);
    // Mapeamento único entre tipos internos e os switches de Configurações.
    // notificacoes_email controla outro canal e não participa desta decisão.
    $categorias = [
        'treino' => 'lembretes_treino',
        'agendamento' => 'avisos_agendamentos',
        'compra' => 'atualizacoes_compras',
        'oferta' => 'ofertas_novidades',
    ];
    if ($tipo !== 'info' && !isset($categorias[$tipo])) {
        throw new InvalidArgumentException('Tipo de notificação desconhecido. Use treino, agendamento, compra, oferta ou info.');
    }
    if (isset($categorias[$tipo])) {
        // Usa os padrões da própria tabela para contas sem preferências salvas.
        $stmt = $conn->prepare('INSERT INTO preferencias_usuario (id_usuario) VALUES (?) ON DUPLICATE KEY UPDATE id_usuario = VALUES(id_usuario)');
        $stmt->bind_param('i', $usuarioId);
        $stmt->execute();
        $stmt->close();
        $coluna = $categorias[$tipo]; // Identificador exclusivamente da lista interna.
        // Consulta a preferência na própria inserção, sem intervalo entre ler e gravar.
        $stmt = $conn->prepare(
            "INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, link, criada_em)
             SELECT ?, ?, ?, ?, ?, UTC_TIMESTAMP()
             FROM preferencias_usuario WHERE id_usuario = ? AND {$coluna} = 1"
        );
        $stmt->bind_param('issssi', $usuarioId, $titulo, $mensagem, $tipo, $link, $usuarioId);
    } else {
        $stmt = $conn->prepare('INSERT INTO notificacoes (usuario_id, titulo, mensagem, tipo, link, criada_em) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
        $stmt->bind_param('issss', $usuarioId, $titulo, $mensagem, $tipo, $link);
    }
    $stmt->execute();
    $id = $stmt->affected_rows === 1 ? (int) $stmt->insert_id : null;
    $stmt->close();
    return $id;
}

function buscarNotificacoes(int $usuarioId): array
{
    global $conn;
    // As datas geradas pelos helpers são armazenadas em UTC.
    $stmt = $conn->prepare('SELECT id, titulo, mensagem, tipo, link, lida_em, criada_em FROM notificacoes WHERE usuario_id = ? ORDER BY criada_em DESC, id DESC LIMIT 30');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($itens as &$item) {
        $item['id'] = (int) $item['id'];
        try { $item['link'] = notificacaoLinkSeguro($item['link']); }
        catch (InvalidArgumentException $erro) { $item['link'] = null; }
    }
    unset($item);
    $stmt->close();
    $stmt = $conn->prepare('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $naoLidas = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return ['notificacoes' => $itens, 'nao_lidas' => $naoLidas];
}

function marcarNotificacoesComoLidas(int $usuarioId): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE notificacoes SET lida_em = UTC_TIMESTAMP() WHERE usuario_id = ? AND lida_em IS NULL');
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $stmt->close();
}

/** Call only after the checkout COMMIT. Deduplication and delivery are atomic. */
function notificarCompraBackoffice(mysqli $db, int $pedidoId): int
{
    require_once __DIR__ . '/interface.php';
    $stmt = $db->prepare("SELECT p.id_pedido, p.valor_total, p.data_pedido, u.tipo_usuario
        FROM pedido p JOIN usuarios u ON u.id_usuario = p.id_usuario
        WHERE p.id_pedido = ? AND u.tipo_usuario IN ('aluno', 'profissional')");
    $stmt->bind_param('i', $pedidoId);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$pedido) return 0;
    $ids = $db->query("SELECT id_usuario FROM usuarios WHERE tipo_usuario = 'admin' AND status = 'ativo'")->fetch_all(MYSQLI_ASSOC);
    $count = 0;
    foreach ($ids as $admin) {
        $id = (int) $admin['id_usuario'];
        $db->begin_transaction();
        try {
            // Lock and recheck permission; never trust a role supplied by the buyer/session.
            $check = $db->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'admin' AND status = 'ativo' FOR UPDATE");
            $check->bind_param('i', $id);
            $check->execute();
            $allowed = (bool) $check->get_result()->fetch_assoc();
            $check->close();
            if (!$allowed) { $db->rollback(); continue; }
            $evento = 'marketplace.pedido.' . $pedidoId;
            $claim = $db->prepare('INSERT INTO notificacoes_eventos (evento, usuario_id) VALUES (?, ?)');
            $claim->bind_param('si', $evento, $id);
            try { $claim->execute(); }
            catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1062) throw $e;
                $claim->close(); $db->rollback(); continue;
            }
            $claim->close();
            $lang = $db->prepare('SELECT idioma FROM preferencias_usuario WHERE id_usuario = ?');
            $lang->bind_param('i', $id); $lang->execute();
            $locale = $lang->get_result()->fetch_assoc()['idioma'] ?? 'pt-BR'; $lang->close();
            $title = onefitTraduzir('Novo pedido no marketplace', [], $locale);
            $type = onefitTraduzir($pedido['tipo_usuario'] === 'profissional' ? 'Profissional' : 'Aluno', [], $locale);
            $message = onefitTraduzir('{tipo} · Pedido #{pedido} · R$ {valor} · {data}', [
                '{tipo}' => $type, '{pedido}' => (string) $pedidoId,
                '{valor}' => number_format((float) $pedido['valor_total'], 2, ',', '.'),
                '{data}' => date('d/m/Y H:i', strtotime($pedido['data_pedido'])),
            ], $locale);
            $path = rtrim((string) parse_url(defined('BASE_URL') ? BASE_URL : onefitEnv('APP_URL'), PHP_URL_PATH), '/');
            $link = notificacaoLinkSeguro($path . '/pages/dashboard/dashboard.php?section=vendas&pedido=' . $pedidoId);
            $insert = $db->prepare("INSERT INTO notificacoes (usuario_id,titulo,mensagem,tipo,link,criada_em) VALUES (?,?,?,'info',?,UTC_TIMESTAMP())");
            $insert->bind_param('isss', $id, $title, $message, $link);
            $insert->execute(); $insert->close();
            $db->commit(); $count++;
        } catch (Throwable $e) { $db->rollback(); throw $e; }
    }
    return $count;
}
