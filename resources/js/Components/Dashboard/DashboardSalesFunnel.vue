<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const FUNNEL_LABELS = {
    leads: 'Leads',
    qualified: 'Qualificação',
    proposal: 'Proposta',
    closed: 'Fechada',
};

/**
 * Geometria fixa do funil (contínua). Independente da % — evita faixas
 * desconectadas quando alguma etapa está a 0.
 */
const EDGE_WIDTHS = [100, 82, 64, 48, 34];

const BAND_COLORS = [
    'from-talents-700 to-talents-600',
    'from-talents-600 to-violet-500',
    'from-violet-500 to-violet-400',
    'from-violet-400 to-violet-300',
];

const props = defineProps({
    funnel: {
        type: Array,
        default: () => [],
    },
    lost: {
        type: Object,
        default: () => ({
            count: 0,
            href: null,
            items: [],
        }),
    },
});

/**
 * % = conversão acumulada a partir do topo (produto das taxas entre etapas).
 * Assim a % só diminui ou estabiliza. O count exibido é o volume real do mês.
 */
const rows = computed(() => {
    const list = props.funnel || [];
    const counts = list.map((row) => Number(row.count || 0));

    let cumulativeRate = 1;
    const pcts = counts.map((count, index) => {
        if (index === 0) {
            return counts[0] > 0 ? 100 : 0;
        }

        const prev = counts[index - 1];
        const stageRate = prev > 0 ? Math.min(1, count / prev) : 0;
        cumulativeRate *= stageRate;

        return Math.round(100 * cumulativeRate);
    });

    return list.map((row, index) => {
        const count = counts[index];
        const pct = pcts[index];
        const top = EDGE_WIDTHS[Math.min(index, EDGE_WIDTHS.length - 2)];
        const bottom = EDGE_WIDTHS[Math.min(index + 1, EDGE_WIDTHS.length - 1)];

        return {
            key: row.key,
            label: FUNNEL_LABELS[row.key] ?? row.label ?? row.key,
            count,
            pct,
            topWidth: top,
            bottomWidth: bottom,
            colorClass: BAND_COLORS[Math.min(index, BAND_COLORS.length - 1)],
            href: row.href || null,
        };
    });
});

const lostCount = computed(() => Number(props.lost?.count || 0));
const lostHref = computed(() => props.lost?.href || null);
const lostItems = computed(() => props.lost?.items || []);

const selectedLost = ref(null);

const lostModalOpen = computed({
    get: () => selectedLost.value !== null,
    set: (open) => {
        if (!open) {
            selectedLost.value = null;
        }
    },
});

function openLostModal(item) {
    selectedLost.value = item;
}

function closeLostModal() {
    selectedLost.value = null;
}

function formatLostDate(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}
</script>

<template>
    <div class="dashboard-sales-funnel">
        <div
            v-for="(row, index) in rows"
            :key="row.key"
            class="dashboard-sales-funnel__stage"
        >
            <component
                :is="row.href ? Link : 'div'"
                :href="row.href || undefined"
                class="dashboard-sales-funnel__row"
                :class="{
                    'dashboard-sales-funnel__row--link': !!row.href,
                    'dashboard-sales-funnel__row--last': index === rows.length - 1,
                }"
                :title="`${row.label}: ${row.count} no mês · ${row.pct}% de conversão acumulada`"
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
                    />
                </div>

                <div class="dashboard-sales-funnel__metrics">
                    <span class="tabular-nums font-semibold text-slate-800">{{ row.count }}</span>
                    <span class="text-slate-300" aria-hidden="true">|</span>
                    <span class="tabular-nums text-slate-500">{{ row.pct }}%</span>
                </div>
            </component>
        </div>

        <div class="dashboard-sales-funnel__lost mt-4 rounded-xl border border-rose-100 bg-rose-50/70 px-3 py-2.5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">
                        Perdido
                    </p>
                    <p class="mt-0.5 text-sm tabular-nums font-semibold text-slate-800">
                        {{ lostCount }}
                        <span class="font-normal text-slate-500">
                            {{ lostCount === 1 ? 'proposta' : 'propostas' }}
                        </span>
                    </p>
                </div>
                <Link
                    v-if="lostHref && lostCount > 0"
                    :href="lostHref"
                    class="shrink-0 text-xs font-semibold text-rose-700 hover:underline"
                >
                    Ver todas
                </Link>
            </div>

            <ul v-if="lostItems.length" class="mt-2 max-h-48 space-y-1.5 overflow-y-auto">
                <li
                    v-for="item in lostItems"
                    :key="item.key"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg bg-white/80 px-2.5 py-1.5 text-left text-sm transition hover:bg-white hover:ring-1 hover:ring-rose-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500"
                        @click="openLostModal(item)"
                    >
                        <span class="truncate font-medium text-slate-800">{{ item.name }}</span>
                        <span class="shrink-0 tabular-nums text-slate-600">
                            {{ item.count }}
                            <span class="text-slate-400">{{ item.count === 1 ? 'resp.' : 'resps.' }}</span>
                        </span>
                    </button>
                </li>
            </ul>
            <p v-else class="mt-2 text-sm text-slate-500">
                Nenhuma proposta perdida neste mês.
            </p>
        </div>

        <FullScreenOverlay :show="lostModalOpen" @close="closeLostModal">
            <div
                v-if="selectedLost"
                class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="funnel-lost-modal-title"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="funnel-lost-modal-title" class="text-lg font-semibold text-slate-900">
                            {{ selectedLost.name }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Respostas de perda neste mês
                            ({{ selectedLost.count }}
                            {{ selectedLost.count === 1 ? 'registro' : 'registros' }}).
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                        @click="closeLostModal"
                    >
                        Fechar
                    </button>
                </div>

                <ul class="mt-5 space-y-3">
                    <li
                        v-for="response in selectedLost.responses"
                        :key="response.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/80 px-3.5 py-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ response.code || 'Sem código' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ formatLostDate(response.created_at) }}
                            </p>
                        </div>
                        <dl class="mt-2 space-y-1.5 text-sm">
                            <div class="flex gap-2">
                                <dt class="shrink-0 text-slate-500">Motivo</dt>
                                <dd class="font-medium text-slate-800">{{ response.lost_reason_label }}</dd>
                            </div>
                            <div v-if="response.lost_reason_notes" class="flex gap-2">
                                <dt class="shrink-0 text-slate-500">Justificativa</dt>
                                <dd class="whitespace-pre-wrap text-slate-800">{{ response.lost_reason_notes }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="shrink-0 text-slate-500">Vendedor</dt>
                                <dd class="text-slate-800">{{ response.seller_name }}</dd>
                            </div>
                        </dl>
                    </li>
                </ul>
            </div>
        </FullScreenOverlay>
    </div>
</template>

<style scoped>
.dashboard-sales-funnel {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.dashboard-sales-funnel__stage {
    display: flex;
    flex-direction: column;
}

.dashboard-sales-funnel__row {
    display: grid;
    grid-template-columns: minmax(5.5rem, 7.25rem) minmax(0, 1fr) minmax(4.75rem, 5.5rem);
    align-items: center;
    gap: 0.75rem;
    min-height: 2.5rem;
    padding-block: 0.35rem;
    border-radius: 0.5rem;
    color: inherit;
    text-decoration: none;
}

.dashboard-sales-funnel__row--link {
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.dashboard-sales-funnel__row--link:hover {
    background-color: rgb(248 250 252);
}

.dashboard-sales-funnel__row--link:focus-visible {
    outline: 2px solid rgb(124 58 237);
    outline-offset: 2px;
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

.dashboard-sales-funnel__row--link:hover .dashboard-sales-funnel__band {
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
        grid-template-columns: minmax(4.75rem, 6rem) minmax(0, 1fr) minmax(4.5rem, 5.25rem);
        gap: 0.5rem;
    }

    .dashboard-sales-funnel__band {
        max-width: none;
        height: 1.65rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-sales-funnel__band,
    .dashboard-sales-funnel__row--link {
        transition: none;
    }

    .dashboard-sales-funnel__row--link:hover .dashboard-sales-funnel__band {
        filter: none;
    }
}
</style>
