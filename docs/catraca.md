# Catraca facial (OneFit.Turnstile)

A aplicação C# fica em `%USERPROFILE%\source\repos\OneFit.Turnstile`, **fora deste repositório Git**. Os testes deste repositório compilam e testam esse projeto.

## Como a entrada é decidida

O reconhecimento facial só diz **quem** é a pessoa. A decisão é tomada por `AutorizacaoCatraca.cs`, que consulta o banco no momento da tentativa e segue a mesma política de `config/papeis.php`:

| Cargo | Libera quando | Nega quando |
|---|---|---|
| Aluno | conta `ativo` **e** matrícula `ativa` com datas válidas e hoje entre `data_inicio` e `data_fim` (inclusive) | matrícula pendente, vencida ou cancelada, antes do início, depois do fim, datas zeradas ou inválidas, sem matrícula, conta inativa ou bloqueada |
| Professor (e o legado `profissional`) | conta `ativo` **e** `cadastro_profissional.status = 'ativo'` | cadastro inativo ou ausente, conta inativa |
| Diretor, admin, recepcionista, vendedor, faxineira | conta `ativo` (sem matrícula) | conta inativa ou bloqueada |
| Cargo vazio ou desconhecido, pessoa inexistente | — | sempre |
| Erro de conexão ou de consulta, senha não configurada | — | sempre |

- Datas aceitas pelo MySQL remoto (sem modo estrito), como `0000-00-00` ou mês e dia zero, nunca contam como período válido.
- A catraca só lê `usuarios`, `matricula` e `cadastro_profissional`. Não grava, não renova e não ativa nada.
- Uma matrícula pendente nunca libera, nem com pagamento associado. Pagamentos reais estão fora do escopo: a confirmação está descrita em [matriculas.md](matriculas.md).

## O que a tela faz

- **Modo de validação:** só exibe `[Validação] Acesso autorizado` ou `[Validação] Acesso negado: <motivo>`. **Não existe código de acionamento físico**: nada de porta serial, relé, GPIO ou chamada a equipamento.
- **Uma consulta por tentativa, em segundo plano.** A câmera não trava se o banco demorar ou cair. A decisão exibida vale só para a pessoa consultada e por até 2 segundos; depois disso, nova consulta.
- **Mais de um rosto na imagem:** nenhuma decisão ("uma pessoa por vez").
- **Só uma identidade confirmada chega ao banco** (regra abaixo). Rosto não reconhecido, ainda em confirmação ou com resultado ambíguo: nenhuma consulta e nenhuma decisão.
- **Cadastro de fotos:** a catraca não grava fotos. O cadastro é feito no painel, que aplica as permissões e o limite de três fotos ativas (ver [cadastro-facial-usuarios.md](cadastro-facial-usuarios.md)).

## Reconhecimento facial

O modelo LBPH é treinado no próprio PC a partir das pastas `OneFitFaceTest\125` e `OneFitFaceTest\129` da Área de Trabalho e lido de `OneFitFaceTest\modelo_facial.yml`. **Só os ids 125 e 129 são reconhecidos.** As fotos cadastradas no painel (`usuarios_fotos_faciais`) ainda não alimentam esse modelo.

**Por que um desconhecido podia virar o id 129.** O LBPH sempre devolve a pessoa treinada mais parecida: com duas pessoas no modelo, qualquer rosto vira 125 ou 129. O que barrava um estranho era só o limite de distância, 100, que nunca tinha sido medido. Além disso, bastava um único quadro da câmera abaixo do limite.

**Regra atual** (`ReconhecimentoFacial.cs`). Para um rosto seguir para a consulta no banco, é preciso:

1. exatamente um rosto na imagem;
2. pessoa conhecida pela catraca (125 ou 129);
3. distância abaixo de **90**;
4. distância à segunda pessoa mais parecida pelo menos **10** maior (margem);
5. o mesmo resultado em **5 quadros seguidos**.

Qualquer quadro fora disso zera a confirmação. A decisão de entrada continua sendo de `AutorizacaoCatraca` (cargo, conta, matrícula e datas).

**Medição de 20/10/2026** (`tests/catraca-reconhecimento.ps1`). Cadastradas: cada foto testada fora do modelo. Desconhecidos: 400 rostos de 40 pessoas da base pública AT&T/ORL.

| | Distância | Limite 100, 1 quadro (antes) | Regra atual |
|---|---|---|---|
| Pessoa 125 (13 fotos com rosto) | 76,6 a 89,0 | 13/13 | 13/13 identificadas |
| Pessoa 129 (7 fotos com rosto, de 15) | 79,0 a 113,3 | 5/7 | **4/7** identificadas |
| Pessoa fora do modelo, mesma câmera (20) | 99,7 a 128,9 | 2/20 aceitas | 0/20 |
| Desconhecidos AT&T (400), modelo em uso | 98,5 a 139,4 | **4/400 aceitos como 125** | 0/400 |

- Nenhuma foto foi identificada como a outra pessoa.
- Com limite 100 e margem 10, os 4 desconhecidos também seriam barrados: a margem é uma segunda barreira, independente do limite.

**Limitações: o reconhecimento não está comprovado como seguro.**

- **Amostra pequena:** só duas pessoas cadastradas e 20 fotos utilizáveis.
- **Fotos, não câmera:** as distâncias medidas vêm de fotos, não de quadros da câmera; a regra dos 5 quadros não foi medida com vídeo.
- **Base diferente:** os rostos AT&T são de outra câmera, em tons de cinza; desconhecidos reais na mesma sala tendem a ficar mais perto do limite, como mostram os 99,7 da "mesma câmera".
- **Pessoa 129:** foi reconhecida em apenas 4 de 7 fotos. Pode demorar ou falhar ao vivo até o modelo ter mais fotos utilizáveis dela.
- **Foto ou tela de celular:** o LBPH não detecta se é uma pessoa real. Uma foto impressa ou a tela de um celular com o rosto de uma pessoa cadastrada pode ser identificada como ela.

## O que foi verificado

| Nível | Situação |
|---|---|
| Regras (C#) contra banco descartável | `tests/catraca-autorizacao.ps1`: todos os casos da tabela acima, três consultas repetidas sem alterar nenhuma tabela, falha de conexão, senha ausente e servidor fora do ar |
| Compilação | MSBuild da solução, Debug, 0 avisos e 0 erros |
| Integração com o banco remoto | consulta real da catraca em transação `READ ONLY`, sem gravar |
| Reconhecimento com fotos | `tests/catraca-reconhecimento.ps1`: fotos reais fora do modelo, 400 desconhecidos, mesma câmera, dois rostos e o fluxo até o banco descartável (reconhecida e liberada, reconhecida e negada, falha no banco) |
| Câmera e reconhecimento ao vivo | **não testados** de forma automatizada |
| Liberação física | **não existe** |

## Roteiro da demonstração

1. Abrir a solução no Visual Studio e compilar (ou rodar `bin\Debug\OneFit.Turnstile.exe` depois de compilar). Um executável antigo, anterior a 20/10/2026, libera qualquer conta ativa, sem olhar a matrícula.
2. Conferir que a variável de ambiente `ONEFIT_MYSQL_PASSWORD` existe. Sem ela, a tela mostra "Acesso negado: falha ao consultar o banco".
3. Conferir que aparece "Modelo facial carregado com sucesso!" ao abrir.
4. Antes da apresentação, usar **Testar reconhecimento** com fotos novas das duas pessoas e de alguém que não está no modelo. A tela mostra a distância, a margem e se o quadro passaria na regra; anote os valores.
   - Se quem **não** está no modelo passar na regra, não use a câmera na demonstração.
   - Se uma pessoa cadastrada não passar, tire mais fotos dela (de preferência 10 ou mais com o rosto detectado, na luz da sala) e use **Treinar modelo**; a câmera passa a usar o modelo novo quando ele é salvo no mesmo arquivo.
   - Depois de treinar, rode de novo `powershell -File tests/catraca-reconhecimento.ps1`.
5. Na câmera, uma pessoa por vez, olhando de frente e parada até a decisão (antes dela aparece "Confirmando identidade..."). O resultado depende dos dados atuais do banco: um aluno só é liberado com matrícula `ativa` e vigente.
6. Para mostrar os demais casos (pendente, vencida, cancelada, datas inválidas, professor, faxineira, cargo desconhecido, falha de banco), rodar os testes automatizados:

```
powershell -File tests/funcionarios-preparar.ps1
powershell -File tests/catraca-autorizacao.ps1
powershell -File tests/catraca-reconhecimento.ps1   # baixa a base AT&T (cerca de 4 MB) na primeira vez
```

## Pendências

- Validar ao vivo, com a câmera e pessoas fora do modelo, o limite, a margem e os 5 quadros.
- Mais fotos utilizáveis da pessoa 129.
- Detecção de pessoa real (contra foto impressa ou tela de celular).
- Treinar o modelo a partir das fotos do painel, em vez das pastas locais.
- Integrar a liberação física, que exige o equipamento e o protocolo dele.
- Colocar o projeto da catraca sob controle de versão.
