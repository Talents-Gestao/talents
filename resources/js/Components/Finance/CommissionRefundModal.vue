<script setup>
import Modal from '@/Components/Modal.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Devolução de comissão: cobrar (cliente não pagou), registrar o valor devolvido (entra na conta
 * bancária) ou assumir como perda (justificativa obrigatória). Limites vêm do servidor.
 */
const props = defineProps({
    /** 'charge' | 'return' | 'loss' | null */
    mode: { type: String, default: null },
    commission: { type: Object, default: null },
    refund: { type: Object, default: null },
    bankAccounts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const todayIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const centsToInput = (cents) => (Number(cents || 0) / 100).toFixed(2);

const form = useForm({
    amount: '',
    justification: '',
    returned_at: todayIso(),
    bank_account_id: '',
    notes: '',
});

const maxCents = computed(() => {
    if (props.mode === 'charge') return Number(props.commission?.chargeable_advance_cents ?? 0);

    return Number(props.refund?.pending_cents ?? 0);
});

const copy = computed(() => ({
    charge: {
        title: 'Cobrar devolução',
        intro: 'O cliente não pagou: a colaboradora deve devolver o valor adiantado.',
        max: 'Adiantado em aberto',
        submit: 'Cobrar devolução',
    },
    return: {
        title: 'Registrar devolução',
        intro: 'Valor devolvido pela colaboradora. Entra no saldo da conta escolhida (não entra no fluxo de caixa).',
        max: 'Pendente',
        submit: 'Registrar devolução',
    },
    loss: {
        title: 'Assumir como perda',
        intro: 'A empresa assume o valor e a colaboradora não precisa devolvê-lo.',
        max: 'Pendente',
        submit: 'Assumir como perda',
    },
}[props.mode] ?? { title: '', intro: '', max: '', submit: '' }));

const show = computed(() => Boolean(props.mode && props.commission && (props.mode === 'charge' || props.refund)));

watch(
    () => [props.mode, props.commission?.id, props.refund?.id],
    () => {
        if (!show.value) return;
        form.clearErrors();
        form.amount = centsToInput(maxCents.value);
        form.justification = '';
        form.returned_at = todayIso();
        form.bank_account_id = props.bankAccounts.length === 1 ? props.bankAccounts[0].id : '';
        form.notes = '';
    },
    { immediate: true },
);

const close = () => {
    if (form.processing) return;
    emit('close');
};

const submit = () => {
    if (!show.value) return;
    const url = {
        charge: () => route('admin.financeiro.comissoes.devolucoes.store', props.commission.id),
        return: () => route('admin.financeiro.comissoes.devolucoes.registros.store', [props.commission.id, props.refund.id]),
        loss: () => route('admin.financeiro.comissoes.devolucoes.perda.store', [props.commission.id, props.refund.id]),
    }[props.mode]();

    form.transform((data) => {
        if (props.mode === 'return') {
            return { amount: data.amount, returned_at: data.returned_at, bank_account_id: data.bank_account_id, notes: data.notes };
        }

        return { amount: data.amount, justification: data.justification };
    }).post(url, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="close">
        <div v-if="show" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ copy.title }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ commission.seller?.name ?? '—' }} · {{ commission.sale?.code }} — {{ commission.sale?.client_name }}
            </p>
            <p class="mt-2 text-sm text-slate-700">{{ copy.intro }}</p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="commission-refund-amount" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Valor (R$)
                    </label>
                    <input
                        id="commission-refund-amount"
                        v-model="form.amount"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0.01"
                        :max="centsToInput(maxCents)"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p class="mt-1 text-xs text-slate-500">{{ copy.max }}: {{ formatBRL(maxCents) }}</p>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-rose-600">{{ form.errors.amount }}</p>
                </div>

                <template v-if="mode === 'return'">
                    <div>
                        <label for="commission-refund-date" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Devolvido em
                        </label>
                        <input
                            id="commission-refund-date"
                            v-model="form.returned_at"
                            type="date"
                            :max="todayIso()"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="form.errors.returned_at" class="mt-1 text-xs text-rose-600">{{ form.errors.returned_at }}</p>
                    </div>
                    <div>
                        <label for="commission-refund-account" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Conta bancária de entrada
                        </label>
                        <select
                            id="commission-refund-account"
                            v-model="form.bank_account_id"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option value="" disabled>Selecione a conta</option>
                            <option v-for="account in bankAccounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                        <p v-if="form.errors.bank_account_id" class="mt-1 text-xs text-rose-600">{{ form.errors.bank_account_id }}</p>
                    </div>
                    <div>
                        <label for="commission-refund-notes" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Observação
                        </label>
                        <textarea
                            id="commission-refund-notes"
                            v-model="form.notes"
                            rows="2"
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                </template>

                <div v-else>
                    <label for="commission-refund-justification" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Justificativa (obrigatória)
                    </label>
                    <textarea
                        id="commission-refund-justification"
                        v-model="form.justification"
                        rows="3"
                        required
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="form.errors.justification" class="mt-1 text-xs text-rose-600">{{ form.errors.justification }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        :disabled="form.processing"
                        @click="close"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :class="mode === 'loss' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-talents-600 hover:bg-talents-700'"
                        :disabled="form.processing || maxCents < 1 || (mode === 'return' ? !form.bank_account_id : !form.justification.trim())"
                    >
                        {{ form.processing ? 'Salvando…' : copy.submit }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
