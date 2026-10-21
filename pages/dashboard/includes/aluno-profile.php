<?php
require_once __DIR__ . '/../../../config/interface.php';
/** Medidas e IMC derivados dos campos existentes em usuarios. */
function bo_aluno_medida($valor, float $maximo): ?float
{
    $texto = str_replace(',', '.', trim((string) $valor));
    if (!preg_match('/^\d+(?:\.\d+)?$/D', $texto)) return null;
    $numero = round((float) $texto, 2); // Mesma precisão DECIMAL(5,2) do banco.
    return is_finite($numero) && $numero > 0 && $numero <= $maximo ? $numero : null;
}

function bo_aluno_imc($altura, $peso): array
{
    $altura = bo_aluno_medida($altura, 3);
    $peso = bo_aluno_medida($peso, 500);
    if ($altura === null || $peso === null || $altura * $altura == 0) return ['valor' => null, 'classe' => onefitTraduzir('Não informado')];
    $imc = $peso / ($altura * $altura);
    if (!is_finite($imc)) return ['valor' => null, 'classe' => onefitTraduzir('Não informado')];
    $classe = match (true) {
        $imc < 18.5 => onefitTraduzir('Abaixo do peso'),
        $imc < 25 => onefitTraduzir('Peso adequado'),
        $imc < 30 => onefitTraduzir('Sobrepeso'),
        $imc < 35 => onefitTraduzir('Obesidade grau I'),
        $imc < 40 => onefitTraduzir('Obesidade grau II'),
        default => onefitTraduzir('Obesidade grau III'),
    };
    return ['valor' => $imc, 'classe' => $classe];
}
