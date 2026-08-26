<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';

const { canAdmin } = useAdminPermissions();

const isPropostasRoute = computed(() => route().current('admin.comercial.propostas.*'));

const items = computed(() =>
    [
        {
            id: 'dashboard',
            label: 'Resumo',
            module: 'comercial_resumo',
            href: () => route('admin.comercial.dashboard'),
            isActive: () => route().current('admin.comercial.dashboard'),
        },
        {
            id: 'propostas',
            label: 'Propostas',
            module: 'comercial_propostas',
            href: () => route('admin.comercial.propostas.index'),
            isActive: () => isPropostasRoute.value,
        },
        {
            id: 'configuracoes',
            label: 'Valores e contratos',
            module: 'comercial_valores_contratos',
            href: () => route('admin.comercial.settings.edit'),
            isActive: () => route().current('admin.comercial.settings.*'),
        },
    ].filter((item) => canAdmin(item.module)),
);
</script>

<template>
    <nav
        v-if="items.length"
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
