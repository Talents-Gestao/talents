<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
    payCommission: { type: Boolean, default: false },
    commissionPercent: { type: [Number, String], default: 0 },
    disabled: { type: Boolean, default: false },
    /** Quando true, permite editar a % (cadastro na Equipe). */
    editablePercent: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:payCommission', 'update:commissionPercent']);

const percentModel = computed({
    get: () => props.commissionPercent,
    set: (value) => emit('update:commissionPercent', value),
});

const selectMode = (enabled) => {
    if (props.disabled) {
        return;
    }
    emit('update:payCommission', enabled);
    if (!enabled) {
        emit('update:commissionPercent', 0);
    }
};

watch(
    () => props.payCommission,
    (enabled) => {
        if (!enabled && Number(props.commissionPercent) !== 0) {
            emit('update:commissionPercent', 0);
        }
    },
);
</script>

<template>
    <fieldset class="space-y-3" :disabled="disabled">
        <legend class="text-xs font-medium uppercase tracking-wide text-slate-500">Comissão</legend>
        <p class="text-sm text-slate-600">
            Defina se este administrador recebe comissão nas propostas em que for o vendedor.
            A porcentagem é fixa e aplicada automaticamente.
        </p>

        <div class="grid gap-2 sm:grid-cols-2">
            <button
                type="button"
                class="rounded-xl border px-3 py-2.5 text-left text-sm transition"
                :class="!payCommission
                    ? 'border-talents-500 bg-talents-50 text-talents-900 shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-talents-200 hover:bg-talents-50/60'"
                :disabled="disabled"
                @click="selectMode(false)"
            >
                <span class="font-semibold">Sem comissão</span>
                <span class="mt-0.5 block text-xs text-slate-500">Não gerar valor a pagar.</span>
            </button>
            <button
                type="button"
                class="rounded-xl border px-3 py-2.5 text-left text-sm transition"
                :class="payCommission
                    ? 'border-talents-500 bg-talents-50 text-talents-900 shadow-sm'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-talents-200 hover:bg-talents-50/60'"
                :disabled="disabled"
                @click="selectMode(true)"
            >
                <span class="font-semibold">Com comissão</span>
                <span class="mt-0.5 block text-xs text-slate-500">Percentual fixo sobre o valor da venda.</span>
            </button>
        </div>

        <div v-if="payCommission && editablePercent">
            <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="admin-commission-percent">
                Percentual (%)
            </label>
            <input
                id="admin-commission-percent"
                v-model.number="percentModel"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="mt-1 w-full max-w-xs rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                :disabled="disabled"
            />
            <p v-if="errors.commission_percent" class="mt-1 text-xs text-rose-600">
                {{ errors.commission_percent }}
            </p>
        </div>
        <p v-else-if="!payCommission" class="text-xs text-slate-500">
            Nenhuma comissão será lançada nas propostas deste vendedor.
        </p>
    </fieldset>
</template>
