<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    defaults: { type: Object, required: true },
});

const form = useForm({
    company_id: props.defaults.company_id ?? '',
    cycle_year: String(props.defaults.cycle_year),
    cycle_quarter: props.defaults.cycle_quarter,
    respondent_name: '',
    respondent_email: '',
    respondent_phone: '',
    expires_at: props.defaults.expires_at,
});

const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500';

const quarters = [
    { value: 1, label: '1º trimestre' },
    { value: 2, label: '2º trimestre' },
    { value: 3, label: '3º trimestre' },
    { value: 4, label: '4º trimestre' },
];

watch(
    () => form.company_id,
    (companyId) => {
        const company = props.companies.find((item) => item.id === Number(companyId));
        form.respondent_email = company?.contact_email ?? '';
    },
    { immediate: true },
);

const submit = () => {
    form.post(route('admin.client-evaluations.store'));
};
</script>

<template>
    <Head title="Nova avaliação" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.client-evaluations.index')"
                back-label="Avaliações"
                title="Nova avaliação"
                subtitle="O cliente recebe um link de uso único, válido até a data escolhida"
            />
        </template>

        <form class="mx-auto max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <InputLabel for="company_id" value="Empresa" />
                <select id="company_id" v-model="form.company_id" :class="fieldClass" required>
                    <option value="">Selecione</option>
                    <option v-for="company in companies" :key="company.id" :value="company.id">
                        {{ company.name }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.company_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="cycle_year" value="Ano" />
                    <TextInput id="cycle_year" v-model="form.cycle_year" type="number" min="2020" max="2100" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.cycle_year" />
                </div>
                <div>
                    <InputLabel for="cycle_quarter" value="Trimestre" />
                    <select id="cycle_quarter" v-model="form.cycle_quarter" :class="fieldClass" required>
                        <option v-for="quarter in quarters" :key="quarter.value" :value="quarter.value">
                            {{ quarter.label }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.cycle_quarter" />
                </div>
            </div>

            <div>
                <InputLabel for="respondent_name" value="Nome do contato" />
                <TextInput id="respondent_name" v-model="form.respondent_name" class="mt-1 block w-full" required maxlength="120" />
                <InputError class="mt-1" :message="form.errors.respondent_name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="respondent_email" value="E-mail" />
                    <TextInput id="respondent_email" v-model="form.respondent_email" type="email" class="mt-1 block w-full" required />
                    <InputError class="mt-1" :message="form.errors.respondent_email" />
                </div>
                <div>
                    <InputLabel for="respondent_phone" value="Celular (WhatsApp)" />
                    <TextInput id="respondent_phone" v-model="form.respondent_phone" type="tel" class="mt-1 block w-full" placeholder="11 98765-4321" />
                    <InputError class="mt-1" :message="form.errors.respondent_phone" />
                </div>
            </div>

            <div>
                <InputLabel for="expires_at" value="Válido até" />
                <TextInput id="expires_at" v-model="form.expires_at" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.expires_at" />
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <PrimaryButton :disabled="form.processing">Gerar link</PrimaryButton>
                <Link :href="route('admin.client-evaluations.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
