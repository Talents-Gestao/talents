<script setup>
import Modal from '@/Components/Modal.vue';
import { computed } from 'vue';

/**
 * Confirmação curta de «Voltar para …» na barra «Gravação» (só apresentação): diz o que vai acontecer e quem usa
 * faz a requisição. «Enviar» é destrutivo (exclui a gravação) e usa o tom de perigo.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    /** upload | speakers | process */
    step: { type: String, default: '' },
    label: { type: String, default: '' },
    message: { type: String, default: '' },
    submitting: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['close', 'confirm']);

const danger = computed(() => props.step === 'upload');

function close() {
    if (props.submitting) return;
    emit('close');
}

function confirm() {
    if (props.submitting) return;
    emit('confirm');
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6" data-feedback-recording-rewind>
            <h2 class="text-lg font-semibold text-slate-900">Voltar para «{{ label }}»?</h2>
            <p
                class="mt-3 rounded-xl border px-4 py-3 text-sm"
                :class="danger ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-slate-200 bg-slate-50 text-slate-800'"
                :role="danger ? 'alert' : undefined"
            >
                {{ message }}
            </p>
            <p v-if="error" class="mt-3 text-sm font-medium text-rose-700" role="alert">{{ error }}</p>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    :disabled="submitting"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="danger ? 'bg-rose-600 hover:bg-rose-700 focus-visible:ring-rose-500' : 'bg-talents-600 hover:bg-talents-700 focus-visible:ring-talents-500'"
                    :disabled="submitting"
                    data-feedback-recording-rewind-confirm
                    @click="confirm"
                >
                    {{ submitting ? 'Voltando…' : 'Voltar' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
