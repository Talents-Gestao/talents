<script setup>
import { computed } from 'vue';

const props = defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    accent: {
        type: String,
        default: 'talents',
        validator: (value) => ['slate', 'talents', 'teal', 'amber'].includes(value),
    },
});

const accents = {
    slate: {
        card: 'border-slate-200',
        bar: 'bg-gradient-to-b from-slate-600 to-slate-400',
        header: 'bg-gradient-to-br from-slate-50 via-white to-white',
        icon: 'bg-slate-200/80 text-slate-700',
        eyebrow: 'text-slate-600',
    },
    talents: {
        card: 'border-talents-200/80',
        bar: 'bg-gradient-to-b from-talents-700 to-talents-400',
        header: 'bg-gradient-to-br from-talents-50 via-white to-white',
        icon: 'bg-talents-100 text-talents-700',
        eyebrow: 'text-talents-700',
    },
    teal: {
        card: 'border-teal-200/80',
        bar: 'bg-gradient-to-b from-teal-600 to-teal-400',
        header: 'bg-gradient-to-br from-teal-50 via-white to-white',
        icon: 'bg-teal-100 text-teal-700',
        eyebrow: 'text-teal-700',
    },
    amber: {
        card: 'border-amber-200/80',
        bar: 'bg-gradient-to-b from-amber-500 to-yellow-400',
        header: 'bg-gradient-to-br from-amber-50 via-white to-white',
        icon: 'bg-amber-100 text-amber-800',
        eyebrow: 'text-amber-800',
    },
};

const theme = computed(() => accents[props.accent] ?? accents.talents);
</script>

<template>
    <div class="relative overflow-hidden rounded-2xl border bg-white shadow-sm" :class="theme.card">
        <div class="absolute inset-y-0 left-0 w-1.5" :class="theme.bar" aria-hidden="true" />
        <div class="pl-1.5">
            <div class="px-6 py-5 sm:px-7" :class="theme.header">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div
                            v-if="$slots.icon"
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                            :class="theme.icon"
                        >
                            <slot name="icon" />
                        </div>
                        <div class="min-w-0">
                            <p
                                v-if="eyebrow"
                                class="text-[11px] font-semibold uppercase tracking-wider"
                                :class="theme.eyebrow"
                            >
                                {{ eyebrow }}
                            </p>
                            <h2 class="mt-0.5 text-xl font-bold tracking-tight text-slate-900">{{ title }}</h2>
                            <p
                                v-if="description"
                                class="mt-1 max-w-xl text-sm leading-relaxed text-slate-600"
                            >
                                {{ description }}
                            </p>
                            <div v-if="$slots.badges" class="mt-3 flex flex-wrap gap-2">
                                <slot name="badges" />
                            </div>
                        </div>
                    </div>
                    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                        <slot name="actions" />
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-100 px-6 py-6 sm:px-7">
                <slot />
            </div>
        </div>
    </div>
</template>
