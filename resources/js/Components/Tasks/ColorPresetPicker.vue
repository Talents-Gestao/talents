<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import { TASK_COLOR_PRESETS } from '@/utils/taskColorPresets';
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Cor' },
    allowEmpty: { type: Boolean, default: true },
    hint: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed({
    get: () => props.modelValue || '',
    set: (value) => emit('update:modelValue', value || ''),
});

function isSelected(color) {
    const current = (selected.value || '').toLowerCase();
    const next = (color || '').toLowerCase();
    if (!next) {
        return !current;
    }
    return current === next;
}

function pick(color) {
    selected.value = color || '';
}
</script>

<template>
    <div>
        <InputLabel v-if="label" :value="label" />
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <button
                v-if="allowEmpty"
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-full border border-dashed border-slate-300 text-xs font-medium text-slate-500 hover:border-slate-400 hover:text-slate-700"
                :class="{ 'ring-2 ring-talents-500 ring-offset-1': isSelected('') }"
                title="Sem cor"
                @click="pick('')"
            >
                ×
            </button>
            <button
                v-for="preset in TASK_COLOR_PRESETS"
                :key="preset.value"
                type="button"
                class="h-8 w-8 rounded-full border border-white shadow ring-1 ring-slate-200 transition hover:scale-110"
                :class="{ 'ring-2 ring-talents-500 ring-offset-1': isSelected(preset.value) }"
                :style="{ backgroundColor: preset.value }"
                :title="preset.label"
                @click="pick(preset.value)"
            />
        </div>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
        <p v-else-if="selected" class="mt-1 text-xs text-slate-400">{{ selected }}</p>
    </div>
</template>
