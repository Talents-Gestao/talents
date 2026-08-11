<script setup>
/**
 * Overlay de modal em tela cheia (inclui sidebar).
 * Use sempre isto (ou Teleport to="body" + z-[100]) em vez de fixed inset-0
 * dentro do conteúdo do layout — o painel/sidebar criam stacking context.
 *
 * Fecha só se mousedown e click ocorrerem no backdrop
 * (evita fechar ao selecionar texto no formulário e soltar fora).
 */
import { ref } from 'vue';
import {
    isPointerDownOnBackdrop,
    shouldCloseOnBackdropClick,
} from '@/utils/backdropCloseGesture';

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

const emit = defineEmits(['close']);

const backdropPointerDown = ref(false);

const onOverlayPointerDown = (event) => {
    backdropPointerDown.value = isPointerDownOnBackdrop(event);
};

const onBackdropClick = (event) => {
    const startedOnBackdrop = backdropPointerDown.value;
    backdropPointerDown.value = false;

    if (shouldCloseOnBackdropClick(startedOnBackdrop, isPointerDownOnBackdrop(event))) {
        emit('close');
    }
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[100] flex items-center justify-center"
            :class="overlayClass"
            role="dialog"
            aria-modal="true"
            @mousedown="onOverlayPointerDown"
            @click.self="onBackdropClick"
        >
            <slot />
        </div>
    </Teleport>
</template>
