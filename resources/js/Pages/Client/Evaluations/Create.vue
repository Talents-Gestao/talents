<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    company: { type: Object, required: true },
    defaults: { type: Object, required: true },
});

const form = useForm({
    cycle_year: String(props.defaults.cycle_year),
    cycle_quarter: props.defaults.cycle_quarter,
    respondent_name: '',
    respondent_email: props.defaults.respondent_email ?? '',
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

const submit = () => {
    form.post(route('client.avaliacoes.store'));
};
</script>

<template>
    <Head title="Nova avaliação" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.avaliacoes.index')"
                back-label="Avaliações"
                title="Nova avaliação"
                :subtitle="`O link fica vinculado a ${company.name}`"
            />
        </template>

        <form class="mx-auto max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <InputLabel value="Empresa" />
                <p class="mt-1 text-sm font-medium text-slate-900">{{ company.name }}</p>
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
                <Link :href="route('client.avaliacoes.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </ClientLayout>
</template>
