/**
 * Passos do questionário «Minha jornada» (Caderno §7). Campanha V11: uma afirmação por etapa (39 etapas de pergunta);
 * campanhas antigas: uma dimensão por etapa, como sempre foi. Só lógica pura, sem tela.
 */

/**
 * @param {Array<{id: number, title: string, description?: string, questions?: Array<{id: number, body: string}>}>} sections
 * @param {boolean} perQuestion
 * @returns {Array<{key: string, sectionId: number, sectionTitle: string, description: string, questions: Array<object>, ordinal: number|null}>}
 */
export const buildSteps = (sections, perQuestion) => {
    const steps = [];
    let ordinal = 0;

    for (const section of sections) {
        const questions = section.questions ?? [];
        if (!questions.length) {
            continue;
        }

        if (perQuestion) {
            for (const question of questions) {
                ordinal += 1;
                steps.push({
                    key: `q-${question.id}`,
                    sectionId: section.id,
                    sectionTitle: section.title,
                    description: '',
                    questions: [question],
                    ordinal,
                });
            }
        } else {
            steps.push({
                key: `s-${section.id}`,
                sectionId: section.id,
                sectionTitle: section.title,
                description: section.description ?? '',
                questions,
                ordinal: null,
            });
        }
    }

    return steps;
};

/** Perguntas da etapa ainda sem resposta (1 a 5). */
export const unansweredIn = (step, answers) =>
    step.questions.filter((question) => answers[question.id] === null || answers[question.id] === undefined || answers[question.id] === '');

/** Índice (na lista de etapas) da primeira etapa de cada dimensão, para «Rever» na revisão final. */
export const firstStepOfSection = (steps, sectionId) => steps.findIndex((step) => step.sectionId === sectionId);

export const progressPercent = (answers) => {
    const values = Object.values(answers);

    return values.length ? Math.round((values.filter((value) => value !== null && value !== '' && value !== undefined).length / values.length) * 100) : 0;
};
