/**
 * Lógica pura do vínculo da entrevista com candidato e vaga (Contratação · Entrevistas).
 * O servidor exige que o formulário devolva o candidato e a vaga mostrados na confirmação.
 */

/** Valor de confirmação quando a entrevista não tem vaga. */
export const NO_APPLICATION = 'none';

/**
 * @param {{ id: number }|null} candidate
 * @param {number|string|null|undefined} applicationId
 * @returns {{ confirm_candidate_id: string, confirm_application_id: string }}
 */
export function confirmationPayload(candidate, applicationId) {
    return {
        confirm_candidate_id: candidate ? String(candidate.id) : '',
        confirm_application_id: applicationId ? String(applicationId) : NO_APPLICATION,
    };
}

/**
 * Texto da confirmação, com candidato e vaga por extenso.
 *
 * @param {{ name: string, applications?: Array<{ id: number, vacancy_title?: string|null, company_name?: string|null }> }|null} candidate
 * @param {number|string|null|undefined} applicationId
 */
export function confirmationText(candidate, applicationId) {
    if (!candidate) {
        return '';
    }
    const application = (candidate.applications ?? []).find((a) => String(a.id) === String(applicationId ?? ''));
    if (!application) {
        return `Confirmo que esta entrevista é de ${candidate.name}, sem vaga vinculada.`;
    }
    const vacancy = [application.vacancy_title, application.company_name].filter(Boolean).join(' · ');
    return `Confirmo que esta entrevista é de ${candidate.name}, para a vaga ${vacancy || 'selecionada'}.`;
}

/**
 * Rótulo da candidatura para o seletor de vaga.
 *
 * @param {{ vacancy_title?: string|null, company_name?: string|null }} application
 */
export function applicationLabel(application) {
    return [application.vacancy_title ?? 'Vaga', application.company_name].filter(Boolean).join(' · ');
}

/**
 * Só fontes de texto exigem a confirmação antes de gravar.
 *
 * @param {Array<{ value: string, is_text: boolean }>} sources
 * @param {string} value
 */
export function isTextSource(sources, value) {
    return Boolean((sources ?? []).find((s) => s.value === value)?.is_text);
}
