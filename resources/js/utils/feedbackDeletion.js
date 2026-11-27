/**
 * Textos e regras dos alertas de exclusão em Feedbacks (modelo e feedback). Lógica pura, sem Vue, para teste.
 */

/** «1 feedback» / «N feedbacks». */
export function feedbacksLabel(count) {
    const n = Number(count) || 0;
    return n === 1 ? '1 feedback' : `${n} feedbacks`;
}

/** «1 resposta» / «N respostas». */
export function answersLabel(count) {
    const n = Number(count) || 0;
    return n === 1 ? '1 resposta' : `${n} respostas`;
}

/** Mesmo texto do servidor (DeleteFeedbackTemplate::IN_USE_MESSAGE). */
export const TEMPLATE_IN_USE_MESSAGE =
    'Este modelo já foi usado em feedbacks e não pode ser excluído. Arquive-o para que não apareça em novos feedbacks.';

/**
 * Alerta da exclusão de modelo, a partir da situação calculada no servidor.
 * Nunca usado: confirmação simples. Usado (em qualquer versão ou empresa): não se exclui; a orientação é arquivar.
 * Nenhum feedback é apagado junto com um modelo.
 *
 * @param {{ title: string, sessions_count: number, blocked?: boolean, blocked_message?: string | null }} impact
 */
export function templateDeletionCopy(impact) {
    const sessions = Number(impact?.sessions_count) || 0;
    const title = impact?.title ?? '';

    if (impact?.blocked || sessions > 0) {
        return {
            blocked: true,
            heading: 'Modelo em uso',
            message: impact?.blocked_message || TEMPLATE_IN_USE_MESSAGE,
            confirmLabel: null,
            requiresCheck: false,
        };
    }

    return {
        blocked: false,
        heading: 'Excluir modelo',
        message: `Excluir o modelo «${title}»? Ele ainda não foi usado em nenhum feedback.`,
        confirmLabel: 'Excluir modelo',
        requiresCheck: false,
    };
}

/** Pode enviar? Modelo usado exige a caixa marcada; nunca envia durante o carregamento ou o envio. */
export function canConfirmDeletion({ requiresCheck, checked, busy }) {
    if (busy) return false;
    return !requiresCheck || checked === true;
}
