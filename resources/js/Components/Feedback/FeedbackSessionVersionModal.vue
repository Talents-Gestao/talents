<script setup>
import Modal from '@/Components/Modal.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { versionUpdateSummary } from '@/utils/feedbackSessionVersion';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

/**
 * «Atualizar para a versão X»: a prévia (perguntas mantidas, novas e que saem) vem do servidor ao abrir;
 * exige confirmação explícita. Respostas de perguntas que saem ficam guardadas, fora da versão atual.
 */
const props = defineProps({
    session: { type: Object, default: null },
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const preview = ref(null);
const loading = ref(false);
const submitting = ref(false);
const checked = ref(false);
const error = ref('');

const summary = computed(() => versionUpdateSummary(preview.value));
const canSubmit = computed(() => !!preview.value && checked.value && !loading.value && !submitting.value);

async function loadPreview() {
    if (!props.session) return;
    loading.value = true;
    checked.value = false;
    error.value = '';
    try {
        const { data } = await axios.get(feedbackRoute('sessions.version.preview', props.session.id));
        preview.value = data;
    } catch (e) {
        preview.value = null;
        error.value = e?.response?.data?.message || 'Não foi possível carregar a prévia da nova versão. Tente de novo.';
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.show,
    (open) => {
        preview.value = null;
        if (open) loadPreview();
    },
    { immediate: true },
);

function close() {
    if (submitting.value) return;
    emit('close');
}

function submit() {
    if (!canSubmit.value) return;
    router.patch(
        feedbackRoute('sessions.version.update', props.session.id),
        { confirmed: true, version_id: preview.value.to.id },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
            onSuccess: () => emit('close'),
            onError: (errors) => {
                error.value = errors.version || errors.confirmed || Object.values(errors)[0] || 'Não foi possível atualizar.';
                if (errors.version) loadPreview();
            },
        },
    );
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="p-6" data-feedback-version-update>
            <h2 class="text-lg font-semibold text-slate-900">{{ summary?.title ?? 'Atualizar versão do modelo' }}</h2>

            <p v-if="loading" class="mt-3 text-sm text-slate-500">Comparando as versões…</p>

            <template v-else-if="summary">
                <p class="mt-3 text-sm text-slate-600">
                    Este feedback usa a versão {{ preview.from.number }} do modelo. Ao atualizar, ele passa a seguir a versão
                    {{ preview.to.number }}; as respostas das perguntas mantidas continuam onde estão.
                </p>
                <ul class="mt-3 space-y-1 text-sm text-slate-800">
                    <li v-for="line in summary.lines" :key="line">{{ line }}</li>
                </ul>
                <p v-if="summary.warning" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                    {{ summary.warning }}
                </p>

                <details v-if="preview.added.length || preview.removed.length" class="mt-3 text-sm text-slate-700">
                    <summary class="cursor-pointer font-medium text-slate-900">Ver as perguntas que mudam</summary>
                    <div v-if="preview.added.length" class="mt-2">
                        <p class="font-medium">Novas</p>
                        <ul class="ml-4 list-disc">
                            <li v-for="q in preview.added" :key="`a-${q.key}`">{{ q.body }}</li>
                        </ul>
                    </div>
                    <div v-if="preview.removed.length" class="mt-2">
                        <p class="font-medium">Saem da versão</p>
                        <ul class="ml-4 list-disc">
                            <li v-for="q in preview.removed" :key="`r-${q.key}`">
                                {{ q.body }}<span v-if="q.answered" class="text-slate-500"> (resposta guardada)</span>
                            </li>
                        </ul>
                    </div>
                </details>

                <label class="mt-4 flex items-start gap-2 text-sm text-slate-800">
                    <input v-model="checked" type="checkbox" class="mt-0.5 rounded border-slate-300 text-talents-700 focus:ring-talents-500" />
                    <span>Entendo as mudanças e quero atualizar este feedback para a versão {{ preview.to.number }}.</span>
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
                    type="button"
                    class="inline-flex items-center rounded-xl bg-talents-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-800 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ submitting ? 'Atualizando…' : 'Atualizar feedback' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
