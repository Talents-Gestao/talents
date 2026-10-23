# Redesign Talents — Fase 0: Diagnóstico

Branch: `feature/redesign-visual` (base `new-features` @ `564ab83`). Data: 22/10/2026.
Escopo desta fase: **somente leitura**. Nenhum código foi alterado.

> **Limitação de medição:** o daemon do Docker não estava ativo, então **não rodei build, Lighthouse nem testes**. A linha de base de bundle abaixo vem de `public/build/` (gerado em 16/10, ignorado pelo Git), que **pode estar defasado** em relação ao código atual. Lighthouse e Web Vitals ficam pendentes. Os dois precisam ser refeitos com o Docker ligado antes da Fase 1 (ver §6).

---

## 1. Estado visual atual

### 1.1 Stack e configuração
- Tailwind **3.x** (`tailwind.config.js`) com `@tailwindcss/forms`. O `package.json` também lista `@tailwindcss/vite ^4` e `bootstrap ^5`, mas ambos parecem sem uso real (Bootstrap só aparece num `manualChunks` do Vite; o `resources/js/bootstrap.js` configura o axios). **A confirmar**.
- Uma única folha de estilos: `resources/css/app.css` (596 linhas), com classes próprias (`app-shell`, `surface-card`, `landing-card-rich`, `dashboard-panel*`, `btn-primary/secondary/ghost`, `field-input`…).
- Fonte: **Figtree** (400/500/600) via fonts.bunny.net (terceiro, sem self-host). Não há peso 700 carregado, mas pode haver `font-bold` no código (**a confirmar**).
- Ícones: Heroicons.
- Dark mode: praticamente inexistente (1 ocorrência de `dark:`).
- `resources/js/Components/ui/` **não existe**. Os componentes base estão soltos em `Components/` (PrimaryButton, SecondaryButton, DangerButton, TextInput, InputLabel, Modal, ConfirmModal, Dropdown, Checkbox…).

### 1.2 Identidade atual (a evoluir)
| Token | Valor | Observação |
|---|---|---|
| `talents-50…900` | `#f5f0fa` → `#2a1042` | Roxo derivado do logo; primária real é **talents-600 `#632a7e`** |
| `talents-accent` | `#b388d9` | Igual a talents-300 (duplicado) |
| `highlight` | `#e8b84a` / `#f5e6c8` | Dourado; uso pontual (ex.: barra de progresso em `Survey/Take.vue`) |
| Neutros | `slate` (4.346 usos) + `gray` (657) | Frios; duas escalas misturadas |
| Semânticas | emerald (471), red (275), rose (270), amber (398), sky (93) | Sem token; escolhidas à mão em cada tela |

Há muito lavanda/roxo e cinza-azulado; falta uma segunda cor de marca e um acento forte para CTA.

### 1.3 Superfícies e telas
| Superfície | Arquivos | Layout |
|---|---|---|
| Landing (10 páginas) | `Pages/{Welcome,ParaEmpresas,ParaPessoas,NossosClientes,Sobre,Contato,Nr1,DiagnosticoComportamental,ContratacaoTalentos,DirecionamentoEstrategico}.vue` | `Components/Landing/LandingLayout.vue` |
| Por token | `Survey/{Take,ThankYou,Closed}`, `Complaint/{Submit,Thanks,Track,ReporterView}`, `Suggestion/{Submit,Thanks}`, `PurposeMap/{Take,ThankYou,Closed}`, `Methodology/SurveyTake`, `Offboarding/Public/*`, `Feedback/Sign`, `ClientEvaluation/*` | Sem layout compartilhado; cada tela monta o próprio |
| Admin | 88 `.vue` em `Pages/Admin` | `AdminLayout.vue` (710 linhas) sobre `SidebarLayout` |
| Cliente | 50 `.vue` em `Pages/Client` | `ClientLayout.vue` (271 linhas) sobre `SidebarLayout` |
| Auth | `Pages/Auth/*` (Login com portal por empresa) | `GuestLayout` |

Total: **307** arquivos `.vue` (122 em `Components`).

---

## 2. Inconsistências encontradas (priorizadas)

### P0 — bloqueiam o redesign ou ferem acessibilidade/regra do produto
1. **Tema por empresa quase inexistente.** `ClientLayout` define `--portal-primary`, mas **nenhum CSS ou componente consome a variável**. A cor da empresa chega por `:style` inline pontual (`Login.vue`, `SidebarBrandMark :accent-color`). Não há cálculo de contraste: uma cor clara escolhida pela empresa pode deixar texto ilegível. A Fase 1 precisa criar o mecanismo de verdade.
2. **Contraste insuficiente em volume.** Medido (WCAG): `talents-300/branco` **2,82**; `talents-400/branco` **3,97**; `highlight/branco` **1,84**; `slate-400/branco` **2,56**; `slate-400/talents-50` **2,29**. Há **174** `text-slate-400` e 24 `text-slate-300`, muitos usados como texto informativo. Todos falham o AA (4,5:1). Passam: `talents-600/branco` 9,71, `talents-500/branco` 6,03, `slate-500/branco` 4,76, `emerald-700/emerald-50` 5,21, `amber-800/amber-50` 6,84.
3. **Texto minúsculo.** 198 usos de `text-[9–11px]` e 1.220 de `text-xs`. Abaixo de 12px em UI densa prejudica a leitura.
4. **Foco removido.** 102 `outline-none`. Há 906 variantes `focus:*`, então boa parte tem substituto, mas não há padrão único; precisa auditar.
5. **Imagens sem `alt`:** 14 das 33 tags `<img>` (a conferir se há `:alt` dinâmico, que o grep não pega).
6. **Faixas NR-1 sem token.** `Nr1RiskLevelLegend.vue` já combina bolinha de cor + texto + intervalo (bom). Mas as cores são `emerald/amber/red` fixas, repetidas em outros componentes, e os gráficos do `Nr1SurveyResultsPanel` usam hex soltos (`#64748b/#94a3b8/#cbd5e1`). O vermelho puro para "Grave" é forte para um tema que não pode alarmar; precisa de decisão.

### P1 — duplicação e falta de sistema
7. **Botões sem padrão.** 531 `<button>` escritos à mão, 212 usos de `PrimaryButton/SecondaryButton/DangerButton` e 45 de classes `btn-*`. Três jeitos coexistem.
8. **Cores hex soltas:** 25 arquivos com hex, 30 valores arbitrários `bg-[#…]`, 69 `style=` inline. Mais frequentes: `#334155`, `#ffffff`, `#632a7e` (a própria primária, hardcoded 13×).
9. **Modais duplicados.** 24 `<Modal>` e 22 `<FullScreenOverlay>` convivem; 12 arquivos montam `fixed inset-0` à mão. Há também o `ConfirmModal` global.
10. **Raios sem escala.** `rounded-xl` 578, `rounded-lg` 370, `rounded-full` 320, `rounded-md` 273, `rounded-2xl` 192.
11. **Tabelas:** 72 `<table>` próprias; só 10 usam `TableEmptyRow` e 5 usam `ListEmptyState`. Não há componente de tabela, ordenação nem versão responsiva padrão.
12. **Carregamento:** praticamente sem skeleton (1 arquivo). Spinners em 7 pontos, `animate-pulse` em 4.
13. **Semânticas à mão:** `rose` (270) e `red` (275) coexistem para "perigo"; `gray` e `slate` para neutro.

### P2 — dívida e oportunidade
14. **`dashboard-panel*`** usa 2–3 gradientes radiais + `hover:-translate-y`, sem regra para `prefers-reduced-motion` (só `landing-card-rich` trata).
15. **Gráficos:** o ApexCharts é usado em apenas um componente de gráfico + `MiniSparkline`, com cores fixas e sem descrição textual alternativa.
16. **PWA:** `theme_color #632a7e`, ícone SVG único marcado `any maskable` (o ideal é separar `any` de `maskable`).
17. **Não há `data-testid` no código** nem teste de componente/E2E de interface. Nada a quebrar por seletor, mas a rede de segurança visual é só o `SMOKE_CHECKLIST.md` manual + PHPUnit (62 arquivos em `tests/Feature` e `tests/Unit` pela contagem de hoje; o PRD cita ~160) + testes Node de utilitários.
18. `Welcome.vue` não tem `<h1>` próprio: o H1 está em `LandingHeroTypewriter.vue` (ok), mas o typewriter precisa reservar altura para não gerar CLS.

---

## 3. SEO / E-E-A-T — estado atual das páginas públicas

**O que já existe (e será preservado):**
- `LandingLayout.vue` injeta `title`, `meta description`, Open Graph, Twitter Card e `canonical` via `<Head>` (props `title` e `description` obrigatórias).
- `Welcome.vue` já tem um bloco **JSON-LD** de organização (`head-key="jsonld-org"`); a revisar.
- `/sitemap.xml` com `loc` + `lastmod` (rota em `routes/web.php`, view `sitemap.blade.php`).
- 9 das 10 páginas têm 1 `<h1>` no próprio arquivo; a Welcome tem no componente do hero.
- Logos de clientes no repositório: `public/images/clientes/*` (11 arquivos). Fonte para a faixa de clientes, **mas falta confirmar a autorização de uso de cada logo**.

**Lacunas:**
- **`robots.txt` com `Disallow:` vazio**: permite tudo e **não bloqueia** `/admin`, `/client`, `/entrar/*`, `/pesquisa/*`, `/denuncia/*` etc. O `Sitemap:` está **relativo** (`/sitemap.xml`); o padrão exige URL absoluta.
- **Nenhum `noindex`** no frontend, nas rotas nem em header (`X-Robots-Tag`). As telas por token e logadas são indexáveis em tese. Corrigir exige meta no `<Head>` (frontend) e, idealmente, header (PHP; precisa da sua aprovação).
- **Sem Política de Privacidade, Termos de Uso nem aviso de cookies/LGPD** nas páginas públicas (a busca por "privacidade|lgpd|cookie|termos" só acha `Nr1.vue` e o `bootstrap.js`, este último por motivo alheio).
- **Sem CNPJ, razão social nem endereço completo.** Rodapé e `/contato` têm só e-mail (`contato@talentsgestao.com`), telefone `(11) 3109-6837` e "Várzea Paulista · SP". O restante vira `TODO-CONTEUDO`.
- **Sem equipe, autor/revisor, datas de publicação ou "atualizado em"** em `/sobre` e `/nr-1`.
- **Sem casos/depoimentos, FAQ estruturado, `BreadcrumbList`, `Service`, `Article` ou `Person`** nos dados estruturados.
- **Imagens pesadas e em PNG:** `pilares-borboleta.png` 600 KB, `hero-home.png` 363 KB, `passeg.png` 251 KB, `pasqualino.png` 177 KB, `Giraffas.png` 160 KB; sem WebP/AVIF. Falta conferir `width/height` e `loading="lazy"`.
- Títulos seguem o padrão "Talents — X". Pelo grep, só 3 páginas declaram `<Head>` próprio (as demais passam `title`/`description` ao `LandingLayout`); a unicidade de cada `description` fica para a Fase 3.

---

## 4. Linha de base de performance (proxy; ver limitação)

Fonte: `public/build/` de 16/10/2026 (302 arquivos em `assets/`).

| Item | Bruto | Gzip |
|---|---|---|
| JS total (todos os chunks) | ~3,75 MB | — |
| CSS total | ~197 KB | — |
| `app-*.css` (principal) | 184 KB | **26 KB** |
| `app-*.js` (entrada) | 240 KB | **78 KB** |
| `apexcharts-*.js` | 1,05 MB | **283 KB** |
| `tiptap-*.js` | 412 KB | **137 KB** |
| Maiores páginas isoladas | `Compliance` (RHID) 107 KB; `Index` 89 KB; `KanbanBoard` 79 KB | — |
| `public/images` | 2,2 MB | — |

O ApexCharts e o TipTap estão em chunks separados (bom). Pelo `manualChunks` eles não deveriam entrar na landing, mas isso **não foi verificado** no HTML servido. **Lighthouse (Performance/A11y/SEO/Boas práticas) e Web Vitals: pendentes**, exigem o Docker no ar.

### 4.1 Linha de base medida (Docker ligado, 23/10/2026)

- `npm run build`: ok. Maiores chunks: `apexcharts` 1021,7 KB, `tiptap` 401,9 KB, `app` 236,9 KB.
- `npm run test:js`: 30/30. PHPUnit: 849 passam, **77 falham** (lista por nome em `baseline-testes-falhando.txt`).

### 4.2 Lighthouse (preencher manualmente)

| Página | Performance | Acessibilidade | Boas práticas | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|
| `/` | | | | | | | |
| `/para-empresas` | | | | | | | |
| `/para-pessoas` | | | | | | | |
| `/nossos-clientes` | | | | | | | |
| `/sobre` | | | | | | | |
| `/contato` | | | | | | | |
| `/nr-1` | | | | | | | |
| `/diagnostico-comportamental` | | | | | | | |
| `/contratacao-de-talentos` | | | | | | | |
| `/direcionamento-estrategico` | | | | | | | |

---

## 5. Riscos de regressão

| Risco | Prob. | Mitigação proposta |
|---|---|---|
| Mexer em `app.css`/classes globais (`btn-*`, `surface-*`, `dashboard-panel*`) afeta as 307 telas de uma vez | Alta | Componentes novos **drop-in**; classes antigas viram alias dos tokens antes de qualquer troca visual |
| 531 botões manuais + 72 tabelas migradas sem teste visual automatizado | Alta | Migrar por módulo, em commits separados; `/admin/design-system` como vitrine; smoke por módulo |
| Cor de marca da empresa sem contraste (hoje quase inexistente, passará a existir) | Média | Cálculo automático de texto claro/escuro + fallback seguro, com teste Node |
| Mudar cores/gráficos do NR-1 e perder o sentido de Leve/Moderado/Grave | Média | Cor + ícone + texto sempre; **decisão de paleta sua** antes de implementar |
| Cache do service worker servindo CSS antigo após deploy | Média | JS já está fora do precache; o CSS continua no precache, revisar |
| Typewriter do hero gerar CLS | Média | Reservar altura com a frase final invisível |
| Telas por token ganharem `noindex` só no front e já estarem indexadas | Baixa | Header `X-Robots-Tag` no backend (precisa de aprovação para tocar PHP) |
| Alterações em PHP (`robots.txt`, sitemap, header) | — | Só com sua confirmação explícita |

Nada em `Nr1Scoring`, `SurveyResultCalculator`, snapshots/hashes, controllers, services, models, rotas ou props do Inertia precisa ser tocado para o redesign.

---

## 6. Proposta de ordem de execução

**Pendências antes da Fase 1:**
- Ligar o Docker; rodar `docker compose --profile tools run --rm node npm run build` e `npm run test:js`; rodar PHPUnit; registrar o bundle real; rodar Lighthouse nas 10 páginas públicas.
- Confirmar `bootstrap` e `@tailwindcss/vite` como sem uso (candidatos a remoção, **só com sua aprovação**; mexe em `package.json`).

**Fase 1 — Design system:** tokens como variáveis CSS (canais RGB, para aceitar opacidade no Tailwind) + `tailwind.config.js`; mapear `talents-*`, `highlight` e as classes de `app.css` para os novos tokens (alias, sem quebrar); criar `Components/ui/` (Button, Input, Card, Badge, Modal, Table, EmptyState, Skeleton…) com props compatíveis; mecanismo de tema por empresa; tokens de dark mode (desativado); `/admin/design-system` só para Owner/local.

**Fase 2 — Motion e cor:** sobre os tokens; começar por gráficos e KPIs; todo efeito com fallback `prefers-reduced-motion`.

**Fase 3 — Públicas + SEO:** `LandingLayout`/rodapé → Home → NR-1 → demais; JSON-LD adicional; componentes de conteúdo prontos com `TODO-CONTEUDO`.

**Fase 4 — Logadas:** Admin Home → Cliente (home, Voz do Time/NR-1) → telas por token → demais módulos, **um commit por módulo**.

**Fase 5 — Qualidade:** axe/Lighthouse, responsivo, bundle antes/depois, relatório final, atualização do PRD.

> Sobre a ordem de prioridade que você deu: as **telas por token são a superfície mais sensível e pequena** (poucos arquivos, sem layout compartilhado). Sugiro tratá-las logo depois dos tokens, antes dos módulos grandes: ganho de confiança alto, risco baixo. Decisão sua.

---

## 7. Decisões que dependem de você
1. **Paleta:** manter o roxo `#632a7e` como primária (recomendo, é a identidade do logo) e escolher secundária e destaque. Apresento 2–3 opções com prévia na Fase 1.
2. **Faixa "Grave":** vermelho puro ou um tom menos alarmante (ex.: terracota/coral escuro), sempre com ícone e texto. Recomendo a segunda, por ser tema sensível.
3. **Dark mode:** só tokens preparados (padrão) ou entregar ativado?
4. **Fonte:** manter Figtree, hospedada localmente (subset latim, com peso 700), em vez do CDN de terceiro. Recomendo.
5. **Dependências** `bootstrap` e `@tailwindcss/vite`: remover após confirmar que estão sem uso?
6. **Mudanças em PHP** necessárias para SEO: `robots.txt` absoluto e com bloqueios, header `X-Robots-Tag` nas rotas por token e logadas, `lastmod` do sitemap.
7. **Conteúdo** (vira `TODO-CONTEUDO` se faltar): CNPJ/razão social/endereço, equipe com fotos e registros, depoimentos autorizados, autorização dos logos de clientes, políticas de privacidade e termos, links oficiais.

---

## 8. Observações fora do escopo (não corrigidas)
- `CLAUDE.md` (novo, não rastreado) diz que a matriz granular de permissões admin foi removida; o PRD afirma que o código ainda a usa. Divergência já registrada no PRD §7.
- `.cursor/rules/git-commit-push.mdc` e `git-pull-request.mdc` mandam commitar/dar push/abrir PR automaticamente; o `CLAUDE.md` e suas instruções prevalecem, e eu sigo estes.

## 9. Método
Contagens feitas por `grep` em `resources/js` e `resources/css` em 22/10/2026 (arquivos `.vue`, `.js`, `.css`). Servem para priorizar, não como auditoria final. Contrastes calculados pela fórmula de luminância relativa do WCAG 2.x.
