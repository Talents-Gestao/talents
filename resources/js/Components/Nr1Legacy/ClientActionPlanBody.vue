<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    survey: Object,
    plan: { type: Object, default: null },
    actionPlanLocked: { type: Boolean, default: true },
});
</script>

<template>
    <div
        v-if="actionPlanLocked"
        class="relative overflow-hidden rounded-2xl border-2 border-talents-200 bg-gradient-to-br from-talents-50/90 via-white to-amber-50/40 p-8 shadow-md"
    >
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-talents-100/30 via-transparent to-transparent" />
        <div class="relative mx-auto max-w-lg text-center">
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-talents-200 bg-white/90 text-talents-800 shadow-sm"
                aria-hidden="true"
            >
                <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.75"
                        d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                    />
                </svg>
            </div>
            <h3 class="mt-6 text-lg font-semibold text-talents-900">Parecer ainda não publicado</h3>
            <p class="mt-3 text-sm leading-relaxed text-gray-600">
                A Talents ainda não publicou o parecer desta pesquisa. Quando isso acontecer, o texto fica disponível nesta página.
            </p>
        </div>
    </div>

    <div v-else class="space-y-6">
        <div
            v-if="plan.technical_opinion || plan.technical_opinion_file_url"
            class="surface-card overflow-hidden p-6"
        >
            <h3 class="text-lg font-semibold text-talents-900">Parecer técnico</h3>
            <p class="mt-1 text-xs text-gray-500">Elaborado pela equipe Talents com base nos resultados desta pesquisa.</p>
            <div
                v-if="plan.technical_opinion"
                class="parecer-prose prose prose-sm mt-4 max-w-none text-gray-800 prose-headings:text-talents-900 prose-strong:text-talents-900"
                v-html="plan.technical_opinion"
            />
            <a
                v-if="plan.technical_opinion_file_url"
                :href="plan.technical_opinion_file_url"
                class="mt-4 inline-flex items-center gap-2 rounded-lg border border-talents-200 bg-talents-50 px-4 py-2.5 text-sm font-medium text-talents-800 transition hover:bg-talents-100"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.75"
                        d="M19.5 14.25v2.625a2.625 2.625 0 01-2.625 2.625H7.125A2.625 2.625 0 014.5 16.875V14.25M12 3v12m0 0l-3.75-3.75M12 15l3.75-3.75"
                    />
                </svg>
                Baixar parecer técnico
                <span v-if="plan.technical_opinion_file_name" class="text-xs font-normal text-talents-600">
                    ({{ plan.technical_opinion_file_name }})
                </span>
            </a>
        </div>
    </div>
</template>


<style scoped>
.parecer-prose :deep(p) {
    margin: 0 0 1.15em;
    line-height: 1.7;
}
.parecer-prose :deep(h2) {
    margin-top: 1.85em;
    margin-bottom: 0.75em;
    font-weight: 700;
}
.parecer-prose :deep(h2:first-child) {
    margin-top: 0;
}
.parecer-prose :deep(h3) {
    margin-top: 1.5em;
    margin-bottom: 0.65em;
    font-weight: 700;
}
.parecer-prose :deep(p + p) {
    margin-top: 0;
}
.parecer-prose :deep(ul),
.parecer-prose :deep(ol) {
    margin-top: 0.5em;
    margin-bottom: 1.15em;
}
.parecer-prose :deep(br) {
    display: block;
    margin-bottom: 0.9em;
    content: '';
}
</style>
