<?php
/**
 * funcionalidades/permissoes.php
 * Tela "Permissões" desativada. Ela sincronizava usuarios.tipo_usuario a
 * partir da tabela `permissoes`, permitindo promover ou rebaixar contas sem
 * os controles de cargo. Agora o cargo vem apenas de usuarios.tipo_usuario
 * (config/papeis.php) e este handler recusa qualquer requisição, mesmo
 * enviada diretamente. A tabela `permissoes` permanece intacta.
 */

require __DIR__ . '/_shared.php';

bo_flash('error', 'As telas Permissões e Funções foram desativadas e não alteram mais cargos.');
bo_redirect('dashboard');
