<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { extractListItems, formatPeriodPtBr, monthRangeHtmlDates, toRhidYmd } from '@/utils/rhidDate';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    companyId: { type: Number, required: true },
    configured: { type: Boolean, required: true },
});

const { first: monthFirst, last: monthLast } = monthRangeHtmlDates();

const err = ref(null);
const loading = ref(false);
const espelhoGuid = ref('');
const espelhoPercent = ref(null);
const espelhoIniDate = ref(monthFirst);
const espelhoFimDate = ref(monthLast);
const espelhoStatus = ref('1');
const espelhoSelectedFields = ref(['TODAS_MARCACOES', 'ENTRADAS_SAIDAS']);
const espelhoVinculoPersonId = ref('');
const espelhoPolling = ref(false);
const espelhoPollCancelRequested = ref(false);
const espelhoImportsPage = ref(null);
const espelhoLastImport = ref(null);
const espelhoDetailLoading = ref(false);
const espelhoBatchProgress = ref('');
const espelhoBatchPhase = ref('idle');
const espelhoBatchTotal = ref(0);
const espelhoBatchRemaining = ref(0);
const espelhoBatchPollAbort = ref(false);

const ESPELHO_FIELD_OPTIONS = [
    { value: 'DIA_DA_SEMANA', label: 'Dia da semana' },
    { value: 'TODAS_MARCACOES', label: 'Todas as marcações' },
    { value: 'ENTRADAS_SAIDAS', label: 'Entradas / saídas' },
    { value: 'MARCACOES_DESCONSIDERADAS', label: 'Marcações desconsideradas' },
];

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

const rhidPersonId = (row) => {
    const raw = row?.id != null && row?.id !== '' ? row.id : row?.idPerson;
    if (raw == null || raw === '') {
        return null;
    }
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
};

const isRhidPersonRowClearlyInactive = (row) => {
    if (!row || typeof row !== 'object') {
        return false;
    }
    if (row.status === 2 || row.status === '2') {
        return true;
    }
    return String(row.statusStr ?? '').toLowerCase().includes('inativ');
};

const handleError = (e) => {
    err.value = e?.response?.data?.message || e?.message || 'Falha na operação de espelho.';
};

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

const espelhoTargetPersonId = computed(() => {
    const manualIds = parseIdList(espelhoVinculoPersonId.value);
    if (manualIds?.length === 1) {
        return manualIds[0];
    }
    return null;
});

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

const validateEspelhoPeriod = () => {
    const a = new Date(`${espelhoIniDate.value}T12:00:00`);
    const b = new Date(`${espelhoFimDate.value}T12:00:00`);
    if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) {
        return 'Datas inválidas.';
    }
    if (b < a) {
        return 'A data final deve ser igual ou posterior à inicial.';
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
    const body = {
        formatoSaida: 'PDF',
        ini: toRhidYmd(espelhoIniDate.value),
        fim: toRhidYmd(espelhoFimDate.value),
        relatorio: 'espelho',
        destinoRelatorio: 'DOWNLOAD',
        ordenacao: 'Person',
        listColumns: fields,
        listPropertyStr: fields,
        pdfCartaoPontoParameters: {
            fontSizeTitle: 12,
            fontSizeData: 8,
            fontSizeHeader: 8,
            fontSizeHeaderSmall: 8,
            fontSizeFooter: 8,
            fontName: 'Helvetica',
            listIdStr: personIdsOverride ?? parseIdList(espelhoVinculoPersonId.value) ?? [],
            listCompanyStr: [],
            listDepartmentStr: [],
            listCostCenterStr: [],
            listPersonRoleStr: [],
            listShiftStr: [],
        },
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
    filters: {},
});

const fetchAllRhidPersonIdsFromApi = async () => {
    const maxSize = 500;
    const all = new Set();
    for (let page = 0; page < 200; page++) {
        const { data } = await axios.get(route('admin.ponto.companies.people', props.companyId), {
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

const loadEspelhoImports = async () => {
    if (!props.configured) {
        return;
    }
    try {
        const { data } = await axios.get(route('admin.ponto.companies.espelhos.imports.index', props.companyId), {
            params: { per_page: 15 },
        });
        espelhoImportsPage.value = data;
    } catch (e) {
        handleError(e);
    }
};

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
    err.value = null;
    espelhoPolling.value = true;
    loading.value = true;
    try {
        const { data: startData } = await axios.post(
            route('admin.ponto.companies.reports.start', props.companyId),
            buildEspelhoPayload(),
        );
        espelhoGuid.value = startData.guid || '';
        if (!espelhoGuid.value) {
            err.value = 'Não foi possível obter o GUID do RHID.';
            return;
        }
        for (let i = 0; i < 120; i++) {
            if (espelhoPollCancelRequested.value) {
                break;
            }
            const { data } = await axios.get(route('admin.ponto.companies.reports.status', props.companyId), {
                params: { guid: espelhoGuid.value },
            });
            espelhoPercent.value = data.percent ?? null;
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
        route('admin.ponto.companies.reports.download', props.companyId) +
        `?guid=${encodeURIComponent(espelhoGuid.value)}&format=${encodeURIComponent('PDF')}`;
    window.open(url, '_blank');
};

const cancelEspelhoPoll = () => {
    espelhoPollCancelRequested.value = true;
};

const pollEspelhoImportUntilDone = async (importId) => {
    for (let i = 0; i < 90; i++) {
        const { data } = await axios.get(
            route('admin.ponto.companies.espelhos.imports.show', {
                company: props.companyId,
                import: importId,
            }),
        );
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
        err.value =
            (parseIdList(espelhoVinculoPersonId.value)?.length ?? 0) > 1
                ? 'Vários colaboradores selecionados: use Importar todos ou deixe apenas um ID.'
                : 'Informe um ID de colaborador no campo acima.';
        return;
    }
    err.value = null;
    loading.value = true;
    try {
        const { data } = await axios.post(route('admin.ponto.companies.espelhos.store', props.companyId), {
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
        const { data } = await axios.get(
            route('admin.ponto.companies.espelhos.batch.show', {
                company: props.companyId,
                batch: batchId,
            }),
        );
        const b = data.batch;
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
    let ids = parseIdList(espelhoVinculoPersonId.value) ?? [];
    err.value = null;
    loading.value = true;
    espelhoBatchProgress.value = '';
    espelhoBatchPhase.value = 'resolving';
    espelhoBatchTotal.value = 0;
    espelhoBatchRemaining.value = 0;
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
            }
        }
        if (!ids.length) {
            espelhoBatchPhase.value = 'idle';
            err.value = 'Nenhum colaborador ativo encontrado. Verifique a integração ou informe IDs.';
            return;
        }
        espelhoBatchPhase.value = 'starting';
        const { data } = await axios.post(route('admin.ponto.companies.espelhos.batch.start', props.companyId), {
            person_ids: ids,
            ...buildEspelhoBatchMeta(),
        });
        const batchId = data.batch.id;
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
    err.value = null;
    try {
        await axios.post(
            route('admin.ponto.companies.espelhos.imports.reparse', {
                company: props.companyId,
                import: importId,
            }),
        );
        await pollEspelhoImportUntilDone(importId);
        await loadEspelhoImports();
    } catch (e) {
        handleError(e);
    }
};

const syncParseEspelhoImportNow = async (importId) => {
    if (!importId) {
        return;
    }
    err.value = null;
    espelhoDetailLoading.value = true;
    try {
        const { data } = await axios.post(
            route('admin.ponto.companies.espelhos.imports.parse-sync', {
                company: props.companyId,
                import: importId,
            }),
        );
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
        const { data } = await axios.get(
            route('admin.ponto.companies.espelhos.imports.show', {
                company: props.companyId,
                import: importId,
            }),
        );
        espelhoLastImport.value = data.import;
    } catch (e) {
        handleError(e);
    }
};

watch(
    () => props.companyId,
    () => {
        espelhoGuid.value = '';
        espelhoPercent.value = null;
        espelhoLastImport.value = null;
        espelhoImportsPage.value = null;
        err.value = null;
        if (props.configured) {
            loadEspelhoImports();
        }
    },
);

onMounted(() => {
    if (props.configured) {
        loadEspelhoImports();
    }
});

onBeforeUnmount(() => {
    espelhoBatchPollAbort.value = true;
    espelhoPollCancelRequested.value = true;
});
</script>

<template>
    <div v-if="!configured" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Grave as credenciais RHID para gerar e importar os espelhos de ponto.
    </div>
    <div v-else class="space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Espelho e importação</h3>
            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                Gere o espelho de ponto no período escolhido, baixe o PDF ou importe para o Talents. A leitura das
                marcações continua em segundo plano após o PDF ser salvo. Período de até 31 dias.
            </p>
        </div>

        <div
            v-if="espelhoShowProcessingBanner"
            class="flex flex-wrap items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-950"
            role="status"
        >
            <span
                class="inline-block size-5 shrink-0 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1">
                <p class="font-medium">
                    <template v-if="espelhoPolling">Gerando espelho no RHID…</template>
                    <template v-else-if="espelhoBatchPhase === 'resolving' || espelhoBatchPhase === 'starting'">
                        Preparando importação…
                    </template>
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

        <p v-if="err" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ err }}</p>

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
                >
            </div>
            <div>
                <InputLabel value="Data final" />
                <input
                    v-model="espelhoFimDate"
                    type="date"
                    class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                >
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
                    <input v-model="espelhoSelectedFields" type="checkbox" class="rounded border-slate-300" :value="opt.value">
                    {{ opt.label }}
                </label>
            </div>
        </div>
        <div class="max-w-md">
            <InputLabel value="Colaboradores (importação individual ou lista)" />
            <input
                v-model="espelhoVinculoPersonId"
                type="text"
                class="mt-1 block w-full rounded-md border border-slate-300 font-mono text-sm"
                placeholder="Ex.: 12345 ou 10, 20, 30"
            >
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

        <div v-if="espelhoLastImport" class="rounded-md border border-slate-200 bg-white p-3 text-sm shadow-sm">
            <h4 class="font-medium text-slate-800">Última importação</h4>
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
                    :href="route('admin.ponto.companies.espelhos.imports.file', { company: companyId, import: espelhoLastImport.id })"
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
                    <span class="text-xs font-medium text-slate-500">Nome</span>
                    <p class="mt-0.5 font-medium">{{ espelhoExtractHeader.nome }}</p>
                </div>
                <div>
                    <span class="text-xs font-medium text-slate-500">CPF</span>
                    <p class="mt-0.5 font-mono text-sm">{{ espelhoExtractHeader.cpf }}</p>
                </div>
                <div>
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
        </div>

        <div v-if="espelhoImportsPage?.data?.length" class="rounded-md border border-slate-200 bg-slate-50/80 p-3 text-sm">
            <h4 class="font-medium text-slate-800">Importações recentes</h4>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-left text-slate-600">
                            <th class="p-2">Nº</th>
                            <th class="p-2">Colaborador (RHID)</th>
                            <th class="p-2">Período</th>
                            <th class="p-2">Leitura</th>
                            <th class="p-2" />
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
    </div>
</template>
