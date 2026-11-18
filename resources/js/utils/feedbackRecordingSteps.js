/**
 * Etapas visíveis da seção «Gravação e relatório» (só apresentação): Enviar → Confirmar falantes → Processar →
 * Revisar relatório. Lê o que o servidor já manda (recording e recordingAi); não decide permissão nem regra.
 * Status de cada etapa: done | current | error | skipped | todo.
 */
export const RECORDING_STEP_LABELS = ['Enviar', 'Confirmar falantes', 'Processar', 'Revisar relatório'];

export function recordingSteps(recording, ai) {
    const labels = RECORDING_STEP_LABELS;
    const make = (statuses) => labels.map((label, i) => ({ label, status: statuses[i] }));

    if (!recording) {
        return make(['current', 'todo', 'todo', 'todo']);
    }

    const speakersStatus = !recording.has_speaker_labels ? 'skipped' : ai?.speakers_mapped || ai?.clean ? 'done' : 'current';

    if (!ai) {
        return make(['done', speakersStatus === 'done' ? 'done' : speakersStatus, 'todo', 'todo']);
    }

    if (ai.status === 'ready') {
        return make(['done', speakersStatus === 'current' ? 'done' : speakersStatus, 'done', 'current']);
    }
    if (ai.status === 'processing') {
        return make(['done', speakersStatus === 'current' ? 'done' : speakersStatus, 'current', 'todo']);
    }
    if (ai.status === 'failed') {
        return make(['done', speakersStatus === 'current' ? 'done' : speakersStatus, 'error', 'todo']);
    }

    // Enviado: falta confirmar os falantes (se houver) ou processar.
    if (speakersStatus === 'current') {
        return make(['done', 'current', 'todo', 'todo']);
    }

    return make(['done', speakersStatus, 'current', 'todo']);
}
