<script setup>
import ListEmptyState from '@/Components/ListEmptyState.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ positions: Object });

const form = useForm({ name: '' });

const submit = () => {
    form.post(route('client.positions.store'), {
        onSuccess: () => form.reset('name'),
    });
};

const remove = (id) => {
    if (confirm('Excluir este cargo?')) {
        router.delete(route('client.positions.destroy', id));
    }
};
</script>

<template>
    <Head title="Cargos" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Cargos</h2>
        </template>

        <form class="mb-6 flex max-w-xl gap-2" @submit.prevent="submit">
            <input
                v-model="form.name"
                class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm"
                placeholder="Nome do cargo"
                required
            />
            <button type="submit" class="rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                Adicionar
            </button>
        </form>

        <ul class="divide-y divide-slate-200 surface-card overflow-hidden">
            <li v-for="p in positions.data" :key="p.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <span>{{ p.name }}</span>
                <button type="button" class="text-red-600 hover:underline" @click="remove(p.id)">Excluir</button>
            </li>
            <ListEmptyState v-if="!positions.data.length" message="Nenhum cargo encontrado." />
        </ul>
    </ClientLayout>
</template>
