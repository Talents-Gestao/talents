/**
 * Regras puras do formulário de modelo de feedback (Client/Feedbacks/Templates/Form.vue).
 *
 * Depois que o modelo é usado em algum feedback, as respostas apontam para as perguntas dele,
 * então seções e perguntas ficam travadas: só nome, descrição e «Ativo» são enviados.
 */

/**
 * @param {{ title: string, description: string, is_active: boolean, sections?: Array<object> }} data
 * @param {boolean} structureLocked
 */
export function templateFormPayload(data, structureLocked) {
    if (!structureLocked) {
        return data;
    }

    return { title: data.title, description: data.description, is_active: data.is_active };
}

/**
 * Motivo do bloqueio, mostrado acima das seções (ao lado do botão «Duplicar modelo»).
 *
 * @param {number} sessionsCount
 */
export function templateLockMessage(sessionsCount) {
    const count = Number(sessionsCount) || 0;
    const usage = count === 1 ? 'em 1 feedback' : count > 1 ? `em ${count} feedbacks` : 'em feedbacks';

    return (
        `Este modelo já foi usado ${usage}, e as respostas registradas dependem destas perguntas. ` +
        'Por isso seções e perguntas não podem mais ser alteradas; nome, descrição e «Ativo» continuam editáveis. ' +
        'Para mudar as perguntas, duplique o modelo e edite a cópia.'
    );
}

/**
 * Opções no formato do formulário: {value, label}. O valor existente é preservado (o painel da equipe
 * lê as respostas por valor); opção nova vai sem valor e o servidor gera um.
 *
 * @param {Array<string | { value?: string, label?: string }> | null | undefined} options
 * @returns {Array<{ value: string, label: string }>}
 */
export function normalizeTemplateOptions(options) {
    return (options || []).map((option) =>
        typeof option === 'object' && option !== null
            ? { value: String(option.value ?? ''), label: String(option.label ?? '') }
            : { value: '', label: String(option ?? '') },
    );
}

/**
 * Garante ao menos duas opções ao mudar a pergunta para «Escolha única».
 *
 * @param {Array<{ value: string, label: string }>} options
 */
export function ensureTwoOptions(options) {
    const result = [...(options || [])];
    while (result.length < 2) {
        result.push({ value: '', label: '' });
    }

    return result;
}
