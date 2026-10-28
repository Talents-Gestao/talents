<script setup>
import { bandBadgeClass } from '@/utils/nr1Bands';

defineProps({
    attention: { type: Array, default: () => [] },
    hasResults: { type: Boolean, default: false },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-attention-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="nr1-attention-title" class="text-base font-semibold text-talents-900">O que merece atenção</h2>
            <p v-if="attention.length" class="text-xs text-gray-500">
                {{ attention.length }} {{ attention.length === 1 ? 'prioridade' : 'prioridades' }}, da maior para a menor
            </p>
        </div>

        <p v-if="!hasResults" class="mt-4 text-sm text-gray-600">As prioridades aparecem depois que as respostas forem calculadas.</p>
        <p v-else-if="!attention.length" class="mt-4 text-sm text-gray-600">Nenhuma dimensão ficou em atenção nesta pesquisa.</p>

        <ol v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="(dimension, index) in attention" :key="dimension.section_id" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                <span class="flex items-center gap-3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">{{ index + 1 }}</span>
                    <span class="font-medium text-gray-900">{{ dimension.title }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="font-semibold text-talents-900">{{ dimension.score_label }}</span>
                    <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandBadgeClass(dimension.risk_level)">
                        {{ dimension.band_label }}
                    </span>
                </span>
            </li>
        </ol>
    </section>
</template>
