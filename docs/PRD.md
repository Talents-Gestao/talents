# PRD — Talents (produto atual)

Documento vivo do produto, alinhado ao código do repositório `talents/`.  
Complementa `.cursor/rules/Talents-Structure.mdc`.  
Última validação no código: branch de trabalho atual (não assume deploy em produção).

---

## 1. Visão do produto

A **Talents Gestão de Pessoas** é:

1. Consultoria estratégica em gestão de pessoas (Várzea Paulista/SP).
2. SaaS multi-tenant (Laravel + Inertia/Vue) para operação Admin e portal Cliente.

Pilares de metodologia na landing: Metamorfose Comportamental, Contratação de Talentos, Direcionamento Estratégico, Gestão de Riscos Psicossociais (NR-1).

Cadastro público desativado; acesso por login/convite.

---

## 2. Produto atual (implementado no código)

### 2.1 Landing / Welcome

| Capacidade | Estado |
|------------|--------|
| Hero com título **“O resultado que você espera está nas pessoas!”** e subtítulo **“Potencialize resultados com gestão de pessoas, ciência e estratégia.”** | Implementado (`Welcome.vue` + `LandingHeroTypewriter.vue`) |
| Typewriter no **H1** (loop digitar/apagar, tipografia bold da 1ª versão, altura estável via texto fantasma; respeita `prefers-reduced-motion`) | Implementado |
| Leads com campo **origem (`source`)** no formulário de contacto e fluxo admin | Implementado (`LandingInterestSource`, `LandingInterestSourceField`, etc.) |

### 2.2 Painel operacional (Admin Home — `/admin`)

| Capacidade | Estado |
|------------|--------|
| Layout: **3 cards** no topo — Financeiro, Comercial, **Calendário · hoje** | Implementado |
| KPIs em grelha de **3 colunas** (7º card isolado na mesma largura de coluna) | Implementado |
| Financeiro: links **Contas a pagar** (esq.) e **Contas a receber** (dir.) | Implementado |
| Polish visual por domínio (acentos esmeralda / violeta Talents / sky, hovers, timeline do calendário) | Implementado |
| Frase do dia no painel | Implementado (`dailyQuote` partilhado) |
| CTAs no header: tarefas ADM abertas + **Pendências** (`alertsCount`) | Implementado |
| Payload Inertia enxuto (sem listas mortas na página) | Implementado no `DashboardController` |
| Indicadores via `AdminHomeDashboardBuilder` | BD real + heurísticas (conversão, funil, fluxo previsto, etc.) + **meta mensal** por `config/talents.php` / `TALENTS_DASHBOARD_MONTHLY_GOAL_CENTS` |

### 2.3 Assinaturas (ex-“Planos”)

| Capacidade | Estado |
|------------|--------|
| Label de menu / permissão: **Assinaturas** | Implementado (`AdminLayout`, `AdminPermissionModule::Plans`) |
| Rotas e código interno `plans` mantidos | Implementado |
| CRUD UI **sem** preço mensal nem máx. colaboradores | Implementado (colunas BD / preço 0 na criação podem permanecer) |

### 2.4 Empresas — detalhe Admin

Abas **atuais** no `Companies/Show.vue`:

- Empresa  
- Gestão de ponto (se permissão `rhid`)  
- Colaboradores  
- Regulamento interno  

### 2.5 Regulamento interno

| Capacidade | Estado |
|------------|--------|
| Rich text + **anexo** (PDF/DOC/DOCX) na criação/edição | Implementado (`InternalRegulations/Form.vue`, migration `file_path` / `file_name`, download) |

### 2.6 Módulos Admin que continuam (fora do detalhe da empresa)

- Hub **RHID** (`/admin/rhid`) — portfólio / métricas  
- **Destaques do mês** (`/admin/destaques-mes`)  
- **Férias** Admin (`/admin/ferias`, com seleção de empresa)  
- Demais módulos: Comercial, Contratação, Reuniões, Feedbacks, Voz do Time, Calendário, Tarefas, Financeiro, Configuração, etc. (ver `Talents-Structure.mdc`)

### 2.7 Painel Cliente

Inalterado na narrativa desta atualização: dashboard, Voz do Time, acompanhamento, metodologia, feedbacks, férias (`company_admin`), calendário, tarefas, RHID/ponto, utilizadores, etc.

---

## 3. Depreciado / removido da UI

Distinção: **removido da superfície** (ainda pode existir hub/rota) vs **descontinuado**.

| Item | Antes | Agora | Tipo |
|------|--------|--------|------|
| Card “Pendências e alertas” na Home | Visível na linha principal | Removido | Removido da superfície; pendências via CTA header / notificações |
| Props Home `recentLeads`, `upcomingCalendar`, `calendarKindLabels`, lista `alerts` | Enviadas ao Inertia | **Não** enviadas pelo `DashboardController` | Limpeza de payload (builder pode ainda calcular dados internos para `alertsCount`) |
| Label “Operação · hoje” | Título do 3º card | **Calendário · hoje** | Renomeado |
| Aba Portfólio RHID no detalhe da empresa | Tab no Show | Removida do detalhe | Removido da superfície; hub `/admin/rhid` mantém-se |
| Aba Destaques do mês no detalhe | Tab | Removida do detalhe | Removido da superfície; CRUD `/admin/destaques-mes` mantém-se |
| Aba Férias no detalhe | Tab | Removida do detalhe | Removido da superfície; `/admin/ferias` + Client Férias mantêm-se |
| Uniformes / “Controle da empresa” | Tab placeholder | Removida | Removido da superfície |
| Label menu “Planos” | Comercial → Planos | **Assinaturas** | Renomeado na UI |
| Campos preço / máx. colaboradores no CRUD de plano | Visíveis | Removidos da UI | Removido da superfície; schema/colunas podem permanecer |

---

## 4. Planejado / em curso (não documentar como “em produção”)

Itens já referidos no produto como “em breve” e ainda não fechados como módulo completo:

- Contratos fechados (Clientes)  
- Profiler (Contratação)  
- Capacitação (Admin e Cliente)  
- Contas bancárias / a receber (Financeiro Admin)  

Qualquer feature só descrita em prompts e **ainda não** refletida no código deve ficar nesta secção até validação.

---

## 5. Nota sobre dados do Painel operacional

Os números da Home **não** são mocks no Vue. Classificação típica:

- **Real (BD):** parcelas, payables, leads, propostas, empresas, vendas, itens de calendário, etc.  
- **Heurística:** taxa de conversão, funil simplificado, fluxo previsto, tempo médio de contratação, hora parseada da descrição do evento, “negociação” = propostas abertas.  
- **Config:** meta de faturamento mensal (`TALENTS_DASHBOARD_MONTHLY_GOAL_CENTS`, default R$ 20.000).  
- **Catálogo:** frase do dia (`MetamorfoseDailyQuote`).  

Zeros no ecrã = falta de dados / filtros / preços de plano a 0 — não placeholder de UI.

---

## 6. Referências no repositório

| Artefacto | Caminho |
|-----------|---------|
| Estrutura / regras do agente | `.cursor/rules/Talents-Structure.mdc` |
| Builder da Home Admin | `app/Support/Admin/AdminHomeDashboardBuilder.php` |
| Controller Home | `app/Http/Controllers/Admin/DashboardController.php` |
| UI Home | `resources/js/Pages/Admin/Dashboard.vue` |
| Hero landing | `resources/js/Pages/Welcome.vue`, `resources/js/Components/Landing/LandingHeroTypewriter.vue` |
| Detalhe empresa | `resources/js/Pages/Admin/Companies/Show.vue` |
| Assinaturas | `resources/js/Pages/Admin/Plans/*`, `AdminLayout` |
| Regulamento | `resources/js/Pages/Admin/InternalRegulations/*` |
