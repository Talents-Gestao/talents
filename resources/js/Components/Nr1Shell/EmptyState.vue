<script setup>
/** Estado vazio com ilustração em SVG e texto útil. */
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: '' },
});
</script>

<template>
    <div class="n1-empty" role="status">
        <svg viewBox="0 0 120 90" width="120" height="90" aria-hidden="true">
            <rect x="14" y="30" width="92" height="50" rx="12" fill="#f3e9fa" />
            <rect x="26" y="42" width="40" height="7" rx="3.5" fill="#d9c0ea" />
            <rect x="26" y="56" width="62" height="7" rx="3.5" fill="#e6d6f1" />
            <circle cx="90" cy="26" r="14" fill="#def4f1" />
            <path d="M83 26l5 5 9-10" fill="none" stroke="#168a80" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <b>{{ title }}</b>
        <p v-if="text" class="n1-tiny">{{ text }}</p>
    </div>
</template>
