<script setup>
import { computed } from 'vue';
import { surveyStatusBadgeClass, surveyStatusLabel } from '@/utils/surveyStatus';

const props = defineProps({
    status: { type: String, default: null },
});

const label = computed(() => surveyStatusLabel(props.status));
const badgeClass = computed(() => surveyStatusBadgeClass(props.status));
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
        :class="badgeClass"
    >
        {{ label }}
    </span>
</template>
