# Checklist de smoke (pré-demonstração / go-live)

Execute em ambiente de **staging** ou homologação com dados não sensíveis. Marque cada item após validar.

## Autenticação e papéis

- [ ] Login com **super admin** → redireciona para `/admin` (dashboard admin).
- [ ] Login com **usuário de empresa** (`company_admin`) → redireciona para `/client`.
- [ ] Super admin acessa `/client` → redireciona para o painel admin (sem vazar dados de empresa).
- [ ] Usuário de empresa **não** acessa `/admin` (403).

## Admin Talents

- [ ] Listar e abrir empresas (`/admin/companies`).
- [ ] Criar ou editar empresa (se aplicável ao demo).
- [ ] Planos e templates de pesquisa carregam sem erro.
- [ ] **Configurações** (`/admin/settings`): abas IA e e-mail; teste de SMTP/IA se forem usados na apresentação.

## Painel da empresa (`/client`)

- [ ] Dashboard carrega.
- [ ] Setores e cargos: listar e criar um registro de teste (ou usar existentes).
- [ ] **Pesquisas**: criar campanha, definir datas/status, copiar link público.
- [ ] Abrir link **`/pesquisa/{token}`** em aba anônima: formulário ou mensagem de “fechada” coerente com o status/datas.
- [ ] Submeter uma resposta de teste e conferir contagem/resultados no painel.
- [ ] Export JSON/CSV e relatórios PDF (executivo/técnico) se forem mostrados.

## Canal de denúncia pública

- [ ] Abrir **`/denuncia/{token}`** com token válido da empresa demo.
- [ ] Registrar denúncia (anônima e, se possível, identificada) e anotar o protocolo.
- [ ] Acompanhar por protocolo na mesma base de token.
- [ ] No painel `/client/complaints`, ver a denúncia, alterar status e enviar mensagem (e-mail do denunciante, se configurado).

## Público geral

- [ ] Página inicial `/` carrega.
- [ ] Health check `/up` retorna sucesso (monitoramento / Coolify).

## Segurança e limites

- [ ] Rotas públicas de pesquisa e denúncia respondem **429** após volume excessivo de POST (rate limit por IP + token) — opcional, teste rápido com ferramenta de carga local.
- [ ] Credenciais de demo **não** estão ativas em produção; `APP_KEY` está estável se houver dados criptografados.

## Responsivo e UX

- [ ] Fluxos críticos (pesquisa pública e denúncia) usáveis em largura mobile.

## Pós-sessão

- [ ] Anotar falhas e severidade; abrir issues ou corrigir antes do evento público.

Valores numéricos dos rate limits: ver [`config/public_rate_limits.php`](config/public_rate_limits.php).
