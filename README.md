# Talents — Gestão de Pessoas e NR-1

Plataforma SaaS em **Laravel 12**, **Inertia.js** e **Vue 3** para conformidade com a NR-1 (riscos psicossociais): painel administrativo Talents, painel por empresa, pesquisas anônimas, dashboards, insights, plano de ação e relatórios PDF.

## Desenvolvimento local (somente Docker)

**Não use PHP, Composer ou Node instalados no Windows/macOS/Linux host** para este projeto: banco, Redis, filas e extensões PHP estão definidos para rodar dentro dos containers. Todos os comandos abaixo assumem o diretório `talents/` e [Docker Compose](https://docs.docker.com/compose/) v2.

### Subir a stack

```bash
cd talents
docker compose up -d --build
```

### Composer e Artisan (container `app`)

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

### Testes e checklist antes do público

- A suíte **PHPUnit** cobre autenticação, autorização admin/cliente, pesquisa pública (janela de datas e throttle), denúncias e acessos cruzados entre empresas (IDOR).
- **Rate limiting** das rotas públicas (`POST` em pesquisa e denúncia): valores em [`config/public_rate_limits.php`](config/public_rate_limits.php), registrados em `AppServiceProvider`.
- Para demonstração ou go-live, use o checklist manual [`SMOKE_CHECKLIST.md`](SMOKE_CHECKLIST.md).
- **E2E (opcional):** não há Playwright/Cypress no projeto; após estabilizar os testes acima, avalie Laravel Dusk ou Playwright para um fluxo crítico (ex.: login no `/client` e abertura de resultados), se precisar de regressão em navegador real.

### Frontend (npm só no container `node`, profile `tools`)

Não rode `npm` na máquina host. Use o serviço `node`:

```bash
docker compose --profile tools run --rm node npm ci
docker compose --profile tools run --rm node npm run build
```

#### Hot reload (Vite HMR)

1. No `.env`, confira `APP_URL=http://localhost:8080` e as variáveis `VITE_*` (veja `.env.example`).
2. Suba a stack normal e o serviço `vite` (profile `dev` — **não** entra em produção):

```bash
docker compose up -d
docker compose --profile dev up vite
```

3. Abra **http://localhost:8080** (a app). O Vite serve assets em **http://localhost:5173** (HMR).
4. Edite `.vue` / `.js` / `.css` → atualização instantânea. Blade, rotas e controllers → reload automático da página.

Para parar o HMR: `Ctrl+C` no terminal do `vite` ou `docker compose --profile dev stop vite`.

Build de produção continua sendo `npm run build` (sem profile `dev`). O ficheiro `public/hot` é criado só em dev e está no `.gitignore`.

Comando alternativo (one-shot, sem serviço `vite` no compose):

```bash
docker compose --profile tools run --rm -p 5173:5173 node sh -c "npm ci && npm run dev:docker"
```

### Acesso

- **Aplicação:** http://localhost:8080 (`APP_PORT` no `.env`)
- **PostgreSQL no host:** porta `5433` (`FORWARD_DB_PORT`) — usuário/senha `talents` / `secret`, banco `talents`
- **Redis no host:** porta `6380` (`FORWARD_REDIS_PORT`)

O arquivo **`.env`** para Docker local costuma usar `DB_HOST=postgres`, `REDIS_HOST=redis`, `APP_URL=http://localhost:8080`.

### Consulta CNPJ (ReceitaWS)

1. Defina `RECEITAWS_TOKEN` no `.env` (veja `.env.example`).
2. Se aparecer *«Consulta CNPJ não está configurada no servidor»*, o cache de config pode estar antigo. Limpe e reinicie o PHP:

   ```bash
   docker compose exec app php artisan config:clear
   docker compose restart app
   ```

   Evite versionar `bootstrap/cache/config.php`; em produção, após alterar `.env`, rode `config:clear` ou gere o cache de novo com `php artisan config:cache` **somente** quando o token já estiver definido.

### Rotas novas e cache

Se aparecer `Route [admin....] not defined` ou rotas antigas após um deploy, o cache de rotas pode estar desatualizado:

```bash
docker compose exec app php artisan route:clear
# ou, para limpar tudo relacionado a cache de bootstrap:
docker compose exec app php artisan optimize:clear
```

Depois de adicionar rotas em produção, regenere o cache **só se** você usa `route:cache` no pipeline: `php artisan route:cache`.

### Configurações (Mia / IA e SMTP)

No admin, **Configurações** (`/admin/settings`) concentra a IA (Mia) e o envio de e-mail (SMTP). Com SMTP habilitado no painel, os valores do banco substituem `MAIL_*` do `.env` após o boot — útil no Coolify. O atalho antigo `/admin/ai-settings` redireciona para essa página. Ao cadastrar uma **nova empresa**, é criado o primeiro usuário `company_admin` e enviado um e-mail com link para definir senha (depende de SMTP ou do driver `log` em desenvolvimento).

Chaves de API e senhas SMTP são guardadas **criptografadas** com a `APP_KEY`. Se ela mudar (novo deploy sem o mesmo segredo), salve de novo no painel ou use o diagnóstico:

```bash
docker compose exec app php artisan app:check-encryption
```

### Módulo Entrevistas (IA)

Painel admin em **`/admin/entrevistas`**: upload de gravação de entrevista (até ~500 MB / ~1 h), transcrição via **OpenAI Whisper** e relatório estruturado pelas perguntas do roteiro (PDF + DOCX).

**Requisitos**

- **ffmpeg** no container PHP (já incluído no `docker/Dockerfile`).
- **Fila Redis** ativa (`queue` no compose) — o processamento roda em background (`ProcessInterviewAudioJob`, timeout 30 min).
- **Configurações → Mia (IA):** IA habilitada + chave do provedor de análise (OpenAI ou Anthropic). Para transcrição: com OpenAI, a mesma chave; com Anthropic, informe também a chave OpenAI dedicada (Whisper).
- Após `migrate`, o seeder `InterviewQuestionnaireSeeder` cria o roteiro padrão com as 7 seções do processo seletivo Talents.

**Variáveis opcionais (`.env`)**

- `INTERVIEW_MAX_UPLOAD_MB` (padrão 500)
- `INTERVIEW_KEEP_AUDIO` (padrão `true` — manter arquivo após processar)
- `INTERVIEW_WHISPER_MODEL` (padrão `whisper-1`)

**Produção (Coolify):** o volume `talents_storage` monta `storage/` (inclui áudios em `storage/app/private/interviews`). Nginx e PHP aceitam uploads até 512 MB.

**Permissão admin:** módulo `entrevistas` — concedida automaticamente aos super admins não-owner na migration; owners têm acesso total.

### Usuários de demonstração (após `migrate --seed`)

| Perfil | E-mail | Senha |
|--------|--------|-------|
| Admin Talents | admin@talents.local | password |
| RH empresa demo | rh@empresa.local | password |

## Produção (Coolify)

Use `docker-compose.prod.yml` no Coolify. Variáveis de ambiente e segredos ficam no painel do Coolify, não no repositório.

## Rotas principais

- `/` — Landing
- `/login` — Autenticação (cadastro público desativado)
- `/admin/*` — Painel Talents (super admin)
- `/admin/entrevistas` — Entrevistas por áudio (IA): upload, transcrição e relatório
- `/client/*` — Painel da empresa
- `/pesquisa/{token}` — Pesquisa anônima (link da campanha)
- `/denuncia/{token}` — Canal de denúncia da empresa (token público)

## Marca e tema

Logo em `public/images/logo.png`. Paleta Tailwind `talents-*` definida em `tailwind.config.js`.

## Licença

Proprietário — uso interno do projeto Talents.
