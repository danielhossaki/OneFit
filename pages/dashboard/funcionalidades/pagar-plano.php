<?php
/**
 * funcionalidades/pagar-plano.php
 * Botão "Pagar" do modal components/modal-pagar-plano.php (tela "Histórico"
 * do aluno).
 *
 * Não existe gateway de pagamento integrado: o código Pix do modal é
 * demonstrativo e os campos de cartão são só visuais. Por isso esta rota NÃO
 * aprova cobranças nem ativa matrículas: uma requisição do próprio aluno não
 * pode confirmar pagamento. A ativação só acontece por
 * bo_matricula_confirmar_pagamento (includes/matriculas.php), chamada por uma
 * fonte confiável de confirmação. Nada é gravado aqui.
 */

$bo_papeis_permitidos = ['aluno'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

bo_flash('error', 'O pagamento online ainda não está disponível. Nenhuma cobrança foi confirmada por esta tela: a confirmação do pagamento é feita pela academia.');
bo_redirect('historico');
