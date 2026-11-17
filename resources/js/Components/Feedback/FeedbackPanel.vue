<script setup>
/**
 * Painel de Feedbacks: cartão com título, ajuda curta e ações no cabeçalho.
 * Só apresentação; o conteúdo vem no slot padrão.
 */
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    /** Sem padding no corpo (listas com divisórias, acordeões). */
    flush: { type: Boolean, default: false },
    as: { type: String, default: 'section' },
});
</script>

<template>
    <component :is="as" class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <header
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6"
        >
            <div class="min-w-0">
                <h3 v-if="title" class="text-base font-semibold text-slate-900">{{ title }}</h3>
                <p v-if="description" class="mt-0.5 text-pretty text-sm text-slate-600">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="flush ? '' : 'p-5 sm:p-6'">
            <slot />
        </div>
        <footer v-if="$slots.footer" class="border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:px-6">
            <slot name="footer" />
        </footer>
    </component>
</template>
