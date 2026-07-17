<script setup>
/**
 * Stepper horizontal das fases de acompanhamento de contratação.
 */
import { computed } from 'vue';

const props = defineProps({
    stages: {
        type: Array,
        required: true,
    },
    currentStage: {
        type: String,
        required: true,
    },
    stageCounts: {
        type: Object,
        default: () => ({}),
    },
    interactive: {
        type: Boolean,
        default: true,
    },
    progressStage: {
        type: String,
        default: null,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:currentStage']);

const trackMinWidth = computed(() => {
    const perStep = props.compact ? 112 : 148;
    const connectors = Math.max(props.stages.length - 1, 0) * (props.compact ? 12 : 20);
    return `${props.stages.length * perStep + connectors}px`;
});

const countFor = (stage, stageCounts) => {
    const n = stageCounts?.[stage.value];
    return typeof n === 'number' ? n : 0;
};

const isReached = (stage, progressStage, stages) => {
    if (!progressStage) {
        return false;
    }
    const progress = stages.find((s) => s.value === progressStage);
    if (!progress) {
        return false;
    }
    return stage.order <= progress.order;
};

const isPast = (stage, progressStage, stages) => {
    if (!progressStage) {
        return false;
    }
    const progress = stages.find((s) => s.value === progressStage);
    if (!progress) {
        return false;
    }
    return stage.order < progress.order;
};

const onClick = (stage, interactive) => {
    if (!interactive) {
        return;
    }
    emit('update:currentStage', stage.value);
};
</script>

<template>
    <div class="w-full overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:thin]">
        <nav
            class="flex items-start"
            :class="compact ? 'gap-1 px-1' : 'gap-1.5 px-1 pt-2 sm:gap-2 sm:px-2'"
            :style="{ minWidth: trackMinWidth }"
            aria-label="Fases do acompanhamento"
        >
            <template v-for="(stage, index) in stages" :key="stage.value">
                <button
                    type="button"
                    :disabled="!interactive"
                    class="group relative flex flex-col items-center text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    :class="[
                        interactive ? 'cursor-pointer' : 'cursor-default',
                        compact
                            ? 'min-w-[6.5rem] max-w-[7.5rem] flex-1 gap-1.5 px-1.5 pb-2.5 pt-1'
                            : 'min-w-[8.5rem] max-w-[10.5rem] flex-1 gap-2.5 px-2 pb-4 pt-2 sm:min-w-[9rem]',
                    ]"
                    @click="onClick(stage, interactive)"
                >
                    <span
                        class="relative z-[1] flex shrink-0 items-center justify-center rounded-full font-semibold transition duration-200"
                        :class="[
                            compact ? 'h-7 w-7 text-[11px]' : 'h-10 w-10 text-sm',
                            currentStage === stage.value
                                ? 'bg-talents-600 text-white shadow-md shadow-talents-600/25 ring-4 ring-talents-100'
                                : progressStage && isPast(stage, progressStage, stages)
                                  ? 'bg-talents-500 text-white'
                                  : progressStage && isReached(stage, progressStage, stages)
                                    ? 'bg-talents-100 text-talents-800 ring-2 ring-talents-300'
                                    : interactive
                                      ? 'bg-white text-slate-500 ring-1 ring-slate-200 group-hover:ring-talents-300 group-hover:text-talents-700'
                                      : 'bg-slate-100 text-slate-400 ring-1 ring-slate-200',
                        ]"
                    >
                        <svg
                            v-if="progressStage && isPast(stage, progressStage, stages) && currentStage !== stage.value"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <template v-else>{{ index + 1 }}</template>
                    </span>

                    <span
                        class="line-clamp-3 px-0.5 font-medium leading-snug"
                        :class="[
                            compact ? 'text-[10px]' : 'text-[11px] sm:text-xs',
                            currentStage === stage.value ? 'text-talents-800' : 'text-slate-500 group-hover:text-talents-700',
                        ]"
                    >
                        {{ stage.label }}
                    </span>

                    <span
                        v-if="!compact"
                        class="mt-0.5 inline-flex min-w-[1.75rem] items-center justify-center rounded-full px-2 py-0.5 text-[10px] font-semibold tabular-nums"
                        :class="
                            currentStage === stage.value
                                ? 'bg-talents-100 text-talents-800'
                                : 'bg-slate-100 text-slate-500'
                        "
                    >
                        {{ countFor(stage, stageCounts) }}
                    </span>

                    <span
                        v-if="currentStage === stage.value"
                        class="absolute inset-x-4 bottom-0 h-0.5 rounded-full bg-talents-600"
                    />
                </button>

                <div
                    v-if="index < stages.length - 1"
                    class="hidden h-0.5 shrink-0 self-start rounded-full sm:block"
                    :class="[
                        compact ? 'mt-[0.85rem] w-3' : 'mt-[1.6rem] w-4 sm:w-5',
                        progressStage && isPast(stages[index], progressStage, stages)
                            ? 'bg-talents-400'
                            : 'bg-slate-200',
                    ]"
                    aria-hidden="true"
                />
            </template>
        </nav>
    </div>
</template>
