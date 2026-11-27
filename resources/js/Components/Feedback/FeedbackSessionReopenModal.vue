<script setup>
import Modal from '@/Components/Modal.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { reopenCopy } from '@/utils/feedbackSessionVersion';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

/**
 * «Reabrir para edição» de um feedback aguardando assinaturas: cancela os convites e descarta as assinaturas
 * coletadas. Exige confirmação explícita; a quantidade de assinaturas vai junto para o servidor conferir.
 */
const props = defineProps({
    session: { type: Object, default: null },
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const checked = ref(false);
const submitting = ref(false);
const error = ref('');

const copy = computed(() => reopenCopy(props.session?.reopen));

watch(
    () => props.show,
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
    if (!checked.value || submitting.value) return;
    router.patch(
        feedbackRoute('sessions.reopen', props.session.id),
        { confirmed: true, signed_count: props.session.reopen?.signed ?? 0 },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
            onSuccess: () => emit('close'),
            onError: (errors) => {
                error.value = errors.signed_count || errors.reopen || errors.confirmed || Object.values(errors)[0] || 'Não foi possível reabrir.';
            },
        },
    );
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6" data-feedback-reopen>
            <h2 class="text-lg font-semibold text-slate-900">Reabrir para edição</h2>
            <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                {{ copy.message }}
            </p>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-800">
                <input v-model="checked" type="checkbox" class="mt-0.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                <span>{{ copy.checkLabel }}</span>
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
                    {{ submitting ? 'Reabrindo…' : 'Reabrir para edição' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
