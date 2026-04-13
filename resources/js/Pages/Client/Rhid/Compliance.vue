<script setup>
import RhidResponsePanel from '@/Components/Rhid/RhidResponsePanel.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import {
    extractListItems,
    formatRhidBankBalanceDisplay,
    formatRhidBankBalanceMinutes,
    monthRangeHtmlDates,
    parseRhidBankBalanceMinutes,
    pickRhidPersonDisplayName,
    todayHtmlDate,
    toRhidYmd,
} from '@/utils/rhidDate';

const page = usePage();

const props = defineProps({
    configured: { type: Boolean, required: true },
});

const tab = ref('punches');
const err = ref(null);
const loading = ref(false);

const lastPunches = ref([]);

const bankDateHtml = ref(todayHtmlDate());
const bankResult = ref(null);
/** Filtros opcionais do GET person_banco_horas (inteiros RHID) */
const bankFilterCompanies = ref('');
const bankFilterCostcenters = ref('');
const bankFilterDepartments = ref('');
const bankFilterPerson = ref('');
const bankFilterPersonroles = ref('');

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

const isAdmin = computed(() => page.props.auth?.user?.role === 'company_admin');

const bankRows = computed(() => {
    const r = bankResult.value;
    if (!r || !Array.isArray(r.rows)) {
        return [];
    }
    return r.rows;
});

const peopleRows = computed(() => extractListItems(peopleList.value));

const tabs = [
    { id: 'punches', label: 'Marcacoes' },
    { id: 'bank', label: 'Banco de horas' },
    { id: 'justifications', label: 'Justificativas' },
    { id: 'reports', label: 'Relatorios' },
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
const justResult = ref(null);

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

const justRows = computed(() => {
    const r = justResult.value;
    if (!r || !Array.isArray(r.data)) {
        return [];
    }
    return r.data;
});

const justRecordsTotal = computed(() => {
    const t = justResult.value?.recordsTotal;
    return typeof t === 'number' ? t : null;
});

const canJustPrevPage = computed(() => justPage.value > 0);

const canJustNextPage = computed(() => {
    const total = justRecordsTotal.value;
    const size = Number(justMaxSize.value) || 100;
    const page = justPage.value;
    if (total == null) {
        return justRows.value.length >= size;
    }
    return (page + 1) * size < total;
});

const clearErr = () => {
    err.value = null;
};

const handleError = (e) => {
    err.value = e.response?.data?.message || e.message || 'Erro na requisicao';
};

const personDisplayName = (row) =>
    row?.name ?? row?.nome ?? row?.strName ?? row?.personName ?? (row?.id != null ? `Colaborador #${row.id}` : '—');

const personMatricula = (row) =>
    row?.registration ?? row?.matricula ?? row?.pis ?? row?.strMatricula ?? row?.strPis ?? '—';

const bankDisplayName = pickRhidPersonDisplayName;
const bankDisplayValue = formatRhidBankBalanceDisplay;

const rhidPersonId = (row) => {
    const id = row?.idPerson ?? row?.id;
    if (id == null || id === '') {
        return null;
    }
    const n = Number(id);
    return Number.isFinite(n) ? n : null;
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

/** Detalhe ao clicar num grafico: lista de colaboradores */
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

/** Fatias do donut com linhas para drill-down (mesma ordem do grafico). */
const bankDonutSlices = computed(() => {
    const rows = bankRows.value;
    const defs = [
        { label: 'Saldo negativo', color: '#ef4444', test: (m) => m !== null && m < 0 },
        { label: 'Saldo zero', color: '#94a3b8', test: (m) => m !== null && m === 0 },
        { label: 'Saldo positivo', color: '#10b981', test: (m) => m !== null && m > 0 },
        { label: 'Sem dado numerico', color: '#f59e0b', test: (m) => m === null },
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

/** Barras de departamento com media e lista de colaboradores. */
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
        subtitle: `Data ${bankDateHtml.value} · Media ${formatRhidBankBalanceMinutes(avgRounded)} (${slice.count} colaborador(es) com saldo numerico neste agrupamento)`,
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
                formatter: (val) => `${formatRhidBankBalanceMinutes(val)} (media) — clique para listar`,
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

const loadLastPunches = async () => {
    if (!props.configured) {
        return;
    }
    loading.value = true;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.last-punches'));
        lastPunches.value = Array.isArray(data) ? data : [];
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const loadBankHours = async () => {
    if (!props.configured) {
        return;
    }
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
            err.value = 'JSON invalido no campo avancado.';
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

const buildJustificationsBody = () => {
    const iniStr = toRhidYmd(justIniDate.value);
    const fimStr = toRhidYmd(justFimDate.value);
    const body = {
        ini: /^\d{8}$/.test(iniStr) ? parseInt(iniStr, 10) : iniStr,
        fim: /^\d{8}$/.test(fimStr) ? parseInt(fimStr, 10) : fimStr,
        page: justPage.value,
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

const fetchJustifications = async () => {
    if (!props.configured) {
        return;
    }
    const iniStr = toRhidYmd(justIniDate.value);
    const fimStr = toRhidYmd(justFimDate.value);
    if (!/^\d{8}$/.test(iniStr) || !/^\d{8}$/.test(fimStr)) {
        clearErr();
        err.value = 'Informe data inicial e final validas (periodo em formato completo).';
        return;
    }
    loading.value = true;
    clearErr();
    try {
        const { data } = await axios.post(route('client.rhid.api.justifications.list'), buildJustificationsBody());
        justResult.value = data;
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const loadJustifications = async () => {
    justPage.value = 0;
    await fetchJustifications();
};

const justGoPrev = async () => {
    if (!canJustPrevPage.value) {
        return;
    }
    justPage.value -= 1;
    await fetchJustifications();
};

const justGoNext = async () => {
    if (!canJustNextPage.value) {
        return;
    }
    justPage.value += 1;
    await fetchJustifications();
};

const justificationApprovalLabel = (row) => {
    if (row?.approvalStatusStr2) {
        return row.approvalStatusStr2;
    }
    const s = row?.approvalStatus ?? row?._approvalStatus;
    return s != null ? String(s) : '—';
};
</script>

<template>
    <Head title="Compliance RHID" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Compliance de ponto — RHID</h2>
                <Link
                    v-if="isAdmin"
                    :href="route('client.rhid.settings.edit')"
                    class="text-sm font-medium text-talents-700 hover:underline"
                >
                    Configuracao
                </Link>
            </div>
        </template>

        <div
            v-if="!configured"
            class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
        >
            <p class="font-semibold">Integracao nao configurada</p>
            <p class="mt-1">Cadastre e-mail e senha da API RHID para usar este modulo.</p>
            <Link
                v-if="isAdmin"
                :href="route('client.rhid.settings.edit')"
                class="mt-3 inline-block text-sm font-bold text-talents-800 underline"
            >
                Abrir configuracoes
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

            <div v-show="tab === 'punches'" class="space-y-3">
                <PrimaryButton type="button" :disabled="loading" @click="loadLastPunches">Atualizar marcacoes</PrimaryButton>
                <div class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="p-2">ID</th>
                                <th class="p-2">Nome</th>
                                <th class="p-2">Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in lastPunches" :key="i" class="border-t border-slate-100">
                                <td class="p-2">{{ row.id }}</td>
                                <td class="p-2">{{ row.nome }}</td>
                                <td class="p-2">{{ row.data }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-show="tab === 'bank'" class="space-y-4">
                <p class="text-sm text-slate-600">
                    Consulta alinhada ao endpoint RHID
                    <code class="rounded bg-slate-100 px-1 text-xs">GET customerdb/person.svc/person_banco_horas</code>
                    (parametro <code class="text-xs">date</code> em YYYYMMDD; filtros opcionais abaixo). Sem filtros, o
                    backend faz uma unica chamada <code class="text-xs">?date=</code> (comportamento da API). A agregacao
                    por varias requisicoes so vale com <code class="text-xs">RHID_BANK_HOURS_AGGREGATE=true</code> no servidor.
                    O saldo exibido segue o retorno da API (incl. texto <code class="text-xs">strSaldo*</code> ou cadastro
                    em <code class="text-xs">person</code> quando existir); use a mesma data de referencia do espelho no RHID para comparar.
                </p>
                <div class="grid max-w-4xl gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <InputLabel value="Data de referencia (date)" />
                        <input
                            v-model="bankDateHtml"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                    </div>
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
                    <div>
                        <InputLabel value="Departamento (departments)" />
                        <input
                            v-model="bankFilterDepartments"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Funcionario (people)" />
                        <input
                            v-model="bankFilterPerson"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Cargo (personroles)" />
                        <input
                            v-model="bankFilterPersonroles"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                </div>
                <PrimaryButton type="button" :disabled="loading" @click="loadBankHours">Consultar banco de horas</PrimaryButton>
                <p v-if="bankResult?.source" class="text-xs text-slate-500">
                    Fonte: {{ bankResult.source }} · Data referencia: {{ bankResult.date }}
                </p>

                <div v-if="bankRows.length" class="grid gap-4 lg:grid-cols-3">
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-3"
                    >
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Distribuicao de saldo</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Colaboradores por faixa de saldo (HH:mm, mesmo conceito do espelho RHID) na data de referencia.
                            <span class="font-medium text-talents-800"> Clique numa fatia</span> para ver a lista.
                        </p>
                        <apexchart
                            v-if="bankDonutChart.series.length"
                            type="donut"
                            height="300"
                            :options="bankDonutChart.options"
                            :series="bankDonutChart.series"
                        />
                        <p v-else class="text-sm text-slate-500">Sem dados para o grafico.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="mb-1 text-sm font-semibold text-slate-800">Media de saldo por departamento</h3>
                        <p class="mb-3 text-xs text-slate-500">
                            Apenas linhas com saldo numerico; ate 12 departamentos + Outros.
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
                            Nenhum saldo numerico para agrupar por departamento.
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
                <RhidResponsePanel v-if="bankResult" :data="bankResult" title="Resposta completa (suporte)" />
            </div>

            <div v-show="tab === 'justifications'" class="space-y-4">
                <p class="text-sm text-slate-600">
                    Listagem alinhada ao endpoint RHID
                    <code class="rounded bg-slate-100 px-1 text-xs">POST customerdb/justification.svc/list</code>
                    — parametros <code class="text-xs">ini</code> e <code class="text-xs">fim</code> em formato AnoMesDia
                    (YYYYMMDD). Filtros opcionais aceitam um ou varios IDs separados por virgula ou espaco.
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
                        <InputLabel value="Registros por pagina (maxSize)" />
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
                            Pagina {{ justPage + 1
                            }}<span v-if="justRecordsTotal != null"> · Total {{ justRecordsTotal }} registro(s)</span>
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Empresas (companies)" />
                        <input
                            v-model="justFilterCompanies"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="ex.: 1 ou 1, 2"
                        />
                    </div>
                    <div>
                        <InputLabel value="Centros de custo (costcenters)" />
                        <input
                            v-model="justFilterCostcenters"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Departamentos (departments)" />
                        <input
                            v-model="justFilterDepartments"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Cargos (personroles)" />
                        <input
                            v-model="justFilterPersonroles"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Funcionarios (people)" />
                        <input
                            v-model="justFilterPeople"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Horarios (shifts)" />
                        <input
                            v-model="justFilterShifts"
                            type="text"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                            placeholder="opcional"
                        />
                    </div>
                    <div>
                        <InputLabel value="Tipos de justificativa (justificationTypes)" />
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
                        Pagina anterior
                    </SecondaryButton>
                    <SecondaryButton type="button" :disabled="loading || !canJustNextPage" @click="justGoNext">
                        Proxima pagina
                    </SecondaryButton>
                </div>
                <div v-if="justRows.length" class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="whitespace-nowrap p-2">ID</th>
                                <th class="whitespace-nowrap p-2">PIS</th>
                                <th class="whitespace-nowrap p-2">Nome</th>
                                <th class="whitespace-nowrap p-2">Inicio</th>
                                <th class="whitespace-nowrap p-2">Fim</th>
                                <th class="whitespace-nowrap p-2">Justificativa</th>
                                <th class="min-w-[12rem] p-2">Descricao</th>
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
                <p v-else-if="justResult && !loading" class="text-sm text-slate-500">Nenhuma justificativa neste periodo ou filtros.</p>
                <RhidResponsePanel
                    v-if="justResult && tab === 'justifications'"
                    :data="justResult"
                    title="Resposta completa (suporte)"
                />
            </div>

            <div v-show="tab === 'reports'" class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <InputLabel value="Formato de saida" />
                        <select v-model="reportFormato" class="mt-1 w-full rounded-md border border-slate-300 text-sm">
                            <option value="PDF">PDF</option>
                            <option value="PDF2">PDF2</option>
                            <option value="CSV">CSV</option>
                            <option value="HTML">HTML</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Tipo de relatorio" />
                        <select v-model="reportNome" class="mt-1 w-full rounded-md border border-slate-300 text-sm">
                            <option value="espelho">Espelho de ponto</option>
                            <option value="cartao">Cartao de ponto</option>
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
                <details class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                    <summary class="cursor-pointer font-medium text-slate-800">JSON avancado (opcional)</summary>
                    <textarea
                        v-model="reportJsonOverride"
                        class="mt-2 w-full rounded-md border border-slate-300 font-mono text-xs"
                        rows="4"
                        placeholder="Substitui os campos acima"
                    />
                </details>
                <div class="flex flex-wrap gap-2">
                    <PrimaryButton type="button" :disabled="loading" @click="startReport">Iniciar relatorio</PrimaryButton>
                    <PrimaryButton type="button" :disabled="loading || !reportGuid" @click="pollReportStatus">
                        Atualizar status
                    </PrimaryButton>
                    <PrimaryButton type="button" :disabled="!reportGuid" @click="downloadReport">Download</PrimaryButton>
                </div>
                <p v-if="reportGuid" class="text-sm text-slate-600">
                    GUID: <code class="rounded bg-slate-100 px-1">{{ reportGuid }}</code>
                    <span v-if="reportPercent !== null" class="ml-2">Percentual: {{ reportPercent }}%</span>
                </p>
                <RhidResponsePanel
                    v-if="reportPanelData"
                    :data="reportPanelData"
                    title="Status / resposta"
                />
            </div>

            <div v-show="tab === 'collaborators'" class="space-y-3">
                <p class="text-sm text-slate-600">
                    Lista os colaboradores cadastrados no RHID (ate 500 por consulta).
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
                            <tr v-for="(row, i) in peopleRows" :key="row.id ?? i" class="border-t border-slate-100">
                                <td class="p-2 font-mono text-xs">{{ row.id }}</td>
                                <td class="p-2">
                                    <Link
                                        v-if="row.id != null"
                                        :href="route('client.rhid.collaborators.show', row.id)"
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
                    v-if="peopleList && tab === 'collaborators'"
                    :data="peopleList"
                    title="Resposta bruta (suporte)"
                />
            </div>
        </div>
    </ClientLayout>
</template>
