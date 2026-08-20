<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sale: { type: Object, required: true },
    paymentMethods: { type: Object, default: () => ({}) },
});

const localTodayDate = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const commissionForm = useForm({
    status: props.sale.commission?.status ?? 'a_pagar',
    paid_at: props.sale.commission?.paid_at
        ? String(props.sale.commission.paid_at).slice(0, 10)
        : localTodayDate(),
    notes: props.sale.commission?.notes ?? '',
});

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const methodLabel = (m) => props.paymentMethods[m] ?? m;

const statusLabel = (s) =>
    ({
        aberta: 'Aberta',
        parcial: 'Parcial',
        quitada: 'Quitada',
        cancelada: 'Cancelada',
        pendente: 'Pendente',
        pago: 'Pago',
        cancelado: 'Cancelado',
        a_pagar: 'A pagar',
        paga: 'Paga',
    }[s] ?? s);

const saleStatusClass = (s) =>
    ({
        aberta: 'bg-amber-100 text-amber-800',
        parcial: 'bg-sky-100 text-sky-800',
        quitada: 'bg-emerald-100 text-emerald-800',
        cancelada: 'bg-slate-100 text-slate-600',
    }[s] ?? 'bg-slate-100 text-slate-600');

const installmentStatusClass = (inst) => {
    if (inst.status === 'pago') return 'bg-emerald-100 text-emerald-800';
    if (inst.status === 'cancelado') return 'bg-slate-100 text-slate-600';
    if (inst.is_overdue) return 'bg-red-100 text-red-800';
    return 'bg-amber-100 text-amber-800';
};

const installmentStatusText = (inst) => {
    if (inst.status === 'pendente' && inst.is_overdue) return 'Vencida';
    return statusLabel(inst.status);
};

const paidTotalCents = computed(() =>
    (props.sale.installments ?? [])
        .filter((i) => i.status === 'pago')
        .reduce((acc, i) => acc + (i.paid_amount_cents ?? i.amount_cents ?? 0), 0),
);

const saleIsQuitada = computed(() => props.sale.status === 'quitada');
const canMarkCommissionPaid = computed(
    () => saleIsQuitada.value || props.sale.commission?.status === 'paga',
);

const submitCommission = () => {
    if (!props.sale.commission) return;
    commissionForm.patch(route('admin.financeiro.comissoes.update', props.sale.commission.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Venda ${sale.code}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro / Vendas</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ sale.code }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ sale.client_name }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.financeiro.vendas.index')"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Voltar
                    </Link>
                    <Link
                        :href="route('admin.financeiro.vendas.edit', sale.id)"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Editar
                    </Link>
                    <Link
                        v-if="sale.proposal"
                        :href="route('admin.comercial.propostas.edit', sale.proposal.id)"
                        class="inline-flex items-center rounded-xl border border-talents-200 bg-talents-50 px-3 py-2 text-sm font-semibold text-talents-800 shadow-sm transition hover:bg-talents-100"
                    >
                        Proposta {{ sale.proposal.code }}
                    </Link>
                </div>
            </div>
        </template>

        <FinanceModuleNav />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="surface-card p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="saleStatusClass(sale.status)">
                            {{ statusLabel(sale.status) }}
                        </span>
                        <p class="mt-3 text-sm text-slate-600">
                            Vendedor: <strong>{{ sale.seller?.name ?? '—' }}</strong>
                        </p>
                        <p class="text-sm text-slate-600">Vendida em: {{ formatDate(sale.sold_at) }}</p>
                        <p class="text-sm text-slate-600">
                            Pagamento: {{ methodLabel(sale.payment_method) }} · {{ sale.installments_count }} parcela(s)
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Total</p>
                        <p class="text-2xl font-bold tabular-nums text-slate-900">{{ formatBRL(sale.total_cents) }}</p>
                        <p class="mt-1 text-sm text-emerald-700">Recebido: {{ formatBRL(paidTotalCents) }}</p>
                    </div>
                </div>
            </div>

            <div v-if="sale.commission" class="surface-card p-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Comissão</h3>
                <p class="mt-2 text-xl font-bold tabular-nums">{{ formatBRL(sale.commission.amount_cents) }}</p>
                <p class="text-sm text-slate-600">
                    {{ sale.commission.percent }}% sobre {{ formatBRL(sale.commission.base_cents) }}
                </p>
                <p class="mt-1 text-sm text-slate-600">Vendedora: {{ sale.commission.seller?.name ?? '—' }}</p>
                <Link
                    :href="route('admin.financeiro.comissoes.index', { search: sale.code })"
                    class="mt-2 inline-block text-xs font-medium text-talents-700 hover:underline"
                >
                    Ver no índice de comissões
                </Link>

                <form class="mt-4 space-y-3" @submit.prevent="submitCommission">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                        <select
                            v-model="commissionForm.status"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="a_pagar">A pagar</option>
                            <option value="paga" :disabled="!canMarkCommissionPaid">Paga</option>
                        </select>
                        <p v-if="!saleIsQuitada" class="mt-1 text-xs text-amber-700">
                            A venda precisa estar quitada antes de marcar a comissão como paga.
                        </p>
                        <p v-if="commissionForm.errors.status" class="mt-1 text-xs text-rose-600">
                            {{ commissionForm.errors.status }}
                        </p>
                    </div>
                    <div v-if="commissionForm.status === 'paga'">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Paga em</label>
                        <input
                            v-model="commissionForm.paid_at"
                            type="date"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                        <textarea
                            v-model="commissionForm.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <button
                        type="submit"
                        class="w-full rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700 disabled:opacity-50"
                        :disabled="commissionForm.processing"
                    >
                        Salvar comissão
                    </button>
                </form>
            </div>
            <div v-else class="surface-card p-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Comissão</h3>
                <p class="mt-2 text-base font-semibold text-slate-800">Sem comissão</p>
                <p class="mt-1 text-sm text-slate-600">
                    Esta venda não gera valor a pagar no Financeiro.
                </p>
            </div>
        </div>

        <div class="mt-6 surface-card overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Parcelas / cobranças</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Para registrar pagamento, use Contas a receber.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">#</th>
                            <th class="px-4 py-3 text-left font-medium">Vencimento</th>
                            <th class="px-4 py-3 text-left font-medium">Método</th>
                            <th class="px-4 py-3 text-right font-medium">Valor</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Pago em</th>
                            <th class="px-4 py-3 text-right font-medium">Comprovante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="inst in sale.installments" :key="inst.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 tabular-nums">{{ inst.number }}</td>
                            <td class="px-4 py-3">{{ formatDate(inst.due_date) }}</td>
                            <td class="px-4 py-3">{{ methodLabel(inst.method) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ formatBRL(inst.amount_cents) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="installmentStatusClass(inst)"
                                >
                                    {{ installmentStatusText(inst) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">{{ formatDate(inst.paid_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    v-if="inst.receipt_path"
                                    :href="route('admin.financeiro.parcelas.comprovante', inst.id)"
                                    class="text-xs font-semibold text-talents-700 hover:underline"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    Comprovante
                                </a>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
