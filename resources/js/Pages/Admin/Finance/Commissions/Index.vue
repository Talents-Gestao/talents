<script setup>
import CommissionPayButton from '@/Components/Finance/CommissionPayButton.vue';
import CommissionPaymentModal from '@/Components/Finance/CommissionPaymentModal.vue';
import CommissionPaymentsHistory from '@/Components/Finance/CommissionPaymentsHistory.vue';
import CommissionRefundModal from '@/Components/Finance/CommissionRefundModal.vue';
import CommissionRefundsPanel from '@/Components/Finance/CommissionRefundsPanel.vue';
import CommissionStatusBadge from '@/Components/Finance/CommissionStatusBadge.vue';
import {
    commissionSummaryRows,
    formatDueDate,
    paymentBlockedReason,
    rowNotes,
} from '@/Components/Finance/commissionReceipt.js';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronRightIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { reactive, ref } from 'vue';

const props = defineProps({
    commissions: { type: Object, required: true },
    summary: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sellers: { type: Array, default: () => [] },
    statusOptions: { type: Object, default: () => ({}) },
    bankAccounts: { type: Array, default: () => [] },
    can: {
        type: Object,
        default: () => ({
            register_payment: false,
            pay_advance: false,
            delete_payment: false,
            charge_refund: false,
            register_refund: false,
            assume_loss: false,
        }),
    },
});

const NOTE_TONES = {
    amber: 'text-amber-800',
    slate: 'text-slate-600',
    rose: 'font-medium text-rose-700',
};

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

const expanded = ref(new Set());
const toggleHistory = (id) => {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
};

// Guarda só o id: depois de registrar/excluir, a linha é recarregada do servidor.
const paymentTargetId = ref(null);
const paymentTarget = () => props.commissions.data?.find((c) => c.id === paymentTargetId.value) ?? null;

// Devolução: modo + ids (a linha é recarregada do servidor depois da ação).
const refundAction = ref({ mode: null, commissionId: null, refundId: null });
const refundCommission = () => props.commissions.data?.find((c) => c.id === refundAction.value.commissionId) ?? null;
const refundTarget = () => refundCommission()?.refunds?.find((r) => r.id === refundAction.value.refundId) ?? null;
const openRefund = (mode, commission, refund = null) => {
    refundAction.value = { mode, commissionId: commission.id, refundId: refund?.id ?? null };
};
const closeRefund = () => {
    refundAction.value = { mode: null, commissionId: null, refundId: null };
};

const notesModalOpen = ref(false);
const notesTarget = ref(null);
const notesForm = useForm({ notes: '' });

const openNotes = (commission) => {
    notesTarget.value = commission;
    notesForm.notes = commission.notes ?? '';
    notesForm.clearErrors();
    notesModalOpen.value = true;
};

const closeNotes = () => {
    notesModalOpen.value = false;
    notesTarget.value = null;
};

const submitNotes = () => {
    if (!notesTarget.value) return;
    notesForm.patch(route('admin.financeiro.comissoes.update', notesTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => closeNotes(),
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
                        O liberável é o percentual sobre o que a venda já recebeu, menos o que já foi pago. Pagar antes
                        do recebimento (até o saldo) exige permissão; se o cliente não pagar, a colaboradora devolve.
                    </p>
                </div>
            </div>
        </template>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="surface-card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Saldo da comissão</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-amber-800">{{ formatBRL(summary.pending_cents) }}</p>
                <p class="mt-1 text-sm text-slate-600" data-summary-awaiting>
                    {{ formatBRL(summary.awaiting_receipt_cents ?? 0) }} aguardando recebimento
                </p>
                <p class="text-xs text-slate-500">{{ summary.pending_count }} comissão(ões) com saldo</p>
            </div>
            <div class="surface-card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Liberável agora</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-talents-700">{{ formatBRL(summary.releasable_cents) }}</p>
                <p class="mt-1 text-sm text-slate-600">Proporcional ao que já foi recebido</p>
            </div>
            <div class="surface-card p-5" data-summary-advance>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Adiantado em aberto</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-amber-700">{{ formatBRL(summary.advance_cents ?? 0) }}</p>
                <p class="mt-1 text-sm text-slate-600">
                    Pago antes do cliente pagar · {{ summary.advance_count ?? 0 }} comissão(ões)
                </p>
                <Link
                    v-if="summary.refund_pending_count"
                    :href="route('admin.financeiro.comissoes.index', { status: 'devolucoes' })"
                    class="mt-1 inline-block text-sm font-medium text-rose-700 hover:underline"
                >
                    Devoluções pendentes: {{ formatBRL(summary.refund_pending_cents) }} ({{ summary.refund_pending_count }})
                </Link>
            </div>
            <div class="surface-card p-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pagas (filtro atual)</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-emerald-800">{{ formatBRL(summary.paid_cents) }}</p>
                <p v-if="summary.loss_assumed_count" class="mt-1 text-sm font-medium text-rose-700">
                    Perda assumida pela empresa: {{ formatBRL(summary.loss_assumed_cents) }}
                    ({{ summary.loss_assumed_count }} comissão(ões))
                </p>
            </div>
        </div>

        <div class="surface-card p-6">
            <form class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
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
                            <th class="w-8 px-2 py-3"><span class="sr-only">Detalhes</span></th>
                            <th class="px-4 py-3 text-left font-medium">Cliente</th>
                            <th class="px-4 py-3 text-left font-medium">Vendedor</th>
                            <th class="px-4 py-3 text-right font-medium">%</th>
                            <th class="px-4 py-3 text-right font-medium">Total</th>
                            <th class="px-4 py-3 text-right font-medium">Pago</th>
                            <th class="px-4 py-3 text-right font-medium">Liberável</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template v-for="c in commissions.data" :key="c.id">
                            <tr class="hover:bg-slate-50">
                                <td class="px-2 py-3">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                        :aria-expanded="expanded.has(c.id)"
                                        :aria-controls="`comissao-${c.id}-detalhes`"
                                        :aria-label="expanded.has(c.id) ? 'Ocultar detalhes da comissão' : 'Ver detalhes da comissão'"
                                        @click="toggleHistory(c.id)"
                                    >
                                        <ChevronDownIcon v-if="expanded.has(c.id)" class="h-4 w-4" />
                                        <ChevronRightIcon v-else class="h-4 w-4" />
                                    </button>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    <Link
                                        v-if="c.sale"
                                        :href="route('admin.financeiro.vendas.show', c.sale.id)"
                                        class="text-talents-700 hover:underline"
                                    >
                                        {{ c.sale.client_name || 'Ver venda' }}
                                    </Link>
                                    <span v-else>—</span>
                                    <p v-if="c.sale" class="text-xs font-normal text-slate-500">
                                        {{ c.sale.code }} · recebido {{ formatBRL(c.sale.received_cents) }} de {{ formatBRL(c.sale.total_cents) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ c.seller?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ c.percent }}%</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ formatBRL(c.amount_cents) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                                    {{ formatBRL(c.paid_cents) }}
                                    <p v-if="c.loss_assumed" class="text-xs font-medium text-rose-700">
                                        Perda assumida pela empresa: {{ formatBRL(c.loss_assumed_cents) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-talents-700">
                                    {{ formatBRL(c.releasable_cents) }}
                                </td>
                                <td class="px-4 py-3">
                                    <CommissionStatusBadge :status="c.status" :label="c.status_label" />
                                    <p
                                        v-for="note in rowNotes(c)"
                                        :key="note.key"
                                        class="mt-1 text-xs"
                                        :class="NOTE_TONES[note.tone]"
                                        :data-row-note="note.key"
                                    >
                                        {{ note.text }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <CommissionPayButton
                                            v-if="can.register_payment"
                                            :commission-id="c.id"
                                            :blocked-reason="paymentBlockedReason(c, can.pay_advance)"
                                            @pay="paymentTargetId = c.id"
                                        />
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                                            title="Observações"
                                            aria-label="Editar observações"
                                            @click="openNotes(c)"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expanded.has(c.id)" :id="`comissao-${c.id}-detalhes`" class="bg-slate-50/60">
                                <td />
                                <td colspan="8" class="px-4 py-3">
                                    <div class="grid gap-6 lg:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Resumo da comissão</p>
                                            <dl class="grid max-w-md grid-cols-2 gap-x-4 gap-y-1 text-sm" data-commission-summary>
                                                <template v-for="row in commissionSummaryRows(c)" :key="row.key">
                                                    <dt class="text-slate-600">{{ row.label }}</dt>
                                                    <dd class="text-right font-medium tabular-nums text-slate-900">{{ row.value }}</dd>
                                                </template>
                                            </dl>
                                        </div>
                                        <div v-if="c.sale">
                                            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500">Parcelas pendentes da venda</p>
                                            <ul
                                                v-if="c.sale.pending_installments?.length"
                                                class="max-w-md divide-y divide-slate-100 text-sm"
                                                data-pending-installments
                                            >
                                                <li
                                                    v-for="inst in c.sale.pending_installments"
                                                    :key="inst.id"
                                                    class="flex items-center justify-between gap-3 py-1"
                                                    :class="inst.is_overdue ? 'font-medium text-rose-700' : 'text-slate-700'"
                                                >
                                                    <span>
                                                        Parcela {{ inst.number }} · vence em {{ formatDueDate(inst.due_date) }}
                                                        <span
                                                            v-if="inst.is_overdue"
                                                            class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700"
                                                        >Vencida</span>
                                                    </span>
                                                    <span class="tabular-nums">{{ formatBRL(inst.amount_cents) }}</span>
                                                </li>
                                            </ul>
                                            <p v-else class="text-sm text-slate-500">Nenhuma parcela pendente.</p>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <CommissionRefundsPanel
                                            :commission="c"
                                            :can="can"
                                            @charge="(commission) => openRefund('charge', commission)"
                                            @return="(commission, refund) => openRefund('return', commission, refund)"
                                            @loss="(commission, refund) => openRefund('loss', commission, refund)"
                                        />
                                    </div>
                                    <p class="mb-1 mt-4 text-xs font-medium uppercase tracking-wide text-slate-500">Pagamentos</p>
                                    <CommissionPaymentsHistory :commission="c" />
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!commissions.data?.length">
                            <td colspan="9" class="px-4 py-10 text-center text-slate-500">Nenhuma comissão encontrada.</td>
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

        <CommissionPaymentModal
            :show="paymentTargetId !== null"
            :commission="paymentTarget()"
            :bank-accounts="bankAccounts"
            :can-advance="can.pay_advance"
            @close="paymentTargetId = null"
        />

        <CommissionRefundModal
            :mode="refundAction.mode"
            :commission="refundCommission()"
            :refund="refundTarget()"
            :bank-accounts="bankAccounts"
            @close="closeRefund"
        />

        <FullScreenOverlay :show="notesModalOpen && !!notesTarget" @close="closeNotes">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Observações da comissão</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ notesTarget?.sale?.client_name ?? 'Comissão' }} — {{ notesTarget?.seller?.name ?? '—' }}
                </p>
                <form class="mt-4 space-y-4" @submit.prevent="submitNotes">
                    <textarea
                        v-model="notesForm.notes"
                        rows="3"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="notesForm.errors.notes" class="text-xs text-rose-600">{{ notesForm.errors.notes }}</p>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeNotes"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="notesForm.processing"
                        >
                            {{ notesForm.processing ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>
    </AdminLayout>
</template>
