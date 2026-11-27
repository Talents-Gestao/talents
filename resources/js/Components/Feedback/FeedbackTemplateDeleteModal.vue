<script setup>
import Modal from '@/Components/Modal.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { templateDeletionCopy } from '@/utils/feedbackDeletion';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

/**
 * Exclusão de modelo: a situação vem do servidor ao abrir. Modelo nunca usado: confirmação simples.
 * Modelo usado (em qualquer versão ou empresa): não se exclui; o aviso orienta a arquivar. Nenhum feedback
 * é apagado junto com um modelo.
 */
const props = defineProps({
    template: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const impact = ref(null);
const loading = ref(false);
const submitting = ref(false);
const error = ref('');

const copy = computed(() => (impact.value ? templateDeletionCopy(impact.value) : null));
const canSubmit = computed(() => copy.value !== null && !copy.value.blocked && !loading.value && !submitting.value);

async function loadImpact() {
    if (!props.template) return;
    loading.value = true;
    try {
        const { data } = await axios.get(feedbackRoute('templates.deletion', props.template.id));
        impact.value = data;
    } catch {
        impact.value = null;
        error.value = 'Não foi possível verificar se o modelo pode ser excluído. Tente de novo.';
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
        preserveScroll: true,
        onStart: () => (submitting.value = true),
        onFinish: () => (submitting.value = false),
        onSuccess: () => emit('close'),
        onError: (errors) => {
            error.value = errors.template || Object.values(errors)[0] || 'Não foi possível excluir.';
            // O modelo passou a ser usado enquanto o aviso estava aberto: recarrega a situação.
            if (errors.template) loadImpact();
        },
    });
}
</script>

<template>
    <Modal :show="template !== null" max-width="lg" @close="close">
        <div class="p-6" data-feedback-template-delete>
            <h2 class="text-lg font-semibold text-slate-900">{{ copy?.heading ?? 'Excluir modelo' }}</h2>

            <p v-if="loading" class="mt-3 text-sm text-slate-500">Verificando o uso do modelo…</p>

            <p
                v-else-if="copy"
                class="mt-3 text-sm"
                :class="copy.blocked ? 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900' : 'text-slate-600'"
                :role="copy.blocked ? 'alert' : undefined"
            >
                {{ copy.message }}
            </p>

            <p v-if="error" class="mt-3 text-sm font-medium text-rose-700" role="alert">{{ error }}</p>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    :disabled="submitting"
                    @click="close"
                >
                    {{ copy?.blocked ? 'Fechar' : 'Cancelar' }}
                </button>
                <button
                    v-if="!copy?.blocked"
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
