// Evidências do reconhecimento facial da catraca (OneFit.Turnstile/ReconhecimentoFacial.cs).
// Compilado e executado por tests/catraca-reconhecimento.ps1.
// Uso: catraca-reconhecimento.exe PASTA_FOTOS PASTA_DESCONHECIDOS MODELO_EM_USO BANCO_FIXTURE
//   PASTA_FOTOS: OneFitFaceTest, com as subpastas 125 e 129 usadas no treino da catraca;
//   PASTA_DESCONHECIDOS: rostos de pessoas que não estão no modelo (base pública AT&T/ORL);
//   MODELO_EM_USO: o modelo_facial.yml que a catraca carrega;
//   BANCO_FIXTURE: banco descartável onefit_interface_test_* (só para copiar a estrutura das tabelas).
// Só imprime números agregados: nenhum nome de arquivo, imagem ou dado pessoal.
using System;
using System.Collections.Generic;
using System.Drawing;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using Emgu.CV;
using Emgu.CV.CvEnum;
using MySql.Data.MySqlClient;
using OneFit.Turnstile;

internal static class CatracaReconhecimentoTeste
{
    private static readonly int[] Ids = { 125, 129 };
    private static readonly string[] Extensoes = { ".jpg", ".jpeg", ".png", ".bmp" };
    private static CascadeClassifier _detector;
    private static int _checks;

    private static void Check(bool ok, string mensagem)
    {
        if (!ok)
            throw new Exception("FALHOU: " + mensagem);
        _checks++;
    }

    private static IdentificacaoFacial NovaRegra()
    {
        return new IdentificacaoFacial(Ids, IdentificacaoFacial.LimitePadrao, IdentificacaoFacial.MargemPadrao, IdentificacaoFacial.QuadrosPadrao);
    }

    /// <summary>
    /// O mesmo rosto em todos os quadros da confirmação: o caso mais favorável a quem tenta
    /// entrar. Se nem assim um desconhecido é identificado, quem o barra é o limite e a margem.
    /// </summary>
    private static Identificacao Apresentar(ComparacaoFacial comparacao)
    {
        IdentificacaoFacial regra = NovaRegra();
        Identificacao resultado = null;
        for (int i = 0; i < regra.Quadros; i++)
            resultado = regra.Avaliar(1, comparacao);
        return resultado;
    }

    /// <summary>Quadro de câmera simulado com uma imagem: detecção, identificação e, só se identificado, o banco.</summary>
    private static DecisaoCatraca Tentativa(Mat imagem, ModeloFacial modelo, Func<MySqlConnection> fabrica, out Identificacao identificacao)
    {
        IdentificacaoFacial regra = NovaRegra();
        identificacao = null;
        using (Mat cinza = new Mat())
        {
            CvInvoke.CvtColor(imagem, cinza, ColorConversion.Bgr2Gray);
            Rectangle[] rostos = PreparoFacial.DetectarRostos(_detector, cinza);
            for (int i = 0; i < regra.Quadros; i++)
                identificacao = regra.AvaliarQuadro(cinza, rostos, modelo);
        }
        return identificacao.Situacao == SituacaoRosto.Identificado
            ? AutorizacaoCatraca.Autorizar(fabrica, identificacao.IdUsuario)
            : null;
    }

    private static int QuantidadeDeRostos(string arquivo)
    {
        using (Mat original = CvInvoke.Imread(arquivo, ImreadModes.ColorBgr))
        using (Mat cinza = new Mat())
        {
            if (original.IsEmpty)
                return 0;
            CvInvoke.CvtColor(original, cinza, ColorConversion.Bgr2Gray);
            return PreparoFacial.DetectarRostos(_detector, cinza).Length;
        }
    }

    /// <summary>Rosto já recortado (como os da base AT&T) quando o detector não o encontra: imagem inteira.</summary>
    private static Mat ImagemInteira(string arquivo)
    {
        using (Mat cinza = CvInvoke.Imread(arquivo, ImreadModes.Grayscale))
        {
            var preparado = new Mat();
            CvInvoke.Resize(cinza, preparado, PreparoFacial.TamanhoRosto);
            return preparado;
        }
    }

    private static string Faixa(IEnumerable<double> valores)
    {
        List<double> v = valores.Where(x => !double.IsInfinity(x)).OrderBy(x => x).ToList();
        if (v.Count == 0)
            return "-";
        return string.Format("min {0:F1}, mediana {1:F1}, max {2:F1}", v[0], v[v.Count / 2], v[v.Count - 1]);
    }

    private static int Executar(MySqlConnection conexao, string sql)
    {
        using (var comando = new MySqlCommand(sql, conexao))
            return comando.ExecuteNonQuery();
    }

    private static int Main(string[] args)
    {
        Console.OutputEncoding = Encoding.UTF8;
        string banco = null;
        MySqlConnection raiz = null;
        try
        {
            if (args.Length != 4 || !Regex.IsMatch(args[3], @"^onefit_interface_test_\d{8}_\d{6}_[a-f0-9]{4}$"))
                throw new Exception("Use apenas o banco descartável onefit_interface_test_*.");
            // Só neste processo: a conexão real da catraca (ConexaoMySQL) nunca chega ao banco remoto.
            Environment.SetEnvironmentVariable("ONEFIT_MYSQL_PASSWORD", null);
            _detector = new CascadeClassifier(Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "haarcascade_frontalface_default.xml"));
            double limite = IdentificacaoFacial.LimitePadrao, margem = IdentificacaoFacial.MargemPadrao;
            int quadros = IdentificacaoFacial.QuadrosPadrao;
            Console.WriteLine("Regra: distância abaixo de " + limite + ", margem de pelo menos " + margem + ", " + quadros + " quadros seguidos.");

            // --- Amostras ---------------------------------------------------------------
            var arquivos = new Dictionary<int, List<string>>();
            var rostos = new Dictionary<int, List<Mat>>();
            foreach (int id in Ids)
            {
                string[] todos = Directory.GetFiles(Path.Combine(args[0], id.ToString()))
                    .Where(a => Extensoes.Contains(Path.GetExtension(a), StringComparer.OrdinalIgnoreCase)).OrderBy(a => a).ToArray();
                arquivos[id] = new List<string>();
                rostos[id] = new List<Mat>();
                foreach (string arquivo in todos)
                {
                    Mat preparado = PreparoFacial.PrepararFoto(_detector, arquivo);
                    if (preparado == null)
                        continue;
                    arquivos[id].Add(arquivo);
                    rostos[id].Add(preparado);
                }
                Console.WriteLine("Pessoa " + id + ": " + todos.Length + " fotos, " + rostos[id].Count + " com rosto detectado.");
                Check(rostos[id].Count >= 3, "Pessoa " + id + " com pelo menos 3 fotos utilizáveis");
            }
            string[] arquivosDesconhecidos = Directory.GetFiles(args[1], "*.pgm", SearchOption.AllDirectories).OrderBy(a => a).ToArray();
            var desconhecidosDetectados = new List<Mat>();
            var desconhecidosRecortados = new List<Mat>();
            foreach (string arquivo in arquivosDesconhecidos)
            {
                Mat preparado = PreparoFacial.PrepararFoto(_detector, arquivo);
                if (preparado != null)
                    desconhecidosDetectados.Add(preparado);
                else
                    desconhecidosRecortados.Add(ImagemInteira(arquivo));
            }
            int pessoasDesconhecidas = arquivosDesconhecidos.Select(a => Path.GetDirectoryName(a)).Distinct().Count();
            Console.WriteLine("Desconhecidos: " + arquivosDesconhecidos.Length + " rostos de " + pessoasDesconhecidas + " pessoas (" +
                desconhecidosDetectados.Count + " achados pelo detector, " + desconhecidosRecortados.Count + " usados inteiros).");
            Check(arquivosDesconhecidos.Length >= 400 && pessoasDesconhecidas >= 40, "Base de desconhecidos completa");
            var todosDesconhecidos = desconhecidosDetectados.Concat(desconhecidosRecortados).ToList();

            // --- 1. Regra de confiança, sem imagens ---------------------------------------------
            {
                IdentificacaoFacial regra = NovaRegra();
                var bom = new ComparacaoFacial(129, limite - 1, limite + margem + 5);
                for (int i = 1; i < quadros; i++)
                    Check(regra.Avaliar(1, bom).Situacao == SituacaoRosto.Confirmando, "Quadro " + i + " ainda confirma");
                Identificacao ok = regra.Avaliar(1, bom);
                Check(ok.Situacao == SituacaoRosto.Identificado && ok.IdUsuario == 129, "Identifica só no quadro " + quadros);
                Check(regra.Avaliar(1, new ComparacaoFacial(129, limite, 500)).Situacao == SituacaoRosto.Desconhecido, "Distância igual ao limite: desconhecido");
                Check(regra.Avaliar(1, bom).Situacao == SituacaoRosto.Confirmando, "Quadro recusado zera a confirmação");
                regra.Reiniciar();
                Check(Apresentar(new ComparacaoFacial(129, limite + 0.1, 500)).Situacao == SituacaoRosto.Desconhecido, "Distância acima do limite: desconhecido");
                Check(Apresentar(new ComparacaoFacial(129, 10, 10 + margem - 0.1)).Situacao == SituacaoRosto.Desconhecido, "Parecido com as duas pessoas (margem curta): desconhecido");
                Check(Apresentar(new ComparacaoFacial(7, 10, 500)).Situacao == SituacaoRosto.Desconhecido, "Rótulo que a catraca não conhece: desconhecido");
                Check(Apresentar(null).Situacao == SituacaoRosto.Desconhecido, "Sem modelo: desconhecido");
                Check(Apresentar(new ComparacaoFacial(125, 10, double.PositiveInfinity)).IdUsuario == 125, "Modelo de uma pessoa só: vale o limite");
                for (int i = 1; i < quadros; i++)
                    regra.Avaliar(1, bom);
                Check(regra.Avaliar(2, null).Situacao == SituacaoRosto.VariosRostos, "Dois rostos: nenhuma identidade");
                Check(regra.Avaliar(1, bom).Situacao == SituacaoRosto.Confirmando, "Dois rostos zeram a confirmação");
                for (int i = 1; i < quadros - 1; i++)
                    regra.Avaliar(1, bom);
                Check(regra.Avaliar(1, new ComparacaoFacial(125, limite - 1, limite + margem + 5)).Situacao == SituacaoRosto.Confirmando, "Troca de pessoa recomeça a confirmação");
                Check(regra.Avaliar(0, null).Situacao == SituacaoRosto.SemRosto, "Sem rosto");
                Check(regra.Avaliar(1, bom).Situacao == SituacaoRosto.Confirmando, "Sem rosto zera a confirmação");
            }

            // --- 2. Modelo em uso: distâncias iguais ao LBPH e regra antiga x nova ------------------
            using (ModeloFacial emUso = ModeloFacial.Carregar(args[2]))
            {
                Check(emUso.Pessoas.SequenceEqual(Ids), "Modelo em uso tem só as pessoas 125 e 129");
                int conferidas = 0;
                foreach (Mat rosto in todosDesconhecidos.Concat(rostos.Values.SelectMany(r => r)))
                {
                    ComparacaoFacial c = emUso.Comparar(rosto), lbph = emUso.PreverLbph(rosto);
                    Check(c.Rotulo == lbph.Rotulo && Math.Abs(c.Distancia - lbph.Distancia) < 1e-3, "Distância calculada igual à do LBPH");
                    conferidas++;
                }
                var antiga = todosDesconhecidos.Select(r => emUso.PreverLbph(r)).Where(p => Ids.Contains(p.Rotulo) && p.Distancia < 100).ToList();
                int nova = todosDesconhecidos.Count(r => Apresentar(emUso.Comparar(r)).Situacao == SituacaoRosto.Identificado);
                Console.WriteLine("Modelo em uso x desconhecidos (" + todosDesconhecidos.Count + "): regra antiga (1 quadro, limite 100) aceitaria " + antiga.Count +
                    " (" + string.Join(", ", antiga.GroupBy(p => p.Rotulo).Select(g => "como " + g.Key + ": " + g.Count())) + "); regra nova aceita " + nova + ".");
                Check(nova == 0, "Regra nova não aceita nenhum desconhecido com o modelo em uso");
                Console.WriteLine("Distância calculada conferida com o LBPH em " + conferidas + " rostos.");
            }

            // --- 3. Pessoa cadastrada: cada foto fora do modelo -----------------------------------
            var genuinos = new List<Tuple<int, ComparacaoFacial>>();
            var identificaveis = new Dictionary<int, List<int>>();
            foreach (int id in Ids)
            {
                identificaveis[id] = new List<int>();
                for (int i = 0; i < rostos[id].Count; i++)
                {
                    var treino = new List<Mat>(); var rotulos = new List<int>();
                    foreach (int outro in Ids)
                        for (int j = 0; j < rostos[outro].Count; j++)
                            if (outro != id || j != i) { treino.Add(rostos[outro][j]); rotulos.Add(outro); }
                    using (ModeloFacial modelo = ModeloFacial.Treinar(treino, rotulos))
                    {
                        ComparacaoFacial c = modelo.Comparar(rostos[id][i]);
                        genuinos.Add(Tuple.Create(id, c));
                        Identificacao r = Apresentar(c);
                        Check(r.Situacao != SituacaoRosto.Identificado || r.IdUsuario == id, "Pessoa " + id + " nunca identificada como outra");
                        if (r.Situacao == SituacaoRosto.Identificado)
                            identificaveis[id].Add(i);
                    }
                }
                Console.WriteLine("Cadastrada " + id + ": " + identificaveis[id].Count + "/" + rostos[id].Count + " fotos identificadas (nenhuma como outra pessoa); distância " +
                    Faixa(genuinos.Where(g => g.Item1 == id).Select(g => g.Item2.Distancia)) + "; margem " + Faixa(genuinos.Where(g => g.Item1 == id).Select(g => g.Item2.Margem)) + ".");
                Check(identificaveis[id].Count >= 1, "Pessoa " + id + " reconhecida em pelo menos uma foto fora do modelo");
            }

            // --- 4. Pessoa desconhecida -----------------------------------------------------------
            var cruzados = new List<ComparacaoFacial>();
            var desconhecidos = new List<ComparacaoFacial>();
            using (ModeloFacial completo = ModeloFacial.Treinar(rostos[125].Concat(rostos[129]).ToList(),
                Enumerable.Repeat(125, rostos[125].Count).Concat(Enumerable.Repeat(129, rostos[129].Count)).ToList()))
            {
                foreach (Mat rosto in todosDesconhecidos)
                    desconhecidos.Add(completo.Comparar(rosto));
            }
            int aceitosDetectados = desconhecidos.Take(desconhecidosDetectados.Count).Count(c => Apresentar(c).Situacao == SituacaoRosto.Identificado);
            int aceitosInteiros = desconhecidos.Skip(desconhecidosDetectados.Count).Count(c => Apresentar(c).Situacao == SituacaoRosto.Identificado);
            Console.WriteLine("Desconhecidos x modelo das duas pessoas: " + (aceitosDetectados + aceitosInteiros) + "/" + desconhecidos.Count + " identificados; distância " +
                Faixa(desconhecidos.Select(c => c.Distancia)) + "; margem " + Faixa(desconhecidos.Select(c => c.Margem)) + ".");
            Check(aceitosDetectados == 0 && aceitosInteiros == 0, "Nenhum desconhecido identificado");
            // A outra pessoa da mesma câmera como desconhecida: modelo treinado só com uma das duas.
            foreach (int id in Ids)
            {
                int outro = id == 125 ? 129 : 125;
                using (ModeloFacial soOutro = ModeloFacial.Treinar(rostos[outro], Enumerable.Repeat(outro, rostos[outro].Count).ToList()))
                    foreach (Mat rosto in rostos[id])
                        cruzados.Add(soOutro.Comparar(rosto));
            }
            int cruzadosAceitos = cruzados.Count(c => Apresentar(c).Situacao == SituacaoRosto.Identificado);
            Console.WriteLine("Mesma câmera, pessoa fora do modelo: " + cruzadosAceitos + "/" + cruzados.Count + " identificados; distância " + Faixa(cruzados.Select(c => c.Distancia)) + ".");
            Check(cruzadosAceitos == 0, "Pessoa fora do modelo (mesma câmera) não identificada");

            Console.WriteLine("Limite | margem | cadastrada aceita (1 quadro) | mesma câmera aceita | desconhecidos aceitos");
            foreach (double l in new double[] { 80, 85, 90, 95, 100 })
                foreach (double m in new double[] { 0, 10 })
                {
                    Func<Tuple<int, ComparacaoFacial>, bool> genOk = g => g.Item2.Rotulo == g.Item1 && g.Item2.Distancia < l && g.Item2.Margem >= m;
                    Console.WriteLine(string.Format("{0,6} | {1,6} | {2,2}/{3} | {4,2}/{5} | {6,3}/{7}", l, m,
                        genuinos.Count(genOk), genuinos.Count,
                        cruzados.Count(c => c.Distancia < l), cruzados.Count,
                        desconhecidos.Count(c => c.Distancia < l && c.Margem >= m), desconhecidos.Count));
                }

            // --- 5 e 6. Do quadro ao banco (banco descartável próprio, com os ids 125 e 129) ---------------
            raiz = new MySqlConnection(new MySqlConnectionStringBuilder { Server = "127.0.0.1", Port = 3306, UserID = "root", Password = "", ConnectionTimeout = 5 }.ConnectionString);
            raiz.Open();
            banco = "onefit_reconhecimento_test_" + DateTime.Now.ToString("yyyyMMdd_HHmmss");
            if (!Regex.IsMatch(banco, @"^onefit_reconhecimento_test_\d{8}_\d{6}$"))
                throw new Exception("Nome de banco inesperado.");
            Executar(raiz, "CREATE DATABASE `" + banco + "`");
            foreach (string tabela in new[] { "usuarios", "matricula", "cadastro_profissional" })
                Executar(raiz, "CREATE TABLE `" + banco + "`.`" + tabela + "` LIKE `" + args[3] + "`.`" + tabela + "`");
            string local = new MySqlConnectionStringBuilder { Server = "127.0.0.1", Port = 3306, UserID = "root", Password = "", Database = banco, ConnectionTimeout = 5 }.ConnectionString;
            using (var dados = new MySqlConnection(local))
            {
                dados.Open();
                Executar(dados, @"INSERT INTO usuarios (id_usuario, nome, senha, nacionalidade, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, tipo_usuario, status) VALUES
                    (125, 'Pessoa 125', 'x', 'BR', '1990-01-01', 'outro', '00000000125', 'Rua Teste, 1', 'Santos/SP', 'p125@example.test', 1, '(11) 90000-0125', 'aluno', 'ativo'),
                    (129, 'Pessoa 129', 'x', 'BR', '1990-01-01', 'outro', '00000000129', 'Rua Teste, 2', 'Santos/SP', 'p129@example.test', 1, '(11) 90000-0129', 'admin', 'ativo')");
                Executar(dados, @"INSERT INTO matricula (id_usuario, id_plano, data_matricula, data_inicio, data_fim, status, valor_contratado)
                    VALUES (125, 1, CURDATE(), CURDATE(), CURDATE(), 'pendente', 100)");

                int chamadas = 0;
                Func<MySqlConnection> fabrica = () => { chamadas++; return new MySqlConnection(local); };
                Func<MySqlConnection> semBanco = () => { chamadas++; throw new InvalidOperationException("sem configuração"); };
                Func<MySqlConnection> servidorFora = () => { chamadas++; return new MySqlConnection(new MySqlConnectionStringBuilder { Server = "127.0.0.1", Port = 1, UserID = "root", Password = "", Database = banco, ConnectionTimeout = 2 }.ConnectionString); };

                // Uma foto de cada pessoa fora do modelo, com um único rosto na imagem.
                var foto = new Dictionary<int, string>();
                var modeloSemFoto = new Dictionary<int, ModeloFacial>();
                foreach (int id in Ids)
                {
                    int indice = identificaveis[id].FirstOrDefault(i => QuantidadeDeRostos(arquivos[id][i]) == 1);
                    Check(identificaveis[id].Contains(indice) && QuantidadeDeRostos(arquivos[id][indice]) == 1, "Foto de " + id + " com um rosto e reconhecida");
                    foto[id] = arquivos[id][indice];
                    var treino = new List<Mat>(); var rotulos = new List<int>();
                    foreach (int outro in Ids)
                        for (int j = 0; j < rostos[outro].Count; j++)
                            if (outro != id || j != indice) { treino.Add(rostos[outro][j]); rotulos.Add(outro); }
                    modeloSemFoto[id] = ModeloFacial.Treinar(treino, rotulos);
                }
                Func<int, Func<MySqlConnection>, Tuple<Identificacao, DecisaoCatraca>> tentar = (id, f) =>
                {
                    using (Mat imagem = CvInvoke.Imread(foto[id], ImreadModes.ColorBgr))
                    {
                        Identificacao ident;
                        DecisaoCatraca decisao = Tentativa(imagem, modeloSemFoto[id], f, out ident);
                        return Tuple.Create(ident, decisao);
                    }
                };

                // Pessoa cadastrada reconhecida e autorizada (admin ativo).
                var t = tentar(129, fabrica);
                Check(t.Item1.Situacao == SituacaoRosto.Identificado && t.Item1.IdUsuario == 129 && t.Item2.Liberado && t.Item2.Motivo == "ok", "129 (admin ativo) reconhecido e liberado");
                Console.WriteLine("Cadastrada reconhecida: 129 -> " + t.Item2.Motivo + " (distância " + t.Item1.Comparacao.Distancia.ToString("F1") + ", margem " + t.Item1.Comparacao.Margem.ToString("F1") + ").");
                // Reconhecida, mas sem autorização: aluno com matrícula pendente; admin inativo.
                t = tentar(125, fabrica);
                Check(t.Item1.IdUsuario == 125 && !t.Item2.Liberado && t.Item2.Motivo == "sem matrícula válida hoje", "125 (aluno com matrícula pendente) reconhecido e negado");
                Console.WriteLine("Reconhecida sem autorização: 125 (matrícula pendente) -> " + t.Item2.Motivo + ".");
                Executar(dados, "UPDATE usuarios SET status = 'inativo' WHERE id_usuario = 129");
                t = tentar(129, fabrica);
                Check(t.Item1.IdUsuario == 129 && !t.Item2.Liberado && t.Item2.Motivo == "conta inativa ou bloqueada", "129 inativo reconhecido e negado");
                Console.WriteLine("Reconhecida sem autorização: 129 (conta inativa) -> " + t.Item2.Motivo + ".");
                Executar(dados, "UPDATE matricula SET status = 'ativa', data_inicio = DATE_SUB(CURDATE(), INTERVAL 5 DAY), data_fim = DATE_ADD(CURDATE(), INTERVAL 25 DAY) WHERE id_usuario = 125");
                t = tentar(125, fabrica);
                Check(t.Item2 != null && t.Item2.Liberado, "125 com matrícula ativa e vigente: liberado");
                // Falha no banco.
                foreach (var falha in new[] { semBanco, servidorFora })
                {
                    t = tentar(125, falha);
                    Check(t.Item1.IdUsuario == 125 && !t.Item2.Liberado && t.Item2.Motivo == "falha ao consultar o banco", "Falha no banco nega");
                }
                Console.WriteLine("Falha no banco (sem configuração e servidor fora do ar): negado.");
                int chamadasAntes = chamadas;
                Check(chamadasAntes == 6, "Uma consulta por tentativa identificada (" + chamadasAntes + ")");

                // Desconhecido: nenhuma consulta ao banco.
                int desconhecidosComUmRosto = 0;
                foreach (string arquivo in arquivosDesconhecidos.Where((a, i) => i % 10 == 0))
                {
                    using (Mat imagem = CvInvoke.Imread(arquivo, ImreadModes.ColorBgr))
                    {
                        Identificacao ident;
                        DecisaoCatraca decisao = Tentativa(imagem, modeloSemFoto[129], fabrica, out ident);
                        Check(decisao == null && ident.Situacao != SituacaoRosto.Identificado, "Desconhecido não chega ao banco");
                        if (ident.Situacao == SituacaoRosto.Desconhecido)
                            desconhecidosComUmRosto++;
                    }
                }
                Check(chamadas == chamadasAntes && desconhecidosComUmRosto > 0, "Desconhecidos não consultam o banco");
                Console.WriteLine("Desconhecido: " + desconhecidosComUmRosto + " quadros com um rosto, nenhum identificado, 0 consultas ao banco.");

                // Vários rostos: as duas fotos lado a lado.
                using (Mat a = CvInvoke.Imread(foto[125], ImreadModes.ColorBgr))
                using (Mat b = CvInvoke.Imread(foto[129], ImreadModes.ColorBgr))
                using (Mat a2 = new Mat())
                using (Mat b2 = new Mat())
                using (Mat lado = new Mat())
                {
                    CvInvoke.Resize(a, a2, new Size(a.Width * 480 / a.Height, 480));
                    CvInvoke.Resize(b, b2, new Size(b.Width * 480 / b.Height, 480));
                    CvInvoke.HConcat(a2, b2, lado);
                    Identificacao ident;
                    DecisaoCatraca decisao = Tentativa(lado, modeloSemFoto[129], fabrica, out ident);
                    using (Mat cinza = new Mat())
                    {
                        CvInvoke.CvtColor(lado, cinza, ColorConversion.Bgr2Gray);
                        int achados = PreparoFacial.DetectarRostos(_detector, cinza).Length;
                        Check(achados >= 2 && ident.Situacao == SituacaoRosto.VariosRostos && decisao == null && chamadas == chamadasAntes, "Dois rostos: nenhuma identidade e nenhuma consulta");
                        Console.WriteLine("Vários rostos: " + achados + " rostos na imagem -> nenhuma identidade, 0 consultas ao banco.");
                    }
                }
                foreach (ModeloFacial m in modeloSemFoto.Values)
                    m.Dispose();
            }

            Console.WriteLine("Reconhecimento: " + _checks + " verificações passaram.");
            return 0;
        }
        catch (Exception erro)
        {
            Console.Error.WriteLine(erro.Message);
            return 1;
        }
        finally
        {
            if (raiz != null)
            {
                if (banco != null)
                    Executar(raiz, "DROP DATABASE IF EXISTS `" + banco + "`");
                raiz.Close();
            }
        }
    }
}
