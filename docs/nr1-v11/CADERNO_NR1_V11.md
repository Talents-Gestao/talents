**TALENTS / NR-1**

Caderno único de desenvolvimento

Especificação funcional e técnica para implementar o Dashboard Psicossocial V11 no sistema Talents.

VERSÃO BASE · DASHBOARD V11 \| 22 OUTUBRO 2026

| **PARA QUEM**  | **REFERÊNCIA**        | **OBJETIVO**                                                                        |
|----------------|-----------------------|-------------------------------------------------------------------------------------|
| Murilo e Suede | HTML Talents NR-1 V11 | Implementar produção, com dados reais, segurança, documentos e fluxos persistentes. |

## Decisão de escopo

A Talents fornece a preparação da campanha, aplicação da pesquisa, resultados agregados, interpretação, proposta de plano de ação, relatório técnico integrado e serviços associados. A matriz ocupacional 4×4 não integra o fluxo obrigatório da Talents: eventual classificação de risco é tratada no GRO/PGR pela organização e por seu prestador de SST. A aprovação documental da PASSEG não equivale, por si, à integração concluída ao PGR.

## O que entregar nesta fase

- [ ] Dashboard real com perfis, indicadores e navegação da V11.

- [ ] Pesquisa operacional (39 perguntas e 7 dimensões), com metodologia homologada.

- [ ] Dois documentos: resumo executivo e relatório técnico + plano de ação.

- [ ] Fluxo simples Talents → revisão PASSEG → publicação para a empresa.

- [ ] Serviços adicionais e fila comercial, sem acesso a respostas individuais.

Documento de referência para implementação — não substitui homologação metodológica, jurídica, de privacidade e de SST.

# 1 Como usar este caderno

Este é o ÚNICO documento de requisitos para desenvolvimento da V11. O HTML aprovado é a referência visual; este caderno complementa o que o protótipo não implementa (banco de dados, acessos, regras, segurança, auditoria e integrações).

| **MARCAÇÃO**         | **SIGNIFICADO**                                                                     |
|----------------------|-------------------------------------------------------------------------------------|
| V11 — EXISTE NO HTML | Já demonstrado visualmente, mas pode ter dados fictícios e estados não persistidos. |
| IMPLEMENTAR          | Comportamento obrigatório no sistema real.                                          |
| VALIDAR              | Depende de confirmação técnica, comercial ou jurídica; não bloquear o esqueleto.    |
| NÃO IMPLEMENTAR      | Fluxos que foram descartados nas decisões da V11.                                   |

# 2 Regras de produto que não podem mudar

- Interface real do dia a dia: nada de textos explicando ao programador nas telas do usuário.

- Três opções visíveis ao cliente: Orientação NR-1 incluída; Treinamento Online e Presencial para Time e/ou Líderes, contratáveis por público.

- Colaborador vê apenas orientação, treinamentos liberados para seu público e questionário; jamais resultados nem vendas.

- Pesquisa e resultados de percepção (escala 1–5) separados da classificação ocupacional do PGR.

- Exibir todas as dimensões em atenção, sem limitar a três resultados.

- Grupo “Outros Setores” recalculado usando respostas originais, nunca média de médias nem supressão de respondentes.

- A Talents sugere responsáveis e prazos; a empresa confirma ou altera. Registrar histórico e evidências.

- Somente 2 documentos finais: executivo e técnico integrado ao plano de ação.

- Revisão da PASSEG dentro de Documentos, não como uma aba independente.

- Resultados e documentos oficiais somente visíveis ao cliente após publicação.

- Soluções comerciais pós-publicação com critérios de pertinência, não venda automática por nota.

- Sem matriz 4×4 no dashboard obrigatório da Talents; sem sinalização de conformidade legal automática.

# 3 Jornada operacional resumida

| **ORDEM** | **ATOR**             | **EVENTO E RESULTADO**                                                            |
|-----------|----------------------|-----------------------------------------------------------------------------------|
| 01        | Talents              | Cadastra empresa/unidade/campanha, elegíveis, datas, escopo e links de materiais. |
| 02        | Empresa/Time/Líderes | Acessam materiais liberados; respondem questionário aberto via link autorizado.   |
| 03        | Sistema / Talents    | Consolida resultados, sugere prioridades e plano; Talents revisa dados e texto.   |
| 04        | Talents              | Gera versão do relatório técnico + plano de ação e envia à PASSEG.                |
| 05        | PASSEG               | Aprova documentalmente ou solicita ajustes contextualizados (versão registrada).  |
| 06        | Talents              | Publica os dois relatórios; disponibiliza resultados e ações à empresa.           |
| 07        | Empresa              | Define responsáveis/prazos, acompanha execução e registra eficácia.               |
| 08        | Sistema / Comercial  | Libera recomendações de serviços e oportunidades pertinentes.                     |

# 4 Arquitetura de telas e perfis

| **ÁREA NA V11**  | **TALENTS**             | **EMPRESA**        | **PASSEG**         | **COMERCIAL** | **COLABORADOR** |
|------------------|-------------------------|--------------------|--------------------|---------------|-----------------|
| Visão geral      | Sim                     | Após publicar      | Agregado / revisão | Não           | Não             |
| Campanha NR-1    | Configura               | Contratado/ofertas | Não                | Oportunidades | Área própria    |
| Minha jornada    | Prévia                  | Não                | Não                | Não           | Sim             |
| Resultados       | Edita antes de publicar | Após publicar      | Revisão agregada   | Não           | Não             |
| Plano de ação    | Propõe                  | Confirma/atualiza  | Lê para revisão    | Não           | Não             |
| Documentos       | Emite / publica         | Versões publicadas | Parecer/ajustes    | Não           | Não             |
| Soluções Talents | Gerencia                | Solicita contato   | Não                | Vê ofertas    | Não             |
| Oportunidades    | Consulta                | Não                | Não                | Opera CRM     | Não             |

**ATENÇÃO** Na V11 o seletor de perfil demonstra permissões apenas na interface. No sistema real, autorização precisa ser aplicada no servidor e em cada consulta, exportação e arquivo.

# 5 Tela: Visão geral

Implementar cards, gráficos e ações com o estilo V11 (lilás, roxo, verde, dourado, coral; contraste acessível).

- [ ] Cabeçalho: empresa, estabelecimento, campanha, período e status real (rascunho, coleta aberta/encerrada, revisão, publicado).

- [ ] Cards: índice geral; adesão = respondentes válidos / elegíveis × 100; número de dimensões em atenção; execução do plano de ação.

- [ ] Seção Pontos fortes: top itens favoráveis com percentuais, enunciados e base de respostas válidas.

- [ ] Sete dimensões em barras, com índice e faixa interna de interpretação.

- [ ] Lista COMPLETA das dimensões em atenção ordenadas por índice, com opção de expandir e navegar.

- [ ] Participação por grupos; “Outros Setores” exibido como grupo agregado.

- [ ] Ação de imprimir panorama respeitando a publicação e o perfil do solicitante.

Base demonstrativa da V11: 40 respostas; 48 elegíveis FICTÍCIOS (83,3%); índice 2,60; 6/7 dimensões em atenção. Nunca tornar esses números defaults imutáveis.

# 6 Tela: Campanha NR-1 e contratos

| **MODALIDADE**         | **PÚBLICO**            | **REGRA PARA EMPRESA**                                              | **REGRA PARA COLABORADOR**                                |
|------------------------|------------------------|---------------------------------------------------------------------|-----------------------------------------------------------|
| Orientação NR-1        | Todos                  | Sempre “incluído”.                                                  | Sempre visível quando campanha habilitada.                |
| Treinamento Online     | Time / Líderes / ambos | Contratado destacado; não contratado discreto + solicitar proposta. | Somente conteúdo contratado e liberado ao público.        |
| Treinamento Presencial | Time / Líderes / ambos | Contratado destacado; não contratado discreto + solicitar proposta. | Somente detalhes do evento de seu público, se contratado. |

- [ ] Talents seleciona modalidade e público contratados, preço/escopo negociado, início/fim, arquivo/link e responsável.

- [ ] Treinamento presencial aceita local, data/hora, facilitador, capacidade e presença; online aceita link/arquivo, período e disponibilidade.

- [ ] Solicitar proposta cria oportunidade COM origem, empresa, campanha, modalidade, público, solicitante, data e estado “Novo”.

- [ ] Não criar oportunidade apenas por visualização do card; bloquear pedidos duplicados abertos para mesma modalidade/público.

- [ ] Link/QR code da pesquisa respeita abertura/fechamento; não fica publicamente visível quando a campanha não está aberta.

# 7 Tela: Minha jornada (colaborador)

- [ ] Entrada simples por URL/QR de campanha e contexto do público (Time ou Líderes).

- [ ] Exibir objetivo da pesquisa, sigilo e instruções de preenchimento em linguagem clara.

- [ ] Orientação NR-1 sempre visível; Online/Presencial apenas se contratados e habilitados para o público.

- [ ] Questionário com 39 afirmações, 1 por etapa ou grupos, escala Nunca / Raramente / Às vezes / Frequentemente / Sempre.

- [ ] Navegação anterior/próxima, progresso, salvar rascunho conforme política, confirmação de envio e prevenção de envio duplicado.

- [ ] Responsividade, navegação por teclado, rótulos de leitores de tela e acessibilidade adequada.

- [ ] Não apresentar resultados, rankings por setor, ofertas, nomes de respondentes nem dados de outros colaboradores.

**ATENÇÃO** Decisão a validar: se o questionário será integralmente anônimo, não armazenar identificadores diretos e resposta no mesmo registro. Controle de convites/participação, se necessário, deve preservar o anonimato e ser avaliado sob LGPD.

# 8 Motor de pesquisa e metodologia estatística

IMPLEMENTAR uma tabela versionada das 39 perguntas, sete dimensões, polaridade e escala. O sistema deve calcular resultados a partir de respostas válidas, nunca das médias impressas no PDF.

| **ELEMENTO**            | **REGRA FUNCIONAL**                                                                                            |
|-------------------------|----------------------------------------------------------------------------------------------------------------|
| Resposta bruta          | Nunca=1, Raramente=2, Às vezes=3, Frequentemente=4, Sempre=5.                                                  |
| Enunciado desfavorável  | Escore de sinalização = resposta bruta (direto).                                                               |
| Enunciado favorável     | Escore de sinalização = 6 − resposta bruta (invertido).                                                        |
| Média por dimensão      | Somatório de escores válidos da dimensão ÷ quantidade de respostas válidas dos itens da dimensão.              |
| Índice geral            | Somatório de escores válidos dos 39 itens ÷ quantidade de respostas válidas (modelo V11).                      |
| Percentual favorável    | Positivos: Frequentemente+Sempre; desfavoráveis: Nunca+Raramente. Sempre dividir pela base válida da pergunta. |
| Percentual desfavorável | Positivos: Nunca+Raramente; desfavoráveis: Frequentemente+Sempre.                                              |
| Faixas internas V11     | 1,00–2,33 menor sinalização; 2,34–3,66 atenção; 3,67–5,00 maior sinalização.                                   |
| Participação            | Respostas completas/válidas ÷ número de elegíveis da campanha × 100.                                           |

**ATENÇÃO** As faixas, a polaridade de cada item, a regra para respostas ausentes e o peso relativo das dimensões precisam ser HOMOLOGADOS pela responsável técnica da Talents. O índice geral descrito na V11 pondera as perguntas, e não atribui pesos iguais às sete dimensões.

# 9 Resultados: dimensões, setores e perguntas

- [ ] Filtros por campanha, período, estabelecimento, dimensão e grupos liberados; período histórico somente com bases comparáveis.

- [ ] Cards coloridos por setor/grupo, adesão, quantidade de respondentes e índices efetivamente calculados.

- [ ] Gráficos por dimensão e lista completa ordenável pelo índice; usar cor e texto, não apenas cor.

- [ ] 39 cartões com distribuição percentual em cinco faixas, percentuais favoráveis/desfavoráveis, busca por texto e ordenação.

- [ ] Visualização inicial de 12 cartões com “ver todas as 39”; toda pergunta deve ser recuperável por filtro.

- [ ] Pontos positivos (ex.: clareza de responsabilidade), calculados das respostas e com base N explícita.

- [ ] Registro privado da Talents para verificação de dados, documentos ou indicação de avaliação adicional; sem aba própria de alertas.

# 10 Confidencialidade e agrupamento de setores

Regra base proposta: setor com menos de cinco respondentes não aparece isolado em resultados gerenciais; unir esses setores em “Outros Setores”. O limite cinco é política interna sugerida, não limite legal estabelecido pelo MTE.

- [ ] Agrupar por número de respostas válidas da campanha, recalcular todas as médias sobre as respostas ORIGINAIS do grupo.

- [ ] Exibir composição dos nomes dos setores somente se não permitir reidentificação; jamais oferecer filtros que revelem sua contribuição individual.

- [ ] Bloquear consultas que, por subtração de grupos ou combinação de filtros, permitam reconstruir resultados de grupos pequenos.

- [ ] As diferenças de funções podem continuar registradas internamente para discussão ocupacional, sem divulgar respostas de setor pequeno.

- [ ] Permitir supressão de agregados ou agrupamento adicional quando “Outros Setores” ainda for muito pequeno para preservar confidencialidade.

- [ ] Não reutilizar o agregado “Outros Setores” como se fosse um único grupo homogêneo de exposição ocupacional.

# 11 Plano de ação (operacional)

Cada uma das seis frentes iniciais da V11 vira registro editável por campanha. A Talents sugere medida, área responsável e prazo; a empresa confirma titular e calendário, executa e registra evidências.

| **CAMPO**              | **TIPO / COMPORTAMENTO**                                                                |
|------------------------|-----------------------------------------------------------------------------------------|
| Fator/dimensão         | Referência a resultado e descrição do problema organizacional, sem diagnóstico clínico. |
| Ação proposta          | Texto revisável pela Talents; objetivo e resultado esperado.                            |
| Responsável sugerido   | Área/função sugerida pela Talents; não equivale a aceitação.                            |
| Responsável confirmado | Usuário ou responsável cadastrado pela empresa; nome/função/contato interno.            |
| Prazo sugerido         | Dias ou data, ajustável por Talents até publicação; origem e justificativa.             |
| Prazo confirmado       | Data de vencimento e data de aprovação pela empresa.                                    |
| Status                 | Proposta → Confirmada → Em andamento → Concluída / Replanejada.                         |
| Aferição               | Indicador, evidência de execução, verificação de eficácia, data e resultado.            |
| Histórico              | Antes/depois, autor, data e justificativa de mudança.                                   |

- [ ] Talents pode propor e editar antes da publicação; empresa pode confirmar e ajustar pós-publicação conforme permissão.

- [ ] PASSEG pode pedir ajuste ao relatório/plano em Documentos; isso devolve a revisão à Talents sem excluir versões.

- [ ] Evitar que o plano pareça implementado apenas porque foi impresso ou publicado.

- [ ] A ordem gerencial da pesquisa não é automaticamente prioridade legal de risco ocupacional.

- [ ] Oferecer impressão/exportação do plano dentro do relatório técnico consolidado, não outro documento oficial.

# 12 Documentos: dois produtos finais

| **DOCUMENTO**                     | **PÚBLICO / CONTEÚDO**                                                                                                                                                                                                                                                                                                                                                                             |
|-----------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Relatório executivo               | Empresa: campanha, adesão, panorama 7 dimensões, pontos fortes com percentuais, todas as atenções, ações e responsável/prazo (sugerido ou confirmado). Objetivo 1–2 páginas.                                                                                                                                                                                                                       |
| Relatório técnico + plano de ação | Talents/PASSEG e empresa após publicação: identificação, metodologia versão e 39 itens/7 dimensões, 5 frequências, polaridade, regras de cálculo, elegíveis e base, principais evidências, resultados, grupos agregados, pontos positivos, prioridades, plano (medida, responsável, prazo, indicadores, evidências) e revisão documental. Síntese 3–5 páginas; dados de suporte ficam preservados. |

- [ ] Emitir PDF com layout de impressão, cabeçalho, página, identificador, versão, data, elaborador e revisor quando aplicável.

- [ ] Não imprimir “CNPJ não informado” ou “profissional a identificar” como se fossem informações oficiais: exigir antes de publicar ou indicar situação pendente.

- [ ] Não exibir campo fictício de 48 elegíveis em produção; validar valores da campanha.

- [ ] Anexar versão do instrumento e dados agregados de suporte de forma consultável, sem criar um terceiro relatório comercial.

- [ ] Gerar arquivo imutável/versionado por aprovação; não regenerar uma versão publicada silenciosamente ao editar dados.

# 13 Revisão PASSEG em Documentos

Fluxo mínimo: rascunho → enviado à PASSEG → pedido de ajustes OU aprovado documentalmente → publicação pela Talents. Nada de módulo obrigatório de matriz 4×4 ou aba exclusiva para PASSEG.

- [ ] Ações na página Documentos: Enviar revisão, Solicitar alteração (texto obrigatório), Aprovar documento, Publicar, Criar nova versão.

- [ ] PASSEG só visualiza a campanha/arquivos explicitamente compartilhados; pode ler, comentar e aprovar a versão enviada.

- [ ] Após pedido de ajuste, status volta à Talents; nova versão preserva pareceres anteriores e diffs básicos.

- [ ] Na empresa, não abrir resultados nem arquivos oficiais enquanto status não for “Publicado”.

- [ ] “Aprovado pela PASSEG” significa revisão do documento; campo separado “Integração ao PGR confirmada” apenas se houver evidência/documento de SST.

- [ ] Emissão final sob controle da Talents; registrar protocolo de encaminhamento e ciência da empresa.

# 14 Soluções Talents e integração comercial

Dois momentos comerciais: (A) antes da pesquisa, treinamentos contratáveis; (B) após publicação, serviços associados às necessidades organizacionais. A comercialização não interfere no resultado técnico.

| **SINALIZAÇÃO NA V11**        | **SERVIÇOS QUE PODEM SER SUGERIDOS**                                                                                     |
|-------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| Demandas e Sobrecarga         | Diagnóstico Empresarial; Contratação de Talentos; Reestruturação. Contratação só após investigar capacidade e processos. |
| Segurança Psicológica         | Voz do Time; CNV; Canal de Denúncias.                                                                                    |
| Esgotamento e Saúde Emocional | Diagnóstico Empresarial; revisão de processos e práticas de gestão.                                                      |
| Liderança e Gestão            | Treinamento de Lideranças; Profiler; PDI; feedback.                                                                      |
| Relacionamentos e Clima       | Imersão; CNV; Contrato de Expectativas.                                                                                  |
| Percepção Organizacional      | Direcionamento Estratégico; Voz do Time; Diagnóstico.                                                                    |

- [ ] Regra de matching configurável: fator, serviços elegíveis, explicação consultiva, grau de confiança e revisão pela Talents.

- [ ] Público empresário vê sugestões somente após publicação, com botão “Solicitar conversa”.

- [ ] Ao clicar, gerar lead com empresa, campanha, serviço, necessidade relacionada, data e responsável comercial.

- [ ] Para sugestões automáticas sem clique, criar apenas oportunidade “A qualificar”, não interesse declarado; Comercial pode aceitar/dispensar.

- [ ] CRM: Novo / A qualificar / Em contato / Proposta enviada / Ganho / Perdido; última atividade, próxima ação e histórico.

- [ ] Registrar recusa de serviço adicional quando houver oferta; isso encerra somente a proposta, sem declarar obrigação legal cumprida.

- [ ] O Comercial vê somente metadados e resultados agregados liberados, nunca respostas brutas.

# 15 Dados mínimos e entidades

| **ENTIDADE**            | **CAMPOS MÍNIMOS / VÍNCULOS**                                                                             |
|-------------------------|-----------------------------------------------------------------------------------------------------------|
| Organização / Unidade   | id, razão social, nome fantasia, CNPJ, unidade, responsável, contato, setor, status.                      |
| Campanha                | id, organização/unidade, ano, datas, elegíveis, meta, estado de coleta/publicação, versão de instrumento. |
| Setor / elegibilidade   | id da unidade/setor, efetivo elegível, público Time/Líderes, categoria de proteção.                       |
| Questionário/Item       | versão, 39 itens, dimensão, texto, ordem, polaridade, escala, obrigatório.                                |
| Resposta                | id técnico desvinculado de pessoa, campanha, respostas válidas, data coarsened, status completo.          |
| Agregado                | campanha, grupo, dimensão, N, soma escores, média, cinco contagens de frequência.                         |
| Treinamento/Contratação | modalidade, público, contrato, valor se aplicável, status, material, agendamento.                         |
| Plano de ação           | id, fator, medida, responsável sugerido/confirmado, datas, status, eficácia, evidências.                  |
| Documento/Parecer       | arquivo, hash, versão, elaborador/revisor, solicitação de ajuste, aprovação, publicação.                  |
| Oportunidade            | serviço, cliente, campanha, origem, usuário comercial, status, contato, próximas ações.                   |
| Auditoria               | ator, ação, objeto, timestamp, antes/depois relevante, justificativa, versão.                             |

# 16 Requisitos transversais de segurança e qualidade

- [ ] Autenticação segura, MFA para perfis internos de risco e autorização no servidor; isolamento total entre clientes.

- [ ] Criptografia em trânsito e repouso, backup, recuperação e logs de auditoria; evitar dados identificáveis de resposta.

- [ ] Princípio do mínimo privilégio, consentimento/aviso adequado conforme base legal definida, transparência e prazo de retenção documentado.

- [ ] Prevenir reidentificação em exportações, múltiplos filtros, série histórica e grupos pequenos.

- [ ] PDFs versionados; trilha de aprovação de PASSEG e Talents não editável pelo cliente.

- [ ] URLs de vídeo e questionário devem ter controle de validade; nunca publicar material pago para não contratantes.

- [ ] Tratamento de erro, estado vazio, dados ausentes, contagens N=0 e período sem pesquisa.

- [ ] Desempenho em celular e desktop; acessibilidade e contraste; formatos de moeda/data pt-BR.

- [ ] Não usar dados reais de clientes em logs de desenvolvimento e ambientes de teste.

# 17 Ordem de implementação para Murilo e Suede

| **ETAPA**       | **ENTREGÁVEL**                                                           | **PRIORIDADE** | **CRITÉRIO DE AVANÇO**                                      |
|-----------------|--------------------------------------------------------------------------|----------------|-------------------------------------------------------------|
| 0\. Fundação    | Empresas, unidades, campanhas, perfis, autenticação, banco, auditoria.   | P0             | Clientes isolados; acesso autorizado testado.               |
| 1\. Coleta      | Questionário, token/link/QR, públicos Time/Líderes, campanha e vídeos.   | P0             | 40 respostas de teste geram contagens 39/39 sem duplicação. |
| 2\. Cálculo     | 39 itens, polaridade, dimensões, percentuais, agregados e sigilo.        | P0             | Reproduz exemplo 2,60 e percentuais com testes unitários.   |
| 3\. Painel      | Visão geral, setores, perguntas, filtros, pontos fortes e exportação.    | P0             | Telas seguem HTML V11; sem vazamento de setor pequeno.      |
| 4\. Ações       | Plano com sugestão, confirmação, status, evidência e eficácia.           | P0             | Alterações persistem com autoria e data.                    |
| 5\. Docs/PASSEG | Relatórios versionados, revisão, ajustes, aprovação e publicação.        | P0             | Empresa não vê rascunhos; publicação libera versão correta. |
| 6\. Serviços    | Contratações Online/Presencial por público, pedidos e CRM.               | P1             | Interesse chega ao Comercial sem dados individuais.         |
| 7\. Operação    | Notificações, acompanhamento, histórico, filtros avançados, performance. | P1             | Operação multiempresa e reprocessamento auditável.          |
| 8\. Evolução    | Novos comparativos e integrações com sistema Talents principal.          | P2             | Somente após homologar o núcleo.                            |

# 18 Cenários de aceite essenciais

- [ ] T01 — Uma empresa não consegue ver campanhas nem documentos de outra.

- [ ] T02 — Cliente não acessa resultados/documentos oficiais antes de “Publicado”.

- [ ] T03 — PASSEG só aprova versão submetida; mudança exige nova versão.

- [ ] T04 — Retorno “Solicitar alteração” exige justificativa e mantém histórico.

- [ ] T05 — Trilha Time não recebe treinamento contratado apenas para Líderes (e vice-versa).

- [ ] T06 — Orientação NR-1 é exibida para ambos os públicos.

- [ ] T07 — Empresa solicita proposta para Online Time; surge lead no CRM sem duplicação.

- [ ] T08 — Os 39 itens aparecem com escala de frequência; não concordância.

- [ ] T09 — Item favorável inverte (6 − resposta); desfavorável permanece direto.

- [ ] T10 — Percentuais Q7: 37/40 = 92,5%; Q8: 30/40 = 75% no dataset V11.

- [ ] T11 — Com 40 respondentes e 48 elegíveis fictícios, adesão demonstrativa = 83,3%.

- [ ] T12 — “Outros Setores” agrega 15 respostas do exemplo e recalcula a base original.

- [ ] T13 — Filtro e exportação não permitem inferir setor com 1–4 respondentes.

- [ ] T14 — Lista de atenção mostra todas as seis dimensões, em ordem descendente.

- [ ] T15 — Cartões das 39 perguntas são pesquisáveis, filtráveis e ordenáveis.

- [ ] T16 — Talents edita prazo/responsável sugerido sem marcar confirmação.

- [ ] T17 — Empresa confirma responsável e prazo, e salva prova e eficácia.

- [ ] T18 — Relatório técnico impresso incorpora plano de ação; executivo separado.

- [ ] T19 — Documento oficial não contém campos vazios/fictícios na versão publicada.

- [ ] T20 — Depois de publicado, documento não muda sem nova versão.

- [ ] T21 — Oportunidades pós-publicação têm origem rastreável e sem dados brutos.

- [ ] T22 — Recusa comercial não altera classificação nem fecha pendências ocupacionais.

- [ ] T23 — Cadastro de empresa/colaborador excluído ou inativo é tratado conforme política LGPD.

- [ ] T24 — Interface funciona em celular e desktop, teclado e leitores de tela.

- [ ] T25 — Setor/empresa sem dados apresenta estado vazio, sem zerar médias automaticamente.

# 19 Pendências a decidir ANTES de colocar em produção

| **DONO**            | **DECISÃO / CRITÉRIO DE HOMOLOGAÇÃO**                                                             |
|---------------------|---------------------------------------------------------------------------------------------------|
| Talents — técnica   | Homologar redação dos 39 itens, polaridade, completude, faixas, pesos e versão do instrumento.    |
| Talents — comercial | Contratos e regras de liberação por modalidade/público; origem de leads, contatos e preço.        |
| Talents + empresa   | Cadastro de elegíveis, setores, responsáveis e política de confidencialidade.                     |
| Talents + PASSEG    | Formato do relatório; responsável por leitura, pedido de ajustes e integração técnica ao GRO/PGR. |
| Jurídico/LGPD       | Base legal, informativos, retenção, anonimização, segurança de credenciais e compartilhamento.    |
| Murilo/Suede        | Infraestrutura, migração de perguntas/dados, API de documentos, integração com CRM Talents.       |

# 20 Bases normativas e documentos-fonte

- Fonte funcional: Talents_NR1_V11_Aprovacao_Final.html (protótipo aprovado como referência de interface e jornadas).

- Norma NR-1 vigente e orientações GRO/PGR do Ministério do Trabalho: https://www.gov.br/trabalho-e-emprego/pt-br/acesso-a-informacao/participacao-social/conselhos-e-orgaos-colegiados/comissao-tripartite-partitaria-permanente/normas-regulamentadora/normas-regulamentadoras-vigentes/nr-1

- Perguntas e Respostas GRO/PGR, maio/2026: https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/inspecao-do-trabalho/manuais-e-publicacoes/2026/perguntas-e-respostas-gro-pgr-maio-2026/@@download/file

- NR-17, avaliação ergonômica preliminar e AET: https://www.gov.br/trabalho-e-emprego/pt-br/acesso-a-informacao/participacao-social/conselhos-e-orgaos-colegiados/comissao-tripartite-partitaria-permanente/normas-regulamentadora/normas-regulamentadoras-vigentes/norma-regulamentadora-no-17-nr-17

- PGR Pasqualino jul/2026 (PASSEG): referência da matriz ocupacional existente, sob responsabilidade do GRO/PGR, não uma funcionalidade obrigatória da Talents.

# Apêndice A Cadastro inicial das 39 perguntas (versão V11)

Transcrição operacional do protótipo V11. O campo “polaridade” identifica a regra estatística demonstrada: Direta para enunciados desfavoráveis; Invertida para enunciados favoráveis. As contagens são apenas a base de teste da Companhia do Livro e não devem virar valores fixos.

| **ID** | **DIMENSÃO**                  | **ENUNCIADO**                                                              | **POLARIDADE** | **CONTAGENS DE TESTE¹** |
|--------|-------------------------------|----------------------------------------------------------------------------|----------------|-------------------------|
| 1      | Demandas e Sobrecarga         | Minha rotina de trabalho exige um ritmo excessivo de produtividade.        | Direta         | 3/0/15/6/16             |
| 2      | Demandas e Sobrecarga         | Frequentemente preciso deixar tarefas incompletas por excesso de demandas. | Direta         | 10/7/16/1/6             |
| 3      | Demandas e Sobrecarga         | Sinto dificuldade em equilibrar qualidade e volume de trabalho.            | Direta         | 11/10/13/1/5            |
| 4      | Demandas e Sobrecarga         | Meu trabalho exige atenção constante por longos períodos.                  | Direta         | 0/0/7/6/27              |
| 5      | Demandas e Sobrecarga         | Recebo demandas urgentes com frequência excessiva.                         | Direta         | 1/6/18/6/9              |
| 6      | Demandas e Sobrecarga         | Sinto desgaste mental ao final do expediente.                              | Direta         | 8/3/18/3/8              |
| 7      | Autonomia e Organização       | Tenho clareza sobre minhas responsabilidades.                              | Invertida      | 0/1/2/6/31              |
| 8      | Autonomia e Organização       | Consigo organizar minhas atividades com autonomia.                         | Invertida      | 0/1/9/9/21              |
| 9      | Autonomia e Organização       | Tenho acesso às informações necessárias para executar meu trabalho.        | Invertida      | 1/0/10/6/23             |
| 10     | Autonomia e Organização       | As prioridades do meu trabalho são comunicadas com clareza.                | Invertida      | 0/1/14/5/20             |
| 11     | Autonomia e Organização       | Consigo participar de decisões relacionadas à minha rotina profissional.   | Invertida      | 6/5/14/5/10             |
| 12     | Liderança e Gestão            | Minha liderança demonstra respeito no dia a dia.                           | Invertida      | 3/0/12/7/18             |
| 13     | Liderança e Gestão            | Recebo apoio da liderança quando enfrento dificuldades.                    | Invertida      | 7/2/9/5/17              |
| 14     | Liderança e Gestão            | Consigo conversar com minha liderança sem receio.                          | Invertida      | 4/0/11/6/19             |
| 15     | Liderança e Gestão            | Recebo feedbacks claros sobre meu desempenho.                              | Invertida      | 15/6/10/2/7             |
| 16     | Liderança e Gestão            | Minha liderança age de forma coerente com o que comunica.                  | Invertida      | 4/3/14/7/12             |
| 17     | Liderança e Gestão            | Sinto que meu trabalho é reconhecido pela liderança.                       | Invertida      | 10/2/9/7/12             |
| 18     | Segurança Psicológica         | Sinto segurança para expressar opiniões no ambiente de trabalho.           | Invertida      | 12/3/6/4/15             |
| 19     | Segurança Psicológica         | Posso relatar problemas sem medo de represálias.                           | Invertida      | 7/5/12/2/14             |
| 20     | Segurança Psicológica         | Erros são tratados de forma construtiva na empresa.                        | Invertida      | 6/7/11/4/12             |
| 21     | Segurança Psicológica         | Sinto que minhas ideias são respeitadas.                                   | Invertida      | 9/3/10/7/11             |
| 22     | Segurança Psicológica         | Existe abertura para diálogo entre equipes.                                | Invertida      | 6/9/13/5/7              |
| 23     | Segurança Psicológica         | O ambiente de trabalho favorece respeito e acolhimento.                    | Invertida      | 2/5/9/11/13             |
| 24     | Relacionamentos e Clima       | Existe colaboração entre as equipes.                                       | Invertida      | 4/6/10/11/9             |
| 25     | Relacionamentos e Clima       | Os conflitos são tratados de forma adequada.                               | Invertida      | 5/5/16/3/11             |
| 26     | Relacionamentos e Clima       | Sinto respeito nas relações profissionais.                                 | Invertida      | 1/2/13/8/16             |
| 27     | Relacionamentos e Clima       | O ambiente de trabalho é emocionalmente saudável.                          | Invertida      | 7/1/13/7/12             |
| 28     | Relacionamentos e Clima       | Sinto que faço parte da empresa.                                           | Invertida      | 3/0/11/4/22             |
| 29     | Relacionamentos e Clima       | Existe equilíbrio na comunicação entre os setores.                         | Invertida      | 3/4/17/9/7              |
| 30     | Esgotamento e Saúde Emocional | Tenho sentido cansaço emocional relacionado ao trabalho.                   | Direta         | 6/5/17/5/7              |
| 31     | Esgotamento e Saúde Emocional | Sinto dificuldade para desconectar do trabalho fora do expediente.         | Direta         | 14/3/12/2/9             |
| 32     | Esgotamento e Saúde Emocional | Tenho percebido aumento do estresse na minha rotina profissional.          | Direta         | 5/9/19/2/5              |
| 33     | Esgotamento e Saúde Emocional | Meu trabalho tem impactado negativamente meu bem-estar emocional.          | Direta         | 14/14/8/1/3             |
| 34     | Esgotamento e Saúde Emocional | Tenho sentido desmotivação frequente no trabalho.                          | Direta         | 15/4/13/3/5             |
| 35     | Percepção Organizacional      | A empresa demonstra preocupação genuína com os colaboradores.              | Invertida      | 4/1/14/5/16             |
| 36     | Percepção Organizacional      | As decisões internas são comunicadas com transparência.                    | Invertida      | 7/7/12/7/7              |
| 37     | Percepção Organizacional      | Sinto confiança na empresa onde trabalho.                                  | Invertida      | 2/1/9/4/24              |
| 38     | Percepção Organizacional      | A cultura da empresa é coerente com o que é praticado.                     | Invertida      | 3/3/14/6/14             |
| 39     | Percepção Organizacional      | Vejo preocupação da empresa com saúde emocional e bem-estar.               | Invertida      | 5/5/14/5/11             |

¹ Contagens na ordem: Nunca / Raramente / Às vezes / Frequentemente / Sempre. A base exibida tem 40 respostas por pergunta. Dados exclusivos da referência visual V11.

**ATENÇÃO** Após importar a versão do instrumento, bloquear edição destrutiva das perguntas de campanhas já iniciadas; criar nova versão para alterações de redação, dimensão ou polaridade.
