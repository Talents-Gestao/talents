<script setup>
import { QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import { onBeforeUnmount, ref } from 'vue';

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

const trigger = ref(null);
const open = ref(false);
const position = ref({ top: '0px', left: '0px' });

const TOOLTIP_WIDTH = 256;
const VIEWPORT_GAP = 12;

const place = () => {
    const element = trigger.value;
    if (!element) {
        return;
    }

    const rect = element.getBoundingClientRect();
    const maxLeft = window.innerWidth - TOOLTIP_WIDTH - VIEWPORT_GAP;
    const left = Math.min(Math.max(rect.left, VIEWPORT_GAP), Math.max(VIEWPORT_GAP, maxLeft));

    position.value = {
        top: `${rect.bottom + 6}px`,
        left: `${left}px`,
    };
};

const onMove = () => {
    if (open.value) {
        place();
    }
};

const show = () => {
    place();
    open.value = true;
    window.addEventListener('scroll', onMove, true);
    window.addEventListener('resize', onMove);
};

const hide = () => {
    open.value = false;
    window.removeEventListener('scroll', onMove, true);
    window.removeEventListener('resize', onMove);
};

onBeforeUnmount(hide);
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="inline-flex shrink-0 rounded-full p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-400"
        :class="tone === 'onDark' ? 'text-white/75 hover:text-white' : 'text-slate-400 hover:text-talents-700'"
        :aria-label="text"
        @click.stop
        @mouseenter="show"
        @mouseleave="hide"
        @focus="show"
        @blur="hide"
    >
        <QuestionMarkCircleIcon class="h-4 w-4" aria-hidden="true" />
    </button>
    <Teleport to="body">
        <span
            v-if="open"
            role="tooltip"
            class="pointer-events-none fixed z-[80] w-64 rounded-lg px-3 py-2 text-left text-xs font-normal leading-snug normal-case tracking-normal shadow-lg"
            :class="tone === 'onDark' ? 'bg-white text-slate-800' : 'bg-slate-900 text-white'"
            :style="position"
        >
            {{ text }}
        </span>
    </Teleport>
</template>
