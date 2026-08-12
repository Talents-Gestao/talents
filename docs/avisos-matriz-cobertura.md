# Matriz de cobertura — Avisos (sino)

Documento vivo da **Fase 0** (auditoria). Princípio de produto: avisar ações que pedem atenção de outra pessoa ou mudam estado de negócio; **não** avisar autosave/edições cosméticas.

Audiências:
- **Talents** — administradores `/admin` (`CompanyNoticeAudience::Talents`)
- **Empresa** — utilizadores do workspace `/client` (`CompanyNoticeAudience::Company`)

Infra: `PublishCompanyNotice` (+ dedupe opcional) → publishers por domínio → `CompanyNotice` → sino/`NoticeBellDropdown`.

---

## Já coberto (hoje)

| Módulo | Ação | Tem aviso? | Audience | Publisher / `event_kind` | Notas |
|--------|------|------------|----------|--------------------------|-------|
| Comercial | Proposta criada | Sim | Talents | `PublishCommercialNotice::proposalCreated` / `proposal_created` | Em `store` |
| Comercial | Proposta fechada (won) | Sim | Talents | `proposalWon` / `proposal_won` | Em `store`/`update`/`updateStatus` quando passa a fechada (`is_closed` / impliesClosed). **Não** dispara só por `in_progress` |
| Financeiro | Venda criada | Sim | Talents | `saleCreated` / `sale_created` | Conversão proposta→venda |
| Financeiro | Parcela paga | Sim | Talents | `installmentPaid` / `installment_paid` | |
| Financeiro | Parcela vencida | Sim | Talents | `installmentOverdue` / `installment_overdue` | Job/comando; dedupe permanente por parcela |
| Financeiro | Comissão paga | Sim | Talents | `commissionPaid` / `commission_paid` | |
| Leads | Lead landing recebido | Sim | Talents | `PublishLeadNotice` / `lead_received` | |
| Calendário estratégico | Criar / atualizar / data / remover item | Sim | Empresa | `PublishStrategicCalendarChangeNotice` / `created`·`updated`·`date_changed`·`deleted` | Com dedupe curto |
| Feedbacks | Enviado para assinatura | Sim | Empresa | `PublishFeedbackNotice` / `feedback_awaiting_signature` | |
| Feedbacks | Feedback concluído (assinado) | Sim | Empresa | `feedback_completed` | |
| Denúncias | Nova denúncia (público) | Sim | Empresa | `PublishComplaintNotice` / `complaint_created` | Sem texto sensível no body |
| Denúncias | Status atualizado | Sim | Empresa | `complaint_updated` | |
| Avisos manuais | Criação admin | Sim | Empresa (ou conforme form) | `PublishCompanyNotice` direto | CRUD `/admin/notices` |

---

## Lacunas relevantes

| Módulo | Ação | Tem aviso hoje? | Audience sugerida | Prioridade | Notas |
|--------|------|-----------------|-------------------|------------|-------|
| Comercial | Status → **Em andamento** | Não | Talents | **P0** | Hoje só `proposal_won` quando fecha; `in_progress` é silencioso |
| Comercial | Contrato gerado (DOCX/PDF) | Não | Talents | **P0** | `ContractController::store` |
| Comercial | Contrato enviado ZapSign | Não | Talents | **P0** | `ContractController::sendZapSign` |
| Financeiro | Conta a receber criada (manual) | Não | Talents | P1 | Opcional; risco de barulho |
| Financeiro | Conta a receber marcada recebida | Não | Talents | P1 | Parcelas de venda já avisam; receivable avulso é lacuna |
| Financeiro | Conta a pagar criada / paga | Não | Talents | P2 | Menos “atenção de outra pessoa” |
| Contratação | Mudança de estágio (ex. → Contratação) | Não | Talents | **P0** | `HiringProcessController` advance/update stage |
| Tarefas | Card atribuído / concluído | Não | Talents ou Empresa | P1 | Depende do board (admin vs client) |
| Clientes | Empresa criada | Não | Talents | P1 | Útil para ops; baixo volume |
| Férias | Pedido / aprovação | Não | Empresa | P1 | Avaliar audience company_admin |
| Desligamento | Processo iniciado / entrevista respondida | Não | Empresa / Talents | P1 | |
| NR-1 | Campanha publicada / resposta / análise pronta | Não | Empresa | P2 | Volume alto se “cada resposta” |
| RHID | Marcações / inconsistências em massa | Não | Empresa | P2 | **Fora de escopo** (flood) |
| Proposta | Edição de campos / itens / PDF cosmético | Não (ok) | — | — | Não avisar (princípio) |
| Comercial | Status → Em aberto de novo | Não | Talents | P2 | Baixa urgência |

---

## Proposta P0 (máx. 5–7) — **confirmar antes de implementar**

Sugestão enxuta (6 eventos), alinhada ao prompt e ao princípio anti-spam:

| # | Evento | Audience | Publisher sugerido | Dedupe | Gatilho |
|---|--------|----------|--------------------|--------|---------|
| 1 | Proposta → **Em andamento** | Talents | `PublishCommercialNotice` + `proposal_in_progress` | 5 min / proposta | `updateStatus` quando novo status = `in_progress` **e** anterior ≠ `in_progress` (não misturar com `proposal_won`) |
| 2 | **Contrato gerado** | Talents | idem + `contract_generated` | 5 min / contrato | `ContractController::store` |
| 3 | **ZapSign enviado** | Talents | idem + `contract_zapsign_sent` | 1× por contrato (ou janela longa) | `sendZapSign` após sucesso |
| 4 | Processo de contratação → estágio **Contratação** (Contrato) | Talents | novo `PublishHiringNotice` (ou commercial genérico) + `hiring_stage_contratacao` | 5 min / processo | advance/update quando entra em estágio final relevante |
| 5 | *(opcional P0)* Conta a receber **marcada como recebida** | Talents | publisher financeiro + `receivable_paid` | 5 min | só transição → Paid (não create) |
| 6 | *(opcional P0 empresa)* Férias **aprovadas** | Empresa | novo publisher leaves + `leave_approved` | 5 min | transição de status |

**Recomendação de corte para Fase 1:** implementar **1–4** (claramente P0 comercial/ops). Deixar 5–6 para P1 salvo confirmação explícita do produto.

Fora desta fase: ligar todos os CRUDs, RHID em massa, e-mail/push.

---

## Critérios de teste (quando P0 for aprovado)

Por cada evento P0:
- Ação → existe `CompanyNotice` com `event_kind` + `audience` esperados
- Admin vê em recent / unread incrementa
- Dedupe: segunda chamada na janela não duplica

---

*Gerado na auditoria de cobertura de avisos (branch `new-tasks`). Atualizar este ficheiro quando P0 for implementado.*
