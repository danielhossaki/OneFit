<?php
/**
 * mock-data.php
 * Dados de exemplo (mock) para preencher as telas do backoffice antes
 * da integração real com o banco de dados. Cada bloco abaixo alimenta
 * uma tela específica — quando for conectar ao banco de verdade, é só
 * trocar cada array por uma consulta SQL equivalente, mantendo os
 * mesmos nomes de chave (o resto do código depende desses nomes).
 */

/* =======================================================================
 * PERFIL: ADMINISTRADOR
 * ===================================================================== */

// Tela "Usuários" — lista de alunos/usuários cadastrados na plataforma

$usuarios = [
    ['id' => 1, 'nome' => 'Ana Beatriz Souza', 'email' => 'ana.souza@email.com', 'cpf' => '123.456.789-01', 'status' => 'ativo', 'matricula' => 'MAT-0001', 'dataInicial' => '2025-01-10', 'dataFinal' => '2026-01-10', 'acesso' => 'Liberado', 'observacao' => ''],
    ['id' => 2, 'nome' => 'Bruno Carvalho Lima', 'email' => 'bruno.lima@email.com', 'cpf' => '234.567.890-12', 'status' => 'ativo', 'matricula' => 'MAT-0002', 'dataInicial' => '2025-02-15', 'dataFinal' => '2026-02-15', 'acesso' => 'Liberado', 'observacao' => ''],
    ['id' => 3, 'nome' => 'Camila Ferreira Dias', 'email' => 'camila.dias@email.com', 'cpf' => '345.678.901-23', 'status' => 'inativo', 'matricula' => 'MAT-0003', 'dataInicial' => '2024-11-01', 'dataFinal' => '2025-11-01', 'acesso' => 'Bloqueado', 'observacao' => 'Contrato suspenso por inadimplência.'],
    ['id' => 4, 'nome' => 'Diego Martins Rocha', 'email' => 'diego.rocha@email.com', 'cpf' => '456.789.012-34', 'status' => 'ativo', 'matricula' => 'MAT-0004', 'dataInicial' => '2025-03-20', 'dataFinal' => '2026-03-20', 'acesso' => 'Liberado', 'observacao' => ''],
    ['id' => 5, 'nome' => 'Elaine Cristina Melo', 'email' => 'elaine.melo@email.com', 'cpf' => '567.890.123-45', 'status' => 'inativo', 'matricula' => 'MAT-0005', 'dataInicial' => '2024-06-05', 'dataFinal' => '2025-06-05', 'acesso' => 'Bloqueado', 'observacao' => 'Contrato encerrado.'],
    ['id' => 6, 'nome' => 'Felipe Augusto Nunes', 'email' => 'felipe.nunes@email.com', 'cpf' => '678.901.234-56', 'status' => 'ativo', 'matricula' => 'MAT-0006', 'dataInicial' => '2025-05-12', 'dataFinal' => '2026-05-12', 'acesso' => 'Liberado', 'observacao' => ''],
];

// Tela "Permissões" — usuários com função administrativa atribuída
$permissoes = [
    ['id' => 1, 'usuarioId' => 2, 'nome' => 'Bruno Carvalho Lima', 'email' => 'bruno.lima@email.com', 'funcao' => 'Instrutor'],
    ['id' => 2, 'usuarioId' => 4, 'nome' => 'Diego Martins Rocha', 'email' => 'diego.rocha@email.com', 'funcao' => 'Recepção'],
    ['id' => 3, 'usuarioId' => 6, 'nome' => 'Felipe Augusto Nunes', 'email' => 'felipe.nunes@email.com', 'funcao' => 'Gerente'],
];

// Tela "Funções" — cargos disponíveis e o que cada um pode acessar
$funcoes = [
    ['id' => 1, 'nome' => 'Administrador', 'permissoes' => 'Usuários, Pagamentos, Cashbacks, Produtos, Planos, Profissionais'],
    ['id' => 2, 'nome' => 'Instrutor', 'permissoes' => 'Alunos, Agenda'],
    ['id' => 3, 'nome' => 'Recepção', 'permissoes' => 'Usuários, Pagamentos'],
];

// Tela "Pagamentos" — pagamentos recebidos (mensalidade, produtos, taxas)
$pagamentos = [
    ['id' => 1, 'data' => '2026-08-01', 'tipo' => 'PIX', 'valor' => 129.90, 'usuarioId' => 1, 'observacao' => 'Mensalidade agosto'],
    ['id' => 2, 'data' => '2026-08-02', 'tipo' => 'Crédito', 'valor' => 189.90, 'usuarioId' => 2, 'observacao' => 'Compra whey protein'],
    ['id' => 3, 'data' => '2026-08-03', 'tipo' => 'Dinheiro', 'valor' => 50.00, 'usuarioId' => 4, 'observacao' => 'Taxa de matrícula'],
    ['id' => 4, 'data' => '2026-08-05', 'tipo' => 'Débito', 'valor' => 129.90, 'usuarioId' => 6, 'observacao' => 'Mensalidade agosto'],
    ['id' => 5, 'data' => '2026-07-28', 'tipo' => 'PIX', 'valor' => 79.90, 'usuarioId' => 3, 'observacao' => 'Camiseta dry fit'],
];

// Tela "Cashbacks" — resumo de saldo + extrato de transações de cashback
$cashbackResumo = ['saldoTotal' => 22140.00, 'distribuidos' => 6512.90, 'debitado' => 1820.40, 'creditado' => 8333.30];
$cashbackTransacoes = [
    ['id' => 1, 'data' => '2026-08-05', 'tipo' => 'credito', 'valor' => 25.00, 'usuarioId' => 1, 'motivo' => 'Indicação de amigo'],
    ['id' => 2, 'data' => '2026-08-04', 'tipo' => 'debito', 'valor' => 15.00, 'usuarioId' => 2, 'motivo' => 'Uso em compra de produto'],
    ['id' => 3, 'data' => '2026-08-02', 'tipo' => 'credito', 'valor' => 6.50, 'usuarioId' => 4, 'motivo' => 'Cashback de mensalidade'],
    ['id' => 4, 'data' => '2026-07-30', 'tipo' => 'credito', 'valor' => 40.00, 'usuarioId' => 6, 'motivo' => 'Distribuição em massa'],
];

// Tela "Categorias" — categorias de produtos da loja
$categorias = [
    ['id' => 1, 'nome' => 'Suplementos'],
    ['id' => 2, 'nome' => 'Vestuário'],
    ['id' => 3, 'nome' => 'Acessórios'],
    ['id' => 4, 'nome' => 'Equipamentos'],
];

// Tela "Produtos" — resumo do catálogo + lista de produtos da loja
$produtosResumo = ['total' => 48, 'disponiveis' => 41, 'indisponiveis' => 7];
$produtos = [
    ['id' => 1, 'nome' => 'Whey Protein 900g', 'categoria' => 'Suplementos', 'preco' => 189.90, 'desconto' => 10, 'valorFinal' => 170.91, 'cashback' => 5, 'estoque' => 32, 'status' => 'disponivel', 'imagem' => '', 'descricao' => 'Whey concentrado sabor baunilha.'],
    ['id' => 2, 'nome' => 'Camiseta Dry Fit', 'categoria' => 'Vestuário', 'preco' => 79.90, 'desconto' => 0, 'valorFinal' => 79.90, 'cashback' => 3, 'estoque' => 0, 'status' => 'indisponivel', 'imagem' => '', 'descricao' => 'Camiseta respirável para treino.'],
    ['id' => 3, 'nome' => 'Luva de Treino', 'categoria' => 'Acessórios', 'preco' => 49.90, 'desconto' => 15, 'valorFinal' => 42.42, 'cashback' => 4, 'estoque' => 18, 'status' => 'disponivel', 'imagem' => '', 'descricao' => 'Luva com proteção para levantamento.'],
    ['id' => 4, 'nome' => 'Halteres 5kg (par)', 'categoria' => 'Equipamentos', 'preco' => 129.90, 'desconto' => 0, 'valorFinal' => 129.90, 'cashback' => 2, 'estoque' => 6, 'status' => 'disponivel', 'imagem' => '', 'descricao' => 'Par de halteres emborrachados 5kg.'],
];

// Tela "Cadastro de Planos" — planos de assinatura oferecidos
$planos = [
    ['id' => 1, 'nome' => 'Mensal Fit', 'valor' => 129.90, 'ciclo' => 'Mensal', 'descricao' => 'Acesso completo à academia.', 'status' => 'ativo', 'textoBotao' => 'Assinar agora'],
    ['id' => 2, 'nome' => 'Trimestral Fit', 'valor' => 349.90, 'ciclo' => 'Trimestral', 'descricao' => 'Economia de 10% no plano trimestral.', 'status' => 'ativo', 'textoBotao' => 'Quero esse'],
    ['id' => 3, 'nome' => 'Anual Fit', 'valor' => 1199.90, 'ciclo' => 'Anual', 'descricao' => 'Melhor custo-benefício do ano.', 'status' => 'inativo', 'textoBotao' => 'Assinar agora'],
];

// Tela "Profissionais" (visão do admin) — equipe cadastrada
$profissionaisAdm = [
    ['id' => 1, 'nome' => 'Marina Alves Prado', 'funcao' => 'Personal Trainer', 'tituloCard' => 'Personal Trainer Sênior', 'documento' => '123.456.789-00', 'status' => 'ativo', 'email' => 'marina.alves@onefit.com', 'telefone' => '(12) 3456-7890', 'celular' => '(12) 99876-5432', 'descricao' => 'Especialista em treinamento funcional.', 'experiencia' => '8 anos de experiência em academias.', 'foto' => '', 'observacaoInterna' => ''],
    ['id' => 2, 'nome' => 'João Pedro Ramos', 'funcao' => 'Instrutor de Funcional', 'tituloCard' => 'Instrutor de Funcional', 'documento' => '234.567.890-11', 'status' => 'ativo', 'email' => 'joao.ramos@onefit.com', 'telefone' => '(12) 3456-1234', 'celular' => '(12) 99123-4567', 'descricao' => 'Foco em treinos funcionais em grupo.', 'experiencia' => '5 anos de experiência.', 'foto' => '', 'observacaoInterna' => ''],
    ['id' => 3, 'nome' => 'Carla Menezes', 'funcao' => 'Nutricionista', 'tituloCard' => 'Nutricionista Esportiva', 'documento' => '345.678.901-22', 'status' => 'inativo', 'email' => 'carla.menezes@onefit.com', 'telefone' => '(12) 3456-5678', 'celular' => '(12) 99234-5678', 'descricao' => 'Acompanhamento nutricional esportivo.', 'experiencia' => '10 anos de experiência.', 'foto' => '', 'observacaoInterna' => 'Em licença até setembro.'],
];

/* =======================================================================
 * PERFIL: PROFISSIONAL
 * ===================================================================== */

// Tela "Dashboard" do profissional — status do contrato e saldo de cashback
$profContrato = ['status' => 'Ativo', 'validade' => '31/12/2026', 'saldoCashback' => 125.40];

// Tela "Histórico" — valores recebidos por competência (mês)
$profHistorico = [
    ['competencia' => 'Julho/2026', 'valor' => 2400.00, 'tipo' => 'credito', 'cashback' => 36.00],
    ['competencia' => 'Junho/2026', 'valor' => 2200.00, 'tipo' => 'credito', 'cashback' => 33.00],
    ['competencia' => 'Maio/2026', 'valor' => 2100.00, 'tipo' => 'pix', 'cashback' => 0.00],
];

// Tela "Alunos" — alunos vinculados a este profissional
$profAlunos = [
    ['id' => 1, 'nome' => 'Rafael Costa', 'contato' => '(12) 99123-4567', 'plano' => 'Mensal Fit', 'status' => 'ativo', 'valor' => 129.90, 'observacao' => ''],
    ['id' => 2, 'nome' => 'Juliana Prado', 'contato' => '(12) 99876-1234', 'plano' => 'Trimestral Fit', 'status' => 'ativo', 'valor' => 349.90, 'observacao' => ''],
    ['id' => 3, 'nome' => 'Marcos Lima', 'contato' => '(12) 99321-8765', 'plano' => 'Mensal Fit', 'status' => 'inativo', 'valor' => 129.90, 'observacao' => 'Trancado temporariamente.'],
];

// Tela "Agenda" — horários já agendados e horários livres oferecidos
$profAgendados = [
    ['aluno' => 'Rafael Costa', 'contato' => '(12) 99123-4567', 'data' => '12/08/2026 08:00', 'modalidade' => 'Musculação'],
    ['aluno' => 'Juliana Prado', 'contato' => '(12) 99876-1234', 'data' => '12/08/2026 10:00', 'modalidade' => 'Funcional'],
];
$profDisponiveis = [
    ['data' => '12/08/2026 09:00', 'modalidade' => 'Funcional'],
    ['data' => '13/08/2026 07:00', 'modalidade' => 'Avaliação física'],
];

// Tela "Meu cashback" — extrato de créditos recebidos
$profCashbackHistorico = [
    ['data' => '01/08/2026', 'descricao' => 'Bônus por avaliação', 'valor' => 15.00],
    ['data' => '15/07/2026', 'descricao' => 'Indicação de aluno', 'valor' => 20.00],
];

// Tela "Minhas compras" — pedidos em andamento e já finalizados
$profPedidos = [
    ['transacao' => 'TRX-3301', 'produto' => 'Camiseta Dry Fit', 'quantidade' => 1, 'valor' => 79.90, 'status' => 'Aguardando'],
];
$profPedidosHistorico = [
    ['transacao' => 'TRX-3288', 'data' => '28/07/2026 14:20', 'produto' => 'Whey Protein 900g', 'status' => 'Entregue'],
];

/* =======================================================================
 * PERFIL: ALUNO
 * ===================================================================== */

// Tela "Perfil" — dados cadastrais e físicos do aluno logado
$alunoPerfil = ['nome' => 'Rafael Costa', 'email' => 'rafael.costa@email.com', 'plano' => 'Mensal Fit', 'status' => 'Ativo', 'documento' => '321.654.987-00', 'telefone' => '(12) 99123-4567', 'dataCadastro' => '10/01/2026', 'nascimento' => '22/05/1995', 'altura' => 1.78, 'peso' => 82.0, 'objetivo' => 'Hipertrofia'];

// Tela "Histórico" — pagamentos de mensalidade do aluno
$alunoHistorico = [
    ['data' => '01/08/2026 09:00', 'descricao' => 'Mensalidade agosto', 'tipo' => 'PIX', 'status' => 'Pago', 'valor' => 129.90, 'cashback' => 6.50],
    ['data' => '01/07/2026 09:00', 'descricao' => 'Mensalidade julho', 'tipo' => 'Crédito', 'status' => 'Pago', 'valor' => 129.90, 'cashback' => 6.50],
];

// Tela "Cashback" — extrato de crédito/débito de cashback do aluno
$alunoCashbackHistorico = [
    ['data' => '05/08/2026', 'tipo' => 'credito', 'descricao' => 'Cashback de mensalidade', 'valor' => 6.50],
    ['data' => '20/07/2026', 'tipo' => 'debito', 'descricao' => 'Uso em compra', 'valor' => 15.00],
];

// Tela "Minhas compras" — pedidos em andamento e já finalizados
$alunoPedidos = [
    ['transacao' => 'TRX-3305', 'produto' => 'Luva de Treino', 'quantidade' => 1, 'valor' => 42.42, 'status' => 'Aguardando'],
];
$alunoPedidosHistorico = [
    ['transacao' => 'TRX-3210', 'data' => '15/07/2026 11:00', 'produto' => 'Camiseta Dry Fit', 'status' => 'Entregue'],
];

// Tela "Treino" — ficha de exercícios do aluno
$alunoTreino = [
    ['id' => 1, 'nome' => 'Supino reto', 'series' => 4, 'repeticoes' => 10, 'carga' => 40],
    ['id' => 2, 'nome' => 'Agachamento livre', 'series' => 4, 'repeticoes' => 12, 'carga' => 60],
    ['id' => 3, 'nome' => 'Puxada frontal', 'series' => 3, 'repeticoes' => 12, 'carga' => 35],
];

// Tela "Minha agenda" — horários de avaliação física disponíveis
$alunoAgendaDisponiveis = [
    ['data' => '13/08/2026 07:00', 'tipo' => 'Avaliação física'],
    ['data' => '14/08/2026 18:00', 'tipo' => 'Avaliação física'],
];
