<script setup>
/** Reserva a altura da Hero enquanto o chunk das direções carrega (evita deslocamento de layout). */
</script>

<template>
    <div class="hs-placeholder" aria-hidden="true" />
</template>

<style scoped>
.hs-placeholder {
    height: calc(clamp(620px, 50vw, 780px) + 48px);
    background: linear-gradient(to bottom, #ffffff 0%, #f8f4fc 50%, #faf8fc 100%);
}

@media (max-width: 767px) {
    .hs-placeholder {
        height: calc(780px + 32px);
    }
}
</style>
