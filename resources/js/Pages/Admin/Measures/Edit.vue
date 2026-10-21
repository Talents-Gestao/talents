<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
    company: { type: Object, required: true },
    survey: { type: Object, required: true },
    measures: { type: Array, default: () => [] },
    statuses: { type: Object, required: true },
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
                <label class="text-sm text-slate-700">
                    Fator
                    <input v-model="form.factor_label" type="text" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.factor_label" />
                </label>
                <label class="text-sm text-slate-700">
                    Base da evidência
                    <select v-model="form.evidence_basis" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="questionnaire_indication">Indício do questionário</option>
                        <option value="validated_factor">Fator validado</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.evidence_basis" />
                </label>
                <label class="text-sm text-slate-700 sm:col-span-2">
                    Medida
                    <textarea v-model="form.measure_description" required rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.measure_description" />
                </label>
                <label class="text-sm text-slate-700">
                    Responsável (texto)
                    <input v-model="form.responsible_text" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.responsible_text" />
                </label>
                <label class="text-sm text-slate-700">
                    Prazo sugerido
                    <input v-model="form.suggested_due_date" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    <InputError class="mt-1" :message="form.errors.suggested_due_date" />
                </label>
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
                        <select v-model="selectedStatus[measure.id]" class="rounded-lg border-slate-300 text-sm">
                            <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <button
                            type="submit"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Atualizar status
                        </button>
                    </form>
                </li>
            </ul>
        </section>
    </AdminLayout>
</template>
