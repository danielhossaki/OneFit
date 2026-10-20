<?php
/**
 * funcionalidades/funcoes.php
 * Tela "Funções" desativada junto com "Permissões": os nomes da tabela
 * `funcao` eram copiados para usuarios.tipo_usuario. Este handler recusa
 * qualquer requisição, mesmo enviada diretamente. A tabela `funcao`
 * permanece intacta.
 */

require __DIR__ . '/_shared.php';

bo_flash('error', 'As telas Permissões e Funções foram desativadas e não alteram mais cargos.');
bo_redirect('dashboard');
