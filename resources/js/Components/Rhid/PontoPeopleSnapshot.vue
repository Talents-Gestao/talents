<script setup>
import InfoTooltip from '@/Components/InfoTooltip.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
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
    /** URL Ziggy ou string para GET detalhe diário (ini, fim, id_person). */
    detailUrl: { type: String, default: null },
    periodIni: { type: String, default: '' },
    periodFim: { type: String, default: '' },
    /** Quando true, a UI mostra que a seleção do mês disparou importação automática. */
    importingMonth: { type: Boolean, default: false },
    importStatusMessage: { type: String, default: '' },
});

const emit = defineEmits(['month-change', 'person-detail']);

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

const rulesList = [
    'Pontualidade média = média dos percentuais individuais de cumprimento no mês.',
    'Cumprimento de cada pessoa = dias com jornada completa ÷ dias úteis com espelho importado.',
    'Só entram dias já fechados (até ontem) e com espelho importado no sistema.',
    'Feriado, atestado e férias saem do denominador (não contam como falta nem como dia cumprido).',
    'Falta não justificada = dia útil com espelho, sem as 4 batidas analisáveis e sem exclusão válida.',
    'Dia sem espelho importado é lacuna de dados: não entra no cálculo e não vira falta.',
    'Atraso na entrada não vira falta: com as 4 marcações o dia ainda conta como cumprido (atrasos aparecem na aderência ao horário).',
];

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

const rulesModalOpen = ref(false);
const faltasModalOpen = ref(false);
const detailModalOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref(null);
const detailData = ref(null);

const openRulesModal = () => {
    rulesModalOpen.value = true;
};

const openFaltasModal = () => {
    faltasModalOpen.value = true;
};

const closeDetailModal = () => {
    detailModalOpen.value = false;
    detailLoading.value = false;
    detailError.value = null;
    detailData.value = null;
};

const openPersonDetail = async (row) => {
    if (row?.id_person == null) {
        return;
    }
    emit('person-detail', row);

    if (!props.detailUrl || !props.periodIni || !props.periodFim) {
        return;
    }

    detailModalOpen.value = true;
    detailLoading.value = true;
    detailError.value = null;
    detailData.value = null;
    try {
        const { data } = await axios.get(props.detailUrl, {
            params: {
                ini: props.periodIni,
                fim: props.periodFim,
                id_person: row.id_person,
            },
        });
        detailData.value = data;
    } catch (e) {
        detailError.value =
            e?.response?.data?.message ?? e?.message ?? 'Não foi possível carregar o detalhe do ponto.';
    } finally {
        detailLoading.value = false;
    }
};

const formatYmdPt = (ymd) => {
    if (!ymd || typeof ymd !== 'string') {
        return '—';
    }
    const p = ymd.split('-');
    if (p.length !== 3) {
        return ymd;
    }
    return `${p[2]}/${p[1]}/${p[0]}`;
};

const statusBadgeClass = (status) => {
    if (status === 'cumprido') {
        return 'bg-emerald-50 text-emerald-700';
    }
    if (status === 'falta') {
        return 'bg-rose-50 text-rose-700';
    }
    if (status === 'atestado' || status === 'ferias' || status === 'feriado') {
        return 'bg-sky-50 text-sky-700';
    }
    if (status === 'sem_espelho') {
        return 'bg-amber-50 text-amber-800';
    }
    return 'bg-slate-100 text-slate-600';
};

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

        <details class="rounded-xl border border-emerald-100 bg-emerald-50/40 px-4 py-3">
            <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-emerald-900">
                Regras do cálculo de pontualidade média
            </summary>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-xs text-emerald-900/80">
                <li v-for="(rule, ri) in rulesList" :key="`rule-${ri}`">
                    {{ rule }}
                </li>
            </ul>
        </details>

        <p v-if="diagnosticsHint && !importingMonth" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-100">
            {{ diagnosticsHint }}
        </p>

        <div
            v-if="importingMonth || importStatusMessage"
            class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-950"
        >
            <p class="font-medium">
                {{ importingMonth ? 'Importando espelhos do mês selecionado…' : importStatusMessage }}
            </p>
            <p v-if="importingMonth" class="mt-1 text-xs text-sky-800/80">
                Ao escolher o mês, buscamos os espelhos na RHID automaticamente. Isso pode levar alguns minutos na fila.
            </p>
        </div>

        <div v-if="loading && !people" class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="i in 3"
                :key="`kpi-skel-${i}`"
                class="h-32 animate-pulse rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50 to-slate-100"
            />
        </div>

        <template v-else>
            <div class="grid gap-3 sm:grid-cols-3">
                <button
                    type="button"
                    class="group relative overflow-visible rounded-2xl bg-gradient-to-br from-talents-700 via-talents-600 to-talents-500 p-4 text-left text-white shadow-md transition hover:brightness-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                    @click="openRulesModal"
                >
                    <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10 blur-xl" />
                    <div class="relative z-10">
                        <p class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wider text-white/75">
                            Pontualidade média
                            <InfoTooltip
                                tone="onDark"
                                text="Clique para ver as regras do cálculo. Percentual médio de dias com jornada completa nos dias úteis com espelho importado."
                            />
                        </p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">
                            {{ snapshot.has_data ? formatPct(snapshot.punctuality_avg_percent) : '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-white/80">
                            Clique para ver as regras · média do cumprimento nos dias com espelho
                        </p>
                    </div>
                </button>
                <button
                    type="button"
                    class="group relative overflow-visible rounded-2xl bg-gradient-to-br from-talents-800 via-talents-700 to-talents-600 p-4 text-left text-white shadow-md transition hover:brightness-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/80"
                    @click="openFaltasModal"
                >
                    <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10 blur-xl" />
                    <div class="relative z-10">
                        <p class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wider text-white/75">
                            Faltas não justificadas
                            <InfoTooltip
                                tone="onDark"
                                text="Clique para detalhar. Dias úteis com espelho, sem jornada completa e sem atestado, férias ou feriado."
                            />
                        </p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">
                            {{ snapshot.has_data ? snapshot.unjustified_absence_days : '—' }}
                        </p>
                        <p class="mt-0.5 text-xs text-white/80">
                            Clique para detalhar · só dias com espelho importado
                        </p>
                    </div>
                </button>
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
                <template v-if="snapshot.resumo.dias_uteis_sem_espelho_pessoa">
                    · {{ snapshot.resumo.dias_uteis_sem_espelho_pessoa }} lacuna(s) sem espelho
                </template>
                · {{ snapshot.evaluated_count }} colaborador(es) com espelho
            </p>

            <div
                v-if="!snapshot.has_data"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center"
            >
                <p class="text-sm font-medium text-slate-700">Sem dados de cumprimento neste período</p>
                <p class="mt-1 text-xs text-slate-500">
                    Ao selecionar o mês, a importação dos espelhos é enfileirada automaticamente. Também há importação
                    diária após 00:30 (dia fechado). Confira a escala da empresa se o período continuar vazio.
                </p>
            </div>

            <div v-else class="grid gap-3 lg:grid-cols-3">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3">
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-talents-800">
                            Mais pontuais
                            <InfoTooltip text="Quem teve o maior percentual de dias cumpridos. Clique na linha para ver o ponto dia a dia." />
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Clique para detalhar o ponto · maior cumprimento</p>
                    </div>
                    <ol
                        class="space-y-2"
                        :class="rankingExpanded.pontualidade ? 'max-h-[28rem] overflow-auto pr-1' : ''"
                    >
                        <li
                            v-for="(row, idx) in visibleRanking(snapshot.ranking_pontualidade, 'pontualidade')"
                            :key="`pct-${row.id_person ?? idx}`"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2 text-left text-sm transition hover:border-talents-200 hover:bg-talents-50/60"
                                @click="openPersonDetail(row)"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-[10px] font-bold text-emerald-800">
                                        {{ String(idx + 1).padStart(2, '0') }}
                                    </span>
                                    <span class="truncate font-medium text-talents-800">{{ row.nome }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block tabular-nums text-xs font-semibold text-emerald-800">
                                        {{ formatPct(row.percentual) }}
                                    </span>
                                    <span class="block tabular-nums text-[10px] text-slate-500">
                                        {{ row.dias_cumpridos ?? 0 }}/{{ row.dias_esperados ?? 0 }} dias
                                    </span>
                                </span>
                            </button>
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
                            <InfoTooltip text="Quem acumulou mais dias úteis com espelho sem marcação completa e sem justificativa. Clique para detalhar." />
                        </p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Clique para detalhar · dias com espelho incompletos</p>
                    </div>
                    <template v-if="snapshot.ranking_faltas.length">
                        <ol
                            class="space-y-2"
                            :class="rankingExpanded.faltas ? 'max-h-[28rem] overflow-auto pr-1' : ''"
                        >
                            <li
                                v-for="(row, idx) in visibleRanking(snapshot.ranking_faltas, 'faltas')"
                                :key="`falt-${row.id_person ?? idx}`"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 rounded-xl border border-rose-50 bg-rose-50/40 px-3 py-2 text-left text-sm transition hover:border-rose-200 hover:bg-rose-50"
                                    @click="openPersonDetail(row)"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-100 text-[10px] font-bold text-rose-800">
                                            {{ String(idx + 1).padStart(2, '0') }}
                                        </span>
                                        <span class="truncate font-medium text-talents-800">{{ row.nome }}</span>
                                    </span>
                                    <span class="shrink-0 tabular-nums text-sm font-semibold text-rose-800">
                                        {{ row.dias_faltas_nao_justificadas }}
                                    </span>
                                </button>
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

        <Modal :show="rulesModalOpen" max-width="lg" @close="rulesModalOpen = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Regras — Pontualidade média</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Como calculamos o indicador a partir dos espelhos importados
                    <template v-if="monthLabel">· {{ monthLabel }}</template>
                </p>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-700">
                    <li v-for="(rule, ri) in rulesList" :key="`modal-rule-${ri}`">
                        {{ rule }}
                    </li>
                </ul>
                <div class="mt-4 flex justify-end">
                    <SecondaryButton type="button" @click="rulesModalOpen = false">Fechar</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="faltasModalOpen" max-width="lg" @close="faltasModalOpen = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Faltas não justificadas</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Total no mês:
                    <span class="font-semibold text-rose-800">{{ snapshot.unjustified_absence_days }}</span>
                    dia(s) · só entram dias com espelho importado, sem jornada completa e sem atestado/férias/feriado.
                </p>
                <p class="mt-2 text-xs text-slate-500">
                    Dias sem espelho não entram nesta conta (importe o mês para preencher lacunas).
                </p>
                <ul v-if="snapshot.ranking_faltas.length" class="mt-4 max-h-80 space-y-2 overflow-auto">
                    <li
                        v-for="(row, idx) in snapshot.ranking_faltas"
                        :key="`falt-modal-${row.id_person ?? idx}`"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-xl border border-rose-100 bg-rose-50/50 px-3 py-2 text-left text-sm hover:bg-rose-50"
                            @click="faltasModalOpen = false; openPersonDetail(row)"
                        >
                            <span class="truncate font-medium text-slate-800">{{ row.nome }}</span>
                            <span class="shrink-0 tabular-nums font-semibold text-rose-800">
                                {{ row.dias_faltas_nao_justificadas }}
                            </span>
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-4 rounded-xl bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                    Nenhuma falta não justificada neste período.
                </p>
                <div class="mt-4 flex justify-end">
                    <SecondaryButton type="button" @click="faltasModalOpen = false">Fechar</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="detailModalOpen" max-width="2xl" @close="closeDetailModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ detailData?.nome || 'Detalhe do ponto' }}
                </h3>
                <p v-if="detailLoading" class="mt-2 text-sm text-slate-500">Carregando marcações…</p>
                <p v-else-if="detailError" class="mt-2 text-sm text-rose-700">{{ detailError }}</p>
                <template v-else-if="detailData">
                    <p class="mt-1 text-xs text-slate-500">
                        {{ formatYmdPt(detailData.periodo?.ini) }} a {{ formatYmdPt(detailData.periodo?.fim) }}
                        · tolerância {{ detailData.tolerancia_minutos }} min
                    </p>
                    <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-4 text-center">
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Cumprimento no período</p>
                        <p class="mt-1 text-4xl font-bold tabular-nums text-emerald-800">
                            {{ formatPct(detailData.resumo?.percentual) }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-700/80">
                            {{ detailData.resumo?.dias_cumpridos ?? 0 }} de
                            {{ detailData.resumo?.dias_esperados ?? 0 }} dia(s) com espelho e jornada completa
                        </p>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <div class="rounded-lg bg-emerald-50 px-3 py-2.5">
                            <p class="text-emerald-600/80">Cumpridos</p>
                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-800">
                                {{ detailData.resumo?.dias_cumpridos ?? 0 }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-rose-50 px-3 py-2.5">
                            <p class="text-rose-600/80">Faltas</p>
                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-rose-800">
                                {{ detailData.resumo?.dias_faltas_nao_justificadas ?? 0 }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-amber-50 px-3 py-2.5">
                            <p class="text-amber-700/80">Atestados</p>
                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-amber-900">
                                {{ detailData.resumo?.dias_atestado ?? 0 }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                            <p class="text-slate-400">Sem espelho</p>
                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-800">
                                {{ detailData.resumo?.dias_sem_espelho ?? 0 }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Detalhe por dia</p>
                        <div class="max-h-[min(28rem,55vh)] space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="(dia, di) in detailData.dias || []"
                                :key="`dia-${dia.ref_date}-${di}`"
                                class="rounded-xl border border-slate-200 p-3"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold capitalize text-slate-900">
                                            {{ dia.label || formatYmdPt(dia.ref_date) }}
                                        </p>
                                        <span
                                            class="mt-1 inline-flex items-center rounded px-2 py-0.5 text-[10px] font-medium"
                                            :class="statusBadgeClass(dia.status)"
                                        >
                                            {{ dia.status_label }}
                                        </span>
                                    </div>
                                    <div class="shrink-0 text-right text-[10px]">
                                        <p v-if="dia.status === 'cumprido'" class="font-bold text-emerald-700">Conta</p>
                                        <p v-else-if="dia.status === 'falta'" class="font-bold text-rose-700">Falta</p>
                                        <p v-else class="text-slate-400">Fora</p>
                                    </div>
                                </div>
                                <p v-if="dia.explicacao" class="mt-2 text-[11px] text-slate-500">{{ dia.explicacao }}</p>
                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                    <p class="tabular-nums text-slate-700">ENT.1 {{ dia.ent_1 || '—' }}</p>
                                    <p class="tabular-nums text-slate-700">SAI.1 {{ dia.sai_1 || '—' }}</p>
                                    <p class="tabular-nums text-slate-700">ENT.2 {{ dia.ent_2 || '—' }}</p>
                                    <p class="tabular-nums text-slate-700">SAI.2 {{ dia.sai_2 || '—' }}</p>
                                </div>
                            </div>
                            <p
                                v-if="!(detailData.dias || []).length"
                                class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500"
                            >
                                Nenhum dia no período.
                            </p>
                        </div>
                    </div>
                </template>
                <div class="mt-4 flex justify-end">
                    <SecondaryButton type="button" @click="closeDetailModal">Fechar</SecondaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
