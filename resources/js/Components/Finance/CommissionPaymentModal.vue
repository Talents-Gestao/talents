<script setup>
import Modal from '@/Components/Modal.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Registrar pagamento de comissão. Valores (liberável, pago, total) vêm prontos do servidor
 * (CommissionPayoutCalculator); aqui só se converte centavos ↔ reais para o campo.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    commission: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const todayIso = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const centsToInput = (cents) => (Number(cents || 0) / 100).toFixed(2);

const form = useForm({
    amount: '',
    paid_at: todayIso(),
    notes: '',
});

const releasableCents = computed(() => Number(props.commission?.releasable_cents ?? 0));

watch(
    () => [props.show, props.commission?.id],
    ([show]) => {
        if (!show || !props.commission) return;
        form.clearErrors();
        form.amount = centsToInput(releasableCents.value);
        form.paid_at = todayIso();
        form.notes = '';
    },
    { immediate: true },
);

const close = () => {
    if (form.processing) return;
    emit('close');
};

const submit = () => {
    if (!props.commission) return;
    form.post(route('admin.financeiro.comissoes.pagamentos.store', props.commission.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show && !!commission" max-width="md" @close="close">
        <div v-if="commission" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900">Registrar pagamento de comissão</h2>
            <p class="mt-1 text-sm text-slate-600">
                {{ commission.seller?.name ?? '—' }} · {{ commission.sale?.code }} — {{ commission.sale?.client_name }}
            </p>

            <dl class="mt-4 grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-3 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Total</dt>
                    <dd class="font-semibold tabular-nums text-slate-900">{{ formatBRL(commission.amount_cents) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Pago</dt>
                    <dd class="font-semibold tabular-nums text-slate-900">{{ formatBRL(commission.paid_cents) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Liberável</dt>
                    <dd class="font-semibold tabular-nums text-talents-700">{{ formatBRL(commission.releasable_cents) }}</dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-slate-500">
                O liberável acompanha o que já foi recebido da venda
                ({{ formatBRL(commission.sale?.received_cents) }} de {{ formatBRL(commission.sale?.total_cents) }}).
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <label for="commission-payment-amount" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Valor (R$)
                    </label>
                    <input
                        id="commission-payment-amount"
                        v-model="form.amount"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0.01"
                        :max="centsToInput(releasableCents)"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p class="mt-1 text-xs text-slate-500">Máximo agora: {{ formatBRL(releasableCents) }}</p>
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-rose-600">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label for="commission-payment-date" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Pago em
                    </label>
                    <input
                        id="commission-payment-date"
                        v-model="form.paid_at"
                        type="date"
                        :max="todayIso()"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="form.errors.paid_at" class="mt-1 text-xs text-rose-600">{{ form.errors.paid_at }}</p>
                </div>
                <div>
                    <label for="commission-payment-notes" class="text-xs font-medium uppercase tracking-wide text-slate-500">
                        Observação
                    </label>
                    <textarea
                        id="commission-payment-notes"
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="form.errors.notes" class="mt-1 text-xs text-rose-600">{{ form.errors.notes }}</p>
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
                        class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                        :disabled="form.processing || releasableCents < 1"
                    >
                        {{ form.processing ? 'Salvando…' : 'Registrar pagamento' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
