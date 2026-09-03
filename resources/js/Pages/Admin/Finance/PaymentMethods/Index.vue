<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { confirmDialog } from '@/composables/useConfirmDialog';

defineProps({
    methods: { type: Array, default: () => [] },
});

const remove = async (id) => {
    if (await confirmDialog('Remover esta forma de pagamento?')) {
        router.delete(route('admin.financeiro.formas-pagamento.destroy', id));
    }
};
</script>

<template>
    <Head title="Financeiro — Formas de pagamento" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Formas de pagamento</h2>
                    <p class="mt-1 text-sm text-slate-600">Cadastro manual usado em contas a pagar e baixas.</p>
                </div>
                <Link :href="route('admin.financeiro.formas-pagamento.create')">
                    <PrimaryButton type="button">Nova forma</PrimaryButton>
                </Link>
            </div>
        </template>

        <FinanceModuleNav />

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            {{ $page.props.flash.error }}
        </div>

        <div class="surface-card overflow-hidden">
            <div v-if="!methods.length" class="px-5 py-10 text-center text-sm text-slate-600">
                Nenhuma forma de pagamento cadastrada.
            </div>
            <table v-else class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-slate-700">Nome</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-700">Slug</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-700">Ordem</th>
                        <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-slate-700">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in methods" :key="item.id">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ item.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ item.slug }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ item.sort_order }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    item.is_active
                                        ? 'bg-emerald-50 text-emerald-800'
                                        : 'bg-slate-100 text-slate-600'
                                "
                            >
                                {{ item.is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="route('admin.financeiro.formas-pagamento.edit', item.id)"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                Editar
                            </Link>
                            <button
                                type="button"
                                class="ml-3 font-medium text-red-600 hover:underline"
                                @click="remove(item.id)"
                            >
                                Excluir
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
