# Notas de implementação — NR-1 V11

## Pendências de produção

> ### ⛔ BLOQUEIOS: a branch `feature/nr1-v11` NÃO deve ir para produção antes de (1) e (2)
>
> 1. **Gate mínimo de publicação IMPLEMENTADO na Etapa 6 (T02), mas ainda sem o fluxo de revisão da Etapa 7.** Campanhas **novas** (criadas pela empresa) nascem com `requires_publication = true` e só mostram resultados, impressão, exportações, plano e documentos à empresa depois de "Publicado" (404 antes). Campanhas **anteriores** ao gate ficam com `requires_publication = false` e **continuam visíveis como antes (D9)**: enquanto D9 não for decidida, a empresa continua vendo as campanhas já existentes sem publicação. Quem publica é a Talents, por um botão simples na tela do parecer (`POST …/surveys/{survey}/publicar`); não há "despublicar", versão, PASSEG nem histórico de revisão até a Etapa 7. **Antes de produção:** decidir D9 (marcar as campanhas abertas como `requires_publication = true`, ou não) e conferir se há campanhas novas criadas por outros caminhos que não o `Client\SurveyController::store`.
> 2. **Revisão externa DESLIGADA até o MFA (D14) estar decidido e implementado.** `NR1_EXTERNAL_REVIEW_ENABLED` deve ficar `false` (padrão) e `NR1_EXTERNAL_REVIEW_MFA_REQUIRED` `true`. Não ligar em produção, nem para teste com revisor real, sem MFA. Perguntas de infraestrutura na seção abaixo.
>
> 1a. **D9 DECIDIDA: NÃO fazer backfill agora.** As campanhas existentes ficam com `requires_publication = false` (visíveis como hoje). **Antes do deploy:** contar as campanhas reais em produção (quantas têm resultados, documentos liberados, parecer ou relatório publicado, e quantas estão em coleta) e só então decidir se algum marcador de publicação será aplicado. A abordagem e os riscos de um eventual backfill (comando com `--dry-run`, marca de origem, exclusão das campanhas em coleta) estão no resumo da Etapa 6 e continuam válidos como referência; nada foi implementado.
>
> 1b. **Etapa 7 IMPLEMENTADA (fluxo de documentos); a publicação V11 NÃO depende mais do MFA.** Na campanha V11, "Publicado" nasce só da publicação dos dois documentos (o botão direto de publicar da Etapa 6 não vale para ela). Há dois caminhos, por campanha, na opção **«Exigir revisão PASSEG»** (padrão **desligada**): desligada, a Talents publica diretamente (mesma checagem de campos obrigatórios, data, responsável e protocolo); ligada, o fluxo é Em revisão PASSEG → Aprovado → Publicado, e esse caminho exige a **revisão externa ligada**, que segue desligada até o MFA (D14). Ou seja: **sem MFA só se publica pelo caminho direto**; as campanhas que exigirem a PASSEG ficam paradas em «Em edição» até o MFA existir. Decisão pendente (Talents/Suzane): o padrão de uma campanha nova deve ser ligado ou desligado quando o MFA chegar?
>
> ### ⚠ Em aberto, sem bloquear o merge, mas com dono definido
>
> 3. **D8 e demais perguntas em aberto** (detalhes nas seções abaixo):
>    - **D8:** `surveys.show_others_composition` fica `false` em toda campanha até a Suzane e o jurídico confirmarem. Ainda sem tela para ligar.
>    - **D11:** quem é a PASSEG na prática (hoje só a organização aprovadora genérica).
>    - **D14:** decisão de MFA e infraestrutura (sete perguntas abaixo).
>    - **Card "Plano de ação":** a Etapa 6 deve criar o estado "responsável confirmado" (Caderno §11).
>    - **Liberação ao Comercial:** `commercial_released_at` é provisório até a etapa de Documentos; botão "Liberar ao Comercial", botão "Compartilhar", link no menu e fluxo de criar organização/revisor ainda não têm tela.
>    - **Impressão pelo Admin Talents:** precisa existir? (decidir na Etapa 7).

### Revisão externa de documentos (Etapa 4A) — DESLIGADA

- Configuração: `config/nr1.php` → `external_review.enabled` (env `NR1_EXTERNAL_REVIEW_ENABLED`, padrão `false`).
- Desligada: `/revisao/*` responde 404, compartilhar é recusado (service e rota admin) e nenhum compartilhamento existente vale (a policy nega como 404).
- **Não ligar em produção antes do MFA (D14) estar decidido e implementado.**
- Ponto de extensão do MFA: `external_review.mfa_required` (env `NR1_EXTERNAL_REVIEW_MFA_REQUIRED`, padrão `true`) e `EnsureExternalReviewer::mfaSatisfied()`. Hoje ele retorna `false`, então mesmo com a revisão ligada o revisor recebe 403 até o verificador existir. Para testar sem MFA, desligue `mfa_required` explicitamente (os testes fazem isso).
- Falta (fora do escopo desta etapa): fluxo de criar organização aprovadora e convidar/criar o usuário revisor (hoje só por tinker/seeder), e item de menu/botão "Compartilhar" na tela admin do plano de ação (a rota `admin.companies.surveys.psychosocial-documents.share` existe, sem UI).
- Modelo genérico: `approver_organizations` + `user_workspaces.approver_organization_id`. Quem é a PASSEG na prática (D11) continua em aberto e não está no código.

### Perguntas de infraestrutura para o MFA (D14)

1. Qual mecanismo: TOTP (app autenticador), e-mail/SMS, ou chave de acesso (passkey)? Quem fornece o canal?
2. Vale só para o revisor externo ou também para `super_admin` (o plano cita os dois)?
3. Recuperação: quem redefine o segundo fator de um revisor externo que perdeu o dispositivo, e com qual comprovação?
4. Pacote: aceitamos adicionar dependência (ex.: Fortify/2FA) ou preferimos implementação própria?
5. Sessão: de quanto em quanto tempo reexigir o segundo fator na área de revisão?
6. Onde guardar os segredos (criptografia com `APP_KEY`, como as chaves de IA) e qual a política de rotação?
7. Há exigência de log de tentativas de MFA (LGPD/auditoria) e por quanto tempo reter?

### Comercial limitado (Etapa 4B) — liberação provisória

- Módulo de admin `nr1_oportunidades` (matriz de permissões ainda vale). Exige conceder a permissão na tela de Equipe; sem backfill, como `nr1_dados_brutos`.
- "Campanha publicada" ainda não existe (vem na Etapa 7). Provisório: `surveys.commercial_released_at`, preenchido pela Talents em `POST admin/companies/{company}/surveys/{survey}/liberar-comercial`. A Etapa 7 deve trocar esse critério pelo estado "publicado".
- Sem UI para o botão "Liberar ao Comercial" nem link no menu lateral do admin: só rotas e as telas `Admin/Nr1Opportunities/*`.

## Etapa 5 — Painel: decisões tomadas e pendências

- **D15 — dimensões em atenção (decidido).** "Em atenção" = índice ≥ 2,34 (faixas Atenção **e** Maior sinalização), a regra de `Nr1BandLabels::isAttention`, com o selo da faixa em cada dimensão.
- **D7 — "Outros Setores" abaixo do piso (decidido).** Só a **exibição** é suprimida (o grupo e o seu N não saem; fica a nota "Respostas de grupos menores entram no índice geral…"). As respostas seguem no índice geral. Não unir a outros setores.
- **D8 — composição de "Outros Setores" (decidido, com pendência).** Oculta por padrão. Configuração por campanha `surveys.show_others_composition` (padrão `false`): os nomes só aparecem com a flag ligada **e** pelo menos 3 setores no grupo **e** o grupo no piso de respondentes. **Depende de confirmação da Suzane e do jurídico antes de qualquer campanha ligar a flag.** Ainda não há tela para ligar a flag (hoje só por banco/tinker); entra junto da configuração da campanha. Nunca sai no CSV nem no JSON.
- **Card "Plano de ação" (decidido).** Hoje mostra **medidas implantadas / total**. A Etapa 6 deve criar o estado "responsável confirmado" (Caderno §11); então o card volta ao texto do HTML V11 ("medidas com responsável confirmado").
- **Precisão do índice (decidido).** Exibição e faixa com 2 casas (2,5974 → 2,60). Para campanhas V11 (`talents39_v1`) `survey_results` e `survey_group_results` já guardam `score_numerator` (soma) e `score_denominator` (N) por grupo e dimensão, então o índice é auditável em 4 casas (4052/1560 = 2,5974); coberto por `Nr1V11DashboardResultsTest`. Campanhas `production_v1` não guardam soma e N e **não serão auditadas em 4 casas** (decisão: esquema inalterado). Os valores de 4 casas do Caderno (T14) são comparados nos testes com tolerância de 0,005.
- **Barras das dimensões.** Feitas em HTML/CSS com `role="img"` e texto (cor **e** texto, leitor de tela), em vez de ApexCharts: mais acessível e sem dependência. ApexCharts fica para gráficos que precisem de interação.

### Fatia 3 — impressão e exportação

- **Publicação ainda não existe.** O estado "publicado" e o `SurveyPublicationGate` são da Etapa 7 (D9). Impressão (`/painel-psicossocial/imprimir`), CSV/JSON agregados e CSV das perguntas passam todos por `PsychosocialDashboardController::authorizeSurvey()`, o mesmo ponto do painel: é ali que o gate da Etapa 7 deve entrar para cobrir as quatro saídas de uma vez. Hoje valem as regras do painel: empresa da campanha (outra empresa → 404) e permissão Relatórios/ver (sem ela → 403).
- **Impressão é a página do navegador, não um documento oficial.** O HTML é gerado na hora a partir dos agregados (sem gravar arquivo); para PDF, o usuário usa "Salvar como PDF". Documentos oficiais seguem versionados e congelados (resumo executivo, subsídio AEP/PGR, dossiê).
- **Perfis.** Só empresa (administrador, ou usuário com Relatórios/ver). Admin Talents, comercial limitado e revisor externo não acessam estas rotas (a impressão do lado Talents, se for desejada, é decisão a tomar na Etapa 7). Pergunta: o Admin Talents precisa imprimir o panorama de uma empresa?
- **Auditoria.** Dois novos recursos no log de acesso: `panorama_print` e `export_items_csv` (migration `allow_panorama_resources_in_psychosocial_access_logs`, regra CHECK só no Postgres).

## Etapa 6 — Gate mínimo de publicação e plano de ação

### Gate mínimo de publicação (T02)

- **Estado:** `surveys.requires_publication` (padrão `false`; `true` para as campanhas criadas pela empresa), `published_at`, `published_by`. Regra única em `SurveyPublicationGate::allows()` (`!requires_publication || published_at != null`).
- **Aplicação:** middleware `survey.published` em todas as rotas da empresa com `{survey}` que expõem resultado, plano ou documento (resultados, painel, impressão, CSV/JSON, CSV das perguntas, relatórios, exportações legadas, resumo executivo, documentos, validação, parecer, itens do plano, plano de ação novo). O painel inicial da empresa esconde índice, dimensões, link do plano e itens abertos de campanhas não publicadas. Um teste estrutural falha se surgir rota de empresa com `{survey}` sem o gate e fora da lista de gestão da coleta.
- **Fica de fora (de propósito):** gestão da coleta (links, QR, edição da campanha) e o botão de recalcular continuam disponíveis antes de publicar.
- **Dois "publicar" convivem:** `action_plans.admin_published_at` (parecer legado) e `surveys.published_at` (novo, por campanha). A Etapa 7 deve unificá-los: o fluxo de documentos/PASSEG passa a alimentar `published_at`.
- **Comercial limitado:** continua pelo critério provisório `commercial_released_at` (decisão sua); não exige `published_at` ainda.
- **D9 (decidida: sem backfill agora; contar as campanhas reais antes do deploy):** campanhas abertas hoje seguem visíveis sem publicação. Marcá-las como `requires_publication = true` esconderia os resultados delas até alguém publicar. Decidir antes de produção.

### Plano de ação (Caderno §11)

- **Dados:** `psychosocial_measures` ganhou objetivo, resultado esperado, origem e justificativa do prazo sugerido, indicador, responsável e prazo **confirmados** (com data e autor da aprovação) e resultado da eficácia; tabela `psychosocial_measure_evidences` (só inserção; arquivo com SHA-256 ou texto). Status novo `replanned`.
- **Ciclo (rótulos do plano):** Proposta (`to_approve`) → Confirmada (`pending`) → Em andamento (`in_progress`) → Concluída (`implemented`) / Replanejada (`replanned`). Os valores gravados e os rótulos do acompanhamento antigo («A aprovar», «Pendente», «Implantada») **não mudaram**; o plano usa `planLabel()`. O Admin continua podendo mudar o status livremente na tela antiga de medidas (compatibilidade); as transições explícitas valem para o plano da empresa.
- **Quem pode o quê:** a Talents propõe e edita a sugestão (responsável e prazo sugeridos, ação, objetivo, indicador) **até "Publicado"**, sem nunca marcar confirmação, e justifica quando troca um valor já registrado. Depois de publicado ela não edita mais. A empresa **só age depois de "Publicado"** e com a ação `edit` em Planos de ação: confirma responsável (usuário da empresa ou nome/função/contato) e prazo, ajusta com justificativa, inicia, replaneja (justificativa e prazo futuro), registra evidências, conclui (exige ao menos uma evidência) e registra a eficácia. A Talents não confirma pela empresa.
- **Histórico:** todo ajuste grava antes/depois, autor, data e justificativa em `psychosocial_measure_events` (imutável). As telas (empresa e Talents) mostram o histórico.
- **Card do painel:** voltou a "medidas com responsável confirmado" (`confirmed_at` preenchido) / total, com o total de concluídas ao lado.
- **Ainda não feito (Etapa 7):** plano dentro do relatório técnico consolidado impresso (T18); PASSEG pedir ajuste e devolver à Talents; retorno de versão após publicado. Prioridade da lista não é classificação legal de risco (D1) e a tela repete esse aviso.
- **Perguntas em aberto:** (a) a empresa pode ter vários usuários "responsáveis" por medida? Hoje é um por medida. (b) Evidência exige arquivo ou basta texto? Hoje basta texto ou arquivo. (c) Prazo confirmado pode ser posterior ao sugerido sem justificativa? Hoje sim.

## Mapa de telas V11 (HTML aprovado × Caderno × sistema)

> **Status: mapa APROVADO com 4 ajustes** (registrados logo abaixo e aplicados nas tabelas). Aguardando a confirmação da ordem de construção e as respostas da Suzane antes de iniciar a Etapa 7.

**Ajustes aprovados:**

1. **Visão geral: a faixa de boas-vindas FICA** ("Enxergue o que importa. Cuide do que faz diferença.", com os botões "Acompanhar ações" e "Imprimir panorama"). Ela faz parte do HTML aprovado e entra na tela; não é mais OCULTAR.
2. **Rótulos das faixas: não alterar agora.** Aguarda a resposta da Suzane (pergunta 1). A troca por Leve / Atenção / Elevado continua mapeada na seção 4 como mudança **só de exibição**; nenhum arquivo foi mexido.
3. **Documentos extras (subsídio AEP/PGR e dossiê):** ocultar nas campanhas V11 (`talents39_v1`), manter nas antigas, e planejar o essencial deles como **anexo do relatório técnico**. Pendente da confirmação da Suzane (pergunta 6 desta lista).
4. **Nova ordem de construção:** ver a seção 7.

> Somente leitura: nada de código, menu, rota ou commit foi alterado para este mapa. Fonte do contrato: `Talents_NR1_V11_Aprovacao_Final.html` lido no código (script e markup) **e aberto em navegador** (Edge via Playwright, perfil trocado por `selectOption('#role')` e menu por `.nav[data-page]`; scripts e capturas ficaram fora do repositório). Estado do sistema: commits até `d87bf0c` mais o working tree da Etapa 6.

### 1. Contrato extraído do HTML (confirmado no navegador)

**Menu (rótulos exatos, nesta ordem):** Visão geral · Campanha NR-1 · Minha jornada (só colaborador) · Resultados da pesquisa (abas **Dimensões / Setores / 39 perguntas**) · Plano de ação · Documentos · Soluções Talents · Oportunidades.

| Perfil no HTML | Menu visível | Abre em |
|---|---|---|
| Talents (consultoria) | Visão geral, Campanha NR-1, Resultados, Plano de ação, Documentos, Soluções, Oportunidades (tudo menos Minha jornada) | Visão geral |
| Empresa / Gestor | Visão geral, Campanha NR-1, Resultados, Plano de ação, Documentos, Soluções | Visão geral |
| PASSEG | Visão geral, Resultados, Plano de ação, Documentos | **Documentos** |
| Talents / Comercial | Soluções, Oportunidades | **Oportunidades** |
| Colaborador Time / Líderes | só Minha jornada | Minha jornada |

O seletor de perfil do HTML é demonstração: **não vira tela**. No sistema o perfil vem da autenticação (Talents, empresa, revisor externo) ou do token do link (colaborador por público).

**Documentos:** dois produtos (Relatório executivo; Relatório técnico + plano de ação). Estados: Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado. Talents: Iniciar nova revisão, Enviar à PASSEG, Publicar. PASSEG: Aprovar relatório, Solicitar alteração (texto obrigatório). Histórico visível. Soluções e Oportunidades (de soluções) só abrem depois de "Publicado".

**Campanha NR-1:** Orientação sempre incluída; Treinamento Online e Presencial contratáveis por público (Time, Líderes). Não contratado: empresa vê "Solicitar proposta", Talents vê "Marcar contratado", colaborador só vê o que está contratado e liberado ao seu público. Talents também edita "Elegíveis cadastrados".

### 2. Princípios de encaixe

- **Duas cascas hoje, uma no HTML.** O HTML é um app único que esconde telas por perfil; o sistema tem o painel `/admin` (Talents) e o `/client` (empresa). Proposta: **manter as duas cascas e reaproveitar os mesmos componentes Vue**; Talents ganha rotas em `/admin/companies/{empresa}/surveys/{campanha}/…` que renderizam a mesma tela com os blocos exclusivos (Revisão interna, Marcar contratado, Elegíveis, Enviar à PASSEG, Publicar).
- **Menu da empresa:** novo grupo "NR-1" na barra lateral com Visão geral · Campanha NR-1 · Resultados da pesquisa · Plano de ação · Documentos · Soluções Talents (só aparece se o módulo estiver no plano da empresa; cada item respeita a permissão do módulo). "Minha jornada" **não é item de menu**: é a página do link do colaborador.
- **Revisor externo (PASSEG):** ambiente `/revisao/*` (Etapa 4A, desligado até o MFA).
- **Comercial limitado:** módulo `nr1_oportunidades` (Etapa 4B).

### 3. Mapa tela a tela

Decisões: **CRIAR** (não existe), **ESTENDER** (existe, falta parte), **SUBSTITUIR**, **MANTER**, **OCULTAR**.

| Tela (HTML) | Como está no HTML | Como está no sistema | Decisão | Dependências |
|---|---|---|---|---|
| **Visão geral** | Faixa de boas-vindas ("Enxergue o que importa…"), 4 cards (índice, participação, dimensões em atenção, plano), pontos fortes, sete barras, "O que merece atenção", **Próximas ações**, **Participação por grupo** | Aba "Visão geral" do painel (`Client/Surveys/PsychosocialDashboard`): cabeçalho com status real, 4 cards, pontos fortes, barras, lista de atenção. Participação por grupo fica na aba Resultados. Sem "Próximas ações" | **ESTENDER**: acrescentar "Próximas ações" (3 primeiras medidas do plano, com chip "A confirmar" / "Responsável confirmado") e um resumo de "Participação por grupo". **CRIAR a faixa de boas-vindas** do HTML aprovado ("Enxergue o que importa. Cuide do que faz diferença.", com "Acompanhar ações" → Plano de ação e "Imprimir panorama", que **MANTÉM** a impressão atual); o cabeçalho factual (empresa, período, status) fica logo abaixo | Etapa 6 (plano) ✓ |
| **Campanha NR-1** | 3 cartões (Orientação, Treinamento Online, Treinamento Presencial) com Time/Líderes; "Serviços contratados" (Talents marca públicos); questionário (39/7); link; participação; elegíveis editáveis | `Client/Surveys/Show`: links por público, QR, janela de abertura, estatística de coleta, política de envio duplicado. Elegíveis no cadastro técnico da campanha (admin). **Não existe** contratação de treinamento, orientação gerenciável nem "Solicitar proposta" | **ESTENDER** (links, QR, participação, elegíveis: **MANTER**) + **CRIAR**: contratação por modalidade × público (estados Não contratado → Proposta solicitada → Contratado), conteúdo de Orientação e materiais por público, botão "Solicitar proposta" (empresa) e "Marcar contratado" (Talents) | "Solicitar proposta" alimenta Oportunidades; independe da Etapa 7 |
| **Minha jornada** | Orientação, materiais do público, "Responder questionário" (uma pergunta por tela, progresso, agradecimento) | `Survey/Take.vue` por token/público (Etapa 3): tem bloco "Orientação NR-1" e "Como responder"; sem materiais por público | **ESTENDER**: tela inicial do link com orientação e **só** o material contratado e liberado ao público; o questionário segue como está (anônimo, escala de frequência). Não vira menu | Campanha NR-1 (contratos) |
| **Resultados da pesquisa** | Abas Dimensões (barras + "Interpretação dos indicadores" + **Revisão interna da Talents**), Setores (3 cartões de grupo), 39 perguntas; "Imprimir resultados" | Aba "Resultados da pesquisa": participação por grupo, setores com dimensões e 39 cartões **empilhados**; sem Revisão interna; impressão só do panorama | **ESTENDER**: sub-abas Dimensões / Setores / 39 perguntas; legenda de faixas na aba Dimensões; "Imprimir resultados" por aba. **CRIAR** "Revisão interna da Talents" (só Talents, registra encaminhamento + anotações; **não** gera contratação nem aparece à empresa) | Nenhuma |
| **Plano de ação** | 3 contadores; cartão por fator: responsável (lista de áreas + Outro), prazo em **dias** a partir da aprovação, "Andamento" Planejada/Em andamento/Concluída, chips "Sugestão Talents"/"Confirmada pela empresa", evidência e aferição em texto; "Confirmar ação"; "Ver impressão do plano" | Etapa 6: Proposta → Confirmada → Em andamento → Concluída/Replanejada; responsável (usuário ou nome/função/contato) e prazo em **data**; evidências (arquivo/texto), eficácia, histórico antes/depois; sem contadores; sem impressão do plano | **MANTER** o modelo (Caderno prevalece) e **ESTENDER** a tela: 3 contadores (Ações propostas / Responsáveis confirmados / Aguardando confirmação), chips do HTML mapeados aos status, lista de áreas padrão como sugestão. "Ver impressão do plano" = relatório técnico (T18) | Etapa 6 ✓; impressão depende da Etapa 7 |
| **Documentos** | Dois documentos com "Imprimir"; painel de revisão com estados, versão, botões por perfil, parecer da PASSEG e histórico | Três documentos congelados (resumo executivo, subsídio AEP/PGR, dossiê) liberados pela Talents e **validados pela empresa**; sem estados de revisão, sem PASSEG operando, sem histórico de revisão | **SUBSTITUIR** o fluxo (Etapa 7): `review_status` (Em edição → … → Publicado) + `psychosocial_document_reviews`; **ESTENDER** os snapshots: relatório técnico incorpora o plano (T18), executivo separado; PDF imutável por versão (**MANTER**); **subsídio AEP/PGR e dossiê: OCULTAR nas campanhas V11, MANTER nas antigas**, e o essencial deles vira **anexo do relatório técnico** (ajuste aprovado, pendente da Suzane, pergunta 6) | Etapa 7 (alimenta `surveys.published_at`); PASSEG exige MFA (D14) |
| **Soluções Talents** | 6 ofertas ligadas às dimensões em atenção, com "Solicitar conversa" (empresa) / "Consultar oportunidade"; bloqueada até "Publicado" | **Não existe** | **CRIAR**: regras dimensão → serviço editáveis pela Talents (`nr1_service_matches`), textos aprovados, "Solicitar conversa" cria oportunidade "A qualificar"; só abre com campanha publicada (o gate já existe) | Publicado (Etapa 7) e textos aprovados (pergunta 11) |
| **Oportunidades** | Contadores (identificadas / em contato / a qualificar) e fila com origem, etapa e "Assumir oportunidade"; linhas de treinamento aparecem **antes** da publicação; as de solução, depois | Módulo `nr1_oportunidades` (4B): lista de campanhas liberadas e resultado agregado, sem fila e sem etapas | **MANTER** o serializer agregado (nunca resposta bruta) e **ESTENDER**: fila de oportunidades (serviço, cliente, campanha, origem, usuário comercial, status, contato, próxima ação), duas origens (solicitação de treinamento; solução sugerida) | Campanha NR-1 (origem treinamento); Publicado (origem solução); trocar `commercial_released_at` por Publicado |

### 4. Discrepâncias e solução proposta

1. **Rótulos de faixa.** O próprio HTML usa **três vocabulários**: a legenda diz Leve / Atenção / **Elevado** (1,00–2,33 / 2,34–3,66 / 3,67–5,00); os rótulos das barras saem em minúsculas ("leve", "atenção"); e o relatório técnico impresso diz "menor sinalização / atenção / maior sinalização" (igual ao Caderno).
   - **Recomendação:** usar **Leve / Atenção / Elevado** em todas as telas e documentos novos, mantendo o aviso "Faixa do questionário. Não é classificação de risco ocupacional do PGR" e a frase explicativa sob cada faixa ("menor sinalização de fatores desfavoráveis…"). Os valores gravados (`green|yellow|red`) não mudam.
   - **O que já existe:** a Etapa 1 criou `Nr1BandLabels::V11` = Menor sinalização / Atenção / Maior sinalização; a Etapa 5 usa esses rótulos no painel, na impressão e na lista de atenção. As campanhas antigas (`production_v1`) seguem em Leve / Moderado / Grave (inclusive `HealthBadge.vue` e `Nr1SurveyResultsPanel.vue`).
   - **O que mudaria (só exibição):** (a) as 3 strings de `Nr1BandLabels::V11`; (b) a legenda fixa de `PsychosocialDashboardBuilder::LEGENDS` para `talents39_v1`; (c) 2 trechos de texto do prompt de IA em `Nr1AiAnalyzer`; (d) as asserções de `Nr1V11InstrumentTest` (≈5 linhas). Documentos já emitidos guardam o texto congelado e **não mudam**; só as próximas versões saem com o novo rótulo. Esforço: **P**.
   - **Atenção:** "Leve" e "Elevado" soam como classificação de risco, que o Caderno evita de propósito. Pergunta 1.
   - **Decisão aprovada: NÃO alterar agora.** Os rótulos do sistema ficam como estão até a resposta da Suzane; esta lista é só o mapa da mudança de exibição.
2. **Escala do colaborador.** O HTML mostra opções de concordância (Discordo… Concordo), contradizendo o próprio subtítulo ("escala de frequência, Nunca a Sempre") e o Caderno §7/T08. **Solução:** valer Nunca / Raramente / Às vezes / Frequentemente / Sempre. **O sistema já faz isso:** o instrumento V11 grava `response_scale = frequency` e `Take.vue` mostra "Escala de frequência (1 a 5)". Os rótulos de concordância continuam só para templates antigos. Nada a mudar; a tela "Minha jornada" deve copiar a escala do sistema, não a do HTML.
3. **"Outros Setores".** O HTML lista o nome dos setores. **Mantido (D8):** composição oculta por padrão; a flag por campanha `show_others_composition` (desligada) só libera nomes com 3+ setores no grupo e o grupo no piso. **Depende de confirmação da Suzane e do jurídico** (pergunta 2).
4. **Status do plano.** O HTML usa Planejada / Em andamento / Concluída. **Usar o Caderno** e mapear: *Planejada* do HTML = **Proposta** (sem confirmação) **ou Confirmada** (empresa confirmou, ainda não iniciou); *Em andamento* = Em andamento; *Concluída* = Concluída; **Replanejada** não existe no HTML e passa a existir. Chips do HTML: "Sugestão Talents" = Proposta; "Confirmada pela empresa" = Confirmada em diante.
5. **Outras divergências encontradas:**
   - *Faixa de status no topo.* O HTML mostra um único chip ("Publicado") que é o estado dos **documentos**; o sistema mostra o status real da **coleta**. Proposta: dois chips (Coleta: Em coleta/Encerrada; Documentos: Em edição … Publicado).
   - *Prazo.* HTML em dias a partir da aprovação; Caderno e sistema em data. Pergunta 3.
   - *Responsável.* HTML: lista fixa de áreas + "Outro"; Caderno: usuário ou nome/função/contato. Pergunta 4.
   - *Documentos.* HTML tem 2; o sistema emitiu 3 (executivo, subsídio AEP/PGR, dossiê) e exige validação pela empresa (Etapa 9), etapa que o HTML não mostra. **Proposta:** trocar essa validação pela **ciência da empresa** (Caderno §13), registro de recebimento da versão publicada. Perguntas 6 e 7.
   - *PASSEG.* No HTML vê Visão geral, Resultados, Plano e Documentos; o ambiente construído (4A) é mais restrito (só a versão compartilhada com ela). Pergunta 8.
   - *Link da pesquisa.* HTML: um link por empresa com nome; sistema: um link por público (Time/Líderes) com QR e janela de abertura (superset). **MANTER.**
   - *Elegíveis.* HTML aceita editar na tela da campanha (mínimo = respondentes); sistema tem no cadastro técnico e alerta se menor que os respondentes. Expor na tela da Campanha só para a Talents.
   - *Impressão.* HTML gera o relatório no navegador com texto fixo; o sistema imprime o panorama e emite PDFs congelados por versão. **O sistema prevalece**; os textos do HTML servem de modelo de conteúdo.
   - *Pontos fortes.* HTML mostra 3 fixos (Q7, Q8, Q37); sistema mostra as 5 com maior % favorável, com N. **MANTER** o cálculo (sem valores fixos).
   - *"Ver impressão do plano" e "Imprimir resultados".* Não existem no sistema (ver Documentos).
   - *Menu.* O sistema da empresa não tem o grupo NR-1 com esses rótulos; o admin organiza por empresa dentro de Voz do Time > Pesquisas.

### 5. Diferenças visuais (HTML × sistema)

Esforço: **P** (pequeno, 1 componente), **M** (médio), **G** (grande, backend + telas).

| Tela | Elemento | HTML | Sistema hoje | Esforço |
|---|---|---|---|---|
| Visão geral | Faixa de boas-vindas | Faixa roxa com título e dois botões | Cabeçalho factual com empresa, período e status | P (CRIAR a faixa; **fica**, ajuste aprovado) |
| Visão geral | Cards | 4 cards coloridos (roxo, verde, amarelo, rosa) | 4 cards brancos | P |
| Visão geral | Pontos fortes | 3 blocos fixos (Q7, Q8, Q37) | Lista dos 5 maiores % favoráveis, com N | P (MANTER) |
| Visão geral | Próximas ações | Lista com chip "A confirmar" / "Responsável confirmado" | Não existe | M |
| Visão geral | Participação por grupo | Cartão com "Outros Setores" | Só na aba Resultados | P |
| Visão geral | Faixa na barra | "leve / atenção" em minúsculas | Selo com o rótulo da faixa | P |
| Campanha NR-1 | Cartões de treinamento | Online/Presencial com Time/Líderes e estados | Não existe | G |
| Campanha NR-1 | Orientação | Cartão "Disponível para todos" com vídeo | Texto fixo no questionário | M |
| Campanha NR-1 | Contratação | Caixas "Time/Líderes" marcadas pela Talents | Não existe | G |
| Campanha NR-1 | Participação e elegíveis | Barra, % e campo "Atualizar" (Talents) | Cards no painel; cadastro técnico no admin | P |
| Campanha NR-1 | Link | Um campo "Copiar link" | Links por público + QR + janela | P (MANTER) |
| Minha jornada | Materiais | Cartões do público (orientação, treinamento) | Só "Orientação NR-1" e "Como responder" | M |
| Minha jornada | Questionário | Uma pergunta por tela, barra "Pergunta 1 de 39" | A conferir em `Take.vue` (Etapa 3) | P/M |
| Resultados | Abas | Dimensões / Setores / 39 perguntas | Seções empilhadas | M |
| Resultados | Legenda | "Interpretação dos indicadores" com 3 faixas | Frase única sob as barras | P |
| Resultados | Setores | 3 cartões (nome, respostas, % e subtítulo) | Lista com índices por dimensão | M |
| Resultados | Revisão interna | Painel "Somente consultoria" | Não existe | M |
| Plano de ação | Contadores | Propostas / Confirmados / Aguardando | Só o card no painel | P |
| Plano de ação | Cartão da medida | Responsável (lista), prazo em dias, andamento, evidência em texto | Usuário/texto, data, 5 status, evidência (arquivo/texto), eficácia, histórico | M |
| Plano de ação | Impressão | "Ver impressão do plano" | Não existe | M (Etapa 7) |
| Documentos | Lista | 2 documentos com "Imprimir" | 3 PDFs congelados com download | M |
| Documentos | Painel de revisão | Estado, versão, botões, parecer, histórico | Não existe | G (Etapa 7) |
| Soluções | Ofertas | 6 cartões por dimensão com "Solicitar conversa" | Não existe | G |
| Oportunidades | Fila | Contadores e tabela com "Assumir oportunidade" | Lista de campanhas liberadas | G |

### 6. Perguntas para a Suzane (linguagem simples, com recomendação)

1. **Nome das faixas.** O protótipo usa *Leve / Atenção / Elevado*; o Caderno usa *Menor sinalização / Atenção / Maior sinalização*. Qual vale? **Recomendo** *Leve / Atenção / Elevado* com a frase "faixa do questionário, não é classificação de risco do PGR". Preocupação: "Leve" e "Elevado" podem parecer classificação de risco. Confirma?
2. **Setores pequenos reunidos ("Outros Setores").** Podemos mostrar o nome dos setores reunidos? **Recomendo não mostrar** (protege quem respondeu). Só se você e o jurídico aprovarem, e apenas quando o grupo reunir 3 setores ou mais e tiver pelo menos 5 respostas.
3. **Prazo do plano.** A Talents sugere em **dias** ("30 dias depois da aprovação") e a empresa confirma com uma **data**, ou tudo em data? **Recomendo** sugerir em dias e confirmar em data.
4. **Responsável.** A Talents sugere uma **área** (RH, Operações, Diretoria…) e a empresa informa a **pessoa** (usuário do sistema ou nome, função e contato)? **Recomendo** que sim. A lista de áreas do protótipo serve de ponto de partida?
5. **Concluir uma medida.** Exigimos pelo menos uma **evidência** (arquivo ou descrição)? **Recomendo** exigir, para o plano não parecer cumprido só por ter sido impresso. Já está assim.
6. **Documentos.** O protótipo tem 2 (executivo; técnico + plano). Hoje existem 3 (executivo, subsídio AEP/PGR e dossiê). **Recomendo** juntar o essencial deles como anexo do relatório técnico, deixando 2 documentos para a empresa. **Já aprovado no mapa:** nas campanhas V11 o subsídio e o dossiê ficam ocultos (as campanhas antigas mantêm os 3). Falta a sua confirmação do que entra no anexo. Pode?
7. **Ciência da empresa (Caderno §13), no lugar da "validação pela empresa". DECIDIDO:** um registro **por publicação** (os dois documentos saem juntos), com **usuário, data e protocolo de encaminhamento**. Não é selo técnico sobre o conteúdo: é o registro de que a empresa recebeu a versão publicada. A aprovação do conteúdo é da PASSEG ("Aprovado pela PASSEG" = revisão documental) e a publicação é da Talents. Vale nas campanhas V11; as antigas e as versões já validadas ficam como estão.
8. **PASSEG.** Ela vê só o documento que a Talents enviar, ou também o painel e o plano da empresa? **Recomendo** só o documento enviado (mais seguro). Quem são as pessoas da PASSEG e como entram? A revisão externa fica desligada até o segundo fator de segurança (MFA) estar decidido.
9. **Campanhas que já estão abertas.** Continuam visíveis para a empresa sem "Publicado" (como hoje) ou passam a exigir publicação? **Recomendo** manter visíveis e exigir publicação só nas novas, até alguém conferir as antigas.
10. **Treinamentos.** "Marcar contratado" é feito pela Talents à mão (depois do contrato) ou deve vir do contrato assinado? E o presencial: quem marca a data? **Recomendo** começar manual, pela Talents.
11. **Soluções Talents.** Os 6 textos do protótipo estão aprovados? Qual serviço entra para cada dimensão em atenção, e o que fazer com dimensão **Elevada** (3,67 ou mais)? **Recomendo** aprovar os textos e a regra antes de construir; a tela só mostra sugestões depois de "Publicado".
12. **Orientação NR-1.** É vídeo ou texto? Quem fornece o material e como a Talents o troca? **Recomendo** texto agora e vídeo quando houver arquivo aprovado.
13. **Revisão interna da Talents.** Quais itens de conferência aparecem (o protótipo tem "Demandas de trabalho" e "Medidas preventivas já existentes")? Quem registra? **Recomendo** um item por dimensão em atenção, visível só à consultoria.
14. **Elegíveis.** Quem informa o número de colaboradores elegíveis (Talents ou empresa) e de onde ele vem? **Recomendo** a Talents, com a fonte anotada, porque a participação depende dele.

### 7. Ordem de construção (aprovada, aguardando a confirmação final)

Dependências: **Etapa 6** entregou o plano e o gate mínimo ("Publicado" por campanha); **Etapa 7** entrega a máquina de estados dos documentos (que passa a alimentar "Publicado"), a PASSEG e o plano dentro do relatório técnico.

1. **Fechar o Plano de ação (Etapa 6).** Commit da Etapa 6 (aguardando autorização), contadores, chips, lista de áreas e "Próximas ações" na Visão geral (com a faixa de boas-vindas). Perguntas 3 e 4.
2. **Documentos + `SurveyPublicationGate` completo (núcleo da Etapa 7).** Estados Em edição → … → Publicado, versões, parecer, histórico, "Publicar" alimentando `published_at`, relatório técnico com o plano e os anexos essenciais (T18). **Fecha o bloqueio T02** (a empresa só vê depois de Publicado) e **alimenta Soluções e Oportunidades.** A PASSEG operando depende do MFA (D14); sem isso o fluxo roda com a revisão externa desligada.
3. **Resultados em sub-abas + Revisão interna da Talents.** Pergunta 13.
4. **Campanha NR-1.** Contratação por público, orientação, "Solicitar proposta" e "Marcar contratado". Perguntas 10 e 12.
5. **Minha jornada.** Depende do item 4.
6. **Oportunidades (fila).** Origem treinamento (item 4) e origem solução (item 2 e regra da pergunta 11). Troca o critério provisório `commercial_released_at` por "Publicado".
7. **Soluções Talents.** Depende de "Publicado" (item 2), da regra e dos textos aprovados (pergunta 11) e do item 6.

**Bloqueios de produção (continuam valendo):** o fluxo de revisão da Etapa 7 não existe; a decisão D9 sobre campanhas abertas; o MFA para a revisão externa.

## Etapa 7 — Documentos: fluxo de revisão e publicação (Caderno §12 e §13)

**O que existe agora (campanha V11, método `talents39_v1`):**

- **Dois documentos finais, publicados juntos:** relatório executivo e relatório técnico + plano de ação. O técnico **incorpora o plano de ação** (sugerido × confirmado, status, evidências, eficácia: T18) e leva **anexos dentro do próprio PDF**: instrumento (39 afirmações) e dados agregados de suporte (contagens por pergunta). Não há terceiro relatório.
- **Rodada de revisão** (`psychosocial_review_rounds`): Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado. As **versões** dos documentos continuam imutáveis; o estado vive na rodada. Publicada, a rodada não muda mais (T20): mudar = **Iniciar nova revisão** = nova rodada com novas versões, preservando as anteriores. Só uma rodada aberta por vez. A empresa continua vendo a última rodada **publicada** enquanto a nova está em andamento.
- **Quem faz o quê:** Talents: Iniciar nova revisão, Gerar documentos, Enviar à PASSEG (escolhe o revisor e o prazo de acesso, padrão 30 dias), Publicar. Revisor externo: Aprovar relatório e Solicitar alteração (texto obrigatório, mínimo de 8 caracteres), pela tela `/revisao` que já existia, agora ligada à rodada. Empresa: baixar, registrar a ciência e confirmar a integração ao PGR.
- **Histórico** (`psychosocial_review_events`, só inserção): ação, estados de/para, autor, data e justificativa. A Talents vê tudo; a empresa vê só publicação, ciência e integração ao PGR (parecer interno da PASSEG fica com a Talents).
- **Publicar** define o "Publicado" que o `SurveyPublicationGate` usa (`surveys.published_at`/`published_by`) e emite o **protocolo de encaminhamento** (`NR1-{campanha}-R{rodada}-{data}-{código}`). O botão direto de publicar da Etapa 6 **só vale para campanhas fora do fluxo V11**.
- **PDFs finais:** gerados do **snapshot congelado com hash** (snapshot e PDF), nunca ao vivo; o PDF traz a identificação da versão no rodapé. A empresa só baixa a versão **publicada**, nunca um rascunho.
- **Ciência da empresa:** um registro por publicação (os dois documentos juntos), com usuário, data e protocolo (`psychosocial_review_acknowledgements`, só inserção). Exige a permissão `validate` em Relatórios (a mesma da antiga validação). Na V11 a "validação pela empresa" foi **desligada**; nas campanhas antigas continua como estava.
- **"Integração ao PGR confirmada":** campo **separado** de "Aprovado pela PASSEG" (que é só revisão documental). Só existe com o arquivo de evidência de SST (hash gravado), uma por publicação, registrado pela empresa (`psychosocial_pgr_integrations`).
- **Subsídio AEP/PGR e dossiê:** **ocultos** nas campanhas V11 (telas, liberação e download) e **mantidos** nas antigas. Nada foi apagado. O resumo executivo antigo (liberação direta) também fica fechado na V11, porque ele agora é um dos dois documentos do fluxo. O envio avulso ao revisor (Etapa 4A) fica fechado na V11: o envio é pela rodada.
- **Telas:** `Admin/Documents/Central` (Central de documentos, painel de revisão, pendências, histórico), `Client/Surveys/Documents` (documentos publicados, ciência, integração ao PGR, histórico), `ExternalReview/Show` (estado da revisão, os dois documentos, Aprovar relatório, Solicitar alteração) e o link "Documentos" no painel da empresa.

**Revisão PASSEG opcional por campanha («Exigir revisão PASSEG»).** Coluna `surveys.requires_passeg_review` (padrão **desligada**, inclusive nas campanhas existentes; o interruptor está no topo da Central de documentos, só para a Talents).

- **Desligada:** Em edição → **Publicado**, direto pela Talents. Valem as mesmas regras do fluxo longo: documentos gerados de snapshot congelado com hash, checklist de campos obrigatórios sobre o snapshot (T19), data, responsável e **protocolo** gravados, "Publicado" do gate definido, rodada imutável depois (T20), empresa sem acesso antes (T02), ciência e integração ao PGR iguais. A rodada grava `published_without_review = true` e o histórico diz «Publicação direta pela Talents, sem revisão PASSEG (não exigida nesta campanha)». Enviar à PASSEG fica recusado nessa campanha.
- **Ligada:** o fluxo de antes, sem mudança. A publicação direta é recusada.
- **Trava:** a opção só muda sem revisão em andamento com a PASSEG (enviada, alteração solicitada ou aprovada); vale para as próximas publicações. Uma rodada em «Alteração solicitada» não pode ser publicada pelo caminho direto (evita contornar um pedido do revisor); ela volta ao revisor.
- **Auditoria:** cada mudança da opção grava ator, data, valor anterior e novo em `psychosocial_passeg_option_changes` (só inserção; mudar para o mesmo valor não grava; recusas não deixam rastro). A Central mostra o histórico, e cada rodada ainda guarda `published_without_review`.

**T19: bloqueio por campos vazios ou fictícios.** A checagem olha o **snapshot congelado** (o que o PDF imprime), no envio e de novo na publicação. Corrigir o cadastro não basta: é preciso gerar uma nova versão. Pendências indicadas com o nome do campo e onde corrigir (cadastro técnico da campanha):

- razão social, estabelecimento, fonte do número de elegíveis, responsável técnico (nome, função e registro profissional);
- CNPJ preenchido e **válido** (dígitos verificadores);
- total de elegíveis informado e **não menor** que o de respostas;
- resultados já calculados; e, no relatório técnico, ao menos uma medida no plano;
- PDF gerado.

Valor fictício = marcador de lugar ("Não informado", "A confirmar", "Exemplo", "XXX"…) ou texto com "fictício", "demonstrativo", "lorem". **Pergunta (Suzane/Talents técnica):** essa lista de campos obrigatórios está certa? O registro profissional do responsável técnico deve ser obrigatório?

**Correção de integridade encontrada (importante):** um decimal inteiro (ex.: `bar_percent` = 39.0) era gravado no banco como `39`, e o hash do snapshot calculado na emissão não se reproduzia ao reler. O `CanonicalJson` agora trata decimal sem parte fracionária como inteiro. Documentos emitidos antes não mudam (não tinham esse tipo de valor); documentos novos reproduzem o hash. Há teste permanente.

**Perguntas para a Suzane (Etapa 7):**

- (a) O **registro profissional** (CRP ou equivalente) deve ser obrigatório para o responsável técnico no cadastro técnico? **Recomendação: sim** (hoje o sistema já bloqueia a publicação sem ele).
- (b) Quando o MFA existir, o padrão de «Exigir revisão PASSEG» em campanha nova deve ser **ligado**? **Recomendação: sim; até lá, desligado** (padrão atual).

**Alterações em testes antigos:** `PsychosocialDocumentsPhaseFourTest::test_document_header_exists_without_any_version` usava `technical_report` como exemplo de tipo inválido; agora ele é válido, e o exemplo virou `relatorio_inexistente` (mesma intenção). `Nr1V11PublicationGateTest` ganhou o caso "V11 não publica pelo botão direto".

**Ainda não feito / decisões a tomar:**

- **Diff básico entre versões** (Caderno §13: "nova versão preserva pareceres anteriores e diffs básicos"): os pareceres e as versões anteriores ficam preservados e visíveis no histórico, mas **não há comparação campo a campo** entre versões. Pergunta: é necessário no lançamento?
- **Botões "Imprimir" do HTML:** o sistema entrega o PDF congelado para baixar/imprimir; não há impressão a partir da tela.
- **Quem é a PASSEG e MFA (D11, D14):** continuam em aberto; a Talents escolhe o revisor entre os usuários com perfil de revisor externo (ainda sem tela para criá-los).
- **Aviso de publicação por e-mail/aviso interno** à empresa: não implementado.
- **Documentos antigos (V10):** não foram migrados para rodadas; seguem com a validação pela empresa.

## Telas, etapa 1: Visão geral, Resultados e Revisão interna da Talents

**Feito.** Visão geral (empresa e Talents): faixa de boas-vindas do HTML com «Acompanhar ações» e «Imprimir panorama», cards, pontos fortes, sete dimensões, lista completa em atenção, **Próximas ações** (as 3 primeiras medidas abertas do plano, chip «A confirmar» ou «Responsável confirmado») e **Participação por grupo**. Resultados em sub-abas **Dimensões / Setores / 39 perguntas**, com filtros de campanha, período, estabelecimento e dimensão e a legenda «Interpretação dos indicadores». Rótulos das faixas **mantidos** (Menor sinalização / Atenção / Maior sinalização) até a Suzane responder.

- **Filtros.** Campanha, período e estabelecimento trabalham sobre as campanhas V11 da mesma empresa: período e estabelecimento estreitam a lista, e escolher outra campanha abre o painel dela. A empresa só lista as **publicadas**; a Talents lista todas. A dimensão filtra barras, setores e perguntas na tela. O estabelecimento vem do cadastro técnico (um por campanha).
- **Revisão interna da Talents (Caderno §9).** Página da consultoria `admin/companies/{empresa}/surveys/{campanha}/resultados-psicossociais` (o mesmo painel, antes e depois de «Publicado») com o painel «Somente consultoria»: itens de verificação (os dois do HTML mais **um por dimensão em atenção**; pergunta 13 da Suzane), encaminhamento (Solicitar informação ou documento / Sugerir avaliação complementar / Informações suficientes para esta etapa), anotações obrigatórias nos dois primeiros, «Salvar análise interna» e histórico (`psychosocial_internal_reviews`, só inserção). Não cria medida, contratação nem oportunidade. Não vai para a empresa, a PASSEG nem o Comercial: a rota é do módulo `companies` do painel Talents (empresa, revisor e comercial limitado recebem 403) e os dados não entram em nenhuma prop do lado da empresa.
- **Acesso.** O link «Resultados e revisão interna» está na Central de documentos.

**Diferenças visuais restantes (HTML × sistema):** (1) os 4 cards da Visão geral são brancos, o HTML usa gradientes roxo, verde, amarelo e rosa; (2) o menu lateral é o do sistema (claro, com ícones), o HTML tem menu escuro com os nomes das seções; (3) o HTML mostra o chip «Publicado» no topo, o sistema mostra o status real da coleta («Em coleta»); (4) «Participação» mostra «—» quando o total de elegíveis não foi informado, em vez do 83,3% de exemplo; (5) o HTML não tem os botões de exportação do sistema.

## Telas, etapa 2: Campanha NR-1 (Caderno §6)

**Feito.** A tela da campanha V11 (empresa: `client/surveys/{id}`; Talents: `admin/companies/{c}/surveys/{s}/campanha-nr1`) ganhou: cartão **Orientação** (sempre incluída, «Disponível para todos»; texto por enquanto, vídeo quando houver arquivo aprovado, pergunta 12), cartão de **Participação** e os cartões **Treinamento Online** e **Presencial**, uma linha por público (Time, Líderes).

- **Talents:** «Marcar contratado» por modalidade e público (escopo e preço, início e fim, link e/ou arquivo, responsável; presencial: local, data e hora, facilitador, capacidade, presença), «Liberar ao público do colaborador», «Remover contratação» e o cadastro de **elegíveis** (total com fonte; não pode ser menor que as respostas). Só a Talents (empresa e super admin comum recebem 403).
- **Empresa:** contratado em destaque com os dados; não contratado discreto com **«Solicitar proposta»**. O botão só aparece com permissão de edição em Pesquisas.
- **T07.** O pedido cria uma oportunidade (`nr1_opportunities`: origem `training_request`, empresa, campanha, modalidade, público, solicitante, data, estado «Novo») **só no POST**; ver a tela não cria nada. Pedido aberto duplicado para a mesma campanha, modalidade e público é recusado, e o índice único de `open_key` segura até requisições simultâneas; ao fechar (Ganho/Perdido) a chave é limpa e um novo pedido é permitido. Marcar o treinamento como contratado fecha o pedido aberto como **Ganho**. As tabelas da fila (estados, atribuição, próxima ação, histórico) já existem para a etapa de Oportunidades.
- **Link e QR** só aparecem para a empresa com a campanha **e** o link abertos; a rota do QR também recusa (404). Vale para campanhas V11.
- **Material do treinamento:** guardado no disco privado (`nr1/trainings/...`, nome do arquivo original no registro); a entrega ao colaborador é da etapa «Minha jornada».
- **Pendências:** «Marcar contratado» é manual, sem vínculo com contrato assinado (pergunta 10); preço sem máscara (campo em centavos); não há envio de aviso à Talents quando a empresa pede proposta (a fila é a etapa de Oportunidades).

**Diferenças visuais restantes (HTML × sistema):** (1) o HTML mostra «Time» e «Líderes» como caixas marcadas dentro de «Serviços contratados»; o sistema usa uma linha por público dentro de cada cartão; (2) o HTML tem um único campo «Copiar link»; o sistema mantém links e QR por público (superset, mantido); (3) vídeo da orientação não existe (texto); (4) o sistema mantém o bloco antigo de status, período e estatísticas abaixo dos cartões.

## Telas, etapa 3: Minha jornada (Caderno §7)

**Feito.** A página do link do colaborador (`/pesquisa/{token}`, campanha V11) é a «Minha jornada»: começa pela **Orientação** (sempre visível), mostra **só** o treinamento contratado **e** liberado ao público do link (T05, T06) e segue para as **39 afirmações, uma por etapa**, com a escala de frequência **Nunca / Raramente / Às vezes / Frequentemente / Sempre** (não a de concordância do HTML), «Anterior» e «Próxima», barra de progresso, etapa de revisão com «Rever» por dimensão e confirmação de envio. Campanhas antigas continuam com uma dimensão por etapa e sem «Minha jornada».

- **O que o colaborador nunca recebe:** resultados, preço, quem contratou, ofertas, «Solicitar proposta» nem dado de outros colaboradores. A lista de campos da jornada é fechada e há teste (T24 de privacidade). Link por campanha sem público (sem Time/Líderes) não mostra treinamento algum.
- **Arquivo do treinamento:** `/pesquisa/{token}/treinamento/{id}/arquivo`, só para o público contratado e liberado, com a campanha aberta (404 nos demais casos).
- **Envio duplicado:** não foi decidido aqui. Continua a política configurável do plano (`nr1.collection.duplicate_policy`: `none` por padrão, `device_cookie`, `one_time_token`); o teste cobre `none` e `device_cookie`. **Continua item VALIDAR com a Suzane.**
- **Acessibilidade (T24):** o foco vai para o título da etapa a cada mudança, a etapa e o progresso são anunciados (região `aria-live`), cada afirmação é um grupo de rádios com legenda, e os erros usam `role="alert"`. Conferido no navegador **só com o teclado**: das 39 etapas à revisão (Tab, Espaço, Enter), sem erro de console. A lógica de etapas tem teste em `tests/JavaScript/nr1Journey.test.mjs`. Não houve teste com leitor de tela real (NVDA/JAWS/VoiceOver): fica como verificação manual antes do lançamento.
- **Bug de produção corrigido (importante):** `SurveyPublicEntry` consultava `surveys.public_token` (coluna **uuid**) com o token do link por público (texto de 40 caracteres). No PostgreSQL isso gera erro e a página pública dava **500** em todo link por público (Time/Líderes), inclusive os da etapa 3 de coleta; a suíte (SQLite) não pegava. Agora só se consulta a campanha por esse token quando ele é um uuid (teste de regressão em `SurveyPublicEntryTokenTest`). Tokens inventados passam a dar 404 em vez de 500.

**Diferenças visuais restantes (HTML × sistema):** (1) o HTML mostra cartões de materiais por público; o sistema mostra a orientação e a lista de treinamentos liberados na tela inicial; (2) o questionário usa o visual da pesquisa já existente (cabeçalho claro, rádios em linha), não o roxo do HTML; (3) o HTML tem escala de concordância, descartada de propósito; (4) não há vídeo da orientação.

## Telas, etapa 4: Oportunidades (Caderno §14)

**Feito.** Fila de oportunidades em `admin/oportunidades-nr1` (módulo `nr1_oportunidades`): contadores (identificadas, em contato, a qualificar), filtros por origem (**Treinamento**, **Solução sugerida**, **Solução solicitada**) e estado, tabela com cliente e campanha, serviço, estado, responsável, última atividade e próxima ação, e a página da oportunidade (`fila/{id}`) com **histórico** (autor, data, nota). Estados: Novo / A qualificar / Em contato / Proposta enviada / Ganho / Perdido.

- **Assumir oportunidade:** o usuário vira responsável; «Novo» passa a «A qualificar»; quem já tem dono não é tomado.
- **Estados:** Ganho só depois de «Proposta enviada»; **Perdido (recusa, T22)** de qualquer estado aberto, sempre com o motivo (mínimo de 5 caracteres), gravado em `lost_reason` e no histórico; Ganho e Perdido encerram e liberam um novo pedido do mesmo treinamento.
- **Próxima ação:** texto e data opcional, só em oportunidade aberta.
- **Permissões (T21):** ver exige `view`; assumir, mudar estado e próxima ação exigem `edit` no módulo (um Comercial só com `view` lê e não trabalha a fila). Empresa, revisor externo e super admin sem permissão recebem 403. O Comercial vê só **metadados** da oportunidade (lista branca em `Nr1CommercialSerializer::queueRow`) e, na página da oportunidade e em «Campanhas publicadas», o **agregado** da campanha com o grupo mínimo; nunca resposta bruta.
- **«Publicado» no lugar de `commercial_released_at`:** o Comercial só vê campanha com `published_at`. A liberação manual (`liberar-comercial`, controller e rotas) **saiu**; a coluna `commercial_released_at` continua no banco, sem uso, para uma limpeza futura. O teste `Nr1V11CommercialTest` foi atualizado (rota de liberação removida, critério por publicação, rotas do módulo agora incluem as três ações da fila).
- **Criação:** oportunidade de treinamento nasce só do «Solicitar proposta» (etapa 2); a de solução vem da etapa 5.

**Pendências:** não há aviso por e-mail ou notificação quando entra oportunidade nova; o Comercial não cria oportunidade à mão; sem atribuir a outra pessoa nem devolver à fila (quem assumiu não solta); a lista não pagina além de 200 linhas; «Em contato» aceita voltar para «A qualificar» (decisão simples, revisar com o Comercial).

**Diferenças visuais restantes (HTML × sistema):** (1) o HTML mostra «Assumir» na própria linha da fila e a etapa em chip único; o sistema também, mas com colunas extras (campanha, responsável, última atividade, próxima ação); (2) o HTML tem 3 contadores coloridos e o sistema usa cartões brancos; (3) o HTML lista as seis soluções como linhas «antes de publicar»; o sistema só cria oportunidade de solução depois de «Publicado».

## Telas, etapa 5: Soluções Talents (Caderno §14)

**Feito.** Tela da empresa `client/surveys/{id}/solucoes` (só depois de «Publicado»: antes disso 404, como o resto, T02): os **três cartões de serviço** do HTML (Pesquisa Psicossocial, Avaliação Complementar, Intervenção Organizacional) e as **recomendações por dimensão em atenção**, cada uma com a dimensão e o índice, o serviço, a explicação consultiva, os serviços elegíveis, o **grau de confiança** e o aviso «sugestão consultiva, não é diagnóstico nem classificação de risco do PGR».

- **Matching configurável** (`nr1_service_matches`, tela da Talents em `admin/solucoes-nr1`): por dimensão, serviço, serviços elegíveis, explicação consultiva, confiança (Alta, Média, Baixa), regra ativa e responsável comercial padrão. As **seis soluções do HTML entram como padrão inicial**, com confiança Média.
- **Revisão pela Talents:** a empresa só vê uma regra **ativa e revisada** (`nr1.solutions.require_review`, padrão ligado; variável `NR1_SOLUTIONS_REQUIRE_REVIEW`). As seis nascem **não revisadas**, porque a aprovação dos textos é a pergunta 11 da Suzane: a Talents revisa cada uma em «Revisar e liberar à empresa». Alterar o serviço, o texto ou a confiança derruba a revisão; só ativar ou desativar não derruba.
- **«Solicitar conversa»** (POST, exige edição em Pesquisas): gera o lead (`origin = solution_request`, estado «Novo») com empresa, campanha, serviço, necessidade (dimensão em atenção mais a nota digitada), data e solicitante; se a regra tem responsável comercial padrão, ele já vem como responsável. Pedido aberto duplicado do mesmo serviço é recusado; ver a tela não cria nada.
- **Sugestão automática:** ao **publicar** (`ReviewRoundService::publish`), cada regra ativa que casa com uma dimensão em atenção vira uma oportunidade **«A qualificar»**, sem solicitante e com a nota «não é interesse declarado». Nunca vira «Novo» nem `solution_request`. Falha na sugestão não desfaz a publicação. Se a empresa depois clicar em «Solicitar conversa» do mesmo serviço, a sugestão aberta **vira o pedido da empresa** (sem criar uma segunda).
- **Testes de regressão** rodados por causa do gancho em `ReviewRoundService`: DocumentsFlow, PublicationGate, CanonicalJsonLegacyHash, PsychosocialDocuments, ExecutiveBrief, PgrSubsidy, EvidenceDossier, CampaignTrainings, Opportunities e Commercial (122 testes verdes); imutabilidade, hash, T02, T19 e T20 intactos. A suíte completa e a matriz T01 a T25 ficam para a bancada de testes.

**Perguntas e pendências:** (11) os textos, serviços e a regra para dimensão **Elevada** (3,67 ou mais) ainda dependem da Suzane: hoje toda dimensão em atenção usa a mesma regra, e a confiança é escolhida pela Talents (sem cálculo automático); sem aviso por e-mail quando entra lead; a empresa não vê o andamento do lead (só «Conversa solicitada»); «Consultar oportunidade» do HTML para a Talents é a própria fila de Oportunidades; o responsável comercial padrão lista todos os super admins.

**Diferenças visuais restantes (HTML × sistema):** (1) os cartões de serviço do HTML têm número grande e chip colorido; o sistema usa número pequeno e chip simples; (2) o HTML mostra as seis ofertas em 2 colunas sempre; o sistema mostra só as que casam com dimensões em atenção e estão revisadas; (3) o HTML tem o botão «Consultar oportunidade» para a Talents na mesma tela, o sistema leva a Talents à fila.

## Suíte verde: o baseline de 77 falhas foi zerado

**A partir daqui o critério é suíte 100% verde (0 falhas), não mais "sem falha além das 77".** `baseline-nomes.txt` ficou vazio de propósito; qualquer falha é nova.

Correção feita só em testes e fábrica, sem tocar em código de produção:

- `UserFactory::talentsOwner()` (estado novo: super admin proprietário). O padrão `superAdmin()` **não mudou**: continua sem permissão, para os testes de recusa.
- Os 76 testes que criavam super admin sem permissão e esperavam acesso passaram a usar `talentsOwner()`, só nos métodos que falhavam.
- `CommercialDemoSmokeTest`: lê `sale_id` e o código do `label`. `CompanyActivityFieldsTest`: envia `ferias_access_mode`, `desligamento_access_mode` e `acompanhamento_access_mode`.
- `HiringProcessAdminTest`: a regra vigente foi mantida (exige permissão); o teste agora prova 403 sem permissão e 200 com `solides_acompanhamento` na matriz (renomeado: `test_admin_talents_needs_the_matrix_permission_to_access_acompanhamento`).
- **Negativos novos (sem permissão continua 403):** Configurações (`SettingsPageTest`), convites (`CompanyInvitationResendTest`, sem e-mail enviado) e isolamento por empresa (`FeedbackVisibilityTest`: nem a sessão da empresa selecionada abre).

## Classificação das 77 falhas do baseline (somente leitura; nenhum teste ou código foi alterado)

Método: leitura das mensagens do JUnit da Etapa 7 e **um experimento descartável**: com o `UserFactory::superAdmin()` forçado a `is_owner = true` (alteração desfeita depois), rodei a suíte inteira. **74 das 77 falhas passaram**. Das 3 que sobraram, uma é de outro motivo (payload do painel financeiro) e duas continuam por causa específica (campos obrigatórios novos; teste que afirma o contrário da regra atual). Grupos: A = ambiente, B = teste defasado, C = bug real, D = inconclusivo. Esforço: P (< 1 h), M (até 1 dia), G (mais de 1 dia).

**Resultado: A = 0, B = 77, C = 0, D = 0 (com 1 teste, `HiringProcessAdminTest`, que depende de uma decisão de produto, ver abaixo).** Nenhum bug real e nenhum problema de ambiente foram encontrados nas 77.

**Causa única de 76 delas (B1):** o teste cria `User::factory()->superAdmin()` **sem** `is_owner` e **sem** conceder permissões na matriz e espera acesso ao painel Talents. Desde a matriz granular (commits `8f4716b`, `a895e29`, de agosto) um super admin que não é o proprietário **sem permissão recebe 403**, e isso é a regra vigente, afirmada por teste que passa (`AdminTalentsMasterAccessTest::test_non_owner_super_admin_without_grants_cannot_access_financeiro`). Os testes são anteriores à matriz (últimas edições entre abril e agosto) e nunca foram ajustados. A resposta 403 é o lado seguro: nenhuma das 77 falhas indica acesso indevido.

| Arquivo (falhas) | Grupo | Causa | Esforço | Toca em |
|---|---|---|---|---|
| `Tasks/TaskModuleTest` (28) | B1 | admin sem `is_owner` nem permissão em Tarefas | P–M (um helper de criação do admin) | — |
| `StrategicCalendarTest` (8) | B1 | idem, Calendário estratégico (4 são validação que nunca chega a rodar: o 403 vem antes) | P | — |
| `Admin/MeetingModuleTest` (5) | B1 | idem, Reuniões | P | — |
| `Admin/InternalRegulationTest` (5) | B1 | idem, Regulamento interno | P | — |
| `Admin/CompanyInvitationResendTest` (4) | B1 | idem, reenvio de convite e redefinição de senha | P | **segurança** (convite e senha) |
| `Admin/AdminCriticalRoutesAuthorizationTest` (4) | B1 | idem, "super admin acessa" empresas, planos, modelos de pesquisa, interesse da landing | P | **segurança** (autorização de rotas críticas) |
| `CompanyNoticeTest` (3) | B1 | idem, avisos | P | — |
| `Admin/SettingsPageTest` (3) | B1 | idem, Configurações | P | **segurança** (colunas criptografadas, chaves de API) |
| `Admin/MonthlyHighlightTest` (3) | B1 | idem, Destaques do mês | P | — |
| `Admin/CompanyUserManagementTest` (3) | B1 | idem, usuários da empresa, convites, senha | P | **segurança** (convite e senha) |
| `Admin/PlanCrudTest` (2) | B1 | idem, Planos | P | Comercial (planos SaaS) |
| `Admin/AttachExistingEmailTest` (2) | B1 | idem, vínculo de e-mail existente entre workspaces | P | **segurança** (identidade/e-mail) |
| `Admin/CompanyActivityFieldsTest` (2) | B1 (+B3 em 1) | 403; e um deles também envia o formulário **sem** os campos obrigatórios novos (`ferias_access_mode`, `desligamento_access_mode`, `acompanhamento_access_mode`) | P | — |
| `Unit/Feedback/FeedbackVisibilityTest` (1) | B1 | idem, sessão do super admin para a empresa selecionada | P | **segurança** (isolamento entre empresas) |
| `Admin/InterviewModuleTest` (1) | B1 | idem, Entrevistas | P | — |
| `Admin/BusinessDiagnosticTest` (1) | B1 | idem, Diagnóstico | P | — |
| `Admin/HiringProcessAdminTest` (1) | B1 / **D** | o teste cria `is_owner = false` e afirma que o super admin acessa `acompanhamento` "sem permissões granulares". A regra atual dá 403. É o resíduo da época do "acesso master" (`0c6f2e9`, julho), revertida pela matriz. **Decisão de produto:** Acompanhamento deve ser aberto a todo super admin ou só a quem tem permissão? Recomendo manter a regra atual e corrigir o teste. | P (após a decisão) | — |
| `Admin/Commercial/CommercialDemoSmokeTest` (1) | **B2** | `test_demo_finance_dashboard_lists_overdue_and_upcoming_installments` lê `overdueInstallments[].sale.code`, mas o painel financeiro hoje devolve `sale_id` e `label` (o código está no texto do `label`; mudança de 18/09, `4dd422d`, quando entraram os recebimentos manuais). Conferi com os dados: a parcela vencida de VENDA-DEMO-0003 **aparece** no painel. O comportamento está certo; o teste lê o campo antigo | P | **Financeiro / Comercial** (painel financeiro, comissões demo) |

**Pontos que merecem atenção, mesmo sendo grupo B:**

- **Cobertura de autorização perdida:** 76 testes de "caminho feliz" de rotas administrativas estão quebrados desde agosto, entre eles os de **Configurações (chaves criptografadas), convites e redefinição de senha, vínculo de e-mail, isolamento por empresa e rotas críticas**. A recusa (403) continua coberta; **o acesso permitido não é verificado por esses testes**. Uma regressão que bloqueasse quem deveria acessar passaria batida.
- **Não dá para corrigir pelo padrão da fábrica:** com `is_owner = true` por padrão na fábrica, as 77 passam, mas **6 testes que hoje passam quebram** (`AdminUserFunctionLabelTest`, `Nr1Stage0PrivacyTest`, `Nr1V11CommercialTest` ×2, `Nr1V11DocumentsFlowTest` autorização por perfil, `Nr1V11PublicationGateTest` perfil comum), porque dependem de o super admin comum **não** ser proprietário. A correção é por teste (`['is_owner' => true]`) ou um estado novo da fábrica (`talentsOwner()`), não uma mudança do padrão.
- **RHID:** nenhuma das 77 toca em RHID. **Financeiro:** 1 (demo). **Comercial:** 3 (Planos 2, demo 1). **Segurança:** 17 (rotas críticas 4, Configurações 3, convites e senha 7, e-mail 2, isolamento por empresa 1), todas do lado seguro.
- Ordem sugerida de correção: (1) os de segurança, que devolvem a cobertura do acesso permitido; (2) `CommercialDemoSmokeTest` (um campo); (3) o resto por arquivo; (4) `HiringProcessAdminTest` depois da decisão. Esforço total estimado: **P–M (cerca de meio dia a um dia)**, porque a causa é uma só.

## Decisões adotadas

Padrões adotados pelas recomendações, **sem validação formal ainda**. Cada um leva a marca «provisória, aguardando validação de [quem]». Nenhum código foi alterado por causa desta seção; onde o comportamento atual **não** é igual ao padrão adotado, isso está dito na coluna «Estado no código».

| # | Decisão | Marca | Estado no código |
|---|---|---|---|
| 1 | **PASSEG:** cadastro por nome e e-mail; compartilhamento documento a documento. | provisória, aguardando validação da **Suzane e do jurídico (quem é a PASSEG e como entra)** | O compartilhamento por documento (`document_shares`, por versão, com validade) já é o desenho atual. **Diferença:** não existe tela para cadastrar o revisor por nome e e-mail; hoje o usuário revisor precisa já existir (perfil de revisor externo). Falta a tela de cadastro e o MFA (D14). |
| 2 | **Instrumento V11:** homologação técnica pendente antes de qualquer campanha real. | provisória, aguardando validação da **responsável técnica (Suzane) e da homologação metodológica, jurídica, de privacidade e de SST** | Nada no código impede uma campanha real; o aviso «Polaridade não revalidada» aparece no painel enquanto a auditoria de polaridade estiver pendente. A regra é de processo, não de sistema. |
| 3 | **Registro profissional obrigatório** do responsável técnico para publicar. | provisória, aguardando validação da **Suzane** | Já é o comportamento (T19 bloqueia publicar sem nome, função e registro). |
| 4 | **Envio duplicado:** convite único de uso único, desvinculado da resposta, com a configuração `nr1.collection.duplicate_policy` no padrão conservador até o jurídico validar; texto informativo e retenção pendentes do jurídico. | provisória, aguardando validação do **jurídico (LGPD)** | **Diferença:** o padrão da configuração hoje é `none` (só limite de taxa). O convite de uso único (`one_time_token`, só o hash do código é guardado, gerado por `nr1:generate-invites`) existe, mas **não é o padrão**. Trocar o padrão para `one_time_token` muda o comportamento de produção; **aguarda a sua confirmação** (não foi feito). Texto informativo e prazo de retenção: pendentes do jurídico. |
| 5 | **Cadastro excluído ou inativo (T23):** política = definir prazo de retenção e anonimizar; sem implementação até o jurídico definir o prazo. | provisória, aguardando validação do **jurídico (prazo de retenção)** | **Lacuna documentada, sem teste inventado.** Só existe o comando de limpeza de dados da empresa (`WipeCompanyData`). |
| 6 | **Mínimo de 5 respondentes** por grupo; **elegíveis cadastrados pela Talents.** | provisória, aguardando validação da **Suzane e do jurídico** | Já é o comportamento (`Nr1GroupPrivacy::FLOOR = 5`, nunca desce; elegíveis só pela Talents, com fonte). |
| 7 | **Nomes dos setores em «Outros Setores»:** ocultos (D8). | provisória, aguardando validação da **Suzane e do jurídico** | Já é o comportamento (`show_others_composition` desligado; só com 3+ setores e o grupo no piso). Ainda sem tela para ligar. |
| 8 | **Rótulos das faixas:** «menor sinalização / atenção / maior sinalização». | provisória, aguardando validação da **Suzane** | Já é o comportamento (`Nr1BandLabels::V11`). A troca para Leve / Atenção / Elevado segue como mudança só de exibição, não feita. |
| 9 | **Soluções:** textos do HTML como padrão; regra para dimensão «Elevada» igual à de atenção por enquanto. **As 6 NÃO foram marcadas como revisadas:** a aprovação é feita pelo responsável pela interface. | provisória, aguardando validação da **Suzane (textos e regra) e aprovação de cada solução pelo responsável, na interface** | Já é o comportamento: «em atenção» é índice ≥ 2,34 (inclui a faixa maior), e a solução só chega à empresa com `reviewed_at` preenchido (`nr1.solutions.require_review`, padrão ligado). Nada é marcado como revisado por código. Conferir, antes do lançamento, se os 6 textos do HTML estão carregados como padrão inicial. |
| 10 | **Comercial:** «Marcar contratado» manual; vídeo e preços ficam como pendência de conteúdo. | provisória, aguardando validação da **Talents (comercial)** | Já é o comportamento (manual, pela Talents; preço e escopo em texto). Vídeo da orientação não existe. |
| 11 | **«Exigir revisão PASSEG»:** desligado até o MFA existir; depois, ligado por padrão para campanhas novas. **Só registrado, nada implementado agora.** | provisória, aguardando validação da **Talents (segurança) e da Suzane** | O padrão atual é desligado (coluna `requires_passeg_review`, padrão `false`). O «ligado por padrão depois do MFA» não está implementado. |
| 12 | **Ciência da empresa:** um registro por publicação. | provisória, aguardando validação da **Suzane e do jurídico** | Já é o comportamento (`psychosocial_review_acknowledgements`, um por rodada publicada). |

**Para você decidir (muda produção):** os itens **1** (falta a tela de cadastro do revisor) e **4** (padrão de `duplicate_policy` hoje é `none`, não o convite único). Os demais já batem com o código.

## Bancada de testes (plano)

**Status: PLANO, nada foi executado.** Escrito pela janela 1 (somente leitura). A bancada só roda quando for pedido e nunca enquanto a janela 2 estiver editando (conferir `.git/coordenacao-nr1.txt`). Estado do código lido: commits até `1dee9c6`; etapas 4 e 5 em andamento na janela 2.

### 1. Matriz T01 a T25 do Caderno × testes

Legenda: **OK** = cenário coberto por teste automático; **PARCIAL** = coberto em parte; **LACUNA** = sem teste; **MANUAL** = só se prova à mão.

| T | Cenário (Caderno) | Teste que cobre | Situação e lacuna |
|---|---|---|---|
| T01 | Empresa não vê campanha nem documento de outra | `Nr1V11DocumentsFlowTest` (autorização por perfil), `Nr1V11ActionPlanTest`, `Nr1V11CampaignTrainingsTest`, `Nr1V11OverviewResultsTest` (outra empresa → 404) | **PARCIAL.** Não há um teste único que percorra **todas** as rotas com `{survey}` trocando de empresa. O `Nr1V11PublicationGateTest` lista as rotas da empresa, mas só para o gate de publicação. Sugestão: teste que itere a lista de rotas e exija 404 para a empresa B. |
| T02 | Sem acesso antes de «Publicado» | `Nr1V11PublicationGateTest` (todas as rotas com `{survey}`), `Nr1V11DocumentsFlowTest` | **OK.** O teste de rotas obriga a nova rota a estar no gate ou na lista de exceções (já pegou a rota de proposta). |
| T03 | PASSEG só aprova versão submetida; mudança exige nova versão | `Nr1V11DocumentsFlowTest`, `Nr1V11ExternalReviewTest` | **OK.** |
| T04 | «Solicitar alteração» exige justificativa e guarda histórico | `Nr1V11DocumentsFlowTest` (T04 por HTTP) | **OK.** |
| T05 | Time não recebe treinamento só de Líderes (e vice-versa) | `Nr1V11JourneyTest`, `Nr1V11CampaignTrainingsTest` | **OK.** Vale também para o arquivo do treinamento (404 por público). |
| T06 | Orientação visível aos dois públicos | `Nr1V11JourneyTest` | **PARCIAL.** O teste prova a orientação para o link do Time; falta afirmar a mesma coisa para o link de Líderes (uma linha). |
| T07 | Proposta Online/Time cria lead sem duplicar | `Nr1V11CampaignTrainingsTest` (T07, índice único) | **OK.** O «CRM» é a fila `nr1_opportunities` (etapa 4). |
| T08 | 39 itens com escala de frequência | `Nr1V11InstrumentTest`, `Nr1V11JourneyTest` (rótulos e 39 itens) | **OK.** A tela por teclado foi conferida no navegador (não automatizada). |
| T09 | Inversão (6 − resposta) nos itens favoráveis | `Nr1V11CalculationTest`, `Nr1Talents39CalculationPhaseSixTest`, `Nr1V11InstrumentTest` | **OK.** Confirmar na leitura que o teste de inversão usa itens dos dois sentidos. |
| T10 | Q7 = 92,5% e Q8 = 75% | `Nr1V11CalculationTest`, `Nr1V11DashboardOverviewTest` | **OK.** |
| T11 | 40 respostas e 48 elegíveis = 83,3% | `Nr1V11DashboardOverviewTest`, `Nr1V11CampaignTrainingsTest` (participação) | **OK.** |
| T12 | «Outros Setores» agrega 15 respostas e recalcula | `Nr1V11CalculationTest` | **OK.** |
| T13 | Filtro e exportação não deixam inferir setor de 1 a 4 | `Nr1V11DashboardResultsTest`, `Nr1V11PanoramaPrintTest`, `Nr1Stage0PrivacyTest` | **PARCIAL.** O filtro por **dimensão** da tela de Resultados é do navegador e não tem teste de privacidade próprio (ele só filtra dados já agregados). |
| T14 | Lista de atenção com as seis dimensões, em ordem | `Nr1V11DashboardOverviewTest` | **OK.** |
| T15 | 39 cartões pesquisáveis, filtráveis, ordenáveis | `tests/JavaScript/nr1Items.test.mjs` | **PARCIAL.** Só lógica pura em JS; sem teste de tela (o projeto não tem E2E). |
| T16 | Talents edita prazo e responsável sugerido sem confirmar | `Nr1V11ActionPlanTest` | **OK.** |
| T17 | Empresa confirma responsável e prazo, salva prova e eficácia | `Nr1V11ActionPlanTest` | **OK.** |
| T18 | Relatório técnico incorpora o plano; executivo separado | `Nr1V11DocumentsFlowTest` | **OK.** |
| T19 | Documento publicado sem campos vazios ou fictícios | `Nr1V11DocumentsFlowTest` (envio e publicação, caminho direto e com PASSEG) | **OK.** A lista de campos obrigatórios aguarda a Suzane. |
| T20 | Publicado não muda sem nova versão | `Nr1V11DocumentsFlowTest`, `CanonicalJsonLegacyHashTest` | **OK.** |
| T21 | Oportunidades pós-publicação: origem rastreável, sem dado bruto | `Nr1V11OpportunitiesTest`, `Nr1V11CommercialTest` (janela 2, em andamento) | **A conferir** quando a janela 2 terminar. |
| T22 | Recusa comercial não altera classificação nem fecha pendências | `Nr1V11OpportunitiesTest` (janela 2) | **A conferir.** Falta um teste que prove que a recusa **não toca** nos resultados do painel nem nas medidas. |
| T23 | Empresa ou colaborador excluído/inativo (LGPD) | só o comando de limpeza (`WipeCompanyData`, nos testes das fases 3 e 4) | **LACUNA.** Não há teste de política para empresa/usuário **inativo** ou excluído nas telas e links V11 (link público de campanha de empresa inativa; treinamentos e oportunidades na limpeza). Depende de a Suzane e o jurídico definirem a política. |
| T24 | Celular, desktop, teclado e leitor de tela | `tests/JavaScript/nr1Journey.test.mjs`, privacidade em `Nr1V11JourneyTest`; teclado conferido no navegador | **MANUAL.** Falta leitor de tela real e conferência em celular (ver o roteiro abaixo). |
| T25 | Estado vazio sem zerar médias | `Nr1V11CalculationTest`, `Nr1V11DashboardOverviewTest`, `Nr1V11OverviewResultsTest`, `Nr1V11CampaignTrainingsTest` | **OK.** |

**Lacunas a fechar (todas pequenas, na ordem):** T23 (política) → T01 (teste que itere as rotas) → T22 (recusa não altera resultados) → T06 (Líderes) → T13 (filtro de dimensão) → T15 (tela).

### 2. Execução no PostgreSQL (o bug do uuid só aparece lá)

**Por quê:** a suíte padrão roda em SQLite em memória. O erro de `SurveyPublicEntry` (token de texto contra a coluna uuid `surveys.public_token`) só existe no PostgreSQL, e `CHECK` do Postgres são criadas só nos ramos `pgsql` das migrations. Qualquer coisa que use `uuid`, `CHECK`, `jsonb` ou índice único parcial precisa de uma passada no Postgres.

**Plano (a executar só quando pedido):**

1. **Banco isolado.** Criar um banco separado (`talents_test_pg`) no mesmo container do Postgres; **nunca** apontar para o banco de desenvolvimento (a suíte usa `RefreshDatabase` e apaga tudo).
2. **Configuração só na execução.** Passar `DB_CONNECTION=pgsql DB_DATABASE=talents_test_pg` por variável de ambiente no comando (sem editar `phpunit.xml`, que força SQLite; verificar se as variáveis de ambiente prevalecem; se não, usar um `phpunit.pgsql.xml` **fora** do repositório).
3. **Ordem.** (a) `migrate:fresh` no banco novo (valida as migrations e os `CHECK` das fases 1 a 7, etapas 1 a 5); (b) só os testes V11: `Nr1V11*`, `Nr1Stage0Privacy`, `SurveyPublicEntryTokenTest`, `CanonicalJsonLegacyHashTest`, `Nr1Talents39*`, `PsychosocialDocumentsPhaseFour`; (c) depois a suíte completa, uma execução por vez.
4. **O que esperar de diferente.** Falhas por `LIKE` sensível a maiúsculas, ordenação de `NULL`, `uuid` inválido, tipos `boolean` e `json` e `CHECK` violados (por exemplo, `psychosocial_documents_type_check`, `nr1_*_check`, `psychosocial_review_rounds_published_check`). Cada falha nova no Postgres é **bug real a investigar**, não ruído.
5. **Verificação manual complementar no banco de desenvolvimento (já Postgres):** abrir um link de Time e de Líderes sem erro 500 (feito na etapa 3 depois da correção), enviar uma resposta e conferir `survey_responses`.
6. **Critério:** suíte verde também no Postgres; qualquer exceção documentada aqui com o motivo.

### 3. Teste manual com leitor de tela e dos links por público

**Preparação:** campanha V11 com Time e Líderes, um treinamento contratado e liberado só para o Time, um contratado e não liberado, um só dos Líderes; um arquivo anexado. Perfis: colaborador (links), administrador da empresa e Talents.

**Links por público (celular e desktop):**
- [ ] Link do Time abre sem erro, mostra «Você está respondendo pelo grupo: Time», a orientação e **só** o treinamento do Time contratado e liberado.
- [ ] Link dos Líderes mostra os treinamentos dos Líderes e **nenhum** do Time; o arquivo do Time devolve 404 para o link dos Líderes.
- [ ] Treinamento contratado e não liberado não aparece; ao liberar, aparece sem recarregar a campanha.
- [ ] Link fora da janela, desabilitado ou de campanha encerrada mostra «indisponível» (sem erro) e o QR some da tela da empresa.
- [ ] Token inexistente dá 404 (e não 500). QR code lido com a câmera do celular abre o link certo.
- [ ] Envio das 39 afirmações pelo celular, com a política de duplicidade escolhida; depois do envio, a página de agradecimento não mostra resultado nem oferta.

**Leitor de tela (NVDA no Windows com Edge/Chrome; VoiceOver no iPhone/Mac com Safari; TalkBack no Android):**
- [ ] Início: o título «Minha jornada» é lido; orientação e treinamentos são lidos como regiões com título.
- [ ] Cada etapa: o foco vai para «Afirmação N de 39», a dimensão é lida, o grupo de rádios é anunciado com o enunciado como legenda e a quantidade de opções («1 de 5»).
- [ ] Ao mudar de etapa, a região de status anuncia «Afirmação N de 39. X% respondido».
- [ ] «Próxima» sem escolher: o erro é lido (`role="alert"`) e o foco permanece na etapa.
- [ ] Revisão: o botão «Rever» de cada dimensão volta à primeira afirmação dela; a confirmação de envio é um checkbox rotulado.
- [ ] Só teclado (sem mouse): Tab, Espaço e Enter completam o questionário (já conferido no navegador; repetir no celular com teclado físico).
- [ ] Zoom a 200% e fonte grande: nada some nem sobrepõe; contraste das faixas e dos rótulos.
- [ ] Telas da empresa e da Talents (painel, plano, documentos, campanha, oportunidades): navegação por Tab, abas por setas, rótulos dos filtros, tabelas com cabeçalho.

### 4. Lista final de pendências de produção

**Bloqueios técnicos:**
1. **MFA (D14)** para ligar a revisão externa da PASSEG. Enquanto isso, «Exigir revisão PASSEG» fica desligado e a Talents publica direto.
2. **Passada completa no PostgreSQL** (seção 2). Já houve um erro 500 que só existia lá.
3. **D9:** contar as campanhas reais em produção; hoje as existentes seguem `requires_publication = false`.
4. **Documentos V10** não foram migrados para o fluxo V11.
5. **Auditoria de quem mudou configurações** fora de «Exigir revisão PASSEG» (por exemplo, `show_others_composition`, ainda sem tela).
6. **Fila de oportunidades e soluções:** conferir a conclusão das etapas 4 e 5 e a troca de `commercial_released_at` por «Publicado».

**Decisões de pessoas (Suzane, jurídico, Talents):**
7. Nome das faixas (Leve/Atenção/Elevado × Menor/Maior sinalização).
8. «Outros Setores»: mostrar a composição? (hoje oculta; só liga com 3+ setores e o grupo no piso).
9. Registro profissional obrigatório para o responsável técnico; lista de campos obrigatórios dos documentos.
10. Padrão de «Exigir revisão PASSEG» quando o MFA existir (recomendado: ligado).
11. Envio duplicado do questionário (`none`, `device_cookie`, `one_time_token`): decidir e documentar.
12. Prazo do plano (dias × data), responsável (área × pessoa), concluir medida exige evidência.
13. Textos e regra das seis soluções; dimensão «Elevada»; orientação em vídeo.
14. «Marcar contratado» manual ou a partir do contrato assinado; quem marca data do presencial.
15. Política LGPD para empresa e colaborador excluído ou inativo (T23).
16. Quem é a PASSEG, como entra, e o que ela enxerga além do documento enviado.

**Qualidade e operação:**
17. Teste manual com leitor de tela e celular (seção 3) antes do lançamento.
18. Aviso por e-mail ou interno à empresa quando os documentos forem publicados e à Talents quando houver pedido de proposta.
19. Diferença campo a campo entre versões de documento e impressão a partir da tela (Caderno §13).
20. `docs/PRD.md` e os 3 documentos apagados continuam como alterações antigas fora de todos os commits; decidir se entram.

## Bancada de testes: resultado

**Executada.** Suíte completa **1155 testes, 0 falhas no SQLite** e **1155 testes, 0 falhas no PostgreSQL** (banco `talents_test_pg`, separado do de desenvolvimento).

**Como o PostgreSQL foi rodado (fora do repositório).** O `tests/TestCase.php` força SQLite de propósito (`database.default = sqlite`), então `DB_CONNECTION` na linha de comando **não** basta: a primeira passada «no PostgreSQL» tinha rodado em SQLite sem avisar (conferido por uma sonda). A bancada copia `tests/` para `/tmp/tests-pg` dentro do container, troca no `TestCase` copiado o banco para `talents_test_pg` (e aborta se o nome do banco for outro, para nunca tocar no de desenvolvimento), usa um `phpunit` com `DB_CONNECTION=pgsql` e um bootstrap que carrega esse `TestCase` primeiro. `RefreshDatabase` faz o `migrate:fresh` (146 tabelas, incluindo todos os `CHECK` dos ramos `pgsql`). Nada disso entrou no repositório. Sugestão para o futuro: um `phpunit.pgsql.xml` e um `TestCase` que respeite uma variável `TEST_DB=pgsql`, para a CI rodar o PostgreSQL.

**Achados no PostgreSQL (o que o SQLite escondia):**
1. **Bug de produção corrigido: `psychosocial_measures.origin` era `varchar(16)` e o valor `engine_suggestion` tem 17 caracteres.** Toda sugestão do motor (medida «Proposta» criada automaticamente) falhava no PostgreSQL com «value too long for type character varying(16)» (9 testes). Correção: migration `2026_10_28_130000_widen_psychosocial_measures_origin` (coluna para 32; nenhum dado nem regra muda). Foi aplicada também no banco de desenvolvimento.
2. **Teste ajustado (só teste): `PsychosocialDocumentsPhaseFourTest::test_down_without_versions_drops_tables_and_keeps_survey_results`.** O `down` da fase 4 não podia apagar `psychosocial_document_versions` com as tabelas de revisão externa e de rodadas ainda referenciando-a (no PostgreSQL a FK impede). O teste agora remove as dependentes antes, como um rollback em ordem.
3. O erro 500 do `SurveyPublicEntry` (uuid) já tinha sido corrigido na etapa 3 e continua coberto.

**Lacunas fechadas (`tests/Feature/Nr1V11BenchTest.php`, 6 testes):** T01 (percorre **todas** as rotas `client.*` com `{survey}` como outra empresa, com e sem corpo válido; a medida da outra empresa não muda), T01 nos links públicos (arquivo de treinamento de outra campanha: 404), T06 (orientação também no link de Líderes, com só o treinamento deles), T13 (filtro por dimensão, impressão, CSV, JSON e CSV de perguntas nunca mostram setor pequeno), T15 no backend (39 cartões completos, únicos, ordenáveis; CSV com 39 linhas), T22 (a recusa comercial não altera resultados, grupos, medidas, treinamento, rodadas nem a campanha). T21 e T25 já tinham cobertura. **T23: não escrito, decisão pendente** (item 5 de «Decisões adotadas»).

**Achado que exige a sua decisão (muda produção, NÃO corrigido):** as cinco rotas de escrita das medidas do plano (`measures.confirm`, `adjust`, `replan`, `evidences.store`, `effectiveness`) **validam o corpo antes de autorizar**. Para uma empresa de fora, sem corpo, a resposta é um redirecionamento 302 com erro de validação em vez de 404. **Sem efeito:** com corpo válido a resposta é 404 e nada é gravado (o teste prova). A correção seria autorizar a medida antes de validar (uma linha em `ActionPlanMeasureController`), mudando só a resposta para quem não deveria estar ali. O `Nr1V11BenchTest` hoje documenta o comportamento atual; ao corrigir, a lista esperada fica vazia.

### Pendências de produção (lista final)

**Bloqueios técnicos**
1. **MFA (D14)** para ligar a revisão externa da PASSEG (hoje a Talents publica direto, com «Exigir revisão PASSEG» desligado).
2. **Tela de cadastro do revisor externo** por nome e e-mail (item 1 de «Decisões adotadas»).
3. **D9:** contar as campanhas reais em produção; as existentes seguem `requires_publication = false`.
4. **Aplicar as migrations no PostgreSQL de produção** (inclui a `widen_psychosocial_measures_origin`) e fazer a passada no PostgreSQL de homologação com a campanha demo.
5. **CI com PostgreSQL** (hoje a suíte padrão é só SQLite; já houve dois erros que só existiam no PostgreSQL).
6. **Autorizar-antes-de-validar** nas cinco rotas de medidas (achado acima).
7. **Documentos V10** não migrados para o fluxo V11.
8. **Auditoria da configuração** fora de «Exigir revisão PASSEG» (por exemplo `show_others_composition`, sem tela).
9. Conferir que os **6 textos de soluções** estão carregados como padrão inicial e que a aprovação de cada um é feita pela interface.

**Decisões de pessoas** (todas em «Decisões adotadas», provisórias)
10. Homologação técnica do instrumento V11 antes de campanha real (Suzane, metodológica, jurídica, privacidade, SST).
11. Envio duplicado: padrão de `duplicate_policy` (hoje `none`) × convite único; texto informativo e retenção (jurídico).
12. Política LGPD para cadastro excluído ou inativo e prazo de retenção (T23, jurídico).
13. Nome das faixas; «Outros Setores»; registro profissional; padrão de «Exigir revisão PASSEG» depois do MFA; ciência da empresa (Suzane/jurídico).
14. Quem é a PASSEG e o que ela enxerga além do documento enviado.
15. Regra da dimensão «Elevada» nas soluções e textos finais; vínculo de «Marcar contratado» com contrato assinado.

**Qualidade e operação**
16. **Teste manual com leitor de tela e celular** (roteiro em «Bancada de testes (plano)»): nenhum leitor real foi usado; só teclado no navegador.
17. Aviso por e-mail ou interno à empresa quando os documentos forem publicados e à Talents quando houver pedido de proposta.
18. Diferença campo a campo entre versões de documento e impressão a partir da tela.
19. Vídeo da orientação e preços/conteúdo comercial (pendência de conteúdo).
20. `docs/PRD.md` e os 3 documentos apagados continuam como alterações antigas fora de todos os commits.

## Rodada pós-bancada (decisões do Suede)

**Feito e commitado** (cada um com a suíte completa verde no SQLite): `c18ac72` autorizar antes de validar nas rotas de medidas (teste estrutural em `Nr1V11BenchTest`: nenhuma rota da empresa com `{survey}` valida antes de autorizar); `aa2f141` cadastro de revisor externo pela Talents (nome, e-mail, organização, convite; continua desligado e sem acesso enquanto a revisão externa estiver desligada) e **convite único por campanha** (`surveys.uses_invitations`, tabela `survey_invitation_tokens` só com hash e mês de uso, sem vínculo com a resposta; padrão global continua `none`; testes de anonimato); `5bda1fe` avisos no sistema de Avisos (nova oportunidade → Comercial; nova solicitação de proposta ou conversa → Talents; relatório publicado → empresa), com e-mail só quando há SMTP configurado e filtrados por permissão (`NoticeVisibility`); `3cc1cac` a empresa vê o andamento do próprio pedido em Soluções (Solicitado, Em contato, Proposta enviada, Encerrado), sem nada interno do Comercial; `fix(ui)` lista do seletor de empresa acima dos modais.

**Suíte:** SQLite 1185 testes, 0 falhas; **PostgreSQL (`talents_test_pg`) 1185 testes, 0 falhas** (suíte completa, com a proteção que aborta se o banco for outro).

**Observações:** (1) cada publicação gera um aviso «nova oportunidade» por regra de solução ativa (as sugestões automáticas); com as 6 regras padrão são até 6 avisos juntos ao Comercial: se ficar ruidoso, agrupar em um só. (2) A rota de avisos do módulo de calendário estratégico continua restrita a esse módulo; o sino mostra os avisos por permissão. (3) **Alterações não reconhecidas no working tree:** `docs/redesign/00-diagnostico.md` e `docs/redesign/baseline-testes-falhando.txt` apareceram apagados sem ação desta janela; não foram tocados nem commitados (docs/ fica fora de todos os commits).

**MFA (somente desenho, nada implementado, aguarda aprovação):** TOTP por aplicativo autenticador para `super_admin` e revisor externo; tabela `user_mfa_secrets` (segredo cifrado com APP_KEY, `confirmed_at`) e `user_mfa_recovery_codes` (10 códigos, só o hash, uso único); inscrição no primeiro login (QR + confirmação de um código) e obrigatória antes de qualquer tela; middleware `mfa.verified` depois do `auth` (sessão marca `mfa_passed_at`, expira com a sessão); ponto de integração: `config('nr1.external_review.mfa_required')` passa a exigir `mfa_passed_at` no `/revisao` e só então `external_review.enabled` pode ser ligado; recuperação por código ou reset feito por outro admin Talents (com registro); limite de tentativas e auditoria; impacto: o login ganha uma etapa, as rotas admin/revisão exigem a marca e os testes passam a usar um estado de fábrica `->mfaVerified()` (o `talentsOwner()` já o incluiria) sem mudar o padrão; esforço estimado: migrations + serviço TOTP (lib `pragmarx/google2fa`) + 3 telas + ajuste de testes.

## MFA implementado (TOTP)

**Decisão do Suede aplicada** (desenho aprovado com ajustes). Biblioteca madura `pragmarx/google2fa` (sem criptografia própria); QR com `bacon/bacon-qr-code`, já instalada.

- **Regras do código:** TOTP de 30 s com tolerância de ±1 intervalo; **um código já usado não vale de novo** (guarda-se o último intervalo usado e a atualização é condicional, então duas requisições com o mesmo código não passam as duas); 10 códigos de recuperação de uso único, **só o hash** (HMAC com a `APP_KEY`), exibidos **uma única vez**; gerar novos invalida os antigos.
- **Segredo:** cifrado com a `APP_KEY` (`user_mfa_credentials.secret`). Se não decifra, o estado é «precisa de nova inscrição» e **nunca libera** (falha segura).
- **Tentativas:** limite por usuário **e** por IP (5 erradas, bloqueio de 15 minutos, configurável em `config/mfa.php`); durante o bloqueio nem o código certo nem o de recuperação passam.
- **Quem precisa:** inscrição opcional para todo super_admin; **obrigatória para o revisor externo no login** (com `nr1.external_review.mfa_required`); **step-up** (MFA concluído há até 15 minutos) nas ações sensíveis do NR-1: **publicar documentos, dado bruto (CSV) e salvar a Revisão interna**. Quem não se inscreveu é levado à inscrição só nessas ações; Financeiro, Comercial, RHID, Tarefas etc. não travam. A obrigatoriedade de MFA no login de todo super_admin fica atrás de `MFA_REQUIRED_AT_LOGIN` (**desligada por padrão**); o step-up pode ser desligado com `MFA_STEP_UP_REQUIRED=false`.
- **Revisão externa:** `nr1.external_review.mfa_required` agora exige a marca `mfa_verified_at` na sessão em `/revisao`; sem ela o revisor vai para o desafio ou a inscrição. A revisão externa continua **desligada por padrão**; com o MFA implementado, o único pré-requisito técnico para ligá-la é o seu OK (e o cadastro dos revisores).
- **Reset:** por outro admin, só o **proprietário** (`is_owner`), nunca sobre si mesmo; registra auditoria (`mfa_audit_events`: ator, alvo, data, IP; só inserção) e **avisa o usuário por e-mail**. Comando de emergência: `php artisan mfa:reset {email}` (auditoria sem ator humano, também avisa por e-mail).
- **Telas:** inscrição (QR e chave), código (com opção de código de recuperação), códigos de recuperação (única vez), gestão da conta em `/mfa` (gerar novos códigos e desativar, só com verificação recente; o revisor não desativa), botão «Resetar» no cadastro do usuário e na lista de revisores (só proprietário).
- **Testes:** estado de fábrica `->mfaVerified()` (credencial ativa) e helper `actingAsMfa()` (sessão já verificada); **o padrão da fábrica não mudou**. Casos negativos: sem MFA, código errado, reutilizado, intervalos fora da janela, bloqueio por usuário e por IP, falha ao decifrar, reset sem `is_owner`, desativação do revisor. Testes antigos que publicam, exportam dado bruto ou salvam a Revisão interna agora usam `mfaVerified()`.
- **Achado dos testes:** a biblioteca devolve `true` (e não o intervalo) quando não se informa o intervalo anterior; a primeira versão guardava `1` e deixava o replay passar. Corrigido (intervalo anterior `0`), com teste.
- **Cuidado de operação:** o limitador de tentativas usa o cache (Redis em produção); os testes do MFA usam cache em memória. Em produção, o Redis precisa estar de pé (sem ele o limitador falha).

**E-mail de segurança ao usuário (feito):** uso de código de recuperação, nova inscrição, novos códigos gerados e reset por administrador/comando avisam o próprio usuário (data, hora e IP; nunca códigos). Falha de envio só é registrada em log. **Limitador falha fechado:** se o cache/Redis cair, `locked()` devolve «bloqueado»: código do aplicativo, código de recuperação e inscrição são negados e nenhum código de recuperação é consumido (teste `test_the_limiter_fails_closed_when_the_cache_is_unavailable`; as rotas com `throttle` também respondem 500).

**Pendências do MFA:** expiração/rotação de segredo; reenvio de convite do revisor leva a inscrição no primeiro login (já coberto); teste manual com um aplicativo autenticador real (Google/Microsoft Authenticator) antes de ligar a revisão externa.

## Plano de release

> Somente leitura: nada foi executado em produção nem em homologação. Levantado em 2026-10-27 a partir do repositório (`git diff`, migrations, `config/`, `.env.example`, `docker/`, `bootstrap/app.php`). Pontos que dependem do estado real do servidor estão marcados como **[confirmar]**.

### 1. O que há entre `main` e `feature/nr1-v11`

- **Atenção ao ponto de comparação:** o `main` **local** está parado em 2026-09-10 (`ce9cfd3`). O `origin/main` está em 2026-10-22 (`84f474a`, PR #114) e **já contém tudo que a branch trouxe até 2026-10-22**. A branch não está atrás em conteúdo: os 14 commits de `origin/main` que ela não tem são só merges de PR (`git diff HEAD...origin/main` vazio; `git merge-tree` sem conflito). **Atualizar o `main` local (`git fetch`) antes de qualquer PR**, senão o PR mostra 112 commits em vez de 31.
- **Contra `origin/main` (o que de fato muda): 31 commits, 286 arquivos, +24.274 / −667 linhas, 19 migrations.** Por área:
  - NR-1 V11 (22 commits `feat/fix/test(nr1)`): instrumento de 39 itens, política única de grupo mínimo, coleta por público (links, QR, convite único), painel/resultados, gate de publicação, plano de ação com sugerido × confirmado, fluxo de documentos com revisão PASSEG opcional, Revisão interna, Campanha NR-1 com treinamentos, Minha jornada, Oportunidades, Soluções, avisos ao Comercial, cadastro de revisor externo.
  - MFA (2 commits `feat(mfa)`): TOTP, códigos de recuperação, step-up, MFA do revisor, reset pelo proprietário, comando `mfa:reset`.
  - Redesign visual (2 `feat(ui)` + 1 `fix(ui)`): tokens semânticos, tema por empresa com contraste AA, fonte Figtree local, z-index do seletor de empresa.
  - Testes (1 `test`) e 1 merge.
  - **O MFA (e o e-mail de segurança do MFA, se o commit desta rodada for feito) ainda não está em nenhum PR.**
- **Contra o `main` local (se produção ainda estiver no estado de 09-10):** 112 commits, 633 arquivos, +50.764 / −4.547, 38 migrations; entram também os PRs #76 a #114 (mapa de propósito, avaliação de clientes, sugestões, gestão de ponto, portal de entrada por empresa, comissões múltiplas, protocolo legível de denúncia, import automático RHID etc.). **[confirmar]** qual commit está em produção hoje (`git rev-parse HEAD` no servidor / tag) e rodar `php artisan migrate:status` lá: só as migrations "Pending" valem.

### 2. Migrations que vão rodar (ordem de execução = ordem do nome)

**Das 19 novas contra `origin/main`** (todas **aditivas**: criam tabelas/colunas com default ou nulas; nenhuma apaga ou reescreve dado existente):

| # | Migration | Efeito | Peso / risco |
|---|---|---|---|
| 1 | `2026_10_23_100000_add_min_group_size_to_surveys` | coluna `min_group_size` default 5 + CHECK ≥ 5 (PG) | leve; `ALTER` rápido |
| 2 | `2026_10_23_100100_allow_raw_export_in_psychosocial_access_logs` | recria o CHECK de `resource` (só amplia) | leve. `down` recusa se houver linha nova |
| 3 | `2026_10_23_110000_create_survey_group_results_table` | tabela nova | leve |
| 4 | `2026_10_23_120000_create_survey_audience_links_and_invitations` | 2 tabelas + `survey_responses.audience` nula | leve (coluna nula, sem reescrita) |
| 5 | `2026_10_26_100000_create_external_review_tables` | `approver_organizations`, `document_shares`, `document_share_reviews`, coluna em `user_workspaces` | leve |
| 6 | `2026_10_26_110000_add_commercial_released_at_to_surveys` | coluna nula | leve |
| 7 | `2026_10_26_120000_add_show_others_composition_to_surveys` | coluna, default `false` | leve |
| 8 | `2026_10_26_130000_allow_panorama_resources_in_psychosocial_access_logs` | recria CHECK (só amplia) | leve. `down` recusa se houver linha nova |
| 9 | `2026_10_26_140000_add_publication_to_surveys` | `requires_publication` default **false**, `published_at/by` | leve. **Sem backfill (D9):** campanhas existentes continuam visíveis como hoje |
| 10 | `2026_10_26_150000_extend_psychosocial_measures_for_action_plan` | colunas novas em `psychosocial_measures`, tabela de evidências, CHECKs recriados (status ganha valores) | **o mais "mexido"**: troca CHECK numa tabela de medidas; `ADD CONSTRAINT` valida as linhas atuais (confirmed/effectiveness vêm nulos, passa). Tabela pequena. `down` recusa se houver `replanned` |
| 11 | `2026_10_27_100000_create_psychosocial_review_flow_tables` | 4 tabelas + CHECK de `psychosocial_documents.type` ampliado (`technical_report`) | leve. `down` recusa se houver `technical_report` |
| 12 | `2026_10_27_110000_add_passeg_review_option_to_surveys` | `requires_passeg_review` default false + `published_without_review` | leve |
| 13 | `2026_10_27_120000_create_passeg_review_option_changes_table` | tabela de auditoria | leve |
| 14 | `2026_10_28_100000_create_psychosocial_internal_reviews_table` | tabela | leve |
| 15 | `2026_10_28_110000_create_nr1_trainings_and_opportunities_tables` | 3 tabelas | leve |
| 16 | `2026_10_28_120000_create_nr1_service_matches_table` | tabela **e insere 6 regras iniciais "dimensão → serviço"** (dado, só se a tabela nasce) | leve; único que grava dados. Texto das regras precisa de revisão (pergunta 11 da Suzane) |
| 17 | `2026_10_28_130000_widen_psychosocial_measures_origin` | `varchar(16)` → `varchar(32)` (`->change()`) | leve em volume, mas é `ALTER TYPE` com lock curto em `psychosocial_measures`. Corrige bug real do PostgreSQL (`engine_suggestion`) |
| 18 | `2026_10_28_140000_add_uses_invitations_to_surveys` | coluna default false | leve |
| 19 | `2026_10_29_100000_create_mfa_tables` | 3 tabelas | leve |

- **Pesadas:** nenhuma em volume. Os `ALTER` acima pegam lock de tabela por instantes; `surveys` (7 colunas novas em 6 migrations) e `psychosocial_measures` são as mais tocadas. Fazer fora do horário de uso.
- **Destrutivas / irreversíveis:** **nenhuma das 19 apaga ou altera dado existente.** O `down()` de várias é protegido (recusa reverter se já houver dado novo), o que na prática torna o rollback de schema **parcial**: depois que alguém usa os recursos novos, voltar atrás exige restaurar o backup, não `migrate:rollback`.
- **Se o servidor estiver atrás de `origin/main`, estas migrations antigas também rodam e MEXEM em dado existente [confirmar se já foram aplicadas]:**
  - `2026_09_11_151000_strip_disclaimer_from_technical_opinions`: **reescreve** `action_plans.technical_opinion` e `ai_analyses.content` removendo o texto do Disclaimer. **Destrutiva** (o texto removido não volta; `down` não restaura).
  - `2026_10_07_180000_change_complaint_protocol_to_human_code`: **converte** `complaints.protocol` para código legível e troca a unicidade para (empresa, protocolo). **Altera identificador existente**; link/protocolo já entregue a denunciante muda. Irreversível na prática.
  - `2026_10_13_154500_rename_nr1_insight_risk_labels`: **reescreve** textos de `survey_insights` (renomeia rótulos de risco, em lotes de 200).
  - `2026_10_08_160000_allow_multiple_commissions_per_sale`: troca a unicidade `sale_id` por `(sale_id, seller_id)` (só afrouxa).
  - `2026_10_09_140100_add_sugestoes_module`: insere módulo e liga aos planos que têm o módulo de origem.

### 3. Variáveis de ambiente (valores seguros para produção)

| Variável | Padrão no código | Valor em produção | Observação |
|---|---|---|---|
| `NR1_SHELL_ENABLED` | `false` fora do `local` | **`false`** até a Suzane aprovar o visual | liga o item único «NR-1» no menu e as rotas `/nr1/*`; desligada, nada muda (ver «Shell NR-1») |
| `NR1_EXTERNAL_REVIEW_ENABLED` | `false` | **`false`** até o teste manual com autenticador real e o seu OK | com `false`, `/revisao/*` dá 404 |
| `NR1_EXTERNAL_REVIEW_MFA_REQUIRED` | `true` | **`true`** | exige a marca de MFA verificado em `/revisao` |
| `MFA_REQUIRED_AT_LOGIN` | `false` | **`false`** | `true` obriga MFA no login de todo super_admin (travaria quem ainda não se inscreveu) |
| `MFA_STEP_UP_REQUIRED` | `true` | **`true`** | exige MFA recente para publicar documentos, dado bruto e Revisão interna. **Efeito imediato no deploy:** super_admin sem MFA é levado à inscrição nessas ações |
| `MFA_STEP_UP_MINUTES` | `15` | `15` | |
| `MFA_MAX_ATTEMPTS` / `MFA_LOCKOUT_MINUTES` | `5` / `15` | `5` / `15` | limitador usa o cache (Redis) e **falha fechado** se ele cair |
| `MFA_ISSUER` | `Talents` | `Talents` | nome que aparece no aplicativo autenticador |
| `NR1_DUPLICATE_POLICY` | `none` | `none` (ou `one_time_token` por campanha, hoje controlado por `surveys.uses_invitations`) | |
| `NR1_CLIENT_DRAFT` | `false` | `false` | rascunho só no navegador |
| `NR1_SOLUTIONS_REQUIRE_REVIEW` | `true` | **`true`** | empresa só vê a regra "dimensão → serviço" depois que a Talents revisa o texto |
| `APP_KEY` | já existe | **não trocar** | cifra o segredo do MFA e as chaves de IA; trocar invalida todos os MFA (todos precisam se reinscrever) |
| `MAIL_*` / SMTP do painel | já existe | **configurado e testado** | os avisos por e-mail ao Comercial só saem com `MailSetting::smtpConfigured()` (o aviso dentro do sistema sempre existe); convite do revisor e e-mails de segurança do MFA tentam enviar e, se falharem, registram o erro sem derrubar a ação (o convite mostra a falha na tela) |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `SESSION_DRIVER` | `redis` no `.env.example` | `redis` | |

Nenhuma variável `MFA_*` ou `NR1_*` está no `.env.example`: **acrescentar** (comentadas, com o valor seguro) é a única mudança de arquivo de ambiente sugerida. Não há variável nova para avisos; o interruptor é o SMTP.

### 4. Serviços necessários

- **Redis: obrigatório e crítico.** Sessão, cache, fila e o limitador do MFA. Sem Redis o MFA nega tudo (fail-closed, testado) e o login também fica comprometido.
- **Fila (`queue:work`) e agendador (`schedule:work`):** os containers `queue` e `scheduler` do compose precisam estar de pé. O agendador roda `rhid:import-closed-day` (não é novo desta branch). **PDFs (DomPDF) e e-mails desta branch são gerados/enviados de forma síncrona na requisição**, não dependem da fila; a fila continua necessária para IA, entrevistas, reuniões, espelhos RHID e notificações de tarefas.
- **ffmpeg:** só para Entrevistas/Reuniões (já está no `Dockerfile`; nada novo).
- **Armazenamento:** disco privado `local` para PDFs dos documentos (snapshots congelados) e evidências do plano de ação. Garantir volume persistente, backup junto com o banco e espaço para crescer. Os documentos publicados não podem ser regenerados de forma idêntica, então **o arquivo faz parte do backup**.
- **PostgreSQL:** as migrations têm CHECKs só no ramo `pgsql`; testar na versão real de produção (a suíte da branch passou em PostgreSQL 1214/1214 num banco de teste).
- **Front-end:** `npm run build` no processo de build da imagem (há dependências novas de CSS/fonte e páginas Vue). `composer install --no-dev` (novos: `pragmarx/google2fa`, `bacon/bacon-qr-code`).

### 5. Seeds e comandos a executar (nesta ordem, depois do `migrate`)

1. `php artisan nr1:import-instrument-v11`: **cria o template de 39 itens** (idempotente; **nenhuma migration nem seeder o instala**). Sem ele não há campanha V11.
2. `php artisan nr1:accept-polarity <id da versão do instrumento> --user=<id> --reference=<...>`: **somente depois** da homologação técnica da Suzane (sem isso AEP/PGR não são liberados). Não rodar por conveniência.
3. **Regras de solução:** já nascem pela migration 16 (6 regras, `NR1_SOLUTIONS_REQUIRE_REVIEW=true`). A Talents revisa/aprova os textos em Soluções antes de a empresa ver.
4. **Permissões novas na matriz admin:** `nr1_dados_brutos`, `nr1_oportunidades` e demais módulos novos **não têm backfill**: super_admin que não é proprietário **não vê nada deles (403)** até receber o acesso na tela de Equipe. Conceder `nr1_oportunidades` (ver, e editar para quem trabalha as oportunidades) ao Comercial e `nr1_dados_brutos` só a quem deve ver dado bruto. O proprietário tem acesso total.
5. **Módulo `nr1_oportunidades`:** é módulo de **admin** (Equipe), não de plano da empresa. Conferir quais perfis do Comercial precisam dele.
6. `php artisan optimize:clear` e depois o ciclo normal de cache de config/rotas/views do deploy (as rotas e o `config/mfa.php` são novos).
7. Convidar os super_admin a se inscreverem no MFA (`/mfa`) **antes** do primeiro uso das ações sensíveis; comando de emergência `php artisan mfa:reset {email}`.
8. Nada a rodar para o revisor externo enquanto `NR1_EXTERNAL_REVIEW_ENABLED=false`.

### 6. Riscos

- **Campanhas existentes e `requires_publication`:** campanhas atuais ficam `false` (D9, sem backfill) e seguem visíveis como hoje, **sem passar pelo novo fluxo de publicação**. Campanhas **novas** criadas pela empresa nascem `true` e só mostram resultados/impressão/exportações depois da publicação. Risco: a empresa perceber duas regras diferentes. Antes do deploy, contar as campanhas reais (com resultados, documentos liberados, parecer) e decidir se alguma deve passar a `true`.
- **Documentos V10:** documentos e pareceres antigos continuam como estão; o fluxo de revisão/snapshot congelado vale para campanhas V11 (`talents39_v1`). Os hashes de snapshots anteriores à correção do `CanonicalJson` têm teste que garante a verificação. O CHECK de `psychosocial_documents.type` só é ampliado. Risco residual: V10 e V11 convivendo nas telas; conferir uma campanha V10 real em homologação.
- **Usuários sem matriz:** super_admin não proprietário sem grants não acessa os módulos NR-1 novos (403, por desenho). Se hoje alguém do Comercial acessa Oportunidades por outro caminho, vai perder acesso até receber a permissão. Listar quem é afetado antes do deploy.
- **Step-up do MFA já ativo no deploy:** quem publica documento, exporta dado bruto ou salva Revisão interna será levado a se inscrever no MFA. Se algo urgente estiver agendado para o dia, avisar antes ou subir com `MFA_STEP_UP_REQUIRED=false` e ligar depois.
- **E-mail:** sem SMTP funcionando, e-mails de aviso, convite e segurança não saem (a ação não falha; o aviso dentro do sistema continua). O reset de MFA avisa por e-mail; se o e-mail não sair, só a auditoria registra.
- **Redesign visual:** tokens, tema por empresa e fonte Figtree afetam todas as telas. Fazer passada visual (login, painel admin, painel da empresa, pesquisa pública, impressão do parecer) em homologação com o tema de pelo menos uma empresa com cores próprias.
- **Rollback de schema parcial** (ver §2) e **`APP_KEY` imutável** (ver §3).
- **Pendências de produto que não bloqueiam o merge, mas bloqueiam uso real:** homologação técnica do instrumento V11 (Suzane/metodológica/jurídica/privacidade/SST), D8 (`show_others_composition` fica `false`), D11 (quem é a PASSEG), teste manual do MFA com Google/Microsoft Authenticator, revisão do texto das regras de solução.
- **Hoje o `main` local está desatualizado** (§1): abrir o PR contra o `main` correto.

### 7. Ordem recomendada de deploy

1. **Congelar** o que vai: fechar o commit do e-mail de segurança do MFA; `git fetch`, confirmar `origin/main` incluído; suíte verde nos dois bancos (já está: SQLite e PostgreSQL).
2. **Homologação** com cópia recente do banco de produção (anonimizada se necessário): `migrate:status`; **`migrate --pretend`** para ler o SQL; `migrate`; `nr1:import-instrument-v11`; conceder permissões na Equipe; smoke test (abaixo); medir o tempo do `migrate`.
3. **Backup de produção antes do `migrate`:** dump do PostgreSQL (`pg_dump -Fc`, com restore testado em homologação) **e** snapshot do volume de arquivos privados (`storage/app`). Guardar o hash do commit atual de produção.
4. **Janela curta, fora do horário de uso:** modo manutenção (`php artisan down`), deploy da imagem nova (composer sem dev, `npm run build`), `php artisan migrate --force`, `nr1:import-instrument-v11`, `optimize:clear` e recache, `php artisan up`. Confirmar que `queue` e `scheduler` subiram com a imagem nova (`queue:restart`).
5. **Smoke test em produção (10 a 15 min):** login admin e login de empresa; Financeiro, Comercial, RHID/Ponto e Tarefas abrem (nada travou); criar campanha V11 de teste numa empresa de teste, responder pelo link público, ver Visão geral/Resultados; publicar documento (pede MFA) e baixar o PDF; abrir Oportunidades com um usuário do Comercial com permissão e outro sem; Soluções (regra só aparece depois de revisada); inscrever-se no MFA com autenticador real, usar um código de recuperação, resetar pelo proprietário; enviar e-mail de teste do SMTP; `/revisao/` deve responder 404.
6. **Depois:** observar log de erros e a fila por 24 h; só então, e com o seu OK, ligar `NR1_EXTERNAL_REVIEW_ENABLED=true` e cadastrar o primeiro revisor.

### 8. Plano de rollback

- **Antes de qualquer dado novo (primeiras horas):** voltar a imagem anterior + `migrate:rollback --step=19` funciona (os `down()` removem as tabelas/colunas novas; os que recusam só recusam se já houver dado novo nas tabelas ampliadas). Mesmo assim, preferir o caminho abaixo.
- **Depois de uso real (campanhas publicadas, MFA inscrito, oportunidades abertas):** o caminho seguro é **restaurar o dump** feito no passo 3 + voltar a imagem anterior, aceitando perder o que foi gravado desde o backup (por isso a janela de deploy deve ser curta e o smoke test, rápido).
- **Desligar sem reverter:** problemas só de MFA: `MFA_STEP_UP_REQUIRED=false` (e `NR1_EXTERNAL_REVIEW_ENABLED=false`, que já é o padrão); usuário preso: `php artisan mfa:reset {email}`. Problema nas regras de Soluções: manter `NR1_SOLUTIONS_REQUIRE_REVIEW=true` (empresa não vê). Nenhum desses exige reverter schema.
- **Não fazer:** rodar `migrate:fresh`, trocar `APP_KEY`, apagar `mfa_*`/`psychosocial_*` à mão.

## Shell NR-1 (fluxo próprio em /nr1): Etapa A

**Diretriz (prioridade máxima):** a Suzane quer um fluxo NR-1 próprio, fiel ao HTML aprovado, com shell e sidebar próprios. **Ajuste do Suede:** nada do que existe é excluído nem muda de comportamento; sem redirecionamentos; as rotas, controllers, páginas Vue, componentes e testes antigos seguem valendo por URL direta.

**Flag e motivo.** `config('nr1.shell_enabled')`, lida de `NR1_SHELL_ENABLED`; padrão **`false` fora do ambiente local e `true` no `local`** (`env('APP_ENV') === 'local'`). Ligada: aparece **um só item «NR-1»** no menu principal (Talents, Empresa, Comercial; só para quem tem acesso a alguma seção), some o item antigo «Pesquisas NR1» do menu da empresa e as rotas `/nr1/*` respondem. Desligada: o sistema fica **exatamente como hoje**, sem o item novo, e `/nr1/*` responde 404. Motivo: a Suzane ainda não aprovou o visual; o fluxo novo precisa poder subir em produção apagado. **Plano de release: ligar `NR1_SHELL_ENABLED=true` só quando a Suzane aprovar o visual** (entra na tabela de variáveis do «Plano de release» com valor seguro `false`).

**Arquitetura (Etapa A).** `App\Support\Nr1Shell\Nr1Shell` (perfis, matriz de seções por perfil = PERMISSIONS do HTML e a decisão `allows()`, a mesma para o menu e para a rota: esconder o item nunca é a única barreira), `Nr1ShellContext` (empresa e campanha em foco), `Http\Controllers\Nr1\ShellController`, middleware `nr1.shell` (404 com a flag desligada), `routes/nr1.php`, layout `Layouts/Nr1Layout.vue`, `Components/Nr1Shell/Nr1ContextBar.vue`, `Pages/Nr1/Section.vue`, tokens do HTML em `resources/css/nr1-shell.css` (variáveis `--n1-*` **escopadas em `.nr1-shell`**, sem tocar no tema do resto). Único compartilhado alterado: **`AdminLayout.vue` e `ClientLayout.vue`** (item «NR-1» condicionado ao prop compartilhado `nr1Shell.url`, que é `null` com a flag desligada; na `ClientLayout`, o item «Pesquisas NR1» só some com a flag ligada) e `HandleInertiaRequests` (prop `nr1Shell`). Com a flag desligada o efeito é nulo.

**Perfis e menu (contrato do HTML, aplicado no back-end):** Talents (todas menos Minha jornada; abre em Visão geral; escolhe empresa e campanha no cabeçalho, com busca); Empresa (Visão geral, Campanha NR-1, Resultados, Plano de ação, Documentos, Soluções; entra direto na própria empresa e **só vê campanha «Publicada»**); Comercial (Soluções e Oportunidades conforme a matriz; abre em Oportunidades); revisor externo (só Documentos, e só com a revisão externa ligada: a seção redireciona para `/revisao`, que mantém o MFA e o compartilhamento por versão); colaborador (Campanha NR-1 e Minha jornada, **sem login, pelo link da campanha** `/nr1/jornada/{token}`). O seletor de perfil do HTML **não** foi implementado.

**Telas atuais e para onde vão** (todas continuam existindo; o shell as encaixa por etapas):

| Seção do shell | Tela atual (Talents) | Tela atual (Empresa) | Etapa |
|---|---|---|---|
| Visão geral | `admin/companies/{c}/surveys/{s}/resultados-psicossociais` | `client/surveys/{s}/painel-psicossocial` | B |
| Campanha NR-1 | `.../campanha-nr1` | `client/surveys/{s}` (links por público, QR, janela) | B |
| Minha jornada | (link público `/pesquisa/{token}`) | (idem) | B |
| Resultados da pesquisa | `.../resultados-psicossociais` (+ Revisão interna) | `client/surveys/{s}/painel-psicossocial` (abas) | C |
| Plano de ação | `.../medidas` | `client/surveys/{s}/plano-de-acao` | C |
| Documentos | `.../documentos` (+ compartilhar com PASSEG) | `client/surveys/{s}/documentos` | D |
| Soluções Talents | `admin/solucoes-nr1` | `client/surveys/{s}/solucoes` | D |
| Oportunidades | `admin/oportunidades-nr1` (+ fila) | n/a | D |

**Estado da Etapa A (honesto):** o shell, a sidebar do protótipo, o cabeçalho com status e seletor, a política de acesso por perfil e o item de menu estão prontos. As seções ainda **não reproduzem o visual** do HTML: mostram o contexto, os quatro indicadores reais (Visão geral e Resultados, vindos do `PsychosocialDashboardBuilder`) e um botão para a tela atual. Embutir as telas antigas dentro do shell exigiria alterar o layout delas; por isso a Etapa A leva até elas por link e as Etapas B a D as refazem com componentes novos em `Components/Nr1Shell/*`, reaproveitando os mesmos serviços e policies.

**Seeder de demonstração (só local/testing, aborta fora disso; não commitado):** `database/seeders/Nr1DemoSeeder.php` (`php artisan db:seed --class=Nr1DemoSeeder`): empresa «Companhia do Livro (demo NR-1)», «Campanha 2026» publicada com as 40 respostas do Apêndice A, 48 elegíveis, índice 2,60, 6 de 7 em atenção, Produção 19 / Manual 6 / Outros Setores 15. Logins (senha `Demo-12345-xyz`): `demo-owner@talents.local` (Talents), `demo-rh@demo-nr1.local` (Empresa), `demo-comercial@talents.local` (Comercial; só `nr1_oportunidades`). Colaborador: `/nr1/jornada/{public_token da campanha}`.

**Testes:** `tests/Feature/Nr1ShellTest.php` (flag desligada = nada novo no menu e `/nr1/*` 404; ligada = item único e menu exato por perfil; rotas antigas respondem nos dois estados; Empresa sem acesso a Oportunidades, sem ver campanha não publicada e sem trocar de empresa pela URL; Comercial só Oportunidades; `company_user` sem grants recebe 403; revisor só Documentos; colaborador só as duas seções).

**Decisões do Suede após a Etapa A:** (1) **o destino do QR e dos links por público NÃO muda**: continua `/pesquisa/{token}`; `/nr1/jornada/{token}` é a «Minha jornada» do shell (mesma regra de abertura de `survey.public`: token inexistente 404, campanha inativa/não iniciada/encerrada mostram a tela «Closed»). (2) **Soluções para o Comercial segue estritamente a matriz do HTML, sem exceção** (Comercial vê Soluções só com a permissão `methodology` na matriz de admin, que não é concedida por padrão).

**Perguntas em aberto para o Suede (RESPONDIDAS acima):** (1) o colaborador anônimo entra por `/nr1/jornada/{token}`; o QR e os links por público hoje apontam para `/pesquisa/{token}`: trocar o destino do QR para o shell é decisão sua (afetaria quem já recebeu o link); (2) Comercial vê «Soluções» só se a matriz der `methodology`; quer conceder por padrão?

## Shell NR-1: Etapa B (Visão geral, Campanha NR-1, Minha jornada)

**Verificação da Etapa A (a pedido do Suede).** As mudanças de `Client/Dashboard.vue`, `Client/TeamVoice/Index.vue`, da regra de grupo mínimo (`ShellController`, `Nr1Layout.vue`), de `Nr1Shell::allows` (módulo `relatorios`), de `jornada/{token}` (mesma regra de `survey.public`), do `AdminLayout` (só o item antigo «Pesquisas» some com a flag), da prioridade do middleware em `bootstrap/app.php` e dos testes extras de `Nr1ShellTest` **não foram escritas por mim nesta janela**: apareceram entre 15:09 e 15:16, enquanto eu só rodava a suíte. Sinal de edição fora da aba 1. Revisei todas, estão coerentes e testadas, e as mantive. Estado das correções da aba 2: (1) mínimo de grupo em respostas/participação para Empresa e Colaborador: **feito**; (2) `jornada/{token}` espelha `survey.public`: **feito**; (3) Admin só esconde o item antigo de NR-1: **feito**; (4) permissão da Empresa por módulo do client: **feito**; (5) pasta lixo `C\uF03A` (`C:` com U+F03A): **não feito**: ela NÃO está vazia (contém `Users\...\Temp\junit.xml`, 369 KB); fica ignorada pelo git e **não foi removida** (aguarda a sua decisão); testes listados: **feitos** (17 em `Nr1ShellTest`). Flag: com `APP_ENV=production` sem a variável o valor é `false` (verificado), com `staging` também `false`, só `NR1_SHELL_ENABLED=true` liga; `.env.example` e `docker-compose*.yml` não definem a variável.

**Diretriz criativa aplicada.** Paletas separadas como tokens do shell (`nr1-shell-motion.css` e `nr1Theme.js`): (a) níveis Leve verde, Atenção âmbar, Elevado vermelho suave, sempre com ícone e texto (`LevelPill`); (b) dimensões e grupos na família roxo, azul, rosa e turquesa (sem vermelho). As 7 dimensões têm cor e ícone próprios (`DimensionBadge`). Componentes novos só em `Components/Nr1Shell/*`: `ScoreGauge` (medidor com as 3 faixas), `ProgressRing`, `CountUp`, `DimensionBars` (barras que crescem, ordenadas por índice, clicáveis, mostram as perguntas da dimensão com barra empilhada Nunca a Sempre e tooltip/aria), `GroupDonut` (rosca com legenda interativa), `EmptyState`, `ToastHost`/`useNr1Toast`, barra de carregamento e entrada animada entre seções. `prefers-reduced-motion` desliga contagens e animações. Sem biblioteca nova (heroicons já estava no projeto). Privacidade inalterada: os gráficos usam o payload do back-end (`Nr1ShellPageData`), que reaproveita `PsychosocialDashboardBuilder` (grupo mínimo e «Outros Setores» já aplicados) e não leva respondente, token nem departamento.

**Tabela de divergências com o HTML (Etapa B):**

| Ponto | HTML | Shell | Motivo |
|---|---|---|---|
| Seletor de perfil e rodapé «Protótipo V11» | existe | não existe; há seletor de empresa e campanha e «Voltar ao Talents» | diretriz do produto |
| Rótulos de faixa | leve / atenção / grave | Menor sinalização / Atenção / Maior sinalização | decisão já tomada (Caderno) |
| Pontos fortes | perguntas 7, 8 e 37 fixas | as 3 mais favoráveis dos dados reais (7, 8, 9 na demonstração) | dado real, nada fixo |
| Participação por grupo | Produção, Outros, Manual | ordenado por tamanho; rosca no lugar das barras | diretriz criativa |
| Próximas ações | «N dias sugeridos» | prazo em data (o back-end guarda a data) | dado real |
| «(exemplo)» nos elegíveis, «Coleta finalizada» | fixos | número real e estado real da campanha (aberta ou encerrada) | dado real |
| Orientação/treinamento da empresa | proposta simulada | botão real de «Solicitar proposta» (rota existente) | back-end existente |
| Minha jornada: texto do treinamento | texto fixo | escopo cadastrado pela Talents | dado real |
| Cabeçalho do colaborador | nome da empresa | sem empresa nem título | privacidade (regra de `survey.public`) |
| Ícones/cores por dimensão, medidor, anel, rosca | não existem | novos | diretriz criativa |

**Demonstração:** `Nr1DemoSeeder` agora também cria as 6 ações do plano, os links por público e o treinamento online contratado (Time e Líderes); colaborador: `/nr1/jornada/{token do link «time»}` (com links por público, o token da campanha em si deixa de abrir, como em `survey.public`).

## Shell NR-1: entrada e botões

**Prompts das 16:06 e 16:09: NÃO foram recebidos** pela janela 1 (só o adendo dos botões chegou, e depois a cobrança). Tudo abaixo foi feito a partir da descrição do Suede na cobrança; se algum detalhe dos prompts originais divergir, o Suede corrige.

**(a) Entrada do shell** (flag ligada; desligada, nada muda):
- Item «NR-1» **removido** do menu principal do Admin (arquivo voltou ao estado do HEAD) e da empresa. O prop compartilhado `nr1Shell.url` continua existindo só como destino de «Pesquisas NR1».
- **Empresa:** «Pesquisas NR1» restaurado e, com a flag ligada, abre o shell (`/nr1`); sem a flag abre a lista antiga.
- **Talents:** entra pelo bloco «Pesquisas e parecer» da página da empresa: nas pesquisas V11 aparece o botão **«Abrir painel NR-1»** (leva a `/nr1/visao-geral?empresa=&campanha=`) no lugar de «Parecer e plano», que fica oculto só nas V11 com a flag ligada; pesquisas antigas intactas. O controller só envia `nr1ShellUrls` quando a flag está ligada.
- **Seletor de empresa removido** do cabeçalho do shell (`Nr1ContextBar` apagado); o contexto vem da entrada (query `empresa`/`campanha`, lembrada na sessão).
- **Rodapé:** «← Voltar à empresa» (Talents com empresa em foco: página da empresa; Empresa: painel) ou «← Voltar ao Talents» (Comercial).
- **Proposta de entrada do Comercial (não implementada):** hoje não existe item de menu para Oportunidades NR-1 no Admin. Proposta: no grupo «Comercial» do menu do Admin, um item **«Oportunidades NR-1»**, visível para quem tem `nr1_oportunidades` (ver), que abre `/nr1/oportunidades` **somente com a flag ligada** (com a flag desligada o item não aparece, para o menu continuar idêntico ao atual). Aguarda o OK do Suede.

**(b) Botões: causa raiz do hover bugado.** As regras genéricas `.n1-btn:hover` e `.n1-btn.primary:hover:not(:disabled)` tinham especificidade MAIOR que as sobrescritas do hero (`.n1-hero .n1-btn.primary` e `.soft`), que só definiam a cor do texto para o repouso: no hover o fundo mudava (roxo escuro, ou lavanda) e o texto não acompanhava (roxo escuro sobre roxo escuro; branco sobre lavanda), então o rótulo sumia. Correção na raiz: **apagadas todas as regras `.n1-btn` e `.n1-back`** e criado um conjunto único, `Components/Nr1Shell/Nr1Button.vue` + `nr1Button.js` + `resources/css/nr1-shell-buttons.css`, em que cada variante define fundo, texto, borda e anel em todos os estados com a mesma especificidade.

Variantes: `primary`, `secondary`, `inverse` (claro sobre o hero), `ghost-on-dark` (translúcido escuro, nunca clareia o fundo), `ghost-on-light`, `link`, `icon`, `chip` (com `selected`); tamanhos `md`/`sm`; `block`. Estados: repouso, hover, `focus-visible` (anel 3 px, branco sobre fundo escuro), active, disabled, loading (`aria-busy`). Transição curta e `prefers-reduced-motion` desliga.

**Inventário (todos migrados para `Nr1Button`):**

| Tela | Rótulo | Tipo | Variante |
|---|---|---|---|
| Layout | ← Voltar à empresa / ao Talents | link | ghost-on-dark |
| Layout | ☰ Menu (mobile) | botão | secondary |
| Seção genérica | «Abrir…» (telas atuais) | link | primary |
| Visão geral | Acompanhar ações → | link Inertia | inverse (no hero) |
| Visão geral | Imprimir panorama ↓ | botão | ghost-on-dark (no hero) |
| Visão geral | Ver plano completo → | link Inertia | secondary sm |
| Campanha | Acessar orientação | botão | primary block |
| Campanha | Ver conteúdo / Detalhes | botão | primary sm |
| Campanha | Marcar contratado / Solicitar proposta / Proposta solicitada (desabilitado) | botão | secondary sm |
| Campanha | Salvar contratação (loading) / Cancelar / Atualizar / Fechar | botão | primary sm / secondary sm |
| Campanha | Copiar link da pesquisa | botão | primary block |
| Minha jornada | Ver orientações / Responder questionário → | botão / link | primary |
| Minha jornada | Acessar treinamento (externo) / Baixar material / Ver informações / Fechar | link / botão | secondary |

Elementos clicáveis que NÃO são botões e têm CSS próprio, revisados: itens do menu lateral (`.n1-nav`, ganhou `:focus-visible` branco e `:active`), cabeçalho de dimensão expansível (`DimensionBars`, é `<button>` com `aria-expanded`, hover e foco do navegador) e itens da legenda da rosca (`tabindex=0`, destaque no hover e no foco). Campos (busca, selects, checkboxes) são controles de formulário, fora do escopo.

**Verificação (Playwright, fora do repositório):** folha de contato com 16 combinações de variante e fundo (hero escuro, página clara, cartão) × 6 estados (repouso, hover, foco, pressionado, desabilitado, loading) simulados de verdade (mouse, teclado, `mouse.down`), com contraste do texto medido no navegador: **0 falhas** (a 1ª rodada achou `chip` selecionado pressionado com 1,31:1; corrigido). Texto em AA (4,5:1) em todos os estados habilitados; desabilitado é isento (WCAG 1.4.3). Capturas do hero real: repouso, hover e foco nos dois botões.

**Testes:** `tests/JavaScript/nr1Button.test.mjs` (4 testes no runner `node --test` que já existe; sem infraestrutura nova: o projeto não tem Vitest/Vue Test Utils) e `tests/Feature/Nr1ShellEntryTest.php` (entrada pela página da empresa, rótulos do rodapé, menus sem «NR-1» e sem seletor).

**Pasta lixo `C:` (U+F03A):** removida com autorização (caminho conferido, continha apenas `Users\SUEDGA~1\AppData\Local\Temp\junit.xml`, de 23/10 15:03). Causa: um comando de teste foi executado **dentro do contêiner Docker com caminho absoluto do Windows** em `--log-junit` (ex.: `C:\Users\...\Temp\junit.xml`); o Docker Desktop troca o `:` por U+F03A e cria a pasta no volume do projeto. Não há `logging`/`junit` em `phpunit.xml`, `composer.json` nem scripts do repositório: a causa não está no código e não alterei testes. Prevenção: usar caminho **relativo** (`storage/logs/junit-*.xml`) ao rodar via `docker compose exec`. A linha `C/` em `.git/info/exclude` permanece.

## Shell NR-1: Etapa C (Resultados da pesquisa e Plano de ação)

**Regra operacional (decisão do Suede):** nunca passar caminho absoluto do Windows em `--log-junit` ao rodar testes pelo contêiner (`docker compose exec app php artisan test --log-junit=C:\\Users\\...`): o Docker Desktop troca o `:` por U+F03A e cria a pasta lixo `C` + U+F03A dentro do projeto (aconteceu em 23/10 com `Temp\junit.xml`). **Usar sempre caminho relativo**, por exemplo `--log-junit=storage/logs/junit-nome.xml`. A pasta foi removida com autorização; a linha `C/` segue em `.git/info/exclude`.

**Menu do Comercial (aprovado e implementado):** item «Oportunidades NR-1» no grupo Comercial do menu do Admin, só para quem tem `nr1_oportunidades:view` e só com a flag `NR1_SHELL_ENABLED` ligada; abre `/nr1/oportunidades`. Prop compartilhado `nr1Shell.opportunitiesUrl` (nulo com a flag desligada, sem permissão e para a empresa). Testes: flag desligada = item ausente e rota 404; sem permissão = oculto e 403.

**Ajuste visual do cabeçalho do shell:** o prompt «enviado logo antes» **não chegou à janela 1**; nada foi feito além da remoção do seletor de empresa. Aguarda o texto do Suede.

**Resultados da pesquisa** (`ResultsView`, `QuestionsExplorer`, `SectorsPanel`, `InternalReviewPanel`): abas Dimensões / Setores / 39 perguntas com teclado (setas, Home, End), `role=tablist` e transição; Dimensões = barras clicáveis com as perguntas de cada dimensão + «Interpretação dos indicadores» com os rótulos adotados do Caderno (Menor sinalização 1,00–2,33 · Atenção 2,34–3,66 · Maior sinalização 3,67–5,00); Setores = «Onde estão os participantes» (cartões de grupo em cores da marca, nível em chip com ícone, 7 dimensões por grupo) e «Outros Setores, sem perder respostas»; 39 perguntas = filtros Todas / Ordem das perguntas / Mais favoráveis / Mais pontos de atenção, filtro por dimensão e busca, distribuição Nunca→Sempre em barra empilhada com tooltip e texto, «Ver todas as 39 perguntas»; **Revisão interna** (categorias, encaminhamento, anotações, histórico) **só para a consultoria** (payload `null` para a empresa), salvando pela rota existente (step-up de MFA).
- **Privacidade:** setores com menos que o grupo mínimo só aparecem dentro de «Outros Setores»; nome de setor pequeno, `department_id` e composição do grupo nunca vão no payload (teste com 3 setores de 4 respostas); abaixo do mínimo, Empresa não vê setores nem participação.
- Os rótulos de faixa dos setores usam o nível (`BAND_LABELS`), não o texto antigo do painel («Moderado/Grave/Leve»).

**Plano de ação** (`PlanView`, `MeasureCard`): contadores (Ações propostas / Responsáveis confirmados `0/6` no demo / Aguardando confirmação), cartões de medida com índice e faixa da dimensão, chips «Sugestão Talents» × «Confirmada pela empresa» e status (Proposta, Confirmada, Em andamento, Concluída, Replanejada), linha do tempo, responsável e prazo **sugeridos** (editáveis pela Talents até a publicação, rota `PATCH .../sugestao`), confirmação pela empresa (responsável do cadastro ou texto, prazo) e Iniciar/Concluir pelas rotas do client; «Ver impressão do plano» (impressão do navegador, com CSS de impressão). Evidências, eficácia e replanejamento seguem na tela completa do plano (link no rodapé). Nenhuma regra nova de back-end.

**Tabela de divergências com o HTML (Etapa C):**

| Ponto | HTML | Shell | Motivo |
|---|---|---|---|
| Rótulos de faixa | Leve / atenção / grave | Menor sinalização / Atenção / Maior sinalização | decisão do Caderno |
| Setores: texto «Reúne Costura, Diretoria, …» | lista os setores | não lista (composição oculta) | privacidade: nenhum setor isolado dentro de Outros Setores |
| Setores: «índices em processamento» | placeholder | índice e 7 dimensões reais por grupo | dado real |
| Revisão interna: categorias | 2 fixas | as do back-end: 2 fixas + 1 por dimensão em atenção, com checkbox | `InternalReviewService` existente |
| Revisão interna: salvar | feedback local | grava e pede o step-up de MFA | regra de segurança já implementada |
| 39 perguntas: cores | cartões simples | + cor/ícone da dimensão, ordenação animada | diretriz criativa |
| Plano: prazo em «dias» | número de dias | data (o back-end guarda a data) | dado real |
| Plano: andamento Planejada / Em andamento / Concluída | 3 valores, select | ciclo real (Proposta → Confirmada → Em andamento → Concluída/Replanejada) e botões | Caderno §11 / Etapa 6 |
| Plano: responsável | lista fixa + Outro | Talents: lista fixa + Outro (sugestão); Empresa: usuários da empresa ou texto | back-end existente |
| Impressão | página do protótipo | impressão do navegador do shell (sem menu) | sem arquivo novo |
| Seletor de perfil e seletor de empresa | existe / não existe | não existe | diretrizes do produto |

## Shell NR-1: Etapa L (legado integrado ao shell)

**Diretriz:** toda pesquisa NR-1 (V11 `talents39_v1` e as antigas: `production_v1` e as sem método) abre no painel NR-1. Nada é excluído: as telas e rotas antigas seguem valendo por URL direta; com a flag desligada nada muda. «Modelo anterior» = campanha que não usa o fluxo de revisão (`Survey::usesReviewFlow() === false`).

### Matriz: seção do shell × modelo da campanha

| Seção | V11 (`talents39_v1`) | Modelo anterior (`production_v1` e sem método) |
|---|---|---|
| **Visão geral** | KPIs, pontos fortes, dimensões, prioridades, próximas ações, grupos (Etapa B) | **Cabeçalho do laudo** (período, cenário de risco, mínimo por setor, última publicação, status) + indicador geral e dimensões do legado; aviso «Pesquisa no modelo anterior»; sem pontos fortes, sem plano V11 |
| **Campanha NR-1** | Orientação, treinamentos por público, 39 itens, links, elegíveis | Só o que existe: período, status da coleta, participação (respostas) e link público; aviso discreto; sem treinamentos, sem elegíveis |
| **Minha jornada** | Link do colaborador (`/nr1/jornada/{token}`) | Não existe (404, como hoje) |
| **Resultados da pesquisa** | Abas Dimensões / Setores / 39 perguntas + Revisão interna (Talents) | **Laudo 01 Diagnóstico**: o mesmo painel do legado (indicador geral, dimensões com radar, participação por setor, risco por setor, dimensões por setor, tabela de calor, detalhamento por pergunta, insights) + «Continuar para o parecer» (leva a Documentos); sem aba de 39 perguntas V11 nem Revisão interna; aviso de privacidade por setor (mín. por setor do legado) |
| **Plano de ação** | Medidas V11 (Etapa C) | Estado vazio com aviso: o plano do legado vive no parecer (Documentos); sem medidas V11 inventadas |
| **Documentos** | Central de documentos V11 (fluxo de revisão; Etapa D) | **Laudo 02 Parecer** (texto, Mia, anexos, relatórios automáticos por cenário, arquivos personalizados) + **03 Publicar** + **Documentos liberados** (Talents); Empresa: parecer e plano publicados, PDFs e anexos. Fluxo Rascunho → Publicado e o gate «só após publicação» idênticos aos de hoje |
| **Soluções Talents** | Regras dimensão → serviço (Etapa D) | Aviso «Pesquisa no modelo anterior», sem regras nem erro |
| **Oportunidades** | Fila comercial (Etapa D) | Aviso, sem dados |

Acesso, publicação e MFA: **idênticos ao legado**. O shell chama os mesmos controllers e as mesmas regras (`survey.published`, módulos do client, step-up) e nunca mostra à Empresa o que ainda não foi publicado.

### Componentes legados reaproveitáveis

| Componente / tela | Arquivo | Uso no shell |
|---|---|---|
| Painel de resultados (radar, barras, setores, tabela de calor, perguntas, insights) | `resources/js/Components/Nr1SurveyResultsPanel.vue` (já independente de layout) | Resultados (modelo anterior) |
| Laudo: cabeçalho, 01/02/03, Documentos liberados | `resources/js/Pages/Admin/ActionPlan/Edit.vue` (1175 linhas, layout + corpo) | extrair o corpo para `Components/Nr1Legacy/LaudoBody.vue`; a página antiga passa a usar o componente |
| Parecer/Mia | `Components/MiaNr1AdminPanel.vue` | dentro do corpo do laudo |
| Documentos liberados | `Components/PsychosocialDocumentsAdminPanel.vue` | dentro do corpo do laudo |
| Parecer e plano da empresa | `resources/js/Pages/Client/Surveys/ActionPlan.vue` | extrair o corpo para `Components/Nr1Legacy/ClientActionPlanBody.vue` |
| Resultados da empresa (IA, insights, painel) | `resources/js/Pages/Client/Surveys/Results.vue` (455 linhas) | extrair o corpo para `Components/Nr1Legacy/ClientResultsBody.vue` |
| Selo de status, dicas | `Components/SurveyStatusBadge.vue`, `Components/InfoTooltip.vue` | reaproveitados dentro dos corpos |

Dados: `ActionPlanAdminController::edit`, `Client\SurveyResultsController::show` e `Client\ActionPlanController::show` continuam sendo a fonte; o shell chama esses métodos e reaproveita as props (sem duplicar consulta ou regra).

### Etapa L: o que foi feito

- **Entrada:** com a flag ligada, TODA pesquisa da lista «Pesquisas e parecer» (página da empresa, Talents) ganha «Abrir painel NR-1» e o botão antigo «Parecer e plano» fica oculto (a tela segue por URL); «Pesquisas NR1» da Empresa abre o shell. O contexto do shell passou a incluir todas as campanhas da empresa; um seletor de CAMPANHA (nunca de empresa) aparece no cabeçalho quando há mais de uma.
- **Corpos sem layout (legado):** `Components/Nr1Legacy/LaudoBody.vue` (de `Admin/ActionPlan/Edit.vue`), `ClientResultsBody.vue` (de `Client/Surveys/Results.vue`) e `ClientActionPlanBody.vue` (de `Client/Surveys/ActionPlan.vue`). As páginas antigas seguem com o layout antigo, o cabeçalho e as ações de sempre e usam o corpo extraído. **Regressão: capturas das 5 telas antigas (Laudo 01, Laudo 02 Parecer, Laudo 03 Publicar, Resultados e Plano de ação da empresa) são idênticas, byte a byte (hash), antes e depois da extração.** O corpo do laudo só muda de comportamento com a prop `shellMode` (padrão desligada) e expõe `releaseExecutiveBrief`, `releasePgrSubsidy` e `openExportPdfModal` para a barra de ações do shell.
- **Dados:** `Nr1ShellLegacy` chama os MESMOS controllers das telas antigas (`ActionPlanAdminController::edit`, `Client\SurveyResultsController::show`, `Client\ActionPlanController::show`) e reaproveita as props (teste compara as props do laudo no shell com as da página antiga). Acesso: o shell exige os mesmos módulos das rotas antigas (Resultados: `pesquisas`; Plano/Parecer: `planos_acao`), o gate de publicação continua valendo e a empresa nunca recebe props do Talents (Mia do laudo, URLs de geração). Ações (publicar, liberar resumo/subsídio/dossiê, exportar) postam para as rotas antigas, com o step-up de MFA que elas já têm.
- **Tema:** `.nr1-theme` (`resources/css/nr1-theme.css`) aplica tokens, raio, sombra, linha, foco, transições e hover de cartão ao legado SÓ dentro do shell; os gráficos (radar, barras, tabela de calor) e suas cores de faixa ficam como são; `prefers-reduced-motion` desliga as transições.
- **Seções do modelo anterior:** Visão geral = cabeçalho do laudo + índice geral e dimensões; Campanha = período, coleta, respostas e link; Resultados = Laudo 01 completo; Documentos = Laudo 02/03 + Documentos liberados (Talents, com a barra de ações) ou parecer e plano publicados + relatórios (empresa); Plano de ação, Soluções e Oportunidades = aviso «Pesquisa no modelo anterior» sem erro.
- **Seeder:** `Nr1DemoSeeder` cria «Campanha 2025 (modelo anterior)» (COPSOQ, 36 respostas em 6 setores, mínimo por setor 1, resultados calculados) na empresa demo (empresa #9, campanha #7).

### PENDÊNCIA DE DECISÃO (Sued e Suzane): privacidade do legado antes de produção

A pesquisa antiga mostra resultados por setor com «Mín. por setor: 1» (a V11 usa grupo mínimo 5 com «Outros Setores»). **Não foi alterado**: o comportamento do legado é o de sempre. O shell sinaliza para a Empresa, no topo de Resultados do modelo anterior, que a regra de grupo mínimo da V11 não se aplica àquela campanha (e o próprio painel já mostra «Aguardando mínimo (1)»). **Decidir:** aplicar grupo mínimo 5 e supressão (e «Outros Setores») também ao legado antes de produção, ou manter, e com que base jurídica/LGPD. Com mínimo 1, um setor com 1 respondente expõe a resposta individual (risco de reidentificação).

### Divergências do legado no shell vs tela antiga

| Ponto | Tela antiga | Shell |
|---|---|---|
| Botões do topo (Cadastro técnico, Medidas, Liberar resumo/subsídio, Exportar; Recalcular, relatórios, JSON/CSV) | no cabeçalho da página | barra «Ações do laudo» em Documentos, e «Relatórios e exportações» / «Recalcular» em Resultados (Empresa) |
| Cabeçalho do laudo (período, cenário, mín. por setor, última publicação) | cartão no topo do laudo | Visão geral e cabeçalho do shell |
| Etapa 01 do stepper | dentro do laudo | seção Resultados; o stepper em Documentos tem só 02 e 03 |
| «Continuar para o parecer» | botão da etapa 01 | fim de Resultados, leva a Documentos |
| Estilo | tema do sistema | tema do shell (`.nr1-theme`); gráficos intactos |
| Ícones das dimensões | n/a | ícone neutro (os 7 ícones próprios são das dimensões da V11) |

## Shell NR-1: cabeçalho e confirmação da Etapa C

**Confirmação da Etapa C (V11):** Resultados com abas Dimensões / Setores / 39 perguntas e Revisão interna só para a consultoria: **feito**. Plano de ação com contadores, cartões de medida, responsável sugerido × confirmado, prazo sugerido editável (Talents até a publicação; confirmação pela empresa), status em chip, linha do tempo e impressão: **feito**. O que estava **parcial** e foi concluído agora: (1) capturas lado a lado reais (HTML à esquerda, shell à direita) em `captures-c/lado-a-lado`; (2) estado editável do plano capturado (campanha em «Em edição»: 12 campos editáveis); (3) impressão verificada com mídia `print` (menu e controles do cabeçalho somem). Testes: `Nr1ShellStageCTest` (4) mais o legado e o cabeçalho.

**Cabeçalho do shell (`ShellHeader`, `CompanyAvatar`, `CampaignSelect`, `StatusChip`, `nr1-shell-header.css`):** monograma quadrado arredondado (logo da empresa se existir, senão as iniciais sobre o gradiente da marca); «NR-1 · PESQUISA PSICOSSOCIAL» em caixa alta espaçada; nome da empresa 24 px, peso 800, roxo da marca, com reticências e tooltip; linha de contexto (respostas · campanha); fundo branco com faixa de 3 px de gradiente da marca e borda sutil. À direita: «← Voltar à empresa» (ghost-on-light), seletor de campanha com rótulo «Campanha», ícone e lista estilizada com a campanha ativa marcada (teclado: setas, Home, End, Enter, Esc; só leitura se houver uma campanha), chip de estado com ícone (Publicado verde, Em revisão âmbar, Em edição cinza; Ativa/Encerrada quando a campanha não exige publicação) e «Imprimir» ghost. Tudo pelas variantes do `Nr1Button`. Estado vem de `statusBadge` (só leitura: rodada de revisão e publicação). Colaborador: sem nome da empresa (título «Campanha NR-1»). Mobile: nome na 1ª linha, controles roláveis na 2ª, menu da sidebar por botão ☰ com `aria-expanded`. O botão de voltar saiu do rodapé da sidebar e foi para o topo. Contraste AA medido nos 3 tons do chip. `prefers-reduced-motion` desliga a animação da lista.

**Ordem das campanhas:** o padrão do shell passou a ser a campanha de coleta mais recente (por data de início), para a empresa não cair numa campanha antiga só por ter sido cadastrada depois.

## Interruptor do Laudo no shell (NR1_SHELL_LEGACY_EMBED) e decisão pendente

**Decisão do Sued:** não gostou do Laudo NR-1 («Parecer e plano») renderizado dentro do shell (Etapa L). Quer a tela do Laudo como era ANTES da Etapa L e **ainda vai decidir como o Laudo entra no fluxo** (aba, menu novo ou outro). **DECISÃO PENDENTE.**

**Interruptor:** `config('nr1.shell_legacy_embed')`, lido de `NR1_SHELL_LEGACY_EMBED`, **padrão `false` em todos os ambientes, inclusive o local**. Nada foi apagado: `Components/Nr1Legacy/*`, `LaudoBody`, `ClientResultsBody`, `ClientActionPlanBody`, o tema `.nr1-theme`, as rotas e os testes da Etapa L continuam no código.

- **Desligado (padrão):** (a) página da empresa (Talents): as pesquisas do modelo anterior voltam a mostrar «Parecer e plano», sem «Abrir painel NR-1»; as V11 seguem com «Abrir painel NR-1» e sem «Parecer e plano». «Pesquisas NR1» da Empresa: se a empresa só tem pesquisa antiga, o item volta a abrir a lista antiga; se tem V11, abre o shell. (b) Abrindo `/nr1` de uma campanha do modelo anterior por URL: só o aviso «Pesquisa no modelo anterior» e o botão `Nr1Button` «Abrir Laudo NR-1» (Talents) ou «Abrir resultados da pesquisa» (Empresa), que levam à tela antiga; **nenhum conteúdo legado é consultado nem renderizado no shell** (Resultados, Documentos, Visão geral e Campanha mostram o mesmo aviso). (c) O seletor de campanha do cabeçalho marca as antigas com «· modelo anterior» (a escolha leva ao aviso com o botão, nunca a uma tela vazia).
- **Ligado:** tudo volta a funcionar como na Etapa L.

**Telas antigas idênticas:** as 6 capturas (Laudo 01, Parecer, Publicar, Resultados e Plano de ação da empresa, mais a guarda 404 da V11) têm o MESMO hash das capturas feitas antes da Etapa L (`captures-legacy/before3` × `after-switch`): sem diferença. Nenhum estilo do shell vaza: o `.nr1-theme` e o CSS do shell só valem sob `.nr1-shell`, que só existe no `Nr1Layout`.

**Testes:** `Nr1ShellLegacyTest` cobre os dois modos (ligado = Etapa L; desligado = aviso e botão, sem vazamento de props do legado, seletor marcado, entradas e menu da empresa) e o padrão `false` da configuração; `Nr1ShellEntryTest` cobre a entrada nos dois modos.

## Shell NR-1: Etapa D (Documentos, Soluções Talents e Oportunidades da V11)

**Escopo:** só a V11. As pesquisas do modelo anterior seguem como o interruptor `NR1_SHELL_LEGACY_EMBED` define (padrão: Laudo antigo). Nada de lógica nova de negócio: `Nr1ShellStageD` chama os MESMOS controllers das telas atuais (`ReviewRoundAdminController::index`, `Client\DocumentsController::index`, `Client\SolutionsController::show`, `Nr1OpportunityController::index`) e as ações postam para as rotas atuais. Uma única mudança fora do shell: `ReviewRoundAdminController::back()` volta para o shell quando a requisição traz `_nr1_return` com um caminho interno `/nr1...` (qualquer outro destino é ignorado; sem o campo, a tela antiga de sempre).

**Verificação da campanha padrão (pedida pelo Sued):** Empresa = a mais recente PUBLICADA e visível por ela (por início da coleta); uma campanha mais nova ainda não publicada nunca é o padrão, não entra no seletor, não é aberta por `?campanha=` e o nome dela não vai no payload (teste com «Campanha mais nova SIGILOSA»). Talents = a mais recente por início da coleta.

**Documentos (`DocumentsView`, `ReviewStepper`, `ReviewTimeline`, `PublicationChecklist`):** «Central de documentos / Documentos oficiais» com os dois documentos e «Imprimir ↓» (abre o PDF congelado, com versão, identificador e data); bloco «Revisão do relatório técnico e plano de ação» com stepper (Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado; passos da PASSEG aparecem como «Não exigida» quando a revisão não é exigida), versão, ações da Talents por estado (Iniciar nova revisão, Gerar documentos, Enviar à PASSEG com revisor e prazo, Publicar com confirmação, exigência da PASSEG), checklist do que bloqueia, ciência e integração ao PGR, histórico em linha do tempo. **Publicar continua com o step-up de MFA** (teste: sem verificação recente vai ao desafio e nada é publicado). **Aprovar relatório e Solicitar alteração** (com parecer) são do revisor externo em `/revisao`: o shell explica isso e não oferece esses botões à Talents. Empresa: só a rodada publicada (eventos de publicação, ciência e PGR), registrar ciência e confirmar integração ao PGR (com evidência) conforme a permissão de sempre; antes de publicar a mensagem do HTML «Documento em revisão pela Talents.», sem expor estado.

**Soluções (`SolutionsView`):** os 3 serviços (01 Pesquisa Psicossocial «Serviço realizado»; 02 Avaliação Complementar e 03 Intervenção Organizacional «Sob proposta») e «Recomendações relacionadas aos resultados» (N frentes de atenção) das regras configuráveis; antes da publicação: «Recomendações disponíveis quando o relatório for publicado.»; a empresa só recebe regras ativas e REVISADAS e solicita conversa pela rota atual; Talents vê as ativas e consulta a oportunidade; Comercial só com a permissão `methodology` na matriz (sem exceção), sem campanha em foco, só cartões. Textos das soluções e das regras seguem provisórios (pendentes da Suzane).

**Oportunidades (`OpportunitiesView`, `OpportunityCard`):** KPIs (identificadas, em contato, a qualificar), «Fila comercial» em cartões (serviço, origem, empresa e campanha, estágio, solicitante, responsável, última atividade, próxima ação, histórico de contato), filtros por estágio e origem (os do controller atual), mudança de estágio, assumir e próxima ação pelas rotas atuais (permissão de edição; Perdido exige motivo), e quadro por estágio **apenas visual** (arrastar não existe no back-end). Empresa nunca chega (403); Comercial só com a permissão do módulo.

**Seeder de demonstração (só local):** campanha 2026 com a rodada 1 PUBLICADA e a rodada 2 em edição; «Campanha 2026 · Unidade Sul» (não publicada) com a rodada em revisão PASSEG (estado gravado direto, pois o envio real exige revisor cadastrado); fila com solicitações da empresa e sugestões, uma assumida e em contato com próxima ação.

**Divergências com o HTML (Etapa D):**

| Ponto | HTML | Shell | Motivo |
|---|---|---|---|
| Aprovar relatório / Solicitar alteração no mesmo painel | botões por perfil | informados como ação da PASSEG em `/revisao` | o revisor externo fica em `/revisao` (decisão) |
| «Imprimir» do relatório | imprime a página | abre o PDF congelado da versão | snapshots com hash |
| Histórico | frases fixas | eventos reais (autor, data, de → para) | back-end |
| Soluções: 6 recomendações fixas | 6 frentes | as regras ativas/revisadas, uma por dimensão em atenção | dado real |
| Fila: tabela | tabela simples | cartões com histórico e ações, mais quadro visual | diretriz criativa |
| «Qualificar» fictício | local | rotas reais com histórico e motivo de recusa | back-end |
| Quadro por estágio | n/a | só visual | sem arrastar no back-end |

**Pint (nota operacional):** a partir daqui o Pint roda só nos arquivos alterados. Dos ~25 controllers reformatados por engano, só `Client/RhidApiController.php` tinha diferença puramente de espaço e foi restaurado; os demais mudam importações/FQCN/chaves de construtor (sem lógica, mas não é só espaço) e aguardam a decisão do Sued.

## Shell NR-1: Etapa E (fechamento: comparação final, acessibilidade, bancada, privacidade, desempenho e release)

**Pint.** Dos 25 controllers reformatados por engano, 21 tinham só importações, nomes de classe completos e chaves de construtor e foram restaurados (`git restore`); `CompanyUserController` (tirou um `use ($company)` não usado) e `Concerns/ResolvesDesligamentoRoutes` e `ResolvesSuggestionRoutes` (removeram docblocks `@param mixed`) mudam algo além do combinado e **ficaram como estão, aguardando o Sued**. Depois da restauração a suíte completa passou (1266). **Atenção:** outros controllers (ClosedContracts, CommercialProcess, BankAccount, Receivable, PurposeMap x3) voltaram a aparecer reformatados por quem rodou o Pint em outra janela; não são desta janela e não foram tocados.

**Commits locais (sem push, sem PR):** `94cfacb` (interruptor do Laudo) e `314ea4a` (Etapa D). O primeiro commit não compila sozinho em teste isolado porque o campo `legacy` do contexto mora em `ShellController`, que entrou junto da Etapa D (o `git add -p` interativo não existe aqui). Ficaram fora: `docs/PRD.md`, `CLAUDE.md`, `docs/nr1-v11/`, os 5 docs deletados, o seeder de demonstração e os arquivos da landing/Hero.

**Comparação final com o HTML:** capturas desktop e mobile, lado a lado, em `captures-e/lado-a-lado` (scratchpad, fora do repositório): Visão geral (Talents e Empresa), Campanha, Minha jornada, Resultados (abas Dimensões, Setores, 39 perguntas e Revisão interna: `talents_resultados_aba*` e `*_revisao_*`), Plano de ação, Documentos (Talents e Empresa), Soluções, Oportunidades (Comercial). Perfis exercitados no navegador: Talents, Empresa, Comercial e Colaborador. **O revisor externo não é uma tela do shell** (fica em `/revisao`, desligado por `NR1_EXTERNAL_REVIEW_ENABLED=false`): coberto só por teste (`Nr1ShellTest`: só chega aos documentos e vai para a área de revisão), sem captura. Resultado da varredura: as 44 telas respondem como a matriz manda (403 onde deve: Empresa em Oportunidades, Comercial fora de Oportunidades sem `methodology`), **zero erros de console** (um 404 de script de PWA apareceu duas vezes no mobile e não repetiu), nenhum estouro horizontal em 390 px.

**Divergências finais (consolidadas; as tabelas por etapa acima continuam valendo):**

| Tela | Elemento | HTML | Sistema | Esforço | Decisão pendente de |
|---|---|---|---|---|---|
| Todas | seletor de perfil, rodapé «Protótipo» | existe | não existe; seletor de campanha e «Voltar ao Talents» | n/a | decidido |
| Visão geral / Resultados | rótulos de faixa | leve/atenção/grave | Menor sinalização / Atenção / Maior sinalização | n/a | decidido (Caderno) |
| Resultados | setor isolado | lista setores dentro de «Outros» | não lista (privacidade) | n/a | decidido |
| Resultados | Revisão interna | 2 categorias | 2 fixas + 1 por dimensão em atenção; salvar pede step-up | n/a | decidido |
| Plano de ação | prazo e andamento | dias; 3 estados | data; ciclo real Proposta a Concluída | n/a | decidido |
| Documentos | aprovar/solicitar alteração | botões no painel | só no `/revisao` do revisor | M (se quiserem no shell) | Sued (D11) |
| Documentos | «Imprimir» | imprime a página | abre o PDF congelado | P | Sued |
| Soluções | textos dos 3 serviços e das regras | fixos | provisórios, vindos das regras revisáveis | P | **Suzane** (pergunta 11) |
| Oportunidades | fila | tabela | cartões com histórico + quadro visual (sem arrastar) | G (arrastar exige back-end) | Sued |
| Legado (V10) | Laudo no shell | n/a | aviso + botão para o Laudo (`NR1_SHELL_LEGACY_EMBED=false`) | M | **Sued**: como o Laudo entra no fluxo (aba, menu novo ou outro) |
| Legado | grupo mínimo | n/a | do modelo antigo (1) quando embutido | M | **Sued e Suzane**: 1 ou 5 antes de produção |
| Revisor externo | tela | existe no HTML | `/revisao`, fora do shell | M | Sued |

**Acessibilidade (medida com script próprio no Edge/Playwright, sem leitor de tela).**
- *Estrutura:* 1 `h1` por página (antes eram 2: o título do cabeçalho virou `div`), `nav` rotulado, `aria-current="page"` no item do menu e `aria-current="step"` nos passos, `role=tablist/tab` com `aria-selected` e setas/Home/End nas abas de Resultados, nenhum controle sem nome acessível, nenhum `id` duplicado, nenhum SVG/imagem/canvas informativo sem texto alternativo.
- *Teclado:* Tab percorre menu, seletor de campanha, abas, stepper, cartões e formulários sem armadilha (11 a 41 paradas por tela; Oportunidades chega a 70 por causa de um formulário por cartão). Foco visível: anel de 3 px em `#7a3f94` (antes `#bd9ace`, 2,4:1, abaixo dos 3:1 de elemento não textual) agora também em `input`, `select`, `textarea`, checkbox e rádio. O detector acusou alguns `INPUT` sem anel em Oportunidades e no Plano da Empresa; verifiquei um campo de texto/data (anel presente, `:focus-visible` verdadeiro) e **os demais ficam para o teste manual**.
- *Contraste AA (texto pequeno 4,5:1; grande 3:1):* a varredura achou 8 padrões abaixo (rótulos «Confirmada» do histórico 3,04; setinha e «frentes de atenção» 4,12; número do serviço 1,41; textos de apoio 3,2 a 3,9; números de prioridade 3,1 a 3,9; passo concluído 4,22). **Corrigidos** em `nr1-shell-d.css` e nos tokens locais; repetida a varredura: 0 falhas nas telas auditadas. Medidas dos tokens: `--n1-mut` sobre lavanda 4,23 (ainda abaixo; usado só em fundo branco/`--n1-bg`, onde dá 4,87/4,53); teal, ouro e rosa **não** servem como cor de texto sobre branco (4,22/3,56/4,00) e o shell só os usa em ícones, barras e fundos.
- *Movimento:* `prefers-reduced-motion: reduce` zera as animações e transições do shell (a varredura em modo reduzido contou 0 elementos animados contra 8 a 57 sem a preferência).
- **Ainda exige teste manual por pessoa, com NVDA e VoiceOver:** leitura do stepper (passo atual e «Não exigida»), anúncio dos toasts e da barra de carregamento, foco ao abrir/fechar o seletor de campanha e a confirmação de «Publicar», gráficos (rosca, medidor, barras: leitura em tabela alternativa), os `INPUT` citados acima, ordem de leitura no mobile com o menu aberto, e o contraste dos textos sobre degradê (o script ignora fundos com gradiente, como o herói roxo).

**Bancada completa (uma suíte por vez).** SQLite: 1266 (depois da restauração do Pint) e **rodada final, com os 4 testes novos de privacidade: 1275 testes, 13.988 asserções, 0 falhas** (o total inclui testes que a aba 3 acrescentou). PostgreSQL (`talents_test_pg`, harness fora do repositório): **1271 testes, 13.808 asserções, OK**. `npm run build` ok. `npm run test:js`: **115/115**.

**Privacidade e acesso.** Já cobertos por testes existentes (todos verdes nos dois bancos): grupo mínimo 5 com piso e supressão em cascata (`Nr1Stage0PrivacyTest`), Outros Setores sem nome de setor pequeno (`Nr1ShellStageCTest`), exportação da empresa só agregada e sem linhas, exportação bruta só interna/auditada/com permissão, gate de publicação e step-up de MFA (`Nr1ShellStageDTest`), isolamento entre empresas, revisor externo e Comercial limitado. **Novo:** `Nr1ShellPrivacySweepTest` (4 testes) varre o payload Inertia de TODAS as seções: Empresa (nada de outra empresa, nenhum token alheio, nenhum campo de linha: `answers`, `respondent_`, `email`, `cpf`, `raw_export`, `ip_address`...), Colaborador (jornada sem resultados, setores, medidas, documentos, oportunidades) e Comercial (fila sim; resultados, plano, documentos, campanha, visão geral e soluções 403). Achado documentado, não é vazamento: a tela Campanha mostra à empresa o link público da própria campanha (é o que ela distribui; a tela antiga faz o mesmo).

**Desempenho** (PostgreSQL de teste, campanha sintética de 3.000 respondentes x 39 perguntas = 117 mil respostas, 14 setores desiguais, 300 oportunidades; gerada só em `talents_test_pg`, descartada ao fim):

| Tela | Talents | Empresa | Consultas |
|---|---|---|---|
| Visão geral | 3,5 s (1ª requisição, painel frio) / depois ~0,2 s | 0,19 s | 44 a 50 |
| Campanha | 0,08 s | 0,06 s | 23 a 29 |
| Resultados | 0,18 s | 0,24 s | 53 a 58 |
| Plano de ação | 0,09 s | 0,10 s | 30 a 37 |
| Documentos | 0,09 s | 0,06 s | 19 a 23 |
| Soluções | 0,11 s | 0,12 s | 31 a 41 |
| Oportunidades (300) | 0,32 s | 403 | 21 |

Nenhuma consulta repetida 10 vezes ou mais (sem N+1) e a mais lenta leva 46 ms. Pontos de atenção: o cálculo de resultados (`recalculate`) leva ~12 s para 3.000 respondentes (roda no fechamento, não nas telas) e a primeira Visão geral depois de recalcular paga o painel frio; Oportunidades carrega as 300 de uma vez (sem paginação): aceitável, mas vale paginar se a fila passar de alguns milhares.

### Rodada pós-Etapa E (decisões do Sued)

- **Pint:** restaurados `CompanyUserController`, `Concerns/ResolvesDesligamentoRoutes` e `Concerns/ResolvesSuggestionRoutes`. Seguem reformatados por outra janela, **não tocados**: `Admin/ClosedContractsController`, `Admin/Commercial/CommercialProcessController`, `Admin/Finance/BankAccountController`, `Admin/Finance/ReceivableController`, `Admin/PurposeMapCampaignController`, `Client/PurposeMapCampaignController`, `PurposeMap/PublicPurposeMapController` (`git diff -w --stat` vazio e só linhas `use`/em branco mudam: ordem de importação).
- **`.env.example`:** todas as variáveis `NR1_*` e `MFA_*` lidas por `config/nr1.php` e `config/mfa.php`, com padrão seguro (interruptores desligados, proteções ligadas) e um comentário por linha. `tests/Unit/EnvExampleCoversNr1AndMfaTest` falha se uma variável nova faltar no exemplo.
- **Oportunidades paginada no servidor:** `Nr1OpportunityController::queuePage()` (25 por página, parâmetro `pagina`, mesmos filtros e ordenação de `index()`, mais `id` como desempate); KPIs sobre o total e `stageCounts` (total por estágio, só pelo filtro de origem) para o cabeçalho do quadro, que mostra os cartões da página atual. `index()` (tela antiga) continua com o corte de 200. Empresa segue 403.
- **Recálculo lento, investigação (nada implementado; aguarda OK):** ver a proposta no relatório do chat; números medidos no `talents_test_pg` com 3.000 respondentes (117 mil respostas): `recalculate` 11,3 s, 144 consultas, **só 0,4 s em SQL (4%)**, pico de 217 MB, ou seja, custo em PHP (hidratar 117 mil `SurveyAnswer` e varrer coleções por grupo, ~120 inserts de resultado). A «primeira Visão geral» de 3,3 s é praticamente a partida a frio do processo de teste (2ª requisição 0,15 s; Resultados 0,16 s). O ponto crítico real é que `PublicSurveyController::submit` chama `recalculate` **a cada resposta enviada, de forma síncrona**: a resposta de número N espera um recálculo O(N) e envios simultâneos disputam a transação que apaga e regrava os resultados.

### Plano de release: atualização da Etapa E (complementa a seção «Plano de release»)

- **Migrations:** as mesmas 19 (todas aditivas, todas com `down()`). Reversíveis de fato: 15; reversíveis só enquanto não há dado novo: 2, 8 e 11 (recusam); **`2026_10_28_130000_widen_psychosocial_measures_origin` tem `down()` vazio de propósito** (voltar para 16 caracteres truncaria valores): o rollback dessa é por restauração do dump. **O shell não adiciona migration nenhuma.**
- **Variáveis de ambiente novas do shell** (nenhuma está no `.env.example`; acrescentar comentadas): `NR1_SHELL_ENABLED` (padrão `false` fora do `local`; **produção `false`** até a Suzane aprovar o visual) e `NR1_SHELL_LEGACY_EMBED` (padrão `false` em todo ambiente; **produção `false`** até a decisão do Sued sobre o Laudo e a privacidade do legado). Já listadas na seção anterior: `NR1_EXTERNAL_REVIEW_ENABLED=false`, `NR1_EXTERNAL_REVIEW_MFA_REQUIRED=true`, `MFA_*`, `NR1_SOLUTIONS_REQUIRE_REVIEW=true`, `NR1_DUPLICATE_POLICY`, `NR1_CLIENT_DRAFT`. `LANDING_HERO_GLASS` é da landing (outra janela) e fora deste escopo.
- **Ordem de deploy:** (1) congelar e conferir `origin/main` incluído; (2) homologação com cópia do banco: `migrate --pretend`, `migrate`, `nr1:import-instrument-v11`, permissões na Equipe (`nr1_oportunidades` para o Comercial, sem backfill); (3) dump do PostgreSQL e snapshot de `storage/app`; (4) janela curta: `down`, imagem nova (composer sem dev, `npm run build`), `migrate --force`, `nr1:import-instrument-v11`, `optimize:clear` e recache, `up`; (5) smoke test; (6) **só depois** ligar `NR1_SHELL_ENABLED=true` para um grupo piloto (Talents primeiro), observar 24 h e então as empresas. O shell desligado não muda nenhuma rota, menu ou tela atual (teste: «flag desligada» em `Nr1ShellTest` e `Nr1ShellEntryTest`), então o merge pode ir antes de ligar a flag.
- **Flags e padrões:** `NR1_SHELL_ENABLED=false` (produção), `NR1_SHELL_LEGACY_EMBED=false`, `NR1_EXTERNAL_REVIEW_ENABLED=false`, `MFA_REQUIRED_AT_LOGIN=false`, `MFA_STEP_UP_REQUIRED=true`.
- **Precisa de validação humana antes de produção:** leitor de tela (NVDA/VoiceOver) nas telas do shell; teste manual do MFA com autenticador real e do revisor externo; recálculo e impressão com uma campanha real; passada visual do tema de uma empresa com cores próprias; leitura das regras e textos de Soluções (Suzane); homologação técnica do instrumento V11 (`nr1:accept-polarity` só depois dela); envio de e-mail com SMTP real.
- **Bloqueios de produção:** SMTP configurado e testado; Redis de pé (MFA falha fechado); `APP_KEY` imutável; Suzane aprovando o visual do shell e os textos provisórios; decisão da privacidade do legado antes de ligar `NR1_SHELL_LEGACY_EMBED`.
- **Pendências de decisão do Sued:** como o Laudo entra no fluxo do shell (aba, menu novo ou outro); privacidade do legado no shell (grupo mínimo 1 ou 5); os 3 controllers com mudança além de formatação (`CompanyUserController`, `Concerns/ResolvesDesligamentoRoutes`, `ResolvesSuggestionRoutes`); aprovar/solicitar alteração no shell ou só em `/revisao`; quem é a PASSEG (D11); «Imprimir» como PDF congelado; paginar a fila de Oportunidades; acrescentar as variáveis ao `.env.example`; autorizar o commit do trabalho restante e a atualização do PR #117.
- **Pendências da Suzane:** textos dos 3 serviços e das 6 regras de Soluções (pergunta 11); homologação técnica do instrumento V11; privacidade do legado (com o Sued); aprovação do visual do shell (cores, ícones, movimento).

## Documentação defasada

- `CLAUDE.md` diz que a matriz granular de permissões admin foi removida; ela segue em uso (`AdminPermissionModule`, `EnsureAdminPermission`, `AdminUserPermission`). Correção proposta no chat; o arquivo não foi editado.
