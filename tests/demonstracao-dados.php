<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Coerência de uma base de demonstração (usuários, cargos, matrículas, cobranças, cadastros
// profissionais e fotos faciais), sem gravar nada: lê dentro de START TRANSACTION READ ONLY.
// Uso: php tests/demonstracao-dados.php BANCO_LOCAL   (banco local onefit_ensaio_reset_* ou onefit_interface_test_*)
// Não imprime dados pessoais: só contagens e ids.
$banco = $argv[1] ?? '';
if (!preg_match('/^onefit_(ensaio_reset_\d{8}_\d{6}|interface_test_\d{8}_\d{6}_[a-f0-9]{4})$/D', $banco)) {
    throw new RuntimeException('Use apenas um banco local de ensaio ou de teste.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', $banco);
$db->set_charset('utf8mb4');
$db->query('START TRANSACTION READ ONLY');

$checks = 0;
function ddCheck(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}
$n = static fn(string $sql): int => (int) $db->query($sql)->fetch_row()[0];
$tabelas = array_column($db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetch_all(), 0);
$existe = static fn(string $t): bool => in_array($t, $tabelas, true);

// Usuários e cargos.
$usuarios = $n('SELECT COUNT(*) FROM usuarios');
ddCheck($usuarios >= 15 && $usuarios <= 20, "Entre 15 e 20 usuários ($usuarios)");
$principais = ['admin@demo.onefit.test' => 'diretor', 'rodrigo@demo.onefit.test' => 'professor', 'daniel@demo.onefit.test' => 'recepcionista', 'fabricio@demo.onefit.test' => 'aluno', 'arthur@demo.onefit.test' => 'vendedor'];
foreach ($principais as $email => $cargo) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND tipo_usuario = ? AND status = 'ativo' AND email_verificado = 1 AND senha LIKE '\$2y\$%'");
    $stmt->bind_param('ss', $email, $cargo);
    $stmt->execute();
    ddCheck((int) $stmt->get_result()->fetch_row()[0] === 1, "Conta principal $email: $cargo, ativa, e-mail marcado e senha com hash");
}
ddCheck($n("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'diretor'") === 1, 'Um único diretor (a conta principal)');
ddCheck($n("SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'admin' AND status = 'ativo'") >= 1, 'Ao menos um administrador comum ativo');
ddCheck($n("SELECT COUNT(*) FROM usuarios WHERE email NOT LIKE '%@demo.onefit.test'") === 0, 'Todos os e-mails no domínio de demonstração');
ddCheck($n("SELECT COUNT(*) FROM usuarios WHERE email NOT IN ('" . implode("','", array_keys($principais)) . "') AND nome NOT LIKE '%(demo)'") === 0, 'Pessoas fictícias marcadas com (demo)');
ddCheck($n('SELECT COUNT(*) FROM (SELECT email FROM usuarios GROUP BY email HAVING COUNT(*) > 1) t') === 0, 'E-mails únicos');
ddCheck($n('SELECT COUNT(*) FROM (SELECT cpf FROM usuarios GROUP BY cpf HAVING COUNT(*) > 1) t') === 0, 'CPFs únicos');
$cpfInvalido = 0;
foreach ($db->query('SELECT cpf FROM usuarios')->fetch_all() as [$cpf]) {
    $ok = preg_match('/^\d{11}$/', $cpf) && !preg_match('/^(\d)\1{10}$/', $cpf);
    for ($p = 9; $ok && $p < 11; $p++) {
        $soma = 0;
        for ($i = 0; $i < $p; $i++) {
            $soma += (int) $cpf[$i] * (($p + 1) - $i);
        }
        $ok = (int) $cpf[$p] === ((10 * $soma) % 11) % 10;
    }
    $cpfInvalido += $ok ? 0 : 1;
}
ddCheck($cpfInvalido === 0, 'CPFs com dígitos verificadores válidos');
ddCheck($n("SELECT COUNT(*) FROM usuarios WHERE senha NOT LIKE '\$2y\$%'") === 0, 'Todas as senhas com hash bcrypt (password_hash)');
ddCheck($n('SELECT COUNT(*) FROM usuarios WHERE YEAR(data_nascimento) < 1900 OR MONTH(data_nascimento) = 0 OR DAYOFMONTH(data_nascimento) = 0') === 0, 'Datas de nascimento válidas');

// Cadastro profissional.
ddCheck($n("SELECT COUNT(*) FROM usuarios u WHERE u.tipo_usuario IN ('professor','profissional') AND NOT EXISTS (SELECT 1 FROM cadastro_profissional cp WHERE cp.id_usuario = u.id_usuario)") === 0, 'Todo professor tem cadastro profissional');
ddCheck($n("SELECT COUNT(*) FROM cadastro_profissional cp LEFT JOIN usuarios u ON u.id_usuario = cp.id_usuario WHERE u.id_usuario IS NULL OR u.tipo_usuario NOT IN ('professor','profissional') OR cp.email <> u.email") === 0, 'Cadastro profissional só de professor, com o mesmo e-mail');

// Matrículas e cobranças.
$dataValida = static fn(string $c): string => "(YEAR($c) >= 1000 AND MONTH($c) > 0 AND DAYOFMONTH($c) > 0)";
ddCheck($n('SELECT COUNT(*) FROM matricula WHERE NOT (' . $dataValida('data_inicio') . ' AND ' . $dataValida('data_fim') . ' AND ' . $dataValida('data_matricula') . ') OR data_fim < data_inicio') === 0, 'Matrículas com datas válidas e coerentes');
ddCheck($n("SELECT COUNT(*) FROM matricula m JOIN usuarios u ON u.id_usuario = m.id_usuario WHERE u.tipo_usuario <> 'aluno'") === 0, 'Só alunos têm matrícula');
ddCheck($n('SELECT COUNT(*) FROM matricula m LEFT JOIN cadastro_planos p ON p.id_plano = m.id_plano WHERE p.id_plano IS NULL') === 0, 'Matrícula com plano existente');
ddCheck($n('SELECT COUNT(*) FROM matricula m WHERE NOT EXISTS (SELECT 1 FROM pagamento p WHERE p.id_matricula = m.id_matricula)') === 0, 'Toda matrícula tem cobrança');
ddCheck($n("SELECT COUNT(*) FROM matricula m WHERE m.status = 'pendente' AND (NOT EXISTS (SELECT 1 FROM pagamento p WHERE p.id_matricula = m.id_matricula AND p.status = 'pendente') OR EXISTS (SELECT 1 FROM pagamento p WHERE p.id_matricula = m.id_matricula AND p.status = 'aprovado'))") === 0, 'Matrícula pendente: cobrança pendente e nenhuma aprovada');
ddCheck($n("SELECT COUNT(*) FROM matricula m WHERE m.status IN ('ativa','vencida') AND NOT EXISTS (SELECT 1 FROM pagamento p WHERE p.id_matricula = m.id_matricula AND p.status = 'aprovado')") === 0, 'Matrícula ativa ou vencida tem cobrança aprovada');
ddCheck($n("SELECT COUNT(*) FROM matricula m WHERE m.status = 'cancelada' AND EXISTS (SELECT 1 FROM pagamento p WHERE p.id_matricula = m.id_matricula AND p.status NOT IN ('cancelado','recusado'))") === 0, 'Matrícula cancelada sem cobrança em aberto ou aprovada');
ddCheck($n("SELECT COUNT(*) FROM matricula WHERE status = 'vencida' AND data_fim >= CURDATE()") === 0, 'Matrícula vencida já terminou');
ddCheck($n("SELECT COUNT(*) FROM pagamento WHERE status = 'aprovado' AND (codigo_transacao IS NULL OR codigo_transacao NOT LIKE 'DEMO-%' OR data_pagamento IS NULL)") === 0, 'Cobrança aprovada só de demonstração (DEMO-) e com data de pagamento');
ddCheck($n("SELECT COUNT(*) FROM pagamento WHERE status <> 'aprovado' AND data_pagamento IS NOT NULL") === 0, 'Só cobrança aprovada tem data de pagamento');
ddCheck($n('SELECT COUNT(*) FROM pagamento p JOIN matricula m ON m.id_matricula = p.id_matricula WHERE p.valor <> m.valor_contratado OR ' . 'NOT ' . $dataValida('p.data_vencimento')) === 0, 'Cobrança com o valor contratado e vencimento válido');
// Cenários de aluno: ao menos um de cada.
$cenarios = [
    'ativa e vigente' => "m.status = 'ativa' AND CURDATE() BETWEEN m.data_inicio AND m.data_fim AND u.status = 'ativo'",
    'pendente' => "m.status = 'pendente'",
    'cancelada' => "m.status = 'cancelada'",
    'vencida' => "m.status = 'vencida'",
    "'ativa' já terminada" => "m.status = 'ativa' AND m.data_fim < CURDATE()",
    "'ativa' que ainda não começou" => "m.status = 'ativa' AND m.data_inicio > CURDATE()",
    'conta inativa com matrícula vigente' => "m.status = 'ativa' AND CURDATE() BETWEEN m.data_inicio AND m.data_fim AND u.status <> 'ativo'",
];
foreach ($cenarios as $rotulo => $condicao) {
    ddCheck($n("SELECT COUNT(*) FROM matricula m JOIN usuarios u ON u.id_usuario = m.id_usuario WHERE $condicao") >= 1, "Cenário de aluno: $rotulo");
}
ddCheck($n("SELECT COUNT(*) FROM usuarios u WHERE u.tipo_usuario = 'aluno' AND NOT EXISTS (SELECT 1 FROM matricula m WHERE m.id_usuario = u.id_usuario)") >= 1, 'Cenário de aluno: sem matrícula');

// Fotos faciais e rótulos do modelo da catraca.
ddCheck($n('SELECT COUNT(*) FROM (SELECT id_usuario FROM usuarios_fotos_faciais WHERE ativo = 1 GROUP BY id_usuario HAVING COUNT(*) > 3) t') === 0, 'No máximo 3 fotos ativas por pessoa');
ddCheck($n("SELECT COUNT(*) FROM usuarios_fotos_faciais f JOIN usuarios u ON u.id_usuario = f.id_usuario WHERE u.tipo_usuario IN ('admin', 'diretor')") === 0, 'Nenhuma foto de conta administrativa na demonstração');
foreach ([125 => 'Daniel', 129 => 'Rodrigo'] as $id => $nome) {
    ddCheck($db->query("SELECT SUBSTRING_INDEX(TRIM(nome), ' ', 1) FROM usuarios WHERE id_usuario = $id")->fetch_row()[0] === $nome, "Rótulo $id do modelo facial continua sendo de $nome");
}

// Produtos: todos com um vendedor ativo (o da demonstração).
ddCheck($n("SELECT COUNT(*) FROM produtos p LEFT JOIN usuarios u ON u.id_usuario = p.id_vendedor WHERE u.id_usuario IS NULL OR u.tipo_usuario <> 'vendedor' OR u.status <> 'ativo'") === 0, 'Produtos com vendedor ativo');

// Órfãos: toda referência a pessoa, com ou sem chave estrangeira, aponta para alguém que existe.
$referencias = [
    ['matricula', 'id_usuario'], ['cadastro_profissional', 'id_usuario'], ['usuarios_fotos_faciais', 'id_usuario'],
    ['notificacoes', 'usuario_id'], ['notificacoes_eventos', 'usuario_id'], ['preferencias_usuario', 'id_usuario'],
    ['agendamento', 'id_usuario'], ['agendamento', 'id_profissional'], ['profissional_aluno', 'id_aluno'], ['profissional_aluno', 'id_profissional'],
    ['avaliacao_produto', 'id_usuario'], ['af_avaliacoes', 'aluno_id'], ['af_avaliacoes', 'profissional_id'], ['treino_exercicio', 'id_usuario'],
    ['testemunhos', 'id_usuario'], ['cashback', 'id_usuario'], ['pedido', 'id_usuario'], ['enderecos_entrega', 'id_usuario'],
    ['recuperacao_senha_tokens', 'usuario_id'], ['verificacao_email_tokens', 'usuario_id'], ['auditoria_eventos', 'id_operador'], ['auditoria_eventos', 'id_alvo'],
    ['produtos', 'id_vendedor'],
];
$orfaos = [];
foreach ($referencias as [$tabela, $coluna]) {
    if (!$existe($tabela)) {
        continue;
    }
    $qtd = $n("SELECT COUNT(*) FROM `$tabela` t WHERE t.`$coluna` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.id_usuario = t.`$coluna`)");
    if ($qtd) {
        $orfaos[] = "$tabela.$coluna=$qtd";
    }
}
ddCheck(!$orfaos, 'Sem referências a pessoas inexistentes: ' . implode(', ', $orfaos));
ddCheck($n('SELECT COUNT(*) FROM pagamento p WHERE NOT EXISTS (SELECT 1 FROM matricula m WHERE m.id_matricula = p.id_matricula)') === 0, 'Sem cobrança órfã');

$db->query('ROLLBACK');
echo "Base de demonstração ($banco): $checks verificações passaram ($usuarios usuários).\n";
