<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import {
    formatApiDatePtBr,
    formatPeriodPtBr,
    formatRhidBankBalanceDisplay,
    formatRhidDotNetDate,
    pickRhidPersonDisplayName,
    todayHtmlDate,
    toRhidYmd,
} from '@/utils/rhidDate';

const props = defineProps({
    configured: { type: Boolean, required: true },
    personId: { type: Number, required: true },
});

const loading = ref(false);
const err = ref(null);
const detail = ref(null);
const bankDateHtml = ref(todayHtmlDate());
const bankPack = ref(null);

/** Espelhos importados no Talents (mesmo id_person RHID) */
const espelhoImportsPage = ref(null);
const espelhoListLoading = ref(false);
const expandedImportId = ref(null);
const expandedImportDetail = ref(null);
const espelhoDetailLoading = ref(false);

/** Preferencia Talents: 1º vs 2º intervalo de almoco na aderencia */
const schedulePrefSecond = ref(false);
const schedulePrefSaving = ref(false);

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
 * @param {Record<string, unknown>|null|undefined} frag
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

const espelhoPunchTableRows = computed(() => {
    const imp = expandedImportDetail.value;
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

const parseStatusLabel = (s) => {
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

const displayName = computed(() => pickRhidPersonDisplayName(detail.value ?? {}));
const bankRow = computed(() => {
    const rows = bankPack.value?.rows;
    return Array.isArray(rows) && rows[0] ? rows[0] : null;
});

const deptLabel = computed(() => {
    const d = detail.value;
    if (!d || typeof d !== 'object') {
        return '—';
    }
    if (d.departmentName && String(d.departmentName).trim()) {
        return String(d.departmentName).trim();
    }
    return d.idDepartment != null ? `#${d.idDepartment}` : '—';
});

const cargoLabel = computed(() => {
    const d = detail.value;
    if (!d || typeof d !== 'object') {
        return '—';
    }
    if (d.roleName && String(d.roleName).trim()) {
        return String(d.roleName).trim();
    }
    return d.idPersonRole != null ? `#${d.idPersonRole}` : '—';
});

const admissaoLabel = computed(() => {
    const d = detail.value;
    if (!d) {
        return '—';
    }
    const s = d.admissionDateStr && String(d.admissionDateStr).trim();
    if (s) {
        return s;
    }
    return formatRhidDotNetDate(d.admissionDate) || '—';
});

const clearErr = () => {
    err.value = null;
};

const handleError = (e) => {
    err.value = e.response?.data?.message || e.message || 'Erro na requisicao';
};

const loadDetail = async () => {
    if (!props.configured) {
        return;
    }
    loading.value = true;
    clearErr();
    detail.value = null;
    try {
        const { data } = await axios.get(route('client.rhid.api.people.show', props.personId));
        detail.value = data;
        schedulePrefSecond.value = Boolean(data.schedulePreference?.use_second_lunch_interval);
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const saveSchedulePreference = async () => {
    if (!props.configured) {
        return;
    }
    schedulePrefSaving.value = true;
    clearErr();
    try {
        const { data } = await axios.put(
            route('client.rhid.api.people.schedule-preference.update', props.personId),
            { use_second_lunch_interval: schedulePrefSecond.value },
        );
        if (data?.schedulePreference) {
            schedulePrefSecond.value = Boolean(data.schedulePreference.use_second_lunch_interval);
        }
    } catch (e) {
        handleError(e);
    } finally {
        schedulePrefSaving.value = false;
    }
};

const loadBank = async () => {
    if (!props.configured) {
        return;
    }
    loading.value = true;
    clearErr();
    bankPack.value = null;
    try {
        const dateParam = toRhidYmd(bankDateHtml.value) || bankDateHtml.value;
        const { data } = await axios.get(route('client.rhid.api.person-bank-hours'), {
            params: {
                date: dateParam,
                people: [props.personId],
            },
        });
        bankPack.value = data;
    } catch (e) {
        handleError(e);
    } finally {
        loading.value = false;
    }
};

const loadEspelhoImports = async () => {
    if (!props.configured) {
        return;
    }
    espelhoListLoading.value = true;
    try {
        const { data } = await axios.get(route('client.rhid.api.espelhos.imports.index'), {
            params: { id_person: props.personId, per_page: 30 },
        });
        espelhoImportsPage.value = data;
    } catch (e) {
        handleError(e);
    } finally {
        espelhoListLoading.value = false;
    }
};

const toggleEspelhoMarcacoes = async (importId) => {
    if (expandedImportId.value === importId) {
        expandedImportId.value = null;
        expandedImportDetail.value = null;
        return;
    }
    expandedImportId.value = importId;
    expandedImportDetail.value = null;
    espelhoDetailLoading.value = true;
    clearErr();
    try {
        const { data } = await axios.get(route('client.rhid.api.espelhos.imports.show', importId));
        expandedImportDetail.value = data.import;
    } catch (e) {
        handleError(e);
        expandedImportId.value = null;
    } finally {
        espelhoDetailLoading.value = false;
    }
};

onMounted(async () => {
    if (!props.configured) {
        return;
    }
    await loadDetail();
    await Promise.all([loadBank(), loadEspelhoImports()]);
});
</script>

<template>
    <Head :title="`RHID — ${displayName !== '—' ? displayName : 'Colaborador'}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <Link
                        :href="route('client.rhid.compliance.index')"
                        class="mb-1 inline-block text-sm text-talents-700 hover:underline"
                    >
                        Compliance RHID
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">
                        Colaborador — RHID
                    </h2>
                </div>
            </div>
        </template>

        <div
            v-if="!configured"
            class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
        >
            <p class="font-semibold">Integracao nao configurada</p>
            <p class="mt-1">Cadastre e-mail e senha da API RHID nas configuracoes.</p>
        </div>

        <div v-else class="space-y-6">
            <p v-if="err" class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ err }}</p>
            <p v-if="loading && !detail" class="text-sm text-slate-500">Carregando cadastro...</p>

            <div
                v-if="detail"
                class="grid gap-4 md:grid-cols-2"
            >
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold text-slate-800">Identificacao</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Nome</dt>
                            <dd class="text-right font-medium text-slate-900">{{ displayName }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">ID RHID</dt>
                            <dd class="font-mono text-xs text-slate-800">{{ detail.id ?? personId }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Matricula / PIS</dt>
                            <dd class="text-slate-800">
                                {{ detail.registration ?? detail.matricula ?? detail.pis ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">CPF</dt>
                            <dd class="text-slate-800">{{ detail.cpf ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 pb-1">
                            <dt class="text-slate-500">E-mail</dt>
                            <dd class="max-w-[14rem] truncate text-slate-800" :title="detail.email || ''">
                                {{ detail.email || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Telefone</dt>
                            <dd class="text-slate-800">{{ detail.phone || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold text-slate-800">Vinculo</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Empresa</dt>
                            <dd class="max-w-[14rem] truncate text-right text-slate-800" :title="detail.companyTradingName || detail.companyName || ''">
                                {{ detail.companyTradingName || detail.companyName || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Departamento</dt>
                            <dd class="text-right text-slate-800">{{ deptLabel }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Cargo</dt>
                            <dd class="text-right text-slate-800">{{ cargoLabel }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Centro de custo</dt>
                            <dd class="text-right text-slate-800">
                                {{ detail.costCenterName || (detail.idCostCenter != null ? `#${detail.idCostCenter}` : '—') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Admissao</dt>
                            <dd class="text-slate-800">{{ admissaoLabel }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Status</dt>
                            <dd class="text-slate-800">{{ detail.statusStr ?? detail.status ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div
                v-if="detail"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Aderencia (Talents)</h3>
                <p class="mb-3 text-xs text-slate-600">
                    Na analise de aderencia (Compliance &gt; Marcacoes &gt; Aderencia), as batidas SAI.1 / ENT.2 sao
                    comparadas ao horario de almoco esperado. Marque abaixo se este colaborador usa o
                    <span class="font-medium">segundo intervalo de almoco</span> definido na configuracao de horarios da
                    empresa (requer horarios &quot;Inicio 2º / Fim 2º&quot; por dia). Caso contrario, usa-se o primeiro
                    intervalo (saida/volta do almoco).
                </p>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-800">
                    <input
                        v-model="schedulePrefSecond"
                        type="checkbox"
                        class="rounded border-slate-300 text-talents-700 focus:ring-talents-600"
                    />
                    Usar segundo intervalo de almoco na aderencia
                </label>
                <div class="mt-3">
                    <PrimaryButton type="button" :disabled="schedulePrefSaving || loading" @click="saveSchedulePreference">
                        Salvar preferencia
                    </PrimaryButton>
                </div>
            </div>

            <div v-if="detail" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Banco de horas (referencia)</h3>
                <div class="mb-4 flex max-w-md flex-wrap items-end gap-3">
                    <div>
                        <InputLabel value="Data de referencia" />
                        <input
                            v-model="bankDateHtml"
                            type="date"
                            class="mt-1 block w-full rounded-md border border-slate-300 text-sm shadow-sm"
                        />
                    </div>
                    <PrimaryButton type="button" :disabled="loading" @click="loadBank">Consultar saldo</PrimaryButton>
                </div>
                <p v-if="bankPack?.source" class="mb-2 text-xs text-slate-500">
                    Fonte: {{ bankPack.source }} · Data: {{ bankPack.date }}
                </p>
                <p v-if="bankRow" class="text-lg font-semibold tabular-nums text-slate-900">
                    {{ formatRhidBankBalanceDisplay(bankRow) }}
                </p>
                <p v-else-if="bankPack && !loading" class="text-sm text-slate-500">Nenhum retorno de saldo para esta data.</p>
            </div>

            <div v-if="detail" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Espelhos importados</h3>
                <p class="mb-3 text-xs text-slate-500">
                    Dados trazidos do RHID pela area Compliance; o periodo abaixo esta no calendario brasileiro.
                </p>
                <p v-if="espelhoListLoading" class="text-sm text-slate-500">Carregando importacoes…</p>
                <p v-else-if="!espelhoImportsPage?.data?.length" class="text-sm text-slate-500">
                    Nenhum espelho importado ainda para este colaborador.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-600">
                            <tr>
                                <th class="p-2">Período</th>
                                <th class="p-2">Leitura</th>
                                <th class="p-2">Quando</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in espelhoImportsPage.data" :key="row.id">
                                <tr class="border-t border-slate-100">
                                    <td class="p-2 whitespace-nowrap">
                                        {{ formatPeriodPtBr(row.period_ini, row.period_fim) }}
                                    </td>
                                    <td class="p-2">
                                        <span
                                            :class="{
                                                'text-emerald-700': row.parse_status === 'ok',
                                                'text-amber-700': row.parse_status === 'pending',
                                                'text-red-700': row.parse_status === 'failed',
                                            }"
                                        >
                                            {{ parseStatusLabel(row.parse_status) }}
                                        </span>
                                    </td>
                                    <td class="p-2 text-slate-600">{{ formatApiDatePtBr(row.created_at) }}</td>
                                    <td class="p-2 whitespace-nowrap">
                                        <a
                                            :href="route('client.rhid.api.espelhos.imports.file', row.id)"
                                            class="mr-2 text-xs font-medium text-talents-800 hover:underline"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            PDF
                                        </a>
                                        <SecondaryButton
                                            type="button"
                                            class="!px-2 !py-1 text-xs"
                                            @click="toggleEspelhoMarcacoes(row.id)"
                                        >
                                            {{
                                                expandedImportId === row.id
                                                    ? 'Fechar'
                                                    : row.parse_status === 'ok'
                                                      ? 'Marcacoes'
                                                      : 'Detalhes'
                                            }}
                                        </SecondaryButton>
                                    </td>
                                </tr>
                                <tr v-if="expandedImportId === row.id" class="border-t border-slate-100 bg-slate-50/80">
                                    <td colspan="4" class="p-3">
                                        <p v-if="espelhoDetailLoading" class="text-sm text-slate-500">Carregando…</p>
                                        <p
                                            v-else-if="expandedImportDetail?.parse_status === 'pending'"
                                            class="text-sm text-amber-800"
                                        >
                                            Leitura do PDF ainda em fila; atualize a pagina em instantes.
                                        </p>
                                        <p v-else-if="expandedImportDetail?.parse_error" class="text-sm text-red-700">
                                            {{ expandedImportDetail.parse_error }}
                                        </p>
                                        <div
                                            v-else-if="espelhoPunchTableRows.length"
                                            class="max-h-80 overflow-auto rounded border border-slate-200 bg-white"
                                        >
                                            <table class="min-w-full text-xs">
                                                <thead class="sticky top-0 bg-slate-50">
                                                    <tr>
                                                        <th class="p-2 text-left">Data</th>
                                                        <th
                                                            v-for="col in espelhoSlotColumns"
                                                            :key="col.key"
                                                            class="p-2 text-left"
                                                        >
                                                            {{ col.label }}
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(pr, prIdx) in espelhoPunchTableRows"
                                                        :key="prIdx"
                                                        class="border-t border-slate-100"
                                                    >
                                                        <td class="whitespace-nowrap p-2 text-slate-700">{{ pr.ref_date }}</td>
                                                        <td
                                                            v-for="col in espelhoSlotColumns"
                                                            :key="col.key"
                                                            class="p-2 tabular-nums text-slate-800"
                                                        >
                                                            {{ espelhoMarcacaoSlots(pr.fragment)[col.key] || '—' }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p v-else class="text-sm text-slate-500">Sem marcacoes extraidas neste periodo.</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
