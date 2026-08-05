<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Título do hero — tipografia da 1ª versão (bold, sem uppercase forçado);
 * só muda o texto + typing em loop. Altura estável via fantasma.
 */
const TITLE = 'O resultado que você espera está nas pessoas!';

const props = defineProps({
    typeSpeed: {
        type: Number,
        default: 60,
    },
    deleteSpeed: {
        type: Number,
        default: 28,
    },
    holdMs: {
        type: Number,
        default: 2600,
    },
    pauseMs: {
        type: Number,
        default: 450,
    },
    startDelayMs: {
        type: Number,
        default: 500,
    },
});

/** Classes tipográficas do H1 original da Welcome (antes do redesign). */
const titleClass =
    'mt-3 text-2xl font-bold leading-snug text-slate-900 sm:text-3xl md:mt-4 md:text-4xl md:leading-tight lg:text-5xl';

const prefersReducedMotion = ref(false);
const charCount = ref(0);
const isDeleting = ref(false);
const showCursor = ref(true);

let timerId = null;
let cursorTimerId = null;
let cancelled = false;
let mediaQuery = null;

const displayed = computed(() => TITLE.slice(0, charCount.value));

const clearTimer = () => {
    if (timerId !== null) {
        clearTimeout(timerId);
        timerId = null;
    }
};

const schedule = (fn, ms) => {
    clearTimer();
    timerId = setTimeout(() => {
        timerId = null;
        if (!cancelled) {
            fn();
        }
    }, ms);
};

const stopAnimation = () => {
    cancelled = true;
    clearTimer();
    charCount.value = TITLE.length;
    isDeleting.value = false;
    showCursor.value = false;
};

const tick = () => {
    if (!isDeleting.value) {
        if (charCount.value < TITLE.length) {
            charCount.value += 1;
            schedule(tick, props.typeSpeed);
            return;
        }

        schedule(() => {
            isDeleting.value = true;
            tick();
        }, props.holdMs);
        return;
    }

    if (charCount.value > 0) {
        charCount.value -= 1;
        schedule(tick, props.deleteSpeed);
        return;
    }

    isDeleting.value = false;
    schedule(tick, props.pauseMs);
};

const onMotionPreferenceChange = (event) => {
    prefersReducedMotion.value = event.matches;
    if (event.matches) {
        stopAnimation();
        return;
    }

    cancelled = false;
    charCount.value = 0;
    isDeleting.value = false;
    showCursor.value = true;
    schedule(tick, props.startDelayMs);
};

onMounted(() => {
    cancelled = false;

    if (typeof window !== 'undefined' && window.matchMedia) {
        mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        prefersReducedMotion.value = mediaQuery.matches;
        mediaQuery.addEventListener('change', onMotionPreferenceChange);
    }

    if (prefersReducedMotion.value) {
        stopAnimation();
        return;
    }

    cursorTimerId = setInterval(() => {
        if (prefersReducedMotion.value) {
            showCursor.value = false;
            return;
        }
        showCursor.value = !showCursor.value;
    }, 530);

    schedule(tick, props.startDelayMs);
});

onBeforeUnmount(() => {
    cancelled = true;
    clearTimer();
    if (cursorTimerId !== null) {
        clearInterval(cursorTimerId);
        cursorTimerId = null;
    }
    if (mediaQuery) {
        mediaQuery.removeEventListener('change', onMotionPreferenceChange);
        mediaQuery = null;
    }
});
</script>

<template>
    <div class="landing-hero-typewriter">
        <h1 :class="['relative', titleClass]">
            <span class="sr-only">{{ TITLE }}</span>

            <!-- Fantasma: mesma tipografia + texto completo → altura estável com wrap natural -->
            <span class="invisible block select-none" aria-hidden="true">{{ TITLE }}</span>

            <span
                class="absolute inset-0"
                aria-hidden="true"
                :aria-live="prefersReducedMotion ? undefined : 'polite'"
            >
                <template v-if="prefersReducedMotion">{{ TITLE }}</template>
                <template v-else>
                    {{ displayed }}
                    <span
                        class="landing-hero-typewriter__cursor"
                        :class="{ 'landing-hero-typewriter__cursor--on': showCursor }"
                    >|</span>
                </template>
            </span>
        </h1>

        <p class="mt-4 text-base leading-relaxed text-slate-600 md:mt-6 md:text-lg">
            Potencialize resultados com gestão de pessoas, ciência e estratégia.
        </p>
    </div>
</template>

<style scoped>
.landing-hero-typewriter__cursor {
    margin-left: 0.08em;
    font-weight: 400;
    color: currentColor;
    opacity: 0;
}

.landing-hero-typewriter__cursor--on {
    opacity: 1;
}
</style>
