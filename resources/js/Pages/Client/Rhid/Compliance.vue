<script setup>
import RhidResponsePanel from '@/Components/Rhid/RhidResponsePanel.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import {
    extractListItems,
    formatRhidDotNetDate,
    monthRangeHtmlDates,
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
    { id: 'reports', label: 'Relatorios' },
    { id: 'collaborators', label: 'Colaboradores' },
];

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

const bankDisplayName = (row) =>
    row?.nome ??
    row?.name ??
    row?.person?.nome ??
    row?.person?.name ??
    row?.strPersonName ??
    (row?.idPerson != null ? `ID ${row.idPerson}` : row?.id != null ? `ID ${row.id}` : '—');

const bankHasOptionalFilters = () =>
    [bankFilterCompanies, bankFilterCostcenters, bankFilterDepartments, bankFilterPerson, bankFilterPersonroles].some(
        (r) => String(r.value ?? '').trim() !== '',
    );

const bankRowAdmissao = (row) =>
    (row?.admissionDateStr && String(row.admissionDateStr).trim()) ||
    formatRhidDotNetDate(row?.admissionDate) ||
    '—';

const bankRowInicioBh = (row) =>
    (row?.inicioBancoHorasStr && String(row.inicioBancoHorasStr).trim()) ||
    formatRhidDotNetDate(row?.inicioBancoHoras) ||
    '—';

const bankRowEmpresa = (row) =>
    row?.companyTradingName || row?.companyName || (row?.idCompany != null ? `#${row.idCompany}` : '—');

const bankRowDepartamento = (row) =>
    row?.departmentName || (row?.idDepartment != null ? `#${row.idDepartment}` : '—');

const bankRowCentroCusto = (row) =>
    row?.costCenterName || (row?.idCostCenter != null ? `#${row.idCostCenter}` : '—');

const bankRowCargo = (row) => row?.roleName || (row?.idPersonRole != null ? `#${row.idPersonRole}` : '—');

const bankDisplayValue = (row) => {
    const keys = [
        'strSaldoBancoHoras',
        'saldoBancoHoras',
        'bancoHoras',
        'saldo',
        'strBanco',
        'strSaldo',
        'totalBancoHoras',
        'minutesBank',
        'balance',
    ];
    for (const k of keys) {
        const v = row?.[k];
        if (v != null && v !== '') {
            return String(v);
        }
    }
    return '—';
};

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
                    sistema agrega todos os colaboradores em varias chamadas quando necessario.
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
                <div v-if="bankRows.length" class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-[72rem] text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="whitespace-nowrap p-2">ID</th>
                                <th class="whitespace-nowrap p-2">Nome</th>
                                <th class="whitespace-nowrap p-2">Nome social</th>
                                <th class="whitespace-nowrap p-2">Matricula</th>
                                <th class="whitespace-nowrap p-2">CPF</th>
                                <th class="whitespace-nowrap p-2">Saldo BH</th>
                                <th class="whitespace-nowrap p-2">Empresa</th>
                                <th class="whitespace-nowrap p-2">Depto</th>
                                <th class="whitespace-nowrap p-2">Centro custo</th>
                                <th class="whitespace-nowrap p-2">Cargo</th>
                                <th class="whitespace-nowrap p-2">Admissao</th>
                                <th class="whitespace-nowrap p-2">Inicio BH</th>
                                <th class="whitespace-nowrap p-2">E-mail</th>
                                <th class="whitespace-nowrap p-2">Telefone</th>
                                <th class="whitespace-nowrap p-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in bankRows" :key="row.id ?? i" class="border-t border-slate-100">
                                <td class="whitespace-nowrap p-2 font-mono text-xs">{{ row.id ?? '—' }}</td>
                                <td class="max-w-[10rem] truncate p-2 font-medium text-slate-800" :title="bankDisplayName(row)">
                                    {{ bankDisplayName(row) }}
                                </td>
                                <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="row.socialName || ''">
                                    {{ row.socialName || '—' }}
                                </td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.registration ?? '—' }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.cpf ?? '—' }}</td>
                                <td class="whitespace-nowrap p-2 tabular-nums font-medium text-slate-800">
                                    {{ bankDisplayValue(row) }}
                                </td>
                                <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="bankRowEmpresa(row)">
                                    {{ bankRowEmpresa(row) }}
                                </td>
                                <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="bankRowDepartamento(row)">
                                    {{ bankRowDepartamento(row) }}
                                </td>
                                <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="bankRowCentroCusto(row)">
                                    {{ bankRowCentroCusto(row) }}
                                </td>
                                <td class="max-w-[8rem] truncate p-2 text-slate-600" :title="bankRowCargo(row)">
                                    {{ bankRowCargo(row) }}
                                </td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ bankRowAdmissao(row) }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ bankRowInicioBh(row) }}</td>
                                <td class="max-w-[10rem] truncate p-2 text-slate-600" :title="row.email || ''">
                                    {{ row.email || '—' }}
                                </td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.phone || '—' }}</td>
                                <td class="whitespace-nowrap p-2 text-slate-600">{{ row.status ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else-if="bankResult && !loading" class="text-sm text-slate-500">Nenhum registro retornado.</p>
                <RhidResponsePanel v-if="bankResult" :data="bankResult" title="Resposta completa (suporte)" />
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
                                <td class="p-2">{{ personDisplayName(row) }}</td>
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
