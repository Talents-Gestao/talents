<script setup>
import { formatBRL } from '@/composables/useCommercialPricing';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Histórico de pagamentos de uma comissão. Só o mais recente pode ser excluído
 * (can_delete vem do servidor, já considerando a permissão de exclusão).
 */
const props = defineProps({
    commission: { type: Object, required: true },
});

const deleting = ref(false);
const error = ref('');

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const destroy = (payment) => {
    if (deleting.value) return;
    if (!window.confirm(`Excluir o pagamento de ${formatBRL(payment.amount_cents)}? O valor volta a ficar liberável.`)) {
        return;
    }
    deleting.value = true;
    error.value = '';
    router.delete(
        route('admin.financeiro.comissoes.pagamentos.destroy', [props.commission.id, payment.id]),
        {
            preserveScroll: true,
            onError: (errors) => {
                error.value = errors.payment ?? Object.values(errors)[0] ?? 'Não foi possível excluir o pagamento.';
            },
            onFinish: () => {
                deleting.value = false;
            },
        },
    );
};
</script>

<template>
    <div>
        <p v-if="!commission.payments?.length" class="text-sm text-slate-500">Nenhum pagamento registrado.</p>
        <ul v-else class="divide-y divide-slate-100 text-sm">
            <li
                v-for="payment in commission.payments"
                :key="payment.id"
                class="flex flex-wrap items-start justify-between gap-2 py-2"
            >
                <div>
                    <p class="font-semibold tabular-nums text-slate-900">{{ formatBRL(payment.amount_cents) }}</p>
                    <p class="text-xs text-slate-500">
                        {{ formatDate(payment.paid_at) }}
                        <template v-if="payment.paid_by"> · por {{ payment.paid_by }}</template>
                    </p>
                    <p v-if="payment.notes" class="mt-0.5 text-xs text-slate-600">{{ payment.notes }}</p>
                </div>
                <button
                    v-if="payment.can_delete"
                    type="button"
                    class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                    :disabled="deleting"
                    @click="destroy(payment)"
                >
                    {{ deleting ? 'Excluindo…' : 'Excluir' }}
                </button>
            </li>
        </ul>
        <p v-if="error" role="alert" class="mt-2 text-xs text-rose-600">{{ error }}</p>
    </div>
</template>
