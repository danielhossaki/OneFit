<?php
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
