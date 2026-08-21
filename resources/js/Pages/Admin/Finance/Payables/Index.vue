<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    payables: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    bankAccounts: { type: Array, default: () => [] },
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

const payModalOpen = ref(false);
const payItem = ref(null);
const payForm = useForm({
    bank_account_id: '',
    payment_method_id: '',
});

const openPayModal = (item) => {
    payItem.value = item;
    payForm.clearErrors();
    payForm.bank_account_id = item.bank_account_id ?? '';
    payForm.payment_method_id = item.payment_method_id ?? '';
    payModalOpen.value = true;
};

const closePayModal = () => {
    payModalOpen.value = false;
    payItem.value = null;
    payForm.reset();
    payForm.clearErrors();
};

const submitPay = () => {
    if (!payItem.value?.id) {
        return;
    }
    payForm.patch(route('admin.financeiro.contas-a-pagar.mark-paid', payItem.value.id), {
        preserveScroll: true,
        onSuccess: () => closePayModal(),
    });
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
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Conta de origem</th>
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
                            <td class="px-4 py-3 text-slate-700">
                                {{ item.bank_account?.name || item.payment_method?.name || '—' }}
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
                                    @click="openPayModal(item)"
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

        <FullScreenOverlay :show="payModalOpen && !!payItem" @close="closePayModal">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Registrar pagamento</h3>
                <p class="mt-1 text-sm text-slate-600">{{ payItem?.title }}</p>
                <p class="mt-1 text-sm text-slate-600">Valor: {{ formatBRL(payItem?.amount_cents ?? 0) }}</p>

                <form class="mt-4 space-y-4" @submit.prevent="submitPay">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Conta de origem
                        </label>
                        <select
                            v-model="payForm.bank_account_id"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="" disabled>Selecione a conta</option>
                            <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                        <p v-if="payForm.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                            {{ payForm.errors.bank_account_id }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Forma de pagamento
                        </label>
                        <select
                            v-model="payForm.payment_method_id"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="">Não informado</option>
                            <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closePayModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="payForm.processing"
                        >
                            {{ payForm.processing ? 'A pagar…' : 'Confirmar pagamento' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>
    </AdminLayout>
</template>
