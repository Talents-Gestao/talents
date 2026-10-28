<script setup>
defineProps({
    participation: { type: Object, required: true },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="participation-title">
        <h2 id="participation-title" class="text-base font-semibold text-talents-900">Participação</h2>
        <p v-if="participation.completed === 0" class="mt-3 text-sm text-gray-600">Ainda não há respostas concluídas.</p>
        <div v-else class="mt-3">
            <p class="text-3xl font-bold text-talents-900">{{ participation.percent_label ?? '—' }}</p>
            <p class="text-sm text-gray-700">
                {{ participation.completed }} {{ participation.completed === 1 ? 'resposta' : 'respostas' }}
                <span v-if="participation.eligible"> · {{ participation.eligible }} elegíveis</span>
            </p>
            <p v-if="!participation.eligible" class="mt-1 text-xs text-gray-500">Total de elegíveis ainda não informado pela Talents.</p>
            <p v-else-if="participation.eligible_source" class="mt-1 text-xs text-gray-500">Fonte: {{ participation.eligible_source }}</p>
        </div>
    </section>
</template>
