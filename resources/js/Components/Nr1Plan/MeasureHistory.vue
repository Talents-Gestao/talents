<script setup>
defineProps({
    history: { type: Array, default: () => [] },
});
</script>

<template>
    <details class="mt-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm">
        <summary class="cursor-pointer font-medium text-gray-800">Histórico ({{ history.length }})</summary>
        <p v-if="!history.length" class="mt-2 text-gray-600">Sem alterações registradas.</p>
        <ol v-else class="mt-2 space-y-2">
            <li v-for="event in history" :key="event.id" class="border-l-2 border-gray-300 pl-3">
                <p class="text-gray-900">
                    <span class="font-medium">{{ event.field_label }}</span>
                    <template v-if="event.field !== 'created'">
                        : <span class="text-gray-500">{{ event.old ?? '—' }}</span> → <span>{{ event.new ?? '—' }}</span>
                    </template>
                    <template v-else> ({{ event.new }})</template>
                </p>
                <p class="text-xs text-gray-500">{{ event.author }} · {{ event.at }}</p>
                <p v-if="event.reason" class="text-xs text-gray-700">Justificativa: {{ event.reason }}</p>
            </li>
        </ol>
    </details>
</template>
