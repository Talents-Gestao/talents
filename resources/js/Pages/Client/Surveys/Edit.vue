<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
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
    min_group_size: props.survey.min_group_size ?? 5,
});

const submit = () => {
    form.put(route('client.surveys.update', props.survey.id));
};
</script>

<template>
    <Head title="Editar campanha" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.surveys.show', survey.id)"
                back-label="Campanha"
                title="Editar campanha"
            />
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
                <InputLabel for="min" value="Mínimo de respondentes por grupo (anonimato)" />
                <input
                    id="min"
                    v-model.number="form.min_group_size"
                    type="number"
                    min="5"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700"
                />
                <p class="mt-1 text-xs text-gray-500">
                    Setores com menos respondentes do que este número não aparecem isolados nos resultados. O mínimo é 5 e pode ser aumentado.
                </p>
                <p v-if="form.errors.min_group_size" class="mt-1 text-xs text-red-600">{{ form.errors.min_group_size }}</p>
            </div>
            <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
        </form>
    </ClientLayout>
</template>
