<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ templates: Array });

const form = useForm({
    survey_template_id: null,
    title: '',
    starts_at: '',
    ends_at: '',
    status: 'active',
    min_responses_for_breakdown: 5,
});

const submit = () => {
    form.post(route('client.surveys.store'));
};
</script>

<template>
    <Head title="Nova campanha" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Nova campanha</h2>
        </template>

        <form class="surface-card max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="survey_template_id" value="Mapeamento" />
                <select
                    id="survey_template_id"
                    v-model="form.survey_template_id"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    required
                >
                    <option :value="null" disabled>Selecione</option>
                    <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.title }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="title" value="Título da campanha" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
            </div>
            <div>
                <InputLabel for="starts_at" value="Início" />
                <TextInput id="starts_at" v-model="form.starts_at" type="datetime-local" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="ends_at" value="Fim" />
                <TextInput id="ends_at" v-model="form.ends_at" type="datetime-local" class="mt-1 block w-full" />
            </div>
            <div>
                <InputLabel for="status" value="Status" />
                <select id="status" v-model="form.status" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="active">Ativa</option>
                    <option value="draft">Rascunho</option>
                    <option value="closed">Encerrada</option>
                </select>
            </div>
            <div>
                <InputLabel for="min" value="Mínimo de respondentes por corte (setor)" />
                <TextInput id="min" v-model="form.min_responses_for_breakdown" type="number" min="1" class="mt-1 block w-full" />
            </div>
            <PrimaryButton :disabled="form.processing">Criar</PrimaryButton>
        </form>
    </ClientLayout>
</template>
