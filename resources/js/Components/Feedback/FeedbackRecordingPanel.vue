<script setup>
import FeedbackRecordingAi from '@/Components/Feedback/FeedbackRecordingAi.vue';
import FeedbackRecordingDeleteModal from '@/Components/Feedback/FeedbackRecordingDeleteModal.vue';
import FeedbackRecordingSection from '@/Components/Feedback/FeedbackRecordingSection.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';

/**
 * Seção «Gravação e relatório» do feedback, ligada ao servidor: envia a transcrição do Plaud (com consentimento),
 * abre o texto original (só administradores, sob demanda), exclui a gravação e, com a IA (FeedbackRecordingAi),
 * mapeia falantes, processa e mostra o relatório e a transcrição limpa. O visual fica em
 * FeedbackRecordingSection e FeedbackRecordingDeleteModal.
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

async function showOriginal() {
    if (loadingOriginal.value) return;
    loadingOriginal.value = true;
    originalError.value = '';
    try {
        const { data } = await axios.get(feedbackRoute('sessions.recording.original', props.sessionId));
        original.value = data;
    } catch {
        originalError.value = 'Não foi possível abrir a transcrição original. Tente de novo.';
    } finally {
        loadingOriginal.value = false;
    }
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
        :errors="form.errors"
        :uploading="form.processing"
        :original="original"
        :loading-original="loadingOriginal"
        :original-error="originalError"
        :reset-key="resetKey"
        @file-change="form.clearErrors('file')"
        @upload="upload"
        @show-original="showOriginal"
        @hide-original="original = null"
        @request-delete="openDelete"
    >
        <template v-if="ai" #ai>
            <FeedbackRecordingAi
                :session-id="sessionId"
                :ai="ai"
                :access="access"
                :original="original"
                :loading-original="loadingOriginal"
                :original-error="originalError"
                @show-original="showOriginal"
            />
        </template>
    </FeedbackRecordingSection>

    <FeedbackRecordingDeleteModal
        :show="deleting"
        :submitting="deleteSubmitting"
        :error="deleteError"
        @close="deleting = false"
        @confirm="confirmDelete"
    />
</template>
