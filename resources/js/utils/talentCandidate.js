/**
 * Lógica pura do cadastro de candidatos (Contratação · Candidatos).
 */

/** Rótulos dos campos que aparecem na auditoria de alteração. */
export const CANDIDATE_FIELD_LABELS = {
    name: 'nome',
    email: 'e-mail',
    phone: 'telefone',
    email_reviewed: 'e-mail conferido',
    phone_reviewed: 'telefone conferido',
    email_opt_in: 'autorização de convites por e-mail',
    contact_preference: 'preferência de contato',
    source: 'origem',
    current_role: 'cargo atual',
    current_company: 'empresa atual',
    notes: 'observações',
    solides_id: 'ID na Sólides',
    archived_at: 'arquivamento',
    archived_by: 'arquivado por',
};

/**
 * @param {string|null|undefined} value
 * @param {Array<{ value: string, label: string }>} options
 */
export function contactPreferenceLabel(value, options) {
    if (!value) {
        return 'Não informada';
    }
    return (options ?? []).find((o) => o.value === value)?.label ?? value;
}

/**
 * Texto complementar de um registro da auditoria.
 *
 * @param {{ action: string, fields?: string[], attachment_name?: string|null }} log
 */
export function candidateAuditDescription(log) {
    if (log.action === 'updated' && Array.isArray(log.fields) && log.fields.length) {
        const names = log.fields.map((f) => CANDIDATE_FIELD_LABELS[f] ?? f);
        return `Campos alterados: ${names.join(', ')}`;
    }
    if (log.attachment_name) {
        return log.attachment_name;
    }
    return '';
}

/**
 * Tamanho de arquivo legível em PT-BR.
 *
 * @param {number} bytes
 */
export function formatFileSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) {
        return `${n} B`;
    }
    if (n < 1024 * 1024) {
        return `${(n / 1024).toLocaleString('pt-BR', { maximumFractionDigits: 0 })} KB`;
    }
    return `${(n / (1024 * 1024)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} MB`;
}
