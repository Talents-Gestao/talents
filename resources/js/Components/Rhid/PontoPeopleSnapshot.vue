<script setup>
import InfoTooltip from '@/Components/InfoTooltip.vue';
import { computed, reactive, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const RANKING_PREVIEW = 3;

const props = defineProps({
    people: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    monthLabel: { type: String, default: '' },
    diagnosticsHint: { type: String, default: '' },
    showMonthPicker: { type: Boolean, default: false },
    monthValue: { type: String, default: '' },
    monthMax: { type: String, default: '' },
    monthDisabled: { type: Boolean, default: false },
    linkCollaborators: { type: Boolean, default: false },
});

const emit = defineEmits(['month-change']);

const snapshot = computed(() => {
    const p = props.people && typeof props.people === 'object' ? props.people : {};
    return {
        has_data: Boolean(p.has_data),
        evaluated_count: Number(p.evaluated_count ?? 0),
        punctuality_avg_percent: p.punctuality_avg_percent ?? null,
        unjustified_absence_days: Number(p.unjustified_absence_days ?? 0),
        atestado_days: Number(p.atestado_days ?? 0),
        ranking_pontualidade: Array.isArray(p.ranking_pontualidade) ? p.ranking_pontualidade : [],
        ranking_faltas: Array.isArray(p.ranking_faltas) ? p.ranking_faltas : [],
        ranking_atestados: Array.isArray(p.ranking_atestados) ? p.ranking_atestados : [],
        resumo: p.resumo && typeof p.resumo === 'object' ? p.resumo : null,
    };
});

const formatPct = (value) => {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${Number(value).toFixed(1)}%`;
};

const onMonthChange = (event) => {
    emit('month-change', event?.target?.value);
};

const personHref = (row) => {
    if (!props.linkCollaborators || row?.id_person == null) {
        return null;
    }
    return route('client.rhid.collaborators.show', row.id_person);
};

const rankingExpanded = reactive({
    pontualidade: false,
    faltas: false,
    atestados: false,
});

const visibleRanking = (rows, key) => {
    if (!Array.isArray(rows) || rankingExpanded[key] || rows.length <= RANKING_PREVIEW) {
        return rows;
    }
    return rows.slice(0, RANKING_PREVIEW);
};

const rankingToggleLabel = (rows, key) => {
    if (rankingExpanded[key]) {
        return 'Ver menos';
    }
    const hidden = Math.max(0, rows.length - RANKING_PREVIEW);
    return hidden > 0 ? `Ver mais (${hidden})` : 'Ver mais';
};

watch(
    () => props.people,
    () => {
        rankingExpanded.pontualidade = false;
        rankingExpanded.faltas = false;
        rankingExpanded.atestados = false;
    },
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-talents-800">Equipe no mês</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Pontualidade, faltas não justificadas e atestados
                    <template v-if="monthLabel">· {{ monthLabel }}</template>
                    · a partir dos espelhos importados
                </p>
            </div>
            <div v-if="showMonthPicker" class="shrink-0">
                <label class="sr-only" for="ponto-people-month">Mês de referência</label>
                <input
                    id="ponto-people-month"
                    type="month"
                    class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-1 focus:ring-talents-400"
                    :value="monthValue"
                    :max="monthMax"
                    :disabled="monthDisabled || loading"
                    @change="onMonthChange"
                >
            </div>
        </div>

        <p v-if="diagnosticsHint" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-100">
            {{ diagnosticsHint }}
        </p>

        <div v-if="loading && !people" class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="i in 3"
                :key="`kpi-skel-${i}`"
                class="h-32 animate-pulse rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50 to-slate-100"
            />
        </div>

        <template v-else>
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="group relative overflow-visible rounded-2xl bg-gradient-to-br from-talents-700 via-talents-600 to-talents-500 p-4 text-left text-white shadow-md">
                    <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10 blur-xl" />
                    <div class="relative z-10">
                        <p class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wider text-white/75">
                            Pontualidade média
                            <InfoTooltip
                                tone="onDark"
                                text="Percentual médio de dias em que a equipe cumpriu a jornada esperada, nos dias úteis com espelho importado."
                            />
                        </p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">
                            {{ snapshot.has_data ? formatPct(snapshot.punctuality_avg_percent) : '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-white/80">
                            Média do cumprimento nos dias úteis esperados
                        </p>
                    </div>
                </div>
                <div class="group relative overflow-visible rounded-2xl bg-gradient-to-br from-talents-800 via-talents-700 to-talents-600 p-4 text-left text-white shadow-md">
                    <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10 blur-xl" />
                    <div class="relative z-10">
                        <p class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wider text-white/75">
                            Faltas não justificadas
                            <InfoTooltip
                                tone="onDark"
                                text="Dias úteis sem jornada completa e sem atestado, férias ou feriado. Conta como falta no ponto."
                            />
                        </p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">
                            {{ snapshot.has_data ? snapshot.unjustified_absence_days : '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-white/80">
                            Dias úteis sem marcação completa e sem atestado, férias ou feriado
                        </p>
                    </div>
                </div>
                <div class="group relative overflow-visible rounded-2xl bg-gradient-to-br from-amber-500 via-amber-400 to-yellow-400 p-4 text-left text-white shadow-md">
                    <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/20 blur-xl" />
                    <div class="relative z-10">
                        <p class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wider text-white/85">
                            Atestados
                            <InfoTooltip
                                tone="onDark"
                                text="Dias úteis cobertos por atestado no ponto. Esses dias saem do cálculo de pontualidade e de falta."
                            />
                        </p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">
                            {{ snapshot.has_data ? snapshot.atestado_days : '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-white/85">
                            Dias úteis cobertos por atestado no ponto
                        </p>
                    </div>
                </div>
            </div>

            <p
                v-if="loading"
                class="text-[11px] font-medium text-talents-700"
            >
                Atualizando indicadores da equipe…
            </p>

            <p
                v-if="snapshot.resumo"
                class="text-[11px] text-slate-500"
            >
                {{ snapshot.resumo.dias_uteis_no_periodo ?? '—' }} dia(s) útil(eis) no período
                <template v-if="snapshot.resumo.dias_feriado_no_periodo">
                    · {{ snapshot.resumo.dias_feriado_no_periodo }} feriado(s)
                </template>
                · {{ snapshot.evaluated_count }} colaborador(es) com espelho
            </p>

            <div
                v-if="!snapshot.has_data"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center"
            >
                <p class="text-sm font-medium text-slate-700">Sem dados de cumprimento neste período</p>
                <p class="mt-1 text-xs text-slate-500">
                    Importe os espelhos de ponto e configure a escala da empresa para ver pontualidade, faltas e atestados.
                </p>
            </div>

            <div v-else class="grid gap-3 lg:grid-cols-3">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3">
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-talents-800">
                            Mais pontuais
                            <InfoTooltip text="Quem teve o maior percentual de dias cumpridos em relação aos dias úteis esperados no mês." />
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Do maior para o menor cumprimento</p>
                    </div>
                    <ol
                        class="space-y-2"
                        :class="rankingExpanded.pontualidade ? 'max-h-[28rem] overflow-auto pr-1' : ''"
                    >
                        <li
                            v-for="(row, idx) in visibleRanking(snapshot.ranking_pontualidade, 'pontualidade')"
                            :key="`pct-${row.id_person ?? idx}`"
                            class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2 text-sm"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-[10px] font-bold text-emerald-800">
                                    {{ String(idx + 1).padStart(2, '0') }}
                                </span>
                                <Link
                                    v-if="personHref(row)"
                                    :href="personHref(row)"
                                    class="truncate font-medium text-talents-800 hover:underline"
                                >
                                    {{ row.nome }}
                                </Link>
                                <span v-else class="truncate font-medium text-slate-800">{{ row.nome }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block tabular-nums text-xs font-semibold text-emerald-800">
                                    {{ formatPct(row.percentual) }}
                                </span>
                                <span class="block tabular-nums text-[10px] text-slate-500">
                                    {{ row.dias_cumpridos ?? 0 }}/{{ row.dias_esperados ?? 0 }} dias
                                </span>
                            </span>
                        </li>
                    </ol>
                    <button
                        v-if="snapshot.ranking_pontualidade.length > RANKING_PREVIEW"
                        type="button"
                        class="mt-3 w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-talents-800 ring-1 ring-talents-200 transition hover:bg-talents-50"
                        @click="rankingExpanded.pontualidade = !rankingExpanded.pontualidade"
                    >
                        {{ rankingToggleLabel(snapshot.ranking_pontualidade, 'pontualidade') }}
                    </button>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3">
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-talents-800">
                            Mais faltas não justificadas
                            <InfoTooltip text="Quem acumulou mais dias úteis sem marcação completa e sem justificativa válida." />
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Dias esperados sem marcação completa</p>
                    </div>
                    <template v-if="snapshot.ranking_faltas.length">
                        <ol
                            class="space-y-2"
                            :class="rankingExpanded.faltas ? 'max-h-[28rem] overflow-auto pr-1' : ''"
                        >
                            <li
                                v-for="(row, idx) in visibleRanking(snapshot.ranking_faltas, 'faltas')"
                                :key="`falt-${row.id_person ?? idx}`"
                                class="flex items-center justify-between gap-2 rounded-xl border border-rose-50 bg-rose-50/40 px-3 py-2 text-sm"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-100 text-[10px] font-bold text-rose-800">
                                        {{ String(idx + 1).padStart(2, '0') }}
                                    </span>
                                    <Link
                                        v-if="personHref(row)"
                                        :href="personHref(row)"
                                        class="truncate font-medium text-talents-800 hover:underline"
                                    >
                                        {{ row.nome }}
                                    </Link>
                                    <span v-else class="truncate font-medium text-slate-800">{{ row.nome }}</span>
                                </span>
                                <span class="shrink-0 tabular-nums text-sm font-semibold text-rose-800">
                                    {{ row.dias_faltas_nao_justificadas }}
                                </span>
                            </li>
                        </ol>
                        <button
                            v-if="snapshot.ranking_faltas.length > RANKING_PREVIEW"
                            type="button"
                            class="mt-3 w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-talents-800 ring-1 ring-talents-200 transition hover:bg-talents-50"
                            @click="rankingExpanded.faltas = !rankingExpanded.faltas"
                        >
                            {{ rankingToggleLabel(snapshot.ranking_faltas, 'faltas') }}
                        </button>
                    </template>
                    <p v-else class="rounded-xl bg-slate-50 px-3 py-6 text-center text-xs text-slate-500">
                        Nenhuma falta não justificada neste período.
                    </p>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3">
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-talents-800">
                            Mais atestados
                            <InfoTooltip text="Quem teve mais dias úteis cobertos por atestado no período." />
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Dias úteis cobertos por atestado</p>
                    </div>
                    <template v-if="snapshot.ranking_atestados.length">
                        <ol
                            class="space-y-2"
                            :class="rankingExpanded.atestados ? 'max-h-[28rem] overflow-auto pr-1' : ''"
                        >
                            <li
                                v-for="(row, idx) in visibleRanking(snapshot.ranking_atestados, 'atestados')"
                                :key="`atest-${row.id_person ?? idx}`"
                                class="flex items-center justify-between gap-2 rounded-xl border border-amber-50 bg-amber-50/50 px-3 py-2 text-sm"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-[10px] font-bold text-amber-900">
                                        {{ String(idx + 1).padStart(2, '0') }}
                                    </span>
                                    <Link
                                        v-if="personHref(row)"
                                        :href="personHref(row)"
                                        class="truncate font-medium text-talents-800 hover:underline"
                                    >
                                        {{ row.nome }}
                                    </Link>
                                    <span v-else class="truncate font-medium text-slate-800">{{ row.nome }}</span>
                                </span>
                                <span class="shrink-0 tabular-nums text-sm font-semibold text-amber-900">
                                    {{ row.dias_atestado_excluidos }}
                                </span>
                            </li>
                        </ol>
                        <button
                            v-if="snapshot.ranking_atestados.length > RANKING_PREVIEW"
                            type="button"
                            class="mt-3 w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-talents-800 ring-1 ring-talents-200 transition hover:bg-talents-50"
                            @click="rankingExpanded.atestados = !rankingExpanded.atestados"
                        >
                            {{ rankingToggleLabel(snapshot.ranking_atestados, 'atestados') }}
                        </button>
                    </template>
                    <p v-else class="rounded-xl bg-slate-50 px-3 py-6 text-center text-xs text-slate-500">
                        Nenhum dia de atestado no período.
                    </p>
                </section>
            </div>
        </template>
    </div>
</template>
