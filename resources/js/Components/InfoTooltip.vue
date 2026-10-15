<script setup>
import { QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';

defineProps({
    text: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'muted',
        validator: (value) => ['muted', 'onDark'].includes(value),
    },
});
</script>

<template>
    <button
        type="button"
        class="group/info relative inline-flex shrink-0 rounded-full p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-400"
        :class="tone === 'onDark' ? 'text-white/75 hover:text-white' : 'text-slate-400 hover:text-talents-700'"
        :aria-label="text"
        @click.stop
    >
        <QuestionMarkCircleIcon class="h-4 w-4" aria-hidden="true" />
        <span
            role="tooltip"
            class="pointer-events-none absolute left-1/2 top-full z-[90] mt-1.5 w-64 -translate-x-1/2 rounded-lg px-3 py-2 text-left text-xs font-normal leading-snug normal-case tracking-normal opacity-0 shadow-lg transition-opacity group-hover/info:opacity-100 group-focus-within/info:opacity-100"
            :class="tone === 'onDark' ? 'bg-white text-slate-800' : 'bg-slate-900 text-white'"
        >
            {{ text }}
        </span>
    </button>
</template>
