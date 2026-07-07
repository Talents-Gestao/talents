# Usuários de demonstração

Estes acessos são criados pelo seeder **`Database\Seeders\TalentsSeeder`** ao rodar migrate + seed.

## Com Docker (forma correta neste projeto)

O `.env` usa `DB_HOST=postgres` (rede Docker). Rode o **Artisan dentro do container** `app`, não no PHP do Windows:

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Ou em um comando: `docker compose exec app php artisan migrate --seed`

A aplicação fica em **http://localhost:8080** (porta definida por `APP_PORT` no `.env`).

Se você rodar `php artisan` no PowerShell com XAMPP, aparece `could not find driver` — o PHP local não tem extensão PostgreSQL; use os comandos acima.

## Credenciais

| Perfil | E-mail | Senha | Papel | Onde acessa |
|--------|--------|--------|--------|----------------|
| **Admin Talents** | `admin@talents.local` | `password` | Super administrador (`super_admin`) | `/admin` — painel Talents (empresas, planos, templates NR-1, **Configurações** IA/SMTP, etc.) |
| **RH demo (empresa)** | `rh@empresa.local` | `password` | Administrador da empresa (`company_admin`) | `/client` — painel da **Empresa Demo** (pesquisas, resultados, setores, etc.) |
| **Líder demo (empresa)** | `lider@empresa.local` | `password` | Usuário da empresa (`company_user`) | `/client/feedbacks` — feedbacks da equipe do líder |
| **RH Inovação Plus** | `rh@inovacao.local` | `password` | Administrador da empresa (`company_admin`) | `/client` — segunda empresa demo (feedbacks) |
| **Vendedora Karen** | `karen@talents.local` | `password` | Super admin + comercial | Propostas atribuídas à Karen nos cenários demo |
| **Vendedora Luciana** | `luciana@talents.local` | `password` | Super admin + comercial | Propostas atribuídas à Luciana nos cenários demo |

## Comercial e financeiro (dados de teste)

Após `db:seed`, o **`CommercialDemoSeeder`** cria propostas, vendas, parcelas e comissões de exemplo (códigos `PROP-DEMO-*` e `VENDA-DEMO-*`).

**Login recomendado:** `admin@talents.local` / `password`

### O que testar

| Cenário | Código | Onde ver |
|--------|--------|----------|
| Fila #1 (mais antiga) | `PROP-DEMO-0001` | Comercial → **Fila** |
| Fila #2–#4 | `PROP-DEMO-0002` … `0004` | Comercial → Fila (inclui proposta sem vendedor) |
| Fechada, converter em venda | `PROP-DEMO-0005` | Comercial → Propostas → ícone **Converter** |
| Venda quitada + comissão paga | `VENDA-DEMO-0001` | Financeiro → Vendas / Comissões (filtro **Paga**) |
| Venda parcial (1/3 parcelas) | `VENDA-DEMO-0002` | Financeiro → Vendas |
| Parcela vencida | `VENDA-DEMO-0003` | Financeiro → Painel (vencidos) |
| Quitada, comissão a pagar | `VENDA-DEMO-0004` | Financeiro → **Comissões** (pendentes) |
| 4 parcelas futuras | `VENDA-DEMO-0005` | Financeiro → Próximos vencimentos |

### URLs rápidas

- Fila de propostas: `/admin/comercial/propostas?status=abertas&ordenacao=fila`
- Comissões pendentes: `/admin/financeiro/comissoes?status=a_pagar`
- Painel financeiro: `/admin/financeiro`

Para repor só os dados comerciais (sem resetar a BD):

```bash
docker compose exec app php artisan db:seed --class=CommercialDemoSeeder --force
```

O seeder é **idempotente** — pode rodar várias vezes sem duplicar vendas.

### Smoke test automatizado

Para validar que todos os cenários demo existem e as páginas respondem corretamente:

```bash
docker compose exec app php artisan test --filter=CommercialDemoSmokeTest
```

São **9 testes** (112 asserções): fila FIFO, proposta conversível, estados das vendas/parcelas, ordem das comissões, páginas Inertia e idempotência do seeder.

## Feedbacks internos (dados de teste)

Após `db:seed`, o **`FeedbackDemoSeeder`** cria colaboradores e sessões de exemplo:

- **Empresa Demo** — 4 colaboradores, 4 sessões (concluída, aguardando assinatura, em preenchimento, histórico)
- **Inovação Plus** — 1 colaborador, 1 sessão concluída

**Admin Talents:** `/admin/feedbacks` — visão do portfólio.

Para repor só os dados de feedback (sem resetar a BD):

```bash
docker compose exec app php artisan db:seed --class=FeedbackDemoSeeder --force
```

(Só insere se ainda não existirem sessões na empresa demo.)


- **Empresa:** Empresa Demo (CNPJ fictício `00.000.000/0001-99` no seed).
- **Usuário admin** não possui `company_id` (acesso global Talents).
- **Usuário RH** está vinculado à empresa demo e ao plano seed **NR1 Pro**.

## Como a empresa acessa o sistema (painel `/client`)

1. **Login é sempre de pessoa (usuário), não da empresa.** Quem entra em `/login` usa **e-mail e senha** da tabela `users` (`users.email`), não o cadastro da tabela `companies` sozinho.
2. **Painel da empresa** (`/client`, pesquisas, resultados, etc.) exige um usuário com papel `company_admin` ou `company_user` e com **`company_id`** apontando para aquela empresa. O middleware `company` bloqueia quem não tem empresa vinculada; super admin é redirecionado para `/admin`.
3. **Cadastrar uma nova empresa no admin** (`/admin/companies/create`) exige o **e-mail do administrador da empresa**. Ao salvar, o sistema cria a empresa, o primeiro usuário `company_admin` com esse e-mail e envia um **e-mail com link para definir a senha** (SMTP configurável em **Admin → Configurações → E-mail**). O usuário acessa `/login` e o painel em `/client`.
4. **SMTP:** em desenvolvimento, sem SMTP no painel, o Laravel pode usar o driver `log` (e-mail só no log). Para envio real, configure SMTP em **Configurações** ou `MAIL_*` no `.env`.

## Segurança

As senhas acima são **apenas para desenvolvimento**. Em ambiente de produção, altere as senhas ou remova estes usuários após o primeiro deploy.
