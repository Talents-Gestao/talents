<script setup>
import FeedbackRecordingAi from '@/Components/Feedback/FeedbackRecordingAi.vue';
import FeedbackRecordingDeleteModal from '@/Components/Feedback/FeedbackRecordingDeleteModal.vue';
import FeedbackRecordingDetailsModal from '@/Components/Feedback/FeedbackRecordingDetailsModal.vue';
import FeedbackRecordingRewindModal from '@/Components/Feedback/FeedbackRecordingRewindModal.vue';
import FeedbackRecordingSection from '@/Components/Feedback/FeedbackRecordingSection.vue';
import FeedbackRecordingSteps from '@/Components/Feedback/FeedbackRecordingSteps.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { recordingSteps, rewindMessage, rewindOptions } from '@/utils/feedbackRecordingSteps';
import { canAdjustSpeakers, canRegenerateReport, recordingState } from '@/utils/feedbackRecordingView';
import { ArrowPathIcon, InformationCircleIcon, TrashIcon, UserGroupIcon } from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, ref, watch } from 'vue';

/**
 * Seção «Gravação e relatório» do feedback, ligada ao servidor: envia a transcrição do Plaud (com consentimento),
 * abre o texto original (só administradores, sob demanda e registrado), exclui a gravação e, com a IA
 * (FeedbackRecordingAi), mapeia falantes, processa e mostra o relatório e as transcrições. O visual fica em
 * FeedbackRecordingSection, FeedbackRecordingDetailsModal e FeedbackRecordingDeleteModal.
 */
const props = defineProps({
    sessionId: { type: Number, required: true },
    recording: { type: Object, default: null },
    /** Estado da IA (FeedbackRecordingAccess::aiPayload). */
    ai: { type: Object, default: null },
    access: {
        type: Object,
        default: () => ({ canUpload: false, canDelete: false, canViewOriginal: false, locked: false }),
    },
    /** Nomes já presentes na sessão: { leader, employee }. */
    people: { type: Object, default: () => ({}) },
    /** Dentro da aba «Gravação e relatório» do detalhe (FeedbackRecordingSection embedded). */
    embedded: { type: Boolean, default: false },
});

const form = useForm({ file: null, consent: false });
const resetKey = ref(0);

const original = ref(null);
const loadingOriginal = ref(false);
const originalError = ref('');

const deleting = ref(false);
const deleteSubmitting = ref(false);
const deleteError = ref('');

watch(
    () => props.recording?.id,
    () => {
        original.value = null;
        originalError.value = '';
    },
);

function upload({ file, consent }) {
    if (form.processing) return;
    form.file = file;
    form.consent = consent;
    form.post(feedbackRoute('sessions.recording.store', props.sessionId), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            resetKey.value += 1;
        },
    });
}

/** Original sob demanda: cada abertura fica registrada no servidor. */
async function showOriginal() {
    if (loadingOriginal.value) return;
    loadingOriginal.value = true;
    originalError.value = '';
    try {
        const { data } = await axios.get(feedbackRoute('sessions.recording.original', props.sessionId));
        original.value = data;
    } catch {
        originalError.value = 'Não foi possível abrir a transcrição. Tente de novo.';
    } finally {
        loadingOriginal.value = false;
    }
}

const state = computed(() => recordingState(props.recording, props.ai, props.access));
const steps = computed(() => recordingSteps(props.recording, props.ai));
const stepOptions = computed(() => rewindOptions(steps.value, props.recording, props.ai, props.access));

// ── Voltar etapas (barra «Gravação»): confirmação e, conforme a etapa, a ação de sempre ──
const rewindStep = ref('');
const rewindSubmitting = ref(false);
const rewindError = ref('');
/** Muda ao confirmar «Voltar para Confirmar falantes»: a IA reabre o mapeamento atual preenchido. */
const rewindSpeakersSignal = ref(0);
const rewindLabel = computed(() => steps.value.find((s) => s.key === rewindStep.value)?.label ?? '');

function openRewind(key) {
    rewindError.value = '';
    rewindStep.value = key;
}
function closeRewind() {
    if (rewindSubmitting.value) return;
    rewindStep.value = '';
}
function confirmRewind() {
    const step = rewindStep.value;
    if (!step || rewindSubmitting.value) return;
    if (step === 'speakers') {
        // Nada muda até confirmar o novo mapeamento (a volta é registrada nesse envio).
        rewindStep.value = '';
        nextTick(() => (rewindSpeakersSignal.value += 1));
        return;
    }
    router.post(feedbackRoute('sessions.recording.rewind', props.sessionId), { step, confirmed: true }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (rewindSubmitting.value = true),
        onFinish: () => (rewindSubmitting.value = false),
        onSuccess: () => {
            rewindStep.value = '';
            // Novo envio: arquivo e consentimento vazios (o consentimento é pedido de novo).
            if (step === 'upload') resetKey.value += 1;
        },
        onError: (errors) => (rewindError.value = errors.step || errors.ai || errors.confirmed || Object.values(errors)[0] || 'Não foi possível voltar a etapa.'),
    });
}

// ── Detalhes técnicos e privacidade (modal) ──
const detailsOpen = ref(false);
let detailsOpener = null;
function openDetails() {
    detailsOpener = document.activeElement;
    detailsOpen.value = true;
}
function closeDetails() {
    detailsOpen.value = false;
    // Devolve o foco a quem abriu; se foi um item do menu (já fechado), ao botão «Opções da gravação».
    setTimeout(() => {
        const visible = detailsOpener && detailsOpener.isConnected && detailsOpener.offsetParent !== null;
        const target = visible ? detailsOpener : document.querySelector('[data-feedback-recording] [data-menu-trigger]');
        target?.focus?.();
    }, 250);
}

// ── Menu «Opções da gravação» ──
const regenerating = ref(false);
const adjustSignal = ref(0);
const menuItems = computed(() => [
    ...(props.recording
        ? [{ key: 'details', label: 'Detalhes técnicos e privacidade', icon: InformationCircleIcon, onSelect: openDetails }]
        : []),
    ...(canAdjustSpeakers(props.ai, props.access)
        ? [{ key: 'adjust-speakers', label: 'Alterar falantes', icon: UserGroupIcon, onSelect: () => nextTick(() => (adjustSignal.value += 1)) }]
        : []),
    ...(canRegenerateReport(props.ai, props.access)
        ? [{ key: 'regenerate', label: 'Gerar relatório novamente', icon: ArrowPathIcon, disabled: regenerating.value, onSelect: regenerate }]
        : []),
    ...(props.recording && props.access.canDelete && !props.access.locked
        ? [{ key: 'delete-recording', label: 'Excluir gravação', icon: TrashIcon, danger: true, onSelect: openDelete }]
        : []),
]);

function regenerate() {
    if (regenerating.value) return;
    router.post(feedbackRoute('sessions.recording.report', props.sessionId), {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (regenerating.value = true),
        onFinish: () => (regenerating.value = false),
    });
}

function openDelete() {
    deleteError.value = '';
    deleting.value = true;
}

function confirmDelete() {
    if (deleteSubmitting.value) return;
    router.delete(feedbackRoute('sessions.recording.destroy', props.sessionId), {
        data: { confirmed: true },
        preserveScroll: true,
        preserveState: true,
        onStart: () => (deleteSubmitting.value = true),
        onFinish: () => (deleteSubmitting.value = false),
        onSuccess: () => (deleting.value = false),
        onError: (errors) => (deleteError.value = errors.confirmed || Object.values(errors)[0] || 'Não foi possível excluir.'),
    });
}
</script>

<template>
    <FeedbackRecordingSection
        :recording="recording"
        :access="access"
        :state="state"
        :errors="form.errors"
        :uploading="form.processing"
        :reset-key="resetKey"
        :menu-items="menuItems"
        :embedded="embedded"
        :show-no-speakers-warning="!!recording && !recording.has_speaker_labels && ['queued', 'waiting', 'limit', 'idle'].includes(state)"
        @file-change="form.clearErrors('file')"
        @upload="upload"
        @open-details="openDetails"
    >
        <template #steps>
            <FeedbackRecordingSteps :steps="steps" :options="stepOptions" @rewind="openRewind" />
        </template>
        <template v-if="ai" #ai>
            <FeedbackRecordingAi
                :session-id="sessionId"
                :ai="ai"
                :access="access"
                :state="state"
                :people="people"
                :adjust-signal="adjustSignal"
                :rewind-speakers-signal="rewindSpeakersSignal"
                :original="original"
                :loading-original="loadingOriginal"
                :original-error="originalError"
                @show-original="showOriginal"
            />
        </template>
    </FeedbackRecordingSection>

    <FeedbackRecordingDetailsModal :show="detailsOpen" :recording="recording" @close="closeDetails" />

    <FeedbackRecordingRewindModal
        :show="!!rewindStep"
        :step="rewindStep"
        :label="rewindLabel"
        :message="rewindMessage(rewindStep, ai)"
        :submitting="rewindSubmitting"
        :error="rewindError"
        @close="closeRewind"
        @confirm="confirmRewind"
    />

    <FeedbackRecordingDeleteModal
        :show="deleting"
        :submitting="deleteSubmitting"
        :error="deleteError"
        @close="deleting = false"
        @confirm="confirmDelete"
    />
</template>
