<script setup>
import { Link } from '@inertiajs/vue3';

const shortcuts = [
    {
        tab: 'faixas',
        title: 'Faixas por funcionários',
        description: 'Profiler, pesquisas, direcionamento e NR-1 por quantidade de colaboradores.',
    },
    {
        tab: 'fixos',
        title: 'Valores fixos',
        description: 'Devolutivas, palestras, implantação NR-1 e demais valores únicos.',
    },
    {
        tab: 'produtos',
        title: 'Produtos do catálogo',
        description: 'Cadastre novos produtos que aparecem nas propostas e contratos.',
    },
    {
        tab: 'contratos',
        title: 'Modelos de contrato',
        description: 'Templates DOCX e variáveis usadas na geração do contrato.',
    },
];
</script>

<template>
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
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
