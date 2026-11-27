/**
 * Lógica pura das comunicações da vaga e dos contatos da empresa (Contratação · Fase 4).
 * Os status nunca dizem «entregue» nem «lido»: só o que a Talents sabe de fato.
 */

/** Classes do selo de status da comunicação. */
export function communicationStatusClass(status) {
    return (
        {
            draft: 'bg-slate-100 text-slate-700',
            sent: 'bg-emerald-50 text-emerald-800',
            failed: 'bg-red-50 text-red-700',
            copied: 'bg-sky-50 text-sky-800',
        }[status] ?? 'bg-slate-100 text-slate-700'
    );
}

/**
 * Contatos que podem receber pelo canal escolhido.
 *
 * @param {Array<{ id: number, channels: string[] }>} contacts
 * @param {string} channel
 */
export function contactsForChannel(contacts, channel) {
    return (contacts ?? []).filter((c) => (c.channels ?? []).includes(channel));
}

/**
 * Linhas da revisão que bloqueiam o envio (já saíram com o mesmo texto, sem «Enviar novamente»,
 * ou contato inelegível).
 *
 * @param {Array<{ already_done: boolean, reasons: string[] }>} rows
 * @param {boolean} resend
 */
export function reviewBlockers(rows, resend) {
    return (rows ?? []).filter((r) => (r.reasons ?? []).length > 0 || (r.already_done && !resend));
}

/**
 * Cabe no limite diário de e-mails?
 *
 * @param {number} selected
 * @param {number} remaining
 */
export function fitsDailyLimit(selected, remaining) {
    return selected <= Math.max(0, Number(remaining) || 0);
}

/** Formulário vazio de contato da empresa. */
export function emptyContact() {
    return {
        name: '',
        role: '',
        email: '',
        phone: '',
        channel_email: false,
        channel_whatsapp: false,
        topics: [],
        is_enabled: true,
        is_primary: false,
        responsible_user_id: '',
        notes: '',
    };
}

/**
 * E-mail ou telefone alterados desfazem a conferência (o servidor aplica a mesma regra).
 *
 * @param {{ email?: string|null, phone?: string|null }} original
 * @param {{ email?: string|null, phone?: string|null }} edited
 */
export function contactLosesReview(original, edited) {
    const norm = (v) => String(v ?? '').trim().toLowerCase();
    return norm(original?.email) !== norm(edited?.email) || String(original?.phone ?? '').trim() !== String(edited?.phone ?? '').trim();
}
