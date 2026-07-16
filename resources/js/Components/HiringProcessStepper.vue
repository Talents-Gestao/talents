<script setup>
/**
 * Stepper horizontal das fases de acompanhamento de contratação.
 * currentStage: valor ativo (tab selecionada).
 * stageCounts: mapa value → contagem (opcional).
 * interactive: emite update:currentStage ao clicar.
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
    /** Se definido, destaca o progresso até esta fase (processo individual). */
    progressStage: {
        type: String,
        default: null,
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

const onClick = (stage, interactive) => {
    if (!interactive) {
        return;
    }
    emit('update:currentStage', stage.value);
};
</script>

<template>
    <div class="w-full overflow-x-auto">
        <nav
            class="flex min-w-[720px] items-stretch gap-0 border-b border-talents-200"
            aria-label="Fases do acompanhamento"
        >
            <button
                v-for="(stage, index) in stages"
                :key="stage.value"
                type="button"
                :disabled="!interactive"
                class="group relative flex min-w-0 flex-1 flex-col items-center gap-1.5 px-2 pb-3 pt-2 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 focus-visible:ring-offset-2"
                :class="[
                    interactive ? 'cursor-pointer' : 'cursor-default',
                    currentStage === stage.value ? 'text-talents-700' : 'text-slate-500 hover:text-talents-700',
                ]"
                @click="onClick(stage, interactive)"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition"
                    :class="[
                        currentStage === stage.value
                            ? 'bg-talents-600 text-white shadow-sm'
                            : progressStage && isReached(stage, progressStage, stages)
                              ? 'bg-talents-100 text-talents-800 ring-1 ring-talents-300'
                              : 'bg-slate-100 text-slate-600',
                    ]"
                >
                    {{ index + 1 }}
                </span>
                <span class="line-clamp-2 text-[11px] font-medium leading-tight sm:text-xs">
                    {{ stage.label }}
                    <span class="tabular-nums text-slate-400">({{ countFor(stage, stageCounts) }})</span>
                </span>
                <span
                    v-if="currentStage === stage.value"
                    class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-talents-600"
                />
            </button>
        </nav>
    </div>
</template>
