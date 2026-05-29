<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ survey: Object });

const form = useForm({
    title: props.survey.title,
    starts_at: props.survey.starts_at?.slice(0, 16) ?? '',
    ends_at: props.survey.ends_at?.slice(0, 16) ?? '',
    status: props.survey.status,
    min_responses_for_breakdown: props.survey.min_responses_for_breakdown,
});

const submit = () => {
    form.put(route('client.surveys.update', props.survey.id));
};
</script>

<template>
    <Head title="Editar campanha" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Editar campanha</h2>
        </template>

        <form class="surface-card max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Título" />
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
                    <option value="draft">Rascunho</option>
                    <option value="active">Ativa</option>
                    <option value="closed">Encerrada</option>
                </select>
            </div>
            <div>
                <InputLabel for="min" value="Mínimo de respondentes por setor (anonimato)" />
                <input
                    id="min"
                    type="number"
                    value="1"
                    min="1"
                    max="1"
                    readonly
                    class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                />
                <p class="mt-1 text-xs text-gray-500">Fixo em 1 respondente por setor para exibir gráficos detalhados.</p>
            </div>
            <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
        </form>
    </ClientLayout>
</template>
