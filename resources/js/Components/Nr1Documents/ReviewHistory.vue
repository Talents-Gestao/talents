<script setup>
defineProps({
    history: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="mt-5 border-t border-gray-100 pt-4" aria-labelledby="history-title">
        <h4 id="history-title" class="text-sm font-semibold text-gray-900">Histórico</h4>
        <p v-if="!history.length" class="mt-2 text-sm text-gray-600">Sem registros ainda.</p>
        <ol v-else class="mt-2 space-y-3">
            <li v-for="event in [...history].reverse()" :key="event.id" class="border-l-2 border-gray-300 pl-3 text-sm">
                <p class="font-medium text-gray-900">
                    {{ event.label }}
                    <span v-if="event.to && event.from !== event.to" class="font-normal text-gray-600"> · {{ event.from ?? '—' }} → {{ event.to }}</span>
                </p>
                <p class="text-xs text-gray-500">{{ event.author }} · {{ event.at }}</p>
                <p v-if="event.body" class="mt-0.5 text-xs text-gray-700">{{ event.body }}</p>
            </li>
        </ol>
    </section>
</template>
