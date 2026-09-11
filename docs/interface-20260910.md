Implementação das sete alterações de interface, perfil e notificações solicitadas.

Notificações de compra são enviadas depois do commit, separadamente do aviso ao comprador. O destinatário precisa continuar sendo administrador ativo no banco. A chave composta de `notificacoes_eventos` impede duplicação por pedido e destinatário; o registro do evento e a notificação usam a mesma transação. Os links abrem a seção Vendas filtrada pelo pedido e o dashboard revalida a autorização administrativa. Falhas de entrega não desfazem a compra.

Os seletores de estados e nacionalidade mantêm um select real para envio e validação, com aprimoramento progressivo por JavaScript. Incluem pesquisa sem distinção de acentos, teclado, toque, indicação de seleção e ARIA. Sem JavaScript, os selects nativos continuam disponíveis. Estados preservam a UF; países novos usam ISO 3166-1 alpha-2. Valores antigos podem permanecer exatamente como estão, sem migração de dados pessoais. Nome e CPF foram removidos do UPDATE de autoedição; as telas administrativas continuam sendo o caminho para sua alteração.

Idioma é persistido em `preferencias_usuario.idioma`, com valores `pt-BR`, `en` e `es`. É recarregado do banco após login e nas requisições autenticadas. Os dicionários ficam em `config/locales/`, com a mensagem original em português como fallback. Chamadas explícitas traduzem textos fixos de interface; não existe substituição indiscriminada do HTML renderizado nem tradução de dados cadastrados. Home, navegação, autenticação, recuperação de senha, matrícula, marketplace, carrinho e componentes do dashboard foram integrados. Mensagens de login, matrícula, preferências, perfil e checkout também foram contempladas.

Tema global é persistido como `configuracoes_site.chave = 'tema_cores'`. Aceita `dourado`, `azul`, `verde`, `vermelho` e `roxo`. O formulário administrativo oferece amostras e só grava no botão Salvar. O endpoint exige sessão, CSRF e administrador ativo consultado no banco sob bloqueio. A configuração é relida a cada página; os assets usam versão por arquivo. A paleta está centralizada em `assets/css/interface.css`; os tokens anteriores foram mantidos como aliases. O modo claro/escuro individual continua separado do tema global.

O lightbox utiliza dialog modal nativo, botão de fechamento, Escape, clique no fundo, foco preso e restauração de foco. Somente imagens reais JPG/PNG/WEBP dentro de `assets/img/uploads/perfil/`, verificadas por realpath e conteúdo, podem ser ampliadas. URLs externas, arquivos inexistentes e caminhos que escapam dessa pasta não abrem o modal.

Migração aplicada uma vez no banco de desenvolvimento confirmado pelo usuário: `20260910_interface_v1`. O SQL aditivo compatível com MySQL 5.7 fica em `database/migrations/interface-20260910.sql`; o executor CLI `database/migrate-interface.php` faz backup, verificação, bloqueio contra execução simultânea e registro em `onefit_migrations`. Como DDL faz commit implícito no MySQL, não se promete rollback de alterações de estrutura. O executor permite retomar uma execução parcial sem repetir a adição da coluna.

Backup completo anterior à migração, fora do Git e da raiz pública, com ACL restrita ao usuário do Windows:

`C:\Users\dsiqu\OneFit-private-backups\interface-20260910\onefit-before-interface-20260911-001754-8f5fc7aa.json`

SHA-256: `a0574b1181bb6042c7dab4b2cb6739a6e96e993b166f46e404cb70fd53a47771`

O backup contém a definição das 28 tabelas existentes e seus registros completos, com células codificadas em base64, contagens e hashes por tabela. A leitura e o SHA-256 foram verificados novamente em processo separado. A comparação antes/depois da migração confirmou preservação dos campos anteriores de todas as tabelas. Não foram gravadas compras, notificações de teste ou preferências de teste no banco de desenvolvimento. Não houve commit ou push. A alteração preexistente em `index.php` foi preservada.

Origens dos dados e assets:

- Bandeiras: [akagabi/bandeira-dos-estados-do-brasil](https://github.com/akagabi/bandeira-dos-estados-do-brasil), commit `93624a385c870dc11a2ca686a89d3ab73b43538e`, licença MIT incluída. SVGs originais por estado, com remoção apenas de comentários e metadados de edição. URLs e SHA-256 individuais em `assets/img/flags/states/sources.json`. Sem hotlink. As 27 bandeiras foram renderizadas e conferidas visualmente em conjunto.
- Códigos de países e territórios: [ISO-3166-Countries-with-Regional-Codes](https://github.com/lukes/ISO-3166-Countries-with-Regional-Codes). Nomes em português: [Unicode CLDR](https://github.com/unicode-org/cldr-json). Avisos de licença em `config/locales/`. Os emojis de países usam o código regional; o nome e código continuam legíveis quando o sistema não desenha o emoji. O navegador pode localizar nomes padronizados com Intl.DisplayNames.
- Interação do seletor baseada no [padrão combobox da WAI-ARIA APG](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/).

Validação executada:

- 74 verificações unitárias de traduções, fallback, nacionalidades legadas/ISO, confinamento de fotos, correspondência de UF/hash e integridade SVG.
- 19 verificações com SQL real em banco local isolado com dados fictícios: compras de aluno e profissional, deduplicação, destinatários ativos/autorizados, revogação, mensagens por idioma, falha de notificação sem perda do pedido, retry e preservação dos demais campos.
- 34 verificações em Chrome headless, desktop e viewport móvel com toque: seleção, acentos, teclado, envio e persistência, tentativa manual de alterar identidade/usuário, nacionalidade inválida, modal e foco, CSRF, idioma inválido, novo login, páginas, cinco temas e conteúdo cadastrado preservado.
- 20 combinações de contraste: texto de destaque sobre superfície e texto sobre as duas cores do botão, nos cinco temas e modos claro/escuro, todas acima de 4,5:1. Isso não equivale a uma auditoria completa de WCAG de todos os elementos existentes.
- Regressões existentes executadas na cópia isolada: checkout (37 verificações), consultas de compras (75), renderização de compras (10), treino (24), renderização do treino, perfil PHP/JavaScript e PIX sem frete (15).
- Migração, backup e segunda execução sem alterações testados primeiro na base fictícia. Aplicação real seguida de verificação dos dados preservados e backup.
- Home, login, matrícula, marketplace e recuperação de senha da instalação real responderam HTTP 200, sem avisos/erros PHP no HTML, com idioma e tema padrão corretos.

Limites da validação: testes automatizados de navegador usaram Chrome e emulação móvel, sem leitor de tela humano ou aparelho iOS/Android físico. A base isolada usa MariaDB 10.4; o SQL evita recursos incompatíveis com MySQL 5.7. Conteúdo cadastrado, depoimentos, marcas e nomes não são traduzidos; textos ainda ausentes do catálogo permanecem em português. Pagamentos mantêm a simulação existente, sem teste bancário real. O teste não envia e-mails ou mensagens externas.

Os arquivos criados e alterados estão listados em `interface-20260910-files.txt`.
