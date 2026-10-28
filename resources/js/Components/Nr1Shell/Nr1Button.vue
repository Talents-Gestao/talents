<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { buttonClasses } from './nr1Button';

/**
 * Botão único do shell NR-1. Renderiza <button>, <a> (href) ou <Link> do Inertia (href + inertia).
 * Estados e contraste por variante ficam em nr1-shell-buttons.css; nada de estilo solto nos usos.
 */
const props = defineProps({
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: 'md' },
    block: { type: Boolean, default: false },
    href: { type: String, default: null },
    inertia: { type: Boolean, default: false },
    external: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const classes = computed(() => buttonClasses(props));
const inert = computed(() => props.disabled || props.loading);
</script>

<template>
    <Link v-if="href && inertia && !inert" :href="href" :class="classes"><slot /></Link>
    <a
        v-else-if="href"
        :href="inert ? undefined : href"
        :class="classes"
        :target="external ? '_blank' : undefined"
        :rel="external ? 'noopener noreferrer' : undefined"
        :aria-disabled="inert ? 'true' : undefined"
        :aria-busy="loading ? 'true' : undefined"
        :role="inert ? 'link' : undefined"
    >
        <slot />
    </a>
    <button
        v-else
        :type="type"
        :class="classes"
        :disabled="inert"
        :aria-busy="loading ? 'true' : undefined"
        :aria-pressed="variant === 'chip' ? String(selected) : undefined"
    >
        <slot />
    </button>
</template>
