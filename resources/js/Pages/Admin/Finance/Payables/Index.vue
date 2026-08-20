<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    payables: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const localFilters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

watch(
    () => props.filters,
    (value) => {
        localFilters.q = value?.q ?? '';
        localFilters.status = value?.status ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.financeiro.contas-a-pagar.index'),
        {
            q: localFilters.q || undefined,
            status: localFilters.status || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');

const statusClass = (status) =>
    ({
        pending: 'bg-amber-50 text-amber-800',
        paid: 'bg-emerald-50 text-emerald-800',
        cancelled: 'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600');

const markPaid = (id) => {
    if (!confirm('Marcar esta conta como paga?')) {
        return;
    }
    router.patch(route('admin.financeiro.contas-a-pagar.mark-paid', id), {}, { preserveScroll: true });
};

const remove = (id) => {
    if (!confirm('Remover esta conta a pagar?')) {
        return;
    }
    router.delete(route('admin.financeiro.contas-a-pagar.destroy', id));
};
</script>

<template>
    <Head title="Financeiro — Contas a pagar" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Contas a pagar</h2>
                    <p class="mt-1 text-sm text-slate-600">Controle manual de gastos e obrigações.</p>
                </div>
                <Link :href="route('admin.financeiro.contas-a-pagar.create')">
                    <PrimaryButton type="button">Nova conta</PrimaryButton>
                </Link>
            </div>
        </template>

        <FinanceModuleNav />

        <div
            v-if="flashSuccess"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashError"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            {{ flashError }}
        </div>

        <form
            class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_12rem_auto]"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="localFilters.q"
                type="search"
                placeholder="Buscar título ou fornecedor"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            />
            <select
                v-model="localFilters.status"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            >
                <option value="">Todos os status</option>
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div v-if="!payables.data?.length" class="px-5 py-10 text-center text-sm text-slate-600">
                Nenhuma conta a pagar encontrada.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Título</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Vencimento</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Valor</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Forma</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in payables.data" :key="item.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ item.title }}</p>
                                <p class="text-xs text-slate-500">{{ item.supplier_name || '—' }}</p>
                                <span
                                    v-if="item.recurring_label"
                                    class="mt-1 inline-flex rounded-full bg-talents-50 px-2 py-0.5 text-[11px] font-medium text-talents-800 ring-1 ring-talents-100"
                                >
                                    {{ item.recurring_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ formatDate(item.due_date) }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ formatBRL(item.amount_cents) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ item.payment_method?.name || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(item.status)"
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="item.status === 'pending'"
                                    type="button"
                                    class="font-medium text-emerald-700 hover:underline"
                                    @click="markPaid(item.id)"
                                >
                                    Pagar
                                </button>
                                <Link
                                    :href="route('admin.financeiro.contas-a-pagar.edit', item.id)"
                                    class="ml-3 font-medium text-talents-700 hover:underline"
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
        </div>
    </AdminLayout>
</template>
