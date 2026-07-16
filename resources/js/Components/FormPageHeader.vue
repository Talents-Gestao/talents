<script setup>
import BackLink from '@/Components/BackLink.vue';

defineProps({
    backHref: { type: String, required: true },
    backLabel: { type: String, default: 'Voltar' },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <BackLink :href="backHref" :label="backLabel" />
            <h2 class="mt-2 text-xl font-semibold leading-tight text-slate-900">{{ title }}</h2>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-600">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.trailing" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="trailing" />
        </div>
    </div>
</template>
