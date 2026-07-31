<script setup>
/**
 * Overlay de modal em tela cheia (inclui sidebar).
 * Use sempre isto (ou Teleport to="body" + z-[100]) em vez de fixed inset-0
 * dentro do conteúdo do layout — o painel/sidebar criam stacking context.
 */
defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    overlayClass: {
        type: String,
        default: 'bg-black/40 p-4',
    },
});

defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[100] flex items-center justify-center"
            :class="overlayClass"
            role="dialog"
            aria-modal="true"
            @click.self="$emit('close')"
        >
            <slot />
        </div>
    </Teleport>
</template>
