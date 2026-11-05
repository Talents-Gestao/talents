<script setup>
import InfoTooltip from '@/Components/InfoTooltip.vue';
import InputError from '@/Components/InputError.vue';
import MeasureHistory from '@/Components/Nr1Plan/MeasureHistory.vue';
import MeasureSuggestionForm from '@/Components/Nr1Plan/MeasureSuggestionForm.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    surveyId: { type: Number, required: true },
    measures: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    canEditSuggestion: { type: Boolean, default: true },
    dueOrigins: { type: Object, default: () => ({}) },
    measuresAi: {
        type: Object,
        default: () => ({ generated_at: null, pending: false, refresh_url: null }),
    },
});

const editingId = ref(null);
const toggleEdit = (id) => {
    editingId.value = editingId.value === id ? null : id;
};

const form = useForm({
    factor_label: '',
    measure_description: '',
    evidence_basis: 'questionnaire_indication',
    responsible_text: '',
    suggested_due_date: '',
});

const selectedStatus = reactive({});

watch(
    () => props.measures,
    (rows) => {
        for (const measure of rows) {
            selectedStatus[measure.id] = measure.status;
        }
    },
    { immediate: true },
);

const submit = () => {
    form.post(route('admin.companies.surveys.measures.store', [props.companyId, props.surveyId]), {
        preserveScroll: true,
        onSuccess: () => form.reset('factor_label', 'measure_description', 'responsible_text', 'suggested_due_date'),
    });
};

const refreshMeasures = () => {
    if (!props.measuresAi?.refresh_url || props.measuresAi.pending) {
        return;
    }
    router.post(props.measuresAi.refresh_url, {}, { preserveScroll: true });
};

const saveStatus = (measure) => {
    router.patch(
        route('admin.companies.surveys.measures.status', [props.companyId, props.surveyId, measure.id]),
        { status: selectedStatus[measure.id] },
        { preserveScroll: true },
    );
};

let refreshTimer = null;

const watchRefresh = () => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
    if (props.measuresAi?.pending) {
        refreshTimer = setInterval(() => {
            router.reload({ only: ['section'], preserveScroll: true });
        }, 5000);
    }
};

onMounted(watchRefresh);
onUnmounted(() => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});
watch(() => props.measuresAi?.pending, watchRefresh);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="text-sm text-slate-600">
                Inclua, edite e atualize o status das medidas nesta mesma tela.
                <span v-if="measuresAi.generated_at"> Última geração da Mia: {{ measuresAi.generated_at }}.</span>
            </p>
            <button
                type="button"
                class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                :disabled="measuresAi.pending"
                @click="refreshMeasures"
            >
                {{ measuresAi.pending ? 'Atualizando…' : 'Atualizar medidas' }}
            </button>
        </div>
        <p v-if="measuresAi.pending" class="text-sm text-sky-800">
            Geração em andamento. A lista atualiza sozinha em alguns segundos.
        </p>

        <form v-if="canEditSuggestion" class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2" @submit.prevent="submit">
            <h5 class="text-sm font-semibold text-slate-900 sm:col-span-2">Nova medida</h5>
            <div>
                <div class="flex items-center gap-1">
                    <label class="text-sm text-slate-700" for="parecer-factor">Fator</label>
                    <InfoTooltip text="Fator psicossocial a que a medida se refere. Esse nome sai no subsídio AEP/PGR e no dossiê." />
                </div>
                <input id="parecer-factor" v-model="form.factor_label" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <InputError class="mt-1" :message="form.errors.factor_label" />
            </div>
            <div>
                <div class="flex items-center gap-1">
                    <label class="text-sm text-slate-700" for="parecer-basis">Base da evidência</label>
                    <InfoTooltip text="Indício do questionário é só o que a pesquisa apontou. Fator validado é quando o responsável técnico já confirmou aquele fator." />
                </div>
                <select id="parecer-basis" v-model="form.evidence_basis" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="questionnaire_indication">Indício do questionário</option>
                    <option value="validated_factor">Fator validado</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="text-sm text-slate-700" for="parecer-desc">Medida</label>
                <textarea id="parecer-desc" v-model="form.measure_description" required rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <InputError class="mt-1" :message="form.errors.measure_description" />
            </div>
            <div>
                <label class="text-sm text-slate-700" for="parecer-resp">Responsável (texto)</label>
                <input id="parecer-resp" v-model="form.responsible_text" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-700" for="parecer-due">Prazo sugerido</label>
                <input id="parecer-due" v-model="form.suggested_due_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </div>
            <div class="sm:col-span-2">
                <PrimaryButton :disabled="form.processing">Registrar medida</PrimaryButton>
            </div>
        </form>

        <p v-if="measures.length === 0" class="text-sm text-slate-600">Nenhuma medida ainda. Elas aparecem quando a pesquisa encerra e a Mia gera o plano, ou quando você registra uma.</p>
        <ul v-else class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            <li v-for="measure in measures" :key="measure.id" class="flex flex-wrap items-start justify-between gap-3 p-4">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-900">{{ measure.factor_label }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ measure.measure_description }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ measure.status_label }}
                        <span v-if="measure.origin === 'engine_suggestion'"> · Mia</span>
                    </p>
                </div>
                <button
                    v-if="canEditSuggestion"
                    type="button"
                    class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    @click="toggleEdit(measure.id)"
                >
                    {{ editingId === measure.id ? 'Fechar' : 'Editar' }}
                </button>
                <form class="flex items-center gap-2" @submit.prevent="saveStatus(measure)">
                    <select v-model="selectedStatus[measure.id]" class="rounded-lg border-slate-300 text-sm" :aria-label="`Status de ${measure.factor_label}`">
                        <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <button type="submit" class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        Atualizar status
                    </button>
                </form>
                <div class="w-full text-sm">
                    <p class="text-xs text-slate-600">
                        Plano: <span class="font-medium">{{ measure.plan.status_label }}</span>
                        <template v-if="measure.plan.suggested.responsible"> · Responsável sugerido: {{ measure.plan.suggested.responsible }}</template>
                        <template v-if="measure.plan.suggested.due_date"> · Prazo sugerido: {{ measure.plan.suggested.due_date }}</template>
                    </p>
                    <MeasureSuggestionForm
                        v-if="editingId === measure.id && canEditSuggestion"
                        :company-id="companyId"
                        :survey-id="surveyId"
                        :measure="measure"
                        :due-origins="dueOrigins"
                    />
                    <p v-else-if="!canEditSuggestion" class="mt-2 text-xs text-slate-500">Campanha publicada: a sugestão não é mais editada pela Talents.</p>
                    <MeasureHistory :history="measure.plan.history" />
                </div>
            </li>
        </ul>
    </div>
</template>
