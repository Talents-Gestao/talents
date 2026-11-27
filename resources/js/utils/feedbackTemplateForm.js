/**
 * Regras puras do formulário de modelo de feedback (Client/Feedbacks/Templates/Form.vue).
 *
 * Seções e perguntas são sempre editáveis. Se a versão atual do modelo já foi usada em feedbacks, salvar
 * mudanças na estrutura cria a versão seguinte (os feedbacks já feitos continuam na versão deles).
 */

/**
 * O que vai ao servidor: nome, descrição e a estrutura completa (o servidor decide se cria versão).
 *
 * @param {{ title: string, description: string, sections?: Array<object> }} data
 */
export function templateFormPayload(data) {
    return { title: data.title, description: data.description, sections: data.sections };
}

/**
 * «Usado em N feedbacks; salvar criará a versão X». null quando a versão atual ainda não foi usada.
 *
 * @param {{ number?: number, used?: boolean, sessions_count?: number, next_number?: number | null } | null | undefined} version
 * @returns {string | null}
 */
export function templateVersionNotice(version) {
    if (!version?.used || !version.next_number) return null;
    const count = Number(version.sessions_count) || 0;
    const usage = count === 1 ? 'Usado em 1 feedback' : count > 1 ? `Usado em ${count} feedbacks` : 'Usado em feedbacks';

    return (
        `${usage}; salvar mudanças nas seções e perguntas criará a versão ${version.next_number}. ` +
        `Os feedbacks já feitos continuam na versão ${version.number}. Mudar só o nome ou a descrição não cria versão.`
    );
}

/**
 * Linha do histórico de versões.
 *
 * @param {{ number: number, sessions_count?: number, is_current?: boolean }} version
 */
export function versionUsageLabel(version) {
    const count = Number(version?.sessions_count) || 0;
    const usage = count === 0 ? 'sem feedbacks' : count === 1 ? '1 feedback' : `${count} feedbacks`;

    return `Versão ${version.number}${version.is_current ? ' (atual)' : ''} · ${usage}`;
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
