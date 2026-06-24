<script setup>
import { formatBRL } from '@/composables/useCommercialPricing';
import { computed } from 'vue';

const props = defineProps({
    selection: { type: Object, required: true },
    subtotalCents: { type: Number, default: 0 },
    totalCents: { type: Number, default: 0 },
});

const discountValueReais = computed({
    get() {
        const cents = props.selection.discount_value_cents ?? 0;
        return ((Number(cents) || 0) / 100).toFixed(2).replace('.', ',');
    },
    set(reaisStr) {
        const numeric = Number(String(reaisStr ?? '').replace(/\./g, '').replace(',', '.'));
        props.selection.discount_value_cents = Number.isFinite(numeric)
            ? Math.max(0, Math.round(numeric * 100))
            : 0;
    },
});

const hasDiscount = computed(
    () => props.selection.adjustment === 'discount' && props.subtotalCents > props.totalCents,
);
</script>

<template>
    <div class="mt-3 space-y-3 border-t border-talents-100 pt-3">
        <div>
            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Ajuste comercial</label>
            <select
                v-model="selection.adjustment"
                class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
            >
                <option value="none">Sem ajuste</option>
                <option value="bonus">Bonificação (R$ 0,00)</option>
                <option value="discount">Desconto</option>
            </select>
        </div>

        <template v-if="selection.adjustment === 'discount'">
            <div>
                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Tipo de desconto</label>
                <select
                    v-model="selection.discount_type"
                    class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="percent">Percentagem (%)</option>
                    <option value="value">Valor (R$)</option>
                </select>
            </div>

            <div v-if="selection.discount_type === 'percent'">
                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Desconto (%)</label>
                <input
                    v-model.number="selection.discount_percent"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    placeholder="Ex.: 10"
                    class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                />
            </div>

            <div v-else>
                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Desconto (R$)</label>
                <input
                    v-model="discountValueReais"
                    type="text"
                    placeholder="0,00"
                    class="mt-1 w-full max-w-xs rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                />
            </div>
        </template>

        <div
            v-if="selection.adjustment !== 'none' && subtotalCents > 0"
            class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"
        >
            <div class="flex justify-between gap-2">
                <span>Valor original</span>
                <span class="tabular-nums" :class="{ 'line-through text-slate-400': hasDiscount || selection.adjustment === 'bonus' }">
                    {{ formatBRL(subtotalCents) }}
                </span>
            </div>
            <div v-if="hasDiscount" class="mt-1 flex justify-between gap-2 text-emerald-700">
                <span>Desconto</span>
                <span class="tabular-nums">−{{ formatBRL(subtotalCents - totalCents) }}</span>
            </div>
            <div class="mt-1 flex justify-between gap-2 font-medium text-slate-900">
                <span>Valor final</span>
                <span class="tabular-nums">{{ formatBRL(totalCents) }}</span>
            </div>
        </div>
    </div>
</template>
