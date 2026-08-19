<script setup>
/**
 * DateRangePicker — seletor de período com dois inputs `type="date"` nativos.
 *
 * Props:
 *   modelValue: { start: string, end: string }
 *     Datas no formato "dd/mm/aaaa" (compatível com a API Sólides).
 *   label: string opcional para o campo unificado.
 *
 * Emits:
 *   update:modelValue — { start, end } no formato "dd/mm/aaaa"
 */

import { CalendarDaysIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ start: '', end: '' }),
    },
    label: { type: String, default: 'Período' },
    id: { type: String, default: 'date-range' },
});

const emit = defineEmits(['update:modelValue']);

// Converte "dd/mm/aaaa" → "aaaa-mm-dd" (valor do <input type="date">)
const toInputDate = (ddmmyyyy) => {
    if (!ddmmyyyy) return '';
    const [d, m, y] = ddmmyyyy.split('/');
    if (!d || !m || !y) return '';
    return `${y}-${m.padStart(2, '0')}-${d.padStart(2, '0')}`;
};

// Converte "aaaa-mm-dd" → "dd/mm/aaaa"
const fromInputDate = (yyyymmdd) => {
    if (!yyyymmdd) return '';
    const [y, m, d] = yyyymmdd.split('-');
    if (!y || !m || !d) return '';
    return `${d}/${m}/${y}`;
};

const startInput = computed({
    get: () => toInputDate(props.modelValue?.start),
    set: (v) => {
        const start = fromInputDate(v);
        emit('update:modelValue', { ...props.modelValue, start });
    },
});

const endInput = computed({
    get: () => toInputDate(props.modelValue?.end),
    set: (v) => {
        const end = fromInputDate(v);
        emit('update:modelValue', { ...props.modelValue, end });
    },
});

// Data mínima do end = start (para impedir range invertido no picker nativo)
const minEnd = computed(() => startInput.value || '');
const maxStart = computed(() => endInput.value || '');

const clear = () => emit('update:modelValue', { start: '', end: '' });

const hasValue = computed(() => props.modelValue?.start || props.modelValue?.end);

// Rótulo resumido exibido quando há seleção
const summaryLabel = computed(() => {
    const s = props.modelValue?.start;
    const e = props.modelValue?.end;
    if (s && e) return `${s} — ${e}`;
    if (s) return `A partir de ${s}`;
    if (e) return `Até ${e}`;
    return '';
});
</script>

<template>
    <div class="space-y-1.5">
        <div class="flex items-center justify-between gap-2">
            <label class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                <CalendarDaysIcon class="h-4 w-4 text-talents-500" />
                {{ label }}
            </label>
            <button
                v-if="hasValue"
                type="button"
                class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                @click="clear"
            >
                <XMarkIcon class="h-3.5 w-3.5" />
                Limpar
            </button>
        </div>

        <!-- Resumo quando há período selecionado -->
        <p v-if="summaryLabel" class="text-xs font-semibold text-talents-700">
            {{ summaryLabel }}
        </p>

        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white shadow-sm ring-0 transition focus-within:border-talents-400 focus-within:ring-2 focus-within:ring-talents-200/70">
            <!-- Data inicial -->
            <div class="flex min-w-0 flex-1 flex-col px-3 py-2">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">De</span>
                <input
                    :id="`${id}-start`"
                    v-model="startInput"
                    type="date"
                    :max="maxStart"
                    class="mt-0.5 w-full border-none bg-transparent p-0 text-sm text-slate-800 focus:outline-none focus:ring-0"
                />
            </div>

            <!-- Divisor -->
            <div class="h-8 w-px shrink-0 bg-slate-200" />

            <!-- Data final -->
            <div class="flex min-w-0 flex-1 flex-col px-3 py-2">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Até</span>
                <input
                    :id="`${id}-end`"
                    v-model="endInput"
                    type="date"
                    :min="minEnd"
                    class="mt-0.5 w-full border-none bg-transparent p-0 text-sm text-slate-800 focus:outline-none focus:ring-0"
                />
            </div>
        </div>
    </div>
</template>
