<script setup>
import { Link } from '@inertiajs/vue3';

const items = [
    {
        id: 'dashboard',
        label: 'Resumo',
        href: () => route('admin.financeiro.dashboard'),
        isActive: () => route().current('admin.financeiro.dashboard'),
    },
    {
        id: 'vendas',
        label: 'Vendas',
        href: () => route('admin.financeiro.vendas.index'),
        isActive: () => route().current('admin.financeiro.vendas.*'),
    },
    {
        id: 'comissoes',
        label: 'Comissões',
        href: () => route('admin.financeiro.comissoes.index'),
        isActive: () => route().current('admin.financeiro.comissoes.*'),
    },
    {
        id: 'contas-bancarias',
        label: 'Contas bancárias',
        href: () => route('admin.financeiro.contas-bancarias.index'),
        isActive: () => route().current('admin.financeiro.contas-bancarias.*'),
    },
    {
        id: 'contas-a-pagar',
        label: 'Contas a pagar',
        href: () => route('admin.financeiro.contas-a-pagar.index'),
        isActive: () => route().current('admin.financeiro.contas-a-pagar.*'),
    },
    {
        id: 'contas-a-receber',
        label: 'Contas a receber',
        href: () => route('admin.financeiro.contas-a-receber.index'),
        isActive: () => route().current('admin.financeiro.contas-a-receber.*'),
    },
    {
        id: 'formas-pagamento',
        label: 'Formas de pagamento',
        href: () => route('admin.financeiro.formas-pagamento.index'),
        isActive: () => route().current('admin.financeiro.formas-pagamento.*'),
    },
];
</script>

<template>
    <nav
        class="mb-6 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-slate-50/80 p-2"
        aria-label="Navegação do módulo financeiro"
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
