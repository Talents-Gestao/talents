<script setup>
import { Link } from '@inertiajs/vue3';

const shortcuts = [
    {
        tab: 'produtos',
        title: 'Produtos do catálogo',
        description: 'Cadastre e configure preços dos produtos usados nas propostas e contratos.',
    },
    {
        tab: 'contratos',
        title: 'Modelos de contrato',
        description: 'Templates DOCX e variáveis usadas na geração do contrato.',
    },
];
</script>

<template>
    <div class="mb-6 grid gap-3 sm:grid-cols-2">
        <Link
            v-for="item in shortcuts"
            :key="item.tab"
            :href="`${route('admin.comercial.settings.edit')}?tab=${item.tab}`"
            class="surface-card block rounded-xl p-4 transition hover:border-talents-200 hover:shadow-md"
        >
            <h3 class="text-sm font-semibold text-slate-900">{{ item.title }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ item.description }}</p>
        </Link>
    </div>
</template>
