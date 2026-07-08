<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const isPropostasRoute = computed(() => route().current('admin.comercial.propostas.*'));

const isFilaView = computed(() => {
    if (!isPropostasRoute.value) {
        return false;
    }

    const fromProps = page.props.filters?.ordenacao;
    if (fromProps === 'fila') {
        return true;
    }

    return new URLSearchParams(window.location.search).get('ordenacao') === 'fila';
});

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
        isActive: () => isPropostasRoute.value && !isFilaView.value,
    },
    {
        id: 'fila',
        label: 'Fila',
        href: () => route('admin.comercial.propostas.index', { status: 'abertas', ordenacao: 'fila' }),
        isActive: () => isFilaView.value,
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
