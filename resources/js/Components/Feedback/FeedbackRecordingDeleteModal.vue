<script setup>
import Modal from '@/Components/Modal.vue';
import { ref, watch } from 'vue';

/**
 * Confirmação de «Excluir gravação» (só apresentação): exige marcar a caixa; quem usa faz a requisição.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const checked = ref(false);

watch(
    () => props.show,
    (open) => {
        if (open) checked.value = false;
    },
);

function close() {
    if (props.submitting) return;
    emit('close');
}

function confirm() {
    if (!checked.value || props.submitting) return;
    emit('confirm');
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6" data-feedback-recording-delete>
            <h2 class="text-lg font-semibold text-slate-900">Excluir gravação</h2>
            <p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                A transcrição desta gravação será excluída e não poderá ser recuperada por aqui. As respostas do feedback não mudam.
            </p>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-800">
                <input v-model="checked" type="checkbox" class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                <span>Entendo que a gravação será excluída.</span>
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
                    @click="confirm"
                >
                    {{ submitting ? 'Excluindo…' : 'Excluir gravação' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
