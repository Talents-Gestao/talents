<script setup>
import FinanceModuleNav from '@/Components/Financeiro/FinanceModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { PencilSquareIcon } from '@heroicons/vue/24/outline';
import { reactive, ref } from 'vue';

const props = defineProps({
    commissions: { type: Object, required: true },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sellers: { type: Array, default: () => [] },
    statusOptions: { type: Object, default: () => ({}) },
});

const filterState = reactive({
    search: props.filters.search ?? '',
    seller_id: props.filters.seller_id ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('admin.financeiro.comissoes.index'), filterState, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.search = '';
    filterState.seller_id = '';
    filterState.status = '';
    applyFilters();
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const statusLabel = (s) => props.statusOptions[s] ?? s;

const statusClass = (s) =>
    ({
        a_pagar: 'bg-amber-100 text-amber-800',
        paga: 'bg-emerald-100 text-emerald-800',
    }[s] ?? 'bg-slate-100 text-slate-600');

const editModalOpen = ref(false);
const selectedCommission = ref(null);

const editForm = useForm({
    status: 'a_pagar',
    paid_at: new Date().toISOString().slice(0, 10),
    notes: '',
});

const openEditModal = (commission) => {
    selectedCommission.value = commission;
    editForm.status = commission.status;
    editForm.paid_at = commission.paid_at
        ? new Date(commission.paid_at).toISOString().slice(0, 10)
        : new Date().toISOString().slice(0, 10);
    editForm.notes = commission.notes ?? '';
    editModalOpen.value = true;
};

const closeEditModal = () => {
    editModalOpen.value = false;
    selectedCommission.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (!selectedCommission.value) return;
    editForm.patch(route('admin.financeiro.comissoes.update', selectedCommission.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};
</script>

<template>
    <Head title="Financeiro — Comissões" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Comissões</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-600">
                        Visão central das comissões geradas na conversão de propostas em vendas.
                    </p>
                </div>
            </div>
        </template>

        <FinanceModuleNav />

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="surface-card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">A pagar</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-amber-800">{{ formatBRL(summary.pending_cents) }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ summary.pending_count }} comissão(ões) pendente(s)</p>
            </div>
            <div class="surface-card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pagas (filtro atual)</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-800">{{ formatBRL(summary.paid_cents) }}</p>
            </div>
            <div class="surface-card flex items-center p-5">
                <p class="text-sm text-slate-600">
                    Marque como <strong>paga</strong> ao efetuar o repasse ao vendedor. O histórico fica vinculado à venda.
                </p>
            </div>
        </div>

        <div class="surface-card p-6">
            <form class="grid gap-4 sm:grid-cols-4" @submit.prevent="applyFilters">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Buscar</label>
                    <input
                        v-model="filterState.search"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Cliente, venda ou CNPJ"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vendedor</label>
                    <select
                        v-model="filterState.seller_id"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                    <select
                        v-model="filterState.status"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option v-for="(label, key) in statusOptions" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-4 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="clearFilters"
                    >
                        Limpar
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Filtrar
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Venda</th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-right font-medium">Base</th>
                            <th class="px-4 py-3 text-right font-medium">%</th>
                            <th class="px-4 py-3 text-right font-medium">Comissão</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Gerada</th>
                            <th class="px-4 py-3 text-right font-medium">Paga em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="c in commissions.data" :key="c.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <Link
                                    v-if="c.sale"
                                    :href="route('admin.financeiro.vendas.show', c.sale.id)"
                                    class="font-mono text-xs text-talents-700 hover:underline"
                                >
                                    {{ c.sale.code }}
                                </Link>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ c.sale?.client_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ c.seller?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ formatBRL(c.base_cents) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ c.percent }}%</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ formatBRL(c.amount_cents) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(c.status)">
                                    {{ statusLabel(c.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ formatDate(c.created_at) }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ formatDate(c.paid_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                    title="Atualizar comissão"
                                    @click="openEditModal(c)"
                                >
                                    <PencilSquareIcon class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!commissions.data?.length">
                            <td colspan="10" class="px-4 py-10 text-center text-slate-500">Nenhuma comissão encontrada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="commissions.links?.length > 3"
                class="flex flex-wrap items-center justify-end gap-1 border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm"
            >
                <template v-for="link in commissions.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg px-3 py-1 text-slate-700 transition hover:bg-white"
                        :class="link.active ? 'bg-talents-600 text-white hover:bg-talents-600' : ''"
                        v-html="link.label"
                    />
                    <span v-else class="cursor-not-allowed rounded-lg px-3 py-1 text-slate-400" v-html="link.label" />
                </template>
            </div>
        </div>

        <div
            v-if="editModalOpen && selectedCommission"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeEditModal"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Atualizar comissão</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ selectedCommission.sale?.code }} — {{ formatBRL(selectedCommission.amount_cents) }}
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                        <select
                            v-model="editForm.status"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="a_pagar">A pagar</option>
                            <option value="paga">Paga</option>
                        </select>
                    </div>
                    <div v-if="editForm.status === 'paga'">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Paga em</label>
                        <input
                            v-model="editForm.paid_at"
                            type="date"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                        <textarea
                            v-model="editForm.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeEditModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="editForm.processing"
                        >
                            {{ editForm.processing ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
