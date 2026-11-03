<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import SaleInstallmentEditModal from '@/Components/Finance/SaleInstallmentEditModal.vue';
import PaymentDateFields from '@/Components/Finance/PaymentDateFields.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { BanknotesIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { formatMoneyModel } from '@/utils/moneyMask';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { useListNavigation } from '@/composables/useListNavigation';
import {
    GROUP_BY_SALE,
    buildLedgerRows,
    defaultSortForStatus,
    readGroupPreference,
    saoPauloIsoDay,
    todayIsoInSaoPaulo,
    writeGroupPreference,
} from '@/utils/financeLedger';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sortOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    originOptions: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
    installmentMethodOptions: { type: Array, default: () => [] },
    installmentStatusOptions: { type: Array, default: () => [] },
    today: { type: String, default: '' },
});

const maxPaidDate = computed(() => props.today || todayIsoInSaoPaulo());
/** Visita da própria lista em andamento: controles desabilitados e tabela esmaecida. */
const { navigating } = useListNavigation();
const isGrouped = computed(() => props.filters.group === GROUP_BY_SALE);
const ledgerRows = computed(() => buildLedgerRows(props.items.data ?? [], isGrouped.value));

/** Rótulo da parcela na lista corrida: «Parcela 2/6 · VENDA-2026-0012». */
const installmentContext = (item) => {
    if (item?.source !== 'sale') {
        return '';
    }
    const count = Number(item.installments_count || 0);
    const parts = [count > 0 ? `${item.installment_number}/${count}` : null, item.sale_code].filter(Boolean);
    return parts.join(' · ');
};

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const localFilters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    origin: props.filters.origin ?? '',
    sort: props.filters.sort ?? defaultSortForStatus(props.filters.status),
    group: props.filters.group === GROUP_BY_SALE,
});

watch(
    () => props.filters,
    (value) => {
        localFilters.q = value?.q ?? '';
        localFilters.status = value?.status ?? '';
        localFilters.origin = value?.origin ?? '';
        localFilters.sort = value?.sort ?? defaultSortForStatus(value?.status);
        localFilters.group = value?.group === GROUP_BY_SALE;
    },
    { deep: true },
);

const applyFilters = () => {
    if (navigating.value) {
        return;
    }
    router.get(
        route('admin.financeiro.contas-a-receber.index'),
        {
            q: localFilters.q || undefined,
            status: localFilters.status || undefined,
            origin: localFilters.origin || undefined,
            sort: localFilters.sort || defaultSortForStatus(localFilters.status),
            group: localFilters.group ? GROUP_BY_SALE : undefined,
        },
        { preserveState: true, replace: true },
    );
};

const toggleGroupBySale = () => {
    if (navigating.value) {
        return;
    }
    localFilters.group = !localFilters.group;
    writeGroupPreference(localFilters.group);
    applyFilters();
};

// Reaplica a preferência salva quando a URL não traz a escolha (ex.: entrada pelo menu).
onMounted(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.has('group') || readGroupPreference() !== true || localFilters.group) {
        return;
    }
    localFilters.group = true;
    applyFilters();
});

const statusTabOptions = [
    { value: 'pending', label: 'Pendentes' },
    { value: 'paid', label: 'Recebidas' },
    { value: '', label: 'Todas' },
];

const setStatusTab = (status) => {
    const next = status ?? '';
    if (navigating.value || localFilters.status === next) {
        return;
    }
    localFilters.status = next;
    // Cada aba abre na sua ordem padrão; o usuário pode trocar depois.
    localFilters.sort = defaultSortForStatus(next);
    applyFilters();
};

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    const day = String(iso).slice(0, 10);
    return new Date(`${day}T12:00:00`).toLocaleDateString('pt-BR');
};

const centsToReais = (cents) => (Number(cents || 0) / 100).toFixed(2);

const localTodayDate = () => maxPaidDate.value;

const statusClass = (status) =>
    ({
        pending: 'bg-amber-50 text-amber-800',
        paid: 'bg-emerald-50 text-emerald-800',
        cancelled: 'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600');

const iconBtnClass =
    'rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900';

const paymentModalOpen = ref(false);
const paymentItem = ref(null);
const paidAmountReais = ref('');
const manualReceiveOpen = ref(false);
const manualReceiveItem = ref(null);

const paymentForm = useForm({
    status: 'pago',
    paid_at: localTodayDate(),
    paid_amount_cents: 0,
    bank_account_id: '',
    notes: '',
    receipt: null,
});

const manualReceiveForm = useForm({
    paid_at: '',
    bank_account_id: '',
    payment_method_id: '',
});

const openReceive = (item) => {
    if (item.source === 'sale') {
        paymentItem.value = item;
        paymentForm.reset();
        paymentForm.clearErrors();
        paymentForm.status = 'pago';
        paymentForm.paid_at = localTodayDate();
        paymentForm.paid_amount_cents = item.amount_cents;
        paidAmountReais.value = formatMoneyModel(Number(item.amount_cents || 0) / 100);
        paymentForm.bank_account_id = item.bank_account_id ?? '';
        paymentForm.notes = item.notes ?? '';
        paymentForm.receipt = null;
        paymentModalOpen.value = true;
        return;
    }

    manualReceiveItem.value = item;
    manualReceiveForm.clearErrors();
    manualReceiveForm.paid_at = localTodayDate();
    manualReceiveForm.bank_account_id = item.bank_account_id ?? '';
    manualReceiveForm.payment_method_id = item.payment_method_id ?? '';
    manualReceiveOpen.value = true;
};

const closePaymentModal = () => {
    paymentModalOpen.value = false;
    paymentItem.value = null;
    paymentForm.reset();
    paymentForm.clearErrors();
};

const closeManualReceive = () => {
    manualReceiveOpen.value = false;
    manualReceiveItem.value = null;
    manualReceiveForm.reset();
    manualReceiveForm.clearErrors();
};

const submitManualReceive = () => {
    if (!manualReceiveItem.value?.receivable_id) {
        return;
    }
    manualReceiveForm.patch(
        route('admin.financeiro.contas-a-receber.mark-paid', manualReceiveItem.value.receivable_id),
        {
            preserveScroll: true,
            onSuccess: () => closeManualReceive(),
        },
    );
};

const onReceiptChange = (event) => {
    paymentForm.receipt = event.target.files?.[0] ?? null;
};

const submitPayment = () => {
    if (!paymentItem.value?.installment_id) {
        return;
    }
    if (paymentForm.status === 'pago') {
        paymentForm.paid_amount_cents = Math.round(Number(paidAmountReais.value || 0) * 100);
    }
    paymentForm
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(route('admin.financeiro.parcelas.pagamento', paymentItem.value.installment_id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => closePaymentModal(),
        });
};

const remove = async (receivableId) => {
    if (!(await confirmDialog('Remover esta conta a receber?'))) {
        return;
    }
    router.delete(route('admin.financeiro.contas-a-receber.destroy', receivableId));
};

const editModalOpen = ref(false);
const selectedItem = ref(null);
const saleEditModalOpen = ref(false);
const saleEditInstallment = ref(null);
const saleEditSubtitle = ref('');

const editForm = useForm({
    title: '',
    payer_name: '',
    amount_reais: '',
    due_date: '',
    status: 'pending',
    payment_method_id: '',
    bank_account_id: '',
    method: 'pix',
    notes: '',
    paid_at: '',
    payment_date_reason: '',
});

/** Já estava recebida ao abrir: a data editada é uma correção (auditada, com motivo). */
const editWasPaid = computed(() => selectedItem.value?.status === 'paid');
/** Conta exigida só na baixa nova; já recebida sem conta (legado) mantém a conta como está. */
const editBankRequired = computed(() => editForm.status === 'paid' && !editWasPaid.value);
const editBankPlaceholder = computed(() => {
    if (editForm.status !== 'paid') {
        return 'Opcional (pendente)';
    }
    return editWasPaid.value && !selectedItem.value?.bank_account_id ? 'Sem conta registrada (mantém)' : 'Selecione a conta';
});

const openEditModal = (item) => {
    if (item.source === 'sale') {
        saleEditInstallment.value = {
            id: item.installment_id,
            number: item.installment_number ?? null,
            amount_cents: item.amount_cents,
            due_date: item.due_date,
            method: item.method ?? 'pix',
            status: item.installment_status ?? 'pendente',
            bank_account_id: item.bank_account_id ?? '',
            notes: item.notes ?? '',
            paid_at: item.paid_at ?? null,
        };
        saleEditSubtitle.value = item.counterparty ?? '';
        saleEditModalOpen.value = true;
        return;
    }

    selectedItem.value = item;
    editForm.clearErrors();
    editForm.title = item.title ?? '';
    editForm.payer_name = item.counterparty ?? '';
    editForm.amount_reais = centsToReais(item.amount_cents);
    editForm.due_date = item.due_date ?? '';
    editForm.notes = item.notes ?? '';
    editForm.status = item.status ?? 'pending';
    editForm.payment_method_id = item.payment_method_id ?? '';
    editForm.bank_account_id = item.bank_account_id ?? '';
    // Pago sem data (legado): campo vazio e obrigatório, para não gravar «hoje» sem querer.
    editForm.paid_at = item.status === 'paid' ? saoPauloIsoDay(item.paid_at) : localTodayDate();
    editForm.payment_date_reason = '';
    editForm.method = 'pix';
    editModalOpen.value = true;
};

const closeEditModal = () => {
    editModalOpen.value = false;
    selectedItem.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const closeSaleEditModal = () => {
    saleEditModalOpen.value = false;
    saleEditInstallment.value = null;
    saleEditSubtitle.value = '';
};

const submitEdit = () => {
    if (!selectedItem.value) {
        return;
    }

    editForm
        .transform((data) => ({
            ...data,
            paid_at: data.status === 'paid' ? data.paid_at : null,
            payment_date_reason: data.status === 'paid' && editWasPaid.value ? data.payment_date_reason : null,
        }))
        .put(route('admin.financeiro.contas-a-receber.update', selectedItem.value.receivable_id), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};
</script>

<template>
    <Head title="Financeiro — Contas a receber" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Contas a receber</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Parcelas de vendas e recebimentos manuais documentados.
                    </p>
                </div>
                <Link :href="route('admin.financeiro.contas-a-receber.create')">
                    <PrimaryButton type="button">Novo recebimento</PrimaryButton>
                </Link>
            </div>
        </template>

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

        <div
            class="mb-4 flex flex-wrap items-center gap-2"
            role="tablist"
            aria-label="Filtrar por status"
        >
            <button
                v-for="tab in statusTabOptions"
                :key="tab.value === '' ? 'all' : tab.value"
                type="button"
                role="tab"
                class="rounded-xl px-3 py-1.5 text-sm font-semibold transition disabled:cursor-wait disabled:opacity-60"
                :class="
                    localFilters.status === tab.value
                        ? 'bg-talents-600 text-white shadow-sm'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 hover:text-slate-900'
                "
                :aria-selected="localFilters.status === tab.value"
                :disabled="navigating"
                @click="setStatusTab(tab.value)"
            >
                {{ tab.label }}
            </button>
            <button
                type="button"
                role="switch"
                class="ml-auto inline-flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-wait disabled:opacity-60"
                :aria-checked="localFilters.group"
                :disabled="navigating"
                title="Mostra as parcelas de cada venda juntas, na mesma página"
                @click="toggleGroupBySale"
            >
                <span
                    class="relative inline-flex h-4 w-7 shrink-0 rounded-full transition"
                    :class="localFilters.group ? 'bg-talents-600' : 'bg-slate-300'"
                    aria-hidden="true"
                >
                    <span
                        class="absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition"
                        :class="localFilters.group ? 'left-3.5' : 'left-0.5'"
                    />
                </span>
                Agrupar por venda
            </button>
        </div>

        <form
            class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_12rem_14rem_auto]"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="localFilters.q"
                type="search"
                placeholder="Buscar título, cliente ou código da venda"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            />
            <select
                v-model="localFilters.origin"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            >
                <option value="">Todas as origens</option>
                <option v-for="opt in originOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select
                v-model="localFilters.sort"
                aria-label="Ordenar por"
                title="Ordenar por"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60 disabled:cursor-wait disabled:opacity-60"
                :disabled="navigating"
                @change="applyFilters"
            >
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                    Ordenar por {{ opt.label.toLowerCase() }}
                </option>
            </select>
            <PrimaryButton type="submit" class="disabled:cursor-wait disabled:opacity-60" :disabled="navigating">
                {{ navigating ? 'Carregando…' : 'Filtrar' }}
            </PrimaryButton>
        </form>

        <div
            class="surface-card overflow-hidden transition-opacity duration-150"
            :class="navigating ? 'pointer-events-none opacity-60' : ''"
            :aria-busy="navigating"
        >
            <div v-if="!items.data?.length" class="px-5 py-10 text-center text-sm text-slate-600">
                Nenhum recebimento encontrado.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Descrição</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Origem</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Vencimento</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Recebimento</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Valor</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="row in ledgerRows" :key="row.item.id">
                        <tr v-if="row.groupStart && row.groupSize > 1" class="bg-slate-50">
                            <td colspan="7" class="px-4 py-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ row.item.counterparty || 'Venda' }}</p>
                                        <p class="text-xs text-slate-500">{{ row.groupSize }} parcelas</p>
                                    </div>
                                    <Link
                                        v-if="row.item.href"
                                        :href="row.item.href"
                                        class="text-xs font-semibold text-talents-700 hover:underline"
                                    >
                                        Ver venda
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr
                            :class="row.groupSize > 1 ? 'border-l-4 border-l-talents-200 bg-white' : ''"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ row.item.title }}</p>
                                <p v-if="row.groupSize === 1" class="text-xs text-slate-500">{{ row.item.counterparty || '—' }}</p>
                                <p
                                    v-if="row.groupSize === 1 && installmentContext(row.item)"
                                    class="text-xs text-slate-400"
                                >
                                    {{ installmentContext(row.item) }}
                                </p>
                                <span
                                    v-if="row.item.recurring_label"
                                    class="mt-1 inline-flex rounded-full bg-talents-50 px-2 py-0.5 text-[11px] font-medium text-talents-800 ring-1 ring-talents-100"
                                >
                                    {{ row.item.recurring_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        row.item.source === 'sale'
                                            ? 'bg-violet-50 text-violet-800'
                                            : 'bg-sky-50 text-sky-800'
                                    "
                                >
                                    {{ row.item.source_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ formatDate(row.item.due_date) }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ formatDate(row.item.paid_at) }}</td>
                            <td class="px-4 py-3 font-medium tabular-nums text-slate-900">
                                {{ formatBRL(row.item.amount_cents) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(row.item.status)"
                                >
                                    {{ row.item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center justify-end gap-0.5">
                                    <button
                                        v-if="row.item.can_mark_paid"
                                        type="button"
                                        :class="iconBtnClass"
                                        class="text-emerald-700 hover:text-emerald-900"
                                        title="Receber"
                                        aria-label="Receber"
                                        @click="openReceive(row.item)"
                                    >
                                        <BanknotesIcon class="h-4 w-4" />
                                    </button>
                                    <button
                                        v-if="row.item.can_edit"
                                        type="button"
                                        :class="iconBtnClass"
                                        title="Editar"
                                        aria-label="Editar"
                                        @click="openEditModal(row.item)"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <Link
                                        v-if="row.groupSize === 1 && row.item.source === 'sale' && row.item.href"
                                        :href="row.item.href"
                                        class="ml-1 text-xs font-medium text-slate-600 hover:underline"
                                    >
                                        Ver venda
                                    </Link>
                                    <button
                                        v-if="row.item.can_delete"
                                        type="button"
                                        :class="iconBtnClass"
                                        class="text-red-600 hover:text-red-800"
                                        title="Excluir"
                                        aria-label="Excluir"
                                        @click="remove(row.item.receivable_id)"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div
                v-if="items.links?.length > 3"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm"
            >
                <p class="text-xs text-slate-500" role="status" aria-live="polite">
                    {{ items.from }}–{{ items.to }} de {{ items.total }}
                    <span v-if="navigating" class="ml-2 text-slate-400">Carregando…</span>
                </p>
                <div class="flex flex-wrap items-center justify-end gap-1">
                    <template v-for="link in items.links" :key="link.label">
                        <span
                            v-if="link.url && navigating"
                            class="cursor-wait rounded-lg px-3 py-1 text-slate-400"
                            :class="link.active ? 'bg-talents-600/60 text-white' : ''"
                            aria-disabled="true"
                            v-html="link.label"
                        />
                        <Link
                            v-else-if="link.url"
                            :href="link.url"
                            class="rounded-lg px-3 py-1 text-slate-700 transition hover:bg-white"
                            :class="link.active ? 'bg-talents-600 text-white hover:bg-talents-600' : ''"
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span v-else class="cursor-not-allowed rounded-lg px-3 py-1 text-slate-400" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>

        <FullScreenOverlay :show="paymentModalOpen && !!paymentItem" @close="closePaymentModal">
            <div
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl supports-[height:100dvh]:max-h-[calc(100dvh-2rem)]"
            >
                <div class="shrink-0 border-b border-slate-100 px-5 pb-4 pt-5 sm:px-6 sm:pt-6">
                    <h3 class="text-lg font-semibold text-slate-900">Receber parcela</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ paymentItem?.title }}
                        <span v-if="paymentItem?.counterparty"> — {{ paymentItem.counterparty }}</span>
                    </p>
                    <p class="mt-1 text-sm text-slate-600">
                        Valor: {{ formatBRL(paymentItem?.amount_cents ?? 0) }}
                    </p>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitPayment">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                        <div
                            v-if="paymentForm.hasErrors"
                            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
                        >
                            Não foi possível salvar. Verifique os campos e tente novamente.
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                            <select
                                v-model="paymentForm.status"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="pendente">Pendente</option>
                                <option value="pago">Pago</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                            <p v-if="paymentForm.errors.status" class="mt-1 text-xs text-rose-600">
                                {{ paymentForm.errors.status }}
                            </p>
                        </div>
                        <div v-if="paymentForm.status === 'pago'">
                            <!-- Desktop: data e valor lado a lado; celular: empilhados. -->
                            <div class="grid gap-3 sm:grid-cols-2">
                                <PaymentDateFields
                                    v-model:date="paymentForm.paid_at"
                                    label="Data do recebimento"
                                    :max="maxPaidDate"
                                    :date-error="paymentForm.errors.paid_at"
                                />
                                <div>
                                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                        Valor pago (R$)
                                    </label>
                                    <MoneyInput
                                        v-model="paidAmountReais"
                                        class="mt-1 w-full text-sm"
                                        readonly
                                    />
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">O valor pago precisa ser igual ao da parcela.</p>
                            <p v-if="paymentForm.errors.paid_amount_cents" class="mt-1 text-xs text-rose-600">
                                {{ paymentForm.errors.paid_amount_cents }}
                            </p>
                            <label class="mt-3 block text-xs font-medium uppercase tracking-wide text-slate-500">
                                Conta de destino/recebimento
                            </label>
                            <select
                                v-model="paymentForm.bank_account_id"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="" disabled>Selecione a conta</option>
                                <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <p v-if="paymentForm.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                                {{ paymentForm.errors.bank_account_id }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Comprovante</label>
                            <input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                class="mt-1 w-full text-sm"
                                @change="onReceiptChange"
                            />
                            <p v-if="paymentForm.errors.receipt" class="mt-1 text-xs text-rose-600">
                                {{ paymentForm.errors.receipt }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea
                                v-model="paymentForm.notes"
                                rows="2"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="paymentForm.errors.notes" class="mt-1 text-xs text-rose-600">
                                {{ paymentForm.errors.notes }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closePaymentModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="paymentForm.processing"
                        >
                            {{ paymentForm.processing ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>

        <FullScreenOverlay :show="editModalOpen && !!selectedItem" @close="closeEditModal">
            <div
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl supports-[height:100dvh]:max-h-[calc(100dvh-2rem)]"
            >
                <div class="shrink-0 border-b border-slate-100 px-5 pb-4 pt-5 sm:px-6 sm:pt-6">
                    <h3 class="text-lg font-semibold text-slate-900">Editar cobrança</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ selectedItem?.title }}
                        <span v-if="selectedItem?.counterparty"> — {{ selectedItem.counterparty }}</span>
                    </p>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitEdit">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Título</label>
                            <input
                                v-model="editForm.title"
                                type="text"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="editForm.errors.title" class="mt-1 text-xs text-rose-600">{{ editForm.errors.title }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Pagador / cliente</label>
                            <input
                                v-model="editForm.payer_name"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                            <p v-if="editForm.errors.payer_name" class="mt-1 text-xs text-rose-600">
                                {{ editForm.errors.payer_name }}
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Valor (R$)</label>
                                <MoneyInput
                                    v-model="editForm.amount_reais"
                                    class="mt-1 w-full text-sm"
                                    required
                                />
                                <p v-if="editForm.errors.amount_reais" class="mt-1 text-xs text-rose-600">
                                    {{ editForm.errors.amount_reais }}
                                </p>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vencimento</label>
                                <input
                                    v-model="editForm.due_date"
                                    type="date"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                />
                                <p v-if="editForm.errors.due_date" class="mt-1 text-xs text-rose-600">
                                    {{ editForm.errors.due_date }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                                <select
                                    v-model="editForm.status"
                                    required
                                    class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p v-if="editForm.errors.status" class="mt-1 text-xs text-rose-600">{{ editForm.errors.status }}</p>
                            </div>

                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Forma de pagamento</label>
                                <select
                                    v-model="editForm.payment_method_id"
                                    class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option value="">Não informado</option>
                                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                                <p v-if="editForm.errors.payment_method_id" class="mt-1 text-xs text-rose-600">
                                    {{ editForm.errors.payment_method_id }}
                                </p>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Conta de destino/recebimento
                            </label>
                            <select
                                v-model="editForm.bank_account_id"
                                :required="editBankRequired"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">
                                    {{ editBankPlaceholder }}
                                </option>
                                <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <p v-if="editForm.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                                {{ editForm.errors.bank_account_id }}
                            </p>
                        </div>

                        <PaymentDateFields
                            v-if="editForm.status === 'paid'"
                            v-model:date="editForm.paid_at"
                            v-model:reason="editForm.payment_date_reason"
                            label="Data do recebimento"
                            :max="maxPaidDate"
                            :show-reason="editWasPaid"
                            :date-error="editForm.errors.paid_at"
                            :reason-error="editForm.errors.payment_date_reason"
                        />

                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                            <textarea
                                v-model="editForm.notes"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                placeholder="Anotações internas sobre esta cobrança"
                            />
                            <p v-if="editForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ editForm.errors.notes }}</p>
                        </div>

                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
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
        </FullScreenOverlay>

        <FullScreenOverlay :show="manualReceiveOpen && !!manualReceiveItem" @close="closeManualReceive">
            <div
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-white shadow-xl supports-[height:100dvh]:max-h-[calc(100dvh-2rem)]"
            >
                <div class="shrink-0 border-b border-slate-100 px-5 pb-4 pt-5 sm:px-6 sm:pt-6">
                    <h3 class="text-lg font-semibold text-slate-900">Receber conta</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ manualReceiveItem?.title }}</p>
                    <p class="mt-1 text-sm text-slate-600">
                        Valor: {{ formatBRL(manualReceiveItem?.amount_cents ?? 0) }}
                    </p>
                </div>

                <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submitManualReceive">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                        <PaymentDateFields
                            v-model:date="manualReceiveForm.paid_at"
                            label="Data do recebimento"
                            :max="maxPaidDate"
                            :date-error="manualReceiveForm.errors.paid_at"
                        />
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Conta de destino/recebimento
                            </label>
                            <select
                                v-model="manualReceiveForm.bank_account_id"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="" disabled>Selecione a conta</option>
                                <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <p v-if="manualReceiveForm.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                                {{ manualReceiveForm.errors.bank_account_id }}
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Forma de pagamento
                            </label>
                            <select
                                v-model="manualReceiveForm.payment_method_id"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">Não informado</option>
                                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeManualReceive"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="manualReceiveForm.processing"
                        >
                            {{ manualReceiveForm.processing ? 'A receber…' : 'Confirmar recebimento' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>

        <SaleInstallmentEditModal
            :show="saleEditModalOpen"
            :installment="saleEditInstallment"
            :subtitle="saleEditSubtitle"
            :method-options="installmentMethodOptions"
            :status-options="installmentStatusOptions"
            :bank-accounts="bankAccounts"
            :today="maxPaidDate"
            @close="closeSaleEditModal"
        />
    </AdminLayout>
</template>
