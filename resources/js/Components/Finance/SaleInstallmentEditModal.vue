<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import PaymentDateFields from '@/Components/Finance/PaymentDateFields.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { centsToMoneyModel } from '@/utils/moneyMask';
import { saoPauloIsoDay, todayIsoInSaoPaulo } from '@/utils/financeLedger';

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
    today: { type: String, default: '' },
});

const maxPaidDate = computed(() => props.today || todayIsoInSaoPaulo());

const emit = defineEmits(['close']);

const centsToReais = (cents) => centsToMoneyModel(cents);

const form = useForm({
    amount_reais: '',
    due_date: '',
    status: 'pendente',
    method: 'pix',
    bank_account_id: '',
    notes: '',
    paid_at: '',
    payment_date_reason: '',
});

/** Valor bloqueado se a parcela já estava paga ao abrir o modal (regra do backend). */
const amountLocked = computed(() => props.installment?.status === 'pago');
/** Já paga ao abrir: mudar a data é uma correção auditada (com motivo opcional). */
const wasPaid = amountLocked;
/**
 * Conta exigida só na baixa nova. Já paga sem conta (legado): pode salvar a data
 * mantendo a conta como está (o backend mantém a atual se vier vazia).
 */
const bankRequired = computed(() => form.status === 'pago' && !wasPaid.value);
const bankPlaceholder = computed(() =>
    wasPaid.value && !props.installment?.bank_account_id ? 'Sem conta registrada (mantém)' : 'Selecione a conta',
);

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
    // Paga sem data (legado): campo vazio e obrigatório, para não gravar «hoje» sem querer.
    form.paid_at = inst.status === 'pago' ? saoPauloIsoDay(inst.paid_at) : maxPaidDate.value;
    form.payment_date_reason = '';
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

    form
        .transform((data) => ({
            ...data,
            paid_at: data.status === 'pago' ? data.paid_at : null,
            payment_date_reason: data.status === 'pago' && wasPaid.value ? data.payment_date_reason : null,
        }))
        .patch(route('admin.financeiro.parcelas.update', props.installment.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
};

const fieldClass =
    'mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500';
const labelClass = 'text-xs font-medium uppercase tracking-wide text-slate-500';
</script>

<template>
    <FullScreenOverlay :show="show && !!installment" @close="close">
        <!-- Cabe em qualquer altura: cabeçalho e rodapé fixos, só o corpo rola (padrão do modal «Converter em venda»). -->
        <div
            class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl supports-[height:100dvh]:max-h-[calc(100dvh-2rem)]"
        >
            <div class="shrink-0 border-b border-slate-100 px-5 pb-4 pt-5 sm:px-6 sm:pt-6">
                <h3 class="text-lg font-semibold text-slate-900">Editar parcela</h3>
                <p class="mt-1 text-sm text-slate-600">
                    <template v-if="installment?.number">Parcela {{ installment.number }}</template>
                    <span v-if="subtitle"> — {{ subtitle }}</span>
                </p>
            </div>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass">Valor (R$)</label>
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
                            <label :class="labelClass">Vencimento</label>
                            <input v-model="form.due_date" type="date" required :class="fieldClass" />
                            <p v-if="form.errors.due_date" class="mt-1 text-xs text-rose-600">
                                {{ form.errors.due_date }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass">Status</label>
                            <select v-model="form.status" required :class="fieldClass">
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Método</label>
                            <select v-model="form.method" required :class="fieldClass">
                                <option v-for="opt in methodOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.method" class="mt-1 text-xs text-rose-600">{{ form.errors.method }}</p>
                        </div>
                    </div>

                    <div v-if="form.status === 'pago'">
                        <label :class="labelClass">Conta de destino/recebimento</label>
                        <select v-model="form.bank_account_id" :required="bankRequired" :class="fieldClass">
                            <option value="" :disabled="bankRequired">{{ bankPlaceholder }}</option>
                            <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                        <p v-if="form.errors.bank_account_id" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.bank_account_id }}
                        </p>
                    </div>

                    <PaymentDateFields
                        v-if="form.status === 'pago'"
                        v-model:date="form.paid_at"
                        v-model:reason="form.payment_date_reason"
                        label="Data do recebimento"
                        :max="maxPaidDate"
                        :show-reason="wasPaid"
                        :date-error="form.errors.paid_at"
                        :reason-error="form.errors.payment_date_reason"
                    />

                    <div>
                        <label :class="labelClass">Observações</label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            :class="fieldClass"
                            placeholder="Anotações internas sobre esta parcela"
                        />
                        <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
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
