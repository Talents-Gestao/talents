# CLAUDE.md

Orientação para o Claude Code neste repositório. O contexto completo do produto (perfis, navegação, fluxos, regras, rotas, tabelas e enums) está em **`PRD.md`**; este arquivo resume o que é preciso para trabalhar sem quebrar regras. Em caso de dúvida sobre comportamento, consulte o PRD (capítulo indicado entre parênteses) e confirme no código.

# 1. Regras de Git (obrigatórias)

- Nunca commitar, fazer push, merge, rebase ou abrir PR sem autorização explícita do usuário na conversa.
- Ao pedir autorização, mostrar `git status` e a lista exata de arquivos que entrarão no commit.
- Usar sempre `git add <caminhos explícitos>`; nunca `git add -A` nem `git add .`.
- Trabalhar na branch atual. Não criar nem trocar de branch por conta própria: o usuário consolida o trabalho numa branch e abre um único PR para a `main`. Esta regra **prevalece** sobre `.cursor/rules/git-commit-push.mdc`, `git-pull-request.mdc` e `uma-branch-por-demanda.mdc`.
- Não incluir em commits alterações em `docs/` que não fazem parte da tarefa, salvo pedido expresso.

# 2. Regra de entrega (obrigatória)

Sem feature flags em implementações e ajustes novos.

1. O código novo vai direto ao comportamento final: validar localmente, passar nos testes, PR e deploy.
2. Não criar variável de ambiente, entrada em config ou parâmetro de URL para ligar/desligar funcionalidade nova.
3. Reversão por rollback do deploy anterior no Coolify ou revert do PR; isso consta na descrição do PR.
4. Fallbacks técnicos de compatibilidade (sem WebGL, movimento reduzido, navegador antigo) são permitidos: não são interruptores de produto.
5. Nada experimental vai para a `main`: seletores de teste, comparações A/B/C e parâmetros de depuração ficam só na branch local e saem antes do PR.

**Exceções (só com ordem explícita do usuário):** módulos que dependem de homologação ou decisão jurídica, migrations pesadas, mudanças que podem bloquear acesso, operações irreversíveis com dados reais ou dependentes de integração inexistente. Interruptores que existem hoje e permanecem como estão: `NR1_EXTERNAL_REVIEW_ENABLED` (padrão `false`), `NR1_EXTERNAL_REVIEW_MFA_REQUIRED` (`true`), `NR1_SOLUTIONS_REQUIRE_REVIEW` (`true`), `NR1_DUPLICATE_POLICY` (`none`), `NR1_CLIENT_DRAFT` (`false`), `MFA_REQUIRED_AT_LOGIN` (`false`), `MFA_STEP_UP_REQUIRED` (`true`). `NR1_SHELL_ENABLED` e `NR1_SHELL_LEGACY_EMBED` **não existem mais**. Toda variável `NR1_*`/`MFA_*` nova precisa estar no `.env.example` (o teste `EnvExampleCoversNr1AndMfaTest` falha se faltar).

# 3. Projeto

Talents: SaaS multi-tenant (Laravel 12 + Inertia 2 + Vue 3, Tailwind, Vite 7, PostgreSQL 16, Redis 7) da consultoria **Talents Gestão de Pessoas** (Várzea Paulista/SP). Serve a operação interna da consultoria (comercial, financeiro, clientes, contratação, tarefas, calendário) e entrega às empresas clientes um portal com os módulos contratados: NR-1 (riscos psicossociais), Voz do Time, feedbacks, férias, ponto (RHID), tarefas e calendário (PRD cap. 1).

Interface e domínio em **português do Brasil**: textos visíveis com acentuação correta; não alterar chaves de API, rotas ou identificadores só para acentuar. Valores em reais na tela e em **centavos** no banco (`*_cents`). Datas informadas pelo usuário e agendamentos no fuso **America/Sao_Paulo**.

# 4. Ambiente e comandos

Tudo roda em **Docker**, no ambiente que já existe (projeto `talents`, porta **8080**). Não usar PHP/Composer/npm do host, não criar containers, projetos de compose nem ambientes paralelos em outras portas.

```bash
docker compose up -d --build                         # app, nginx, postgres, redis, queue, scheduler → http://localhost:8080
docker compose exec app php artisan migrate --seed   # migrations e seeders sempre via container
docker compose exec app php artisan test             # PHPUnit (SQLite em memória, ver phpunit.xml)
docker compose exec app php artisan test --filter=NomeDoTesteOuMetodo
docker compose exec app php artisan test tests/Feature/Nr1ShellTest.php
docker compose exec app ./vendor/bin/pint <arquivos alterados>   # lint PHP só no que mudou
docker compose exec app php artisan optimize:clear   # rotas/config antigos ("Route [x] not defined")

docker compose --profile dev up vite                 # HMR em :5173
docker compose --profile tools run --rm node npm run build    # após pull/troca de branch sem HMR
docker compose --profile tools run --rm node npm run test:js  # node --test em tests/JavaScript/*.test.mjs
```

- `docker compose up/restart` não recompila o frontend; sem Vite dev, o navegador serve `public/build/`.
- Ao gerar relatório de testes no container, usar caminho **relativo** (`--log-junit=storage/logs/junit-x.xml`); caminho absoluto do Windows cria uma pasta lixo no projeto.
- Fila: `queue:work` com timeout de 1.800 s (áudios de entrevista e reunião, parser de espelho, PDFs). Agendamentos (`bootstrap/app.php`): 00:30 `rhid:import-closed-day`, 08:00 lembrete de tarefas, 08:15 `commercial:notify-overdue-installments`.
- Usuários de demonstração em `USUARIOS_DEMO.md` (após `migrate --seed`); empresa demo NR-1 com `db:seed --class=Nr1DemoSeeder` (só local/testing).

# 5. Superfícies e acesso (PRD cap. 2 a 4)

| Superfície | Prefixo | Quem | Layout |
|------------|---------|------|--------|
| Landing | `/`, `/para-empresas`, `/nr-1`… | público | `Landing/LandingLayout` |
| Admin Talents | `/admin/*` | `super_admin` | `AdminLayout` |
| Portal da empresa | `/client/*` | `company_admin`, `company_user` | `ClientLayout` (marca da empresa) |
| Painel NR-1 | `/nr1/*` | Talents, Empresa, Comercial; colaborador em `/nr1/jornada/{token}` | `AdminLayout`/`ClientLayout` + moldura `Nr1Frame` (jornada: só a moldura mínima) |
| Revisão externa | `/revisao/*` | `external_reviewer` (desligado) | páginas `ExternalReview/*` |
| Públicas por token | `/pesquisa`, `/denuncia`, `/sugestoes`, `/desligamento`, `/mapa-proposito`, `/avaliacao`, `/feedback/assinar`, `/satisfacao` | sem login | páginas públicas |

Arquivos de rota carregados em `bootstrap/app.php`: `web.php` (+ `auth.php`), `admin.php`, `client.php`, `nr1.php`, `external-review.php`, `survey.php`, `purpose-map.php`, `complaint.php`, `suggestion.php`, `methodology.php`, `feedback.php`, `offboarding.php`, `client-evaluation.php`.

**Workspaces** (`App\Support\WorkspaceManager`): a sessão roda num workspace `talents`, `company` ou `external_review`; com mais de um, o usuário escolhe em `/workspaces/selecionar`. Portal com marca em `/entrar/{slug}` (`CompanyPortal`) seleciona direto o workspace da empresa. Acesso por convite (link sem expiração até o primeiro uso); cadastro público desligado.

**Permissões do admin:** `EnsureSuperAdmin` + `admin.can:{módulo}[,ação]` (`EnsureAdminPermission`, `AdminPermissionModule`, `AdminUserPermission`). Ações: `view`, `create`, `edit`, `delete`, `validate`. `|` na rota = OR. O Owner (`is_owner`) ignora a matriz. Grants legados (`financeiro`, `comercial`, `solides`, `entrevistas`, `companies`) expandem para os filhos (`legacyExpansionMap`). Módulo novo sem backfill só aparece para o Owner até ser concedido em Configuração → Equipe (ex.: `nr1_oportunidades`).

**Permissões do portal:** duas condições sempre: módulo **habilitado na empresa** (`Company::hasModuleEnabled`: módulos NR-1 vêm do plano com a chave `nr1`; outros por plano ou override `*_access` = herdar/ligar/desligar) **e** permissão do usuário (`can.module:{módulo}[,ação]`, `EnsureModulePermission`; `company_admin` tem todas as dos módulos habilitados). Férias, Avaliações e Usuários exigem `company_admin`. `relatorios:validate` valida documentos NR-1. `capacitacao` é sempre falso; `metodologia` tem rotas comentadas no portal.

**Admin «por empresa selecionada»:** Feedbacks, Férias, Desligamento, Denúncias e Sugestões no admin reaproveitam os controllers do portal (concerns `Resolves*Routes`) e exigem empresa escolhida na sessão (`Ensure*CompanySelected`).

Esconder item de menu nunca é a única barreira: a rota precisa recusar (403/404) com a mesma regra. Ao criar tela ou rota, aplicar o middleware certo **e** o filtro por empresa na consulta.

# 6. Mapa do código

**Backend (`app/`)**
- `Http/Controllers/{Admin,Client,Auth,Nr1,ExternalReview,Survey,Complaint,Suggestion,Feedback,Offboarding,PurposeMap,ClientEvaluation}`: controllers finos.
- `Actions/`: uma operação por classe (`Notices/Publish*`, `Tasks/*`, `Commercial/*Commission*`, `Finance/*`, `Hiring/*`, `Leads/*`, `Surveys/ArchiveAndDeleteSurvey`).
- `Services/`: regras por domínio (`Psychosocial`, `Nr1`, `Commercial`, `Rhid`, `Solides`, `Interview`, `Meeting`, `Feedback`, `Offboarding`, `ClientEvaluation`, `Mfa`, `News`) e cálculo/painel NR-1 (`SurveyResultCalculator`, `PsychosocialDashboardBuilder`, `ExecutiveBriefSnapshotBuilder`).
- `Support/`: políticas e helpers puros (`Nr1Scoring`, `Nr1PooledItemScore`, `Nr1GroupPrivacy`, `Nr1BandLabels`, `SurveyPublicationGate`, `SurveyTemplateLock`, `WorkspaceManager`, `CompanyPortal`, `Finance/*`, `Commercial/*`, `Nr1Shell/*`, `Notices/*`).
- `Enums/` (33, com `label()` em PT-BR), `Jobs/`, `Mail/`, `Notifications/`, `Console/Commands/`.
- Validação inline (`$request->validate`) ou Form Request em `Http/Requests`, mensagens em português.

**Frontend (`resources/js`)**
- `Pages/{Admin,Client,Auth,Nr1,…}` (Inertia), `Layouts/`, `Components/` por domínio (`Nr1Shell/*`, `Nr1Campaign/*`, `Nr1Documents/*`, `Nr1Plan/*`, `Finance/*`, `Commercial/*`, `Tasks/*`, `Feedback/*`, `Rhid/*`, `AdminHome/*`, `Landing/*`), `composables/` (`usePermissions`…), `utils/`.
- Alias `@` → `resources/js`; rotas no JS via Ziggy (`route()`); ApexCharts, TipTap, vue-draggable-plus, PWA.
- Menus: `AdminLayout.vue` (itens condicionados por `canAdmin`), `ClientLayout.vue` (por `can`), sub-navegação do NR-1 em `Components/Nr1Shell/Nr1SubNav.vue` (itens vêm do servidor, `shell.menu`). Reuniões e Assinaturas estão ocultas no menu do admin por constante.
- Tema do painel NR-1 escopado em `.nr1-shell` (`resources/css/nr1-shell*.css`, carregado só pelo `Nr1Frame`), aplicado ao cabeçalho e ao conteúdo; a moldura (sidebar principal e sub-navegação) usa os tokens do Talents. Botões do painel só com `Nr1Button`.
- Animações respeitam `prefers-reduced-motion`.

# 7. Domínios e invariantes

## 7.1 NR-1 (PRD cap. 8) — o domínio mais sensível

- **Painel `/nr1` é a única interface do NR-1.** Telas antigas foram removidas; `LegacyUrlRedirectController` só redireciona URLs antigas. Perfis e seções em `App\Support\Nr1Shell\Nr1Shell` (`MATRIX`, `allows()`); contexto em `Nr1ShellContext`; página única `Pages/Nr1/Section.vue`, dentro do `AdminLayout` (Talents, Comercial) ou `ClientLayout` (Empresa), com a sub-navegação à esquerda do conteúdo (`Nr1Frame`/`Nr1SubNav`). Entrada: Talents pelo item «NR-1» do Admin (empresa lembrada ou escolha em `/nr1/empresas`) ou pela empresa («Pesquisas e parecer» → «Abrir painel NR-1»), Empresa pelo menu «Pesquisas NR1», Comercial por «Oportunidades NR-1».
- **Dois métodos:** `production_v1` (modelo anterior, COPSOQ, Laudo; padrão de toda campanha nova) e `talents39_v1` (V11: 39 itens, 7 dimensões, fluxo de revisão). `Survey::usesReviewFlow()` separa os dois. Não há campo na criação para escolher V11 (pendência).
- **Cálculo:** escore direto ou `6 − resposta` pela polaridade; índice V11 = soma dos escores ÷ respostas válidas, arredondado meio para cima, faixa decidida depois (`Nr1PooledItemScore`); cortes 2,33 / 3,66. Telas e documentos **leem** `survey_results`; nunca recalculam nem gravam índice/faixa fora de `SurveyResultCalculator`.
- **Rótulos de faixa** só por `Nr1BandLabels` (V11: Menor sinalização / Atenção / Maior sinalização; legado: Leve / Moderado / Grave). O banco guarda `green|yellow|red`.
- **Anonimato e grupos pequenos:** toda saída por setor (painel, PDF, exportação, IA) passa por `Nr1GroupPrivacy`: mínimo `max(5, min_group_size)`, setores pequenos em «Outros Setores» recalculado com respostas originais, supressão em cascata, composição nunca no payload da empresa. Não guardar identificador junto da resposta; `completed_at` só com o mês; V11 não coleta idade nem tempo de casa. Comercial só vê agregados liberados (`Nr1CommercialSerializer`). Dado bruto só no admin, com `nr1_dados_brutos`, step-up de MFA e log.
- **Gate de publicação:** campanha com `requires_publication` não mostra resultado, plano, exportação nem documento à empresa antes de `published_at` (`SurveyPublicationGate`, middleware `survey.published`). Campanhas antigas sem gate seguem visíveis (D9). A empresa nunca recebe props da Talents (Mia, URLs de geração, campanha não publicada).
- **Documentos:** liberar congela snapshot (JSON canônico + hash) numa versão; o PDF é impressão do snapshot; validar cria versão-filha com o mesmo hash; recalcular não muda documento emitido; lacuna fica nula, nada é inferido; checklist de publicação olha o snapshot (sem «CNPJ não informado» nem elegíveis fictícios). V11: rodada `draft → in_review → changes_requested | approved → published` (`ReviewRoundService`); publicar exige step-up de MFA.
- **Plano de ação V11:** `psychosocial_measures`, ciclo `to_approve → pending → in_progress → implemented` (+ `replanned`), sugerido × confirmado, evidências, eficácia e histórico. Não mexe em `action_plan_items` (plano do legado).
- **Comercial do NR-1:** oportunidade só por pedido explícito (nunca por visualização), sem duplicar pedido aberto da mesma modalidade/público; Soluções só após publicação e com regra revisada.
- **Instrumento versionado:** depois que a campanha coleta, texto/dimensão/polaridade/escala/peso/ordem não mudam no lugar (`SurveyTemplateLock`): vira nova versão. Polaridade V11 pendente até `nr1:accept-polarity`.
- **Revisão externa (`/revisao`) desligada** até o MFA ser testado com autenticador real e o responsável autorizar.
- Comandos `nr1:*` para recalcular, auditar, importar instrumento, gerar convites e diagnósticos (PRD 8.4.5). Requisitos da V11 em `docs/nr1-v11/CADERNO_NR1_V11.md`; protótipo aprovado em `docs/nr1-v11/Talents_NR1_V11_Aprovacao_Final.html`.

## 7.2 Comercial e Financeiro (PRD 7.2 e 7.10)

- Proposta (`commercial_proposals`, `list_status`: `open`/`closed`/`ended`) → contrato por modelo com placeholders (`ContractPlaceholderService`) → ZapSign → venda (`ProposalSaleConversionService`) com parcelas e comissões. Fechar proposta cria Acompanhamento quando o CNPJ bate com uma empresa (`CreateHiringProcessFromClosedProposal`).
- Preço só pelo catálogo (`CommercialPricingService`, `CommercialProductPricingType`); totais `svc_*` são legado.
- **Comissão:** fonte única `CommissionPayoutCalculator` (teto = % × parcelas não canceladas; liberável = % × recebido − pago). Pagamento exige conta bancária ativa e gera conta a pagar baixada e travada; só o último pagamento pode ser desfeito; perda assumida quando o teto cai abaixo do pago. Pagamento de comissão não entra em `FinanceCashflowMetrics`.
- Parcelas canceladas fora do total e do status da venda; venda com pagamento de comissão não pode ser excluída.
- Contas a receber = ledger em SQL (`FinanceReceivableLedger`); data de pagamento corrigida só por `ChangeFinancePaymentDate` (auditada em `finance_audit_logs`); saldo bancário por `FinanceBankAccountBalance` (só movimentos pagos).
- Saneamento de dados antigos: `commercial:recalculate-sale-statuses` (dry-run; `--apply`).

## 7.3 Demais domínios

- **RHID/Ponto (PRD 7.12 e 9.15):** `Services/Rhid` (`RhidClient`, `RhidAuthService`), credenciais por empresa criptografadas, toda chamada em `rhid_audit_logs`; espelhos em PDF com parser Python (`tools/rhid-espelho-parser`) na fila.
- **IA (Mia):** provedor OpenAI/Anthropic e chave Whisper em `/admin/settings`, criptografados com `APP_KEY` (`app:check-encryption`). Entrevistas e reuniões dependem de `ffmpeg` e da fila; Mapa de Propósito cai para frequência de palavras sem IA.
- **SMTP:** habilitado no painel, sobrescreve `MAIL_*` após o boot (`MailSetting::applyToRuntimeConfig`).
- **Denúncias e sugestões:** conteúdo criptografado (`SafelyDecryptsAttributes` mostra placeholder se a chave mudar); protocolo `DEN-AAAA-NNNNN`; avisos nunca incluem a descrição; auditoria em `complaint_audit_logs`.
- **Tarefas:** visibilidade cartão × lista (`TaskCardVisibility`); empresa só move para lista `company` com `allow_company_drop_in`; recorrência cria o próximo cartão ao concluir.
- **Avisos:** sempre por uma Action `Publish*Notice`; visibilidade dos avisos NR-1 por `NoticeVisibility`.
- **URLs externas** chamadas pelo servidor passam por `SafeExternalUrl` (anti-SSRF).

# 8. Testes

- PHPUnit em `tests/Feature` e `tests/Unit`, SQLite em memória, `QUEUE_CONNECTION=sync`: testes que disparam jobs de IA precisam de `Queue::fake()`. Chaves de IA/ZapSign/Sólides são falsas em `phpunit.xml`.
- JS: só lógica pura em `tests/JavaScript/*.test.mjs` (`node --test`); não há E2E nem Vitest.
- Coberturas que não podem regredir: isolamento entre empresas (IDOR), gate de publicação, privacidade de grupos pequenos (inclusive varredura do payload do painel NR-1), MFA/step-up, cálculo V11, comissões.
- Checklist manual de go-live: `SMOKE_CHECKLIST.md`.
- Pronto = testes verdes + build do frontend + textos em PT-BR correto + nenhuma regra do PRD contrariada.

# 9. Documentação

- `PRD.md` é o documento de produto. Quando uma entrega mudar comportamento, tela, rota, permissão ou regra, atualize a seção correspondente do PRD na mesma tarefa (e os apêndices quando a mudança afetar rotas, tabelas ou enums).
- Não criar novos arquivos de documentação ou relatório em `docs/` sem pedido; o contexto de trabalho fica no próprio PRD e nesta conversa.
- Documentos com afirmações desatualizadas (corrigir quando a tarefa for de documentação): `README.md` e `.cursor/rules/Talents-Structure.mdc` dizem que a matriz de permissões do admin foi removida (ela está em uso); `Talents-Structure.mdc` lista contas bancárias/a receber como «em breve» (estão implementadas).
