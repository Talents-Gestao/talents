<script setup>
import CountUp from '@/Components/AdminHome/CountUp.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    /** [{ key, label, count }] na ordem do back-end. */
    rows: { type: Array, default: () => [] },
    /** Cores por índice da linha (as mesmas do gráfico anterior). */
    colors: { type: Array, required: true },
});

const total = computed(() => props.rows.reduce((sum, row) => sum + Number(row.count || 0), 0));

/** Fatia ativa (hover ou foco, na legenda ou no anel). */
const activeKey = ref(null);

const GAP = 1.2; // % do anel deixado entre fatias

const segments = computed(() => {
    const slices = props.rows.filter((row) => Number(row.count) > 0).length;
    let start = 0;

    return props.rows.map((row, index) => {
        const count = Number(row.count || 0);
        const pct = total.value > 0 ? (count / total.value) * 100 : 0;
        const visible = count > 0 ? Math.max(pct - (slices > 1 ? GAP : 0), 0.4) : 0;
        const segment = {
            key: row.key,
            label: row.label,
            count,
            pct,
            color: props.colors[index % props.colors.length],
            dash: `${visible} ${100 - visible}`,
            offset: -start,
        };
        start += pct;
        return segment;
    });
});

const drawn = computed(() => segments.value.filter((segment) => segment.count > 0));
const active = computed(() => segments.value.find((segment) => segment.key === activeKey.value) ?? null);

const formatPct = (value) => `${Math.round(value)}%`;
const summary = computed(() =>
    segments.value.map((segment) => `${segment.label}: ${segment.count}`).join(', '),
);
</script>

<template>
    <div class="home-donut">
        <div class="home-donut__chart">
            <svg
                viewBox="0 0 42 42"
                class="home-donut__svg"
                role="img"
                :aria-label="`Leads por origem, total ${total}. ${summary}`"
            >
                <circle class="home-donut__track" cx="21" cy="21" r="15.9155" fill="none" />
                <g transform="rotate(-90 21 21)">
                    <circle
                        v-for="segment in drawn"
                        :key="segment.key"
                        class="home-donut__slice"
                        :class="{
                            'is-active': activeKey === segment.key,
                            'is-dim': activeKey !== null && activeKey !== segment.key,
                        }"
                        cx="21"
                        cy="21"
                        r="15.9155"
                        fill="none"
                        :stroke="segment.color"
                        :stroke-dasharray="segment.dash"
                        :stroke-dashoffset="segment.offset"
                        @mouseenter="activeKey = segment.key"
                        @mouseleave="activeKey = null"
                    >
                        <title>{{ segment.label }}: {{ segment.count }} ({{ formatPct(segment.pct) }})</title>
                    </circle>
                </g>
            </svg>
            <div class="home-donut__center" aria-hidden="true">
                <template v-if="active">
                    <strong class="home-donut__value">{{ active.count }}</strong>
                    <span class="home-donut__caption">{{ active.label }}</span>
                    <span class="home-donut__share">{{ formatPct(active.pct) }}</span>
                </template>
                <template v-else>
                    <strong class="home-donut__value"><CountUp :value="total" /></strong>
                    <span class="home-donut__caption">Total</span>
                </template>
            </div>
        </div>

        <ul class="home-donut__legend">
            <li
                v-for="segment in segments"
                :key="segment.key"
                class="home-pill"
                :class="{
                    'is-active': activeKey === segment.key,
                    'is-dim': activeKey !== null && activeKey !== segment.key,
                }"
                :style="{ '--pill': segment.color }"
                tabindex="0"
                :aria-label="`${segment.label}: ${segment.count} leads`"
                @mouseenter="activeKey = segment.key"
                @mouseleave="activeKey = null"
                @focus="activeKey = segment.key"
                @blur="activeKey = null"
            >
                <span class="home-pill__dot" aria-hidden="true" />
                <span class="home-pill__label">{{ segment.label }}</span>
                <span class="home-pill__count">{{ segment.count }}</span>
            </li>
        </ul>
    </div>
</template>
