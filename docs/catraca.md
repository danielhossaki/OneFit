# Catraca facial (OneFit.Turnstile)

A aplicação C# fica em `%USERPROFILE%\source\repos\OneFit.Turnstile`, **fora deste repositório Git**. Os testes deste repositório compilam e testam esse projeto.

## Como a entrada é decidida

O reconhecimento facial só diz **quem** é a pessoa. A decisão é tomada por `AutorizacaoCatraca.cs`, que consulta o banco no momento da tentativa e segue a mesma política de `config/papeis.php`:

| Cargo | Libera quando | Nega quando |
|---|---|---|
| Aluno | conta `ativo` **e** matrícula `ativa` com datas válidas e hoje entre `data_inicio` e `data_fim` (inclusive) | matrícula pendente, vencida ou cancelada, antes do início, depois do fim, datas zeradas ou inválidas, sem matrícula, conta inativa ou bloqueada |
| Professor (e o legado `profissional`) | conta `ativo` **e** `cadastro_profissional.status = 'ativo'` | cadastro inativo ou ausente, conta inativa |
| Admin, recepcionista, vendedor, faxineira | conta `ativo` (sem matrícula) | conta inativa ou bloqueada |
| Cargo vazio ou desconhecido, pessoa inexistente | — | sempre |
| Erro de conexão ou de consulta, senha não configurada | — | sempre |

- Datas aceitas pelo MySQL remoto (sem modo estrito), como `0000-00-00` ou mês e dia zero, nunca contam como período válido.
- A catraca só lê `usuarios`, `matricula` e `cadastro_profissional`. Não grava, não renova e não ativa nada.
- Uma matrícula pendente nunca libera, nem com pagamento associado. Pagamentos reais estão fora do escopo: a confirmação está descrita em [matriculas.md](matriculas.md).

## O que a tela faz

- **Modo de validação:** só exibe `[Validação] Acesso autorizado` ou `[Validação] Acesso negado: <motivo>`. **Não existe código de acionamento físico**: nada de porta serial, relé, GPIO ou chamada a equipamento.
- **Uma consulta por tentativa, em segundo plano.** A câmera não trava se o banco demorar ou cair. A decisão exibida vale só para a pessoa consultada e por até 2 segundos; depois disso, nova consulta.
- **Mais de um rosto na imagem:** nenhuma decisão ("uma pessoa por vez").
- **Rosto não reconhecido** ou distância acima do limite: nenhuma consulta e nenhuma decisão.
- **Cadastro de fotos:** a catraca não grava fotos. O cadastro é feito no painel, que aplica as permissões e o limite de três fotos ativas (ver [cadastro-facial-usuarios.md](cadastro-facial-usuarios.md)).

## Reconhecimento: limites conhecidos

- O modelo LBPH é treinado no próprio PC a partir das pastas `OneFitFaceTest\125` e `OneFitFaceTest\129` da Área de Trabalho, e lido de `OneFitFaceTest\modelo_facial.yml`. **Só os ids 125 e 129 são reconhecidos.**
- As fotos cadastradas no painel (`usuarios_fotos_faciais`) ainda não alimentam esse modelo.
- O limite de distância (100) **não foi calibrado**. Com só duas pessoas no modelo, um rosto desconhecido sempre é comparado com uma delas; o que barra um estranho é só esse limite.

## O que foi verificado

| Nível | Situação |
|---|---|
| Regras (C#) contra banco descartável | `tests/catraca-autorizacao.ps1`: todos os casos da tabela acima, três consultas repetidas sem alterar nenhuma tabela, falha de conexão, senha ausente e servidor fora do ar |
| Compilação | MSBuild da solução, Debug, 0 avisos e 0 erros |
| Integração com o banco remoto | consulta real da catraca em transação `READ ONLY`, sem gravar |
| Câmera e reconhecimento ao vivo | **não testados** de forma automatizada |
| Liberação física | **não existe** |

## Roteiro da demonstração

1. Abrir a solução no Visual Studio e compilar (ou rodar `bin\Debug\OneFit.Turnstile.exe` depois de compilar). Um executável antigo, anterior a 20/10/2026, libera qualquer conta ativa, sem olhar a matrícula.
2. Conferir que a variável de ambiente `ONEFIT_MYSQL_PASSWORD` existe. Sem ela, a tela mostra "Acesso negado: falha ao consultar o banco".
3. Conferir que aparece "Modelo facial carregado com sucesso!" ao abrir.
4. Antes da apresentação, usar **Testar reconhecimento** com fotos novas das duas pessoas e de alguém que não está no modelo, e anotar as distâncias. Se a de quem não está no modelo ficar abaixo de 100, o limite precisa ser reduzido.
5. Na câmera, uma pessoa por vez. O resultado depende dos dados atuais do banco: um aluno só é liberado com matrícula `ativa` e vigente.
6. Para mostrar os demais casos (pendente, vencida, cancelada, datas inválidas, professor, faxineira, cargo desconhecido, falha de banco), rodar os testes automatizados:

```
powershell -File tests/funcionarios-preparar.ps1
powershell -File tests/catraca-autorizacao.ps1
```

## Pendências

- Calibrar o limite de distância com fotos de pessoas fora do modelo.
- Treinar o modelo a partir das fotos do painel, em vez das pastas locais.
- Integrar a liberação física, que exige o equipamento e o protocolo dele.
- Colocar o projeto da catraca sob controle de versão.
