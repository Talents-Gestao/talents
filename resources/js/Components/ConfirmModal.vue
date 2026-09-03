<script setup>
import Modal from '@/Components/Modal.vue';
import {
    acceptConfirmDialog,
    cancelConfirmDialog,
    useConfirmDialogState,
} from '@/composables/useConfirmDialog';

const state = useConfirmDialogState();
</script>

<template>
    <Modal :show="state.show" max-width="lg" @close="cancelConfirmDialog">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ state.title }}
            </h2>
            <p
                v-if="state.message"
                class="mt-2 whitespace-pre-line text-sm text-slate-600"
            >
                {{ state.message }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    @click="cancelConfirmDialog"
                >
                    {{ state.cancelLabel }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition"
                    :class="
                        state.variant === 'danger'
                            ? 'bg-rose-600 hover:bg-rose-700'
                            : 'bg-talents-700 hover:bg-talents-800'
                    "
                    @click="acceptConfirmDialog"
                >
                    {{ state.confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
