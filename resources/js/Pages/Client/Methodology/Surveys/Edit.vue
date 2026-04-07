<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ survey: Object });

const form = useForm({
    title: props.survey.title,
    status: props.survey.status,
    starts_at: props.survey.starts_at ? props.survey.starts_at.slice(0, 16) : '',
    ends_at: props.survey.ends_at ? props.survey.ends_at.slice(0, 16) : '',
    collect_email: !!props.survey.collect_email,
});

const submit = () => {
    form.put(route('client.metodologia.pesquisa-satisfacao.update', props.survey.id));
};
</script>

<template>
    <Head title="Editar pesquisa" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Editar pesquisa</h2>
        </template>

        <form class="max-w-xl space-y-6 text-gray-900" @submit.prevent="submit">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div>
                    <InputLabel for="title" value="Título" />
                    <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                </div>
                <div class="mt-4">
                    <InputLabel for="status" value="Status" />
                    <select
                        id="status"
                        v-model="form.status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="active">Ativa</option>
                        <option value="draft">Rascunho</option>
                        <option value="closed">Encerrada</option>
                    </select>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="starts_at" value="Início" />
                        <TextInput id="starts_at" v-model="form.starts_at" type="datetime-local" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="ends_at" value="Fim" />
                        <TextInput id="ends_at" v-model="form.ends_at" type="datetime-local" class="mt-1 block w-full" />
                    </div>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input v-model="form.collect_email" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                    Coletar e-mail
                </label>
            </div>
            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">Salvar</PrimaryButton>
                <Link
                    :href="route('client.metodologia.pesquisa-satisfacao.show', survey.id)"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </ClientLayout>
</template>
