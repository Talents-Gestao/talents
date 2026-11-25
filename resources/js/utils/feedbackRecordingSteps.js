/**
 * Etapas visíveis da seção «Gravação e relatório» (só apresentação): Enviar → Confirmar falantes → Processar →
 * Revisar relatório. Lê o que o servidor já manda (recording e recordingAi); não decide permissão nem regra.
 * Status de cada etapa: done | current | error | skipped | todo.
 */
export const RECORDING_STEP_LABELS = ['Enviar', 'Confirmar falantes', 'Processar', 'Revisar relatório'];

/** Chave de cada etapa (a mesma que o servidor usa em «voltar»). */
export const RECORDING_STEP_KEYS = ['upload', 'speakers', 'process', 'review'];

export function recordingSteps(recording, ai) {
    const labels = RECORDING_STEP_LABELS;
    const make = (statuses) => labels.map((label, i) => ({ key: RECORDING_STEP_KEYS[i], label, status: statuses[i] }));

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

export const REWIND_PROCESSING_REASON = 'A gravação está sendo processada. Aguarde terminar para voltar uma etapa.';

/**
 * Voltar etapas: para cada etapa, { clickable, reason }. Só etapas concluídas podem ser clicadas; a atual e as futuras,
 * nunca. reason (tooltip) explica por que uma etapa concluída não está disponível. Quem não pode alterar a gravação
 * recebe tudo como não clicável e sem motivo (barra só informativa). O servidor confere tudo de novo.
 */
export function rewindOptions(steps, recording, ai, access = {}) {
    const none = steps.map(() => ({ clickable: false, reason: '' }));
    const allowedBy = {
        upload: !!access.canDelete,
        speakers: !!access.canProcess && !!recording?.has_speaker_labels,
        process: !!access.canProcess && ai?.status === 'ready',
    };
    if (!recording || access.locked || !Object.values(allowedBy).some(Boolean)) return none;

    const blocked = access.rewindBlockedReason || (ai?.status === 'processing' ? REWIND_PROCESSING_REASON : '');

    return steps.map((step) => {
        if (step.status !== 'done' || !allowedBy[step.key]) return { clickable: false, reason: '' };
        if (blocked) return { clickable: false, reason: blocked };
        if (step.key === 'process') {
            if (ai?.gate?.allowed !== true) return { clickable: false, reason: 'A IA ainda não foi liberada pela Talents para Feedbacks.' };
            if (ai?.limit_reached) return { clickable: false, reason: 'O limite mensal de IA da empresa foi atingido.' };
        }

        return { clickable: true, reason: '' };
    });
}

/** Texto da confirmação de cada volta. */
export function rewindMessage(key, ai) {
    if (key === 'upload') {
        return 'A transcrição atual, o mapeamento de falantes e os relatórios desta gravação serão excluídos. Esta ação não pode ser desfeita.';
    }
    if (key === 'speakers') {
        const base = 'O mapeamento atual abre já preenchido para você ajustar.';
        return ai?.report || ai?.clean
            ? `${base} O relatório atual vai para o histórico e será preciso processar de novo.`
            : base;
    }

    return 'Um novo relatório será gerado a partir da transcrição limpa atual. O relatório atual fica guardado como versão anterior.';
}
