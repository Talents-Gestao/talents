# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# Regras de Git (obrigatórias)
- Nunca commitar, fazer push, merge ou rebase sem autorização explícita do usuário na conversa.
- Ao pedir autorização, mostrar `git status` e a lista exata de arquivos que entrarão no commit.
- Usar sempre `git add <caminhos explícitos>`, nunca `git add -A` ou `git add .`.
- Nunca incluir em commits as alterações pré-existentes em docs/ (PRD.md modificado e os 3 docs deletados), salvo pedido expresso.
- PRD do projeto: docs/PRD.md.
- Trabalhar na branch atual; não criar/trocar de branch nem abrir PR por conta própria. Isso **prevalece** sobre `.cursor/rules/git-commit-push.mdc` e `git-pull-request.mdc`, que mandam commitar, dar push e abrir PR automaticamente.

# Projeto

Talents: SaaS multi-tenant em Laravel 12 + Inertia.js + Vue 3 (Tailwind, Vite) para a consultoria Talents Gestão de Pessoas — conformidade NR-1 (riscos psicossociais), comercial, contratação com IA, ponto (RHID) e demais módulos de RH. Interface e domínio em português (PT-BR): em textos visíveis use acentuação correta; não altere chaves de API, rotas ou identificadores só para acentuar.

# Comandos

Tudo roda em **Docker** — não use PHP/Composer/npm do host (banco Postgres, Redis, filas e extensões só existem nos containers).

```bash
docker compose up -d --build                         # stack (app, nginx, postgres, redis, queue) em http://localhost:8080
docker compose exec app php artisan migrate --seed   # migrations/seeders sempre via container
docker compose exec app php artisan test             # suíte PHPUnit (SQLite em memória, ver phpunit.xml)
docker compose exec app php artisan test --filter=NomeDoTesteOuMetodo
docker compose exec app php artisan test tests/Feature/Nr1ReportsTest.php
docker compose exec app ./vendor/bin/pint            # lint PHP (Laravel Pint)
docker compose exec app php artisan optimize:clear   # rotas/config antigos ("Route [x] not defined")

docker compose --profile dev up vite                 # HMR em :5173 (não precisa de build no dia a dia)
docker compose --profile tools run --rm node npm run build   # necessário após pull/troca de branch se não usar HMR
docker compose --profile tools run --rm node npm run test:js # testes JS (node --test em tests/JavaScript/*.test.mjs)
```

`docker compose up/restart` não recompila o frontend; o browser serve `public/build/` quando o Vite dev não está ativo.

# Arquitetura

**Cinco superfícies, um app Laravel.** `bootstrap/app.php` carrega vários arquivos de rota em `routes/` (`admin.php`, `client.php`, `survey.php`, `complaint.php`, `feedback.php`, `offboarding.php`, `client-evaluation.php`, `purpose-map.php`, `suggestion.php`, `methodology.php`, `web.php`, `auth.php`):
- `/admin/*` — painel interno Talents (somente `super_admin`, middleware `EnsureSuperAdmin`; permissões granulares por módulo/ação via `AdminPermissionModule`, `EnsureAdminPermission` e `AdminUserPermission`; o owner Talents tem acesso total).
- `/revisao/*` — revisor externo (organização aprovadora, `UserRole::ExternalReviewer`, `WorkspaceType::ExternalReview`): só lê, comenta e aprova versões de documento compartilhadas explicitamente. **Desligado por padrão** (`config/nr1.php` → `external_review.enabled`) até o MFA existir.
- `/client/*` — painel por empresa (`company_admin` com acesso total aos módulos habilitados; `company_user` com permissões granulares por módulo/ação via `EnsureModulePermission`).
- Rotas públicas por token (pesquisas NR-1 `/pesquisa/{token}`, denúncias, desligamento, assinatura de feedback) com rate limit em `config/public_rate_limits.php`.
- Landing/marketing em `Welcome.vue` e páginas soltas de `resources/js/Pages`. Cadastro público está desativado (acesso por login/convite).

**Contexto de workspace.** A sessão opera em um workspace `Talents` ou `Company` (`app/Support/WorkspaceManager.php`, `CompanyPortal`). O módulo do cliente só aparece se a empresa o tem habilitado no plano (`hasModuleEnabled`). Owner Talents (`is_owner`) tem bypass especial nas permissões de empresa. Vários middlewares `Ensure*CompanySelected` exigem empresa selecionada no admin (feedbacks, férias, denúncias, etc.).

**Camadas backend** (`app/`): Controllers finos em `Http/Controllers/{Admin,Client,...}`; regras de negócio em `Actions/` (uma operação por classe, ex. `Notices/Publish*`, `Tasks/*`), `Services/` (por domínio: `Psychosocial`, `Rhid`, `Solides`, `Interview`, `Meeting`, `Commercial`, `Feedback`, `Offboarding`...) e `Support/` (helpers/enrichers puros, ex. `Nr1Scoring`, `StrategicCalendar*`). Enums de domínio em `Enums/`, jobs de IA/processamento em `Jobs/`.

**Domínios com acoplamento entre vários arquivos:**
- *NR-1*: templates de pesquisa versionados (`SurveyTemplateVersioningService`, `SurveyInstrumentVersionResolver`) → respostas anônimas → cálculo (`SurveyResultCalculator`, `Nr1Scoring`, `Nr1PooledItemScore`) → dashboards/insights/plano de ação/PDF e documentos (AEP/PGR/parecer em `Services/Psychosocial`). Há comandos Artisan `nr1:*` para recalcular/auditar/reconstruir. Pesquisas são anônimas — não vazar identidade de respondentes.
- *Comercial*: proposta → contrato (ZapSign) → venda/financeiro; templates de contrato canônicos (`CanonicalContractTemplates`, comando `SyncCanonicalContractTemplates`); planos SaaS definem os módulos da empresa.
- *RHID/Ponto*: integração Control iD via `Services/Rhid` (`RhidClient`, `RhidAuthService`), espelhos de ponto em PDF (`EspelhoPdfIngestService`, parser auxiliar em `tools/rhid-espelho-parser`).
- *IA (Mia)*: provedor OpenAI/Anthropic configurado em `/admin/settings` (guardado criptografado com `APP_KEY`; `php artisan app:check-encryption` diagnostica). Entrevistas (áudio → Whisper → relatório) e reuniões (ata) dependem de ffmpeg e da fila Redis.
- *Config SMTP*: com SMTP habilitado no painel, valores do banco sobrescrevem `MAIL_*` após o boot.

**Frontend** (`resources/js`): Inertia + Vue 3 (script/composition), páginas em `Pages/{Admin,Client,...}`, layouts em `Layouts/`, componentes em `Components/`, helpers em `composables/` e `utils/`. Alias `@` → `resources/js`. Rotas no JS via Ziggy. Gráficos com ApexCharts, editor rich text com TipTap, PWA via `vite-plugin-pwa`.

# Testes

- PHPUnit: `tests/Feature` e `tests/Unit`, SQLite em memória; `QUEUE_CONNECTION=sync`, então testes que disparam jobs de IA precisam de `Queue::fake()`. Chaves de IA/ZapSign/Solides são forçadas a valores falsos/vazios em `phpunit.xml`.
- JS: apenas lógica pura em `tests/JavaScript/*.test.mjs` (runner nativo `node --test`); não há E2E.
- Checklist manual de go-live em `SMOKE_CHECKLIST.md`; usuários de demonstração em `USUARIOS_DEMO.md` (após `migrate --seed`).
