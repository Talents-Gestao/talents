<script setup>
import { bandBadgeClass } from '@/utils/nr1Bands';

defineProps({
    cards: { type: Object, required: true },
    bandDisclaimer: { type: String, default: '' },
});
</script>

<template>
    <section aria-label="Resumo da pesquisa" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Índice geral da pesquisa</h2>
            <template v-if="cards.overall">
                <p class="mt-2 text-3xl font-bold text-talents-900">
                    {{ cards.overall.score_label }}<span class="ml-1 text-base font-medium text-gray-500">/ 5</span>
                </p>
                <span
                    class="mt-2 inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold"
                    :class="bandBadgeClass(cards.overall.risk_level)"
                >
                    {{ cards.overall.band_label }}
                </span>
                <p v-if="bandDisclaimer" class="mt-2 text-xs text-gray-500">{{ bandDisclaimer }}</p>
            </template>
            <template v-else>
                <p class="mt-2 text-3xl font-bold text-gray-400">—</p>
                <p class="mt-2 text-sm text-gray-500">Ainda não há respostas calculadas.</p>
            </template>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Participação</h2>
            <p class="mt-2 text-3xl font-bold text-talents-900">{{ cards.participation.percent_label ?? '—' }}</p>
            <p class="mt-2 text-sm text-gray-700">
                {{ cards.participation.respondents }}
                {{ cards.participation.respondents === 1 ? 'resposta' : 'respostas' }}
                <template v-if="cards.participation.eligible !== null"> · {{ cards.participation.eligible }} elegíveis</template>
            </p>
            <p v-if="cards.participation.status === 'missing'" class="mt-1 text-xs text-gray-500">
                Total de elegíveis não informado.
            </p>
            <p v-else-if="cards.participation.status === 'inconsistent'" class="mt-1 text-xs text-red-700">
                Total de elegíveis menor que o de respostas. Revise o cadastro.
            </p>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dimensões em atenção</h2>
            <p class="mt-2 text-3xl font-bold text-talents-900">
                {{ cards.attention.count ?? '—' }}<span v-if="cards.attention.count !== null" class="ml-1 text-base font-medium text-gray-500">/ {{ cards.attention.total }}</span>
            </p>
            <p class="mt-2 text-sm text-gray-600">Todas aparecem na lista de prioridades.</p>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Plano de ação</h2>
            <p class="mt-2 text-3xl font-bold text-talents-900">
                {{ cards.plan.confirmed }}<span class="ml-1 text-base font-medium text-gray-500">/ {{ cards.plan.total }}</span>
            </p>
            <p class="mt-2 text-sm text-gray-600">
                {{ cards.plan.total === 0 ? 'Nenhuma medida cadastrada ainda.' : 'Medidas com responsável confirmado.' }}
            </p>
            <p v-if="cards.plan.total > 0" class="mt-1 text-xs text-gray-500">{{ cards.plan.implemented }} concluídas.</p>
        </article>
    </section>
</template>
