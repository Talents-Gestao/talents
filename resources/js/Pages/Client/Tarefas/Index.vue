<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    boards: Array,
});
</script>

<template>
    <Head title="Tarefas" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Tarefas</h2>
        </template>

        <div class="surface-card space-y-3 p-6">
            <p class="text-sm text-slate-600">Quadros partilhados consigo pela Talents.</p>
            <ul class="space-y-2">
                <li v-for="b in boards" :key="b.id">
                    <Link
                        :href="route('client.tarefas.show', b.id)"
                        class="font-medium text-talents-800 underline"
                    >
                        {{ b.name }}
                    </Link>
                </li>
            </ul>
            <p v-if="!boards?.length" class="text-sm text-slate-500">Nenhum quadro disponível.</p>
        </div>
    </ClientLayout>
</template>
