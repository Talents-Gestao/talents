<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ templates: Array });

const form = useForm({
    methodology_form_template_id: props.templates?.[0]?.id ?? '',
    title: '',
    status: 'active',
    starts_at: '',
    ends_at: '',
    collect_email: false,
});

const submit = () => {
    form.post(route('client.metodologia.pesquisa-satisfacao.store'));
};
</script>

<template>
    <Head title="Nova pesquisa de satisfação" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Nova pesquisa</h2>
        </template>

        <div v-if="!templates?.length" class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
            Nenhum template de satisfação foi vinculado à sua empresa. É necessário plano com módulo Metodologia e um template vinculado em
            <strong>Admin → Planos</strong> e <strong>Admin → Empresas → sua empresa</strong>.
            <p class="mt-4">
                <Link :href="route('client.metodologia.index')" class="font-medium text-talents-800 underline">Voltar</Link>
            </p>
        </div>

        <form v-else class="max-w-xl space-y-6 text-gray-900" @submit.prevent="submit">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div>
                    <InputLabel for="template" value="Template" />
                    <select
                        id="template"
                        v-model="form.methodology_form_template_id"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.title }} ({{ t.sections_count }} seções)</option>
                    </select>
                </div>
                <div class="mt-4">
                    <InputLabel for="title" value="Título da pesquisa" />
                    <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required placeholder="Ex.: Pesquisa de satisfação 2026" />
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
                        <InputLabel for="starts_at" value="Início (opcional)" />
                        <TextInput id="starts_at" v-model="form.starts_at" type="datetime-local" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="ends_at" value="Fim (opcional)" />
                        <TextInput id="ends_at" v-model="form.ends_at" type="datetime-local" class="mt-1 block w-full" />
                    </div>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm">
                    <input v-model="form.collect_email" type="checkbox" class="rounded border-gray-300 text-talents-600 focus:ring-talents-500" />
                    Coletar e-mail dos respondentes
                </label>
            </div>
            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">Criar pesquisa</PrimaryButton>
                <Link :href="route('client.metodologia.pesquisa-satisfacao.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancelar
                </Link>
            </div>
        </form>
    </ClientLayout>
</template>
