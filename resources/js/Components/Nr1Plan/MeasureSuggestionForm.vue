<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    companyId: { type: Number, required: true },
    surveyId: { type: Number, required: true },
    measure: { type: Object, required: true },
    dueOrigins: { type: Object, default: () => ({}) },
});

const form = useForm({
    measure_description: props.measure.measure_description ?? '',
    objective: props.measure.plan.objective ?? '',
    expected_result: props.measure.plan.expected_result ?? '',
    indicator: props.measure.plan.indicator ?? '',
    responsible_text: props.measure.plan.suggested.responsible ?? '',
    suggested_due_date: props.measure.plan.suggested.due_date ?? '',
    suggested_due_origin: props.measure.plan.suggested.due_origin ?? '',
    suggested_due_note: props.measure.plan.suggested.due_note ?? '',
    reason: '',
});

const submit = () => {
    form.patch(route('admin.companies.surveys.measures.suggestion', [props.companyId, props.surveyId, props.measure.id]), {
        preserveScroll: true,
        onSuccess: () => form.reset('reason'),
    });
};
</script>

<template>
    <form class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2" @submit.prevent="submit">
        <p class="text-xs text-slate-600 sm:col-span-2">
            Editar a sugestão não confirma nada pela empresa. A confirmação de responsável e prazo é feita pela empresa depois de «Publicado».
        </p>
        <div class="sm:col-span-2">
            <label class="text-sm text-slate-700" :for="`desc-${measure.id}`">Ação proposta</label>
            <textarea :id="`desc-${measure.id}`" v-model="form.measure_description" rows="2" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            <p v-if="form.errors.measure_description" class="mt-1 text-xs text-red-600">{{ form.errors.measure_description }}</p>
        </div>
        <div>
            <label class="text-sm text-slate-700" :for="`obj-${measure.id}`">Objetivo</label>
            <textarea :id="`obj-${measure.id}`" v-model="form.objective" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-700" :for="`exp-${measure.id}`">Resultado esperado</label>
            <textarea :id="`exp-${measure.id}`" v-model="form.expected_result" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-700" :for="`ind-${measure.id}`">Indicador</label>
            <input :id="`ind-${measure.id}`" v-model="form.indicator" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-700" :for="`resp-${measure.id}`">Responsável sugerido (área ou função)</label>
            <input :id="`resp-${measure.id}`" v-model="form.responsible_text" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-700" :for="`due-${measure.id}`">Prazo sugerido</label>
            <input :id="`due-${measure.id}`" v-model="form.suggested_due_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
            <label class="text-sm text-slate-700" :for="`orig-${measure.id}`">Origem do prazo</label>
            <select :id="`orig-${measure.id}`" v-model="form.suggested_due_origin" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                <option value="">Não informada</option>
                <option v-for="(label, value) in dueOrigins" :key="value" :value="value">{{ label }}</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="text-sm text-slate-700" :for="`note-${measure.id}`">Justificativa do prazo sugerido</label>
            <input :id="`note-${measure.id}`" v-model="form.suggested_due_note" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div class="sm:col-span-2">
            <label class="text-sm text-slate-700" :for="`why-${measure.id}`">Justificativa da alteração (obrigatória ao trocar um valor já registrado)</label>
            <input :id="`why-${measure.id}`" v-model="form.reason" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-full bg-slate-800 px-4 py-1.5 text-sm font-medium text-white disabled:opacity-60" :disabled="form.processing">
                Salvar sugestão
            </button>
        </div>
    </form>
</template>
