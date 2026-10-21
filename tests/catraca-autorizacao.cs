// Teste da autorização de entrada da catraca (OneFit.Turnstile/AutorizacaoCatraca.cs)
// contra o banco descartável onefit_interface_test_* preparado por
// tests/catraca-autorizacao.php. Compilado e executado por tests/catraca-autorizacao.ps1.
// Uso: catraca-autorizacao.exe BANCO ARQUIVO_DE_CASOS
using System;
using System.IO;
using System.Text;
using System.Text.RegularExpressions;
using MySql.Data.MySqlClient;
using OneFit.Turnstile;

internal static class CatracaAutorizacaoTeste
{
    private static int _checks;

    private static void Check(bool ok, string mensagem)
    {
        if (!ok)
            throw new Exception("FALHOU: " + mensagem);
        _checks++;
    }

    /// <summary>CHECKSUM TABLE das tabelas que a autorização lê ou poderia alterar.</summary>
    private static string Retrato(MySqlConnection conexao, string[] tabelas)
    {
        var retrato = new StringBuilder();
        using (var comando = new MySqlCommand("CHECKSUM TABLE " + string.Join(", ", tabelas), conexao))
        using (MySqlDataReader leitor = comando.ExecuteReader())
        {
            while (leitor.Read())
                retrato.Append(leitor.GetString(0)).Append('=').Append(leitor.IsDBNull(1) ? "ausente" : leitor.GetValue(1).ToString()).Append(';');
        }
        return retrato.ToString();
    }

    private static int Main(string[] args)
    {
        Console.OutputEncoding = Encoding.UTF8;
        try
        {
            if (args.Length != 2 || !Regex.IsMatch(args[0], @"^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$"))
                throw new Exception("Use apenas o banco descartável onefit_interface_test_*.");
            // Só neste processo: a conexão real da catraca (ConexaoMySQL) nunca chega ao banco remoto.
            Environment.SetEnvironmentVariable("ONEFIT_MYSQL_PASSWORD", null);
            Check(Environment.GetEnvironmentVariable("ONEFIT_MYSQL_PASSWORD") == null, "Senha da catraca fora do teste");

            // Regra pura, sem banco.
            Check(!AutorizacaoCatraca.Decidir("x", "Admin", "ativo", true, true).Liberado, "Cargo com maiúscula é desconhecido");
            Check(AutorizacaoCatraca.Decidir("x", "diretor", "ativo", false, false).Liberado, "Diretor ativo entra sem matrícula");
            Check(!AutorizacaoCatraca.Decidir("x", "Diretor", "ativo", true, true).Liberado, "Diretor com maiúscula é desconhecido");
            Check(!AutorizacaoCatraca.Decidir("x", "diretor", "bloqueado", true, true).Liberado, "Diretor bloqueado não entra");
            Check(!AutorizacaoCatraca.Decidir("x", null, "ativo", true, true).Liberado, "Cargo nulo nega");
            Check(!AutorizacaoCatraca.Decidir("x", "", "ativo", true, true).Liberado, "Cargo vazio nega");
            Check(!AutorizacaoCatraca.Decidir("x", "admin", null, true, true).Liberado, "Status nulo nega");
            Check(!AutorizacaoCatraca.Decidir("x", "faxineira", "Ativo", true, true).Liberado, "Status fora do padrão nega");
            Check(!AutorizacaoCatraca.Decidir("x", "aluno", "ativo", false, true).Liberado, "Cadastro profissional não libera aluno");
            Check(!AutorizacaoCatraca.Decidir("x", "professor", "ativo", true, false).Liberado, "Matrícula não libera professor");
            string consulta = AutorizacaoCatraca.Consulta.ToUpperInvariant();
            Check(!Regex.IsMatch(consulta, @"\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE)\b"), "Consulta somente leitura");

            string local = new MySqlConnectionStringBuilder
            {
                Server = "127.0.0.1", Port = 3306, UserID = "root", Password = "", Database = args[0], ConnectionTimeout = 5,
            }.ConnectionString;
            var conexao = new MySqlConnection(local);
            conexao.Open();
            string[] tabelas = { "usuarios", "matricula", "pagamento", "cadastro_profissional", "auditoria_eventos", "usuarios_fotos_faciais" };
            string retratoAntes = Retrato(conexao, tabelas);

            // Os mesmos casos três vezes: duas numa conexão aberta e uma pela rotina que abre a
            // própria conexão, como a tela da catraca faz. A decisão não pode mudar.
            int casos = 0;
            for (int vez = 1; vez <= 3; vez++)
            {
                foreach (string linha in File.ReadAllLines(args[1], Encoding.UTF8))
                {
                    if (linha.Trim() == "")
                        continue;
                    string[] partes = linha.Split(new[] { ';' }, 4);
                    int id = int.Parse(partes[0]);
                    DecisaoCatraca decisao = vez == 2
                        ? AutorizacaoCatraca.Autorizar(() => new MySqlConnection(local), id)
                        : AutorizacaoCatraca.Consultar(conexao, id);
                    bool esperado = partes[1] == "1";
                    Check(decisao.Liberado == esperado && decisao.Motivo == partes[2],
                        partes[3] + " (consulta " + vez + "): obtido " + (decisao.Liberado ? "liberado" : "negado") + " (" + decisao.Motivo + ")");
                    if (vez == 1)
                        casos++;
                }
            }
            Check(casos >= 39, "Todos os casos lidos");
            // Validar de novo não grava nada: cargos, status, matrículas, cobranças, fotos e auditoria iguais.
            Check(Retrato(conexao, tabelas) == retratoAntes, "Validação repetida não altera o banco");

            // A decisão exibida vale só para a pessoa consultada e por pouco tempo.
            var ultima = new UltimaDecisao(TimeSpan.FromSeconds(2));
            var t0 = new DateTime(2026, 10, 21, 9, 0, 0, DateTimeKind.Utc);
            DecisaoCatraca liberada = AutorizacaoCatraca.Decidir("x", "admin", "ativo", false, false);
            Check(ultima.Para(125, t0) == null, "Sem consulta, nada a exibir");
            ultima.Registrar(125, liberada, t0);
            Check(ultima.Para(125, t0.AddSeconds(1)) == liberada, "Mesma pessoa logo depois: mesma decisão");
            Check(ultima.Para(129, t0.AddSeconds(1)) == null, "Outra pessoa: nova consulta");
            Check(ultima.Para(125, t0.AddSeconds(2)) == null, "Depois de 2 s: nova consulta");
            Check(ultima.Para(125, t0.AddSeconds(-1)) == null, "Relógio voltou: nova consulta");
            ultima.Registrar(129, AutorizacaoCatraca.FalhaNoBanco(), t0);
            Check(ultima.Para(125, t0) == null && !ultima.Para(129, t0).Liberado, "Nova consulta substitui a anterior");

            // Falha de banco nega a entrada, sem exibir a mensagem original.
            DecisaoCatraca semFabrica = AutorizacaoCatraca.Autorizar(() => { throw new InvalidOperationException("sem configuração"); }, 1);
            Check(!semFabrica.Liberado && semFabrica.Motivo == "falha ao consultar o banco", "Erro ao criar a conexão nega");
            DecisaoCatraca semSenha = AutorizacaoCatraca.Autorizar(ConexaoMySQL.CriarConexao, 1);
            Check(!semSenha.Liberado && semSenha.Motivo == "falha ao consultar o banco", "Catraca sem senha configurada nega");
            DecisaoCatraca semServidor = AutorizacaoCatraca.Autorizar(() => new MySqlConnection(new MySqlConnectionStringBuilder
            {
                Server = "127.0.0.1", Port = 1, UserID = "root", Password = "", Database = args[0], ConnectionTimeout = 2,
            }.ConnectionString), 1);
            Check(!semServidor.Liberado && semServidor.Motivo == "falha ao consultar o banco", "Servidor fora do ar nega");
            conexao.Close();
            DecisaoCatraca semConexao = AutorizacaoCatraca.Consultar(conexao, 1);
            Check(!semConexao.Liberado && semConexao.Motivo == "falha ao consultar o banco", "Conexão fechada nega");
            var bancoErrado = new MySqlConnection(new MySqlConnectionStringBuilder
            {
                Server = "127.0.0.1", Port = 3306, UserID = "root", Password = "", Database = "information_schema", ConnectionTimeout = 5,
            }.ConnectionString);
            bancoErrado.Open();
            DecisaoCatraca semTabela = AutorizacaoCatraca.Consultar(bancoErrado, 1);
            bancoErrado.Close();
            Check(!semTabela.Liberado && semTabela.Motivo == "falha ao consultar o banco", "Erro de SQL (tabela ausente) nega");

            Console.WriteLine("Catraca: " + _checks + " verificações passaram (" + casos + " casos no banco).");
            return 0;
        }
        catch (Exception erro)
        {
            Console.Error.WriteLine(erro.Message);
            return 1;
        }
    }
}
