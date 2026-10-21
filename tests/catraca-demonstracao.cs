// Confere a decisão real da catraca (OneFit.Turnstile/AutorizacaoCatraca.cs) para cada pessoa de
// uma base de demonstração LOCAL, a partir de um arquivo de casos "id;liberado;motivo;rótulo".
// Compilado e executado por tests/catraca-demonstracao.ps1. Só lê o banco.
// Uso: catraca-demonstracao.exe BANCO_LOCAL ARQUIVO_DE_CASOS
using System;
using System.IO;
using System.Text;
using System.Text.RegularExpressions;
using MySql.Data.MySqlClient;
using OneFit.Turnstile;

internal static class CatracaDemonstracaoTeste
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
            if (args.Length != 2 || !Regex.IsMatch(args[0], @"^onefit_(ensaio_reset_\d{8}_\d{6}|interface_test_\d{8}_\d{6}_[a-f0-9]{4})$"))
                throw new Exception("Use apenas um banco local de ensaio ou de teste.");
            // Só neste processo: a conexão real da catraca (ConexaoMySQL) nunca chega ao banco remoto.
            Environment.SetEnvironmentVariable("ONEFIT_MYSQL_PASSWORD", null);
            string local = new MySqlConnectionStringBuilder
            {
                Server = "127.0.0.1", Port = 3306, UserID = "root", Password = "", Database = args[0], ConnectionTimeout = 5,
            }.ConnectionString;
            string[] linhas = File.ReadAllLines(args[1], Encoding.UTF8);
            int liberados = 0, negados = 0;
            string retratoAntes;
            using (var conexao = new MySqlConnection(local))
            {
                conexao.Open();
                retratoAntes = Retrato(conexao);
            }
            // Duas rodadas, cada consulta pela rotina que abre a própria conexão (como a tela da catraca).
            for (int rodada = 1; rodada <= 2; rodada++)
            {
                foreach (string linha in linhas)
                {
                    if (linha.Trim() == "")
                        continue;
                    string[] partes = linha.Split(new[] { ';' }, 4);
                    DecisaoCatraca decisao = AutorizacaoCatraca.Autorizar(() => new MySqlConnection(local), int.Parse(partes[0]));
                    bool esperado = partes[1] == "1";
                    Check(decisao.Liberado == esperado && decisao.Motivo == partes[2],
                        partes[3] + ": esperado " + (esperado ? "liberado" : "negado") + " (" + partes[2] + "), obtido " + (decisao.Liberado ? "liberado" : "negado") + " (" + decisao.Motivo + ")");
                    if (rodada == 1)
                    {
                        Console.WriteLine((decisao.Liberado ? "  LIBERA " : "  NEGA   ") + partes[3] + " -> " + decisao.Motivo);
                        if (decisao.Liberado) liberados++; else negados++;
                    }
                }
            }
            Check(liberados + negados >= 15, "Casos lidos (" + (liberados + negados) + ")");
            using (var conexao = new MySqlConnection(local))
            {
                conexao.Open();
                Check(Retrato(conexao) == retratoAntes, "Consultar a catraca não altera o banco");
            }
            // Falha no banco: nega.
            DecisaoCatraca semBanco = AutorizacaoCatraca.Autorizar(() => { throw new InvalidOperationException("sem configuração"); }, 1);
            Check(!semBanco.Liberado && semBanco.Motivo == "falha ao consultar o banco", "Falha ao criar a conexão nega");
            DecisaoCatraca semSenha = AutorizacaoCatraca.Autorizar(ConexaoMySQL.CriarConexao, 1);
            Check(!semSenha.Liberado && semSenha.Motivo == "falha ao consultar o banco", "Catraca sem senha configurada nega");

            Console.WriteLine("Catraca na base de demonstração: " + _checks + " verificações passaram (" + liberados + " liberados, " + negados + " negados).");
            return 0;
        }
        catch (Exception erro)
        {
            Console.Error.WriteLine(erro.Message);
            return 1;
        }
    }

    private static string Retrato(MySqlConnection conexao)
    {
        var retrato = new StringBuilder();
        using (var comando = new MySqlCommand("CHECKSUM TABLE usuarios, matricula, pagamento, cadastro_profissional, usuarios_fotos_faciais, auditoria_eventos", conexao))
        using (MySqlDataReader leitor = comando.ExecuteReader())
        {
            while (leitor.Read())
                retrato.Append(leitor.GetString(0)).Append('=').Append(leitor.GetValue(1)).Append(';');
        }
        return retrato.ToString();
    }
}
