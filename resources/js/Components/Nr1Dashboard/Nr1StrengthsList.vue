<script setup>
defineProps({
    strengths: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-strengths-title">
        <h2 id="nr1-strengths-title" class="text-base font-semibold text-talents-900">Pontos fortes da pesquisa</h2>
        <p class="mt-1 text-xs text-gray-500">
            Percentual de respostas favoráveis: “Frequentemente” ou “Sempre” nos enunciados positivos; “Nunca” ou
            “Raramente” nos enunciados desfavoráveis. O N indica a base de cada pergunta.
        </p>

        <p v-if="!strengths.length" class="mt-4 text-sm text-gray-600">Ainda não há respostas para destacar pontos fortes.</p>

        <ol v-else class="mt-4 space-y-3">
            <li v-for="item in strengths" :key="item.id" class="rounded-lg border border-gray-100 p-3">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm text-gray-900">{{ item.body }}</p>
                    <p class="shrink-0 text-right text-sm">
                        <span class="font-bold text-talents-900">{{ item.favorable_pct_label }}</span>
                        <span class="block text-xs text-gray-500">N = {{ item.n }}</span>
                    </p>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ item.section_title }}</p>
                <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100" aria-hidden="true">
                    <div class="h-1.5 rounded-full bg-emerald-500" :style="{ width: `${item.favorable_pct}%` }" />
                </div>
            </li>
        </ol>
    </section>
</template>
