<script setup>
import CountUp from '@/Components/AdminHome/CountUp.vue';
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import { useHomeIntro } from '@/composables/useHomeIntro';
import { Link } from '@inertiajs/vue3';
import { XCircleIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { FUNNEL_OVER_PREVIOUS_HINT, buildFunnelRows } from '@/utils/salesFunnelMetrics';

const FUNNEL_LABELS = {
    leads: 'Leads',
    qualified: 'Qualificação',
    proposal: 'Proposta',
    closed: 'Fechada',
};

/**
 * Pontos de cor da silhueta, do topo à base do funil: a faixa i vai de STOPS[i] a STOPS[i + 1],
 * então o degradê é contínuo entre faixas. Só tokens (roxo da marca → azul `info`).
 */
const STOPS = [
    'rgb(var(--talents-700))',
    'rgb(var(--talents-600))',
    'rgb(var(--talents-500))',
    'color-mix(in srgb, rgb(var(--info)) 55%, rgb(var(--talents-500)))',
    'rgb(var(--info))',
];

/** Largura mínima (%) da faixa e quanto a última faixa afunila na base. */
const MIN_WIDTH = 34;
const LAST_TAPER = 0.78;

/** Prefixo único por instância para os ids dos degradês SVG. */
const gradientPrefix = `home-funnel-${Math.random().toString(36).slice(2, 8)}`;

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
    /** 'month' | 'all' — textos do bloco Perdido e tooltips. */
    scope: {
        type: String,
        default: 'month',
        validator: (value) => ['month', 'all'].includes(value),
    },
});

const intro = useHomeIntro();

const scopePhrase = computed(() => (props.scope === 'all' ? 'no total' : 'neste mês'));
const scopeShort = computed(() => (props.scope === 'all' ? 'no total' : 'no mês'));

/**
 * Contagens independentes por etapa. Leitura principal: % dos leads (contagem ÷ leads);
 * secundária: % da etapa anterior. Cálculo em `utils/salesFunnelMetrics` (não alterado aqui).
 */
const baseRows = computed(() => buildFunnelRows(props.funnel));

const isEmpty = computed(() => baseRows.value.every((row) => row.count === 0));

/**
 * Largura da silhueta: escala de raiz quadrada (suaviza a diferença entre 3 e 300) com mínimo
 * generoso, e nunca maior que a etapa anterior.
 */
const widths = computed(() => {
    const max = Math.max(0, ...baseRows.value.map((row) => row.count));
    let previous = 100;

    return baseRows.value.map((row) => {
        const raw = max > 0 ? MIN_WIDTH + (100 - MIN_WIDTH) * Math.sqrt(row.count / max) : MIN_WIDTH;
        previous = Math.min(previous, raw);
        return previous;
    });
});

const leadsCount = computed(() => baseRows.value[0]?.count ?? 0);

function polygonPoints(top, bottom) {
    // viewBox 0 0 100 40; margem de 1,5 em y para o traço arredondado não vazar da linha.
    return [
        `${50 - top / 2},1.5`,
        `${50 + top / 2},1.5`,
        `${50 + bottom / 2},38.5`,
        `${50 - bottom / 2},38.5`,
    ].join(' ');
}

const rows = computed(() =>
    baseRows.value.map((row, index) => {
        const label = FUNNEL_LABELS[row.key] ?? row.label;
        const top = widths.value[index];
        const isLast = index === baseRows.value.length - 1;
        const bottom = isLast ? top * LAST_TAPER : widths.value[index + 1];
        const tooltip = [
            `${label}: ${row.count} ${scopeShort.value}`,
            `${row.pctOfLeads} dos leads`,
            row.pctOfPrevious !== null ? `${row.pctOfPrevious} da etapa anterior` : null,
            row.overPrevious ? FUNNEL_OVER_PREVIOUS_HINT : null,
        ]
            .filter(Boolean)
            .join(' · ');

        return {
            ...row,
            label,
            tooltip,
            points: polygonPoints(top, bottom),
            gradientId: `${gradientPrefix}-${index}`,
            from: STOPS[Math.min(index, STOPS.length - 1)],
            to: STOPS[Math.min(index + 1, STOPS.length - 1)],
            // proporção real do total de leads (a mesma base de "% dos leads")
            share: leadsCount.value > 0 ? Math.min(1, row.count / leadsCount.value) : 0,
        };
    }),
);

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
        <div v-if="isEmpty" class="funnel-empty">
            <svg class="funnel-empty__art" viewBox="0 0 120 72" fill="none" aria-hidden="true">
                <path d="M10 6h100l-22 22H32L10 6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" opacity="0.5" />
                <path d="M32 34h56l-14 16H46L32 34Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" opacity="0.7" />
                <path d="M46 56h28l-7 10H53l-7-10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
            </svg>
            <p class="funnel-empty__title">Ainda não há leads {{ scopePhrase }}</p>
            <p class="funnel-empty__text">Quando os leads chegarem, as etapas do funil aparecem aqui.</p>
        </div>

        <template v-else>
            <div
                v-for="(row, index) in rows"
                :key="row.key"
                class="dashboard-sales-funnel__stage"
            >
                <component
                    :is="row.href ? Link : 'div'"
                    :href="row.href || undefined"
                    :tabindex="row.href ? undefined : 0"
                    class="dashboard-sales-funnel__row home-funnel-row"
                    :class="{
                        'dashboard-sales-funnel__row--link': !!row.href,
                        'dashboard-sales-funnel__row--last': index === rows.length - 1,
                        'dashboard-sales-funnel__row--zero': row.count === 0,
                    }"
                    data-home-funnel-row
                >
                    <div class="dashboard-sales-funnel__label">
                        {{ row.label }}
                    </div>

                    <div
                        class="dashboard-sales-funnel__band-wrap"
                        :class="{ 'is-intro-seq': intro }"
                        :style="{ '--seq': index }"
                    >
                        <svg
                            class="dashboard-sales-funnel__band"
                            viewBox="0 0 100 40"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <defs>
                                <linearGradient :id="row.gradientId" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" :style="{ stopColor: row.from }" />
                                    <stop offset="1" :style="{ stopColor: row.to }" />
                                </linearGradient>
                            </defs>
                            <polygon
                                :points="row.points"
                                :fill="`url(#${row.gradientId})`"
                                :stroke="`url(#${row.gradientId})`"
                                stroke-width="3"
                                stroke-linejoin="round"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                    </div>

                    <div class="dashboard-sales-funnel__metrics">
                        <div class="dashboard-sales-funnel__figures">
                            <span class="dashboard-sales-funnel__count"><CountUp :value="row.count" /></span>
                            <span class="dashboard-sales-funnel__sep" aria-hidden="true">|</span>
                            <span class="dashboard-sales-funnel__pct">{{ row.pctOfLeads }}</span>
                        </div>
                        <div
                            class="dashboard-sales-funnel__meter"
                            role="img"
                            :aria-label="`${row.pctOfLeads} dos leads`"
                        >
                            <span
                                class="dashboard-sales-funnel__meter-fill"
                                :style="{ transform: `scaleX(${row.share})` }"
                            />
                        </div>
                        <div v-if="row.pctOfPrevious !== null" class="dashboard-sales-funnel__prev tabular-nums">
                            {{ row.pctOfPrevious }} da anterior<span v-if="row.overPrevious" aria-hidden="true"> ›</span>
                        </div>
                    </div>

                    <span class="dashboard-sales-funnel__tip" role="tooltip">{{ row.tooltip }}</span>
                </component>
            </div>
        </template>

        <div class="dashboard-sales-funnel__lost">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="dashboard-sales-funnel__lost-chip" aria-hidden="true">
                        <XCircleIcon class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="dashboard-sales-funnel__lost-title">Perdido</p>
                        <p class="dashboard-sales-funnel__lost-count">
                            <span class="dashboard-sales-funnel__lost-number tabular-nums">{{ lostCount }}</span>
                            {{ lostCount === 1 ? 'proposta' : 'propostas' }}
                        </p>
                    </div>
                </div>
                <Link
                    v-if="lostHref && lostCount > 0"
                    :href="lostHref"
                    class="dashboard-sales-funnel__lost-link"
                >
                    Ver todas
                </Link>
            </div>

            <ul v-if="lostItems.length" class="mt-3 max-h-48 space-y-1.5 overflow-y-auto">
                <li
                    v-for="item in lostItems"
                    :key="item.key"
                >
                    <button
                        type="button"
                        class="dashboard-sales-funnel__lost-item"
                        @click="openLostModal(item)"
                    >
                        <span class="truncate font-medium">{{ item.name }}</span>
                        <span class="shrink-0 tabular-nums">
                            {{ item.count }}
                            <span class="dashboard-sales-funnel__lost-unit">{{ item.count === 1 ? 'resp.' : 'resps.' }}</span>
                        </span>
                    </button>
                </li>
            </ul>
            <p v-else class="dashboard-sales-funnel__lost-empty">
                Nenhuma proposta perdida {{ scopePhrase }}.
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
                            Respostas de perda {{ scopePhrase }}
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
    /* Largura máxima da coluna de rótulos + gap: o tooltip começa depois dela e nunca cobre um rótulo. */
    --funnel-tip-left: 7.25rem;
    --funnel-tip-right: 0px;

    container-type: inline-size;
    display: flex;
    flex-direction: column;
    gap: 3px; /* pequeno espaçamento entre faixas; topo de cada uma = base da anterior */
}

.dashboard-sales-funnel__stage {
    display: flex;
    flex-direction: column;
}

.dashboard-sales-funnel__row {
    position: relative;
    display: grid;
    grid-template-columns: minmax(5rem, 6.5rem) minmax(0, 1fr) minmax(7.5rem, 8.5rem);
    align-items: stretch;
    gap: 0.75rem;
    min-height: 3.25rem;
    border-radius: 0.5rem;
    color: inherit;
    text-decoration: none;
}

.dashboard-sales-funnel__row--link {
    cursor: pointer;
}

.dashboard-sales-funnel__row:focus-visible {
    outline: 2px solid rgb(var(--primary-ring));
    outline-offset: 2px;
}

.dashboard-sales-funnel__label {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    font-weight: 700;
    line-height: 1.25;
    color: rgb(var(--ink));
}

.dashboard-sales-funnel__band-wrap {
    position: relative;
    min-height: 2.75rem;
}

.dashboard-sales-funnel__band {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
    transition: filter 0.2s ease;
}

.dashboard-sales-funnel__row:hover .dashboard-sales-funnel__band,
.dashboard-sales-funnel__row:focus-visible .dashboard-sales-funnel__band {
    filter: brightness(1.08) saturate(1.1);
}

/*
 * Tooltip acima da linha, ancorado à direita e limitado às colunas da faixa e dos números: os
 * rótulos das etapas (desta e da anterior) continuam legíveis durante o hover.
 */
.dashboard-sales-funnel__tip {
    position: absolute;
    bottom: calc(100% + 2px);
    right: var(--funnel-tip-right);
    z-index: 5;
    width: max-content;
    max-width: min(16rem, calc(100% - var(--funnel-tip-left) - var(--funnel-tip-right)));
    padding: 0.4rem 0.6rem;
    border-radius: 0.6rem;
    background: rgb(var(--ink));
    color: rgb(var(--surface));
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1.35;
    text-align: left;
    box-shadow: 0 10px 24px -8px rgb(15 23 42 / 0.5);
    opacity: 0;
    pointer-events: none;
    transform: translateY(4px);
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.dashboard-sales-funnel__row:hover .dashboard-sales-funnel__tip,
.dashboard-sales-funnel__row:focus-visible .dashboard-sales-funnel__tip {
    opacity: 1;
    transform: none;
}

/* Etapa sem contagem: contorno tracejado e números neutros, sem o degradê de etapa "cheia". */
.dashboard-sales-funnel__row--zero polygon {
    fill: rgb(var(--primary) / 0.05);
    stroke: rgb(var(--primary) / 0.32);
    stroke-width: 1.25;
    stroke-dasharray: 4 3;
}

.dashboard-sales-funnel__row--zero .dashboard-sales-funnel__label,
.dashboard-sales-funnel__row--zero .dashboard-sales-funnel__count {
    color: rgb(var(--ink-muted));
}

.dashboard-sales-funnel__row--zero .dashboard-sales-funnel__pct {
    color: rgb(var(--ink-muted));
    font-weight: 600;
}

.dashboard-sales-funnel__row--zero:hover .dashboard-sales-funnel__band,
.dashboard-sales-funnel__row--zero:focus-visible .dashboard-sales-funnel__band {
    filter: none;
}

.dashboard-sales-funnel__metrics {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 0.2rem;
    white-space: nowrap;
}

.dashboard-sales-funnel__figures {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
}

.dashboard-sales-funnel__count {
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    color: rgb(var(--ink));
}

.dashboard-sales-funnel__sep {
    color: rgb(var(--line));
}

.dashboard-sales-funnel__pct {
    font-size: 0.875rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: rgb(var(--primary-text));
}

/* Barra fina: proporção real da etapa sobre o total de leads. */
.dashboard-sales-funnel__meter {
    width: 100%;
    height: 4px;
    overflow: hidden;
    border-radius: 9999px;
    background: rgb(var(--line));
}

.dashboard-sales-funnel__meter-fill {
    display: block;
    height: 100%;
    width: 100%;
    transform-origin: left;
    border-radius: 9999px;
    background: linear-gradient(90deg, rgb(var(--primary)), rgb(var(--info)));
    transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}

.dashboard-sales-funnel__prev {
    font-size: 0.75rem;
    line-height: 1.1;
    color: rgb(var(--ink-muted));
}

/* Estado vazio */
.funnel-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    padding: 1.25rem 0.5rem;
    text-align: center;
    color: rgb(var(--primary));
}

.funnel-empty__art {
    width: 7.5rem;
    height: auto;
}

.funnel-empty__title {
    margin-top: 0.4rem;
    font-size: 0.9375rem;
    font-weight: 700;
    color: rgb(var(--ink));
}

.funnel-empty__text {
    max-width: 18rem;
    font-size: 0.8125rem;
    color: rgb(var(--ink-muted));
}

/* Perdido */
.dashboard-sales-funnel__lost {
    margin-top: 1rem;
    border-radius: 1rem;
    border: 1px solid rgb(var(--danger) / 0.18);
    background: linear-gradient(135deg, rgb(var(--danger) / 0.07), rgb(var(--danger) / 0.02));
    padding: 0.75rem 0.9rem;
}

.dashboard-sales-funnel__lost-chip {
    display: inline-flex;
    height: 2.25rem;
    width: 2.25rem;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    background: rgb(var(--danger) / 0.12);
    color: rgb(var(--danger));
}

.dashboard-sales-funnel__lost-title {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: rgb(var(--danger));
}

.dashboard-sales-funnel__lost-count {
    font-size: 0.875rem;
    color: rgb(var(--ink-muted));
}

.dashboard-sales-funnel__lost-number {
    margin-right: 0.15rem;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: rgb(var(--ink));
}

.dashboard-sales-funnel__lost-link {
    flex: none;
    font-size: 0.8125rem;
    font-weight: 700;
    color: rgb(var(--danger));
}

.dashboard-sales-funnel__lost-link:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
}

.dashboard-sales-funnel__lost-item {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border-radius: 0.6rem;
    background: rgb(var(--surface) / 0.8);
    padding: 0.4rem 0.65rem;
    text-align: left;
    font-size: 0.875rem;
    color: rgb(var(--ink));
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
}

.dashboard-sales-funnel__lost-item:hover {
    background: rgb(var(--surface));
    box-shadow: 0 0 0 1px rgb(var(--danger) / 0.3);
}

.dashboard-sales-funnel__lost-item:focus-visible {
    outline: 2px solid rgb(var(--danger));
    outline-offset: 2px;
}

.dashboard-sales-funnel__lost-unit {
    color: rgb(var(--ink-muted));
}

.dashboard-sales-funnel__lost-empty {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: rgb(var(--ink-muted));
}

@media (max-width: 639px) {
    .dashboard-sales-funnel {
        --funnel-tip-left: 5.5rem;
    }

    .dashboard-sales-funnel__row {
        grid-template-columns: minmax(4.25rem, 5rem) minmax(0, 1fr) minmax(6.5rem, 7.5rem);
        gap: 0.5rem;
    }
}

/*
 * Cartão estreito (ex.: três colunas em 1024px): três colunas deixariam a silhueta sem largura.
 * A faixa ocupa a esquerda nas duas linhas; rótulo e números empilham à direita.
 */
@container (max-width: 21rem) {
    .dashboard-sales-funnel__row {
        --funnel-tip-left: 0px;
        --funnel-tip-right: 7.75rem;

        grid-template-columns: minmax(0, 1fr) minmax(6.5rem, 7.25rem);
        grid-template-rows: auto auto;
        column-gap: 0.5rem;
        row-gap: 0.1rem;
        min-height: 3.75rem;
    }

    .dashboard-sales-funnel__band-wrap {
        grid-column: 1;
        grid-row: 1 / span 2;
    }

    .dashboard-sales-funnel__label {
        grid-column: 2;
        grid-row: 1;
        justify-content: flex-end;
        align-items: flex-end;
        font-size: 0.8125rem;
    }

    .dashboard-sales-funnel__metrics {
        grid-column: 2;
        grid-row: 2;
        justify-content: flex-start;
    }

    .dashboard-sales-funnel__tip {
        right: auto;
        left: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .dashboard-sales-funnel__band,
    .dashboard-sales-funnel__tip,
    .dashboard-sales-funnel__meter-fill,
    .dashboard-sales-funnel__lost-item {
        transition: none;
    }

    .dashboard-sales-funnel__row:hover .dashboard-sales-funnel__band {
        filter: none;
    }
}
</style>
