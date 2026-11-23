/**
 * Formulário da sugestão de medida (Talents, plano de ação NR-1): o que mudou em relação ao que já está gravado.
 * Espelha a regra do servidor (PsychosocialMeasureService::updateSuggestion): a justificativa da alteração é
 * obrigatória quando um valor já registrado é trocado; preencher um campo vazio não exige justificativa.
 */

export const SUGGESTION_FIELDS = [
    'factor_label',
    'measure_description',
    'objective',
    'expected_result',
    'indicator',
    'responsible_text',
    'suggested_due_date',
    'suggested_due_origin',
    'suggested_due_note',
];

const norm = (value) => {
    const text = value == null ? '' : String(value).trim();
    return text === '' ? null : text;
};

/** Valores gravados da medida, no formato do formulário. */
export function suggestionValues(measure) {
    return {
        factor_label: measure?.factor_label ?? '',
        measure_description: measure?.measure_description ?? '',
        objective: measure?.objective ?? '',
        expected_result: measure?.expected_result ?? '',
        indicator: measure?.indicator ?? '',
        responsible_text: measure?.suggested?.responsible ?? '',
        suggested_due_date: measure?.suggested?.due_date ?? '',
        suggested_due_origin: measure?.suggested?.due_origin ?? '',
        suggested_due_note: measure?.suggested?.due_note ?? '',
    };
}

/** Campos com valor já registrado que o usuário trocou (ou apagou). */
export function replacedFields(original, current) {
    return SUGGESTION_FIELDS.filter((field) => {
        const before = norm(original?.[field]);
        return before !== null && before !== norm(current?.[field]);
    });
}

/** O prazo sugerido mudou: só então origem e justificativa do prazo ficam editáveis. */
export function dueDateChanged(original, current) {
    return norm(original?.suggested_due_date) !== norm(current?.suggested_due_date);
}
