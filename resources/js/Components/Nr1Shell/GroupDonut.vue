<script setup>
import { computed, ref } from 'vue';
import { GROUP_COLORS } from './nr1Theme';

/**
 * Rosca de participação por grupo com legenda interativa (hover ou foco destaca o grupo). As cores diferenciam grupos,
 * não níveis de risco. Só recebe os grupos já filtrados pelo back-end (grupo mínimo e «Outros Setores»).
 */
const props = defineProps({
    groups: { type: Array, required: true },
});

const active = ref(null);
const R = 36;
const C = 2 * Math.PI * R;
const total = computed(() => props.groups.reduce((sum, group) => sum + group.n, 0) || 1);
const segments = computed(() => {
    let offset = 0;

    return props.groups.map((group, index) => {
        const length = (group.n / total.value) * C;
        const segment = { ...group, color: GROUP_COLORS[index % GROUP_COLORS.length], dash: `${length} ${C - length}`, offset: -offset };
        offset += length;

        return segment;
    });
});
const summary = computed(() => props.groups.map((group) => `${group.name}: ${group.n} respostas, ${group.percent_label}`).join('; '));
</script>

<template>
    <div class="n1-donut-wrap">
        <svg viewBox="0 0 100 100" class="n1-donut" role="img" :aria-label="`Participação por grupo. ${summary}`">
            <circle cx="50" cy="50" :r="R" fill="none" stroke="#f0ecf4" stroke-width="14" />
            <circle
                v-for="segment in segments"
                :key="segment.name"
                cx="50" cy="50" :r="R" fill="none"
                :stroke="segment.color" stroke-width="14"
                :stroke-dasharray="segment.dash" :stroke-dashoffset="segment.offset"
                transform="rotate(-90 50 50)"
                :class="['n1-donut-seg', { dim: active && active !== segment.name }]"
            />
            <text x="50" y="48" text-anchor="middle" class="n1-donut-n">{{ total }}</text>
            <text x="50" y="60" text-anchor="middle" class="n1-donut-l">respostas</text>
        </svg>
        <ul class="n1-donut-legend">
            <li
                v-for="segment in segments"
                :key="segment.name"
                tabindex="0"
                :class="{ on: active === segment.name }"
                @mouseenter="active = segment.name"
                @mouseleave="active = null"
                @focus="active = segment.name"
                @blur="active = null"
            >
                <i :style="{ background: segment.color }" aria-hidden="true"></i>
                <b>{{ segment.name }}</b>
                <span>{{ segment.n }} respostas · {{ segment.percent_label }}</span>
            </li>
        </ul>
    </div>
</template>
