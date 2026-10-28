# PRD — Talents (produto atual)

Documento vivo do produto, alinhado ao código do repositório `talents/`.  
Complementa `.cursor/rules/Talents-Structure.mdc`.  
Última revisão completa: **22/10/2026**, sobre a branch `new-features` (não assume deploy em produção).

Legenda de estado: **Implementado** (há rota, tela e teste/uso no código) · **Parcial** (existe, com limitação anotada) · **Planejado** (ainda não entregue).

---

## 1. Visão do produto

A **Talents Gestão de Pessoas** é:

1. Consultoria estratégica em gestão de pessoas (comportamento, liderança, cultura), sede em Várzea Paulista/SP.
2. SaaS multi-tenant (Laravel 12 + Inertia/Vue 3) com três superfícies:
   - **Admin Talents** (`/admin`) — operação interna da consultoria.
   - **Portal da empresa cliente** (`/client`) — módulos habilitados por plano/configuração.
   - **Superfícies públicas por token** — pesquisas, denúncias, sugestões, entrevista de desligamento, assinatura de feedback, avaliação de cliente.

Pilares de metodologia na landing: Metamorfose Comportamental, Contratação de Talentos, Direcionamento Estratégico, Gestão de Riscos Psicossociais (NR-1).

Cadastro público desativado; acesso por login/convite. Cada empresa pode ter **portal próprio** com login em `/entrar/{slug}`, nome e cor da marca.

### 1.1 Perfis de acesso

| Perfil | Papel (`UserRole`) | Alcance |
|--------|--------------------|---------|
| Owner Talents | `super_admin` com `is_owner` | Acesso master a todo `/admin/*`, ignora a matriz de permissões |
| Admin Talents | `super_admin` | `/admin/*` conforme a **matriz de permissões por módulo/ação** (ver 1.2) |
| Administrador da empresa | `company_admin` | `/client/*` com todos os módulos habilitados; gerencia usuários; Férias e Avaliações restritas a este papel |
| Utilizador da empresa | `company_user` | `/client/*` com permissões granulares por módulo/ação |

Contexto de sessão por **workspace** (`Talents` | `Company`), com tela de seleção quando o usuário tem mais de um. Sessão com aviso de expiração (`SESSION_WARNING_MINUTES`).

### 1.2 Permissões

- **Admin:** `AdminPermissionModule` (~35 módulos, de Painel a Equipe) × `PermissionAction` (`view`, `create`, `edit`, `delete`, `validate`). Middleware `admin.can:{modulo}[,acao]`; vários módulos com `|` (OR). Painel (`view`) é liberado a qualquer super admin ativo. Grants legados (`financeiro`, `comercial`, `solides`, `entrevistas`, `companies`) continuam valendo e expandem para os filhos atuais. A matriz é editada em **Configuração → Equipe**.
- **Cliente:** `PermissionModule` (17 módulos, 16 atribuíveis; `Capacitação` fora). Middleware `can.module:{modulo}[,acao]`. Cada módulo exige também estar habilitado na empresa (`Company::hasModuleEnabled`): os de NR-1 vêm do **plano** (`Module::KEY_NR1`); os demais, de flags da empresa (`*_access`) ou de módulos do plano.
- **Ação `validate`:** exclusiva do módulo `relatorios` do cliente; autoriza validar documentos NR-1 no sistema (ver 2.8).

---

## 2. Produto atual (implementado no código)

### 2.1 Landing / páginas públicas

| Capacidade | Estado |
|------------|--------|
| Páginas: `/`, `/para-empresas`, `/para-pessoas`, `/nossos-clientes`, `/sobre`, `/contato`, `/nr-1`, `/diagnostico-comportamental`, `/contratacao-de-talentos`, `/direcionamento-estrategico`, `/sitemap.xml` | Implementado (`routes/web.php`) |
| Hero com H1 **“O resultado que você espera está nas pessoas!”**, subtítulo **“Potencialize resultados com gestão de pessoas, ciência e estratégia.”** e typewriter (respeita `prefers-reduced-motion`) | Implementado (`LandingHeroTypewriter.vue`) |
| Formulário de interesse `POST /interesse` com **origem (`source`)**, limitado a 5/min por IP | Implementado (`LandingInterestController`, `LandingInterestSource`) |
| PWA (`vite-plugin-pwa`) | Implementado |

### 2.2 Painel operacional (Admin Home — `/admin/dashboard`)

| Capacidade | Estado |
|------------|--------|
| 3 cards no topo: Financeiro, Comercial, **Calendário · hoje** | Implementado |
| KPIs em grelha de 3 colunas; gráficos de leads, funil e meta | Implementado |
| Financeiro com links para **Contas a pagar** e **Contas a receber** | Implementado |
| Frase do dia (`MetamorfoseDailyQuote`, catálogo em `resources/data/metamorfose-quotes.json`) | Implementado |
| CTAs no header: tarefas ADM abertas + **Pendências** (`alertsCount`) | Implementado |
| Indicadores via `AdminHomeDashboardBuilder` (BD real + heurísticas) | Implementado |
| **Meta mensal** cadastrável (`admin_dashboard_settings`), com fallback `TALENTS_DASHBOARD_MONTHLY_GOAL_CENTS` (default R$ 20.000) | Implementado |
| Layout configurável (`useAdminDashboardLayout`) | Implementado |

### 2.3 Comercial

| Capacidade | Estado |
|------------|--------|
| **Gestão** de propostas em kanban/lista (`/admin/comercial/propostas`): criação, preview de cálculo, status, marcação de contato, notas, reabrir, motivo de perda (`ProposalLostReason`), validade | Implementado |
| PDF da proposta com seções opcionais | Implementado (`CommercialProposalPdfService`) |
| **Comissões extras** por proposta (múltiplas comissões por venda) | Implementado |
| Propostas **recorrentes** (campos de recorrência em propostas e vendas) | Implementado |
| **Contratos** gerados a partir da proposta (placeholders, PDF) e **envio para assinatura via ZapSign** | Implementado |
| **Modelos de contrato** (3 modelos canônicos + timbrado oficial, editor, download DOCX; comando `commercial:sync-canonical-contract-templates`) | Implementado |
| **Tabela de valores / produtos** (preço por tipo — `CommercialProductPricingType`), vendedores e configurações | Implementado (`/admin/comercial/configuracoes`) |
| **Processos comerciais** (documentos de processo com download; hoje no menu **Configuração → Processos**) | Implementado |
| **Assinaturas** (ex-“Planos”): CRUD de planos SaaS e seus módulos; UI sem preço mensal nem máx. colaboradores | Implementado |
| Conversão de proposta fechada em **venda** e criação automática de acompanhamento de contratação | Implementado (`ProposalSaleConversionService`, `CreateHiringProcessFromClosedProposal`) |

### 2.4 Clientes

| Capacidade | Estado |
|------------|--------|
| **Leads** da landing (origem, qualificado, responsável, notas admin, inclusão manual) | Implementado |
| **Empresas**: CRUD, consulta CNPJ (ReceitaWS), CEP (ViaCEP), logo, Instagram, flags de módulos, branding/portal, convite do primeiro `company_admin` com reenvio | Implementado |
| **Diagnóstico empresarial** | Implementado |
| **Contratos fechados** (histórico de propostas `is_closed`, badges de contrato/ZapSign/venda) | Implementado |
| **Avaliações** de clientes: envio por link/e-mail, ciclo anual/trimestral, NPS e notas de atendimento/entrega/comunicação, exclusão pelo admin; o `company_admin` também cria e acompanha no portal | Implementado |
| Detalhe da empresa — abas: **Empresa**, **Gestão de ponto** (permissão `rhid`), **Colaboradores**, **Regulamento interno** | Implementado |
| Colaboradores: cadastro completo com lookup de CEP | Implementado |
| Regulamento interno: rich text + anexo PDF/DOC/DOCX com download | Implementado |
| **Destaques do mês** (`/admin/destaques-mes`, categorias em `MonthlyHighlightCategory`) | Implementado |

### 2.5 Contratação e reuniões (IA)

| Capacidade | Estado |
|------------|--------|
| **Banco de talentos** (currículos via integração Sólides) | Implementado |
| **Acompanhamento** da contratação em kanban de 8 etapas (`HiringProcessStage`: engenharia do cargo → contratação), com avançar/recuar, reordenar, observações e histórico de etapas; visível também no portal do cliente | Implementado |
| **Entrevistas IA**: upload de áudio (até ~500 MB), transcrição Whisper, relatório por roteiro em PDF e DOCX, reprocessamento | Implementado (`ProcessInterviewAudioJob`, fila Redis, `ffmpeg`) |
| **Roteiros** de entrevista (7 seções padrão no seeder) | Implementado |
| **Reuniões IA**: gravação, transcrição e ata editável (`MeetingStatus`) | Implementado |
| **Profiler** | Planejado (permissão e rota “em breve” existem) |

### 2.6 Voz do Time e NR-1

**Pesquisas NR-1 (COPSOQ)** — campanhas anônimas por empresa, com link público `/pesquisa/{token}`.

| Capacidade | Estado |
|------------|--------|
| Templates de pesquisa/mapeamento com seções e perguntas; versionamento do instrumento | Implementado |
| Resposta pública anônima com janela de datas, limite de mínimo de respostas por recorte e throttle | Implementado |
| Pontuação COPSOQ: média Likert 1–5 com inversão por item; faixas Leve ≤ 2,33 · Moderado ≤ 3,66 · Grave > 3,66 (`config/nr1.php`) | Implementado |
| Resultados, benchmark por segmento, tendência, insights e **análise por IA (Mia)** | Implementado |
| **Plano de ação** (gerado sugerido + edição), parecer técnico, relatórios executivo/técnico/encaminhamento em PDF, exportação JSON/CSV | Implementado |
| Exclusão de campanha com arquivamento (`ArchiveAndDeleteSurvey`, soft delete) | Implementado |
| Reconstrução de respostas e comandos `nr1:*` (recalcular, auditar, diagnosticar, relatório de versões) | Implementado |

**Trilha NR-1 por fases (lançada entre 20 e 22/10/2026)** — separa o processo novo do plano de ação antigo e **nunca grava índice/faixa fora do cálculo oficial**:

| Fase | Capacidade | Estado |
|------|-----------|--------|
| 1 | **Versão de método e de instrumento** por campanha: `production_v1` (padrão) e `talents39_v1` (escolha explícita) | Implementado |
| 2 | **Cadastro técnico** da campanha (unidade, processo, razão social/CNPJ congelados, elegíveis, grupos de exposição, resumo AEP, observação, controles existentes, responsável técnico e organização aprovadora), editado no Admin | Implementado |
| 3 | **Medidas psicossociais** com ciclo `a aprovar → pendente → em andamento → implementada`, origem manual ou sugerida pelo motor, evidências, efetividade e trilha de eventos | Implementado |
| 4 | **Documentos NR-1**: versões congeladas (snapshot + hash) com PDF impresso do snapshot | Implementado |
| 5 | **Painel psicossocial gerencial** (`/client/surveys/{id}/painel-psicossocial`): só leitura dos números gravados; não revela idade, tempo de casa nem respostas individuais; exporta CSV/JSON | Implementado |
| 6 | **Índice `talents39`**: soma das respostas válidas, numerador/denominador, faixa decidida após arredondamento half-up, contagem de pessoas (não respostas) | Implementado |
| 7 | Cadastro técnico visível no fluxo da campanha | Implementado |
| 8 | **Resumo executivo** liberado com snapshot congelado | Implementado |
| 9 | **Subsídio AEP/PGR** liberado e **validação**: no sistema (declaração do usuário com permissão `validate`) ou **informada** (com prova anexa); gera versão-filha validada com o mesmo hash | Implementado |
| 10–11 | **Dossiê de evidências** com anexos (AEP, observação, inventário, outros) e lista congelada; **medidas** vinculadas | Implementado |

Regras de bloqueio: campanha com **auditoria pendente** (ex.: instrumento HSE-IT) não libera subsídio nem dossiê; o resumo executivo continua podendo ser validado. Liberação e validação são do Admin; o cliente baixa e, com permissão, valida.

**Demais canais de Voz do Time**

| Canal | Capacidade | Estado |
|-------|-----------|--------|
| **Denúncias** (Lei 14.457/2022) | Link público por empresa `/denuncia/{token}`, protocolo legível, acompanhamento do denunciante com mensagens, conteúdo criptografado, trilha de auditoria, notificação por e-mail ao denunciante, gestão no Admin (com seleção de empresa) e no Cliente | Implementado |
| **Sugestões e dúvidas** | Canal anônimo `/sugestoes/{token}`, catálogo de assuntos, gestão de status no Admin e no Cliente | Implementado |
| **Desligamento** | Entrevista de saída: roteiro, link público `/desligamento/responder/{token}`, PDF; Admin e Cliente | Implementado |
| **Mapa de Propósito** | Campanha com 5 perguntas abertas (motivação, sonho, dores), resposta pública `/mapa-proposito/{token}`, dashboard e extração de temas por IA (fallback por frequência de palavras) | Implementado |
| **Visão geral** do cliente | `voz-do-time` agrega os canais | Implementado |
| **Direcionamento Estratégico / pesquisa de satisfação** (`/satisfacao/{token}`) | Admin mantém templates; **rotas do portal do cliente comentadas** até o módulo estar pronto | Parcial |

### 2.7 RH operacional

| Módulo | Capacidade | Estado |
|--------|-----------|--------|
| **Feedbacks internos** | Colaboradores, sessões com modelo (tipos `FeedbackQuestionType`), coleta de **assinaturas por link** (`/feedback/assinar/{token}`), PDF, dashboard de análise do time; Admin por empresa selecionada e Cliente | Implementado |
| **Férias** | Registro, edição e status; restrito a `company_admin` no portal; Admin com seleção de empresa; alimenta o calendário | Implementado |
| **Calendário estratégico** | Eventos, **rituais** e aniversários; recorrência; anexos; temas mensais (Janeiro Branco etc.); conclusão por usuário; importação de CSV; mistura tarefas e férias; visão por período | Implementado |
| **Avisos** | Sino interno (Admin e Cliente) com eventos tipados (`CompanyNoticeEventKind`: lead, proposta ganha, parcela vencida, comissão paga, denúncia, feedback, mudança de data no calendário…), marcar lido/excluir, contador de não lidos | Implementado |
| **Notícias** | Feed externo por categoria (RH, empreendedorismo, saúde, datas importantes…) com cache, no drawer | Implementado |
| **Tarefas** | Kanban: processos-modelo → quadros e cards; membros, labels, checklists, anexos, comentários com menção, atividade, favoritos, recorrência (`TaskCardRecurrence`), arquivar/restaurar, lembrete diário de vencimento (08:00); partilha de cartões com o portal | Implementado |
| **Setores e cargos** | CRUD e importação (cliente, módulo NR-1) | Implementado |
| **Usuários da empresa** | CRUD com matriz de permissões e reenvio de convite (`company_admin`) | Implementado |

### 2.8 RHID / Gestão de ponto (Control iD)

| Capacidade | Estado |
|------------|--------|
| **Admin → Gestão de ponto** (`/admin/ponto`): painel por empresa, métricas, últimas marcações, pessoas, aderência à escala, justificativas, configuração própria; `/admin/rhid` redireciona para cá | Implementado |
| **Cliente → RHID / Ponto**: compliance, ficha do colaborador, configurações, dispositivos (incl. ID Cloud), AFD, banco de horas, justificativas (incl. em massa), turnos, relatórios assíncronos | Implementado |
| **Espelhos de ponto** em PDF: upload, lote, parser Python (`tools/rhid-espelho-parser`), reprocesso, aderência a escala e conformidade mensal, preferências de escala por pessoa | Implementado |
| **Importação automática do dia fechado** (`rhid:import-closed-day`, 00:30 America/São_Paulo) | Implementado |
| Credenciais RHID por empresa (senha criptografada) e trilha de auditoria (`rhid_audit_logs`) | Implementado |

### 2.9 Financeiro

| Capacidade | Estado |
|------------|--------|
| **Resumo** (`FinanceCashflowMetrics`), alimentando também a Home | Implementado |
| **Vendas**: conversão de proposta, lançamento manual, edição/exclusão com impacto nas parcelas, parcelas com baixa e comprovante | Implementado |
| **Comissões** por vendedor (várias por venda, percentual por usuário) | Implementado |
| **Contas bancárias** (CRUD, saldo, transferência entre contas) | Implementado |
| **Contas a pagar** (recorrência mensal, baixa, conta vinculada) | Implementado |
| **Contas a receber** (ledger unificado: parcelas de venda + recebimentos manuais) | Implementado |
| **Formas de pagamento** | Implementado |
| Comando diário `commercial:notify-overdue-installments` (08:15) gera aviso de parcela vencida | Implementado |

### 2.10 Configuração

| Capacidade | Estado |
|------------|--------|
| **Geral** (`/admin/settings`): IA/Mia (OpenAI ou Anthropic, chave de transcrição Whisper separada), SMTP (substitui `MAIL_*` após o boot), Sólides; testes de conexão | Implementado |
| **Equipe**: usuários Admin Talents, convites, matriz de permissões, % de comissão | Implementado |
| **Empresa Talents** (dados da própria consultoria, usados em PDFs e contratos) | Implementado |
| Segredos criptografados com `APP_KEY`; diagnóstico `app:check-encryption` | Implementado |
| Menu externo **Certificados** (link para site de terceiro) | Implementado |

---

## 3. Depreciado / removido da UI

Distinção: **removido da superfície** (ainda pode existir rota/dado) vs **descontinuado**.

| Item | Antes | Agora | Tipo |
|------|--------|--------|------|
| Card “Pendências e alertas” na Home | Linha principal | Removido | Removido da superfície; pendências via CTA do header e avisos |
| Props Home `recentLeads`, `upcomingCalendar`, `calendarKindLabels`, lista `alerts` | Enviadas ao Inertia | Não enviadas | Limpeza de payload |
| “Operação · hoje” | Título do 3º card | **Calendário · hoje** | Renomeado |
| Aba Portfólio RHID no detalhe da empresa; hub `/admin/rhid` | Visíveis | Removidos; `/admin/rhid` → Gestão de ponto | Removido da superfície |
| Abas Destaques do mês, Férias, Uniformes/Controle no detalhe da empresa | Visíveis | Removidas | Removido da superfície; `/admin/destaques-mes` e `/admin/ferias` mantidos |
| “Planos” | Comercial → Planos | **Assinaturas** (rotas `plans` mantidas) | Renomeado |
| Preço e máx. colaboradores no CRUD de plano | Visíveis | Removidos da UI | Colunas podem permanecer |
| `/admin/ai-settings` | Tela própria | Redireciona para Configuração → Geral | Substituído |
| Capacitação | Módulo | `PermissionModule::Capacitacao` sempre desabilitado e fora da matriz | Oculto |
| Direcionamento Estratégico no portal do cliente | Rotas ativas | Comentadas em `routes/client.php` | Oculto até estar pronto |
| Pesquisa “Avaliação de clientes” | “Avaliações de clientes” | **Avaliação / Avaliações** | Renomeado |
| Processos comerciais | Menu Comercial | Menu **Configuração → Processos** | Movido |
| Comercial · Resumo | Tela própria | Unificado em **Gestão** (grants antigos continuam válidos) | Unificado |

---

## 4. Planejado / em curso (não documentar como “em produção”)

- **Profiler** (Contratação) — apenas rota “em breve” e permissão.  
- **Direcionamento Estratégico no portal do cliente** — Admin existe; portal oculto.  
- **Capacitação** — módulo desativado.  
- **E2E em navegador** (Dusk/Playwright) — não há; cobertura é PHPUnit + testes Node de utilitários.  
- **Documentos NR-1 em produção** — fases 1–11 entregues na branch `new-features`; confirmar merge, `migrate` e a rotina de PDFs na fila antes de tratar como disponível ao cliente.

Qualquer feature só descrita em prompts e ainda não refletida no código deve ficar aqui até validação.

---

## 5. Notas sobre dados e regras de negócio

### 5.1 Dados do Painel operacional

Os números da Home **não** são mocks no Vue:

- **Real (BD):** parcelas, payables, leads, propostas, empresas, vendas, itens de calendário.  
- **Heurística:** taxa de conversão, funil simplificado, fluxo previsto, tempo médio de contratação, hora lida da descrição do evento, “negociação” = propostas abertas.  
- **BD + fallback env:** meta mensal (`admin_dashboard_settings.monthly_revenue_goal_cents` → `TALENTS_DASHBOARD_MONTHLY_GOAL_CENTS`).  
- **Catálogo:** frase do dia.

Zeros na tela indicam falta de dados ou filtros, não placeholder.

### 5.2 Princípios do NR-1

- Faixa do questionário **não é** classificação de risco ocupacional do PGR (aviso exibido no painel).  
- Documentos são **imutáveis**: liberar congela o snapshot; validar cria versão-filha com o mesmo hash; recalcular a campanha **não** altera hashes nem arquivos já emitidos.  
- Lacuna de dado permanece nula; o sistema não infere.  
- Anonimato: recortes abaixo de `min_responses_for_breakdown` não são exibidos.

### 5.3 Segurança e abuso

- Rotas públicas com *rate limit* por IP+token (`config/public_rate_limits.php`, `AppServiceProvider`).  
- Isolamento entre empresas coberto por testes (IDOR).  
- Segredos (IA, SMTP, RHID, denúncias) criptografados; `SafelyDecryptsAttributes` evita falha com `APP_KEY` trocada.

---

## 6. Arquitetura e operação

| Item | Detalhe |
|------|---------|
| Backend | PHP 8.2+, Laravel 12, Sanctum, Breeze, Ziggy, DomPDF, PhpWord |
| Frontend | Vue 3, Inertia 2, Vite 7, Tailwind, Heroicons, TipTap, ApexCharts, vue-draggable-plus, PWA |
| Banco / fila | PostgreSQL 16, Redis 7; ~160 migrations |
| Containers | `app`, `nginx`, `postgres`, `redis`, `queue` (timeout 1800 s), `scheduler` (`schedule:work`); perfis `tools` (node) e `dev` (vite) |
| Agendamentos | Lembrete de vencimento de tarefas (08:00) · avisos de parcelas vencidas (08:15) · importação RHID do dia fechado (00:30) |
| Integrações | OpenAI/Anthropic (Mia), OpenAI Whisper, ReceitaWS, ViaCEP, Sólides, RHID/Control iD, ZapSign, feeds de notícias |
| Produção | Coolify com `docker-compose.prod.yml`; volume `talents_storage` guarda áudios e documentos; uploads até 512 MB |
| Testes | ~160 arquivos PHPUnit (Feature/Unit) + testes Node em `tests/JavaScript`; checklist manual em `SMOKE_CHECKLIST.md` |
| Desenvolvimento | Somente via Docker; sem PHP/Composer/npm no host |

---

## 7. Divergências conhecidas entre documentos

- `README.md` e `Talents-Structure.mdc` afirmam que a matriz granular de permissões admin foi removida; **o código ainda a usa** (`AdminPermissionModule`, `admin.can`, tela Equipe), com bypass para o Owner. Este PRD segue o código; corrigir as duas fontes.  
- `Talents-Structure.mdc` lista “contas bancárias/a receber em breve”; ambas estão **implementadas**.  
- `docs/avisos-matriz-cobertura.md`, `docs/testes-manuais-gestao-ponto.md` e `docs/marketing/welcome-copy-alternativas.md` constam no Git mas estão **ausentes do diretório de trabalho** (aparecem como excluídos em `git status`).

---

## 8. Referências no repositório

| Artefato | Caminho |
|----------|---------|
| Estrutura / regras do agente | `.cursor/rules/Talents-Structure.mdc` |
| Rotas | `routes/admin.php`, `routes/client.php`, `routes/web.php` e públicas por módulo |
| Permissões | `app/Enums/AdminPermissionModule.php`, `PermissionModule.php`, `PermissionAction.php`; `app/Http/Middleware/EnsureAdminPermission.php`, `EnsureModulePermission.php` |
| Módulos por plano | `app/Models/Module.php`, `Plan.php`, `Company::hasModuleEnabled` |
| Builder da Home Admin | `app/Support/Admin/AdminHomeDashboardBuilder.php` |
| NR-1 (cálculo) | `app/Support/Nr1Scoring.php`, `app/Services/SurveyResultCalculator.php`, `config/nr1.php` |
| NR-1 (documentos) | `app/Services/Psychosocial/*`, `ExecutiveBriefSnapshotBuilder.php`, `PsychosocialDashboardBuilder.php`, `PsychosocialMeasureService.php` |
| Comercial | `app/Services/Commercial/*`, `app/Support/Commercial/*` |
| Financeiro | `app/Support/Finance/*`, `app/Http/Controllers/Admin/Finance/*` |
| RHID | `app/Services/Rhid/*`, `tools/rhid-espelho-parser/` |
| IA | `app/Services/Interview/*`, `Meeting/*`, `Nr1AiAnalyzer.php`, `PurposeMapThemeAnalyzer.php` |
| Menus | `resources/js/Layouts/AdminLayout.vue`, `ClientLayout.vue` |
| Detalhe da empresa | `resources/js/Pages/Admin/Companies/Show.vue` |
| Infra | `docker-compose.yml`, `docker-compose.prod.yml`, `docker/` |
| Checklists | `SMOKE_CHECKLIST.md`, `USUARIOS_DEMO.md` |
