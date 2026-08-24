<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { centsToMoneyModel } from '@/utils/moneyMask';

const props = defineProps({
    show: { type: Boolean, default: false },
    installment: { type: Object, default: null },
    subtitle: { type: String, default: '' },
    methodOptions: {
        type: Array,
        default: () => [
            { value: 'pix', label: 'PIX' },
            { value: 'boleto', label: 'Boleto' },
            { value: 'cartao', label: 'Cartão' },
        ],
    },
    statusOptions: {
        type: Array,
        default: () => [
            { value: 'pendente', label: 'Pendente' },
            { value: 'pago', label: 'Pago' },
            { value: 'cancelado', label: 'Cancelado' },
        ],
    },
    bankAccounts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const centsToReais = (cents) => centsToMoneyModel(cents);

const form = useForm({
    amount_reais: '',
    due_date: '',
    status: 'pendente',
    method: 'pix',
    bank_account_id: '',
    notes: '',
});

/** Valor bloqueado se a parcela já estava paga ao abrir o modal (regra do backend). */
const amountLocked = computed(() => props.installment?.status === 'pago');

const fillFromInstallment = (inst) => {
    if (!inst) {
        return;
    }
    form.clearErrors();
    form.amount_reais = centsToReais(inst.amount_cents);
    form.due_date = inst.due_date ? String(inst.due_date).slice(0, 10) : '';
    form.status = inst.status ?? 'pendente';
    form.method = inst.method ?? 'pix';
    form.bank_account_id = inst.bank_account_id ?? '';
    form.notes = inst.notes ?? '';
};

watch(
    () => [props.show, props.installment?.id],
    ([show]) => {
        if (show && props.installment) {
            fillFromInstallment(props.installment);
        }
    },
);

const close = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    if (!props.installment?.id) {
        return;
    }

    form.patch(route('admin.financeiro.parcelas.update', props.installment.id), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};
</script>

<template>
    <FullScreenOverlay :show="show && !!installment" @close="close">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900">Editar parcela</h3>
            <p class="mt-1 text-sm text-slate-600">
                <template v-if="installment?.number">Parcela {{ installment.number }}</template>
                <span v-if="subtitle"> — {{ subtitle }}</span>
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Valor (R$)</label>
                        <MoneyInput
                            v-model="form.amount_reais"
                            class="mt-1 w-full text-sm"
                            required
                            :readonly="amountLocked"
                        />
                        <p v-if="amountLocked" class="mt-1 text-xs text-slate-500">
                            Parcela paga: o valor não pode ser alterado.
                        </p>
                        <p v-if="form.errors.amount_reais" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.amount_reais }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Vencimento</label>
                        <input
                            v-model="form.due_date"
                            type="date"
                            required
                            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p v-if="form.errors.due_date" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.due_date }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</label>
                    <select
                        v-model="form.status"
                        required
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                </div>

                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Método</label>
                    <select
                        v-model="form.method"
                        required
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option v-for="opt in methodOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.method" class="mt-1 text-xs text-rose-600">{{ form.errors.method }}</p>
                </div>

                <div v-if="form.status === 'pago'">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Conta de destino/recebimento
                    </label>
                    <select
                        v-model="form.bank_account_id"
                        required
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="" disabled>Selecione a conta</option>
                        <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <p v-if="form.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.bank_account_id }}
                    </p>
                </div>

                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observações</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Anotações internas sobre esta parcela"
                    />
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="close"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </form>
        </div>
    </FullScreenOverlay>
</template>
