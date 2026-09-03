<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    accounts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    transferAccounts: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const localFilters = reactive({
    q: props.filters.q ?? '',
    only_active: Boolean(props.filters.only_active),
});

watch(
    () => props.filters,
    (value) => {
        localFilters.q = value?.q ?? '';
        localFilters.only_active = Boolean(value?.only_active);
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        route('admin.financeiro.contas-bancarias.index'),
        {
            q: localFilters.q || undefined,
            only_active: localFilters.only_active ? 1 : undefined,
        },
        { preserveState: true, replace: true },
    );
};

const remove = async (id) => {
    if (!(await confirmDialog('Remover esta conta bancária?'))) {
        return;
    }
    router.delete(route('admin.financeiro.contas-bancarias.destroy', id));
};

const transferModalOpen = ref(false);
const today = () => new Date().toISOString().slice(0, 10);

const transferForm = useForm({
    from_bank_account_id: '',
    to_bank_account_id: '',
    amount_reais: '',
    transferred_at: today(),
    notes: '',
});

const canTransfer = computed(() => (props.transferAccounts?.length ?? 0) >= 2);

const openTransferModal = (fromId = null) => {
    transferForm.reset();
    transferForm.clearErrors();
    transferForm.transferred_at = today();
    transferForm.from_bank_account_id = fromId ?? '';
    transferForm.to_bank_account_id = '';
    transferModalOpen.value = true;
};

const closeTransferModal = () => {
    transferModalOpen.value = false;
    transferForm.reset();
    transferForm.clearErrors();
};

const submitTransfer = () => {
    transferForm.post(route('admin.financeiro.contas-bancarias.transfer'), {
        preserveScroll: true,
        onSuccess: () => closeTransferModal(),
    });
};

const destinationAccounts = computed(() =>
    (props.transferAccounts ?? []).filter(
        (a) => String(a.id) !== String(transferForm.from_bank_account_id),
    ),
);
</script>

<template>
    <Head title="Financeiro — Contas bancárias" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-500">Financeiro</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Contas bancárias</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Cadastro das contas usadas no fluxo de caixa e nos recebimentos.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canTransfer"
                        :title="
                            canTransfer
                                ? 'Transferir entre contas'
                                : 'Cadastre ao menos duas contas ativas para transferir'
                        "
                        @click="openTransferModal()"
                    >
                        Transferir
                    </button>
                    <Link :href="route('admin.financeiro.contas-bancarias.create')">
                        <PrimaryButton type="button">Nova conta</PrimaryButton>
                    </Link>
                </div>
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

        <div class="mb-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contas ativas</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">
                    {{ summary.active_count ?? 0 }}
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Saldo atual (ativos)
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-700">
                    {{ formatBRL(summary.active_balance_cents ?? 0) }}
                </p>
            </div>
        </div>

        <form
            class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="localFilters.q"
                type="search"
                placeholder="Buscar nome, banco ou conta"
                class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
            />
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input v-model="localFilters.only_active" type="checkbox" class="rounded border-slate-300" />
                Só ativas
            </label>
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div v-if="!accounts.data?.length" class="px-5 py-10 text-center text-sm text-slate-600">
                Nenhuma conta bancária cadastrada.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Conta</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Tipo</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Saldo inicial</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Saldo atual</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                            <th class="px-4 py-3 text-right font-medium text-slate-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in accounts.data" :key="item.id">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ item.name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ item.bank_name || '—' }}
                                    <template v-if="item.agency || item.account_number">
                                        · Ag {{ item.agency || '—' }} / {{ item.account_number || '—' }}
                                    </template>
                                </p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ item.type_label }}</td>
                            <td class="px-4 py-3 font-medium tabular-nums text-slate-900">
                                {{ formatBRL(item.initial_balance_cents) }}
                            </td>
                            <td class="px-4 py-3 font-semibold tabular-nums text-emerald-800">
                                {{ formatBRL(item.current_balance_cents) }}
                            </td>
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
                                <button
                                    v-if="item.is_active && canTransfer"
                                    type="button"
                                    class="font-medium text-slate-700 hover:underline"
                                    @click="openTransferModal(item.id)"
                                >
                                    Transferir
                                </button>
                                <Link
                                    :href="route('admin.financeiro.contas-bancarias.edit', item.id)"
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

        <FullScreenOverlay :show="transferModalOpen" @close="closeTransferModal">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Transferir entre contas</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Move o valor de uma conta ativa para outra, sem gerar conta a pagar ou a receber.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitTransfer">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Conta de origem
                        </label>
                        <select
                            v-model="transferForm.from_bank_account_id"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="" disabled>Selecione a conta</option>
                            <option
                                v-for="a in transferAccounts"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.name }}
                            </option>
                        </select>
                        <p v-if="transferForm.errors.from_bank_account_id" class="mt-1 text-xs text-rose-600">
                            {{ transferForm.errors.from_bank_account_id }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Conta de destino
                        </label>
                        <select
                            v-model="transferForm.to_bank_account_id"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="" disabled>Selecione a conta</option>
                            <option
                                v-for="a in destinationAccounts"
                                :key="a.id"
                                :value="a.id"
                            >
                                {{ a.name }}
                            </option>
                        </select>
                        <p v-if="transferForm.errors.to_bank_account_id" class="mt-1 text-xs text-rose-600">
                            {{ transferForm.errors.to_bank_account_id }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Valor (R$)
                        </label>
                        <MoneyInput
                            v-model="transferForm.amount_reais"
                            class="mt-1 w-full text-sm"
                            required
                        />
                        <p v-if="transferForm.errors.amount_reais" class="mt-1 text-xs text-rose-600">
                            {{ transferForm.errors.amount_reais }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Data
                        </label>
                        <input
                            v-model="transferForm.transferred_at"
                            type="date"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="transferForm.errors.transferred_at" class="mt-1 text-xs text-rose-600">
                            {{ transferForm.errors.transferred_at }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Observações
                        </label>
                        <textarea
                            v-model="transferForm.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="transferForm.errors.notes" class="mt-1 text-xs text-rose-600">
                            {{ transferForm.errors.notes }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeTransferModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="transferForm.processing"
                        >
                            {{ transferForm.processing ? 'A transferir…' : 'Confirmar transferência' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>
    </AdminLayout>
</template>
