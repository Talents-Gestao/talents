<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue';
import InputError from '@/Components/InputError.vue';
import MeasureHistory from '@/Components/Nr1Plan/MeasureHistory.vue';
import MeasureSuggestionForm from '@/Components/Nr1Plan/MeasureSuggestionForm.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
    company: { type: Object, required: true },
    survey: { type: Object, required: true },
    measures: { type: Array, default: () => [] },
    statuses: { type: Object, required: true },
    canEditSuggestion: { type: Boolean, default: true },
    dueOrigins: { type: Object, default: () => ({}) },
});

const page = usePage();

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
    form.post(route('admin.companies.surveys.measures.store', [props.company.id, props.survey.id]), {
        preserveScroll: true,
        onSuccess: () => form.reset('factor_label', 'measure_description', 'responsible_text', 'suggested_due_date'),
    });
};

const saveStatus = (measure) => {
    router.patch(
        route('admin.companies.surveys.measures.status', [props.company.id, props.survey.id, measure.id]),
        { status: selectedStatus[measure.id] },
        { preserveScroll: true },
    );
};
</script>

<template>
    <AdminLayout>
        <Head :title="`Medidas — ${survey.title}`" />

        <FormPageHeader
            :back-href="route('admin.companies.surveys.action-plan.edit', [company.id, survey.id])"
            back-label="Voltar ao parecer"
            title="Medidas da campanha"
            :subtitle="`${company.name} — ${survey.title}`"
        />

        <p v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ page.props.flash.success }}
        </p>
        <p v-if="page.props.flash?.error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ page.props.flash.error }}
        </p>

        <section class="mb-6 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-slate-900">Nova medida</h2>
            <p class="mt-1 text-sm text-slate-600">A medida nasce como «A aprovar». Marcar «Implantada» exige o seu usuário, no status da lista.</p>

            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <div class="flex items-center gap-1">
                        <label class="text-sm text-slate-700" for="factor_label">Fator</label>
                        <InfoTooltip text="Fator psicossocial a que a medida se refere. Esse nome sai no subsídio AEP/PGR e no dossiê." />
                    </div>
                    <input id="factor_label" v-model="form.factor_label" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.factor_label" />
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <label class="text-sm text-slate-700" for="evidence_basis">Base da evidência</label>
                        <InfoTooltip text="Indício do questionário é só o que a pesquisa apontou. Fator validado é quando o responsável técnico já confirmou aquele fator." />
                    </div>
                    <select id="evidence_basis" v-model="form.evidence_basis" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="questionnaire_indication">Indício do questionário</option>
                        <option value="validated_factor">Fator validado</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.evidence_basis" />
                </div>
                <div class="sm:col-span-2">
                    <div class="flex items-center gap-1">
                        <label class="text-sm text-slate-700" for="measure_description">Medida</label>
                        <InfoTooltip text="Ação proposta para o fator. Entra no plano que acompanha o subsídio. Não preenche gravidade nem probabilidade do inventário." />
                    </div>
                    <textarea id="measure_description" v-model="form.measure_description" required rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.measure_description" />
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <label class="text-sm text-slate-700" for="responsible_text">Responsável (texto)</label>
                        <InfoTooltip text="Nome ou área que vai executar a medida. É um texto livre e não vincula um usuário do sistema." />
                    </div>
                    <input id="responsible_text" v-model="form.responsible_text" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.responsible_text" />
                </div>
                <div>
                    <div class="flex items-center gap-1">
                        <label class="text-sm text-slate-700" for="suggested_due_date">Prazo sugerido</label>
                        <InfoTooltip text="Data sugerida para a medida. Não é o prazo oficial do inventário do PGR." />
                    </div>
                    <input id="suggested_due_date" v-model="form.suggested_due_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.suggested_due_date" />
                </div>
                <div class="sm:col-span-2">
                    <PrimaryButton :disabled="form.processing">Registrar medida</PrimaryButton>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-semibold text-slate-900">Medidas registradas</h2>
            <p v-if="measures.length === 0" class="mt-3 text-sm text-slate-600">Nenhuma medida registrada.</p>
            <ul v-else class="mt-4 divide-y divide-slate-100">
                <li v-for="measure in measures" :key="measure.id" class="flex flex-wrap items-start justify-between gap-3 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-900">{{ measure.factor_label }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ measure.measure_description }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ measure.status_label }}</p>
                    </div>
                    <form class="flex items-center gap-2" @submit.prevent="saveStatus(measure)">
                        <InfoTooltip text="A aprovar ainda depende de decisão. Implantada só pode ser marcada por quem está logado e grava essa pessoa no registro." />
                        <select v-model="selectedStatus[measure.id]" class="rounded-lg border-slate-300 text-sm" :aria-label="`Status de ${measure.factor_label}`">
                            <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <button
                            type="submit"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Atualizar status
                        </button>
                    </form>

                    <div class="w-full text-sm">
                        <p class="text-xs text-slate-600">
                            Plano: <span class="font-medium">{{ measure.plan.status_label }}</span>
                            <template v-if="measure.plan.suggested.responsible"> · Responsável sugerido: {{ measure.plan.suggested.responsible }}</template>
                            <template v-if="measure.plan.suggested.due_date"> · Prazo sugerido: {{ measure.plan.suggested.due_date }}</template>
                        </p>
                        <p v-if="measure.plan.confirmed" class="mt-1 text-xs text-emerald-800">
                            Confirmado pela empresa em {{ measure.plan.confirmed.approved_at }}: {{ measure.plan.confirmed.responsible }}, prazo {{ measure.plan.confirmed.due_date }}.
                        </p>
                        <p v-else class="mt-1 text-xs text-slate-500">Responsável e prazo ainda não confirmados pela empresa.</p>

                        <details v-if="canEditSuggestion" class="mt-2">
                            <summary class="cursor-pointer text-xs font-medium text-slate-700">Editar sugestão</summary>
                            <MeasureSuggestionForm :company-id="company.id" :survey-id="survey.id" :measure="measure" :due-origins="dueOrigins" />
                        </details>
                        <p v-else class="mt-2 text-xs text-slate-500">Campanha publicada: a sugestão não é mais editada pela Talents.</p>

                        <MeasureHistory :history="measure.plan.history" />
                    </div>
                </li>
            </ul>
        </section>
    </AdminLayout>
</template>
