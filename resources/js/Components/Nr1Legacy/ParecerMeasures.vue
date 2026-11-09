<script setup>
import CompanyShowAccordion from '@/Components/Admin/Companies/CompanyShowAccordion.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue';
import InputError from '@/Components/InputError.vue';
import MeasureCard from '@/Components/Nr1Shell/MeasureCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    surveyId: { type: Number, required: true },
    measures: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    canEditSuggestion: { type: Boolean, default: true },
    dueOrigins: { type: Object, default: () => ({}) },
    effectivenessResults: { type: Object, default: () => ({}) },
    measuresAi: {
        type: Object,
        default: () => ({ generated_at: null, pending: false, refresh_url: null }),
    },
});

const newMeasureSection = ref(null);

const form = useForm({
    factor_label: '',
    measure_description: '',
    evidence_basis: 'questionnaire_indication',
    responsible_text: '',
    suggested_due_date: '',
});

const submit = () => {
    form.post(route('admin.companies.surveys.measures.store', [props.companyId, props.surveyId]), {
        preserveScroll: true,
        onSuccess: () => form.reset('factor_label', 'measure_description', 'responsible_text', 'suggested_due_date'),
        onError: () => newMeasureSection.value?.open(),
    });
};

const refreshMeasures = () => {
    if (!props.measuresAi?.refresh_url || props.measuresAi.pending) {
        return;
    }
    router.post(props.measuresAi.refresh_url, {}, { preserveScroll: true });
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

        <CompanyShowAccordion
            v-if="canEditSuggestion"
            ref="newMeasureSection"
            title="Nova medida"
            description="Fator, medida, responsável e prazo."
        >
        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <div>
                <div class="flex items-center gap-1">
                    <label class="text-sm text-slate-700" for="parecer-factor">Fator</label>
                    <InfoTooltip text="Fator psicossocial a que a medida se refere. Esse nome sai no Inventário de Riscos Psicossocial e no dossiê." />
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
        </CompanyShowAccordion>

        <p v-if="measures.length === 0" class="text-sm text-slate-600">Nenhuma medida ainda. Elas aparecem quando a pesquisa encerra e a Mia gera o plano, ou quando você registra uma.</p>
        <div v-else class="space-y-4">
            <MeasureCard
                v-for="measure in measures"
                :key="measure.id"
                :measure="measure"
                :users="[]"
                :effectiveness-results="effectivenessResults"
                :due-origins="dueOrigins"
                :statuses="canEditSuggestion ? statuses : null"
                :company-id="companyId"
                :survey-id="surveyId"
            />
            <p v-if="!canEditSuggestion" class="text-xs text-slate-500">Campanha publicada: a sugestão não é mais editada pela Talents.</p>
        </div>
    </div>
</template>
