<script setup>
/**
 * Stepper horizontal das fases de acompanhamento de contratação.
 */
defineProps({
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
    <div class="w-full overflow-x-auto pb-1">
        <nav
            class="flex min-w-[760px] items-start"
            :class="compact ? 'gap-0 px-1' : 'gap-0 px-2 pt-1'"
            aria-label="Fases do acompanhamento"
        >
            <template v-for="(stage, index) in stages" :key="stage.value">
                <button
                    type="button"
                    :disabled="!interactive"
                    class="group relative flex min-w-0 flex-1 flex-col items-center text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                    :class="[
                        interactive ? 'cursor-pointer' : 'cursor-default',
                        compact ? 'gap-1 px-1 pb-2 pt-1' : 'gap-2 px-1.5 pb-3 pt-2',
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
                        class="line-clamp-2 font-medium leading-snug"
                        :class="[
                            compact ? 'text-[10px]' : 'text-[11px] sm:text-xs',
                            currentStage === stage.value ? 'text-talents-800' : 'text-slate-500 group-hover:text-talents-700',
                        ]"
                    >
                        {{ stage.label }}
                    </span>

                    <span
                        v-if="!compact"
                        class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
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
                        class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-talents-600"
                    />
                </button>

                <div
                    v-if="index < stages.length - 1"
                    class="mt-[1.35rem] hidden h-0.5 w-3 shrink-0 self-start rounded-full sm:block sm:w-4"
                    :class="[
                        compact ? 'mt-[0.85rem]' : 'sm:mt-[1.6rem]',
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
