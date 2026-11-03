<script setup>
/**
 * Campo «Data de pagamento» (só-dia, sem datas futuras) e, ao corrigir um
 * lançamento já baixado, o «Motivo da alteração» opcional (vai para a auditoria).
 * Desktop: data e motivo lado a lado; celular: empilhados.
 */
import { computed } from 'vue';
import { todayIsoInSaoPaulo } from '@/utils/financeLedger';

const props = defineProps({
    label: { type: String, default: 'Data de pagamento' },
    max: { type: String, default: '' },
    showReason: { type: Boolean, default: false },
    dateError: { type: String, default: '' },
    reasonError: { type: String, default: '' },
    required: { type: Boolean, default: true },
});

const date = defineModel('date', { type: String, default: '' });
const reason = defineModel('reason', { type: String, default: '' });

const maxDate = computed(() => props.max || todayIsoInSaoPaulo());
const inputClass =
    'mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500';
</script>

<template>
    <div class="grid gap-3" :class="showReason ? 'sm:grid-cols-2' : ''">
        <div>
            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</label>
            <input v-model="date" type="date" :max="maxDate" :required="required" :class="inputClass" />
            <p v-if="dateError" class="mt-1 text-xs text-rose-600">{{ dateError }}</p>
        </div>
        <div v-if="showReason">
            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                Motivo (opcional)
            </label>
            <input
                v-model="reason"
                type="text"
                maxlength="500"
                placeholder="Ex.: lançado com atraso"
                :class="inputClass"
            />
            <p v-if="reasonError" class="mt-1 text-xs text-rose-600">{{ reasonError }}</p>
        </div>
        <p v-if="showReason && !date" class="text-xs text-amber-700 sm:col-span-2">
            Lançamento pago sem data registrada: informe a data do pagamento.
        </p>
        <p v-else-if="showReason" class="text-xs text-slate-500 sm:col-span-2">
            A alteração da data fica registrada no histórico financeiro.
        </p>
    </div>
</template>
