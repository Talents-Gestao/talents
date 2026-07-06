<script setup>
import { computed } from 'vue';
import { feedbackStatusBadgeClass, feedbackStatusLabel } from '@/utils/feedbackStatus';

const props = defineProps({
    status: { type: String, default: null },
    label: { type: String, default: '' },
});

const text = computed(() => props.label || feedbackStatusLabel(props.status));
const badgeClass = computed(() => feedbackStatusBadgeClass(props.status));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
        :class="badgeClass"
    >
        {{ text }}
    </span>
</template>
