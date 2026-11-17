<script setup>
import Modal from '@/Components/Modal.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { answersLabel, canConfirmDeletion } from '@/utils/feedbackDeletion';
import { formatDateTime } from '@/utils/dateOnly';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * Exclusão de um feedback: mostra de quem é (colaborador, líder, data) e quantas respostas saem junto;
 * exige marcar a confirmação (o servidor também recusa sem ela).
 */
const props = defineProps({
    session: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const checked = ref(false);
const submitting = ref(false);
const error = ref('');

watch(
    () => props.session?.id,
    () => {
        checked.value = false;
        error.value = '';
    },
);

function close() {
    if (submitting.value) return;
    emit('close');
}

function submit() {
    if (!canConfirmDeletion({ requiresCheck: true, checked: checked.value, busy: submitting.value })) return;
    router.delete(feedbackRoute('sessions.destroy', props.session.id), {
        data: { confirmed: true },
        onStart: () => (submitting.value = true),
        onFinish: () => (submitting.value = false),
        onSuccess: () => emit('close'),
        onError: (errors) => (error.value = errors.confirmed || Object.values(errors)[0] || 'Não foi possível excluir.'),
    });
}
</script>

<template>
    <Modal :show="session !== null" max-width="lg" @close="close">
        <div v-if="session" class="p-6" data-feedback-session-delete>
            <h2 class="text-lg font-semibold text-slate-900">Excluir feedback</h2>
            <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                Este feedback e todas as respostas serão excluídos e não poderão ser recuperados por aqui.
            </p>

            <dl class="mt-4 grid grid-cols-[auto,1fr] gap-x-4 gap-y-1 text-sm">
                <dt class="text-slate-500">Feedback</dt>
                <dd class="font-medium text-slate-900">{{ session.title }}</dd>
                <dt class="text-slate-500">Colaborador</dt>
                <dd class="text-slate-800">{{ session.employee?.name ?? '—' }}</dd>
                <dt class="text-slate-500">Líder</dt>
                <dd class="text-slate-800">{{ session.leader?.name ?? '—' }}</dd>
                <dt class="text-slate-500">Data</dt>
                <dd class="text-slate-800">{{ session.scheduled_at ? formatDateTime(session.scheduled_at) : '—' }}</dd>
                <dt class="text-slate-500">Respostas</dt>
                <dd class="text-slate-800">{{ answersLabel(session.answers_count) }}</dd>
            </dl>

            <label class="mt-4 flex items-start gap-2 text-sm text-slate-800">
                <input v-model="checked" type="checkbox" class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                <span>Entendo que este feedback e as respostas serão excluídos.</span>
            </label>

            <p v-if="error" class="mt-3 text-sm font-medium text-rose-700" role="alert">{{ error }}</p>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    :disabled="submitting"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!checked || submitting"
                    @click="submit"
                >
                    {{ submitting ? 'Excluindo…' : 'Excluir feedback' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
