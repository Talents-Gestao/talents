/**
 * Cor do «Detalhamento por pergunta» pela polaridade do enunciado (testado em tests/JavaScript/nr1QuestionPolarity.test.mjs).
 *
 * Fonte única: survey_template_questions.reverse_score, que já vem do servidor em cada pergunta
 * (true = enunciado de sentido favorável/positivo; false = desfavorável). Aqui só se mapeia para cor e rótulo;
 * nenhuma regra de polaridade é recalculada. Com a polaridade do instrumento pendente de auditoria
 * (polarity_audit_status = pending_audit), tudo fica cinza neutro: a Talents vê o selo e a legenda da auditoria;
 * a empresa vê só o cinza, sem selo nem legenda (`showAuditBadge` = false).
 */
export const POLARITY = {
    favorable: {
        key: 'favorable',
        label: 'Sentido favorável',
        legend: 'Verde: enunciado favorável (concordar indica proteção).',
        bar: 'bg-emerald-500',
        chip: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    },
    unfavorable: {
        key: 'unfavorable',
        label: 'Sentido desfavorável',
        legend: 'Âmbar: enunciado desfavorável (concordar indica risco).',
        bar: 'bg-amber-500',
        chip: 'bg-amber-50 text-amber-900 ring-amber-200',
    },
    pending: {
        key: 'pending',
        label: 'Polaridade pendente de auditoria',
        legend: 'Cinza: polaridade pendente de auditoria (sem cor de sentido).',
        bar: 'bg-slate-400',
        chip: 'bg-slate-100 text-slate-700 ring-slate-300',
    },
    /** Empresa com polaridade em auditoria: só o cinza, sem selo nem legenda. */
    neutral: {
        key: 'neutral',
        label: null,
        legend: null,
        bar: 'bg-slate-400',
        chip: null,
    },
};

/** Polaridade de uma pergunta: pendente (instrumento em auditoria) ou pelo reverse_score. */
export function questionPolarity(question, { polarityPending = false, showAuditBadge = true } = {}) {
    if (polarityPending) return showAuditBadge ? POLARITY.pending : POLARITY.neutral;

    return question?.reverse_score ? POLARITY.favorable : POLARITY.unfavorable;
}

/** Legenda curta do topo da seção (vazia para a empresa quando a polaridade está em auditoria). */
export function polarityLegend({ polarityPending = false, showAuditBadge = true } = {}) {
    if (polarityPending) return showAuditBadge ? [POLARITY.pending] : [];

    return [POLARITY.favorable, POLARITY.unfavorable];
}
