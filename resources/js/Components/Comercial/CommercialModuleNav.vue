<script setup>
import { Link } from '@inertiajs/vue3';

const items = [
    {
        id: 'dashboard',
        label: 'Resumo',
        href: () => route('admin.comercial.dashboard'),
        isActive: () => route().current('admin.comercial.dashboard'),
    },
    {
        id: 'propostas',
        label: 'Propostas',
        href: () => route('admin.comercial.propostas.index'),
        isActive: () => route().current('admin.comercial.propostas.*'),
    },
    {
        id: 'configuracoes',
        label: 'Valores e contratos',
        href: () => route('admin.comercial.settings.edit'),
        isActive: () => route().current('admin.comercial.settings.*'),
    },
];
</script>

<template>
    <nav
        class="mb-6 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-slate-50/80 p-2"
        aria-label="Navegação do módulo comercial"
    >
        <Link
            v-for="item in items"
            :key="item.id"
            :href="item.href()"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="item.isActive() ? 'bg-talents-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
