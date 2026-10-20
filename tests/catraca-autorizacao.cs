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

    private static int Main(string[] args)
    {
        Console.OutputEncoding = Encoding.UTF8;
        try
        {
            if (args.Length != 2 || !Regex.IsMatch(args[0], @"^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$"))
                throw new Exception("Use apenas o banco descartável onefit_interface_test_*.");

            // Regra pura, sem banco.
            Check(!AutorizacaoCatraca.Decidir("x", "Admin", "ativo", true, true).Liberado, "Cargo com maiúscula é desconhecido");
            Check(!AutorizacaoCatraca.Decidir("x", null, "ativo", true, true).Liberado, "Cargo nulo nega");
            Check(!AutorizacaoCatraca.Decidir("x", "", "ativo", true, true).Liberado, "Cargo vazio nega");
            Check(!AutorizacaoCatraca.Decidir("x", "admin", null, true, true).Liberado, "Status nulo nega");
            Check(!AutorizacaoCatraca.Decidir("x", "faxineira", "Ativo", true, true).Liberado, "Status fora do padrão nega");
            Check(!AutorizacaoCatraca.Decidir("x", "aluno", "ativo", false, true).Liberado, "Cadastro profissional não libera aluno");
            Check(!AutorizacaoCatraca.Decidir("x", "professor", "ativo", true, false).Liberado, "Matrícula não libera professor");
            string consulta = AutorizacaoCatraca.Consulta.ToUpperInvariant();
            Check(!Regex.IsMatch(consulta, @"\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE)\b"), "Consulta somente leitura");

            var conexao = new MySqlConnection(new MySqlConnectionStringBuilder
            {
                Server = "127.0.0.1", Port = 3306, UserID = "root", Password = "", Database = args[0], ConnectionTimeout = 5,
            }.ConnectionString);
            conexao.Open();
            int casos = 0;
            foreach (string linha in File.ReadAllLines(args[1], Encoding.UTF8))
            {
                if (linha.Trim() == "")
                    continue;
                string[] partes = linha.Split(new[] { ';' }, 4);
                DecisaoCatraca decisao = AutorizacaoCatraca.Consultar(conexao, int.Parse(partes[0]));
                bool esperado = partes[1] == "1";
                Check(decisao.Liberado == esperado && decisao.Motivo == partes[2],
                    partes[3] + ": obtido " + (decisao.Liberado ? "liberado" : "negado") + " (" + decisao.Motivo + ")");
                casos++;
            }
            Check(casos >= 25, "Todos os casos lidos");

            // Falha de banco nega a entrada, sem exibir a mensagem original.
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
