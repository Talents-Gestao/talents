# Plano de implementação — NR-1 V11

Proposta, **sem execução**. Depende da aprovação do `GAP_ANALYSIS.md`. Base: `feature/nr1-v11` (criada de `new-features`). Uma etapa por vez, um commit por etapa (só com sua autorização, `git add` por caminho), suíte completa antes de dizer "pronto".

**Regras para todas as etapas.** Migrations só aditivas, com `down()` que reverte só o que criaram. Nenhuma coluna ou dado apagado. Nada de recalcular documento emitido (snapshot e hash). Nada que toque Financeiro, RHID ou Calendário. Textos em pt-BR com acento. Dataset de teste do Apêndice A em `tests/Support/Nr1V11Dataset.php` (novo, só em teste).

**Mapa para o §17 do Caderno:** E0 = pré-requisito de segurança (fora do Caderno) · E1+E2 = "2. Cálculo" · E3 = "1. Coleta" · E4 = "0. Fundação" (perfis) · E5 = "3. Painel" · E6 = "4. Ações" · E7 = "5. Docs/PASSEG" · E8 = "6. Serviços" · E9 = "7. Operação".

**Ordem sugerida:** E0 → E1 → E2 → E3 → E4 → E5 → E6 → E7 → E8 → E9. E4 antes de E5 porque o painel já nasce com o perfil certo; E7 é onde o gate "Publicado" entra (muda o que o cliente vê hoje).

---

## Etapa 0 — Segurança (commit próprio, antes de tudo)

Objetivo: fechar os vazamentos de grupo pequeno hoje existentes. **Não** muda cálculo, faixas nem documentos emitidos.

**0a. Exportações e leituras respeitam o mínimo, sem data/hora exata.**
- Criar `app/Support/Nr1GroupPrivacy.php` (puro, sem I/O): recebe `departmentId => n` e o mínimo; devolve setores publicáveis (move para cá a lógica de `PsychosocialDashboardBuilder::publishableSectorIds`, que passa a delegar) e, para uso na E2, os que irão para "Outros Setores". Um só lugar para a política.
- `ExportController::csv` e `::json` (linha a linha, com setor + faixa etária + tempo de casa + data): **trocar por exportação agregada** (reuso de `PsychosocialDashboardBuilder::exportRows`), ou, se a exportação por linha precisar continuar, só com setores publicáveis, sem `age_range`/`tenure_range` e sem data. Proposta: remover o linha a linha do painel da empresa e deixá-lo só para o Admin Talents, com a mesma política. **Decisão D4** sobre faixa etária/tempo de casa.
- Tela legada "Resultados" (`SurveyResultsPresenter`, `SurveyResultsController`): filtrar setores pela política (hoje exibe setor < 5; há um teste que afirma isso e **precisará mudar**: `PsychosocialDashboardPhaseFiveTest::current screen and presenter still show sector below five`).
- Data coarsened: migration aditiva `survey_responses.completed_period` (date, nula; início da semana ISO) preenchida no envio; `PublicSurveyController::submit` grava também o período, e nenhuma leitura/export usa mais `completed_at`. **Não** mexer em `completed_at` existente nesta etapa (apagar a hora exata de dados antigos é destrutivo e irreversível: comando artisan separado, com autorização, depois de **D3** definir dia/semana/mês).

**0b. Um único mínimo efetivo (5), configurável por campanha.**
- Migration: `surveys.min_group_size` (`unsignedSmallInteger`, not null, default 5). Backfill: 5 em todas. A coluna `min_responses_for_breakdown` **fica como está** (legado, deixa de ser gravada com 1 e de ser enviada à IA).
- `PsychosocialDashboardBuilder` usa `surveys.min_group_size` (não mais `methodVersion.min_group_size_new_outputs`, que vira só o default de campanhas novas). Validação em `SurveyController`: inteiro, **piso 5** por padrão (reduzir abaixo de 5 = **D2**).
- Campo na UI da campanha (`Client/Surveys/Create|Edit.vue`, hoje `min_responses_for_breakdown` fixo em 1) trocado por `min_group_size` com texto explicando o motivo.
- `SurveyResultCalculator` continua gravando setores com n ≥ 1 (precisa para "Outros Setores" na E2), mas **toda leitura passa por `Nr1GroupPrivacy`**; teste de arquitetura que falha se um controller/serviço ler `survey_results` por setor sem a política (lista de leitores conhecidos: painel, apresentador, IA, exports, relatórios).

**0c. `Nr1AiAnalyzer` (confirmado: envia setor pequeno).**
- `buildAggregatedPayload`: aplicar `Nr1GroupPrivacy` em `by_department_summary` e `by_department_and_dimension`; remover `min_responses_for_breakdown` do payload; incluir só setores publicáveis e, se houver, o agregado "Outros Setores" (E2). Se nenhum setor for publicável, enviar só geral e dimensões.
- Os relatórios legados que leem setor (`ReportGenerator`, `reports.technical`, `Nr1ReportService`) passam pela mesma política (verificar: não li o Blade de cada um).
- Não mexer no texto do prompt agora (rótulos trocam na E1).

**Testes da E0:** (PHPUnit; `Http::fake()` para IA)
- Cascata: 29/19/6/4 publica só A (já existe) + novos casos (1 setor de 3; dois de 2 e 3; soma dos ocultos < 5 oculta o menor visível).
- T13: CSV/JSON da empresa não trazem setor com 1–4 respondentes nem combinação setor×idade×tempo que isole alguém; sem data/hora exata.
- Payload da IA (`Http::fake` capturando o corpo): não contém nomes de setores pequenos nem `respondent_count` < mínimo.
- `min_group_size`: default 5; rejeita < 5; `min_responses_for_breakdown` não é mais forçado a 1.
- IDOR que faltam (T01): painel, export do painel, download e validação de documento, medidas, `Admin/*` com outra empresa; colaborador/`company_user` sem permissão → 403.
- Regressão: `talents39_v1` continua dando 2,60 (4052/1560) e `production_v1` inalterado.

**Arquivos a tocar:** `app/Support/Nr1GroupPrivacy.php` (novo), `PsychosocialDashboardBuilder`, `ExportController`, `SurveyResultsPresenter`, `SurveyResultsController`, `Nr1AiAnalyzer`, `PublicSurveyController`, `SurveyController`, `Survey` (fillable), `Client/Surveys/Create|Edit.vue`; migrations `add_min_group_size_to_surveys`, `add_completed_period_to_survey_responses`.

**Depende de pessoas:** D2 (piso do mínimo), D3 (granularidade da data), D4 (faixa etária/tempo de casa).

**Risco:** a exportação linha a linha some para a empresa (quebra de uso conhecido). Aviso ao suporte e ao cliente antes do deploy.

---

## Etapa 1 — Instrumento V11 (39 itens) e rótulos de faixa

- Dados dos 39 itens em `database/seeders/Talents39InstrumentData.php` (texto, dimensão, polaridade do Apêndice A; conferido por script). Comando `nr1:import-instrument-v11` (idempotente) cria o template "Talents NR-1 V11 (39 itens)", 7 seções na ordem do Apêndice, `response_scale = frequency`, `reverse_score = (Invertida)`, e a `survey_instrument_versions` (`instrument_code = talents39`, `version_label = v11-1`, `item_matrix` + sha256 por `Nr1InstrumentMatrix`), com `polarity_audit_status = pending_audit` (bloqueia SUB/DOS até a homologação, **D1**). O comando chama-se em ambiente; não vai em `DatabaseSeeder` de produção.
- **Bloqueio de edição destrutiva:** guarda em `SurveyTemplateQuestion` e `SurveyTemplateSection` (eventos `updating`/`deleting`) que lança exceção se o template estiver ligado a uma campanha iniciada (`starts_at` ≤ agora ou com respostas) em `body`, `reverse_score`, seção, ordem, `response_scale`; ação "Criar nova versão" (clona o template e abre `survey_instrument_versions` nova). Recálculo passa a usar a `item_matrix` congelada da campanha (hoje lê o template vivo), com teste de que alterar o template não altera o resultado de uma campanha congelada.
- **Rótulos de faixa** (só exibição): `app/Support/Nr1BandLabels.php` (por código de método). `talents39_v1`: menor sinalização / atenção / maior sinalização; `production_v1`: Leve/Moderado/Grave. Trocar em `Nr1Scoring::riskLabel`, `PsychosocialDashboardBuilder::BAND_LABELS`/`LEGENDS`, `Nr1RiskLevelLegend.vue`, `nr1_risk_legend.blade.php`, prompt e payload de `Nr1AiAnalyzer`. `survey_results.risk_level` não muda (`green|yellow|red`).
- Migrations: **nenhuma tabela nova obrigatória**; opcional `survey_template_questions.required` (boolean, default true) para o §15.

**Testes:** o dataset do Apêndice A (40 respostas × 39 itens) reproduz: índice 4052/1560 = **2,5974 → exibido 2,60**, faixa "atenção"; dimensões Demandas 3,3042 · Autonomia 1,9050 · Liderança 2,5625 · Segurança Psicológica 2,7458 · Relacionamentos 2,4500 · Esgotamento 2,6400 · Percepção 2,4400; T08 (frequência, não concordância), T09 (inversão), 11 Direta + 28 Invertida, 39 itens/7 seções, hash da `item_matrix`; edição destrutiva bloqueada; rótulos novos só em `talents39_v1`; snapshot antigo mantém o texto emitido.

**Arquivos:** `Talents39InstrumentData`, `Nr1ImportInstrumentV11Command`, `SurveyTemplateQuestion`, `SurveyTemplateSection`, `SurveyResultCalculator` (usar matriz congelada), `Nr1BandLabels`, `Nr1Scoring`, builder/Vue/Blade/IA citados.

**Depende de pessoas:** D1 (texto, polaridade, faixas, pesos, ausentes), D15.

---

## Etapa 2 — Cálculo e confidencialidade

- **"Outros Setores" sobre respostas originais:** `SurveyResultCalculator` (caminho `talents39_v1`) passa a gravar, além dos setores, uma linha por recorte para o grupo agregado, calculada sobre as respostas dos setores não publicáveis (numerador/denominador somados; nunca média de médias). Migration: `survey_results.group_key` (string nula; `'others'`) + índice; a identificação por `department_id` nulo e `group_key='others'`. Se o agregado ainda estiver abaixo do mínimo: aplicar **D7** (suprimir ou unir ao menor setor visível). Não gravar composição por padrão (**D8**).
- **Distribuições por item:** migration `survey_item_distributions` (`survey_id`, `question_id`, `group_key`/`department_id` nulos, `c1..c5`, `n`, único por recorte), preenchida no mesmo recálculo. Serve a %, favorável/desfavorável e aos 39 cartões. Favorável = Frequentemente+Sempre (item favorável) ou Nunca+Raramente (item desfavorável); base = respostas válidas do item.
- **Adesão:** `eligible_count` por campanha (já existe) + opcional `survey_eligibility` (`survey_id`, `department_id`, `audience`, `eligible_count`) para adesão por grupo (**D12**). Percentual = respondentes válidos ÷ elegíveis × 100; sem elegíveis informado, mostrar "—", nunca 0.
- Estado vazio: N=0 não grava média nem zera; já preservado em `storePooledAggregate`.

**Testes:** T10 Q7 = 37/40 = **92,5%** e Q8 = 30/40 = **75%**; T11 adesão 40/48 = **83,3%** (elegíveis = 48 só no fixture); T12 "Outros Setores" com 15 respostas (fixture Produção 19, Manual 6, outros 15) recalculado sobre as respostas, **igual** ao cálculo direto das 15 respostas (e **diferente** da média das médias quando os tamanhos diferem); T13; T25; duas campanhas, mesmo cálculo antes/depois (sem alterar `production_v1`); recálculo idempotente.

**Arquivos:** `SurveyResultCalculator`, `SurveyResult`, `Nr1GroupPrivacy`, `PsychosocialDashboardBuilder`, novos modelos/migrations; `Nr1RecalculateCommand` (reprocessamento auditável, E9).

**Depende de pessoas:** D7, D8, D12, D1 (regra de ausentes).

---

## Etapa 3 — Coleta (Time/Líderes, link/QR, duplicidade)

- **Público:** migration `survey_audience_links` (`survey_id`, `audience` `time|lideres`, `token` único, `opens_at`, `closes_at`, `enabled`) e `survey_responses.audience` (nulo para campanhas antigas). Cada público tem URL/QR próprios; o link só resolve na janela e quando habilitado (hoje o token resolve sempre e a página diz "Encerrada"). QR gerado no servidor (SVG; escolher biblioteca já presente ou adicionar com revisão).
- **Rascunho:** conforme **D5**. Opção neutra: rascunho **só no navegador do respondente** (`localStorage`), nada no servidor, sem impacto no anonimato.
- **Minha jornada:** `Survey/Take.vue` reescrito por etapas (anterior/próxima, progresso, confirmação), com os blocos da §7 (objetivo, sigilo, Orientação NR-1 sempre; Online/Presencial só se contratado e liberado ao público). Acessibilidade: foco, `aria`, teclado, contraste AA.
- **Envio duplicado — opções (a decidir, D6; nada escolhido aqui):**

| Opção | Como funciona | Anonimato | Prós | Contras |
|---|---|---|---|---|
| A. Só limite de taxa (hoje) | `throttle` por IP | Total | Zero mudança | Não impede duplicata; um IP corporativo bloqueia colegas |
| B. Cookie/dispositivo | Marca o navegador ao enviar | Alto (marca no cliente, não no servidor) | Simples, sem cadastro | Burlável (outro navegador/anônimo); não é garantia |
| C. Token de uso único por convite (voucher cego) | Lista de convites separada (sem ligação com a resposta); o token é consumido no envio e só o hash do token vive em tabela de "usados", sem `response_id` | Alto se a separação for real | Impede duplicidade de forma forte | Exige distribuir tokens por pessoa; mais operação; risco LGPD se a tabela de convites ligar a pessoa e o momento do envio |
| D. Lista de participação (controle de convites) | Registra quem já participou (sem guardar a resposta junto) | Médio: sabe-se **quem** respondeu, não **o quê**; correlação por horário é possível | Também dá adesão por pessoa | Exige base legal e avaliação LGPD (Caderno §7); datas coarsened são essenciais |
| E. Detecção a posteriori | Marca respostas suspeitas por padrão (mesmo cookie, taxa, tempo) para revisão da Talents | Alto | Não bloqueia pessoa de boa-fé | Não previne; custo de revisão |

  Implementação só começa depois da decisão; a base (tabela `survey_audience_links` e campo `audience`) serve a todas.
- Vídeos/materiais: campo de link/arquivo com validade (`SafeExternalUrl` já existe) e liberação por público; nunca visível a não contratante.

**Testes:** T05, T06, T08; 40 respostas de teste geram **contagens 39/39 por item sem duplicação** (critério do §17-1); link fechado não resolve; abertura/fechamento por público; duas campanhas isoladas; acessibilidade verificada por checklist manual (adicionar ao `SMOKE_CHECKLIST.md`).

**Depende de pessoas:** D5, D6, D10 (aviso/base legal na tela de entrada), D13 (liberação por público).

---

## Etapa 4 — Perfis e autorização

- **PASSEG (externo):** novo valor `UserRole::ExternalReviewer` (`passeg_reviewer`) em vez de reaproveitar `company_user`, para **não herdar** nenhuma permissão da empresa. Tabela `document_shares` (`document_version_id`, `user_id`, `granted_by`, `granted_at`, `revoked_at`): só o que for compartilhado. Middleware `EnsureExternalReviewer` + `Policy` por versão. Painel agregado só leitura.
- **Comercial limitado:** novo módulo de admin `nr1_oportunidades` (`AdminPermissionModule`) e serializer próprio que devolve só metadados e agregados de campanha **publicada**; teste de que nunca expõe `survey_answers` nem linha por setor abaixo do mínimo.
- **Colaborador por público:** continua sem login; "perfil" = o link do público (E3). Teste: nenhuma rota do colaborador devolve resultado, venda ou oferta de outro público.
- **MFA** (D14): pacote 2FA para `super_admin` e PASSEG; hoje não existe. Decisão de infra (Murilo/Suede) antes.
- Auditoria de perfis: `PsychosocialAccessLog` ganha ator externo.

**Testes:** T01 completo (matriz perfil × recurso × ação), T03 (preparação), IDOR para todos os endpoints novos, PASSEG sem compartilhamento = 404, Comercial sem resposta bruta.

**Migrations:** `add_external_reviewer_role` (a coluna `users.role` é string; conferir se há CHECK), `create_document_shares`.

**Depende de pessoas:** D11 (quem é a PASSEG na prática), D14.

---

## Etapa 5 — Painel

- `PsychosocialDashboardBuilder` estendido (leitura, sem recalcular): cabeçalho/status, cards (índice, adesão %, nº de dimensões em atenção, execução do plano), pontos fortes (top favoráveis com % e N), barras das 7 dimensões, **lista completa** das dimensões em atenção em ordem decrescente, participação por grupo com "Outros Setores", 39 cartões (distribuição em 5 faixas, % favorável/desfavorável, busca, filtro por dimensão, ordenação, 12 iniciais + "ver todas").
- Filtros por campanha, período, dimensão, grupos: **só combinações que passam por `Nr1GroupPrivacy`** (sem filtros que revelem a contribuição de um setor pequeno; sem filtro por faixa etária/tempo).
- Front (`Client/Surveys/PsychosocialDashboard.vue` + componentes): design system do projeto, ApexCharts, cor **e** texto, estado vazio (N=0), teclado e leitor de tela; pt-BR; baseado no HTML V11 (`docs/nr1-v11/Talents_NR1_V11_Aprovacao_Final.html`). Sem texto de programador.
- Impressão do panorama respeita publicação e perfil (E7).

**Testes:** T14 (seis dimensões, ordem: Demandas 3,3042 > Segurança 2,7458 > Esgotamento 2,6400 > Liderança 2,5625 > Relacionamentos 2,4500 > Percepção 2,4400; Autonomia 1,9050 fora), T15, T25, T13 (filtros), valores de cartões; JS puro (ordenação/busca) em `tests/JavaScript/*.test.mjs`. Verificação manual em celular e desktop (T24).

**Depende de pessoas:** D15, D7/D8 (texto de "Outros Setores").

---

## Etapa 6 — Plano de ação

- Estender `psychosocial_measures` (aditivo): `suggested_responsible` (copiado de `responsible_text`, que fica), `confirmed_responsible_user_id` / `confirmed_responsible_text`, `confirmed_due_date`, `confirmed_at`, `confirmed_by`, `suggested_due_origin`, `suggested_due_note`, `objective`, `expected_result`, `indicator`; tabela `psychosocial_measure_evidences` (arquivo, hash, autor, data); status novo `replanned` (recriar `psychosocial_measures_status_check`). Rótulos: Proposta / Confirmada / Em andamento / Concluída / Replanejada (valores gravados mantidos; ver GAP §I).
- `PsychosocialMeasureService`: transições explícitas (hoje livres); só Talents edita sugerido antes da publicação; só a empresa (módulo `planos_acao`, ação `edit`) confirma/ajusta depois; todo ajuste gera `PsychosocialMeasureEvent` (já imutável, com motivo).
- Plano entra no relatório técnico (E7); sem PDF "plano" oficial separado.

**Testes:** T16, T17, histórico preservado, `Concluída` exige data e usuário, empresa não edita antes de publicar, IDOR por medida, CHECK novo.

**Depende de pessoas:** D1 (prioridades não são classificação legal), D11.

---

## Etapa 7 — Documentos, PASSEG e publicação

- **Dois produtos:** manter `executive_brief` (estender conteúdo) e criar `technical_plan` (`technical_plan.v1`, builder novo reaproveitando `ExecutiveBriefSnapshotBuilder::buildForSchema`, incluindo plano, metodologia, 39 itens, elegíveis, agregados). `pgr_subsidy`, `evidence_dossier` e os 4 PDFs legados ficam **ocultos na interface**, com dados e rotas preservados (e atrás do mesmo gate).
- **Máquina de estados** em `psychosocial_document_versions` (aditivo): `review_status` (`draft|sent|changes_requested|approved|published`), `published_at`, `published_by`; tabela `psychosocial_document_reviews` (imutável: versão, autor, ação, texto, data) para "Solicitar alteração" (texto obrigatório) e "Aprovar"; `pgr_integration_confirmed_at` + `pgr_integration_evidence_path` (campo separado de "Aprovado pela PASSEG"). Ações: Enviar revisão, Solicitar alteração, Aprovar, Publicar, Nova versão; pareceres anteriores preservados; diff básico por campo do snapshot. Versão publicada é imutável (a nova versão é nova linha, como `release()` faz).
- **Publicação bloqueada com campos vazios:** `assertReleasable` passa a exigir razão social, CNPJ, unidade, responsável técnico, elegíveis, instrumento homologado (`polarity_audit_status = accepted`) etc., com mensagem por campo. "CNPJ não informado" nunca é impresso.
- **Gate "Publicado" (T02):** `SurveyPublicationGate` (policy/middleware) aplicado a painel, tela legada, exportações e downloads: a empresa só vê resultado e arquivo de **versão publicada**. **D9** decide o que acontece com campanhas já abertas (sem decisão, nada muda para elas; flag `surveys.requires_publication`, default `false` para as existentes e `true` para as novas).
- Protocolo de encaminhamento e ciência da empresa: reaproveitar `validateInSystem` como "ciência".

**Testes:** T02, T03, T04, T18, T19, T20 (publicado não muda sem nova versão; hash estável), PASSEG só vê compartilhado, empresa não vê rascunho, documento legado oculto mas rotas respondem 404 quando sem publicação, regressão dos testes de `PsychosocialDocumentsPhaseFourTest` (os já emitidos não regeneram).

**Migrations:** `add_review_workflow_to_psychosocial_document_versions`, `create_psychosocial_document_reviews`, `add_requires_publication_to_surveys`.

**Depende de pessoas:** D9, D11, D1, D14.

---

## Etapa 8 — Serviços, treinamentos e CRM

- **Ofertas por campanha:** `survey_training_offerings` (`survey_id`, `modality` `online|presencial`, `audience` `time|lideres|ambos`, `contracted`, escopo/preço, início/fim, link/arquivo, responsável; presencial: local, data/hora, facilitador, capacidade; presença).
- **Oportunidades:** `commercial_opportunities` (`company_id`, `survey_id`, `origin` `request|automatic|…`, `service`, `modality`, `audience`, `requested_by`, `status` Novo/A qualificar/Em contato/Proposta enviada/Ganho/Perdido, `last_activity_at`, `next_action`, `commercial_proposal_id` nulo, `assigned_to`) + `commercial_opportunity_events`. **Índice único parcial** em (`company_id`, `survey_id`, `modality`, `audience`) onde o status é aberto (T07). Não cria oportunidade por visualização.
- **Matching:** tabela de regras (`nr1_service_matches`: dimensão, serviços elegíveis, explicação, confiança, ativo), editável pela Talents; sugestões só pós-publicação; "Solicitar conversa" cria lead; sugestão automática sem clique cria só "A qualificar".
- Recusa registrada sem alterar classificação nem fechar pendências ocupacionais (T22).

**Testes:** T05, T07, T21, T22; Comercial não vê resposta bruta; IDOR por oportunidade.

**Depende de pessoas:** D13 (regras comerciais, preço, integração com CRM externo), D12.

---

## Etapa 9 — Operação

- Notificações (reusar o centro de avisos existente), acompanhamento, histórico, filtros avançados já sob a política de privacidade, desempenho.
- Auditoria genérica `nr1_audit_events` (ator, ação, objeto, antes/depois, justificativa, versão) para publicação, aprovação, mudança de campanha e reprocessamento; `Nr1RecalculateCommand` registra cada execução.
- Política de retenção/inativação (T23) conforme **D10**.
- Registro privado de verificação da Talents (sem aba de alertas), em torno de `PsychosocialAccessLog`.

**Testes:** T23, trilha de auditoria imutável, reprocessamento auditável, desempenho com 10 mil respostas.

**Depende de pessoas:** D10.

---

## Riscos transversais

1. **Gate "Publicado" muda o que clientes veem hoje** (E7, D9). Precisa de comunicação.
2. **Exportação linha a linha deixa de existir para a empresa** (E0).
3. **Polaridade `pending_audit` bloqueia SUB/DOS** até a Suzane homologar (D1); não bloqueia o painel.
4. **Dados antigos com data exata e faixa etária** continuam no banco até decisão de D3/D4.
5. **Produção desconhecida:** só consultei o banco local. Antes de qualquer migration, contar em produção campanhas `talents39_v1`, documentos emitidos e setores com n < 5.
6. **Sem E2E no projeto:** T24 depende de verificação manual (`SMOKE_CHECKLIST.md`).

## Verificação antes de "pronto" (cada etapa)

`docker compose exec app php artisan test` (suíte completa, não só o subconjunto), `./vendor/bin/pint`, `npm run test:js` e `npm run build` quando houver front; relatório do que mudou e do que ficou pendente.
