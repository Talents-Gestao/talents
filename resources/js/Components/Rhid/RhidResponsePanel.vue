<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: null, required: true },
    title: { type: String, default: 'Resposta' },
    maxColumns: { type: Number, default: 12 },
});

const KEY_LABELS = {
    guid: 'GUID',
    percent: 'Percentual',
    percentual: 'Percentual',
    message: 'Mensagem',
    total: 'Total',
    page: 'Página',
    maxSize: 'Tamanho máximo',
    status: 'Status',
    id: 'ID',
    name: 'Nome',
    nome: 'Nome',
};

const labelForKey = (key) => KEY_LABELS[key] ?? key;

const isPlainObject = (v) =>
    v !== null && typeof v === 'object' && !Array.isArray(v) && Object.getPrototypeOf(v) === Object.prototype;

const formatCell = (val) => {
    if (val === null || val === undefined) {
        return '—';
    }
    if (typeof val === 'boolean') {
        return val ? 'Sim' : 'Não';
    }
    if (typeof val === 'number') {
        return String(val);
    }
    if (typeof val === 'string') {
        return val.length > 200 ? `${val.slice(0, 200)}…` : val;
    }
    if (Array.isArray(val)) {
        return val.length ? `[${val.length} itens]` : '[]';
    }
    if (isPlainObject(val)) {
        return '{…}';
    }
    return String(val);
};

const tableRows = computed(() => {
    const d = props.data;
    if (d == null) {
        return null;
    }
    if (Array.isArray(d) && d.length > 0 && typeof d[0] === 'object' && d[0] !== null) {
        return d;
    }
    if (isPlainObject(d) && Array.isArray(d.data) && d.data.length > 0 && typeof d.data[0] === 'object') {
        return d.data;
    }
    return null;
});

const tableColumns = computed(() => {
    const rows = tableRows.value;
    if (!rows?.length) {
        return [];
    }
    const keys = new Set();
    for (const row of rows.slice(0, 50)) {
        if (row && typeof row === 'object') {
            Object.keys(row).forEach((k) => keys.add(k));
        }
    }
    return Array.from(keys).slice(0, props.maxColumns);
});

const metaEntries = computed(() => {
    const d = props.data;
    if (!isPlainObject(d)) {
        return [];
    }
    const skip = new Set(['data']);
    const entries = [];
    for (const [k, v] of Object.entries(d)) {
        if (skip.has(k)) {
            continue;
        }
        if (v !== null && typeof v === 'object') {
            continue;
        }
        entries.push([k, v]);
    }
    return entries;
});

const summaryObject = computed(() => {
    const d = props.data;
    if (d == null || typeof d !== 'object') {
        return null;
    }
    if (Array.isArray(d)) {
        return null;
    }
    if (tableRows.value && Array.isArray(d.data)) {
        return null;
    }
    if (Array.isArray(d.data)) {
        return null;
    }
    const keys = Object.keys(d);
    if (keys.length === 0) {
        return null;
    }
    const allPrimitive = keys.every((k) => {
        const v = d[k];
        return v === null || ['string', 'number', 'boolean'].includes(typeof v);
    });
    if (allPrimitive && keys.length <= 20) {
        return d;
    }
    return null;
});

const primitiveDisplay = computed(() => {
    const d = props.data;
    if (d === null || d === undefined) {
        return null;
    }
    if (typeof d === 'string' || typeof d === 'number' || typeof d === 'boolean') {
        return String(d);
    }
    return null;
});

const jsonFormatted = computed(() => {
    try {
        return JSON.stringify(props.data, null, 2);
    } catch {
        return String(props.data);
    }
});

const isEmptyArray = computed(
    () => Array.isArray(props.data) && props.data.length === 0,
);

const isEmptyDataPayload = computed(() => {
    const d = props.data;
    return (
        isPlainObject(d) &&
        Array.isArray(d.data) &&
        d.data.length === 0
    );
});
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-800">
        <p class="text-xs font-semibold text-slate-500">{{ title }}</p>

        <p v-if="isEmptyArray" class="mt-2 text-slate-600">Nenhum registro retornado.</p>

        <p v-else-if="primitiveDisplay !== null" class="mt-2 break-words text-slate-800">
            {{ primitiveDisplay }}
        </p>

        <dl
            v-else-if="summaryObject && !tableRows"
            class="mt-2 grid gap-1 sm:grid-cols-2"
        >
            <template v-for="(v, k) in summaryObject" :key="k">
                <dt class="text-xs font-medium text-slate-500">{{ labelForKey(k) }}</dt>
                <dd class="break-all font-mono text-xs text-slate-800">{{ formatCell(v) }}</dd>
            </template>
        </dl>

        <div v-if="metaEntries.length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
            <span v-for="([k, v]) in metaEntries" :key="k">
                <span class="font-medium text-slate-500">{{ labelForKey(k) }}:</span>
                {{ formatCell(v) }}
            </span>
        </div>

        <p v-if="isEmptyDataPayload" class="mt-2 text-slate-600">Nenhum item na listagem.</p>

        <div v-if="tableColumns.length && tableRows" class="mt-2 overflow-x-auto rounded border border-slate-100">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="col in tableColumns"
                            :key="col"
                            class="max-w-[14rem] whitespace-nowrap p-2 font-semibold text-slate-700"
                        >
                            {{ labelForKey(col) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, ri) in tableRows" :key="ri" class="border-t border-slate-100">
                        <td v-for="col in tableColumns" :key="col" class="max-w-[14rem] p-2 align-top font-mono text-slate-800">
                            {{ formatCell(row[col]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <details class="mt-3 rounded border border-dashed border-slate-200 bg-slate-50 p-2">
            <summary class="cursor-pointer text-xs font-medium text-slate-600">Detalhes tecnicos (JSON)</summary>
            <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-all text-xs text-slate-700">{{ jsonFormatted }}</pre>
        </details>
    </div>
</template>
