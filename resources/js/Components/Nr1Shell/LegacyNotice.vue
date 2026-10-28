<script setup>
/** Aviso discreto de que a campanha é do modelo anterior (sem os blocos que só existem na V11). */
defineProps({
    text: { type: String, default: 'Pesquisa no modelo anterior' },
});
</script>

<template>
    <span class="n1-legacy-notice" role="note">
        <svg viewBox="0 0 20 20" width="13" height="13" aria-hidden="true"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 4v5m0 3v.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        {{ text }}
    </span>
</template>
