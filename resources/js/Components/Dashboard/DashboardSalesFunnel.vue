<script setup>
import { computed } from 'vue';

const FUNNEL_LABELS = {
    proposal: 'Proposta',
    negotiation: 'Negociação',
    approved: 'Aprovada',
    sale: 'Venda',
    ended: 'Encerrada',
};

/**
 * Larguras nas arestas entre segmentos (geometria clássica).
 * Independente de count — visual estável mesmo com etapas opcionais.
 */
const EDGE_WIDTHS = [100, 86, 72, 58, 44, 32];

const BAND_COLORS = [
    'from-talents-700 to-talents-600',
    'from-talents-600 to-violet-500',
    'from-violet-500 to-violet-400',
    'from-violet-400 to-violet-300',
    'from-violet-300 to-violet-200',
];

const props = defineProps({
    funnel: {
        type: Array,
        default: () => [],
    },
});

const rows = computed(() => {
    const list = props.funnel || [];
    // Base = etapa Proposta (cohort do mês); % nunca acima de 100 (salvo arredondamento).
    const base = Number(list[0]?.count || 0);

    return list.map((row, index) => {
        const count = Number(row.count || 0);
        const top = EDGE_WIDTHS[Math.min(index, EDGE_WIDTHS.length - 2)];
        const bottom = EDGE_WIDTHS[Math.min(index + 1, EDGE_WIDTHS.length - 1)];
        const rawPct = base > 0 ? Math.round((100 * count) / base) : 0;

        return {
            key: row.key,
            label: FUNNEL_LABELS[row.key] ?? row.label ?? row.key,
            count,
            pct: Math.min(100, rawPct),
            topWidth: top,
            bottomWidth: bottom,
            colorClass: BAND_COLORS[Math.min(index, BAND_COLORS.length - 1)],
        };
    });
});
</script>

<template>
    <div class="dashboard-sales-funnel">
        <div
            v-for="(row, index) in rows"
            :key="row.key"
            class="dashboard-sales-funnel__row"
            :class="{ 'dashboard-sales-funnel__row--last': index === rows.length - 1 }"
        >
            <div class="dashboard-sales-funnel__label">
                {{ row.label }}
            </div>

            <div class="dashboard-sales-funnel__band-wrap">
                <div
                    class="dashboard-sales-funnel__band bg-gradient-to-b"
                    :class="row.colorClass"
                    :style="{
                        '--funnel-top': `${row.topWidth}%`,
                        '--funnel-bottom': `${row.bottomWidth}%`,
                    }"
                    :title="`${row.label}: ${row.count} (${row.pct}%)`"
                />
            </div>

            <div class="dashboard-sales-funnel__metrics">
                <span class="tabular-nums font-semibold text-slate-800">{{ row.count }}</span>
                <span class="text-slate-300" aria-hidden="true">|</span>
                <span class="tabular-nums text-slate-500">{{ row.pct }}%</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dashboard-sales-funnel {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.dashboard-sales-funnel__row {
    display: grid;
    grid-template-columns: minmax(4.5rem, 5.5rem) minmax(0, 1fr) minmax(4.75rem, 5.5rem);
    align-items: center;
    gap: 0.75rem;
    min-height: 2.5rem;
    padding-block: 0.35rem;
    border-bottom: 1px solid rgb(241 245 249);
}

.dashboard-sales-funnel__row--last {
    border-bottom: 0;
}

.dashboard-sales-funnel__label {
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(51 65 85);
    line-height: 1.25;
}

.dashboard-sales-funnel__band-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 2rem;
}

.dashboard-sales-funnel__band {
    height: 1.85rem;
    width: 100%;
    max-width: 11rem;
    clip-path: polygon(
        calc((100% - var(--funnel-top)) / 2) 0,
        calc((100% + var(--funnel-top)) / 2) 0,
        calc((100% + var(--funnel-bottom)) / 2) 100%,
        calc((100% - var(--funnel-bottom)) / 2) 100%
    );
    transition: filter 0.2s ease, transform 0.2s ease;
}

.dashboard-sales-funnel__band:hover {
    filter: brightness(1.06);
}

.dashboard-sales-funnel__metrics {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.35rem;
    font-size: 0.875rem;
    white-space: nowrap;
}

@media (max-width: 639px) {
    .dashboard-sales-funnel__row {
        grid-template-columns: minmax(4.25rem, 5rem) minmax(0, 1fr) minmax(4.5rem, 5.25rem);
        gap: 0.5rem;
    }

    .dashboard-sales-funnel__band {
        max-width: none;
        height: 1.65rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-sales-funnel__band {
        transition: none;
    }

    .dashboard-sales-funnel__band:hover {
        filter: none;
    }
}
</style>
