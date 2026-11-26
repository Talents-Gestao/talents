<script setup>
import Modal from '@/Components/Modal.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { canConfirmDeletion, templateDeletionCopy, templateDeletionPayload } from '@/utils/feedbackDeletion';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

/**
 * Exclusão de modelo: o impacto (feedbacks e respostas que saem junto) vem do servidor ao abrir.
 * Modelo usado exige marcar a confirmação; se a contagem mudou até confirmar, o servidor recusa e o alerta recarrega.
 */
const props = defineProps({
    template: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const impact = ref(null);
const loading = ref(false);
const submitting = ref(false);
const checked = ref(false);
const error = ref('');

const copy = computed(() => (impact.value ? templateDeletionCopy(impact.value) : null));
// Modelo universal já usado (em qualquer empresa): não se exclui; a orientação é desativar.
const blocked = computed(() => Boolean(impact.value?.blocked));
const canSubmit = computed(
    () => copy.value !== null
        && !blocked.value
        && canConfirmDeletion({ requiresCheck: copy.value.requiresCheck, checked: checked.value, busy: loading.value || submitting.value }),
);

async function loadImpact() {
    if (!props.template) return;
    loading.value = true;
    checked.value = false;
    try {
        const { data } = await axios.get(feedbackRoute('templates.deletion', props.template.id));
        impact.value = data;
    } catch {
        impact.value = null;
        error.value = 'Não foi possível calcular o impacto da exclusão. Tente de novo.';
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.template?.id,
    (id) => {
        impact.value = null;
        error.value = '';
        if (id) loadImpact();
    },
    { immediate: true },
);

function close() {
    if (submitting.value) return;
    emit('close');
}

function submit() {
    if (!canSubmit.value) return;
    error.value = '';
    router.delete(feedbackRoute('templates.destroy', props.template.id), {
        data: templateDeletionPayload(impact.value, checked.value),
        preserveScroll: true,
        onStart: () => (submitting.value = true),
        onFinish: () => (submitting.value = false),
        onSuccess: () => emit('close'),
        onError: (errors) => {
            error.value = errors.template || errors.sessions_count || errors.confirmed || Object.values(errors)[0] || 'Não foi possível excluir.';
            // Contagem desatualizada: mostra o impacto atual e pede nova confirmação.
            if (errors.sessions_count || errors.confirmed) loadImpact();
        },
    });
}
</script>

<template>
    <Modal :show="template !== null" max-width="lg" @close="close">
        <div class="p-6" data-feedback-template-delete>
            <h2 class="text-lg font-semibold text-slate-900">{{ copy?.heading ?? 'Excluir modelo' }}</h2>

            <p v-if="loading" class="mt-3 text-sm text-slate-500">Calculando o impacto…</p>

            <p
                v-else-if="blocked"
                class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                role="alert"
            >
                {{ impact.blocked_message }}
            </p>

            <template v-else-if="copy">
                <p
                    class="mt-3 text-sm"
                    :class="copy.inUse ? 'rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900' : 'text-slate-600'"
                    :role="copy.inUse ? 'alert' : undefined"
                >
                    {{ copy.message }}
                </p>
                <p v-if="copy.inUse && impact.answers_count" class="mt-2 text-xs text-slate-500">
                    {{ impact.answers_count === 1 ? '1 resposta registrada' : `${impact.answers_count} respostas registradas` }}
                    nesses feedbacks.
                </p>
                <label v-if="copy.requiresCheck" class="mt-4 flex items-start gap-2 text-sm text-slate-800">
                    <input
                        v-model="checked"
                        type="checkbox"
                        class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                    />
                    <span>{{ copy.checkLabel }}</span>
                </label>
            </template>

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
                    v-if="!blocked"
                    type="button"
                    class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ submitting ? 'Excluindo…' : (copy?.confirmLabel ?? 'Excluir modelo') }}
                </button>
            </div>
        </div>
    </Modal>
</template>
