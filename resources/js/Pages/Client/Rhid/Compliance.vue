<script setup>
import RhidResponsePanel from '@/Components/Rhid/RhidResponsePanel.vue';
import OverviewSection from '@/Pages/Client/Rhid/Compliance/OverviewSection.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    extractListItems,
    formatRhidBankBalanceDisplay,
    formatRhidBankBalanceMinutes,
    monthRangeHtmlDates,
    parseRhidBankBalanceMinutes,
    pickRhidPersonDisplayName,
    formatPeriodPtBr,
    todayHtmlDate,
    toRhidYmd,
} from '@/utils/rhidDate';
import {
    buildJustificationTypeMapFromPayload,
    buildPersonDepartmentMapFromPayload,
    chartColorAt,
    departmentLabelForJustification,
    isAtestadoByKeyword,
    justificationTypeLabel,
    JUST_ANALYTICS_MAX_PAGES,
    JUST_MAX_DEPT_CHART,
    JUST_TOP_COLLABORATORS,
} from '@/utils/rhidJustificationsAnalytics';

const page = usePage();

const props = defineProps({
    configured: { type: Boolean, required: true },
});

const tab = ref('overview');
const err = ref(null);
const loading = ref(false);
/** Detalhes tecnicos (admin): endpoints, GUID, JSON bruto */
const supportMode = ref(false);

const lastPunches = ref([]);

/** Painel | aderência | espelho | export (relatórios RHID) */
const punchesSubTab = ref('dashboard');
const lastPunchesUpdatedAt = ref(null);

const bankDateHtml = ref(todayHtmlDate());
const bankResult = ref(null);
/** Filtros opcionais do GET person_banco_horas (inteiros RHID) */
const bankFilterCompanies = ref('');
const bankFilterCostcenters = ref('');
const bankFilterDepartments = ref('');
const bankFilterPerson = ref('');
const bankFilterPersonroles = ref('');
/** Filtros amigáveis (valores = id RHID ou '') */
const bankSelDepartment = ref('');
const bankSelPersonRole = ref('');
const bankSelPerson = ref('');

const rhidDepartmentsPayload = ref(null);
const rhidPersonRolesPayload = ref(null);
const rhidPeopleForFiltersPayload = ref(null);
const rhidMetaLoading = ref(false);

const overviewLoading = ref(false);
const overviewLoadedAt = ref(null);
const overviewPunchesSample = ref(null);
const overviewBankRows = ref([]);
const overviewAdherence = ref(null);
const overviewJustTotal = ref(null);
const overviewJustAtestados = ref(null);
const overviewJustNote = ref('');

const peopleList = ref(null);

const reportGuid = ref('');
const reportPercent = ref(null);
const { first: monthFirst, last: monthLast } = monthRangeHtmlDates();
const reportIniDate = ref(monthFirst);
const reportFimDate = ref(monthLast);
const reportFormato = ref('PDF');
const reportNome = ref('espelho');
const reportJsonOverride = ref('');

const reportIni = computed(() => toRhidYmd(reportIniDate.value));
const reportFim = computed(() => toRhidYmd(reportFimDate.value));

/** Espelho de ponto (POST report.svc/ponto — mesmas rotas que Relatórios) */
const espelhoGuid = ref('');
const espelhoPercent = ref(null);
const espelhoPanelData = ref(null);
const espelhoIniDate = ref(monthFirst);
const espelhoFimDate = ref(monthLast);
/** '' = todos; 1 = ativos; 2 = inativos — padrão ativos (evita exceder limite da licença RHID) */
const espelhoStatus = ref('1');
const espelhoSelectedFields = ref(['TODAS_MARCACOES', 'ENTRADAS_SAIDAS']);
const espelhoFilterPeople = ref('');
const espelhoFilterCompanies = ref('');
const espelhoFilterDepartments = ref('');
const espelhoFilterCostcenters = ref('');
const espelhoFilterPersonroles = ref('');
const espelhoFilterShifts = ref('');
const espelhoPolling = ref(false);
const espelhoPollCancelRequested = ref(false);
/** ID RHID do colaborador para vincular ao PDF salvo (alternativa a um único ID em Filtros opcionais) */
const espelhoVinculoPersonId = ref('');
/** Listagem paginada de imports (Laravel paginator JSON) */
const espelhoImportsPage = ref(null);
/** Último import criado ou detalhe expandido */
const espelhoLastImport = ref(null);
const espelhoDetailLoading = ref(false);
const espelhoBatchProgress = ref('');
/** idle | resolving | starting | polling | done */
const espelhoBatchPhase = ref('idle');
const espelhoBatchTotal = ref(0);
const espelhoBatchRemaining = ref(0);
const espelhoBatchRemote = ref(null);
const espelhoBatchPollAbort = ref(false);
const espelhoShowTechnicalPanel = ref(false);

/** Aderência espelho importado vs horários da empresa */
const espelhoAdherenceIni = ref(monthFirst);
const espelhoAdherenceFim = ref(monthLast);
const espelhoAdherenceLoading = ref(false);
const espelhoAdherenceResult = ref(null);
/** Modal: marcações do espelho no período da análise */
const espelhoAdherenceMarksOpen = ref(false);
const espelhoAdherenceMarksLoading = ref(false);
const espelhoAdherenceMarksError = ref(null);
const espelhoAdherenceMarksData = ref(null);

const espelhoParseLabel = (s) => {
    if (s === 'ok') {
        return 'Pronto';
    }
    if (s === 'pending') {
        return 'Em processamento';
    }
    if (s === 'failed') {
        return 'Com erro';
    }
    return s ?? '—';
};

const ESPELHO_FIELD_OPTIONS = [
    { value: 'DIA_DA_SEMANA', label: 'Dia da semana' },
    { value: 'TODAS_MARCACOES', label: 'Todas as marcações' },
    { value: 'ENTRADAS_SAIDAS', label: 'Entradas / saídas' },
    { value: 'MARCACOES_DESCONSIDERADAS', label: 'Marcações desconsideradas' },
];

const isAdmin = computed(() => page.props.auth?.user?.role === 'company_admin');

const espelhoTargetPersonId = computed(() => {
    const manualIds = parseIdList(espelhoVinculoPersonId.value);
    if (manualIds?.length === 1) {
        return manualIds[0];
    }
    const ids = parseIdList(espelhoFilterPeople.value);
    if (ids?.length === 1) {
        return ids[0];
    }

    return null;
});

/** Percentual do RHID pode vir como número ou string */
const espelhoIsReadyForDownload = computed(() => {
    const p = Number(espelhoPercent.value);
    return Boolean(espelhoGuid.value) && !Number.isNaN(p) && p >= 100;
});

const espelhoShowProcessingBanner = computed(() => {
    if (espelhoPolling.value) {
        return true;
    }
    return ['resolving', 'starting', 'polling'].includes(espelhoBatchPhase.value);
});

const espelhoBatchProgressPercent = computed(() => {
    const t = Number(espelhoBatchTotal.value);
    const r = Number(espelhoBatchRemaining.value);
    if (!t || t <= 0) {
        return 0;
    }
    return Math.min(100, Math.round(((t - r) / t) * 100));
});

/** Pares ENT.n/SAÍ.n alinhados ao parser Python (`marcacoes_string_to_ent_sai_slots`) */
const ESPELHO_SLOT_KEYS = ['ent_1', 'sai_1', 'ent_2', 'sai_2', 'ent_3', 'sai_3', 'ent_4', 'sai_4'];

const espelhoSlotColumns = [
    { key: 'ent_1', label: 'ENT. 1' },
    { key: 'sai_1', label: 'SAÍ. 1' },
    { key: 'ent_2', label: 'ENT. 2' },
    { key: 'sai_2', label: 'SAÍ. 2' },
    { key: 'ent_3', label: 'ENT. 3' },
    { key: 'sai_3', label: 'SAÍ. 3' },
    { key: 'ent_4', label: 'ENT. 4' },
    { key: 'sai_4', label: 'SAÍ. 4' },
];

/**
 * @param {Record<string, unknown>|null|undefined} frag — fragmento em `row_json.colaboradores[]`
 * @returns {Record<string, string>}
 */
const espelhoMarcacaoSlots = (frag) => {
    const out = {};
    for (const k of ESPELHO_SLOT_KEYS) {
        const v = frag?.[k];
        out[k] = v != null && String(v).trim() !== '' ? String(v).trim() : '';
    }
    const needsFallback = ESPELHO_SLOT_KEYS.every((k) => !out[k]);
    if (needsFallback && frag?.marcacoes) {
        const times = String(frag.marcacoes).match(/\b\d{2}:\d{2}\b/g) || [];
        for (let i = 0; i < Math.min(8, times.length); i += 1) {
            const pair = Math.floor(i / 2) + 1;
            const key = i % 2 === 0 ? `ent_${pair}` : `sai_${pair}`;
            out[key] = times[i];
        }
    }
    return out;
};

/** Nome / CPF / período para o bloco de resumo (primeiro fragmento parseado) */
const espelhoExtractHeader = computed(() => {
    const imp = espelhoLastImport.value;
    const empty = {
        nome: '—',
        cpf: '—',
        period_ini: imp?.period_ini ?? '',
        period_fim: imp?.period_fim ?? '',
    };
    if (!imp?.days?.length) {
        return empty;
    }
    const rj = imp.days[0].row_json;
    const colabs = rj?.colaboradores;
    const first = Array.isArray(colabs) && colabs.length ? colabs[0] : null;
    return {
        nome: (first?.nome && String(first.nome).trim()) || '—',
        cpf: (first?.cpf && String(first.cpf).trim()) || '—',
        period_ini: imp.period_ini ?? '',
        period_fim: imp.period_fim ?? '',
    };
});

/** Uma linha por (dia × colaborador no PDF) para a tabela de marcações */
const espelhoPunchTableRows = computed(() => {
    const imp = espelhoLastImport.value;
    if (!imp?.days?.length) {
        return [];
    }
    const rows = [];
    for (const d of imp.days) {
        const rj = d.row_json || {};
        const colabs = Array.isArray(rj.colaboradores) ? rj.colaboradores : [];
        if (!colabs.length) {
            rows.push({ ref_date: d.ref_date, nome: '', fragment: {} });
            continue;
        }
        for (const c of colabs) {
            rows.push({
                ref_date: d.ref_date,
                nome: (c.nome && String(c.nome).trim()) || '—',
                fragment: c,
            });
        }
    }
    return rows;
});

const bankRows = computed(() => {
    const r = bankResult.value;
    if (!r || !Array.isArray(r.rows)) {
        return [];
    }
    return r.rows;
});

const peopleRows = computed(() => extractListItems(peopleList.value));

const tabs = [
    { id: 'overview', label: 'Visão geral' },
    { id: 'punches', label: 'Marcações e aderência' },
    { id: 'bank', label: 'Banco de horas' },
    { id: 'justifications', label: 'Justificativas' },
    { id: 'collaborators', label: 'Colaboradores' },
];

const justIniDate = ref(monthFirst);
const justFimDate = ref(monthLast);
const justPage = ref(0);
const justMaxSize = ref(100);
const justFilterCompanies = ref('');
const justFilterCostcenters = ref('');
const justFilterDepartments = ref('');
const justFilterPersonroles = ref('');
const justFilterPeople = ref('');
const justFilterShifts = ref('');
const justFilterJustificationTypes = ref('');
/** Multi-select (ids RHID) sincronizados com justFilter* (string) */
const justMultiDepartments = ref([]);
const justMultiPersonroles = ref([]);
const justMultiPeople = ref([]);

watch(
    justMultiDepartments,
    (arr) => {
        justFilterDepartments.value = Array.isArray(arr) && arr.length ? arr.join(', ') : '';
    },
    { deep: true },
);
watch(
    justMultiPersonroles,
    (arr) => {
        justFilterPersonroles.value = Array.isArray(arr) && arr.length ? arr.join(', ') : '';
    },
    { deep: true },
);
watch(
    justMultiPeople,
    (arr) => {
        justFilterPeople.value = Array.isArray(arr) && arr.length ? arr.join(', ') : '';
    },
    { deep: true },
);

const justResult = ref(null);
/** Período completo (multi-página RHID); tabela e gráficos derivam disto */
const justAnalyticsRows = ref([]);
/** True após concluir uma listagem (mesmo com 0 registros) */
const justListLoaded = ref(false);
const justAnalyticsMeta = ref({
    pagesLoaded: 0,
    truncated: false,
    mergedTotal: 0,
    recordsTotalFromApi: null,
});
const justificationTypeMap = ref({});
const personDeptMapForJust = ref({});
const justChartDrilldown = ref(null);

const parseIdList = (raw) => {
    const t = String(raw ?? '').trim();
    if (!t) {
        return null;
    }
    const parts = t.split(/[\s,;]+/);
    const ids = [];
    for (const p of parts) {
        const n = parseInt(p.trim(), 10);
        if (!Number.isNaN(n)) {
            ids.push(n);
        }
    }
    return ids.length ? ids : null;
};

const justPageSize = computed(() => Math.min(500, Math.max(1, Number(justMaxSize.value) || 100)));

const justRows = computed(() => {
    const all = justAnalyticsRows.value;
    if (!all.length) {
        return [];
    }
    const size = justPageSize.value;
    const start = justPage.value * size;
    return all.slice(start, start + size);
});

const justRecordsTotal = computed(() => {
    const n = justAnalyticsRows.value.length;
    return justListLoaded.value ? n : null;
});

const canJustPrevPage = computed(() => justPage.value > 0);

const canJustNextPage = computed(() => {
    const total = justAnalyticsRows.value.length;
    const size = justPageSize.value;
    const page = justPage.value;
    return (page + 1) * size < total;
});

const clearErr = () => {
    err.value = null;
};

const handleError = (e) => {
    err.value = e.response?.data?.message || e.message || 'Erro na requisição';
};

const personDisplayName = (row) =>
    row?.name ?? row?.nome ?? row?.strName ?? row?.personName ?? (row?.id != null ? `Colaborador #${row.id}` : '—');

const personMatricula = (row) =>
    row?.registration ?? row?.matricula ?? row?.pis ?? row?.strMatricula ?? row?.strPis ?? '—';

const bankDisplayName = pickRhidPersonDisplayName;
const bankDisplayValue = formatRhidBankBalanceDisplay;

/**
 * ID do colaborador no RHID (mesmo usado em GET person.svc/a/{id} e na rota do perfil).
 * Preferir `id` na raiz: na lista de pessoas o link do perfil usa `row.id`; `idPerson` pode
 * divergir em alguns payloads e o lote de preferência gravaria outro id_person.
 */
const rhidPersonId = (row) => {
    const raw = row?.id != null && row?.id !== '' ? row.id : row?.idPerson;
    if (raw == null || raw === '') {
        return null;
    }
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
};

/** RHID: 1 = ativo, 2 = inativo (mesmo conceito do filtro do espelho). */
const isRhidPersonRowClearlyInactive = (row) => {
    if (!row || typeof row !== 'object') {
        return false;
    }
    const s = row.status;
    if (s === 2 || s === '2') {
        return true;
    }
    const st = String(row.statusStr ?? '').toLowerCase();
    return st.includes('inativ');
};

const bankHasOptionalFilters = () =>
    [bankFilterCompanies, bankFilterCostcenters, bankFilterDepartments, bankFilterPerson, bankFilterPersonroles].some(
        (r) => String(r.value ?? '').trim() !== '',
    );

const bankRowDepartamento = (row) =>
    row?.departmentName || (row?.idDepartment != null ? `#${row.idDepartment}` : '—');

const bankRowCargo = (row) => row?.roleName || (row?.idPersonRole != null ? `#${row.idPersonRole}` : '—');

const MAX_DEPT_CHART = 12;
const TOP_DEBIT_N = 10;
const TOP_CREDIT_N = 10;

/** Detalhe ao clicar num gráfico: lista de colaboradores */
const bankChartDrilldown = ref(null);

const closeBankChartDrilldown = () => {
    bankChartDrilldown.value = null;
};

const bankRowToDrillLine = (row) => {
    const m = parseRhidBankBalanceMinutes(row);
    return {
        name: bankDisplayName(row),
        balance: bankDisplayValue(row),
        balanceMin: m,
        dept: bankRowDepartamento(row),
        role: bankRowCargo(row),
        cpf: row.cpf != null && String(row.cpf).trim() !== '' ? String(row.cpf) : '—',
        personId: rhidPersonId(row),
    };
};

/** Fatias do donut com linhas para drill-down (mesma ordem do gráfico). */
const bankDonutSlices = computed(() => {
    const rows = bankRows.value;
    const defs = [
        { label: 'Saldo negativo', color: '#ef4444', test: (m) => m !== null && m < 0 },
        { label: 'Saldo zero', color: '#94a3b8', test: (m) => m !== null && m === 0 },
        { label: 'Saldo positivo', color: '#10b981', test: (m) => m !== null && m > 0 },
        { label: 'Sem dado numérico', color: '#f59e0b', test: (m) => m === null },
    ];
    const out = [];
    for (const d of defs) {
        const sliceRows = rows.filter((row) => d.test(parseRhidBankBalanceMinutes(row)));
        if (sliceRows.length) {
            out.push({ label: d.label, color: d.color, count: sliceRows.length, rows: sliceRows });
        }
    }
    return out;
});

const openBankDrilldownFromDonut = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const s = bankDonutSlices.value[i];
    if (!s) {
        return;
    }
    const lines = s.rows.map(bankRowToDrillLine).sort((a, b) => {
        const ma = a.balanceMin;
        const mb = b.balanceMin;
        if (ma == null && mb == null) {
            return a.name.localeCompare(b.name, 'pt');
        }
        if (ma == null) {
            return 1;
        }
        if (mb == null) {
            return -1;
        }
        if (ma !== mb) {
            return ma - mb;
        }
        return a.name.localeCompare(b.name, 'pt');
    });
    bankChartDrilldown.value = {
        title: s.label,
        subtitle: `Data ${bankDateHtml.value} · ${s.count} colaborador(es) nesta faixa`,
        lines,
    };
};

const bankDonutChart = computed(() => {
    const slices = bankDonutSlices.value;
    const series = slices.map((s) => s.count);
    const labels = slices.map((s) => s.label);
    const colors = slices.map((s) => s.color);
    const total = series.reduce((a, b) => a + b, 0);
    const options = {
        chart: {
            type: 'donut',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openBankDrilldownFromDonut,
            },
        },
        labels,
        colors,
        legend: { position: 'bottom', fontSize: '13px' },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: total > 0,
                        total: {
                            show: true,
                            label: 'Colaboradores',
                            formatter: () => String(total),
                        },
                    },
                },
            },
        },
        dataLabels: { enabled: true, style: { fontSize: '11px' } },
        tooltip: {
            y: {
                formatter: (val) => {
                    const pct = total ? ((Number(val) / total) * 100).toFixed(1) : '0';
                    return `${val} (${pct}%) — clique para listar`;
                },
            },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return { series, options };
});

/** Barras de departamento com média e lista de colaboradores. */
const bankDeptSlices = computed(() => {
    const map = new Map();
    for (const row of bankRows.value) {
        const m = parseRhidBankBalanceMinutes(row);
        if (m === null) {
            continue;
        }
        const name = bankRowDepartamento(row);
        if (!map.has(name)) {
            map.set(name, { sum: 0, count: 0, rows: [] });
        }
        const g = map.get(name);
        g.sum += m;
        g.count += 1;
        g.rows.push(row);
    }
    const entries = [...map.entries()].map(([name, { sum, count, rows }]) => ({
        name,
        sum,
        count,
        rows,
        avg: count ? sum / count : 0,
    }));
    entries.sort((a, b) => Math.abs(b.avg) - Math.abs(a.avg));
    const top = entries.slice(0, MAX_DEPT_CHART);
    const rest = entries.slice(MAX_DEPT_CHART);
    let final = top;
    if (rest.length) {
        let outSum = 0;
        let outCount = 0;
        const mergedRows = [];
        for (const e of rest) {
            outSum += e.sum;
            outCount += e.count;
            mergedRows.push(...e.rows);
        }
        final = [
            ...top,
            {
                name: 'Outros',
                sum: outSum,
                count: outCount,
                rows: mergedRows,
                avg: outCount ? outSum / outCount : 0,
            },
        ];
    }
    return final;
});

const openBankDrilldownFromDept = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const slice = bankDeptSlices.value[i];
    if (!slice) {
        return;
    }
    const lines = slice.rows.map(bankRowToDrillLine).sort((a, b) => {
        const ma = a.balanceMin ?? 0;
        const mb = b.balanceMin ?? 0;
        if (ma !== mb) {
            return ma - mb;
        }
        return a.name.localeCompare(b.name, 'pt');
    });
    const avgRounded = Math.round(slice.avg);
    bankChartDrilldown.value = {
        title: slice.name,
        subtitle: `Data ${bankDateHtml.value} · Média ${formatRhidBankBalanceMinutes(avgRounded)} (${slice.count} colaborador(es) com saldo numérico neste agrupamento)`,
        lines,
    };
};

const bankDeptAvgChart = computed(() => {
    const final = bankDeptSlices.value;
    const categories = final.map((e) => e.name);
    const data = final.map((e) => Math.round(e.avg));
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openBankDrilldownFromDept,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '70%',
                dataLabels: { position: 'right' },
            },
        },
        colors: ['#632a7e'],
        dataLabels: {
            enabled: true,
            formatter: (val) => formatRhidBankBalanceMinutes(val),
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        tooltip: {
            y: {
                formatter: (val) => `${formatRhidBankBalanceMinutes(val)} (média) — clique para listar`,
            },
        },
        yaxis: { labels: { maxWidth: 200 } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Media (min)', data }],
        options,
        empty: data.length === 0,
    };
});

const bankTopDebitScored = computed(() =>
    bankRows.value
        .map((row) => ({ row, m: parseRhidBankBalanceMinutes(row) }))
        .filter(({ m }) => m !== null && m < 0)
        .sort((a, b) => a.m - b.m)
        .slice(0, TOP_DEBIT_N),
);

const openBankDrilldownFromDebit = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const item = bankTopDebitScored.value[i];
    if (!item) {
        return;
    }
    const line = bankRowToDrillLine(item.row);
    bankChartDrilldown.value = {
        title: 'Maior debito — detalhe',
        subtitle: `${line.name} · Data ${bankDateHtml.value} · Saldo ${line.balance}`,
        lines: [line],
    };
};

const bankTopDebitChart = computed(() => {
    const scored = bankTopDebitScored.value;
    const categories = scored.map(({ row }) => {
        const n = bankDisplayName(row);
        return n.length > 36 ? `${n.slice(0, 34)}…` : n;
    });
    const data = scored.map(({ m }) => m);
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openBankDrilldownFromDebit,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '72%',
                dataLabels: { position: 'left' },
            },
        },
        colors: ['#b91c1c'],
        dataLabels: {
            enabled: true,
            formatter: (val) => formatRhidBankBalanceMinutes(val),
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        tooltip: {
            y: {
                formatter: (val) => `${formatRhidBankBalanceMinutes(val)} — clique para detalhe`,
            },
        },
        yaxis: { labels: { maxWidth: 220 } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Saldo', data }],
        options,
        empty: data.length === 0,
    };
});

const bankTopCreditScored = computed(() =>
    bankRows.value
        .map((row) => ({ row, m: parseRhidBankBalanceMinutes(row) }))
        .filter(({ m }) => m !== null && m > 0)
        .sort((a, b) => b.m - a.m)
        .slice(0, TOP_CREDIT_N),
);

const openBankDrilldownFromCredit = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const item = bankTopCreditScored.value[i];
    if (!item) {
        return;
    }
    const line = bankRowToDrillLine(item.row);
    bankChartDrilldown.value = {
        title: 'Maior saldo — detalhe',
        subtitle: `${line.name} · Data ${bankDateHtml.value} · Saldo ${line.balance}`,
        lines: [line],
    };
};

const bankTopCreditChart = computed(() => {
    const scored = bankTopCreditScored.value;
    const categories = scored.map(({ row }) => {
        const n = bankDisplayName(row);
        return n.length > 36 ? `${n.slice(0, 34)}…` : n;
    });
    const data = scored.map(({ m }) => m);
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openBankDrilldownFromCredit,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '72%',
                dataLabels: { position: 'right' },
            },
        },
        colors: ['#047857'],
        dataLabels: {
            enabled: true,
            formatter: (val) => formatRhidBankBalanceMinutes(val),
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        tooltip: {
            y: {
                formatter: (val) => `${formatRhidBankBalanceMinutes(val)} — clique para detalhe`,
            },
        },
        yaxis: { labels: { maxWidth: 220 } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Saldo', data }],
        options,
        empty: data.length === 0,
    };
});

const normalizeLastPunchesPayload = (data) => {
    if (Array.isArray(data)) {
        return data;
    }
    if (data && typeof data === 'object') {
        if (Array.isArray(data.rows)) {
            return data.rows;
        }
        if (Array.isArray(data.data)) {
            return data.data;
        }
        if (Array.isArray(data.aaData)) {
            return data.aaData;
        }
        if (Array.isArray(data.d)) {
            return data.d;
        }
    }
    return [];
};

const pickPunchNome = (row) => String(row?.nome ?? row?.Nome ?? row?.name ?? '').trim() || '—';

const pickPunchDataRaw = (row) =>
    row?.data ?? row?.Data ?? row?.dataHora ?? row?.dateTime ?? row?.dt ?? row?.marcacao ?? '';

const pickPunchPersonId = (row) => {
    const candidates = [
        row?.idPerson,
        row?.IdPerson,
        row?.id_pessoa,
        row?.idPessoa,
        row?.IdPessoa,
        row?.id,
        row?.Id,
    ];
    for (const c of candidates) {
        if (c == null || c === '') {
            continue;
        }
        const n = parseInt(String(c), 10);
        if (!Number.isNaN(n) && n > 0) {
            return n;
        }
    }
    return null;
};

const pickPunchIdLabel = (row, index) => {
    const v = row?.id ?? row?.Id;
    if (v != null && String(v).trim() !== '') {
        return String(v);
    }
    return `—`;
};

const formatPunchDateTimePtBr = (raw) => {
    const s = String(raw ?? '').trim();
    if (!s) {
        return '—';
    }
    const t = Date.parse(s);
    if (!Number.isNaN(t)) {
        return new Date(t).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' });
    }
    return s;
};

const punchExtractHour = (dataRaw) => {
    const s = String(dataRaw ?? '').trim();
    if (!s) {
        return null;
    }
    const hm = s.match(/\b(\d{1,2}):(\d{2})\b/);
    if (hm) {
        const h = parseInt(hm[1], 10);
        if (h >= 0 && h <= 23) {
            return h;
        }
    }
    const t = Date.parse(s);
    if (!Number.isNaN(t)) {
        return new Date(t).getHours();
    }
    return null;
};

const punchDashboardRows = computed(() => {
    const list = normalizeLastPunchesPayload(lastPunches.value);
    return list.map((row, i) => {
        const nome = pickPunchNome(row);
        const dataRaw = pickPunchDataRaw(row);
        return {
            idLabel: pickPunchIdLabel(row, i),
            nome,
            dataRaw,
            dataDisplay: formatPunchDateTimePtBr(dataRaw),
            personId: pickPunchPersonId(row),
            raw: row,
        };
    });
});

const punchDistinctCollaborators = computed(() => {
    const keys = new Set();
    for (const r of punchDashboardRows.value) {
        const k = r.personId != null ? `id:${r.personId}` : `nome:${r.nome}`;
        keys.add(k);
    }
    return keys.size;
});

const overviewPunchRows = computed(() => normalizeLastPunchesPayload(overviewPunchesSample.value));
const overviewPunchDistinct = computed(() => {
    const keys = new Set();
    for (const r of overviewPunchRows.value) {
        const nome = pickPunchNome(r);
        const pid = pickPunchPersonId(r);
        keys.add(pid != null ? `id:${pid}` : `nome:${nome}`);
    }
    return keys.size;
});

const overviewBankNumericRows = computed(() =>
    overviewBankRows.value.filter((row) => parseRhidBankBalanceMinutes(row) !== null),
);

const overviewBankAvgMinutes = computed(() => {
    const rows = overviewBankNumericRows.value;
    if (!rows.length) {
        return null;
    }
    const sum = rows.reduce((acc, row) => acc + (parseRhidBankBalanceMinutes(row) ?? 0), 0);
    return Math.round(sum / rows.length);
});

const overviewBankWorstThree = computed(() =>
    [...overviewBankRows.value]
        .map((row) => ({ row, m: parseRhidBankBalanceMinutes(row) }))
        .filter(({ m }) => m !== null && m < 0)
        .sort((a, b) => a.m - b.m)
        .slice(0, 3)
        .map(({ row }) => row),
);

const overviewAdherenceWorstEntrada = computed(() => {
    const r = overviewAdherence.value?.ranking_atrasos_entrada;
    if (!Array.isArray(r)) {
        return [];
    }
    return r.slice(0, 5);
});

const PUNCH_TOP_N = 10;
const PUNCH_HOUR_BUCKET_LABELS = ['0h–5h', '6h–11h', '12h–17h', '18h–23h'];

const punchHourBucketIndex = (hour) => {
    if (hour == null || hour < 0 || hour > 23) {
        return null;
    }
    if (hour <= 5) {
        return 0;
    }
    if (hour <= 11) {
        return 1;
    }
    if (hour <= 17) {
        return 2;
    }
    return 3;
};

const punchTopCollaboratorsChart = computed(() => {
    const map = new Map();
    for (const r of punchDashboardRows.value) {
        const label = r.nome;
        map.set(label, (map.get(label) ?? 0) + 1);
    }
    const entries = [...map.entries()].sort((a, b) => b[1] - a[1]).slice(0, PUNCH_TOP_N);
    const categories = entries.map(([name]) => name);
    const data = entries.map(([, c]) => c);
    const empty = data.length === 0;
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '72%',
                dataLabels: { position: 'right' },
            },
        },
        colors: ['#0d9488'],
        dataLabels: {
            enabled: true,
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        yaxis: { labels: { maxWidth: 220 } },
        tooltip: { y: { formatter: (val) => `${val} marcação(ões)` } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Marcações', data }],
        options,
        empty,
    };
});

const punchHourDistributionChart = computed(() => {
    const counts = [0, 0, 0, 0];
    for (const r of punchDashboardRows.value) {
        const h = punchExtractHour(r.dataRaw);
        const bi = punchHourBucketIndex(h);
        if (bi != null) {
            counts[bi] += 1;
        }
    }
    const empty = counts.every((c) => c === 0);
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
        },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '62%' } },
        colors: ['#6366f1'],
        dataLabels: { enabled: true, style: { fontSize: '11px', colors: ['#334155'] } },
        xaxis: { categories: PUNCH_HOUR_BUCKET_LABELS },
        tooltip: { y: { formatter: (val) => `${val} marcação(ões)` } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Marcações', data: counts }],
        options,
        empty,
    };
});

const loadLastPunches = async () => {
    if (!props.configured) {
        return;
    }
    loading.value = true;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.last-punches'));
        lastPunches.value = data;
        lastPunchesUpdatedAt.value = new Date();
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const rhidDepartmentRows = computed(() => extractListItems(rhidDepartmentsPayload.value));
const rhidPersonRoleRows = computed(() => extractListItems(rhidPersonRolesPayload.value));
const rhidPeopleFilterRows = computed(() => extractListItems(rhidPeopleForFiltersPayload.value));

const rhidRowLabel = (row) => {
    const n =
        row?.name ??
        row?.nome ??
        row?.strName ??
        row?.description ??
        row?.departmentName ??
        row?.roleName ??
        '';
    const id = row?.id ?? row?.Id;
    const s = String(n || '').trim();
    if (s && id != null) {
        return `${s} (#${id})`;
    }
    if (id != null) {
        return `#${id}`;
    }
    return s || '—';
};

const loadRhidFilterMeta = async () => {
    if (!props.configured) {
        return;
    }
    rhidMetaLoading.value = true;
    try {
        const [depRes, roleRes, peopleRes] = await Promise.all([
            axios.get(route('client.rhid.api.departments.index'), { params: { page: 0, maxSize: 500 } }),
            axios.get(route('client.rhid.api.person-roles.index'), { params: { page: 0, maxSize: 500 } }),
            axios.get(route('client.rhid.api.people.index'), { params: { page: 0, maxSize: 500 } }),
        ]);
        rhidDepartmentsPayload.value = depRes.data;
        rhidPersonRolesPayload.value = roleRes.data;
        rhidPeopleForFiltersPayload.value = peopleRes.data;
    } catch (e) {
        handleError(e);
    } finally {
        rhidMetaLoading.value = false;
    }
};

const syncBankFiltersFromSelectors = () => {
    bankFilterDepartments.value = String(bankSelDepartment.value ?? '').trim();
    bankFilterPerson.value = String(bankSelPerson.value ?? '').trim();
    bankFilterPersonroles.value = String(bankSelPersonRole.value ?? '').trim();
};

const loadOverviewData = async () => {
    if (!props.configured) {
        return;
    }
    overviewLoading.value = true;
    clearErr();
    try {
        const { first: mFirst, last: mLast } = monthRangeHtmlDates();
        const dateParam = toRhidYmd(todayHtmlDate()) || todayHtmlDate();
        const [punchRes, bankRes, adhRes, typesRes] = await Promise.all([
            axios.get(route('client.rhid.api.last-punches')),
            axios.get(route('client.rhid.api.person-bank-hours.all'), { params: { date: dateParam } }),
            axios.get(route('client.rhid.api.espelhos.schedule-adherence'), {
                params: { ini: mFirst, fim: mLast },
            }),
            axios.get(route('client.rhid.api.justification-types')),
        ]);
        overviewPunchesSample.value = punchRes.data;
        overviewBankRows.value = Array.isArray(bankRes.data?.rows) ? bankRes.data.rows : [];
        overviewAdherence.value = adhRes.data;

        const iniStr = toRhidYmd(mFirst);
        const fimStr = toRhidYmd(mLast);
        const tmap = buildJustificationTypeMapFromPayload(typesRes.data);
        const { data: jdata } = await axios.post(route('client.rhid.api.justifications.list'), {
            ini: iniStr,
            fim: fimStr,
            page: 0,
            maxSize: 500,
        });
        const chunk = Array.isArray(jdata?.data) ? jdata.data : [];
        const recordsTotal = typeof jdata?.recordsTotal === 'number' ? jdata.recordsTotal : chunk.length;
        overviewJustTotal.value = recordsTotal;
        overviewJustAtestados.value = chunk.filter((r) => isAtestadoByKeyword(r, tmap)).length;
        overviewJustNote.value =
            chunk.length >= 500 && recordsTotal > chunk.length
                ? 'Atestados: contagem na primeira página da amostra; refine o período na aba Justificativas para o detalhe completo.'
                : '';
        overviewLoadedAt.value = new Date();
    } catch (e) {
        handleError(e);
    } finally {
        overviewLoading.value = false;
    }
};

const loadEspelhoScheduleAdherence = async () => {
    if (!props.configured) {
        return;
    }
    espelhoAdherenceLoading.value = true;
    espelhoAdherenceResult.value = null;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.espelhos.schedule-adherence'), {
            params: {
                ini: espelhoAdherenceIni.value,
                fim: espelhoAdherenceFim.value,
            },
        });
        espelhoAdherenceResult.value = data;
    } catch (e) {
        handleError(e);
    } finally {
        espelhoAdherenceLoading.value = false;
    }
};

const formatEspelhoAdherenceYmdToPt = (ymd) => {
    if (!ymd || typeof ymd !== 'string') {
        return '—';
    }
    const p = ymd.split('-');
    if (p.length !== 3) {
        return ymd;
    }
    return `${p[2]}/${p[1]}/${p[0]}`;
};

const espelhoAdherenceSituacaoLabel = (code) => {
    if (code === 'sem_escala') {
        return 'Sem escala (dia não útil)';
    }
    if (code === 'insuficiente') {
        return 'Insuficiente (4 batidas)';
    }
    if (code === 'analisavel') {
        return 'Analisável';
    }
    return code ?? '—';
};

const closeEspelhoAdherenceMarksModal = () => {
    espelhoAdherenceMarksOpen.value = false;
    espelhoAdherenceMarksError.value = null;
    espelhoAdherenceMarksData.value = null;
};

const openEspelhoAdherenceMarksModal = async (row) => {
    if (row?.id_person == null) {
        return;
    }
    espelhoAdherenceMarksOpen.value = true;
    espelhoAdherenceMarksLoading.value = true;
    espelhoAdherenceMarksError.value = null;
    espelhoAdherenceMarksData.value = null;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.espelhos.schedule-adherence.marks'), {
            params: {
                ini: espelhoAdherenceIni.value,
                fim: espelhoAdherenceFim.value,
                id_person: row.id_person,
            },
        });
        espelhoAdherenceMarksData.value = data;
    } catch (e) {
        espelhoAdherenceMarksError.value =
            e?.response?.data?.message ?? e?.message ?? 'Não foi possível carregar as marcações.';
    } finally {
        espelhoAdherenceMarksLoading.value = false;
    }
};

/** Rankings e gráficos: até 10 colaboradores por painel (alinhado ao backend TOP_RANK). */
const ESPELHO_ADHERENCE_CHART_TOP = 10;

const truncateAdherenceChartName = (name) => {
    const n = String(name ?? '—');
    return n.length > 36 ? `${n.slice(0, 34)}…` : n;
};

const espelhoAdherenceEntradaTopRows = computed(() => {
    const r = espelhoAdherenceResult.value?.ranking_atrasos_entrada;
    if (!Array.isArray(r)) {
        return [];
    }
    return r.slice(0, ESPELHO_ADHERENCE_CHART_TOP);
});

const espelhoAdherenceEntradaPiorDiaRows = computed(() => {
    const r = espelhoAdherenceResult.value?.ranking_atrasos_entrada;
    if (!Array.isArray(r) || r.length === 0) {
        return [];
    }
    return [...r]
        .sort(
            (a, b) =>
                (b.maior_atraso_entrada_minutos ?? 0) - (a.maior_atraso_entrada_minutos ?? 0),
        )
        .slice(0, ESPELHO_ADHERENCE_CHART_TOP);
});

const espelhoAdherenceAlmocoTopRows = computed(() => {
    const r = espelhoAdherenceResult.value?.ranking_infracoes_almoco;
    if (!Array.isArray(r)) {
        return [];
    }
    return r.slice(0, ESPELHO_ADHERENCE_CHART_TOP);
});

const openAdherenceFromEntradaTotalChart = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const row = espelhoAdherenceEntradaTopRows.value[i];
    if (row?.id_person != null) {
        openEspelhoAdherenceMarksModal(row);
    }
};

const openAdherenceFromEntradaPiorDiaChart = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const row = espelhoAdherenceEntradaPiorDiaRows.value[i];
    if (row?.id_person != null) {
        openEspelhoAdherenceMarksModal(row);
    }
};

const openAdherenceFromAlmocoChart = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const row = espelhoAdherenceAlmocoTopRows.value[i];
    if (row?.id_person != null) {
        openEspelhoAdherenceMarksModal(row);
    }
};

const espelhoAdherenceChartEntradaTotal = computed(() => {
    const rows = espelhoAdherenceEntradaTopRows.value;
    const categories = rows.map((row) => truncateAdherenceChartName(row.nome));
    const data = rows.map((row) => Number(row.total_atraso_entrada_minutos ?? 0));
    const empty = data.length === 0;
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: true },
            zoom: { enabled: true },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openAdherenceFromEntradaTotalChart,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '72%',
                dataLabels: { position: 'right' },
            },
        },
        colors: ['#0d9488'],
        dataLabels: {
            enabled: true,
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        yaxis: { labels: { maxWidth: 220 } },
        tooltip: {
            y: {
                formatter: (val) => `${val} min — clique para ver marcações`,
            },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return { series: [{ name: 'Minutos', data }], options, empty };
});

const espelhoAdherenceChartEntradaPiorDia = computed(() => {
    const rows = espelhoAdherenceEntradaPiorDiaRows.value;
    const categories = rows.map((row) => truncateAdherenceChartName(row.nome));
    const data = rows.map((row) => Number(row.maior_atraso_entrada_minutos ?? 0));
    const empty = data.length === 0;
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: true },
            zoom: { enabled: true },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openAdherenceFromEntradaPiorDiaChart,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '72%',
                dataLabels: { position: 'right' },
            },
        },
        colors: ['#ea580c'],
        dataLabels: {
            enabled: true,
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        yaxis: { labels: { maxWidth: 220 } },
        tooltip: {
            y: {
                formatter: (val) => `${val} min no pior dia — clique para ver marcações`,
            },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return { series: [{ name: 'Minutos', data }], options, empty };
});

const espelhoAdherenceChartAlmoco = computed(() => {
    const rows = espelhoAdherenceAlmocoTopRows.value;
    const categories = rows.map((row) => truncateAdherenceChartName(row.nome));
    const data = rows.map(
        (row) =>
            Number(row.total_minutos_atraso_saida_almoco ?? 0) +
            Number(row.total_minutos_atraso_volta_almoco ?? 0),
    );
    const diasExtra = rows.map((row) => Number(row.dias_com_infracao_almoco ?? 0));
    const empty = data.length === 0;
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: true },
            zoom: { enabled: true },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: {
                dataPointSelection: openAdherenceFromAlmocoChart,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '72%',
                dataLabels: { position: 'right' },
            },
        },
        colors: ['#7c3aed'],
        dataLabels: {
            enabled: true,
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        yaxis: { labels: { maxWidth: 220 } },
        tooltip: {
            y: {
                formatter: (val, opts) => {
                    const idx = opts.dataPointIndex;
                    const d = diasExtra[idx] ?? 0;
                    return `${val} min (saída+volta); ${d} dia(s) c/ infração — clique para ver marcações`;
                },
            },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return { series: [{ name: 'Minutos', data }], options, empty };
});

watch(
    [tab, punchesSubTab],
    ([t, sub]) => {
        if (t !== 'punches') {
            return;
        }
        if (sub === 'dashboard') {
            loadLastPunches();
        }
        if (sub === 'espelho') {
            loadEspelhoImports();
        }
    },
    { immediate: true },
);

watch([tab, punchesSubTab], ([t, sub]) => {
    if (!(t === 'punches' && sub === 'espelho')) {
        espelhoBatchPollAbort.value = true;
    }
});

watch(tab, (t) => {
    if (t === 'overview') {
        loadOverviewData();
    }
    if (t === 'bank' || t === 'justifications') {
        loadRhidFilterMeta();
    }
});

onMounted(() => {
    if (props.configured) {
        loadRhidFilterMeta();
        if (tab.value === 'overview') {
            loadOverviewData();
        }
    }
});

const loadBankHours = async () => {
    if (!props.configured) {
        return;
    }
    syncBankFiltersFromSelectors();
    loading.value = true;
    clearErr();
    bankResult.value = null;
    try {
        const dateParam = toRhidYmd(bankDateHtml.value) || bankDateHtml.value;
        if (bankHasOptionalFilters()) {
            const params = { date: dateParam };
            const n = (v) => {
                const t = String(v ?? '').trim();
                if (t === '') {
                    return null;
                }
                const x = parseInt(t, 10);
                return Number.isNaN(x) ? null : x;
            };
            const c = n(bankFilterCompanies.value);
            const cc = n(bankFilterCostcenters.value);
            const d = n(bankFilterDepartments.value);
            const p = n(bankFilterPerson.value);
            const pr = n(bankFilterPersonroles.value);
            if (c !== null) {
                params.companies = c;
            }
            if (cc !== null) {
                params.costcenters = cc;
            }
            if (d !== null) {
                params.departments = d;
            }
            if (p !== null) {
                params.people = [p];
            }
            if (pr !== null) {
                params.personroles = pr;
            }
            const { data } = await axios.get(route('client.rhid.api.person-bank-hours'), { params });
            bankResult.value = data;
        } else {
            const { data } = await axios.get(route('client.rhid.api.person-bank-hours.all'), {
                params: { date: dateParam },
            });
            bankResult.value = data;
        }
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const loadCollaborators = async () => {
    if (!props.configured) {
        return;
    }
    loading.value = true;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.people.index'), {
            params: { page: 0, maxSize: 500 },
        });
        peopleList.value = data;
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

/**
 * IDs de colaboradores ativos (status=1 na API quando suportado + exclusão por linha inativa).
 * Evita incluir inativos que geram espelho/PDF em branco no RHID.
 */
const fetchAllRhidPersonIdsFromApi = async () => {
    const maxSize = 500;
    const all = new Set();
    const maxPages = 200;
    for (let page = 0; page < maxPages; page++) {
        const { data } = await axios.get(route('client.rhid.api.people.index'), {
            params: { page, maxSize, status: 1 },
        });
        const rows = extractListItems(data);
        if (!rows.length) {
            break;
        }
        for (const row of rows) {
            if (isRhidPersonRowClearlyInactive(row)) {
                continue;
            }
            const id = rhidPersonId(row);
            if (id != null) {
                all.add(id);
            }
        }
        if (rows.length < maxSize) {
            break;
        }
    }
    return [...all].sort((a, b) => a - b);
};

const buildReportPayload = () => {
    const base = {
        formatoSaida: reportFormato.value,
        ini: reportIni.value,
        fim: reportFim.value,
        relatorio: reportNome.value,
        destinoRelatorio: 'DOWNLOAD',
        ordenacao: 'Person',
        pdfCartaoPontoParameters: {
            fontSizeTitle: 12,
            fontSizeData: 8,
            fontSizeHeader: 9,
            fontSizeHeaderSmall: 8,
            fontSizeFooter: 8,
            fontName: 'Helvetica',
            listIdStr: [],
            listCompanyStr: [],
            listDepartmentStr: [],
            listPersonRoleStr: [],
            listCostCenterStr: [],
            listShiftStr: [],
        },
    };
    if (reportNome.value === 'espelho') {
        base.listColumns = ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'];
        base.listPropertyStr = ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'];
    } else {
        base.listColumns = ['strHorarioContratualSimples', 'horasTotalNaoExtra'];
        base.listPersonInfo = ['Person.name', 'Person.pis'];
    }
    /** Somente ativos por padrão: relatórios de ponto incluem muitos inativos e podem estourar o limite da licença */
    base.status = '1';
    return base;
};

const reportPanelData = ref(null);

const startReport = async () => {
    if (!props.configured) {
        return;
    }
    loading.value = true;
    clearErr();
    reportGuid.value = '';
    reportPercent.value = null;
    reportPanelData.value = null;
    try {
        let body;
        const raw = reportJsonOverride.value.trim();
        if (raw) {
            body = JSON.parse(raw);
        } else {
            body = buildReportPayload();
        }
        const { data } = await axios.post(route('client.rhid.api.reports.start'), body);
        reportGuid.value = data.guid || '';
        reportPanelData.value = data;
    } catch (e) {
        if (e instanceof SyntaxError) {
            err.value = 'JSON inválido no campo avançado.';
        } else {
            handleError(e);
        }
    } finally {
        loading.value = false;
    }
};

const pollReportStatus = async () => {
    if (!reportGuid.value) {
        return;
    }
    loading.value = true;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.reports.status'), {
            params: { guid: reportGuid.value },
        });
        reportPercent.value = data.percent ?? null;
        reportPanelData.value = data;
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const downloadReport = () => {
    if (!reportGuid.value) {
        return;
    }
    const url =
        route('client.rhid.api.reports.download') +
        `?guid=${encodeURIComponent(reportGuid.value)}&format=${encodeURIComponent(reportFormato.value)}`;
    window.open(url, '_blank');
};

const validateEspelhoPeriod = () => {
    const ini = espelhoIniDate.value;
    const fim = espelhoFimDate.value;
    const a = new Date(`${ini}T12:00:00`);
    const b = new Date(`${fim}T12:00:00`);
    if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) {
        return 'Datas inválidas.';
    }
    if (b < a) {
        return 'A data final deve ser igual ou posterior a inicial.';
    }
    const daysInclusive = Math.floor((b - a) / 86400000) + 1;
    if (daysInclusive > 31) {
        return 'Período máximo de 1 mês: até 31 dias entre a data inicial e a final (conforme API RHID).';
    }
    return null;
};

const buildEspelhoPayload = (personIdsOverride = null) => {
    const fields =
        espelhoSelectedFields.value.length > 0
            ? [...espelhoSelectedFields.value]
            : ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'];
    const pdfCartaoPontoParameters = {
        fontSizeTitle: 12,
        fontSizeData: 8,
        fontSizeHeader: 8,
        fontSizeHeaderSmall: 8,
        fontSizeFooter: 8,
        fontName: 'Helvetica',
        listIdStr: personIdsOverride ?? parseIdList(espelhoFilterPeople.value) ?? [],
        listCompanyStr: parseIdList(espelhoFilterCompanies.value) ?? [],
        listDepartmentStr: parseIdList(espelhoFilterDepartments.value) ?? [],
        listCostCenterStr: parseIdList(espelhoFilterCostcenters.value) ?? [],
        listPersonRoleStr: parseIdList(espelhoFilterPersonroles.value) ?? [],
        listShiftStr: parseIdList(espelhoFilterShifts.value) ?? [],
    };
    /** @type {Record<string, unknown>} */
    const body = {
        formatoSaida: 'PDF',
        ini: toRhidYmd(espelhoIniDate.value),
        fim: toRhidYmd(espelhoFimDate.value),
        relatorio: 'espelho',
        destinoRelatorio: 'DOWNLOAD',
        ordenacao: 'Person',
        listColumns: fields,
        listPropertyStr: fields,
        pdfCartaoPontoParameters,
    };
    if (espelhoStatus.value === '1' || espelhoStatus.value === '2') {
        body.status = espelhoStatus.value;
    }
    return body;
};

const buildEspelhoBatchMeta = () => ({
    ini: toRhidYmd(espelhoIniDate.value),
    fim: toRhidYmd(espelhoFimDate.value),
    rhid_status: espelhoStatus.value === '1' || espelhoStatus.value === '2' ? espelhoStatus.value : null,
    list_columns:
        espelhoSelectedFields.value.length > 0
            ? [...espelhoSelectedFields.value]
            : ['TODAS_MARCACOES', 'ENTRADAS_SAIDAS'],
    filters: {
        list_company_str: parseIdList(espelhoFilterCompanies.value) ?? [],
        list_department_str: parseIdList(espelhoFilterDepartments.value) ?? [],
        list_cost_center_str: parseIdList(espelhoFilterCostcenters.value) ?? [],
        list_person_role_str: parseIdList(espelhoFilterPersonroles.value) ?? [],
        list_shift_str: parseIdList(espelhoFilterShifts.value) ?? [],
    },
});

/** Inicia o relatório em PDF e acompanha até o processamento chegar a 100% */
const gerarEspelhoCompleto = async () => {
    if (!props.configured) {
        return;
    }
    const periodErr = validateEspelhoPeriod();
    if (periodErr) {
        err.value = periodErr;
        return;
    }
    espelhoPollCancelRequested.value = false;
    espelhoGuid.value = '';
    espelhoPercent.value = null;
    espelhoPanelData.value = null;
    clearErr();
    espelhoPolling.value = true;
    loading.value = true;
    try {
        const { data: startData } = await axios.post(route('client.rhid.api.reports.start'), buildEspelhoPayload());
        espelhoGuid.value = startData.guid || '';
        espelhoPanelData.value = startData;
        if (!espelhoGuid.value) {
            err.value = 'Não foi possível obter o GUID do RHID.';
            return;
        }
        for (let i = 0; i < 120; i++) {
            if (espelhoPollCancelRequested.value) {
                break;
            }
            const { data } = await axios.get(route('client.rhid.api.reports.status'), {
                params: { guid: espelhoGuid.value },
            });
            espelhoPercent.value = data.percent ?? null;
            espelhoPanelData.value = data;
            const p = Number(data.percent);
            if (p === 100) {
                break;
            }
            if (data.error) {
                err.value = String(data.error);
                break;
            }
            await new Promise((r) => setTimeout(r, 1500));
        }
    } catch (e) {
        handleError(e);
    } finally {
        espelhoPolling.value = false;
        loading.value = false;
    }
};

const downloadEspelho = () => {
    if (!espelhoGuid.value) {
        return;
    }
    const url =
        route('client.rhid.api.reports.download') +
        `?guid=${encodeURIComponent(espelhoGuid.value)}&format=${encodeURIComponent('PDF')}`;
    window.open(url, '_blank');
};

const cancelEspelhoPoll = () => {
    espelhoPollCancelRequested.value = true;
};

const loadEspelhoImports = async () => {
    if (!props.configured || !isAdmin.value) {
        return;
    }
    try {
        const { data } = await axios.get(route('client.rhid.api.espelhos.imports.index'), {
            params: { per_page: 15 },
        });
        espelhoImportsPage.value = data;
    } catch (e) {
        handleError(e);
    }
};

const pollEspelhoImportUntilDone = async (importId) => {
    for (let i = 0; i < 90; i++) {
        const { data } = await axios.get(route('client.rhid.api.espelhos.imports.show', importId));
        const imp = data.import;
        espelhoLastImport.value = imp;
        if (imp.parse_status === 'ok' || imp.parse_status === 'failed') {
            return imp;
        }
        await new Promise((r) => setTimeout(r, 2000));
    }
    return espelhoLastImport.value;
};

const saveEspelhoToTalents = async () => {
    if (!props.configured) {
        err.value = 'Configure a integração RHID antes de salvar o espelho.';
        return;
    }
    if (!espelhoIsReadyForDownload.value) {
        err.value = 'Gere o espelho e aguarde o processamento chegar a 100% antes de importar.';
        return;
    }
    if (espelhoTargetPersonId.value == null) {
        const many =
            (parseIdList(espelhoVinculoPersonId.value)?.length ?? 0) > 1 ||
            (parseIdList(espelhoFilterPeople.value)?.length ?? 0) > 1;
        err.value = many
            ? 'Vários colaboradores selecionados: use Importar todos ou deixe apenas um ID no campo ou nos filtros opcionais.'
            : 'Informe um ID de colaborador no campo acima ou nos filtros opcionais.';
        return;
    }
    clearErr();
    loading.value = true;
    try {
        const { data } = await axios.post(route('client.rhid.api.espelhos.store'), {
            guid: espelhoGuid.value,
            id_person: espelhoTargetPersonId.value,
            ini: toRhidYmd(espelhoIniDate.value),
            fim: toRhidYmd(espelhoFimDate.value),
        });
        espelhoLastImport.value = data.import;
        await pollEspelhoImportUntilDone(data.import.id);
        await loadEspelhoImports();
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const pollEspelhoBatchUntilDone = async (batchId) => {
    espelhoBatchPollAbort.value = false;
    for (;;) {
        if (espelhoBatchPollAbort.value) {
            espelhoBatchPhase.value = 'idle';
            return;
        }
        const { data } = await axios.get(route('client.rhid.api.espelhos.batch.show', batchId));
        const b = data.batch;
        espelhoBatchRemote.value = b;
        espelhoBatchTotal.value = b.total ?? 0;
        espelhoBatchRemaining.value = b.remaining ?? 0;
        if (b.status === 'completed' || b.status === 'failed') {
            espelhoBatchPhase.value = 'done';
            espelhoBatchProgress.value =
                b.message || (b.status === 'failed' ? 'Não foi possível concluir o lote.' : 'Lote concluído.');
            await loadEspelhoImports();
            return;
        }
        await new Promise((r) => setTimeout(r, 2500));
    }
};

const saveEspelhoTodosToTalents = async () => {
    if (!props.configured) {
        err.value = 'Configure a integração RHID antes de importar os espelhos.';
        return;
    }
    const periodErr = validateEspelhoPeriod();
    if (periodErr) {
        err.value = periodErr;
        return;
    }
    const fromFilters = parseIdList(espelhoFilterPeople.value) ?? [];
    const fromManual = parseIdList(espelhoVinculoPersonId.value) ?? [];
    let ids = [...new Set([...fromFilters, ...fromManual])];
    clearErr();
    loading.value = true;
    espelhoBatchProgress.value = '';
    espelhoBatchPhase.value = 'resolving';
    espelhoBatchTotal.value = 0;
    espelhoBatchRemaining.value = 0;
    espelhoBatchRemote.value = null;
    try {
        if (!ids.length) {
            ids = await fetchAllRhidPersonIdsFromApi();
        } else {
            const activeSet = new Set(await fetchAllRhidPersonIdsFromApi());
            const before = ids.length;
            ids = ids.filter((id) => activeSet.has(id));
            if (!ids.length) {
                espelhoBatchPhase.value = 'idle';
                err.value =
                    'Nenhum dos IDs informados está entre os colaboradores ativos. Ajuste a lista ou deixe em branco para importar todos os ativos.';
                return;
            }
            if (before > ids.length) {
                espelhoBatchProgress.value = `${before - ids.length} colaborador(es) inativo(s) foram ignorados.`;
                await new Promise((r) => setTimeout(r, 800));
            }
        }
        if (!ids.length) {
            espelhoBatchPhase.value = 'idle';
            err.value = 'Nenhum colaborador ativo encontrado. Verifique a integração ou informe IDs.';
            return;
        }
        espelhoBatchPhase.value = 'starting';
        const { data } = await axios.post(route('client.rhid.api.espelhos.batch.start'), {
            person_ids: ids,
            ...buildEspelhoBatchMeta(),
        });
        const batchId = data.batch.id;
        espelhoBatchRemote.value = data.batch;
        espelhoBatchTotal.value = data.batch.total ?? ids.length;
        espelhoBatchRemaining.value = data.batch.remaining ?? ids.length;
        espelhoBatchPhase.value = 'polling';
        await pollEspelhoBatchUntilDone(batchId);
    } catch (e) {
        handleError(e);
        espelhoBatchPhase.value = 'idle';
    } finally {
        loading.value = false;
        if (espelhoBatchPhase.value === 'done') {
            setTimeout(() => {
                espelhoBatchPhase.value = 'idle';
            }, 6000);
        }
    }
};

const reparseEspelhoImport = async (importId) => {
    if (!importId) {
        return;
    }
    clearErr();
    try {
        await axios.post(route('client.rhid.api.espelhos.imports.reparse', importId));
        await pollEspelhoImportUntilDone(importId);
        await loadEspelhoImports();
        if (espelhoLastImport.value?.id === importId) {
            /* espelhoLastImport atualizado pelo poll */
        }
    } catch (e) {
        handleError(e);
    }
};

const syncParseEspelhoImportNow = async (importId) => {
    if (!importId) {
        return;
    }
    clearErr();
    espelhoDetailLoading.value = true;
    try {
        const { data } = await axios.post(route('client.rhid.api.espelhos.imports.parse-sync', importId));
        espelhoLastImport.value = data.import;
        await loadEspelhoImports();
    } catch (e) {
        handleError(e);
    } finally {
        espelhoDetailLoading.value = false;
    }
};

const showEspelhoImportRow = async (importId) => {
    if (!importId) {
        return;
    }
    try {
        const { data } = await axios.get(route('client.rhid.api.espelhos.imports.show', importId));
        espelhoLastImport.value = data.import;
    } catch (e) {
        handleError(e);
    }
};

onBeforeUnmount(() => {
    espelhoBatchPollAbort.value = true;
});

/**
 * @param {number|undefined} pageOverride — se omitido, usa justPage (listagem paginada)
 */
const buildJustificationsBody = (pageOverride) => {
    const iniStr = toRhidYmd(justIniDate.value);
    const fimStr = toRhidYmd(justFimDate.value);
    const body = {
        ini: iniStr,
        fim: fimStr,
        page: pageOverride !== undefined ? pageOverride : justPage.value,
        maxSize: Math.min(500, Math.max(1, Number(justMaxSize.value) || 100)),
    };
    const add = (key, r) => {
        const arr = parseIdList(r.value);
        if (arr) {
            body[key] = arr;
        }
    };
    add('companies', justFilterCompanies);
    add('costcenters', justFilterCostcenters);
    add('departments', justFilterDepartments);
    add('personroles', justFilterPersonroles);
    add('people', justFilterPeople);
    add('shifts', justFilterShifts);
    add('justificationTypes', justFilterJustificationTypes);
    return body;
};

/**
 * Carrega todo o período no RHID (multi-página), tipos e pessoas para gráficos e tabela paginada localmente.
 */
const loadJustificationsFullPeriod = async () => {
    if (!props.configured) {
        return;
    }
    const iniStr = toRhidYmd(justIniDate.value);
    const fimStr = toRhidYmd(justFimDate.value);
    if (!/^\d{8}$/.test(iniStr) || !/^\d{8}$/.test(fimStr)) {
        clearErr();
        err.value = 'Informe data inicial e final válidas (período em formato completo).';
        return;
    }
    loading.value = true;
    clearErr();
    justListLoaded.value = false;
    justAnalyticsRows.value = [];
    justResult.value = null;
    justAnalyticsMeta.value = {
        pagesLoaded: 0,
        truncated: false,
        mergedTotal: 0,
        recordsTotalFromApi: null,
    };
    try {
        const [typesRes, peopleRes] = await Promise.all([
            axios.get(route('client.rhid.api.justification-types')),
            axios.get(route('client.rhid.api.people.index'), { params: { page: 0, maxSize: 500 } }),
        ]);
        justificationTypeMap.value = buildJustificationTypeMapFromPayload(typesRes.data);
        personDeptMapForJust.value = buildPersonDepartmentMapFromPayload(peopleRes.data);

        const maxSize = Math.min(500, Math.max(1, Number(justMaxSize.value) || 100));
        const merged = [];
        let recordsTotal = null;
        for (let p = 0; p < JUST_ANALYTICS_MAX_PAGES; p += 1) {
            const { data } = await axios.post(route('client.rhid.api.justifications.list'), buildJustificationsBody(p));
            const chunk = Array.isArray(data?.data) ? data.data : [];
            if (typeof data?.recordsTotal === 'number') {
                recordsTotal = data.recordsTotal;
            }
            merged.push(...chunk);
            justAnalyticsMeta.value = {
                ...justAnalyticsMeta.value,
                pagesLoaded: p + 1,
                mergedTotal: merged.length,
                recordsTotalFromApi: recordsTotal,
            };
            if (chunk.length < maxSize) {
                break;
            }
            if (recordsTotal != null && merged.length >= recordsTotal) {
                break;
            }
        }
        const truncated = justAnalyticsMeta.value.pagesLoaded >= JUST_ANALYTICS_MAX_PAGES
            && (recordsTotal == null || merged.length < recordsTotal);
        justAnalyticsMeta.value = {
            ...justAnalyticsMeta.value,
            truncated,
            mergedTotal: merged.length,
            recordsTotalFromApi: recordsTotal,
        };
        justAnalyticsRows.value = merged;
        justResult.value = {
            data: merged,
            recordsTotal: merged.length,
            recordsFiltered: merged.length,
            source: 'justifications.periodo_completo',
        };
        justListLoaded.value = true;
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const loadJustifications = async () => {
    justPage.value = 0;
    await loadJustificationsFullPeriod();
};

const justGoPrev = () => {
    if (!canJustPrevPage.value) {
        return;
    }
    justPage.value -= 1;
};

const justGoNext = () => {
    if (!canJustNextPage.value) {
        return;
    }
    justPage.value += 1;
};

const justificationApprovalLabel = (row) => {
    if (row?.approvalStatusStr2) {
        return row.approvalStatusStr2;
    }
    const s = row?.approvalStatus ?? row?._approvalStatus;
    return s != null ? String(s) : '—';
};

const closeJustChartDrilldown = () => {
    justChartDrilldown.value = null;
};

const justRowToDrillLine = (row) => {
    const pid = row?.idPerson;
    const tmap = justificationTypeMap.value;
    const pmap = personDeptMapForJust.value;
    return {
        name: row?.name ?? '—',
        personId: pid != null && Number.isFinite(Number(pid)) ? Number(pid) : null,
        tipo: justificationTypeLabel(row?.idJustificationType, tmap),
        dept: departmentLabelForJustification(pid, pmap),
        ini: row?.inicioStrColumn ?? row?.inicioStr ?? '—',
        fim: row?.fimStrColumn ?? row?.fimStr ?? '—',
        status: justificationApprovalLabel(row),
        justificativa: row?.justificativa ?? '—',
    };
};

const justTypeSlices = computed(() => {
    const rows = justAnalyticsRows.value;
    const tmap = justificationTypeMap.value;
    const byKey = new Map();
    for (const row of rows) {
        const id = row?.idJustificationType;
        const key = id != null ? String(id) : '_none';
        if (!byKey.has(key)) {
            byKey.set(key, {
                key,
                label: justificationTypeLabel(id, tmap),
                rows: [],
                color: chartColorAt(byKey.size),
            });
        }
        byKey.get(key).rows.push(row);
    }
    return [...byKey.values()].sort((a, b) => b.rows.length - a.rows.length);
});

const justAtestadoSlices = computed(() => {
    const rows = justAnalyticsRows.value;
    const tmap = justificationTypeMap.value;
    const at = rows.filter((r) => isAtestadoByKeyword(r, tmap));
    const rest = rows.filter((r) => !isAtestadoByKeyword(r, tmap));
    const out = [];
    if (at.length) {
        out.push({ label: 'Atestados', color: '#dc2626', rows: at });
    }
    if (rest.length) {
        out.push({ label: 'Outras justificativas', color: '#64748b', rows: rest });
    }
    return out;
});

const justAtestadoCount = computed(() => {
    const tmap = justificationTypeMap.value;
    return justAnalyticsRows.value.filter((r) => isAtestadoByKeyword(r, tmap)).length;
});

const openJustDrilldownFromTypeDonut = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const s = justTypeSlices.value[i];
    if (!s) {
        return;
    }
    const lines = s.rows.map(justRowToDrillLine).sort((a, b) => a.name.localeCompare(b.name, 'pt'));
    justChartDrilldown.value = {
        title: s.label,
        subtitle: `${s.rows.length} registro(s) no período · por tipo`,
        lines,
    };
};

const justTypeDonutChart = computed(() => {
    const slices = justTypeSlices.value;
    const series = slices.map((s) => s.rows.length);
    const labels = slices.map((s) => (s.label.length > 40 ? `${s.label.slice(0, 38)}…` : s.label));
    const colors = slices.map((s) => s.color);
    const total = series.reduce((a, b) => a + b, 0);
    const options = {
        chart: {
            type: 'donut',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: { dataPointSelection: openJustDrilldownFromTypeDonut },
        },
        labels,
        colors,
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: total > 0,
                        total: {
                            show: true,
                            label: 'Justificativas',
                            formatter: () => String(total),
                        },
                    },
                },
            },
        },
        dataLabels: { enabled: true, style: { fontSize: '10px' } },
        tooltip: {
            y: {
                formatter: (val) => {
                    const pct = total ? ((Number(val) / total) * 100).toFixed(1) : '0';
                    return `${val} (${pct}%) — clique para listar`;
                },
            },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return { series, options, empty: total === 0 };
});

const openJustDrilldownFromAtestadoDonut = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const s = justAtestadoSlices.value[i];
    if (!s) {
        return;
    }
    const lines = s.rows.map(justRowToDrillLine).sort((a, b) => a.name.localeCompare(b.name, 'pt'));
    justChartDrilldown.value = {
        title: s.label,
        subtitle: `${s.rows.length} registro(s) · tipo ou texto contém «atest»`,
        lines,
    };
};

const justAtestadoDonutChart = computed(() => {
    const slices = justAtestadoSlices.value;
    const series = slices.map((s) => s.rows.length);
    const labels = slices.map((s) => s.label);
    const colors = slices.map((s) => s.color);
    const total = series.reduce((a, b) => a + b, 0);
    const options = {
        chart: {
            type: 'donut',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: { dataPointSelection: openJustDrilldownFromAtestadoDonut },
        },
        labels,
        colors,
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: {
            pie: {
                donut: {
                    size: '62%',
                    labels: {
                        show: total > 0,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: () => String(total),
                        },
                    },
                },
            },
        },
        dataLabels: { enabled: true, style: { fontSize: '11px' } },
        tooltip: {
            y: {
                formatter: (val) => {
                    const pct = total ? ((Number(val) / total) * 100).toFixed(1) : '0';
                    return `${val} (${pct}%)`;
                },
            },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return { series, options, empty: total === 0 };
});

const justTopPersonSlices = computed(() => {
    const rows = justAnalyticsRows.value;
    const pmap = personDeptMapForJust.value;
    const byPerson = new Map();
    for (const row of rows) {
        const pid = row?.idPerson;
        const key = pid != null ? String(pid) : '_none';
        const name = row?.name ?? pmap[key]?.name ?? (pid != null ? `#${pid}` : 'Sem pessoa');
        if (!byPerson.has(key)) {
            byPerson.set(key, { name, personId: pid, rows: [] });
        }
        byPerson.get(key).rows.push(row);
    }
    const entries = [...byPerson.values()]
        .sort((a, b) => b.rows.length - a.rows.length)
        .slice(0, JUST_TOP_COLLABORATORS);
    return entries;
});

const openJustDrilldownFromTopPerson = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const s = justTopPersonSlices.value[i];
    if (!s) {
        return;
    }
    const lines = s.rows.map(justRowToDrillLine).sort((a, b) => a.ini.localeCompare(b.ini, 'pt'));
    justChartDrilldown.value = {
        title: s.name,
        subtitle: `${s.rows.length} justificativa(s) no período`,
        lines,
    };
};

const justTopPersonBarChart = computed(() => {
    const slices = justTopPersonSlices.value;
    const categories = slices.map((s) => {
        const n = s.name;
        return n.length > 32 ? `${n.slice(0, 30)}…` : n;
    });
    const data = slices.map((s) => s.rows.length);
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: { dataPointSelection: openJustDrilldownFromTopPerson },
        },
        plotOptions: {
            bar: { horizontal: true, borderRadius: 4, barHeight: '72%', dataLabels: { position: 'right' } },
        },
        colors: ['#632a7e'],
        dataLabels: {
            enabled: true,
            formatter: (val) => String(val),
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        tooltip: { y: { formatter: (val) => `${val} ocorrência(s) — clique para listar` } },
        yaxis: { labels: { maxWidth: 200 } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Quantidade', data }],
        options,
        empty: data.length === 0,
    };
});

const justDeptSlices = computed(() => {
    const rows = justAnalyticsRows.value;
    const pmap = personDeptMapForJust.value;
    const map = new Map();
    for (const row of rows) {
        const d = departmentLabelForJustification(row?.idPerson, pmap);
        if (!map.has(d)) {
            map.set(d, { name: d, rows: [] });
        }
        map.get(d).rows.push(row);
    }
    const entries = [...map.entries()]
        .map(([, v]) => v)
        .sort((a, b) => b.rows.length - a.rows.length);
    const top = entries.slice(0, JUST_MAX_DEPT_CHART);
    const rest = entries.slice(JUST_MAX_DEPT_CHART);
    if (!rest.length) {
        return top;
    }
    const mergedRows = rest.flatMap((e) => e.rows);
    return [
        ...top,
        {
            name: 'Outros',
            rows: mergedRows,
        },
    ];
});

const openJustDrilldownFromDept = (_event, _chartContext, config) => {
    const i = config?.dataPointIndex;
    if (i == null || i < 0) {
        return;
    }
    const s = justDeptSlices.value[i];
    if (!s) {
        return;
    }
    const lines = s.rows.map(justRowToDrillLine).sort((a, b) => a.name.localeCompare(b.name, 'pt'));
    justChartDrilldown.value = {
        title: s.name,
        subtitle: `${s.rows.length} justificativa(s) neste agrupamento`,
        lines,
    };
};

const justDeptBarChart = computed(() => {
    const final = justDeptSlices.value;
    const categories = final.map((e) => (e.name.length > 28 ? `${e.name.slice(0, 26)}…` : e.name));
    const data = final.map((e) => e.rows.length);
    const options = {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'Figtree, sans-serif',
            foreColor: '#334155',
            events: { dataPointSelection: openJustDrilldownFromDept },
        },
        plotOptions: {
            bar: { horizontal: true, borderRadius: 4, barHeight: '70%', dataLabels: { position: 'right' } },
        },
        colors: ['#0d9488'],
        dataLabels: {
            enabled: true,
            formatter: (val) => String(val),
            style: { fontSize: '11px', colors: ['#334155'] },
        },
        xaxis: { categories },
        tooltip: { y: { formatter: (val) => `${val} — clique para listar` } },
        yaxis: { labels: { maxWidth: 200 } },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
    };
    return {
        series: [{ name: 'Quantidade', data }],
        options,
        empty: data.length === 0,
    };
});

</script>

<template>
    <Head title="Compliance RHID" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Compliance de ponto — RHID</h2>
                <div class="flex flex-wrap items-center gap-4">
                    <label
                        v-if="isAdmin"
                        class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"
                    >
                        <input
                            v-model="supportMode"
                            type="checkbox"
                            class="rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                        />
                        Modo suporte
                    </label>
                    <Link
                        v-if="isAdmin"
                        :href="route('client.rhid.settings.edit')"
                        class="text-sm font-medium text-talents-700 hover:underline"
                    >
                        Configuração RHID
                    </Link>
                </div>
            </div>
        </template>

        <div
            v-if="!configured"
            class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
        >
            <p class="font-semibold">Integração não configurada</p>
            <p class="mt-1">Cadastre e-mail e senha da API RHID para usar este módulo.</p>
            <Link
                v-if="isAdmin"
                :href="route('client.rhid.settings.edit')"
                class="mt-3 inline-block text-sm font-bold text-talents-800 underline"
            >
                Abrir configurações
            </Link>
        </div>

        <div v-else class="space-y-6">
            <Modal :show="bankChartDrilldown != null" max-width="2xl" @close="closeBankChartDrilldown">
                <div v-if="bankChartDrilldown" class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ bankChartDrilldown.title }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ bankChartDrilldown.subtitle }}</p>
                    <div class="mt-4 max-h-[min(32rem,70vh)] overflow-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="sticky top-0 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="whitespace-nowrap p-2">Nome</th>
                                    <th class="whitespace-nowrap p-2">Saldo BH</th>
                                    <th class="whitespace-nowrap p-2">Departamento</th>
                                    <th class="whitespace-nowrap p-2">Cargo</th>
                                    <th class="whitespace-nowrap p-2">CPF</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(ln, di) in bankChartDrilldown.lines"
                                    :key="di"
                                    class="border-t border-slate-100"
                                >
                                    <td class="max-w-[14rem] p-2 font-medium text-slate-800">
                                        <Link
                                            v-if="ln.personId != null"
                                            :href="route('client.rhid.collaborators.show', ln.personId)"
                                            class="text-talents-800 hover:underline"
                                        >
                                            {{ ln.name }}
                                        </Link>
                                        <span v-else>{{ ln.name }}</span>
                                    </td>
                                    <td class="whitespace-nowrap p-2 tabular-nums text-slate-800">{{ ln.balance }}</td>
                                    <td class="max-w-[10rem] truncate p-2 text-slate-600" :title="ln.dept">{{ ln.dept }}</td>
                                    <td class="max-w-[10rem] truncate p-2 text-slate-600" :title="ln.role">{{ ln.role }}</td>
                                    <td class="whitespace-nowrap p-2 text-slate-600">{{ ln.cpf }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <SecondaryButton type="button" @click="closeBankChartDrilldown">Fechar</SecondaryButton>
                    </div>
                </div>
            </Modal>

            <Modal :show="justChartDrilldown != null" max-width="3xl" @close="closeJustChartDrilldown">
                <div v-if="justChartDrilldown" class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ justChartDrilldown.title }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ justChartDrilldown.subtitle }}</p>
                    <div class="mt-4 max-h-[min(32rem,70vh)] overflow-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="sticky top-0 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="whitespace-nowrap p-2">Colaborador</th>
                                    <th class="whitespace-nowrap p-2">Tipo</th>
                                    <th class="whitespace-nowrap p-2">Setor</th>
                                    <th class="whitespace-nowrap p-2">Início</th>
                                    <th class="whitespace-nowrap p-2">Fim</th>
                                    <th class="whitespace-nowrap p-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(ln, ji) in justChartDrilldown.lines"
                                    :key="ji"
                                    class="border-t border-slate-100"
                                >
                                    <td class="max-w-[12rem] p-2 font-medium text-slate-800">
                                        <Link
                                            v-if="ln.personId != null"
                                            :href="route('client.rhid.collaborators.show', ln.personId)"
                                            class="text-talents-800 hover:underline"
                                        >
                                            {{ ln.name }}
                                        </Link>
                                        <span v-else>{{ ln.name }}</span>
                                    </td>
                                    <td class="max-w-[10rem] truncate p-2 text-slate-700" :title="ln.tipo">{{ ln.tipo }}</td>
                                    <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="ln.dept">{{ ln.dept }}</td>
                                    <td class="whitespace-nowrap p-2 text-slate-700">{{ ln.ini }}</td>
                                    <td class="whitespace-nowrap p-2 text-slate-700">{{ ln.fim }}</td>
                                    <td class="whitespace-nowrap p-2 text-slate-600">{{ ln.status }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <SecondaryButton type="button" @click="closeJustChartDrilldown">Fechar</SecondaryButton>
                    </div>
                </div>
            </Modal>

            <Modal :show="espelhoAdherenceMarksOpen" max-width="3xl" @close="closeEspelhoAdherenceMarksModal">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-slate-900">Marcações do espelho (período da análise)</h3>
                    <p v-if="espelhoAdherenceMarksLoading" class="mt-2 text-sm text-slate-500">Carregando…</p>
                    <p v-else-if="espelhoAdherenceMarksError" class="mt-2 text-sm text-red-700">
                        {{ espelhoAdherenceMarksError }}
                    </p>
                    <template v-else-if="espelhoAdherenceMarksData">
                        <p class="mt-1 text-sm text-slate-600">
                            {{ espelhoAdherenceMarksData.nome }}
                            <span class="font-mono text-slate-500"> · ID {{ espelhoAdherenceMarksData.id_person }}</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Período:
                            {{ formatEspelhoAdherenceYmdToPt(espelhoAdherenceMarksData.periodo?.ini) }} a
                            {{ formatEspelhoAdherenceYmdToPt(espelhoAdherenceMarksData.periodo?.fim) }} · Tolerância:
                            {{ espelhoAdherenceMarksData.tolerancia_minutos }} min (config.) · Import mais recente por dia
                        </p>
                        <div
                            v-if="espelhoAdherenceMarksData.dias?.length"
                            class="mt-4 max-h-[min(32rem,70vh)] overflow-auto rounded-lg border border-slate-200"
                        >
                            <table class="min-w-full text-left text-sm">
                                <thead
                                    class="sticky top-0 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600"
                                >
                                    <tr>
                                        <th class="whitespace-nowrap p-2">Data</th>
                                        <th class="whitespace-nowrap p-2">ENT.1</th>
                                        <th class="whitespace-nowrap p-2">SAI.1</th>
                                        <th class="whitespace-nowrap p-2">ENT.2</th>
                                        <th class="whitespace-nowrap p-2">SAI.2</th>
                                        <th class="whitespace-nowrap p-2">Situacao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(d, di) in espelhoAdherenceMarksData.dias"
                                        :key="di"
                                        class="border-t border-slate-100"
                                    >
                                        <td class="whitespace-nowrap p-2 text-slate-800">
                                            {{ formatEspelhoAdherenceYmdToPt(d.ref_date) }}
                                        </td>
                                        <td class="whitespace-nowrap p-2 font-mono tabular-nums">{{ d.ent_1 ?? '—' }}</td>
                                        <td class="whitespace-nowrap p-2 font-mono tabular-nums">{{ d.sai_1 ?? '—' }}</td>
                                        <td class="whitespace-nowrap p-2 font-mono tabular-nums">{{ d.ent_2 ?? '—' }}</td>
                                        <td class="whitespace-nowrap p-2 font-mono tabular-nums">{{ d.sai_2 ?? '—' }}</td>
                                        <td class="p-2 text-slate-700">{{ espelhoAdherenceSituacaoLabel(d.situacao) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="mt-4 text-sm text-slate-500">
                            Nenhum dia de espelho encontrado para este colaborador no período.
                        </p>
                    </template>
                    <div class="mt-4 flex justify-end">
                        <SecondaryButton type="button" @click="closeEspelhoAdherenceMarksModal">Fechar</SecondaryButton>
                    </div>
                </div>
            </Modal>

            <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm font-medium"
                    :class="
                        tab === t.id
                            ? 'bg-talents-700 text-white'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                    "
                    @click="tab = t.id"
                >
                    {{ t.label }}
                </button>
            </div>

            <p v-if="err" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ err }}</p>
            <p v-if="loading" class="text-sm text-slate-500">Carregando...</p>

            <OverviewSection
                v-show="tab === 'overview'"
                :overview-loading="overviewLoading"
                :overview-loaded-at="overviewLoadedAt"
                :overview-punch-rows-length="overviewPunchRows.length"
                :overview-punch-distinct="overviewPunchDistinct"
                :overview-bank-numeric-rows-length="overviewBankNumericRows.length"
                :overview-bank-avg-minutes="overviewBankAvgMinutes"
                :overview-bank-worst-three="overviewBankWorstThree"
                :overview-adherence="overviewAdherence"
                :overview-adherence-worst-entrada="overviewAdherenceWorstEntrada"
                :overview-just-total="overviewJustTotal"
                :overview-just-atestados="overviewJustAtestados"
                :overview-just-note="overviewJustNote"
                :is-admin="isAdmin"
                :format-rhid-bank-balance-minutes="formatRhidBankBalanceMinutes"
                :bank-display-name="bankDisplayName"
                :bank-display-value="bankDisplayValue"
                :rhid-person-id="rhidPersonId"
                @refresh="loadOverviewData"
                @go-punches-dashboard="tab = 'punches'; punchesSubTab = 'dashboard'"
                @go-punches-adherence="tab = 'punches'; punchesSubTab = 'adherence'"
                @go-bank="tab = 'bank'"
                @go-justifications="tab = 'justifications'"
                @go-espelho="tab = 'punches'; punchesSubTab = 'espelho'"
            />

            <div v-show="tab === 'punches'" class="space-y-4">
                <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="
                            punchesSubTab === 'dashboard'
                                ? 'bg-talents-700 text-white'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                        "
                        @click="punchesSubTab = 'dashboard'"
                    >
                        Painel
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="
                            punchesSubTab === 'adherence'
                                ? 'bg-talents-700 text-white'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                        "
                        @click="punchesSubTab = 'adherence'"
                    >
                        Aderência ao horário
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="
                            punchesSubTab === 'espelho'
                                ? 'bg-talents-700 text-white'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                        "
                        @click="punchesSubTab = 'espelho'"
                    >
                        Espelho e importação
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium"
                        :class="
                            punchesSubTab === 'export'
                                ? 'bg-talents-700 text-white'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                        "
                        @click="punchesSubTab = 'export'"
                    >
                        Exportar relatório (RHID)
                    </button>
                </div>

                <div v-show="punchesSubTab === 'dashboard'" class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <PrimaryButton type="button" :disabled="loading" @click="loadLastPunches">
                            Atualizar marcações
                        </PrimaryButton>
                        <p v-if="lastPunchesUpdatedAt" class="text-sm text-slate-600">
                            Última atualização:
                            {{
                                lastPunchesUpdatedAt.toLocaleString('pt-BR', {
                                    dateStyle: 'short',
                                    timeStyle: 'medium',
                                })
                            }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Linhas retornadas</p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">
                                {{ punchDashboardRows.length }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Colaboradores distintos
                            </p>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">
                                {{ punchDistinctCollaborators }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Painel</p>
                            <p class="mt-1 text-sm text-slate-700">
                                Últimas marcações registradas no RHID (amostra para acompanhamento rápido).
                            </p>
                            <p v-if="supportMode" class="mt-1 text-xs text-slate-500">
                                Endpoint:
                                <code class="rounded bg-slate-100 px-1">util.svc/ultimasmarcacoes</code>
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1">
                            <h3 class="mb-1 text-sm font-semibold text-slate-800">Top colaboradores</h3>
                            <p class="mb-3 text-xs text-slate-500">
                                Contagem de linhas por nome nesta amostra (até {{ PUNCH_TOP_N }}).
                            </p>
                            <apexchart
                                v-if="!punchTopCollaboratorsChart.empty"
                                type="bar"
                                :height="Math.max(280, (punchTopCollaboratorsChart.series[0]?.data?.length ?? 0) * 36)"
                                :options="punchTopCollaboratorsChart.options"
                                :series="punchTopCollaboratorsChart.series"
                            />
                            <p v-else class="text-sm text-slate-500">Sem dados para o gráfico.</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1">
                            <h3 class="mb-1 text-sm font-semibold text-slate-800">Marcações por faixa de hora</h3>
                            <p class="mb-3 text-xs text-slate-500">
                                Quando a data/hora puder ser interpretada, a hora da marcação é agrupada em faixas.
                            </p>
                            <apexchart
                                v-if="!punchHourDistributionChart.empty"
                                type="bar"
                                height="280"
                                :options="punchHourDistributionChart.options"
                                :series="punchHourDistributionChart.series"
                            />
                            <p v-else class="text-sm text-slate-500">
                                Nenhuma hora reconhecida nos registros (formato de data variável).
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="p-2">ID</th>
                                    <th class="p-2">Nome</th>
                                    <th class="p-2">Data / hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, i) in punchDashboardRows"
                                    :key="i"
                                    class="border-t border-slate-100"
                                >
                                    <td class="whitespace-nowrap p-2 text-slate-800">{{ row.idLabel }}</td>
                                    <td class="p-2">
                                        <Link
                                            v-if="row.personId != null"
                                            :href="route('client.rhid.collaborators.show', row.personId)"
                                            class="font-medium text-talents-800 hover:underline"
                                        >
                                            {{ row.nome }}
                                        </Link>
                                        <span v-else class="font-medium text-slate-800">{{ row.nome }}</span>
                                    </td>
                                    <td class="whitespace-nowrap p-2 text-slate-700">{{ row.dataDisplay }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-show="punchesSubTab === 'adherence'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-800">Aderência: espelho importado vs horários da empresa</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600">
                            Compara os PDFs de espelho já importados (sub-aba <span class="font-medium">Espelho e importação</span>)
                            com os horários cadastrados em
                            <Link
                                v-if="isAdmin"
                                :href="route('client.rhid.settings.edit')"
                                class="font-medium text-talents-800 underline"
                            >Configuração RHID</Link><span v-else class="font-medium">Configuração RHID</span>.
                            Convenção de 4 batidas por dia. Clique no nome para ver as marcações do espelho no período.
                        </p>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <div>
                                <InputLabel value="Data inicial" />
                                <input
                                    v-model="espelhoAdherenceIni"
                                    type="date"
                                    class="mt-1 block rounded-md border border-slate-300 text-sm"
                                />
                            </div>
                            <div>
                                <InputLabel value="Data final" />
                                <input
                                    v-model="espelhoAdherenceFim"
                                    type="date"
                                    class="mt-1 block rounded-md border border-slate-300 text-sm"
                                />
                            </div>
                            <PrimaryButton
                                type="button"
                                :disabled="espelhoAdherenceLoading"
                                @click="loadEspelhoScheduleAdherence"
                            >
                                Analisar aderência
                            </PrimaryButton>
                        </div>
                        <p v-if="espelhoAdherenceLoading" class="mt-2 text-sm text-slate-500">Calculando…</p>
                        <template v-else-if="espelhoAdherenceResult?.resumo">
                            <p class="mt-2 text-xs text-slate-600">
                                Período: {{ espelhoAdherenceResult.resumo.ini }} a {{ espelhoAdherenceResult.resumo.fim }} ·
                                Tolerância: {{ espelhoAdherenceResult.resumo.tolerancia_minutos }} min · Dias analisados:
                                {{ espelhoAdherenceResult.resumo.dias_registro_analisados }}
                            </p>
                            <p class="mt-3 text-xs text-slate-500">
                                Dashboard: até {{ ESPELHO_ADHERENCE_CHART_TOP }} colaboradores por gráfico. Passe o mouse para
                                detalhes; use a barra do gráfico para exportar (PNG/SVG) ou ampliar. Clique numa barra para
                                ver as marcações do espelho no período.
                            </p>
                            <div class="mt-4 grid gap-4 xl:grid-cols-3">
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <h4 class="mb-1 text-sm font-semibold text-slate-800">Atraso na 1ª entrada (total)</h4>
                                    <p class="mb-3 text-xs text-slate-500">
                                        Soma dos minutos de atraso na entrada no período (top
                                        {{ ESPELHO_ADHERENCE_CHART_TOP }}).
                                    </p>
                                    <apexchart
                                        v-if="!espelhoAdherenceChartEntradaTotal.empty"
                                        type="bar"
                                        :height="
                                            Math.max(
                                                260,
                                                (espelhoAdherenceChartEntradaTotal.series[0]?.data?.length ?? 0) * 36,
                                            )
                                        "
                                        :options="espelhoAdherenceChartEntradaTotal.options"
                                        :series="espelhoAdherenceChartEntradaTotal.series"
                                    />
                                    <p v-else class="text-sm text-slate-500">Sem dados para o gráfico.</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <h4 class="mb-1 text-sm font-semibold text-slate-800">Pior dia — atraso na entrada</h4>
                                    <p class="mb-3 text-xs text-slate-500">
                                        Maior atraso num único dia (1ª entrada), top
                                        {{ ESPELHO_ADHERENCE_CHART_TOP }}.
                                    </p>
                                    <apexchart
                                        v-if="!espelhoAdherenceChartEntradaPiorDia.empty"
                                        type="bar"
                                        :height="
                                            Math.max(
                                                260,
                                                (espelhoAdherenceChartEntradaPiorDia.series[0]?.data?.length ?? 0) * 36,
                                            )
                                        "
                                        :options="espelhoAdherenceChartEntradaPiorDia.options"
                                        :series="espelhoAdherenceChartEntradaPiorDia.series"
                                    />
                                    <p v-else class="text-sm text-slate-500">Sem dados para o gráfico.</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                    <h4 class="mb-1 text-sm font-semibold text-slate-800">Infrações de almoço (min)</h4>
                                    <p class="mb-3 text-xs text-slate-500">
                                        Total min (saída para almoço + volta), top
                                        {{ ESPELHO_ADHERENCE_CHART_TOP }} (mesma ordenação da tabela).
                                    </p>
                                    <apexchart
                                        v-if="!espelhoAdherenceChartAlmoco.empty"
                                        type="bar"
                                        :height="
                                            Math.max(260, (espelhoAdherenceChartAlmoco.series[0]?.data?.length ?? 0) * 36)
                                        "
                                        :options="espelhoAdherenceChartAlmoco.options"
                                        :series="espelhoAdherenceChartAlmoco.series"
                                    />
                                    <p v-else class="text-sm text-slate-500">Sem dados para o gráfico.</p>
                                </div>
                            </div>
                            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold uppercase text-slate-700">
                                        Maiores atrasos (entrada) — detalhe
                                    </h4>
                                    <p class="mb-2 text-xs text-slate-500">
                                        Listagem alinhada ao ranking (até {{ ESPELHO_ADHERENCE_CHART_TOP }} linhas).
                                    </p>
                                    <div class="overflow-x-auto rounded border border-slate-200 text-sm">
                                        <table class="min-w-full text-left">
                                            <thead class="bg-slate-50 text-xs">
                                                <tr>
                                                    <th class="p-2">Nome</th>
                                                    <th class="p-2">ID</th>
                                                    <th class="p-2">Total min</th>
                                                    <th class="p-2">Pior dia min</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row, ri) in espelhoAdherenceResult.ranking_atrasos_entrada"
                                                    :key="'ae-' + ri"
                                                    class="border-t border-slate-100"
                                                >
                                                    <td class="p-2">
                                                        <button
                                                            type="button"
                                                            class="text-left font-medium text-talents-800 hover:underline"
                                                            @click="openEspelhoAdherenceMarksModal(row)"
                                                        >
                                                            {{ row.nome }}
                                                        </button>
                                                    </td>
                                                    <td class="p-2 font-mono text-xs">{{ row.id_person }}</td>
                                                    <td class="p-2">{{ row.total_atraso_entrada_minutos }}</td>
                                                    <td class="p-2">{{ row.maior_atraso_entrada_minutos }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p
                                        v-if="!espelhoAdherenceResult.ranking_atrasos_entrada?.length"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        Nenhum dia analisável no período (importe espelhos e configure dias úteis).
                                    </p>
                                </div>
                                <div>
                                    <h4 class="mb-2 text-xs font-semibold uppercase text-slate-700">
                                        Infrações de almoço — detalhe
                                    </h4>
                                    <p class="mb-2 text-xs text-slate-500">
                                        Ordenação: mais dias com problema; depois soma dos atrasos em minutos (saída para
                                        almoço + volta). Infrações só por duração (almoço curto/longo) podem ter 0 min
                                        nessa soma. Listagem até {{ ESPELHO_ADHERENCE_CHART_TOP }} linhas.
                                    </p>
                                    <div class="overflow-x-auto rounded border border-slate-200 text-sm">
                                        <table class="min-w-full text-left">
                                            <thead class="bg-slate-50 text-xs">
                                                <tr>
                                                    <th class="p-2">Nome</th>
                                                    <th class="p-2">ID</th>
                                                    <th class="p-2">Dias c/ problema</th>
                                                    <th class="p-2">Total min atraso</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row, ri) in espelhoAdherenceResult.ranking_infracoes_almoco"
                                                    :key="'al-' + ri"
                                                    class="border-t border-slate-100"
                                                >
                                                    <td class="p-2">
                                                        <button
                                                            type="button"
                                                            class="text-left font-medium text-talents-800 hover:underline"
                                                            @click="openEspelhoAdherenceMarksModal(row)"
                                                        >
                                                            {{ row.nome }}
                                                        </button>
                                                    </td>
                                                    <td class="p-2 font-mono text-xs">{{ row.id_person }}</td>
                                                    <td class="p-2">{{ row.dias_com_infracao_almoco }}</td>
                                                    <td class="p-2">
                                                        {{
                                                            (row.total_minutos_atraso_saida_almoco ?? 0) +
                                                            (row.total_minutos_atraso_volta_almoco ?? 0)
                                                        }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p
                                        v-if="!espelhoAdherenceResult.ranking_infracoes_almoco?.length"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        Nenhum dia analisável ou sem infrações no ranking.
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div v-show="tab === 'bank'" class="space-y-4">
                <p class="text-sm text-slate-600">
                    Consulte o banco de horas na data de referência. Sem filtros opcionais, todos os colaboradores retornados
                    pela API são agregados. Use os filtros para refinar por departamento, cargo ou colaborador.
                </p>
                <p v-if="supportMode" class="text-xs text-slate-500">
                    API RHID person_banco_horas · parâmetro date (YYYYMMDD). Agregação no servidor pode depender de
                    RHID_BANK_HOURS_AGGREGATE.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <SecondaryButton type="button" :disabled="rhidMetaLoading" @click="loadRhidFilterMeta">
                        {{ rhidMetaLoading ? 'Carregando listas…' : 'Atualizar departamentos / cargos / pessoas' }}
                    </SecondaryButton>
                </div>
                <div class="grid max-w-4xl gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Data de referência" />
                        <input
                            v-model="bankDateHtml"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Departamento" />
                        <select
                            v-model="bankSelDepartment"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        >
                            <option value="">Todos</option>
                            <option
                                v-for="d in rhidDepartmentRows"
                                :key="d.id ?? d.idDepartment ?? rhidRowLabel(d)"
                                :value="String(d.id ?? d.idDepartment ?? '')"
                            >
                                {{ rhidRowLabel(d) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Cargo" />
                        <select
                            v-model="bankSelPersonRole"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        >
                            <option value="">Todos</option>
                            <option
                                v-for="r in rhidPersonRoleRows"
                                :key="r.id ?? r.idPersonRole ?? rhidRowLabel(r)"
                                :value="String(r.id ?? r.idPersonRole ?? '')"
                            >
                                {{ rhidRowLabel(r) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Colaborador" />
                        <select
                            v-model="bankSelPerson"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        >
                            <option value="">Todos</option>
                            <option
                                v-for="p in rhidPeopleFilterRows"
                                :key="rhidPersonId(p) ?? p.id ?? rhidRowLabel(p)"
                                :value="String(rhidPersonId(p) ?? p.id ?? '')"
                            >
                                {{ rhidRowLabel(p) }}
                            </option>
                        </select>
                    </div>
                    <div v-if="supportMode" class="sm:col-span-2 lg:col-span-3">
                        <p class="text-xs font-medium text-slate-700">Filtros avançados (ID numérico RHID)</p>
                        <div class="mt-2 grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Empresa (companies)" />
                                <input
                                    v-model="bankFilterCompanies"
                                    type="number"
                                    min="0"
                                    class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                    placeholder="opcional"
                                />
                            </div>
                            <div>
                                <InputLabel value="Centro de custo (costcenters)" />
                                <input
                                    v-model="bankFilterCostcenters"
                                    type="number"
                                    min="0"
                                    class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                                    placeholder="opcional"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <PrimaryButton type="button" :disabled="loading" @click="loadBankHours">Consultar banco de horas</PrimaryButton>
                <p v-if="bankResult?.source" class="text-xs text-slate-500">
                    Fonte: {{ bankResult.source }} · Data referência: {{ bankResult.date }}
                </p>

                <div v-if="bankRows.length" class="grid gap-4 lg:grid-cols-3">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-3"
                    >
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Distribuição de saldo</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Colaboradores por faixa de saldo (HH:mm, mesmo conceito do espelho RHID) na data de referência.
                            <span class="font-medium text-talents-800"> Clique numa fatia</span> para ver a lista.
                        </p>
                        <apexchart
                            v-if="bankDonutChart.series.length"
                            type="donut"
                            height="300"
                            :options="bankDonutChart.options"
                            :series="bankDonutChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Sem dados para o gráfico.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Média de saldo por departamento</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Apenas linhas com saldo numérico; até 12 departamentos + Outros.
                            <span class="font-medium text-talents-800"> Clique numa barra</span> para listar os colaboradores.
                        </p>
                        <apexchart
                            v-if="!bankDeptAvgChart.empty"
                            type="bar"
                            :height="Math.max(280, (bankDeptAvgChart.series[0]?.data?.length ?? 0) * 36)"
                            :options="bankDeptAvgChart.options"
                            :series="bankDeptAvgChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">
                            Nenhum saldo numérico para agrupar por departamento.
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Maiores debitos (BH)</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Ate 10 colaboradores com saldo mais negativo.
                            <span class="font-medium text-talents-800"> Clique numa barra</span> para ver departamento, cargo e documentos.
                        </p>
                        <apexchart
                            v-if="!bankTopDebitChart.empty"
                            type="bar"
                            :height="Math.max(260, (bankTopDebitChart.series[0]?.data?.length ?? 0) * 40)"
                            :options="bankTopDebitChart.options"
                            :series="bankTopDebitChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Nenhum saldo negativo nesta consulta.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Maiores saldos (BH)</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Ate 10 colaboradores com maior saldo positivo.
                            <span class="font-medium text-talents-800"> Clique numa barra</span> para ver o detalhe.
                        </p>
                        <apexchart
                            v-if="!bankTopCreditChart.empty"
                            type="bar"
                            :height="Math.max(260, (bankTopCreditChart.series[0]?.data?.length ?? 0) * 40)"
                            :options="bankTopCreditChart.options"
                            :series="bankTopCreditChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Nenhum saldo positivo nesta consulta.</p>
                    </div>
                </div>

                <div v-if="bankRows.length" class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="whitespace-nowrap p-2">Nome</th>
                                <th class="whitespace-nowrap p-2">Nome social</th>
                                <th class="whitespace-nowrap p-2">Matricula</th>
                                <th class="whitespace-nowrap p-2">CPF</th>
                                <th class="whitespace-nowrap p-2">Saldo BH (HH:mm)</th>
                                <th class="whitespace-nowrap p-2">Departamento</th>
                                <th class="whitespace-nowrap p-2">Cargo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in bankRows" :key="i" class="border-t border-slate-100">
                                <td class="max-w-[12rem] truncate p-2 font-medium text-slate-800" :title="bankDisplayName(row)">
                                    <Link
                                        v-if="rhidPersonId(row) != null"
                                        :href="route('client.rhid.collaborators.show', rhidPersonId(row))"
                                        class="text-talents-800 hover:underline"
                                    >
                                        {{ bankDisplayName(row) }}
                                    </Link>
                                    <span v-else>{{ bankDisplayName(row) }}</span>
                                </td>
                                <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="row.socialName || ''">
                                    {{ row.socialName || '—' }}
                                </td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.registration ?? '—' }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.cpf ?? '—' }}</td>
                                <td class="whitespace-nowrap p-2 tabular-nums font-medium text-slate-800">
                                    {{ bankDisplayValue(row) }}
                                </td>
                                <td class="max-w-[10rem] truncate p-2 text-slate-600" :title="bankRowDepartamento(row)">
                                    {{ bankRowDepartamento(row) }}
                                </td>
                                <td class="max-w-[10rem] truncate p-2 text-slate-600" :title="bankRowCargo(row)">
                                    {{ bankRowCargo(row) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="bankResult && !loading" class="text-sm text-slate-500">Nenhum registro retornado.</p>
                <RhidResponsePanel
                    v-if="supportMode && bankResult"
                    :data="bankResult"
                    title="Resposta completa (suporte)"
                />
            </div>

            <div v-show="tab === 'justifications'" class="space-y-4">
                <p class="text-sm text-slate-600">
                    Liste justificativas do período, com gráficos e tabela. Escolha datas e, se quiser, refine por
                    departamento, cargo ou colaborador.
                </p>
                <p v-if="supportMode" class="text-xs text-slate-500">
                    POST justification.svc/list · ini/fim YYYYMMDD. Erro de referência nula: informe empresa (companies)
                    ou RHID_JUSTIFICATION_LIST_DEFAULT_COMPANY_ID no servidor.
                </p>
                <div class="grid max-w-5xl gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <InputLabel value="Data inicial (ini)" />
                        <input
                            v-model="justIniDate"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Data final (fim)" />
                        <input
                            v-model="justFimDate"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Registros por página (maxSize)" />
                        <input
                            v-model.number="justMaxSize"
                            type="number"
                            min="1"
                            max="500"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                    </div>
                    <div class="flex items-end gap-2 pb-0.5">
                        <p class="text-xs text-slate-500">
                            Página {{ justPage + 1
                            }}<span v-if="justRecordsTotal != null"> · Total {{ justRecordsTotal }} registro(s)</span>
                        </p>
                    </div>
                    <div v-if="supportMode">
                        <InputLabel value="Empresas (IDs RHID)" />
                        <input
                            v-model="justFilterCompanies"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="ex.: 1 ou 1, 2"
                        />
                    </div>
                    <div v-if="supportMode">
                        <InputLabel value="Centros de custo (IDs)" />
                        <input
                            v-model="justFilterCostcenters"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Departamentos (Ctrl+clique para vários)" />
                        <select
                            v-model="justMultiDepartments"
                            multiple
                            class="mt-1 min-h-[5.5rem] w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        >
                            <option
                                v-for="d in rhidDepartmentRows"
                                :key="d.id ?? d.idDepartment ?? rhidRowLabel(d)"
                                :value="String(d.id ?? d.idDepartment ?? '')"
                            >
                                {{ rhidRowLabel(d) }}
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Cargos (Ctrl+clique para vários)" />
                        <select
                            v-model="justMultiPersonroles"
                            multiple
                            class="mt-1 min-h-[5.5rem] w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        >
                            <option
                                v-for="r in rhidPersonRoleRows"
                                :key="r.id ?? r.idPersonRole ?? rhidRowLabel(r)"
                                :value="String(r.id ?? r.idPersonRole ?? '')"
                            >
                                {{ rhidRowLabel(r) }}
                            </option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel value="Colaboradores (Ctrl+clique para vários)" />
                        <select
                            v-model="justMultiPeople"
                            multiple
                            class="mt-1 min-h-[5.5rem] w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        >
                            <option
                                v-for="p in rhidPeopleFilterRows"
                                :key="rhidPersonId(p) ?? p.id ?? rhidRowLabel(p)"
                                :value="String(rhidPersonId(p) ?? p.id ?? '')"
                            >
                                {{ rhidRowLabel(p) }}
                            </option>
                        </select>
                    </div>
                    <div v-if="supportMode">
                        <InputLabel value="Horários / turnos (IDs)" />
                        <input
                            v-model="justFilterShifts"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div v-if="supportMode">
                        <InputLabel value="Tipos de justificativa (IDs)" />
                        <input
                            v-model="justFilterJustificationTypes"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <PrimaryButton type="button" :disabled="loading" @click="loadJustifications">Listar justificativas</PrimaryButton>
                    <SecondaryButton type="button" :disabled="loading || !canJustPrevPage" @click="justGoPrev">
                        Página anterior
                    </SecondaryButton>
                    <SecondaryButton type="button" :disabled="!canJustNextPage" @click="justGoNext">
                        Próxima página
                    </SecondaryButton>
                </div>
                <p class="text-xs text-slate-500">
                    Ao listar, o período completo é carregado (até {{ JUST_ANALYTICS_MAX_PAGES }} páginas no RHID); a tabela abaixo
                    pagina localmente. Gráficos e setor usam o mesmo conjunto; cadastro de pessoas limitado a 500 na consulta.
                </p>
                <div
                    v-if="justAnalyticsRows.length"
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase text-slate-500">Total no período</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ justAnalyticsMeta.mergedTotal }}</p>
                        <p
                            v-if="supportMode && justAnalyticsMeta.recordsTotalFromApi != null"
                            class="mt-1 text-xs text-slate-500"
                        >
                            RHID recordsTotal: {{ justAnalyticsMeta.recordsTotalFromApi }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-rose-100 bg-rose-50/80 p-4 shadow-sm">
                        <p class="text-xs font-medium uppercase text-rose-800">Atestados</p>
                        <p class="mt-1 text-2xl font-semibold text-rose-900">{{ justAtestadoCount }}</p>
                        <p class="mt-1 text-xs text-rose-700">Quando o tipo ou a descrição mencionam atestado</p>
                    </div>
                    <div
                        v-if="justAnalyticsMeta.truncated"
                        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:col-span-2 lg:col-span-2"
                    >
                        Limite de páginas atingido — estreite o período ou os filtros para carregar tudo.
                    </div>
                </div>
                <div v-if="justAnalyticsRows.length" class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Por tipo de justificativa</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Clique numa fatia para ver colaboradores e datas.
                        </p>
                        <apexchart
                            v-if="!justTypeDonutChart.empty"
                            type="donut"
                            height="320"
                            :options="justTypeDonutChart.options"
                            :series="justTypeDonutChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Sem dados.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Atestado vs outras</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Separação por tipo ou texto que contém «atest» (demais entram em outras justificativas).
                        </p>
                        <apexchart
                            v-if="!justAtestadoDonutChart.empty"
                            type="donut"
                            height="280"
                            :options="justAtestadoDonutChart.options"
                            :series="justAtestadoDonutChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Sem dados.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Top colaboradores</h3>
                        <p class="mb-3 text-xs text-slate-500">Mais justificativas no período ({{ JUST_TOP_COLLABORATORS }}).</p>
                        <apexchart
                            v-if="!justTopPersonBarChart.empty"
                            type="bar"
                            :height="Math.max(260, (justTopPersonBarChart.series[0]?.data?.length ?? 0) * 40)"
                            :options="justTopPersonBarChart.options"
                            :series="justTopPersonBarChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Sem dados.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Por setor (departamento)</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Até {{ JUST_MAX_DEPT_CHART }} setores + Outros. Clique na barra para listar.
                        </p>
                        <apexchart
                            v-if="!justDeptBarChart.empty"
                            type="bar"
                            :height="Math.max(280, (justDeptBarChart.series[0]?.data?.length ?? 0) * 36)"
                            :options="justDeptBarChart.options"
                            :series="justDeptBarChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Sem dados.</p>
                    </div>
                </div>
                <div v-if="justRows.length" class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="whitespace-nowrap p-2">ID</th>
                                <th class="whitespace-nowrap p-2">PIS</th>
                                <th class="whitespace-nowrap p-2">Nome</th>
                                <th class="whitespace-nowrap p-2">Início</th>
                                <th class="whitespace-nowrap p-2">Fim</th>
                                <th class="whitespace-nowrap p-2">Justificativa</th>
                                <th class="min-w-[12rem] p-2">Descrição</th>
                                <th class="whitespace-nowrap p-2">Status</th>
                                <th class="whitespace-nowrap p-2">Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, ji) in justRows" :key="row.id ?? ji" class="border-t border-slate-100">
                                <td class="whitespace-nowrap p-2 font-mono text-xs">{{ row.id ?? '—' }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.pis ?? '—' }}</td>
                                <td class="max-w-[10rem] p-2 font-medium text-slate-800">
                                    <Link
                                        v-if="row.idPerson != null"
                                        :href="route('client.rhid.collaborators.show', row.idPerson)"
                                        class="text-talents-800 hover:underline"
                                    >
                                        {{ row.name ?? '—' }}
                                    </Link>
                                    <span v-else>{{ row.name ?? '—' }}</span>
                                </td>
                                <td class="whitespace-nowrap p-2 text-slate-700">{{ row.inicioStrColumn ?? row.inicioStr ?? '—' }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-700">{{ row.fimStrColumn ?? row.fimStr ?? '—' }}</td>
                                <td class="max-w-[14rem] truncate p-2 text-slate-700" :title="row.justificativa || ''">
                                    {{ row.justificativa ?? '—' }}
                                </td>
                                <td class="max-w-[14rem] truncate p-2 text-slate-600" :title="row.description || ''">
                                    {{ row.description ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ justificationApprovalLabel(row) }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-600">
                                    {{ row.idJustificationType != null ? `#${row.idJustificationType}` : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="justListLoaded && !justAnalyticsRows.length && !loading" class="text-sm text-slate-500">
                    Nenhuma justificativa neste período ou filtros.
                </p>
                <RhidResponsePanel
                    v-if="supportMode && justListLoaded && justResult && tab === 'justifications'"
                    :data="justResult"
                    title="Resposta completa (suporte)"
                />
            </div>

            <div v-show="tab === 'punches' && punchesSubTab === 'espelho'" class="space-y-3">
                <div
                    v-if="espelhoShowProcessingBanner"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-950 shadow-sm"
                    role="status"
                >
                    <span
                        class="inline-block size-5 shrink-0 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            <template v-if="espelhoPolling">Gerando espelho no RHID…</template>
                            <template v-else-if="espelhoBatchPhase === 'resolving' || espelhoBatchPhase === 'starting'"
                                >Preparando importação…</template
                            >
                            <template v-else-if="espelhoBatchPhase === 'polling'">Importando PDFs em segundo plano…</template>
                            <template v-else>Processando…</template>
                        </p>
                        <p v-if="espelhoPolling && espelhoPercent !== null" class="mt-0.5 text-sm text-emerald-900/90">
                            Andamento: {{ espelhoPercent }}%
                        </p>
                        <template v-else-if="espelhoBatchPhase === 'polling' && espelhoBatchTotal > 0">
                            <p class="mt-0.5 text-sm text-emerald-900/90">
                                Restam {{ espelhoBatchRemaining }} de {{ espelhoBatchTotal }} colaboradores
                            </p>
                            <div class="mt-2 h-2 w-full max-w-md overflow-hidden rounded-full bg-emerald-100">
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-[width] duration-300"
                                    :style="{ width: espelhoBatchProgressPercent + '%' }"
                                />
                            </div>
                        </template>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-600">
                    Gere o espelho de ponto no período escolhido, baixe o PDF ou importe para o Talents. A leitura das
                    marcações continua em segundo plano após o PDF ser salvo. Período de até 31 dias.
                </p>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Status dos funcionários" />
                        <select v-model="espelhoStatus" class="mt-1 w-full rounded-md border border-slate-300 text-sm">
                            <option value="">Todos</option>
                            <option value="1">Ativos</option>
                            <option value="2">Inativos</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Data inicial" />
                        <input
                            v-model="espelhoIniDate"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Data final" />
                        <input
                            v-model="espelhoFimDate"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                        />
                    </div>
                </div>
                <div>
                    <InputLabel value="Colunas do relatório" />
                    <div class="mt-2 flex flex-wrap gap-3">
                        <label
                            v-for="opt in ESPELHO_FIELD_OPTIONS"
                            :key="opt.value"
                            class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"
                        >
                            <input v-model="espelhoSelectedFields" type="checkbox" class="rounded border-slate-300" :value="opt.value" />
                            {{ opt.label }}
                        </label>
                    </div>
                </div>
                <details
                    v-if="supportMode"
                    class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm"
                >
                    <summary class="cursor-pointer font-medium text-slate-800">Filtros opcionais por ID no RHID</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <InputLabel value="Colaboradores" />
                            <input
                                v-model="espelhoFilterPeople"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs"
                                placeholder="ex: 1, 2, 3"
                            />
                        </div>
                        <div>
                            <InputLabel value="Empresas" />
                            <input
                                v-model="espelhoFilterCompanies"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel value="Departamentos" />
                            <input
                                v-model="espelhoFilterDepartments"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel value="Centros de custo" />
                            <input
                                v-model="espelhoFilterCostcenters"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel value="Cargos" />
                            <input
                                v-model="espelhoFilterPersonroles"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs"
                            />
                        </div>
                        <div>
                            <InputLabel value="Horários (turnos)" />
                            <input
                                v-model="espelhoFilterShifts"
                                type="text"
                                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-xs"
                            />
                        </div>
                    </div>
                </details>
                <div class="max-w-md">
                    <InputLabel value="Colaboradores (importação individual ou lista)" />
                    <input
                        v-model="espelhoVinculoPersonId"
                        type="text"
                        class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-sm"
                        placeholder="Ex.: 12345 ou 10, 20, 30"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Um ID para importar um único espelho. Várias IDs separadas por vírgula, ou deixe em branco e use
                        <strong class="font-medium">Importar todos</strong> para todos os colaboradores ativos.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <PrimaryButton type="button" :disabled="loading" @click="gerarEspelhoCompleto">
                        Gerar espelho
                    </PrimaryButton>
                    <SecondaryButton type="button" :disabled="!espelhoPolling" @click="cancelEspelhoPoll">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton type="button" :disabled="!espelhoIsReadyForDownload" @click="downloadEspelho">
                        Download PDF
                    </PrimaryButton>
                    <PrimaryButton type="button" :disabled="loading" @click="saveEspelhoToTalents">
                        Salvar no Talents e extrair
                    </PrimaryButton>
                    <SecondaryButton type="button" :disabled="loading" @click="saveEspelhoTodosToTalents">
                        Importar todos
                    </SecondaryButton>
                </div>
                <p v-if="espelhoBatchProgress && espelhoBatchPhase === 'idle'" class="text-sm text-slate-600">
                    {{ espelhoBatchProgress }}
                </p>
                <details
                    v-if="supportMode && (espelhoGuid || espelhoPanelData)"
                    class="rounded-md border border-slate-100 bg-slate-50/80 p-2 text-xs text-slate-600"
                >
                    <summary class="cursor-pointer font-medium text-slate-700">Detalhes tecnicos (suporte)</summary>
                    <p v-if="espelhoGuid" class="mt-2">
                        Identificador da tarefa no RHID:
                        <code class="rounded bg-white px-1">{{ espelhoGuid }}</code>
                    </p>
                    <div v-if="espelhoPanelData" class="mt-2 space-y-2">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="espelhoShowTechnicalPanel" type="checkbox" class="rounded border-slate-300" />
                            Mostrar resposta completa do RHID
                        </label>
                        <RhidResponsePanel v-if="espelhoShowTechnicalPanel" :data="espelhoPanelData" title="Resposta RHID" />
                    </div>
                </details>

                <div v-if="espelhoLastImport" class="rounded-md border border-slate-200 bg-white p-3 text-sm shadow-sm">
                    <h3 class="font-medium text-slate-800">Última importação</h3>
                    <p class="mt-1 text-slate-600">
                        Registro #{{ espelhoLastImport.id }} — colaborador
                        <code class="rounded bg-slate-100 px-1">{{ espelhoLastImport.id_person }}</code>
                        — {{ formatPeriodPtBr(espelhoLastImport.period_ini, espelhoLastImport.period_fim) }} — leitura:
                        <span
                            :class="{
                                'text-emerald-700': espelhoLastImport.parse_status === 'ok',
                                'text-amber-700': espelhoLastImport.parse_status === 'pending',
                                'text-red-700': espelhoLastImport.parse_status === 'failed',
                            }"
                        >
                            {{ espelhoParseLabel(espelhoLastImport.parse_status) }}
                        </span>
                    </p>
                    <p v-if="espelhoLastImport.parse_error" class="mt-1 text-red-700">
                        {{ espelhoLastImport.parse_error }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <a
                            :href="route('client.rhid.api.espelhos.imports.file', espelhoLastImport.id)"
                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            target="_blank"
                            rel="noopener"
                        >
                            Abrir PDF salvo
                        </a>
                        <SecondaryButton type="button" @click="reparseEspelhoImport(espelhoLastImport.id)">
                            Reprocessar (fila)
                        </SecondaryButton>
                        <SecondaryButton
                            type="button"
                            :disabled="espelhoDetailLoading"
                            @click="syncParseEspelhoImportNow(espelhoLastImport.id)"
                        >
                            Processar agora (sync)
                        </SecondaryButton>
                    </div>
                    <div
                        v-if="espelhoLastImport.parse_status === 'ok'"
                        class="mt-3 grid gap-2 rounded-md border border-slate-100 bg-slate-50/80 p-3 text-slate-800 sm:grid-cols-3"
                    >
                        <div>
                            <span class="text-xs font-medium text-slate-500">NOME</span>
                            <p class="mt-0.5 font-medium">{{ espelhoExtractHeader.nome }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-slate-500">CPF</span>
                            <p class="mt-0.5 font-mono text-sm">{{ espelhoExtractHeader.cpf }}</p>
                        </div>
                        <div class="sm:col-span-1">
                            <span class="text-xs font-medium text-slate-500">Período</span>
                            <p class="mt-0.5 whitespace-nowrap">
                                {{ formatPeriodPtBr(espelhoExtractHeader.period_ini, espelhoExtractHeader.period_fim) }}
                            </p>
                        </div>
                    </div>
                    <div v-if="espelhoPunchTableRows.length" class="mt-3 max-h-96 overflow-auto rounded border border-slate-100">
                        <table class="min-w-full text-xs">
                            <thead class="sticky top-0 bg-slate-50">
                                <tr>
                                    <th class="p-2 text-left font-medium text-slate-700">Dia</th>
                                    <th class="p-2 text-left font-medium text-slate-700">Nome</th>
                                    <th
                                        v-for="col in espelhoSlotColumns"
                                        :key="col.key"
                                        class="whitespace-nowrap p-2 text-left font-medium text-slate-700"
                                    >
                                        {{ col.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(pr, prIdx) in espelhoPunchTableRows"
                                    :key="`${pr.ref_date}-${prIdx}`"
                                    class="border-t border-slate-100"
                                >
                                    <td class="whitespace-nowrap p-2 text-slate-800">{{ pr.ref_date }}</td>
                                    <td class="max-w-[10rem] truncate p-2 text-slate-700" :title="pr.nome">{{ pr.nome }}</td>
                                    <td
                                        v-for="col in espelhoSlotColumns"
                                        :key="col.key"
                                        class="whitespace-nowrap p-2 font-mono text-slate-800"
                                    >
                                        {{ espelhoMarcacaoSlots(pr.fragment)[col.key] || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-else-if="espelhoLastImport.days?.length && espelhoLastImport.parse_status === 'ok'"
                        class="mt-2 text-xs text-slate-500"
                    >
                        Nenhuma linha de marcação extraída para exibir (verifique o PDF ou reprocessar).
                    </p>
                </div>

                <div v-if="espelhoImportsPage?.data?.length" class="rounded-md border border-slate-200 bg-slate-50/80 p-3 text-sm">
                    <h3 class="font-medium text-slate-800">Importacoes recentes</h3>
                    <table class="mt-2 min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-600">
                                <th class="p-2">Nº</th>
                                <th class="p-2">Colaborador (RHID)</th>
                                <th class="p-2">Período</th>
                                <th class="p-2">Leitura</th>
                                <th class="p-2"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in espelhoImportsPage.data" :key="row.id" class="border-t border-slate-200">
                                <td class="p-2 tabular-nums">{{ row.id }}</td>
                                <td class="p-2 font-mono">{{ row.id_person }}</td>
                                <td class="whitespace-nowrap p-2">{{ formatPeriodPtBr(row.period_ini, row.period_fim) }}</td>
                                <td class="p-2">{{ espelhoParseLabel(row.parse_status) }}</td>
                                <td class="p-2">
                                    <button
                                        type="button"
                                        class="font-medium text-talents-800 hover:underline"
                                        @click="showEspelhoImportRow(row.id)"
                                    >
                                        Ver
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-show="tab === 'punches' && punchesSubTab === 'export'" class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <InputLabel value="Formato de saída" />
                        <select v-model="reportFormato" class="mt-1 w-full rounded-md border border-slate-300 text-sm">
                            <option value="PDF">PDF</option>
                            <option value="PDF2">PDF2</option>
                            <option value="CSV">CSV</option>
                            <option value="HTML">HTML</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Tipo de relatório" />
                        <select v-model="reportNome" class="mt-1 w-full rounded-md border border-slate-300 text-sm">
                            <option value="espelho">Espelho de ponto</option>
                            <option value="cartao">Cartão de ponto</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Data inicial" />
                        <input
                            v-model="reportIniDate"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                        />
                    </div>
                    <div>
                        <InputLabel value="Data final" />
                        <input
                            v-model="reportFimDate"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                        />
                    </div>
                </div>
                <details v-if="supportMode" class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                    <summary class="cursor-pointer font-medium text-slate-800">JSON avançado (opcional)</summary>
                    <textarea
                        v-model="reportJsonOverride"
                        class="mt-2 w-full rounded-md border border-slate-300 font-mono text-xs"
                        rows="4"
                        placeholder="Substitui os campos acima"
                    />
                </details>
                <div class="flex flex-wrap gap-2">
                    <PrimaryButton type="button" :disabled="loading" @click="startReport">Iniciar relatório</PrimaryButton>
                    <PrimaryButton type="button" :disabled="loading || !reportGuid" @click="pollReportStatus">
                        Atualizar status
                    </PrimaryButton>
                    <PrimaryButton type="button" :disabled="!reportGuid" @click="downloadReport">Download</PrimaryButton>
                </div>
                <p v-if="supportMode && reportGuid" class="text-sm text-slate-600">
                    GUID: <code class="rounded bg-slate-100 px-1">{{ reportGuid }}</code>
                    <span v-if="reportPercent !== null" class="ml-2">Percentual: {{ reportPercent }}%</span>
                </p>
                <p v-else-if="reportGuid" class="text-sm text-slate-600">
                    Relatório em processamento<span v-if="reportPercent !== null"> ({{ reportPercent }}%)</span>.
                </p>
                <RhidResponsePanel
                    v-if="supportMode && reportPanelData"
                    :data="reportPanelData"
                    title="Status / resposta"
                />
            </div>

            <div v-show="tab === 'collaborators'" class="space-y-3">
                <p class="text-sm text-slate-600">
                    Lista os colaboradores cadastrados no RHID (até 500 por consulta).
                </p>
                <PrimaryButton type="button" :disabled="loading" @click="loadCollaborators">Carregar colaboradores</PrimaryButton>
                <div v-if="peopleRows.length" class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-2">ID</th>
                                <th class="p-2">Nome</th>
                                <th class="p-2">Matricula / PIS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in peopleRows" :key="rhidPersonId(row) ?? row.id ?? i" class="border-t border-slate-100">
                                <td class="p-2 font-mono text-xs">{{ rhidPersonId(row) ?? '—' }}</td>
                                <td class="p-2">
                                    <Link
                                        v-if="rhidPersonId(row) != null"
                                        :href="route('client.rhid.collaborators.show', rhidPersonId(row))"
                                        class="font-medium text-talents-800 hover:underline"
                                    >
                                        {{ personDisplayName(row) }}
                                    </Link>
                                    <span v-else>{{ personDisplayName(row) }}</span>
                                </td>
                                <td class="p-2 text-slate-600">{{ personMatricula(row) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <RhidResponsePanel
                    v-if="supportMode && peopleList && tab === 'collaborators'"
                    :data="peopleList"
                    title="Resposta bruta (suporte)"
                />
            </div>
        </div>
    </ClientLayout>
</template>
