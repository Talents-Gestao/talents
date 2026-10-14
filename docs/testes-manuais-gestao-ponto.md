# Testes visuais manuais — Gestão de ponto

Roteiro para conferir a primeira impressão da operação de ponto (Admin e Cliente), **sem NR-1** nessas telas.

Pré-requisitos: no ambiente local, rode o seeder abaixo e atualize `/admin/ponto`. Sem ele a lista fica vazia (só entram empresas com e-mail e senha RHID).

```bash
docker compose exec app php artisan db:seed --class=PontoVisualQaSeeder
```

Depois disso deve aparecer a empresa **QA Ponto Visual**, com:

- **Ana Pontual** no topo de Mais pontuais (100%)
- **Bruno Faltas** no topo de Mais faltas não justificadas
- **Carla Atestado** no topo de Mais atestados

Cliente: `rh-qa-ponto@talents.local` / `password` (visão geral de ponto).

Marque cada item ao concluir.

---

## Itens visuais (olhar a tela)

Use estes itens primeiro. São o que deve (e o que não deve) aparecer.

### Cartões (3 KPIs)

| # | O que olhar | O que deve ver |
| --- | --- | --- |
| V1 | Primeira linha, três cartões lado a lado | Da esquerda para a direita: **Pontualidade média** (verde), **Faltas não justificadas** (rosa), **Atestados** (âmbar) |
| V2 | Título dos cartões | Texto em maiúsculas pequenas: `PONTUALIDADE MÉDIA`, `FALTAS NÃO JUSTIFICADAS`, `ATESTADOS` |
| V3 | Valor com espelho no mês | Percentual com uma casa (`87,5%` / `87.5%`) no verde; número inteiro de dias no rosa e no âmbar |
| V4 | Valor **sem** espelho no mês | Traço `—` nos três cartões, não `0%` nem `0` |
| V5 | Texto de apoio sob cada número | Verde: dias úteis esperados; rosa: sem marcação completa e sem atestado/férias/feriado; âmbar: dias úteis cobertos por atestado |

### Rankings (3 colunas)

| # | O que olhar | O que deve ver |
| --- | --- | --- |
| V6 | Segunda linha, três colunas | **Mais pontuais** · **Mais faltas não justificadas** · **Mais atestados** |
| V7 | Ordem em “Mais pontuais” | Do maior para o menor percentual; badge `01`, `02`… em círculo verde; à direita o `%` e `X/Y dias` |
| V8 | Ordem em “Mais faltas…” | Quem tem mais dias no topo; badge rosa; número de dias à direita |
| V9 | Ordem em “Mais atestados” | Quem tem mais dias de atestado no topo; badge âmbar |
| V10 | Ranking de faltas vazio | Caixa cinza: `Nenhuma falta não justificada neste período.` |
| V11 | Ranking de atestados vazio | Caixa cinza: `Nenhum dia de atestado no período.` |
| V12 | Sem nenhum espelho | Bloco tracejado: `Sem dados de cumprimento neste período` + pedido para importar espelhos e configurar a escala |
| V13 | Cabeçalho “Equipe no mês” | Subtítulo: pontualidade, faltas e atestados a partir dos espelhos importados |
| V14 | Linha de resumo (quando há dados) | Algo como `N dia(s) útil(eis) no período · X colaborador(es) com espelho` |

### O que **não** pode aparecer nestas telas

| # | Tela | Não deve existir |
| --- | --- | --- |
| V15 | `/admin/ponto` | Badge **NR-1**, **Risco duplo**, alerta operacional, os 4 cartões antigos (marcações / banco / aderência / justificativas) |
| V16 | `/admin/ponto` | Aba **Resumo** ou **Hoje** (marcações em tempo real) |
| V17 | Aba Ponto da empresa | NR-1 / risco duplo no cartão de indicadores |
| V18 | Cliente visão geral | Gráfico radial de cobertura, donut de banco, donut de justificativas, bloco **Top 5 — Cumprimento do mês completo** |
| V19 | Cliente visão geral | Título `Compliance de Ponto · RHID` ou `Cobertura de aderência` |

### Abas e chrome (Admin)

| # | O que olhar | O que deve ver |
| --- | --- | --- |
| V20 | Título da página | `Gestão de ponto` |
| V21 | Botão no cabeçalho | Só `Atualizar` |
| V22 | Abas sob o cartão | Só **Aderência** · **Banco de horas** · **Justificativas** · **Equipe** |
| V23 | Aba Aderência | Tabelas de atrasos na entrada, infrações de almoço e pior aderência (espelhos do mês) |
| V24 | Aba Equipe | Campo `Buscar colaborador...` e as três pessoas: Ana (matrícula 1001, Analista de RH), Bruno (1002, Auxiliar administrativo), Carla (1003, Assistente) |
| V24b | Aba Banco de horas | Três linhas: **Bruno Faltas** `-8:00`, **Carla Atestado** `0:00`, **Ana Pontual** `2:00` |
| V24c | Aba Justificativas | Pelo menos um atestado de **Carla Atestado** no mês corrente |

### Cliente

| # | O que olhar | O que deve ver |
| --- | --- | --- |
| V25 | Título | `Gestão de ponto` |
| V26 | Seletor de mês | Input tipo mês no canto do cartão da equipe (só no cliente) |
| V27 | Nomes nos rankings | Azul/roxo Talents, viram link para a ficha do colaborador |
| V28 | Abaixo do cartão da equipe | Últimas marcações, atalhos (importar espelho, colaboradores, horários) e maiores débitos de banco |

### Menu

| # | O que olhar | O que deve ver |
| --- | --- | --- |
| V29 | Menu lateral Admin | Sem item **Gestão de ponto**; acesso só pela ficha da empresa (Clientes → empresa → aba) |

---



## 1. Hub Admin — `/admin/ponto`

- [ ] O menu lateral **não** tem item **Gestão de ponto**. O acesso é **Clientes → empresa → aba Gestão de ponto → Abrir gestão de ponto**.
- [ ] Há **um** botão **Atualizar** no cabeçalho (não há “Atualizar empresa”, “Atualizar marcações” nem “Atualizar lista” nas abas).
- [ ] `/admin/rhid` redireciona para `/admin/ponto`.
- [ ] Ao selecionar uma empresa com RHID, o cartão superior mostra **três KPIs**: pontualidade média, faltas não justificadas e atestados.
- [ ] Abaixo dos KPIs há **três rankings**: mais pontuais, mais faltas não justificadas, mais atestados.
- [ ] **Não** aparece badge NR-1, “risco duplo”, alerta operacional nem os quatro cartões antigos (marcações / banco / aderência / justificativas).
- [ ] As abas abaixo são só **Hoje**, **Banco de horas**, **Justificativas** e **Equipe** (não há Resumo nem Aderência).
- [ ] Aba **Hoje**: lista de últimas marcações (Ana e Carla no fixture; não fica em “Atualizando…”).
- [ ] Aba **Banco de horas**: tabela de saldos (Bruno em débito, Ana em crédito).
- [ ] Aba **Justificativas**: lista o atestado de Carla (criar/editar nesta empresa de teste é somente leitura).
- [ ] Aba **Equipe**: lista Ana, Bruno e Carla, com busca.
- [ ] Empresa **sem credenciais RHID** não entra na lista da esquerda.
- [ ] Empresa **com RHID e sem espelho** no mês: KPIs em “—” e mensagem para importar espelhos / configurar escala.

## 2. Ficha da empresa — aba Gestão de ponto

Caminho: `/admin/empresas/{id}` → aba **Gestão de ponto**.

- [ ] A aba mostra só o **estado da integração**: módulo ligado/desligado, credenciais ok/em falta, e-mail RHID, domínio e se a senha está cadastrada.
- [ ] **Não** há KPIs, rankings, marcações, banco nem justificativas nesta aba (isso fica no hub).
- [ ] **Não** há NR-1 / risco duplo nesta aba.
- [ ] O botão **Abrir gestão de ponto** leva ao hub com a empresa já selecionada (`?company=`).
- [ ] Com credenciais ok, o formulário de e-mail/senha fica **recolhido** (“Alterar credenciais”).
- [ ] Sem credenciais, o formulário aparece aberto para o admin preencher.

## 3. Painel Cliente — visão geral de ponto

Caminho: `/client/rhid` (visão geral / compliance).

- [ ] O título fala em gestão de ponto (não em “cobertura de aderência”).
- [ ] Primeira impressão: **três KPIs** (pontualidade média, faltas não justificadas, atestados) e **três rankings**.
- [ ] Há seletor de **mês**; ao mudar o mês, os rankings recarregam.
- [ ] Nomes nos rankings abrem a ficha do colaborador RHID.
- [ ] Ranking de faltas vazio mostra “Nenhuma falta não justificada neste período.”
- [ ] Ranking de atestados vazio mostra “Nenhum dia de atestado no período.”
- [ ] Sem espelho no mês: estado vazio pedindo importação de espelhos e escala.
- [ ] **Não** aparecem os gráficos antigos de cobertura radial, donut de banco e donut de justificativas, nem o Top 5 único de cumprimento.
- [ ] Mais abaixo ainda existem: últimas marcações, atalhos (importar espelho, colaboradores, horários) e maiores débitos de banco.
- [ ] **Não** há menção a NR-1 nesta visão.

## 4. Conferência de números (empresa com espelho)

Escolha 2–3 pessoas conhecidas do mês.

- [ ] **Pontualidade**: quem cumpriu mais dias úteis (entrada + almoço) aparece no topo de “Mais pontuais”. Feriado, atestado e férias **não** entram como falta.
- [ ] **Faltas não justificadas**: dia útil sem marcação completa e **sem** atestado/férias/feriado. Quem tem mais desses dias lidera o ranking de faltas.
- [ ] **Atestados**: só dias úteis cobertos por atestado no ponto. Quem tem mais dias lidera o ranking de atestados.
- [ ] O KPI de pontualidade média é a média dos percentuais das pessoas com espelho (não um “Top 5” isolado).
- [ ] O KPI de faltas soma os dias não justificados; o de atestados soma os dias de atestado.

## 5. Integração e permissões

- [ ] Cliente **sem** módulo RHID não vê o menu de ponto.
- [ ] Admin Talents vê o hub mesmo quando o cliente ainda não abriu o portal.
- [ ] Um único **Atualizar** no cabeçalho recarrega indicadores, marcações, banco, justificativas e equipe, sem quebrar o layout.

---

Se algum item falhar, anote a tela, a empresa, o mês e o que apareceu versus o esperado.
