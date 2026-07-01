<script setup>
import { computed } from 'vue';
import { kindTheme } from '@/utils/strategicCalendarThemes';

const props = defineProps({
    kind: { type: String, required: true },
    label: { type: String, required: true },
    compact: { type: Boolean, default: false },
});

const theme = computed(() => kindTheme(props.kind));
</script>

<template>
    <span
        class="inline-flex max-w-full items-center gap-1.5 text-slate-700"
        :class="compact ? 'text-xs' : 'text-sm'"
    >
        <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: theme.color }" aria-hidden="true" />
        <span class="min-w-0 truncate font-medium">{{ label }}</span>
    </span>
</template>
