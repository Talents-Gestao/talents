# GAP_ANALYSIS — Caderno NR-1 V11 × código atual

Somente leitura. Cruza `CADERNO_NR1_V11.md` (seções 2 a 18, Apêndice A, cenários T01–T25) com `AUDITORIA_CODIGO.md` (leia a §10 dela: resolve as pendências). Base: `new-features` (NR-1 fases 1–11) + redesign. Plano de execução em `PLANO_IMPLEMENTACAO.md`.

**Legenda.** SIM = existe e atende. PARCIAL = existe parte ou com outro comportamento. NÃO = não existe. Decisão: **reaproveitar** (usar como está), **estender** (additivo sobre o existente), **criar** (novo). Risco: B/M/A.

**Resumo.** O cálculo central já existe e bate com os valores esperados. Os maiores buracos são de **segurança** (exportações e IA vazam setor pequeno; sem gate "Publicado"), **coleta** (sem 39 itens, sem Time/Líderes, sem antiduplicidade) e **fluxo** (sem PASSEG, sem CRM).

---

## A. Regras de produto (§2)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Interface sem textos para o programador | PARCIAL | Textos do painel novo já voltados ao usuário (`PsychosocialDashboard.vue`); tela legada não auditada | estender (revisão por tela) | B |
| 3 opções ao cliente: Orientação NR-1, Treinamento Online/Presencial por público | NÃO | Nenhum modelo de treinamento/contratação por público; Capacitação oculta | criar (E8) | M |
| Colaborador só vê orientação, treinamento do seu público e questionário | PARCIAL | Colaborador responde por link público sem login (`PublicSurveyController`), então já não vê resultados; não há "Minha jornada" nem público | criar (E3) | M |
| Percepção separada da classificação do PGR | SIM | `PsychosocialDashboardBuilder::BAND_DISCLAIMER`; sem matriz 4×4 no painel novo | reaproveitar | B |
| Exibir todas as dimensões em atenção | NÃO | `build()` entrega dimensões sem seleção/ordenação por faixa | estender (E5) | B |
| "Outros Setores" recalculado sobre respostas originais | NÃO | Só supressão em cascata (`publishableSectorIds`); setor pequeno some, não é agregado | criar (E2) | A |
| Talents sugere responsável/prazo; empresa confirma; histórico | PARCIAL | `PsychosocialMeasure` tem `responsible_text`, `suggested_due_date`; `PsychosocialMeasureEvent` imutável com antes/depois/motivo. Falta confirmado × sugerido | estender (E6) | M |
| Somente 2 documentos finais | NÃO | 3 documentos novos (RE, SUB, DOS) + 4 PDFs legados (executivo, técnico, encaminhamento, plano) | estender (E7: ocultar, sem apagar) | M |
| Revisão PASSEG dentro de Documentos | NÃO | Só liberar → validar (cliente ou Admin informando) | criar (E7) | A |
| Resultados e documentos só visíveis após "Publicado" | NÃO | Painel, tela legada, exports e PDFs só checam `company_id`+módulo; só o plano legado tem `admin_published_at` | criar gate (E7); **mitigação interina em E0 não cabe: muda comportamento atual, ver decisão D9** | A |
| Soluções comerciais pós-publicação, sem venda automática | NÃO | — | criar (E8) | M |
| Sem matriz 4×4 nem conformidade automática | SIM | Nenhuma matriz; faixas rotuladas como "do questionário" | reaproveitar | B |

## B. Perfis e telas (§3, §4)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Perfil Talents (edita antes de publicar) | PARCIAL | `super_admin` + `is_owner` + `AdminPermissionModule` (matriz existe, contra o `CLAUDE.md`) | reaproveitar | B |
| Perfil Empresa (após publicar; confirma plano) | PARCIAL | `company_admin`/`company_user` + `PermissionModule` (`pesquisas`, `planos_acao`, `relatorios`, `validate`) | estender | M |
| Perfil PASSEG externo, vê só o compartilhado | NÃO | `UserRole` tem 3 valores; sem compartilhamento por campanha/arquivo | criar (E4) | A |
| Perfil Comercial só metadados/agregados | PARCIAL | `users.is_commercial`, módulos `Comercial*` (propostas, financeiro). Nada limita a agregados NR-1 | estender (E4/E8) | A |
| Colaborador por público | NÃO | Sem público, sem login do colaborador | criar (E3/E4) | M |
| Autorização no servidor em toda consulta, exportação e arquivo | PARCIAL | `abort_unless(company_id…)` nos controllers; `User::canAccess`; 7 testes de IDOR de pesquisa (tela, CSV, JSON, 4 PDFs) mais 1 de denúncia. Faltam painel, export do painel, documentos, medidas | estender (testes IDOR em E0/E7) | A |
| Jornada (8 passos) | PARCIAL | Passos 01, 03, 04 (liberar), 07 (medidas) existem em partes | ver seções abaixo | M |

## C. Visão geral (§5)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Cabeçalho com status real (rascunho, coleta aberta/encerrada, revisão, publicado) | PARCIAL | `surveys.status`, `publicParticipationClosureReason`; sem "revisão/publicado" | estender (E7) | B |
| Card índice geral | SIM | `overall` em `build()` (já 2 casas no `talents39_v1`) | reaproveitar | B |
| Card adesão = válidos/elegíveis × 100 | PARCIAL | `eligibleCard` mostra "X de Y"; **sem percentual**; elegível só por campanha, não por setor/público | estender (E2/E5) | B |
| Card nº de dimensões em atenção | NÃO | — | criar (E5) | B |
| Card execução do plano | PARCIAL | Status das medidas existe; sem agregação | estender (E6) | B |
| Pontos fortes (top favoráveis, %, base) | NÃO | Não há % favorável por item | criar (E2/E5) | M |
| 7 dimensões em barras | PARCIAL | Dimensões listadas (tabela); sem gráfico de barras com faixa | estender (E5) | B |
| Lista completa de dimensões em atenção, ordenada | NÃO | — | criar (E5) | B |
| Participação por grupos + "Outros Setores" | PARCIAL | Setores publicáveis listados; sem grupo agregado | estender (E2/E5) | M |
| Imprimir panorama respeitando publicação/perfil | NÃO | — | criar (E7) | B |
| Nunca fixar 40 respostas/48 elegíveis | SIM | Nenhum default no código; `eligible_count` vem do cadastro e mostra "Aguardando total elegível" se nulo | reaproveitar | B |

## D. Campanha e contratos (§6)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Modalidades Orientação / Online / Presencial por público | NÃO | `CommercialProposal.svc_nr1_implantacao_modo` (`online|presencial`) existe, só como serviço da proposta, sem público | criar tabela de ofertas por campanha (E8) | M |
| Preço/escopo, datas, link/arquivo, responsável | NÃO | — | criar (E8) | M |
| Presencial: local, data/hora, facilitador, capacidade, presença | NÃO | — | criar (E8) | B |
| Solicitar proposta cria oportunidade (origem, empresa, campanha, modalidade, público, solicitante, data, "Novo") | NÃO | Nenhum fluxo "solicitar"; `LandingInterestSubmission` e `CommercialProposal` não têm esses vínculos | criar (E8) | M |
| Não criar oportunidade por visualização; bloquear duplicado aberto | NÃO | — | criar com índice único parcial (E8) | M |
| Link/QR respeita abertura/fechamento e não fica público se fechada | PARCIAL | Janela existe (`starts_at/ends_at`, `acceptsPublicResponses`); a página responde "Encerrada" mas **o token continua resolvendo**; sem QR | estender (E3) | M |

## E. Minha jornada (§7)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Entrada por URL/QR com contexto do público | NÃO | `survey.public` por token único da campanha | criar (E3) | M |
| Objetivo, sigilo e instruções em linguagem clara | PARCIAL | `Survey/Take.vue` (conteúdo não auditado) | estender | B |
| Orientação NR-1 sempre visível | NÃO | — | criar (E3/E8) | B |
| 39 afirmações, escala Nunca…Sempre | PARCIAL | Escala existe (`likertLabels`); **os 39 itens não** (21 e 35 itens nos templates) | criar instrumento (E1) | M |
| Anterior/próxima, progresso, **rascunho**, confirmação, **antiduplicidade** | NÃO | `Take.vue` envia tudo de uma vez; sem rascunho nem controle de duplicidade (só `throttle`) | criar (E3), **política = D5/D6** | A |
| Responsivo, teclado, leitor de tela | PARCIAL | Não auditado em `Take.vue` | estender + teste manual (E3) | B |
| Não exibir resultados/rankings/nomes | SIM | Página de agradecimento só mostra título | reaproveitar | B |
| Decisão anonimato: sem identificador direto e resposta no mesmo registro | PARCIAL | `survey_responses` não tem identificador direto; porém guarda `age_range`, `tenure_range`, setor e **`completed_at` exato** | estender (E0 + D3/D4) | A |

## F. Motor de pesquisa (§8)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Tabela versionada de 39 itens, 7 dimensões, polaridade, escala | PARCIAL | Versionamento existe (`survey_instrument_versions`, `item_matrix` + sha256, `Nr1InstrumentMatrix`). **Conteúdo dos 39 não existe**; polaridade vive em `reverse_score` por pergunta | criar dados + estender (E1) | M |
| Escala frequência 1–5 (Nunca=1 … Sempre=5) | SIM | `PublicSurveyController::show` (`likertLabels`); `survey_template_questions.response_scale` | reaproveitar | B |
| Direto = resposta; favorável invertido = 6 − x | SIM | `Nr1Scoring::effectiveLikertValue` | reaproveitar | B |
| Média da dimensão = Σ escores ÷ respostas válidas | SIM | `SurveyResultCalculator::storePooledAggregate` (`talents39_v1`) | reaproveitar | B |
| Índice geral = Σ 39 itens ÷ respostas válidas | SIM | idem; `Nr1PooledItemScore` (aritmética inteira, meio para cima, 2 casas). Reproduz 4052/1560 → 2,60 | reaproveitar | B |
| % favorável / desfavorável por item, base válida | NÃO | `items()` só devolve n | criar (E2) | M |
| Faixas 1,00–2,33 / 2,34–3,66 / 3,67–5,00 | SIM (valor) / PARCIAL (rótulo) | `survey_method_versions` (`talents39_v1`: 2,33/3,66, faixa após arredondar). Rótulos Leve/Moderado/Grave em `config/nr1.php`, `BAND_LABELS`, `LEGENDS`, Vue/Blade, prompt da IA | estender: **só rótulo de exibição** (E1), ver nota abaixo | M |
| Participação = válidos ÷ elegíveis × 100 | PARCIAL | ver §C | estender | B |
| Homologar faixas, polaridade, ausentes, pesos | NÃO | `polarity_audit_status` (`pending_audit` bloqueia SUB/DOS) é o mecanismo certo; nada registra a homologação de faixas/pesos/ausentes | reaproveitar + estender (E1), **decisão D1** | A |
| Regra para respostas ausentes | PARCIAL | Cálculo ignora ausentes (denominador = respostas válidas); `min_item_coverage` existe como coluna, `null` | estender após D1 | M |

**Nota — renomear faixas só na exibição.** `survey_results.risk_level` grava `green|yellow|red`; manter. Criar um mapa único `Nr1BandLabels` (por `method_version.code`) com `talents39_v1 → menor sinalização / atenção / maior sinalização` e `production_v1 → Leve/Moderado/Grave` (campanhas antigas não mudam). Trocar em: `Nr1Scoring::riskLabel`, `BAND_LABELS`, `LEGENDS`, `Nr1RiskLevelLegend.vue`, `nr1_risk_legend.blade.php`, prompt e payload de `Nr1AiAnalyzer`. Snapshots de documentos já emitidos guardam texto e não mudam; conferir se `psychosocial/*.blade.php` imprime rótulo do snapshot ou da constante (a impressão não regenera, mas `renderHtml` deve ser testado).

## G. Resultados (§9)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Filtros por campanha, período, estabelecimento, dimensão, grupos | NÃO | Painel novo é por campanha; sem filtros | criar (E5) com a política de privacidade (E0) | A |
| Cards por setor/grupo com adesão, N e índices | PARCIAL | Setores publicáveis com N e índice; sem adesão por setor (elegível só na campanha) | estender; exige elegíveis por setor (D12) | M |
| Gráficos por dimensão; cor **e** texto | PARCIAL | Tabela de dimensões; sem gráfico | estender (E5; ApexCharts já no projeto) | B |
| 39 cartões com distribuição em 5 faixas, %, busca, ordenação | NÃO | — | criar (E2 dados, E5 UI) | M |
| 12 cartões iniciais + "ver todas" | NÃO | — | criar (E5) | B |
| Pontos positivos com N explícito | NÃO | — | criar (E5) | B |
| Registro privado da Talents (sem aba de alertas) | PARCIAL | `PsychosocialAccessLog` registra acessos, não notas de verificação | estender (E9) | B |

## H. Confidencialidade (§10)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Setor < 5 não aparece isolado | PARCIAL | Painel novo sim (`publishableSectorIds`). **Tela legada "Resultados", `Nr1AiAnalyzer`, `ExportController` (CSV/JSON linha a linha) e PDFs legados não aplicam**; `min_responses_for_breakdown` forçado a 1 | estender (E0) | **A** |
| Agrupar em "Outros Setores" por respostas válidas, recalcular sobre originais | NÃO | — | criar (E2) | A |
| Mostrar composição só se não reidentifica | NÃO | HTML V11 lista os 9 setores; sistema não lista | decisão D8; padrão: não listar | M |
| Bloquear subtração e combinação de filtros | PARCIAL | Cascata cobre subtração entre setores no painel novo; **não cobre** exportação linha a linha, nem setor × faixa etária × tempo de casa, nem série histórica | estender (E0/E5) | A |
| Registro interno de diferenças de função sem divulgar | PARCIAL | `survey_results` guarda setor n ≥ 1 (útil internamente, perigoso se lido sem a política) | estender: toda leitura via política única (E0) | A |
| Supressão adicional se "Outros Setores" ainda for pequeno | NÃO | Cascata cobre setores, não o agregado | criar (E2); **conflita com §2 ("nunca supressão") — D7** | M |
| Não tratar "Outros Setores" como grupo homogêneo | NÃO | — | criar texto/aviso (E5) | B |
| Mínimo 5 configurável | PARCIAL | `min_group_size_new_outputs = 5` por método, fixo; `surveys.min_responses_for_breakdown` = 1 | estender (E0) | M |

## I. Plano de ação (§11)

| Campo do Caderno | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Fator/dimensão | SIM | `factor_label`, `evidence_basis` | reaproveitar | B |
| Ação proposta (objetivo, resultado esperado) | PARCIAL | `measure_description`; sem objetivo/resultado esperado separados | estender | B |
| Responsável sugerido | PARCIAL | `responsible_text` (hoje sem distinguir sugerido) | estender: renomear semântica, não a coluna | B |
| Responsável confirmado (usuário/função/contato) | NÃO | — | criar colunas (E6) | M |
| Prazo sugerido, origem, justificativa | PARCIAL | `suggested_due_date`; sem origem/justificativa | estender | B |
| Prazo confirmado + data de aprovação | NÃO | — | criar | M |
| Status Proposta → Confirmada → Em andamento → Concluída/Replanejada | PARCIAL | `to_approve → pending → in_progress → implemented`; **sem Replanejada**; CHECK no Postgres e `saving()` no model | estender: mapear rótulos + novo valor `replanned` (alterar CHECK) | M |
| Aferição (indicador, evidência, eficácia, data, resultado) | PARCIAL | `effectiveness_note`, `effectiveness_checked_at`; sem indicador e sem evidência anexa | estender | M |
| Histórico antes/depois, autor, data, justificativa | SIM | `PsychosocialMeasureEvent` (imutável) | reaproveitar | B |
| Talents edita antes da publicação; empresa confirma depois | NÃO | Sem noção de publicação | criar regra (E6/E7) | M |
| PASSEG pede ajuste → volta à Talents sem excluir versões | NÃO | — | E7 | M |
| Plano não parece implementado por ter sido impresso | PARCIAL | `implemented` exige data e usuário (model); documento ainda não mostra status por medida | estender (snapshot mostra status) | B |
| Imprimir plano dentro do relatório técnico, não como outro documento | NÃO | Há "plano de ação" PDF legado separado | estender (E7) | M |

**Mapeamento de status (proposta, não aplicar sem aprovação):** `to_approve` → "Proposta"; `pending` → "Confirmada"; `in_progress` → "Em andamento"; `implemented` → "Concluída"; novo `replanned` → "Replanejada". Só rótulos mudam para os 4 existentes; os registros atuais continuam válidos. `PsychosocialMeasureService::changeStatus` aceita **qualquer transição** entre os 4 status (só `implemented` exige usuário e grava data), então "Pendente" não tem semântica imposta; o mapa acima é por rótulo, e a máquina de estados da V11 (Proposta → Confirmada → …) ainda precisa ser criada. Há `CHECK` no Postgres (`psychosocial_measures_status_check`) que precisa ser recriado para aceitar `replanned`.

## J. Documentos (§12) e PASSEG (§13)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Relatório executivo (1–2 págs.) | PARCIAL | `ExecutiveBriefSnapshotBuilder` (`executive_brief.v1`, snapshot+hash, PDF único) | reaproveitar + estender conteúdo (pontos fortes %, todas as atenções, responsável/prazo) | M |
| Relatório técnico + plano de ação (3–5 págs.) | PARCIAL | Existem `pgr_subsidy`, `evidence_dossier` e o técnico legado (`ReportGenerator::technicalPdf`, ao vivo, sem hash). Nenhum é "técnico + plano" congelado | criar builder `technical_plan.v1` reaproveitando a base (`buildForSchema`) | M |
| PDF com cabeçalho, página, identificador, versão, data, elaborador/revisor | PARCIAL | `public_identifier` (RE/SUB/DOS-…), versão, data; elaborador/revisor não | estender | B |
| Não imprimir "CNPJ não informado"/"a identificar"; exigir antes de publicar | **NÃO** | `assertReleasable` do executivo é **vazio**; SUB/DOS só checam polaridade; lacuna fica `null` | criar bloqueio de publicação com obrigatórios (E7) | **A** |
| Sem 48 elegíveis fictícios em produção | SIM | Nenhum default fictício no código | reaproveitar (+ teste) | B |
| Anexar versão do instrumento e agregados de suporte consultáveis | PARCIAL | Versão do método/instrumento e matriz entram na versão; anexos só no dossiê | estender | B |
| Imutável/versionado; não regenerar publicado | SIM | `release()` cria versão nova, snapshot + `snapshot_sha256`, `download()` nunca regenera | reaproveitar | B |
| Fluxo rascunho → enviado à PASSEG → ajustes **ou** aprovado → publicado | NÃO | Só `released` → `validated` | criar máquina de estados + reviews (E7) | A |
| Ações: Enviar revisão, Solicitar alteração (texto obrigatório), Aprovar, Publicar, Nova versão | NÃO | Existe "liberar" e "validar" | criar | M |
| PASSEG só vê o compartilhado; lê, comenta, aprova a versão enviada | NÃO | — | criar (E4/E7) | A |
| Nova versão preserva pareceres e diffs básicos | NÃO | Versões existem; pareceres/diff não | criar | M |
| Empresa sem acesso a resultados/arquivos até "Publicado" | NÃO | ver §A | criar gate | A |
| "Aprovado pela PASSEG" ≠ "Integração ao PGR confirmada" (campo separado, com evidência) | NÃO | — | criar campo + evidência | M |
| Protocolo de encaminhamento e ciência da empresa | PARCIAL | `validateInSystem` (declaração aceita) e `validateInformed` (comprovante) servem como "ciência"; sem protocolo | reaproveitar + estender | B |

**Documentos que ficariam ocultos na interface (sem apagar código nem dados):** subsídio AEP/PGR (`pgr_subsidy`), dossiê de evidências (`evidence_dossier`), relatório executivo legado, relatório técnico legado, encaminhamento técnico e plano de ação PDF legado (e `SurveyNr1Report`/upload de parecer técnico). As rotas legadas continuam existindo; devem passar pelo mesmo gate de publicação, senão seguem gerando PDF ao vivo.

## K. Soluções e CRM (§14)

| Requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Matching dimensão → serviços, configurável, com explicação e confiança | NÃO | — | criar tabela de regras (E8) | M |
| Sugestões só após publicação; botão "Solicitar conversa" | NÃO | — | criar | M |
| Clique gera lead com empresa, campanha, serviço, necessidade, data, responsável | NÃO | `LandingInterestSubmission` é lead do site, sem esses campos | criar (reaproveitar lead? ver abaixo) | M |
| Sugestão sem clique cria só "A qualificar" | NÃO | — | criar | M |
| CRM: Novo / A qualificar / Em contato / Proposta enviada / Ganho / Perdido, atividade, próxima ação, histórico | NÃO | Lead: só `is_qualified`. Proposta: `list_status` `open|closed|ended`, `lost_reason`, `contacted_at` | criar `commercial_opportunities` + eventos; ligar a `CommercialProposal` em "Proposta enviada" | M |
| Registrar recusa de serviço adicional | PARCIAL | `lost_reason` na proposta | estender | B |
| Comercial vê só metadados e agregados liberados | NÃO | — | criar serializer + perfil (E4/E8) | A |

**Reaproveitar Leads/Propostas?** Proposta: **não reaproveitar o lead do site como oportunidade** (sem empresa cliente, sem campanha, sem estágios) e **ligar a oportunidade à proposta existente** quando houver ("Proposta enviada" → `commercial_proposal_id`). Assim o fluxo financeiro/ZapSign continua o mesmo. Decisão D13.

**Bloqueio de edição destrutiva (Apêndice A, "ATENÇÃO"): PARCIAL.** Existe só `survey_answers.survey_template_question_id` com `restrictOnDelete` (migration `2026_06_02_120000`): impede **apagar** pergunta já respondida. **Nada impede editar** `body`, `reverse_score`, seção ou ordem de pergunta de campanha iniciada, e isso muda o resultado recalculado (o recálculo lê o template vivo, não a `item_matrix` congelada). Estender no E1.

## L. Entidades (§15) e transversais (§16)

| Entidade / requisito | Existe? | Evidência | Decisão | Risco |
|---|---|---|---|---|
| Organização/Unidade | PARCIAL | `Company`; unidade em `SurveyCampaignRegistration.unit_name` (por campanha, não entidade) | reaproveitar; avaliar entidade Unidade (D12) | M |
| Campanha | SIM | `Survey` + `SurveyCampaignRegistration` + versão de método/instrumento | reaproveitar | B |
| Setor/elegibilidade (efetivo por setor e público, categoria de proteção) | **NÃO** | Só `eligible_count` total | criar tabela (E2/E3), D12 | M |
| Questionário/Item (obrigatório, ordem, escala) | PARCIAL | `survey_template_questions` (`reverse_score`, `response_scale`, `weight`, `sort_order`); sem `required` | estender | B |
| Resposta (id técnico desvinculado, data coarsened, status) | PARCIAL | ver §E | estender (E0) | A |
| Agregado (N, soma, média, 5 contagens) | PARCIAL | `survey_results` (`score_numerator`, `score_denominator`, N); **sem 5 contagens** | criar `survey_item_distributions` (E2) | M |
| Treinamento/Contratação | NÃO | — | criar (E8) | M |
| Plano de ação | PARCIAL | §I | estender | M |
| Documento/Parecer | PARCIAL | §J | estender | M |
| Oportunidade | NÃO | §K | criar | M |
| Auditoria (ator, ação, objeto, antes/depois, justificativa, versão) | PARCIAL | `PsychosocialMeasureEvent` (medidas), `PsychosocialAccessLog` (acesso), `...DocumentValidation` (validação) | criar log genérico para publicação/aprovação/mudança de dados (E7/E9) | M |
| Autenticação segura, **MFA para perfis internos** | NÃO | Nenhum código de MFA/2FA no projeto (a busca por `two_factor/2fa/mfa/otp` só acha falsos positivos: `PasswordResetLinkController`, `SafeExternalUrl`) | criar ou adotar pacote (D14) antes de abrir PASSEG | A |
| Criptografia, backup, retenção documentada | NÃO confirmado | Fora do escopo do código lido | D10 (Jurídico) | M |
| Reidentificação em exportações, filtros, série histórica | NÃO | ver §H | E0 e E5 | A |
| PDFs versionados; trilha PASSEG/Talents não editável pelo cliente | PARCIAL | Versões imutáveis; sem PASSEG | E7 | M |
| URLs de vídeo/questionário com validade; sem material pago a não contratante | NÃO | — | E3/E8 | M |
| Erro, estado vazio, N=0, sem pesquisa | PARCIAL | Placeholders de elegíveis; `has_results` | estender + T25 | B |
| Desempenho, acessibilidade, contraste, moeda/data pt-BR | PARCIAL | Redesign em andamento (tokens AA em `4ef15fd`) | estender | B |
| Sem dados reais em logs/testes | PARCIAL | Seeders demo existem; não auditei logs | revisar `Log::` e seeders (E0) | M |

---

## T01–T25: cobertura prevista

| Cenário | Estado hoje | Etapa | Observação |
|---|---|---|---|
| T01 Empresa não vê campanhas/docs de outra | PARCIAL: 7 testes IDOR de pesquisa (tela, CSV, JSON, 4 PDFs); faltam painel, export do painel, documentos, medidas | E0 (completar) e E7 | |
| T02 Cliente sem acesso antes de "Publicado" | NÃO | E7 | Impacta campanhas em andamento (D9) |
| T03 PASSEG só aprova versão submetida | NÃO | E7 | |
| T04 "Solicitar alteração" exige justificativa e histórico | NÃO | E7 | |
| T05 Trilha Time não recebe treinamento só de Líderes | NÃO | E3/E8 | |
| T06 Orientação NR-1 para ambos | NÃO | E3 | |
| T07 Solicitar proposta Online Time gera lead sem duplicar | NÃO | E8 | |
| T08 39 itens com escala de frequência | NÃO (itens) | E1 | |
| T09 Favorável inverte; desfavorável direto | SIM (unidade) | E1 reforça com o dataset | |
| T10 Q7 37/40 = 92,5%; Q8 30/40 = 75% | NÃO (sem % favorável) | E2 | Conferido nas contagens do Apêndice A |
| T11 Adesão 40/48 = 83,3% | NÃO (sem %) | E2/E5 | Teste com elegíveis=48 só em fixture |
| T12 "Outros Setores" agrega 15 e recalcula | NÃO | E2 | Fixture: Produção 19, Manual 6, outros 15 (HTML V11) |
| T13 Filtro/exportação não inferem setor 1–4 | NÃO (vaza) | **E0** | |
| T14 Lista de atenção: seis dimensões em ordem | NÃO | E5 | Ordem esperada: Demandas 3,3042; Segurança 2,7458; Esgotamento 2,6400; Liderança 2,5625; Relacionamentos 2,4500; Percepção 2,4400 |
| T15 39 cartões pesquisáveis/filtráveis/ordenáveis | NÃO | E5 | |
| T16 Talents edita sugerido sem marcar confirmação | NÃO | E6 | |
| T17 Empresa confirma responsável/prazo, evidência e eficácia | NÃO | E6 | |
| T18 Técnico incorpora plano; executivo separado | NÃO | E7 | |
| T19 Documento publicado sem campos vazios/fictícios | NÃO | E7 | |
| T20 Publicado não muda sem nova versão | SIM (base) | E7 teste explícito | `release()` já cria versão nova |
| T21 Oportunidades com origem e sem dado bruto | NÃO | E8 | |
| T22 Recusa comercial não altera classificação | NÃO | E8 | |
| T23 Cadastro excluído/inativo conforme LGPD | NÃO | E9 | Depende de D10 |
| T24 Celular/desktop, teclado, leitor de tela | PARCIAL | E3/E5, checklist manual | Sem E2E no projeto (`SMOKE_CHECKLIST.md`) |
| T25 Estado vazio sem zerar médias | PARCIAL | E5 | `has_results` + placeholders existem |

**Critérios de aceite numéricos (verificados contra o Apêndice A por script):** índice geral 4052/1560 = 2,5974 → 2,60 (atenção); dimensões Demandas 793/240 = 3,3042; Autonomia 381/200 = 1,9050; Liderança 615/240 = 2,5625; Segurança 659/240 = 2,7458; Relacionamentos 588/240 = 2,4500; Esgotamento 528/200 = 2,6400; Percepção 488/200 = 2,4400; Q7 31+6 = 37/40; Q8 21+9 = 30/40. Seis dimensões em atenção; só Autonomia e Organização (1,905) fica em "menor sinalização". O dataset do Apêndice só traz contagens por item, não respostas conjuntas: o fixture de teste gera 40 respondentes distribuindo as contagens por item (os totais batem; correlações entre itens não são reproduzidas).

---

## Apêndice A — verificação dos 39 itens

**Conclusão: o instrumento de 39 itens não existe em nenhum seeder nem no banco local.** Não há divergência a listar item a item, porque não há item a comparar:

| Fonte | Itens | Seções | Conteúdo |
|---|---|---|---|
| `TalentsSeeder::seedNr1Questions` ("NR-1 / COPSOQ (modelo Talents)") | 21 | 7 (Exigências do trabalho, Organização do trabalho, Relações interpessoais e liderança, Compensação e reconhecimento, Interface trabalho-vida, Saúde e bem-estar, Assédio e violência) | Redação, nomes de dimensão e polaridade diferentes dos do Apêndice |
| `HseItQuestionData` ("HSE-IT") | 35 | 7 (Demandas, Controle, …) | Mistura frequência e concordância; polaridade `pending_audit` |
| Banco local | 21 e 35 | — | Nenhum template com 39 |
| Testes (`Nr1Talents39CalculationPhaseSixTest`) | 39 sintéticos | 3 | Só para validar a aritmética |

Verificação **do próprio Apêndice A** (script sobre `CADERNO_NR1_V11.md`): 39 linhas, IDs 1–39 sem lacuna; cada item tem exatamente 40 contagens; a soma bate com os critérios de aceite (acima).

| Dimensão (Apêndice A) | IDs | Itens | Polaridade |
|---|---|---|---|
| Demandas e Sobrecarga | 1–6 | 6 | todos Direta |
| Autonomia e Organização | 7–11 | 5 | todos Invertida |
| Liderança e Gestão | 12–17 | 6 | todos Invertida |
| Segurança Psicológica | 18–23 | 6 | todos Invertida |
| Relacionamentos e Clima | 24–29 | 6 | todos Invertida |
| Esgotamento e Saúde Emocional | 30–34 | 5 | todos Direta |
| Percepção Organizacional | 35–39 | 5 | todos Invertida |

Total: 11 itens Direta (1–6, 30–34) e 28 Invertida. Mapeamento para o sistema: `reverse_score = (polaridade == Invertida)`; `response_scale = frequency` nos 39; uma seção por dimensão, na ordem acima; ordem = ID. Para criar: template novo + `survey_instrument_versions` (código `talents39`, já reservado em `Nr1InstrumentMatrix::CODE_TALENTS39`, nunca usado), com `polarity_audit_status = pending_audit` até Suzane homologar (isso já bloqueia SUB/DOS; ver D1).

**Observações de conteúdo a confirmar com a responsável técnica** (não alterei o texto): (a) "Tenho clareza sobre minhas responsabilidades" (item 7) e "Consigo organizar minhas atividades com autonomia" (item 8) são afirmações favoráveis respondidas em escala de **frequência**, o que o T08 exige; (b) o item 4 ("atenção constante por longos períodos") é Direta, mas descreve uma exigência da tarefa, não necessariamente um problema, e pesa 1 dos 6 itens de Demandas; (c) o `reverse_score` só pode ser decidido por item, então qualquer mudança de polaridade exige **nova versão** do instrumento.

---

## Inconsistências e lacunas do próprio Caderno (para decidir)

1. **§2 × §10:** a §2 diz que "Outros Setores" nunca usa "supressão de respondentes", mas a §10 permite suprimir/agrupar de novo quando o agregado ainda for pequeno. Como tratar o caso em que a soma dos setores pequenos é < 5?
2. **"Dimensões em atenção" (§5, §9, T14):** ficam só as da faixa 2,34–3,66 ou também as de "maior sinalização" (≥ 3,67)? No dataset não aparece nenhuma ≥ 3,67, então os testes não resolvem a dúvida.
3. **§8 "peso das dimensões":** o índice geral por respostas válidas dá mais peso a dimensões com mais itens (Demandas, Liderança, Segurança e Relacionamentos têm 6; as demais 5). Confirmar que é o desejado (a própria §8 manda homologar).
4. **"Data coarsened" (§15):** sem granularidade definida (dia, semana, mês).
5. **Faixa etária e tempo de casa:** o Caderno não os lista na entidade Resposta; hoje são coletados e exportados.
6. **Elegíveis por setor/público (§15):** o sistema só tem total por campanha; adesão por grupo exige cadastro por setor.
7. **HTML V11 lista a composição de "Outros Setores"**, o que a §10 só permite se não reidentificar.
8. **Salvar rascunho "conforme política" (§7):** política não definida; rascunho no servidor pode conflitar com anonimato.
9. **Rótulos Leve/Moderado/Grave:** o Caderno só define menor sinalização / atenção / maior sinalização; o sistema atual e o prompt da IA usam os outros nomes.

## Decisões que são de pessoas (nada decidido aqui)

| ID | Decisão | Dono (Caderno §19) |
|---|---|---|
| D1 | Homologar redação, polaridade, faixas, pesos, regra de ausentes e versão do instrumento | Talents — técnica (Suzane) |
| D2 | Mínimo de grupo: 5 como padrão; permitir reduzir? piso? | Talents técnica + Jurídico |
| D3 | Granularidade da data (dia/semana/mês) e se a data exata antiga é coarsened retroativamente | Talents + Jurídico |
| D4 | Continuar coletando faixa etária e tempo de casa? | Talents + Jurídico |
| D5 | Política de rascunho (existe? onde fica?) | Talents + Jurídico |
| D6 | Política de envio duplicado (opções no plano, E3) | Talents + Jurídico |
| D7 | "Outros Setores" ainda pequeno: suprimir ou unir mais? | Talents técnica |
| D8 | Mostrar ou não a composição de "Outros Setores" | Talents + empresa |
| D9 | Campanhas já abertas: passam a exigir "Publicado" ou ficam como estão? | Talents (produto) |
| D10 | Base legal LGPD, retenção, exclusão/inativação (T23), anonimização | Jurídico/LGPD |
| D11 | Formato do relatório, quem lê/pede ajuste na PASSEG, integração ao GRO/PGR | Talents + PASSEG |
| D12 | Cadastro de elegíveis por setor/público e entidade "Unidade" | Talents + empresa |
| D13 | Regras comerciais: matching, preço, origem de leads, integração com "CRM Talents" externo | Talents — comercial |
| D14 | MFA para perfis internos e identidade/MFA da PASSEG | Murilo/Suede + Talents |
| D15 | Dimensões em atenção: só 2,34–3,66 ou também ≥ 3,67 | Talents técnica |
