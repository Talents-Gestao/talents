<script setup>
import { useNr1Toast } from './useNr1Toast';

const { toasts } = useNr1Toast();
</script>

<template>
    <div class="n1-toasts" role="status" aria-live="polite">
        <div v-for="toast in toasts" :key="toast.id" class="n1-toast" :class="toast.tone">
            <span aria-hidden="true">{{ toast.tone === 'ok' ? '✓' : '!' }}</span>
            {{ toast.message }}
        </div>
    </div>
</template>
