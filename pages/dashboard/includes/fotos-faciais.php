<?php
/**
 * includes/fotos-faciais.php
 * Regras do cadastro facial, usadas por actions/fotos-faciais.php e pelos testes:
 *  - operador e pessoa fotografada conferidos no banco, na mesma transação, pela
 *    política central (onefitAvaliarCadastroFacial em config/papeis.php);
 *  - até BO_FOTOS_FACIAIS_MAX_ATIVAS fotos ativas por pessoa. A seguinte só entra
 *    com confirmação explícita e desativa (ativo = 0) a mais antiga: nada é apagado;
 *  - operador e pessoa ficam travados (FOR UPDATE) até o fim da gravação, então
 *    requisições simultâneas não passam do limite;
 *  - auditoria em auditoria_eventos sem imagem nem dado biométrico.
 */
require_once __DIR__ . '/../../../config/papeis.php';
require_once __DIR__ . '/funcionarios.php';

const BO_FOTOS_FACIAIS_MAX_ATIVAS = 3;
const BO_FOTOS_FACIAIS_MAX_BYTES = 5 * 1024 * 1024;

/** Recusa esperada (permissão, dado inválido, confirmação pendente); nada foi gravado. */
final class FotoFacialRecusada extends RuntimeException
{
    public function __construct(string $mensagem, public readonly int $status, public readonly array $dados = [])
    {
        parent::__construct($mensagem);
    }
}

function bo_foto_facial_situacao(mysqli $conn, int $id, bool $travar = false): ?array
{
    return onefitBuscarSituacaoUsuario($conn, $id, $travar);
}

/** Pessoas que o operador pode fotografar, com o nº de fotos ativas. Somente leitura; o CPF só é usado na busca. */
function bo_fotos_faciais_listar(mysqli $conn, int $idOperador, string $termo): array
{
    $operador = bo_foto_facial_situacao($conn, $idOperador);
    $restrito = onefitAdminRestrito($conn, $idOperador);
    $termo = mb_substr(trim($termo), 0, 100, 'UTF-8');
    $busca = '%' . $termo . '%';
    $cargos = array_keys(onefitPoliticasPapeis());
    $marcadores = implode(', ', array_fill(0, count($cargos), '?'));
    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.nome, u.tipo_usuario, u.status,
                (SELECT COUNT(*) FROM usuarios_fotos_faciais f WHERE f.id_usuario = u.id_usuario AND f.ativo = 1) AS fotos_ativas
         FROM usuarios u
         WHERE u.status = 'ativo' AND u.tipo_usuario IN ($marcadores)
           AND (? = '' OR u.nome LIKE ? OR u.cpf LIKE ? OR CAST(u.id_usuario AS CHAR) LIKE ?)
         ORDER BY u.nome, u.id_usuario"
    );
    $parametros = array_merge($cargos, [$termo, $busca, $busca, $busca]);
    $stmt->bind_param(str_repeat('s', count($parametros)), ...$parametros);
    $stmt->execute();
    $pessoas = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $linha) {
        if (!onefitAvaliarCadastroFacial($operador, $linha, $restrito)['permitido']) {
            continue;
        }
        $pessoas[] = [
            'id' => (int) $linha['id_usuario'],
            'nome' => $linha['nome'],
            'tipo_usuario' => $linha['tipo_usuario'],
            'fotos_ativas' => (int) $linha['fotos_ativas'],
            'foto_cadastrada' => (int) $linha['fotos_ativas'] > 0,
        ];
    }
    $stmt->close();
    return $pessoas;
}

/** Confere o conteúdo da imagem (não o nome nem o tipo informados pelo navegador). */
function bo_foto_facial_validar_imagem(string $imagem): void
{
    if ($imagem === '' || strlen($imagem) > BO_FOTOS_FACIAIS_MAX_BYTES) {
        throw new FotoFacialRecusada(onefitTraduzir('A imagem está vazia, excede 5 MB ou não foi enviada corretamente.'), 422);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($imagem);
    $info = getimagesizefromstring($imagem);
    $aceitos = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG];
    if (!is_array($info) || !isset($aceitos[$mime]) || $info[2] !== $aceitos[$mime]
        || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 16777216) {
        throw new FotoFacialRecusada(onefitTraduzir('Formato de imagem inválido. Envie JPEG ou PNG de até 16 megapixels.'), 422);
    }
    if (!function_exists('imagecreatefromstring')) {
        error_log('ONE FIT: extensão GD indisponível para validar imagens faciais.');
        throw new FotoFacialRecusada(onefitTraduzir('A validação de imagens está indisponível no servidor. Avise o administrador.'), 503);
    }
    $decodificada = @imagecreatefromstring($imagem);
    if ($decodificada === false) {
        throw new FotoFacialRecusada(onefitTraduzir('A imagem não pôde ser decodificada. Capture outra foto.'), 422);
    }
    imagedestroy($decodificada);
}

/**
 * Grava uma foto ativa da pessoa. Com BO_FOTOS_FACIAIS_MAX_ATIVAS fotos ativas,
 * exige $substituirMaisAntiga (senão recusa com 409 e requer_confirmacao) e
 * desativa as mais antigas para que, com a nova, restem exatamente o máximo.
 * Retorna ['id_foto_facial' => int, 'fotos_ativas' => int, 'desativadas' => int[]].
 */
function bo_foto_facial_cadastrar(mysqli $conn, int $idOperador, int $idPessoa, string $imagem, bool $substituirMaisAntiga): array
{
    bo_foto_facial_validar_imagem($imagem);
    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start facial photo transaction.');
    }
    try {
        // Operador e pessoa na mesma consulta, travados em ordem de id (sem deadlock entre requisições).
        $stmt = $conn->prepare('SELECT id_usuario, tipo_usuario, status FROM usuarios WHERE id_usuario IN (?, ?) ORDER BY id_usuario FOR UPDATE');
        $stmt->bind_param('ii', $idOperador, $idPessoa);
        $stmt->execute();
        $linhas = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), null, 'id_usuario');
        $stmt->close();
        $operador = $linhas[$idOperador] ?? null;
        $pessoa = $linhas[$idPessoa] ?? null;
        $avaliacao = onefitAvaliarCadastroFacial($operador, $pessoa, onefitAdminRestrito($conn, $idOperador));
        if (!$avaliacao['permitido']) {
            throw $avaliacao['motivo'] === 'operador_sem_permissao'
                ? new FotoFacialRecusada(onefitTraduzir('Você não tem permissão para gerenciar fotos faciais.'), 403)
                // Pessoa inexistente, inativa, de cargo desconhecido ou admin: mesma resposta, sem revelar qual.
                : new FotoFacialRecusada(onefitTraduzir('Pessoa ativa não encontrada ou não permitida para cadastro facial.'), 404);
        }

        $stmt = $conn->prepare('SELECT id_foto_facial FROM usuarios_fotos_faciais WHERE id_usuario = ? AND ativo = 1 ORDER BY data_cadastro, id_foto_facial FOR UPDATE');
        $stmt->bind_param('i', $idPessoa);
        $stmt->execute();
        $ativas = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_foto_facial'));
        $stmt->close();

        $desativar = [];
        if (count($ativas) >= BO_FOTOS_FACIAIS_MAX_ATIVAS) {
            if (!$substituirMaisAntiga) {
                throw new FotoFacialRecusada(
                    onefitTraduzir('Esta pessoa já tem {total} fotos faciais ativas. Para salvar a nova, a foto ativa mais antiga será desativada e mantida no histórico. Deseja continuar?', ['{total}' => (string) count($ativas)]),
                    409,
                    ['requer_confirmacao' => true, 'fotos_ativas' => count($ativas)]
                );
            }
            $desativar = array_slice($ativas, 0, count($ativas) - BO_FOTOS_FACIAIS_MAX_ATIVAS + 1);
            $marcadores = implode(', ', array_fill(0, count($desativar), '?'));
            $stmt = $conn->prepare("UPDATE usuarios_fotos_faciais SET ativo = 0 WHERE id_usuario = ? AND ativo = 1 AND id_foto_facial IN ($marcadores)");
            $parametros = array_merge([$idPessoa], $desativar);
            $stmt->bind_param(str_repeat('i', count($parametros)), ...$parametros);
            $stmt->execute();
            if ($stmt->affected_rows !== count($desativar)) {
                throw new RuntimeException('Facial photo history changed during replacement.');
            }
            $stmt->close();
        }

        $vazio = '';
        $stmt = $conn->prepare('INSERT INTO usuarios_fotos_faciais (id_usuario, imagem_blob, ativo) VALUES (?, ?, 1)');
        $stmt->bind_param('ib', $idPessoa, $vazio);
        if (!$stmt->send_long_data(1, $imagem) || !$stmt->execute()) {
            throw new RuntimeException('Could not store facial photo bytes.');
        }
        $idFoto = (int) $stmt->insert_id;
        $stmt->close();

        bo_auditoria_registrar($conn, $desativar ? 'substituir' : 'cadastrar', $idOperador, $idPessoa, [
            'id_foto_facial' => $idFoto,
            'desativadas' => $desativar,
            'cargo' => onefitPapelCanonico($pessoa['tipo_usuario']),
        ], 'foto_facial');

        if (!$conn->commit()) {
            throw new RuntimeException('Could not commit facial photo transaction.');
        }
        return ['id_foto_facial' => $idFoto, 'fotos_ativas' => count($ativas) - count($desativar) + 1, 'desativadas' => $desativar];
    } catch (Throwable $erro) {
        $conn->rollback();
        throw $erro;
    }
}
