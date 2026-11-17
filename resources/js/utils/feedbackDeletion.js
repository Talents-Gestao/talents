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

/**
 * Alerta da exclusão de modelo, a partir do impacto calculado no servidor.
 * Nunca usado: confirmação simples. Usado: alerta completo, botão com a contagem e confirmação explícita.
 *
 * @param {{ title: string, sessions_count: number }} impact
 */
export function templateDeletionCopy(impact) {
    const sessions = Number(impact?.sessions_count) || 0;
    const title = impact?.title ?? '';

    if (sessions === 0) {
        return {
            inUse: false,
            heading: 'Excluir modelo',
            message: `Excluir o modelo «${title}»? Ele ainda não foi usado em nenhum feedback.`,
            confirmLabel: 'Excluir modelo',
            requiresCheck: false,
            checkLabel: '',
        };
    }

    const n = feedbacksLabel(sessions);
    const made = sessions === 1 ? 'o feedback feito com ele' : `os ${n} feitos com ele`;

    return {
        inUse: true,
        heading: 'Excluir modelo já usado',
        message:
            `Este modelo já foi usado em ${n}. Ao excluí-lo, ${made} e todas as respostas também serão excluídos, `
            + 'e isso afeta o painel de Feedbacks (termômetro e 9-box) e os históricos dos colaboradores. '
            + 'Essa ação não pode ser desfeita por aqui.',
        confirmLabel: `Excluir modelo e ${n}`,
        requiresCheck: true,
        checkLabel: `Entendo que o modelo «${title}» e ${sessions === 1 ? 'o feedback feito' : `os ${n} feitos`} com ele serão excluídos.`,
    };
}

/** Pode enviar? Modelo usado exige a caixa marcada; nunca envia durante o carregamento ou o envio. */
export function canConfirmDeletion({ requiresCheck, checked, busy }) {
    if (busy) return false;
    return !requiresCheck || checked === true;
}

/** Corpo da requisição de exclusão do modelo: a contagem vista no alerta vai junto para o servidor conferir. */
export function templateDeletionPayload(impact, checked) {
    const sessions = Number(impact?.sessions_count) || 0;
    return sessions === 0 ? { sessions_count: 0 } : { confirmed: checked === true, sessions_count: sessions };
}
