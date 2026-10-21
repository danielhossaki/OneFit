<?php
/**
 * components/section-recepcao.php
 * Painel da recepção (cargo recepcionista):
 *  - Agendamentos: criação para aluno ativo; busca e autorização na rota;
 *  - Alunos, Pagamentos, Modalidades e Cashback: só consulta, com busca de
 *    cashback por CPF autorizada em rota própria;
 *  - Cadastro facial (D5): a própria recepcionista, outras recepcionistas e
 *    cargos não administrativos. Quem pode fotografar quem é decidido em
 *    actions/fotos-faciais.php a cada requisição, nunca por esta tela;
 *  - Cashback dos clientes: indicadores, saldo por cliente e movimentações,
 *    só consulta exceto a consulta individual por CPF;
 *  - Marketplace (section-marketplace-recepcao.php): comprar produtos na loja,
 *    como antes, e consultar pedidos e vendas, só consulta;
 *  - Configurações da própria conta (section-configuracoes.php).
 * Depende de $usuarios, $pagamentos, $cashbackResumo, $cashbackTransacoes,
 * $cashbackClientes, $modalidadesAdm e $recepcaoPedidos (includes/db-data.php).
 */
$boPerfilSecao = 'recepcionista';
$boSomenteLeitura = true;
require __DIR__ . '/section-recepcao-agenda.php';
require __DIR__ . '/section-alunos.php';
require __DIR__ . '/section-pagamentos.php';
require __DIR__ . '/section-fotos-faciais.php';
require __DIR__ . '/section-modalidades.php';
require __DIR__ . '/section-cashbacks.php';
require __DIR__ . '/section-marketplace-recepcao.php';
