<script setup>
defineProps({
    status: { type: String, required: true },
    label: { type: String, default: '' },
});

const classes = {
    a_pagar: 'bg-amber-100 text-amber-800',
    parcial: 'bg-sky-100 text-sky-800',
    paga: 'bg-emerald-100 text-emerald-800',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
        :class="classes[status] ?? 'bg-slate-100 text-slate-600'"
    >
        {{ label || status }}
    </span>
</template>
