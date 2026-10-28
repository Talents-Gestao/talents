<script setup>
import CountUp from '@/Components/AdminHome/CountUp.vue';
import { useHomeIntro } from '@/composables/useHomeIntro';
import { computed } from 'vue';

const props = defineProps({
    /** 0–100 */
    percent: { type: Number, default: 0 },
});

const intro = useHomeIntro();

// Semicírculo de raio 80 centrado em (100,100).
const ARC = 'M 20 100 A 80 80 0 0 1 180 100';
const LENGTH = Math.PI * 80;

const clamped = computed(() => Math.min(100, Math.max(0, Number(props.percent) || 0)));
const filled = computed(() => (LENGTH * clamped.value) / 100);

/** Termina neutro (lilás) em 0% e vira verde conforme a meta se aproxima de 100%. */
const endColor = computed(
    () => `color-mix(in srgb, rgb(var(--success)) ${clamped.value}%, rgb(var(--talents-300)))`,
);

const formatPercent = (n) => `${Math.round(n)}%`;
</script>

<template>
    <div class="home-gauge">
        <svg
            viewBox="0 0 200 126"
            class="home-gauge__svg"
            role="img"
            :aria-label="`${Math.round(clamped)}% da meta mensal`"
        >
            <defs>
                <linearGradient id="home-gauge-fill" gradientUnits="userSpaceOnUse" x1="20" y1="0" x2="180" y2="0">
                    <stop offset="0" style="stop-color: rgb(var(--talents-300))" />
                    <stop offset="1" :style="{ stopColor: endColor }" />
                </linearGradient>
            </defs>
            <path :d="ARC" class="home-gauge__track" fill="none" />
            <path
                v-if="clamped > 0"
                :d="ARC"
                class="home-gauge__fill"
                :class="{ 'is-intro': intro }"
                fill="none"
                stroke="url(#home-gauge-fill)"
                :stroke-dasharray="`${filled} ${LENGTH}`"
                :style="{ '--gauge-len': filled, '--gauge-total': LENGTH }"
            />
            <text x="20" y="122" class="home-gauge__tick" text-anchor="middle">0%</text>
            <text x="180" y="122" class="home-gauge__tick" text-anchor="middle">100%</text>
        </svg>
        <div class="home-gauge__center">
            <strong class="home-gauge__value">
                <CountUp :value="Math.round(clamped)" :format="formatPercent" />
            </strong>
            <span class="home-gauge__caption">da meta</span>
        </div>
    </div>
</template>
