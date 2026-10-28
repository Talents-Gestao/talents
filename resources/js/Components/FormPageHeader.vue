<script setup>
import BackLink from '@/Components/BackLink.vue';

defineProps({
    backHref: { type: String, required: true },
    backLabel: { type: String, default: 'Voltar' },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    /** Em create/edit, false = voltar pela hierarquia (backHref), não pelo histórico. */
    preferHistory: { type: Boolean, default: false },
    /** Ações em linha própria, para o título não ser espremido quando a barra lateral abre. */
    stackActions: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="flex flex-col gap-3"
        :class="stackActions ? '' : 'sm:flex-row sm:items-start sm:justify-between'"
    >
        <div class="min-w-0">
            <BackLink :href="backHref" :label="backLabel" :prefer-history="preferHistory" />
            <h2 class="mt-2 text-balance text-xl font-semibold leading-tight text-slate-900">{{ title }}</h2>
            <p v-if="subtitle" class="mt-1 text-pretty text-sm text-slate-600">{{ subtitle }}</p>
        </div>
        <div
            v-if="$slots.trailing"
            class="flex flex-wrap items-center gap-2"
            :class="stackActions ? '' : 'shrink-0'"
        >
            <slot name="trailing" />
        </div>
    </div>
</template>
