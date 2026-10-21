<?php
/**
 * components/section-recepcao.php
 * Painel da recepção (cargo recepcionista):
 *  - Alunos, Pagamentos, Modalidades e Cashback: só consulta. Os componentes
 *    saem sem botões, formulários nem modais de gravação, e nenhuma rota de
 *    funcionalidades/ aceita o cargo (_shared.php recusa no servidor);
 *  - Cadastro facial (D5): a própria recepcionista, outras recepcionistas e
 *    cargos não administrativos. Quem pode fotografar quem é decidido em
 *    actions/fotos-faciais.php a cada requisição, nunca por esta tela;
 *  - Marketplace: link para a loja, como para os demais cargos;
 *  - Configurações da própria conta (section-configuracoes.php).
 * Depende de $usuarios, $pagamentos, $cashbackResumo, $cashbackTransacoes e
 * $modalidadesAdm (includes/db-data.php).
 */
$boPerfilSecao = 'recepcionista';
$boSomenteLeitura = true;
require __DIR__ . '/section-alunos.php';
require __DIR__ . '/section-pagamentos.php';
require __DIR__ . '/section-fotos-faciais.php';
require __DIR__ . '/section-modalidades.php';
require __DIR__ . '/section-cashbacks.php';
