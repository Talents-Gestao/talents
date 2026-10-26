<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    actions: { type: Array, default: () => [] },
    planUrl: { type: String, default: null },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="nr1-next-actions-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="nr1-next-actions-title" class="text-base font-semibold text-talents-900">Próximas ações</h2>
            <Link v-if="planUrl" :href="planUrl" class="text-sm text-talents-700 hover:underline">Ver plano completo →</Link>
        </div>

        <p v-if="!actions.length" class="mt-4 text-sm text-gray-600">Ainda não há medidas no plano de ação desta campanha.</p>

        <ul v-else class="mt-4 space-y-3">
            <li v-for="action in actions" :key="action.id" class="rounded-lg border border-gray-100 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900">{{ action.factor }}</p>
                    <span
                        class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold"
                        :class="action.confirmed ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'"
                    >
                        {{ action.chip }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-700">{{ action.description }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ action.status_label }}
                    <span v-if="action.responsible"> · {{ action.responsible }}</span>
                    <span v-if="action.due_date"> · prazo {{ action.due_date }}</span>
                </p>
            </li>
        </ul>
    </section>
</template>
