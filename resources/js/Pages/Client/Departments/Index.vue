<script setup>
import ListEmptyState from '@/Components/ListEmptyState.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ departments: Object });

const form = useForm({ name: '' });

const submit = () => {
    form.post(route('client.departments.store'), {
        onSuccess: () => form.reset('name'),
    });
};

const importForm = useForm({ file: null });

const importCsv = () => {
    importForm.post(route('client.import.departments'), {
        forceFormData: true,
        onSuccess: () => importForm.reset('file'),
    });
};

const remove = (id) => {
    if (confirm('Excluir este setor?')) {
        router.delete(route('client.departments.destroy', id));
    }
};
</script>

<template>
    <Head title="Setores" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Setores</h2>
        </template>

        <form class="mb-6 flex max-w-xl gap-2" @submit.prevent="submit">
            <input
                v-model="form.name"
                class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm"
                placeholder="Nome do setor"
                required
            />
            <button type="submit" class="rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                Adicionar
            </button>
        </form>

        <div class="mb-6 max-w-xl rounded-lg border border-dashed border-talents-300 bg-talents-50 p-4">
            <p class="text-sm font-medium text-talents-900">Importar CSV (uma coluna com o nome do setor)</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <input type="file" accept=".csv,.txt" class="text-sm" @input="importForm.file = $event.target.files[0]" />
                <button
                    type="button"
                    class="rounded-md border border-talents-400 px-3 py-1 text-sm text-talents-900"
                    :disabled="!importForm.file || importForm.processing"
                    @click="importCsv"
                >
                    Importar
                </button>
            </div>
        </div>

        <ul class="divide-y divide-slate-200 surface-card overflow-hidden">
            <li v-for="d in departments.data" :key="d.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <span>{{ d.name }}</span>
                <button type="button" class="text-red-600 hover:underline" @click="remove(d.id)">Excluir</button>
            </li>
            <ListEmptyState v-if="!departments.data.length" message="Nenhum setor encontrado." />
        </ul>
    </ClientLayout>
</template>
