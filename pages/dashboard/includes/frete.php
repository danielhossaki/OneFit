<?php
/**
 * includes/frete.php
 * Helpers puros de CEP/frete, sem dependência do bootstrap do dashboard —
 * podem ser incluídos tanto pelo checkout (pages/carrinho) quanto pelas
 * telas administrativas de vendas/transportadoras.
 */

/** Remove tudo que não for dígito e preenche com zeros à esquerda até 8 posições. */
function bo_normalizar_cep(string $cep): string
{
    $digitos = preg_replace('/\D/', '', $cep) ?? '';
    return str_pad(substr($digitos, 0, 8), 8, '0', STR_PAD_LEFT);
}

/**
 * Todas as transportadoras ativas que têm uma faixa de CEP cobrindo o CEP
 * informado, da mais barata para a mais cara. Não depende de vendedor: as
 * transportadoras são sempre globais (cadastradas pelo admin). Usada para
 * deixar o cliente escolher o tipo de entrega no checkout, em vez de só
 * aplicar automaticamente a opção mais barata.
 */
function bo_listar_opcoes_frete(mysqli $conn, string $cep): array
{
    $cepNormalizado = bo_normalizar_cep($cep);

    $stmt = $conn->prepare(
        "SELECT f.id_transportadora, t.nome, t.tipo, f.valor_frete, f.prazo_dias
         FROM faixas_cep_frete f
         INNER JOIN transportadoras t ON t.id_transportadora = f.id_transportadora
         WHERE t.status = 'ativo' AND ? BETWEEN f.cep_inicial AND f.cep_final
         ORDER BY f.valor_frete ASC"
    );
    $stmt->bind_param('s', $cepNormalizado);
    $stmt->execute();
    $res = $stmt->get_result();

    $opcoes = [];
    while ($row = $res->fetch_assoc()) {
        $opcoes[] = [
            'id_transportadora' => (int) $row['id_transportadora'],
            'nome' => $row['nome'],
            'tipo' => $row['tipo'],
            'valor_frete' => (float) $row['valor_frete'],
            'prazo_dias' => (int) $row['prazo_dias'],
        ];
    }
    $stmt->close();

    return $opcoes;
}

/**
 * Entre as opções de frete disponíveis para o CEP, retorna a de menor valor
 * (usada como sugestão inicial/padrão antes do cliente escolher).
 */
function bo_calcular_frete_mais_barato(mysqli $conn, string $cep): ?array
{
    $opcoes = bo_listar_opcoes_frete($conn, $cep);
    return $opcoes[0] ?? null;
}
