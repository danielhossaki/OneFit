<?php
/**
 * helpers.php
 * Funções utilitárias usadas nas telas do backoffice (dashboard.php e
 * nos components/*.php). Mantidas separadas para não misturar lógica
 * de exibição com a marcação HTML.
 */

/**
 * Gera um <span> de status (verde = ativo / vermelho = inativo).
 * Usado nas tabelas de usuários, produtos, planos, profissionais etc.
 *
 * @param bool   $isActive  true = badge "ativo", false = badge "inativo"
 * @param string $onLabel   texto exibido quando ativo
 * @param string $offLabel  texto exibido quando inativo
 */
function bo_badge($isActive, $onLabel = 'Ativo', $offLabel = 'Inativo')
{
    $cls = $isActive ? 'bo-badge-active' : 'bo-badge-inactive';
    $label = $isActive ? $onLabel : $offLabel;
    return '<span class="bo-badge ' . $cls . '">' . $label . '</span>';
}

/**
 * Formata um valor numérico como moeda brasileira (R$ 1.234,56).
 */
function bo_money($v)
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/**
 * Converte um array PHP em JSON seguro para ser colocado dentro de
 * atributos HTML (usado nos botões "Editar" que abrem o modal já
 * preenchido, ex: onclick='boOpenForm(..., <?php echo bo_json($u); ?>)').
 * As flags JSON_HEX_* evitam que aspas/tags quebrem o HTML.
 */
function bo_json($data)
{
    return json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);
}
