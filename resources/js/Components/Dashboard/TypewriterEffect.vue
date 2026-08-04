<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    /** Frases a percorrer no efeito typewriter */
    phrases: {
        type: Array,
        required: true,
        validator: (value) => Array.isArray(value) && value.length > 0,
    },
    /** ms por caractere ao digitar */
    typeSpeed: {
        type: Number,
        default: 55,
    },
    /** ms por caractere ao apagar */
    deleteSpeed: {
        type: Number,
        default: 28,
    },
    /** pausa com o texto completo */
    holdMs: {
        type: Number,
        default: 2200,
    },
    /** pausa com o texto vazio antes da próxima frase */
    pauseMs: {
        type: Number,
        default: 400,
    },
    /** atraso inicial */
    startDelayMs: {
        type: Number,
        default: 300,
    },
    loop: {
        type: Boolean,
        default: true,
    },
});

const displayed = ref('');
const phraseIndex = ref(0);
const isDeleting = ref(false);
const showCursor = ref(true);

let timerId = null;
let cursorTimerId = null;
let cancelled = false;

const phrases = computed(() =>
    props.phrases.map((p) => String(p ?? '').trim()).filter(Boolean),
);

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

const tick = () => {
    const list = phrases.value;
    if (list.length === 0) {
        return;
    }

    const full = list[phraseIndex.value % list.length];
    const current = displayed.value;

    if (!isDeleting.value) {
        if (current.length < full.length) {
            displayed.value = full.slice(0, current.length + 1);
            schedule(tick, props.typeSpeed);
            return;
        }

        if (!props.loop && phraseIndex.value >= list.length - 1) {
            return;
        }

        schedule(() => {
            isDeleting.value = true;
            tick();
        }, props.holdMs);
        return;
    }

    if (current.length > 0) {
        displayed.value = current.slice(0, -1);
        schedule(tick, props.deleteSpeed);
        return;
    }

    isDeleting.value = false;
    phraseIndex.value = (phraseIndex.value + 1) % list.length;
    schedule(tick, props.pauseMs);
};

onMounted(() => {
    cancelled = false;
    displayed.value = '';
    phraseIndex.value = 0;
    isDeleting.value = false;

    cursorTimerId = setInterval(() => {
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
});
</script>

<template>
    <span class="typewriter-effect inline" aria-live="polite">
        <span class="typewriter-effect__text">{{ displayed }}</span>
        <span
            class="typewriter-effect__cursor"
            :class="{ 'typewriter-effect__cursor--on': showCursor }"
            aria-hidden="true"
        >|</span>
    </span>
</template>

<style scoped>
.typewriter-effect__cursor {
    margin-left: 0.08em;
    font-weight: 400;
    color: currentColor;
    opacity: 0;
}

.typewriter-effect__cursor--on {
    opacity: 1;
}
</style>
