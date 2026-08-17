<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    originOptions: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
    installmentMethodOptions: { type: Array, default: () => [] },
    installmentStatusOptions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const localFilters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    origin: props.filters.origin ?? '',
});

watch(
    () => props.filters,
    (value) => {
        localFilters.q = value?.q ?? '';
        localFilters.status = value?.status ?? '';
        localFilters.origin = value?.origin ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.financeiro.contas-a-receber.index'),
        {
            q: localFilters.q || undefined,
            status: localFilters.status || undefined,
            origin: localFilters.origin || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');

const centsToReais = (cents) => (Number(cents || 0) / 100).toFixed(2);

const statusClass = (status) =>
    ({
        pending: 'bg-amber-50 text-amber-800',
        paid: 'bg-emerald-50 text-emerald-800',
        cancelled: 'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600');

const markPaid = (receivableId) => {
    if (!confirm('Marcar esta conta como recebida?')) {
        return;
    }
    router.patch(route('admin.financeiro.contas-a-receber.mark-paid', receivableId), {}, { preserveScroll: true });
};

const remove = (receivableId) => {
    if (!confirm('Remover esta conta a receber?')) {
        return;
    }
    router.delete(route('admin.financeiro.contas-a-receber.destroy', receivableId));
};

const editModalOpen = ref(false);
const selectedItem = ref(null);
const isSaleEdit = computed(() => selectedItem.value?.source === 'sale');

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
});

const openEditModal = (item) => {
    selectedItem.value = item;
    editForm.clearErrors();
    editForm.title = item.title ?? '';
    editForm.payer_name = item.counterparty ?? '';
    editForm.amount_reais = centsToReais(item.amount_cents);
    editForm.due_date = item.due_date ?? '';
    editForm.notes = item.notes ?? '';

    if (item.source === 'sale') {
        editForm.status = item.installment_status ?? 'pendente';
        editForm.method = item.method ?? 'pix';
        editForm.payment_method_id = '';
        editForm.bank_account_id = '';
    } else {
        editForm.status = item.status ?? 'pending';
        editForm.payment_method_id = item.payment_method_id ?? '';
        editForm.bank_account_id = item.bank_account_id ?? '';
        editForm.method = 'pix';
    }

    editModalOpen.value = true;
};

const closeEditModal = () => {
    editModalOpen.value = false;
    selectedItem.value = null;
    editForm.reset();
    editForm.clearErrors();
};

const submitEdit = () => {
    if (!selectedItem.value) {
        return;
    }

    if (isSaleEdit.value) {
        editForm.patch(route('admin.financeiro.parcelas.update', selectedItem.value.installment_id), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
        return;
    }

    editForm.put(route('admin.financeiro.contas-a-receber.update', selectedItem.value.receivable_id), {
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
            class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-[1fr_10rem_12rem_auto]"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="localFilters.q"
                type="search"
                placeholder="Buscar título, cliente ou código da venda"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            />
            <select
                v-model="localFilters.status"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            >
                <option value="">Todos os status</option>
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select
                v-model="localFilters.origin"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            >
                <option value="">Todas as origens</option>
                <option v-for="opt in originOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
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
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Valor</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items.data" :key="item.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ item.title }}</p>
                                <p class="text-xs text-slate-500">{{ item.counterparty || '—' }}</p>
                                <span
                                    v-if="item.recurring_label"
                                    class="mt-1 inline-flex rounded-full bg-talents-50 px-2 py-0.5 text-[11px] font-medium text-talents-800 ring-1 ring-talents-100"
                                >
                                    {{ item.recurring_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        item.source === 'sale'
                                            ? 'bg-violet-50 text-violet-800'
                                            : 'bg-sky-50 text-sky-800'
                                    "
                                >
                                    {{ item.source_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ formatDate(item.due_date) }}</td>
                            <td class="px-4 py-3 font-medium tabular-nums text-slate-900">
                                {{ formatBRL(item.amount_cents) }}
                            </td>
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
                                    v-if="item.can_mark_paid"
                                    type="button"
                                    class="font-medium text-emerald-700 hover:underline"
                                    @click="markPaid(item.receivable_id)"
                                >
                                    Receber
                                </button>
                                <button
                                    v-if="item.can_edit"
                                    type="button"
                                    class="ml-3 font-medium text-talents-700 hover:underline"
                                    @click="openEditModal(item)"
                                >
                                    Editar
                                </button>
                                <Link
                                    v-if="item.source === 'sale' && item.href"
                                    :href="item.href"
                                    class="ml-3 font-medium text-slate-600 hover:underline"
                                >
                                    Ver venda
                                </Link>
                                <button
                                    v-if="item.can_delete"
                                    type="button"
                                    class="ml-3 font-medium text-red-600 hover:underline"
                                    @click="remove(item.receivable_id)"
                                >
                                    Excluir
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <FullScreenOverlay :show="editModalOpen && !!selectedItem" @close="closeEditModal">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Editar cobrança</h3>
                <p class="mt-1 text-sm text-slate-600">
                    {{ selectedItem?.title }}
                    <span v-if="selectedItem?.counterparty"> — {{ selectedItem.counterparty }}</span>
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <template v-if="!isSaleEdit">
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
                    </template>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Valor (R$)</label>
                            <input
                                v-model="editForm.amount_reais"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
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

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                        <select
                            v-model="editForm.status"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <template v-if="isSaleEdit">
                                <option
                                    v-for="opt in installmentStatusOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </template>
                            <template v-else>
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </template>
                        </select>
                        <p v-if="editForm.errors.status" class="mt-1 text-xs text-rose-600">{{ editForm.errors.status }}</p>
                    </div>

                    <div v-if="isSaleEdit">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Método</label>
                        <select
                            v-model="editForm.method"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option
                                v-for="opt in installmentMethodOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.method" class="mt-1 text-xs text-rose-600">{{ editForm.errors.method }}</p>
                    </div>

                    <template v-else>
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
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Conta bancária</label>
                            <select
                                v-model="editForm.bank_account_id"
                                class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">Não informado</option>
                                <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <p v-if="editForm.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                                {{ editForm.errors.bank_account_id }}
                            </p>
                        </div>
                    </template>

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
        </FullScreenOverlay>
    </AdminLayout>
</template>
