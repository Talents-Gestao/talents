<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import Modal from '@/Components/Modal.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    sales: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sellers: { type: Array, default: () => [] },
    statusOptions: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashInfo = computed(() => page.props.flash?.info ?? null);

const filterState = reactive({
    search: props.filters.search ?? '',
    seller_id: props.filters.seller_id ?? '',
    status: props.filters.status ?? '',
});

const applyFilters = () => {
    router.get(route('admin.financeiro.vendas.index'), filterState, {
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
        aberta: 'bg-amber-100 text-amber-800',
        parcial: 'bg-sky-100 text-sky-800',
        quitada: 'bg-emerald-100 text-emerald-800',
        cancelada: 'bg-slate-100 text-slate-600',
    }[s] ?? 'bg-slate-100 text-slate-600');

const destroyModalOpen = ref(false);
const destroyTarget = ref(null);
const destroying = ref(false);

const destroyImpactItems = computed(() => {
    const sale = destroyTarget.value;
    if (!sale) {
        return [];
    }

    const total = Number(sale.installments_count) || 0;
    const items = [
        {
            key: 'receber',
            label: 'Financeiro · Contas a receber',
            detail:
                total === 1
                    ? '1 parcela desta venda será removida do saldo a receber / fluxo de caixa.'
                    : `${total || 'As'} parcelas desta venda serão removidas do saldo a receber / fluxo de caixa.`,
            href: route('admin.financeiro.contas-a-receber.index'),
        },
    ];

    if (sale.commission && Number(sale.commission.amount_cents) > 0) {
        items.push({
            key: 'comissao',
            label: 'Financeiro · Comissões',
            detail: 'A comissão desta venda também será removida.',
            href: route('admin.financeiro.comissoes.index'),
        });
    }

    if (sale.proposal?.id) {
        items.push({
            key: 'proposta',
            label: `Comercial · Proposta ${sale.proposal.code}`,
            detail: 'A proposta permanece no Comercial, sem venda vinculada (pode ser convertida de novo).',
            href: route('admin.comercial.propostas.edit', sale.proposal.id),
        });
    }

    return items;
});

const openDestroy = (sale) => {
    destroyTarget.value = sale;
    destroyModalOpen.value = true;
};

const closeDestroyModal = () => {
    if (destroying.value) {
        return;
    }
    destroyModalOpen.value = false;
    destroyTarget.value = null;
};

const confirmDestroy = () => {
    const sale = destroyTarget.value;
    if (!sale || destroying.value) {
        return;
    }
    destroying.value = true;
    router.delete(route('admin.financeiro.vendas.destroy', sale.id), {
        preserveScroll: true,
        onFinish: () => {
            destroying.value = false;
            destroyModalOpen.value = false;
            destroyTarget.value = null;
        },
    });
};
</script>

<template>
    <Head title="Financeiro — Vendas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Vendas</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Vendas convertidas de propostas ou criadas manualmente.
                    </p>
                </div>
                <Link :href="route('admin.financeiro.vendas.create')">
                    <PrimaryButton type="button">Nova venda</PrimaryButton>
                </Link>
            </div>
        </template>

        <FinanceModuleNav />

        <div
            v-if="flashSuccess"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ flashSuccess }}
        </div>
        <div
            v-if="flashInfo"
            class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        >
            {{ flashInfo }}
        </div>

        <div class="surface-card p-6">
            <form class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Buscar</label>
                    <input
                        v-model="filterState.search"
                        type="text"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Cliente, código ou CNPJ"
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
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Proposta</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Pendentes</th>
                            <th class="px-4 py-3 text-right font-medium">Vendida em</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 align-middle font-medium">
                                <Link
                                    :href="route('admin.financeiro.vendas.show', sale.id)"
                                    class="text-slate-900 hover:text-talents-700 hover:underline"
                                >
                                    {{ sale.client_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 align-middle text-sm text-slate-600">
                                <Link
                                    v-if="sale.proposal?.id"
                                    :href="route('admin.comercial.propostas.edit', sale.proposal.id)"
                                    class="font-medium text-talents-700 hover:underline"
                                >
                                    Ver proposta
                                </Link>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3 align-middle text-slate-600">{{ sale.seller?.name ?? '—' }}</td>
                            <td class="px-4 py-3 align-middle text-right tabular-nums font-semibold">
                                {{ formatBRL(sale.total_cents) }}
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(sale.status)"
                                >
                                    {{ statusLabel(sale.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 align-middle text-right tabular-nums">
                                {{ sale.pending_installments_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 align-middle text-right text-xs text-slate-500">
                                {{ formatDate(sale.sold_at) }}
                            </td>
                            <td class="px-4 py-3 align-middle text-right">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <Link
                                        :href="route('admin.financeiro.vendas.edit', sale.id)"
                                        class="inline-flex rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                        title="Editar venda"
                                        aria-label="Editar venda"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-700"
                                        title="Excluir venda"
                                        aria-label="Excluir venda"
                                        @click="openDestroy(sale)"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!sales.data?.length">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                                Nenhuma venda encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="destroyModalOpen" max-width="lg" @close="closeDestroyModal">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Excluir venda?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Excluir «{{ destroyTarget?.client_name }}» ({{ destroyTarget?.code }})?
                    Esta ação não pode ser desfeita.
                </p>
                <template v-if="destroyImpactItems.length">
                    <p class="mt-3 text-sm font-medium text-amber-950">
                        Isto afetará o saldo / acompanhamento nestes pontos:
                    </p>
                    <ul class="mt-2 space-y-2">
                        <li
                            v-for="item in destroyImpactItems"
                            :key="item.key"
                            class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-2.5 text-sm text-amber-950"
                        >
                            <p class="font-semibold">{{ item.label }}</p>
                            <p class="mt-0.5 text-amber-900/80">{{ item.detail }}</p>
                            <a
                                v-if="item.href"
                                :href="item.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-flex text-xs font-semibold text-talents-700 underline hover:text-talents-800"
                            >
                                Abrir área
                            </a>
                        </li>
                    </ul>
                </template>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                        :disabled="destroying"
                        @click="closeDestroyModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-60"
                        :disabled="destroying"
                        @click="confirmDestroy"
                    >
                        {{ destroying ? 'Excluindo…' : 'Excluir venda' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
