/**
 * Lógica pura do pós-contratação (Contratação · Fase 5).
 * Estados do convite nunca se confundem: copiar não é entregar; aceito pelo servidor não é lido.
 */

/** Classes do selo de status do marco ou do ciclo. */
export function followupStatusClass(status) {
    return (
        {
            pending_admission: 'bg-slate-100 text-slate-700',
            scheduled: 'bg-sky-50 text-sky-800',
            due: 'bg-amber-50 text-amber-800',
            overdue: 'bg-red-50 text-red-700',
            paused: 'bg-slate-200 text-slate-700',
            completed: 'bg-emerald-50 text-emerald-800',
            terminated: 'bg-slate-100 text-slate-500',
        }[status] ?? 'bg-slate-100 text-slate-700'
    );
}

/** Classes do selo de estado do convite. */
export function invitationStateClass(state) {
    return (
        {
            generated: 'bg-slate-100 text-slate-700',
            copied: 'bg-sky-50 text-sky-800',
            emailed: 'bg-emerald-50 text-emerald-800',
            failed: 'bg-red-50 text-red-700',
            answered: 'bg-talents-50 text-talents-800',
            expired: 'bg-slate-100 text-slate-500',
            revoked: 'bg-slate-100 text-slate-500',
        }[state] ?? 'bg-slate-100 text-slate-700'
    );
}

/**
 * Destinos de um envio e o motivo de cada um não poder receber no canal escolhido.
 *
 * @param {{ email: Record<string, string|null>, copy: Record<string, string|null> }} eligibility
 * @param {'collaborator'|'manager'|'both'} target
 * @param {'email'|'copy'} channel
 * @returns {Array<{ audience: string, reason: string|null }>}
 */
export function plannedAudiences(eligibility, target, channel) {
    const audiences = target === 'both' ? ['collaborator', 'manager'] : [target];
    return audiences.map((audience) => ({ audience, reason: eligibility?.[channel]?.[audience] ?? null }));
}

/** Algum destino pode receber? (envio parcial é permitido; só bloqueia se nenhum puder). */
export function canSendAny(eligibility, target, channel) {
    return plannedAudiences(eligibility, target, channel).some((a) => a.reason === null);
}

/** Texto de um resultado do envio (por destino). */
export function sendOutcomeLabel(result) {
    return (
        {
            emailed: 'Enviado ao servidor de e-mail',
            failed: 'Falhou',
            skipped: 'Não enviado',
            ready_to_copy: 'Pronto para copiar',
            already_answered: 'Já respondido',
        }[result?.outcome] ?? ''
    );
}

/** Mensagem + link para copiar. */
export function copyText(result) {
    return result?.message ?? result?.link ?? '';
}
