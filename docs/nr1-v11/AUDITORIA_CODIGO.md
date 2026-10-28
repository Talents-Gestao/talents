# Auditoria de código — módulo NR-1 (base para a V11)

Somente leitura. Nenhum código de aplicação foi alterado. Gerado em 23/10/2026 a partir da working tree de `feature/redesign-visual`, que contém o mesmo NR-1 de `new-features` (ver §0). O banco consultado é o local de desenvolvimento (Docker), não produção.

## 0. Branch e estado das migrations

- `feature/nr1-riscos-psicossociais` **é ancestral** de `new-features` (`git merge-base --is-ancestor` confirmou). As fases 1–11 do PRD estão em `new-features` (último commit `564ab83`).
- `new-features` é ancestral de `feature/redesign-visual`; esta tem 1 commit a mais (`4ef15fd`, tokens do redesign) e mudanças não commitadas em `app.css`, `app.blade.php`, `vite.config.js` e `resources/fonts/`.
- Branch `feature/nr1-v11` **criada a partir de `new-features`, sem trocar de branch** (`git branch`, working tree intocada).
- Migrations NR-1 aplicadas no banco local (`migrate:status`, todas "Ran"):
  - `2026_10_13_154500_rename_nr1_insight_risk_labels`
  - `2026_10_20_150000_create_survey_method_and_instrument_versions` (lote 74)
  - `2026_10_20_160000_create_survey_campaign_registrations_table` (75)
  - `2026_10_20_170000_create_psychosocial_measures_tables` (76)
  - `2026_10_20_180000_create_psychosocial_documents_tables` (77)
  - Anteriores: `create_survey_nr1_reports_table`, `add_answers_reconstructed_at_to_surveys`, `add_technical_opinion*_to_action_plans`.
- Os arquivos do Caderno **ainda não existem** em `docs/nr1-v11/` no disco (só este arquivo). O GAP_ANALYSIS depende deles.

## 1. Motor de cálculo

| Peça | Arquivo | Fato verificado |
|---|---|---|
| Config | `config/nr1.php` | Likert 1–5; cortes `green_max=2.33`, `yellow_max=3.66`; rótulos `Leve/Moderado/Grave`; benchmarks por segmento. |
| Inversão | `Nr1Scoring::effectiveLikertValue` | `reverse_score ? 6 − x : x`. Maior = mais risco. |
| Faixa (legado) | `Nr1Scoring::riskLevel` | `≤2,33` green; `≤3,66` yellow; senão red, sobre o valor **bruto**. Rótulo vem de `config('nr1.risk_labels')`. |
| Método versionado | tabela `survey_method_versions` | `production_v1` (média das médias por respondente, peso por item, faixa sobre valor bruto) e `talents39_v1` (`pooled_item_mean`, peso igual, `half_up`, 2 casas, faixa **após** arredondar). Ambos com `min_group_size_new_outputs = 5`. |
| Índice agrupado | `Nr1PooledItemScore` | Aritmética inteira: `intdiv(200·num + den, 2·den)` → centésimos. Faixa por centésimos (`≤233`, `≤366`). Exemplo do critério de aceite: 4052/1560 → 260 → "2,60", faixa média. |
| Cálculo | `SurveyResultCalculator::recalculate` | Apaga e regrava `survey_results` (geral, por seção, por setor, por setor×seção). Usa `talents39_v1` só se `surveys.method_version_id` aponta para ele; senão `production_v1`. Guarda `score_numerator`/`score_denominator`. |
| Congelamento | `Nr1InstrumentMatrix`, `survey_instrument_versions` | `item_matrix` (JSON) + `item_matrix_sha256` por campanha. Código de instrumento `talents39` previsto na constante, mas nunca atribuído (só `copsoq_talents`, `hse_it`, `custom`). |

**Faixas hoje.** Os rótulos "Leve/Moderado/Grave" estão em **três lugares independentes**: `config/nr1.php`, `PsychosocialDashboardBuilder::BAND_LABELS` e `::LEGENDS`, mais views/Vue (`Nr1RiskLevelLegend.vue`, `nr1_risk_legend.blade.php`) e a migration que renomeou rótulos de insights. `survey_results.risk_level` guarda `green|yellow|red`, então trocar só o texto de exibição **não mexe em dado gravado**. Os snapshots de documentos já emitidos guardam o rótulo gravado no momento; renomear a constante não os altera, mas qualquer view que releia o rótulo ao imprimir precisa ser conferida.

**Instrumento de 39 itens: não existe no código nem no banco.**
- `TalentsSeeder::seedNr1Questions` cria o template "NR-1 / COPSOQ (modelo Talents)": **21 itens em 7 seções** (Exigências do trabalho; Organização do trabalho; Relações interpessoais e liderança; Compensação e reconhecimento; Interface trabalho-vida; Saúde e bem-estar; Assédio e violência).
- `HseItQuestionData` cria o "HSE-IT": **35 itens em 7 dimensões**, com escala mista frequência/concordância; a própria matriz marca a polaridade do HSE-IT como `pending_audit` ("maior = melhor", oposta a `Nr1Scoring`).
- Banco local: templates 1 (21 itens/7 seções) e 2 (35 itens/7 seções). Há 1 campanha apontando para `talents39_v1` e 4 para `production_v1`. Nenhum template tem 39 itens.
- O teste `Nr1Talents39CalculationPhaseSixTest` monta 39 itens em 3 dimensões na hora, só para validar a conta (1560 respostas = 39 × 40).
- **Consequência:** a listagem dos 39 itens com dimensão e polaridade **não pode ser extraída do código**. Ela virá do Apêndice A do Caderno e precisará ser **criada** (template novo + `reverse_score` por item), não conferida contra um seeder.

## 2. Anonimato e coleta

**Tabelas** (`2026_04_01_210141_create_talents_platform_tables`):
- `survey_responses`: `id`, `survey_id`, `session_token` (string 64, indexado), `department_id`, `age_range`, `tenure_range`, `completed_at` (timestamp com hora completa), timestamps.
- `survey_answers`: `survey_response_id`, `survey_template_question_id`, `value`; único por (resposta, pergunta).
- Não há `user_id`, e-mail, IP nem identificador direto. `session_token` é `Str::random(40)` gerado no envio, sem ligação com pessoa.

**Fluxo público** (`Survey/PublicSurveyController`, rota `survey.public*`, throttle `public-survey-submit`):
- Link único por campanha (`surveys.public_token`), igual para todos. **Não há token por respondente, QR, rascunho nem prevenção de envio duplicado**: qualquer pessoa com o link pode enviar quantas vezes quiser (só o rate limit).
- Janela de abertura existe: `publicParticipationClosureReason()` devolve `inactive|not_started|ended`.
- Escalas de rótulo fixas no controller: frequência Nunca/Raramente/Às vezes/Frequentemente/Sempre; concordância de 5 pontos.
- Setor obrigatório quando a empresa tem departamentos; faixa etária e tempo de casa opcionais (strings livres até 32 caracteres).
- **Não há conceito de público (Time × Líderes)** na campanha; só um template por campanha.

**Riscos de reidentificação (fatos):**
1. `completed_at` guarda data e hora exatas. **Não é "coarsened".**
2. `ExportController::csv` e `::json` (rotas `surveys/{survey}/export/csv|json`) exportam **linha a linha** com setor + faixa etária + tempo de casa + cada resposta, **sem nenhum mínimo de grupo**. Um setor com 1–2 pessoas é identificável. Está no mesmo grupo de rotas do painel e não passa pelo filtro do painel novo.
3. `SurveyResultCalculator` usa `$min = 1` para gravar resultados por setor. A supressão acontece só na leitura do painel novo.
4. `surveys.min_responses_for_breakdown` é **forçado a 1** (`SurveyController` valida `max:1` e grava `1`; seeder usa 1). O campo existe mas não protege nada. O mínimo real (5) vem de `survey_method_versions.min_group_size_new_outputs`.
5. `Nr1AiAnalyzer::buildAggregatedPayload` envia ao provedor de IA o resumo por setor e setor×dimensão (`by_department_summary`, `by_department_and_dimension`). **Não confirmei** se aplica algum mínimo antes de enviar (ler a fundo antes da etapa 2).

**Proteção existente (painel novo):** `PsychosocialDashboardBuilder::publishableSectorIds` faz supressão em cascata: oculta setores com n < mínimo e, enquanto a soma dos ocultos for < mínimo, oculta também o menor setor visível, para impedir recuperação por subtração. O painel não usa idade nem tempo de casa. O CSV/JSON do painel novo (`exportRows`) exporta só linhas já publicáveis. **Não existe agrupamento "Outros Setores"**: setores suprimidos somem, sem linha agregada recalculada sobre as respostas originais. **Não há proteção contra combinação de filtros** porque o painel novo não tem filtros por idade/tempo.

## 3. Painel, itens e adesão

- `PsychosocialDashboardBuilder::build` lê só `survey_results` (nunca recalcula). Entrega: geral, dimensões, setores publicáveis, itens.
- **Itens:** `items()` devolve, por pergunta, `section_title`, `body`, `n` e `quality_alert` (n ≠ respondentes). **Não calcula média por item, nem % favorável, nem busca/filtro/ordenação**; o front não tem os "39 cartões".
- **Pontos fortes / dimensões em atenção:** não há seleção nem ordenação por faixa no builder. As dimensões saem na ordem de `survey_results`.
- **Adesão:** `SurveyCampaignRegistration.eligible_count` (+ `eligible_source`), preenchido no cadastro técnico do Admin (`SurveyCampaignRegistrationAdminController`). Cartão tem estados `missing/inconsistent/ok`; o rótulo é "X de Y". **Não calcula percentual.**
- `survey_campaign_registrations` guarda `legal_name_snapshot` e `cnpj_snapshot` (campos que a V11 quer bloquear quando vazios; hoje a emissão exige o mínimo conforme `assertReleasable` — não li cada builder).
- Auditoria de acesso: `PsychosocialAccessLog` (usado em `logAccess` do painel e no download). **Não há trilha antes/depois com justificativa** para o restante.

## 4. Perfis e permissões

- `UserRole`: `super_admin`, `company_admin`, `company_user`. **Só isso.** `users.is_owner` (bypass Talents) e `users.is_commercial` (flag) existem.
- `PermissionModule` (cliente): `pesquisas`, `planos_acao`, `relatorios`, `usuarios`… 17 módulos. Ações (`PermissionAction`): `view|create|edit|delete|validate`. `User::canAccess`: owner passa; inativo, sem módulo habilitado na empresa → negado; `company_admin` total; `company_user` consulta a tabela `user_permissions`.
- `AdminPermissionModule`: 40+ módulos de tela (comercial, financeiro, RHID…). **Atenção:** o CLAUDE.md diz que a matriz admin foi removida, mas o código mostra `drop_admin_user_permissions_table` (31/07) seguida de `recreate_admin_user_permissions_table` (21/08) e `expand_admin_permission_modules_to_screens` (25/08). Hoje a matriz admin **existe** e `canAccessAdmin` a usa; owner Talents tem acesso master. O CLAUDE.md está defasado nesse ponto.
- `can.module:relatorios,validate` protege `validar` (cliente). Painel: `canValidate` exclui super_admin e owner.
- **Como encaixar novos perfis (proposta, a validar):**
  - **PASSEG (externo):** não cabe em `UserRole`. Opções: novo valor em `UserRole` (`passeg_reviewer`) com escopo por empresa/campanha **compartilhada**, ou usuário `company_user` com permissão nova de módulo. O primeiro é mais seguro (não herda nada de `company_admin`). Exige tabela de compartilhamento (campanha × usuário × documento) para "vê só o que for compartilhado".
  - **Comercial limitado:** já há `is_commercial` e módulos `Comercial*`. Falta um módulo/ação novo (ex.: `nr1_oportunidades`) e um serializer que só devolva metadados + agregados.
  - **Colaborador por público:** hoje o colaborador responde via link público, sem login. "Time × Líderes" exigiria `audience` na campanha (ou duas campanhas) e token por público. Colaborador nunca vê resultados: já é verdade (sem login), mas deve virar teste.
- `Ensure*CompanySelected` e `contextCompany()` fazem o isolamento por empresa; `ExportController` e o painel conferem `survey.company_id`. **Não rodei testes IDOR**; só vi os `abort_unless`.

## 5. Documentos, liberação e validação

**Tipos** (`PsychosocialDocumentService::TYPES`, `PsychosocialDocument`): `executive_brief` (RE), `pgr_subsidy` (SUB, "Subsídio AEP/PGR"), `evidence_dossier` (DOS, dossiê com anexos). Além disso o legado em `ReportController`: `executive`, `technical`, `referral` (encaminhamento) e `action-plan` (+ parecer técnico em arquivo, `ActionPlanController::downloadTechnicalOpinionFile`).

**Ciclo atual:**
1. `release()`: o builder valida (`assertReleasable`), monta o snapshot, grava versão `released` com `snapshot_sha256`, `content_sha256`, `method_version_id`, `instrument_version_id`, `seal = pending_validation`; gera o PDF **uma vez** do snapshot (`printPdf`); `download()` nunca regenera.
2. `validateInSystem` (cliente, declaração fixa) ou `validateInformed` (Admin, com comprovante + sha256): cria versão filha `validated` com o mesmo snapshot (`parent_version_id`). Cada versão é uma linha nova; as anteriores ficam.
3. `isValidatable`: só versão `released` sem filha.

**Comparação com a V11:** **não há** "enviado à PASSEG", "ajustes solicitados", "aprovado" nem "publicado". O que existe é liberar → validar (pelo cliente). A visibilidade para a empresa **não depende de "Publicado"**: o painel e os downloads ficam acessíveis conforme `relatorios`/`pesquisas`; não confirmei no código se existe bloqueio de resultados antes de qualquer liberação. Imutabilidade de snapshot/hash já existe e é a base certa para "versão imutável".

## 6. Medidas (plano de ação)

- `PsychosocialMeasure`: `factor_label`, `evidence_basis` (`questionnaire_indication|validated_factor`), `measure_description`, `responsible_text` (texto livre), `suggested_due_date`, `sst_priority`, `evidence_text`, `status`, `implemented_at/by`, `effectiveness_note`, `effectiveness_checked_at`, `origin` (`manual|engine_suggestion`), `sort_order`; histórico em `PsychosocialMeasureEvent`.
- Status: `to_approve` (A aprovar) → `pending` (Pendente) → `in_progress` (Em andamento) → `implemented` (Implantada). Há CHECK e validação em `saving`; `implemented` exige data e usuário.
- **Mapeamento proposto à V11 (a validar com o Caderno):** `to_approve` ≈ Proposta; `pending` ≈ Confirmada; `in_progress` = Em andamento; `implemented` ≈ Concluída. **"Replanejada" não existe.** Responsável/prazo **sugerido × confirmado** não existem (só `responsible_text` e `suggested_due_date`).
- Também existe o plano de ação legado (`action_plans`, `ActionPlanController`, `ActionPlanAdminController`), com parecer técnico em arquivo. Os dois convivem.

## 7. Comercial (base possível para CRM de oportunidades)

- **Leads:** `LandingInterestSubmission` (`name`, `email`, `phone`, `company`, `message`, `admin_notes`, `is_qualified`, `source`, `assigned_to`, `created_by`). Rotas `admin/interessados-landing`, módulo `landing_interest`. Sem estágio de funil, sem vínculo com empresa cliente nem com campanha NR-1.
- **Propostas:** `CommercialProposal` com `list_status` (`open|closed|ended`, normalizado por `ProposalListStatus`), `is_closed`, `lost_reason`, `contacted_at`, `seller_id`, serviços marcados (`svc_nr1`, `svc_nr1_implantacao_modo` = `online|presencial`, `svc_palestras`, `svc_contratacao`…), totais em centavos, comissão. Fluxos: PDF, contrato (ZapSign), conversão em venda.
- **Gap para o CRM da V11** (Novo / A qualificar / Em contato / Proposta enviada / Ganho / Perdido): a proposta tem só 3 estados; o lead tem só `is_qualified`. Reaproveitar a proposta como "oportunidade" exigiria estágios novos e um vínculo com `surveys`/dimensão. **Não há bloqueio de duplicidade** de proposta por empresa/campanha.
- `Training`/Capacitação aparece como módulo (`AdminPermissionModule::Training`, `PermissionModule::Capacitacao`, "tela oculta"). **Não li** o conteúdo; não sei se serve ao "Treinamento Online/Presencial por público".

## 8. Testes existentes úteis para a V11

`Nr1MethodVersionsPhaseOneTest`, `Nr1CampaignRegistrationPhaseTwoTest`, `PsychosocialMeasuresPhaseThreeTest`, `PsychosocialDocumentsPhaseFourTest`, `PsychosocialDashboardPhaseFiveTest`, `Nr1Talents39CalculationPhaseSixTest` (já usa 40 respondentes × 39 itens), `Nr1ScoringTest`, helper `tests/Support/SeedsNr1SurveyResults.php`. **Não rodei a suíte** nesta fase.

## 9. O que NÃO consegui confirmar no código

1. Os 39 itens, dimensões e polaridade (não existem; dependem do Apêndice A, que ainda não está no disco).
2. Se `Nr1AiAnalyzer` aplica mínimo de grupo antes de enviar agregados por setor ao provedor de IA.
3. Se há bloqueio de acesso da empresa a resultados/documentos antes de uma liberação (qualquer rota além de `relatorios`/`pesquisas`).
4. O que cada `*SnapshotBuilder::assertReleasable` exige exatamente (campos obrigatórios, "CNPJ não informado").
5. Se `ReportController` (executivo, técnico, encaminhamento, plano) tem snapshot/hash ou é gerado ao vivo a cada download.
6. Isolamento entre empresas: só vi checagens de `company_id`; não executei testes IDOR.
7. O conteúdo do módulo Capacitação e se há qualquer fluxo de "solicitar proposta".
8. Em produção: quantas campanhas usam `talents39_v1` e se há documentos já emitidos (só consultei o banco local; qualquer migration nova precisa respeitar isso).
9. Comportamento do front (`PsychosocialDashboard.vue`, `Nr1SurveyResultsPanel.vue`) além dos trechos de texto; não abri os componentes.

> A §10 abaixo resolve esses nove itens (exceto onde diz "não confirmável").

## 10. Complemento — itens da §9 resolvidos (23/10/2026)

| # | Item | Resultado |
|---|---|---|
| 1 | 39 itens | Confirmado: **não existem** no seeder nem no banco (templates 1 = 21 itens, 2 = 35 itens). O Apêndice A do Caderno tem os 39 e foi verificado (ver GAP_ANALYSIS, Apêndice A). |
| 2 | `Nr1AiAnalyzer` e grupo pequeno | **Envia dados de setor pequeno à IA.** `buildAggregatedPayload` inclui `by_department_summary` e `by_department_and_dimension` com todas as linhas de `survey_results` por setor, sem filtro de mínimo (o cálculo grava setores com n ≥ 1). Envia também `min_responses_for_breakdown` (sempre 1). O prompt manda usar "Leve/Moderado/Grave" e proíbe cores; terá de acompanhar a troca de rótulos. |
| 3 | Bloqueio antes da publicação | **Não existe gate "Publicado" para resultados.** `SurveyResultsController::show` (tela legada "Resultados"), `PsychosocialDashboardController`, `ExportController` e `ReportController` só checam `company_id` e permissão de módulo; o cliente vê o resultado assim que `survey_results` existe. Único gate: o plano de ação legado (`action_plans.admin_published_at`, `ActionPlanController`, `streamActionPlan` → 404 sem publicação). Documentos novos só aparecem depois de `release()`, mas isso não é "publicar" no sentido do Caderno (o painel continua aberto). |
| 4 | `assertReleasable` | `ExecutiveBriefSnapshotBuilder::assertReleasable` está **vazio**. `PgrSubsidySnapshotBuilder` e `EvidenceDossierSnapshotBuilder` só bloqueiam se a polaridade estiver `pending_audit`. Nenhum exige CNPJ, razão social, responsável técnico ou elegíveis (campos ficam `null`, "lacuna continua nula"). Logo, hoje é possível liberar documento com campos vazios. |
| 5 | PDFs legados | `ReportController`: **executivo** e **encaminhamento** usam upload publicado (`SurveyNr1Report`, `publishedUpload`) se existir; senão **geram ao vivo** a partir dos resultados atuais, a cada download. **Técnico** (`ReportGenerator::technicalPdf`) **sempre gera ao vivo**. **Plano de ação** gera ao vivo, mas só se publicado. Nenhum tem snapshot/hash. Ou seja, o conteúdo muda se os resultados forem recalculados. Os 3 documentos novos (`PsychosocialDocumentService`) têm snapshot + hash e nunca regeneram. |
| 6 | Isolamento entre empresas | Rodado (ver §11). `ClientCrossCompanyAccessTest` cobre: ver pesquisa, export JSON, export CSV, relatórios executivo/técnico/encaminhamento/plano e denúncia de outra empresa → negado. **Lacunas:** não há teste IDOR para painel psicossocial, export do painel, download/validação de documentos nem medidas. |
| 7 | Capacitação / "solicitar proposta" | Capacitação está **oculta** (teste `hidden capacitacao route is unavailable`); não serve a "Treinamento por público". Busca por fluxo "solicitar proposta/contato" em `app/` e `resources/js`: **nenhum**. |
| 8 | Produção | **Não confirmável** daqui (só há acesso ao banco local). Antes de qualquer migration: contar campanhas com `talents39_v1`, documentos já emitidos e respostas por setor em produção. |
| 9 | Front | `PsychosocialDashboard.vue` (262 linhas) tem 4 blocos: Dimensões, Setores, Itens, Validar documento. Sem busca, filtro, ordenação nem cartões de distribuição. Não abri os outros componentes além dos títulos. |

Também confirmado: a tela legada "Resultados" **ainda mostra setor com menos de 5** (teste `current screen and presenter still show sector below five`); a cascata só vale no painel novo. `PsychosocialMeasureEvent` é imutável (update/delete lançam `LogicException`) e guarda campo, valor antigo/novo, usuário e motivo: base pronta para o "Histórico" do plano de ação.

## 11. Testes executados

Comando (dentro do container, SQLite em memória):

```
docker compose exec -T app php artisan test \
  tests/Feature/Client/ClientCrossCompanyAccessTest.php \
  tests/Feature/PsychosocialDashboardPhaseFiveTest.php \
  tests/Feature/ExecutiveBriefPhaseEightTest.php \
  tests/Feature/SurveyCampaignRegistrationPhaseSevenTest.php \
  tests/Feature/Nr1CampaignRegistrationPhaseTwoTest.php \
  tests/Feature/Nr1MethodVersionsPhaseOneTest.php \
  tests/Feature/Nr1ReportsTest.php \
  tests/Feature/Nr1Talents39CalculationPhaseSixTest.php \
  tests/Feature/PsychosocialDocumentsPhaseFourTest.php \
  tests/Feature/PsychosocialMeasuresPhaseThreeTest.php \
  tests/Unit/Nr1ScoringTest.php tests/Unit/Nr1ReportServiceTest.php \
  tests/Unit/Nr1RiskScenarioResolverTest.php tests/Unit/SurveyNr1ReportTest.php \
  tests/Feature/Permissions/UserPermissionsTest.php
```

Resultado: **122 passed, 718 assertions, 0 falhas** (66 s). Foi um subconjunto (15 arquivos, NR-1 + isolamento + permissões); **não rodei a suíte completa** (fica para antes de qualquer "pronto"). Nenhum arquivo foi alterado.
