<script setup>
import { formatBRL } from '@/composables/useCommercialPricing';

/**
 * Devoluções da comissão com histórico e autoria, e as ações permitidas
 * (cobrar o adiantado, registrar devolução, assumir como perda).
 */
defineProps({
    commission: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['charge', 'return', 'loss']);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <div data-commission-refunds>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Devoluções</p>
            <button
                v-if="can.charge_refund && commission.chargeable_advance_cents > 0"
                type="button"
                class="rounded-lg border border-amber-300 bg-white px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-50"
                @click="emit('charge', commission)"
            >
                Cobrar devolução
            </button>
        </div>
        <p v-if="!commission.refunds?.length" class="mt-1 text-sm text-slate-500">Nenhuma devolução.</p>
        <ul v-else class="mt-1 space-y-3 text-sm">
            <li v-for="refund in commission.refunds" :key="refund.id" class="rounded-lg border border-slate-200 bg-white p-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ refund.origin_label }} · {{ formatBRL(refund.amount_cents) }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ formatDate(refund.created_at) }}<template v-if="refund.requested_by"> · por {{ refund.requested_by }}</template>
                        </p>
                        <p v-if="refund.justification" class="mt-1 text-xs text-slate-600">Justificativa: {{ refund.justification }}</p>
                    </div>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="refund.pending_cents > 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                    >
                        {{ refund.pending_cents > 0 ? `Pendente: ${formatBRL(refund.pending_cents)}` : 'Resolvida' }}
                    </span>
                </div>
                <ul v-if="refund.entries?.length" class="mt-2 space-y-1 border-t border-slate-100 pt-2 text-xs text-slate-600">
                    <li v-for="entry in refund.entries" :key="entry.id">
                        {{ formatDate(entry.occurred_at) }} · {{ entry.type_label }}: {{ formatBRL(entry.amount_cents) }}
                        <template v-if="entry.bank_account"> · {{ entry.bank_account }}</template>
                        <template v-if="entry.user"> · por {{ entry.user }}</template>
                        <template v-if="entry.notes"> · {{ entry.notes }}</template>
                    </li>
                </ul>
                <div v-if="refund.pending_cents > 0" class="mt-2 flex flex-wrap justify-end gap-2">
                    <button
                        v-if="can.register_refund"
                        type="button"
                        class="rounded-lg bg-talents-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-talents-700"
                        @click="emit('return', commission, refund)"
                    >
                        Registrar devolução
                    </button>
                    <button
                        v-if="can.assume_loss"
                        type="button"
                        class="rounded-lg border border-rose-300 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                        @click="emit('loss', commission, refund)"
                    >
                        Assumir como perda
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
