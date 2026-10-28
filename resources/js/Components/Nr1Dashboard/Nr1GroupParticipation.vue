<script setup>
defineProps({
    groups: { type: Object, required: true },
    respondents: { type: Number, default: 0 },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-groups-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="nr1-groups-title" class="text-base font-semibold text-talents-900">Participação por grupo</h2>
            <p class="text-xs text-gray-500">{{ respondents }} {{ respondents === 1 ? 'resposta' : 'respostas' }} no total</p>
        </div>
        <p class="mt-1 text-xs text-gray-500">As cores diferenciam grupos, não níveis de risco.</p>

        <p v-if="!groups.items.length" class="mt-4 text-sm text-gray-600">
            Nenhum grupo pode ser exibido separadamente nesta campanha.
        </p>

        <ul v-else class="mt-4 space-y-3">
            <li v-for="group in groups.items" :key="`${group.kind}-${group.name}`">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-medium text-gray-900">{{ group.name }}</span>
                    <span class="text-gray-700">
                        {{ group.n }} {{ group.n === 1 ? 'resposta' : 'respostas' }}
                        <span v-if="group.percent_label"> · {{ group.percent_label }}</span>
                    </span>
                </div>
                <div
                    class="mt-1.5 h-2 w-full rounded-full bg-gray-100"
                    role="img"
                    :aria-label="`${group.name}: ${group.n} respostas${group.percent_label ? ', ' + group.percent_label : ''}`"
                >
                    <div
                        class="h-2 rounded-full"
                        :class="group.kind === 'others' ? 'bg-slate-500' : 'bg-sky-600'"
                        :style="{ width: `${respondents > 0 ? Math.min(100, (group.n / respondents) * 100) : 0}%` }"
                    />
                </div>
                <p v-if="group.composition" class="mt-1 text-xs text-gray-600">
                    Reúne: {{ group.composition.join(', ') }}.
                </p>
            </li>
        </ul>

        <p v-if="groups.note" class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">{{ groups.note }}</p>
    </section>
</template>
