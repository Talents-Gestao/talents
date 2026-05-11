<script setup>
import { computed } from 'vue';
import ProgressBar from './ProgressBar.vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    /** key for value used in bar width */
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'name' },
    displayValueKey: { type: String, default: 'display_value' },
});

const maxVal = computed(() => {
    const vals = (props.items || []).map((i) => Number(i[props.valueKey]) || 0);
    return Math.max(1, ...vals);
});
</script>

<template>
    <ol class="space-y-3">
        <li v-for="(item, idx) in items" :key="item.id ?? idx" class="flex gap-3">
            <span class="w-6 shrink-0 pt-0.5 text-right text-xs font-bold tabular-nums text-slate-400">{{ idx + 1 }}</span>
            <div class="min-w-0 flex-1">
                <ProgressBar
                    :label="item[labelKey]"
                    :value="maxVal ? (100 * (Number(item[valueKey]) || 0)) / maxVal : 0"
                    :display-value="item[displayValueKey] ?? String(item[valueKey] ?? '')"
                    bar-class="bg-talents-600"
                />
            </div>
        </li>
    </ol>
</template>
