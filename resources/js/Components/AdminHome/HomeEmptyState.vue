<script setup>
/**
 * Estado vazio da Home do admin: ilustração SVG própria (traço na cor do domínio do cartão, via
 * `currentColor` = `--home-a`) + os textos que a tela já usava. Não substitui o EmptyState
 * compartilhado com o painel do cliente.
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    /** 'tasks' | 'calendar' | 'leads' */
    variant: { type: String, default: 'tasks' },
});
</script>

<template>
    <div class="home-empty">
        <svg class="home-empty__art" viewBox="0 0 96 64" fill="none" aria-hidden="true">
            <template v-if="variant === 'calendar'">
                <rect x="22" y="10" width="52" height="46" rx="8" stroke="currentColor" stroke-width="2" opacity="0.55" />
                <path d="M22 22h52" stroke="currentColor" stroke-width="2" opacity="0.55" />
                <path d="M34 6v8M62 6v8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                <g fill="currentColor" opacity="0.3">
                    <circle cx="34" cy="32" r="2.2" />
                    <circle cx="48" cy="32" r="2.2" />
                    <circle cx="62" cy="32" r="2.2" />
                    <circle cx="34" cy="44" r="2.2" />
                    <circle cx="62" cy="44" r="2.2" />
                </g>
                <circle cx="48" cy="44" r="5" fill="currentColor" opacity="0.18" />
                <circle cx="48" cy="44" r="2.6" fill="currentColor" />
            </template>
            <template v-else-if="variant === 'leads'">
                <circle cx="48" cy="32" r="21" stroke="currentColor" stroke-width="6" opacity="0.14" />
                <path d="M48 11a21 21 0 0 1 20 14.5" stroke="currentColor" stroke-width="6" stroke-linecap="round" opacity="0.6" />
                <circle cx="48" cy="32" r="3" fill="currentColor" opacity="0.5" />
            </template>
            <template v-else>
                <rect x="24" y="8" width="48" height="50" rx="8" stroke="currentColor" stroke-width="2" opacity="0.55" />
                <g stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m33 22 3 3 5-6" />
                    <path d="M47 22h16" opacity="0.4" />
                    <path d="m33 34 3 3 5-6" opacity="0.6" />
                    <path d="M47 34h12" opacity="0.4" />
                </g>
                <rect x="32" y="43" width="9" height="7" rx="2" stroke="currentColor" stroke-width="2" opacity="0.35" />
                <path d="M47 46.5h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.25" />
            </template>
        </svg>
        <p class="home-empty__title">{{ title }}</p>
        <p v-if="description" class="home-empty__text">{{ description }}</p>
    </div>
</template>
