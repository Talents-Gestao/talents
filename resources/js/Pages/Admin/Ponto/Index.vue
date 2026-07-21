<script setup>
import CompanyMetricsView from '@/Components/Admin/Rhid/CompanyMetricsView.vue';
import CompanyMetricsDetailTables from '@/Components/Admin/Rhid/CompanyMetricsDetailTables.vue';
import JustificationsCrudPanel from '@/Components/Admin/Ponto/JustificationsCrudPanel.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatRhidDotNetDate } from '@/utils/rhidDate';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    segments: { type: Array, default: () => [] },
});

const tabs = [
    { id: 'resumo', label: 'Resumo' },
    { id: 'marcacoes', label: 'Marcações' },
    { id: 'banco', label: 'Banco de horas' },
    { id: 'aderencia', label: 'Aderência' },
    { id: 'justificativas', label: 'Justificativas' },
    { id: 'colaboradores', label: 'Colaboradores' },
];

const selectedCompanyId = ref(props.companies[0]?.id ?? null);
const search = ref('');
const selectedSegment = ref('');
const activeTab = ref('resumo');

const companyLoading = ref(false);
const companyError = ref(null);
const companyMetrics = ref(null);
const companyLoadedAt = ref(null);

const punchesLoading = ref(false);
const punchesError = ref(null);
const livePunches = ref([]);

const peopleLoading = ref(false);
const peopleError = ref(null);
const peopleRows = ref([]);
const peopleSearch = ref('');

const filteredCompanies = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.companies.filter((company) => {
        const bySegment = !selectedSegment.value || company.segment === selectedSegment.value;
        const byTerm = !term || company.name?.toLowerCase().includes(term);
        return bySegment && byTerm;
    });
});

const selectedCompany = computed(
    () => props.companies.find((company) => Number(company.id) === Number(selectedCompanyId.value)) ?? null,
);

const detailSections = computed(() => {
    switch (activeTab.value) {
        case 'marcacoes':
            return ['punches'];
        case 'banco':
            return ['bank'];
        case 'aderencia':
            return ['adherence'];
        default:
            return null;
    }
});

const showMetricsTables = computed(
    () =>
        companyMetrics.value?.status === 'ok' &&
        !companyLoading.value &&
        ['resumo', 'marcacoes', 'banco', 'aderencia'].includes(activeTab.value),
);

const filteredPeople = computed(() => {
    const term = peopleSearch.value.trim().toLowerCase();
    const rows = peopleRows.value;
    if (!term) {
        return rows;
    }
    return rows.filter((row) =>
        [row.name, row.registration, row.department, row.role]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(term)),
    );
});

const normalizePeoplePayload = (payload) => {
    const list = Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
          ? payload
          : [];

    return list.map((row) => {
        if (!row || typeof row !== 'object') {
            return { name: '—', registration: '', department: '', role: '', id: null };
        }
        return {
            id: row.id ?? row.idPerson ?? row.Id ?? null,
            name: row.name ?? row.nome ?? row.strName ?? row.Name ?? '—',
            registration: row.registration ?? row.matricula ?? row.strRegistration ?? '',
            department: row.departmentName ?? row.departamento ?? row.strDepartment ?? '',
            role: row.personRoleName ?? row.cargo ?? row.strPersonRole ?? '',
        };
    });
};

const normalizePunchesPayload = (payload) => {
    const list = Array.isArray(payload) ? payload : Array.isArray(payload?.data) ? payload.data : [];
    return list.slice(0, 150).map((row) => ({
        name: row.name ?? row.nome ?? row.personName ?? row.strName ?? '—',
        datetime: row.data ?? row.dateTime ?? row.Data ?? row.date ?? null,
    }));
};

const loadCompanyMetrics = async (refresh = false) => {
    if (!selectedCompanyId.value) {
        companyMetrics.value = null;
        companyError.value = null;
        return;
    }

    companyLoading.value = true;
    companyError.value = null;

    try {
        const { data } = await axios.get(route('admin.ponto.companies.metrics', selectedCompanyId.value), {
            params: refresh ? { refresh: 1 } : {},
            timeout: 120000,
        });
        companyMetrics.value = data;
        companyLoadedAt.value = new Date();

        if (data?.status === 'error') {
            companyError.value = data.error || 'Falha ao carregar indicadores da empresa.';
        } else if (data?.status === 'not_configured') {
            companyError.value = data.message || 'Integração RHID não configurada para esta empresa.';
        }
    } catch (e) {
        companyMetrics.value = null;
        companyError.value = e?.response?.data?.message || 'Falha ao carregar indicadores da empresa.';
    } finally {
        companyLoading.value = false;
    }
};

const loadLivePunches = async () => {
    if (!selectedCompanyId.value) {
        livePunches.value = [];
        return;
    }
    punchesLoading.value = true;
    punchesError.value = null;
    try {
        const { data } = await axios.get(route('admin.ponto.companies.last-punches', selectedCompanyId.value), {
            timeout: 60000,
        });
        livePunches.value = normalizePunchesPayload(data);
    } catch (e) {
        livePunches.value = [];
        punchesError.value = e?.response?.data?.message || 'Falha ao carregar últimas marcações.';
    } finally {
        punchesLoading.value = false;
    }
};

const loadPeople = async () => {
    if (!selectedCompanyId.value) {
        peopleRows.value = [];
        return;
    }
    peopleLoading.value = true;
    peopleError.value = null;
    try {
        const { data } = await axios.get(route('admin.ponto.companies.people', selectedCompanyId.value), {
            params: { page: 0, maxSize: 200, status: 1 },
            timeout: 60000,
        });
        peopleRows.value = normalizePeoplePayload(data);
    } catch (e) {
        peopleRows.value = [];
        peopleError.value = e?.response?.data?.message || 'Falha ao carregar colaboradores RHID.';
    } finally {
        peopleLoading.value = false;
    }
};

watch(selectedCompanyId, async () => {
    livePunches.value = [];
    peopleRows.value = [];
    await loadCompanyMetrics(false);
    if (activeTab.value === 'marcacoes') {
        await loadLivePunches();
    }
    if (activeTab.value === 'colaboradores') {
        await loadPeople();
    }
});

watch(selectedSegment, () => {
    if (
        selectedCompanyId.value &&
        !filteredCompanies.value.some((company) => company.id === selectedCompanyId.value)
    ) {
        selectedCompanyId.value = filteredCompanies.value[0]?.id ?? null;
    }
});

watch(activeTab, async (tab) => {
    if (tab === 'marcacoes' && selectedCompanyId.value && !livePunches.value.length && !punchesLoading.value) {
        await loadLivePunches();
    }
    if (tab === 'colaboradores' && selectedCompanyId.value && !peopleRows.value.length && !peopleLoading.value) {
        await loadPeople();
    }
});

onMounted(async () => {
    await loadCompanyMetrics(false);
});
</script>

<template>
    <Head title="Gestão de ponto" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Gestão de ponto</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Operação por empresa sobre a API RHID (Control iD) — marcações, banco, aderência e colaboradores.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.rhid.index')"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-talents-700 ring-1 ring-talents-200 transition hover:bg-talents-50"
                    >
                        Portfólio RHID
                    </Link>
                    <PrimaryButton
                        type="button"
                        :disabled="companyLoading || !selectedCompanyId"
                        @click="loadCompanyMetrics(true)"
                    >
                        Atualizar indicadores
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <section class="grid gap-6 xl:grid-cols-12">
            <aside class="surface-card p-5 xl:col-span-3">
                <div class="space-y-3">
                    <h3 class="text-base font-semibold text-talents-800">Empresas com RHID</h3>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar empresa..."
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                    <select
                        v-model="selectedSegment"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos os segmentos</option>
                        <option v-for="segment in segments" :key="segment" :value="segment">
                            {{ segment }}
                        </option>
                    </select>
                </div>

                <ul v-if="filteredCompanies.length" class="mt-4 max-h-[30rem] space-y-2 overflow-auto pr-1">
                    <li v-for="company in filteredCompanies" :key="company.id">
                        <button
                            type="button"
                            class="w-full rounded-xl border px-3 py-2 text-left transition"
                            :class="
                                company.id === selectedCompanyId
                                    ? 'border-talents-300 bg-talents-50'
                                    : 'border-slate-200 bg-white hover:border-talents-200 hover:bg-slate-50'
                            "
                            @click="selectedCompanyId = company.id"
                        >
                            <p class="truncate text-sm font-semibold text-slate-800">{{ company.name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ company.segment || 'Sem segmento' }}
                            </p>
                        </button>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    class="mt-4 border-0 bg-transparent"
                    title="Nenhuma empresa encontrada"
                    description="Configure credenciais RHID na ficha da empresa ou no portal do cliente."
                />
            </aside>

            <div class="space-y-5 xl:col-span-9">
                <CompanyMetricsView
                    :title="selectedCompany ? `Ponto — ${selectedCompany.name}` : 'Gestão de ponto'"
                    description="Indicadores do mês corrente via RHID: banco de horas, aderência e justificativas."
                    :rhid-configured="Boolean(selectedCompany)"
                    :loading="companyLoading"
                    :error="companyError"
                    :metrics="companyMetrics"
                    :loaded-at="companyLoadedAt"
                    :show-refresh="Boolean(selectedCompany)"
                    refresh-label="Atualizar empresa"
                    @refresh="loadCompanyMetrics(true)"
                />

                <div v-if="selectedCompany" class="surface-card overflow-hidden">
                    <div class="flex flex-wrap gap-1 border-b border-slate-100 px-3 py-2 sm:px-4">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-talents-100 text-talents-800'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-talents-700'
                            "
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="p-4 sm:p-5">
                        <template v-if="activeTab === 'resumo'">
                            <p class="mb-4 text-sm text-slate-600">
                                Use as abas para focar marcações, banco, aderência, justificativas ou a lista de
                                colaboradores ativos na RHID. Os indicadores acima resumem o mês corrente.
                            </p>
                            <CompanyMetricsDetailTables
                                v-if="showMetricsTables"
                                class="!mt-0"
                                :metrics="companyMetrics"
                            />
                        </template>

                        <div v-else-if="activeTab === 'marcacoes'" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm text-slate-600">
                                    Últimas marcações em tempo real na RHID.
                                </p>
                                <PrimaryButton type="button" :disabled="punchesLoading" @click="loadLivePunches">
                                    {{ punchesLoading ? 'Atualizando…' : 'Atualizar marcações' }}
                                </PrimaryButton>
                            </div>
                            <p v-if="punchesError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                {{ punchesError }}
                            </p>
                            <div class="max-h-[28rem] overflow-auto rounded-xl ring-1 ring-slate-100">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                        <tr>
                                            <th class="px-4 py-2">Colaborador</th>
                                            <th class="px-4 py-2">Data/hora</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, idx) in livePunches"
                                            :key="`live-punch-${idx}`"
                                            class="border-t border-slate-50"
                                        >
                                            <td class="px-4 py-2 font-medium text-slate-800">{{ row.name }}</td>
                                            <td class="px-4 py-2 text-slate-600">
                                                {{ formatRhidDotNetDate(row.datetime, { withTime: true }) || '—' }}
                                            </td>
                                        </tr>
                                        <tr v-if="!livePunches.length && !punchesLoading">
                                            <td colspan="2" class="px-4 py-8 text-center text-slate-500">
                                                Sem marcações retornadas. Atualize ou confira a integração RHID.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <CompanyMetricsDetailTables
                                v-if="showMetricsTables"
                                class="!mt-4"
                                :metrics="companyMetrics"
                                :sections="['punches']"
                            />
                        </div>

                        <div v-else-if="activeTab === 'justificativas'">
                            <JustificationsCrudPanel :company-id="selectedCompanyId" />
                        </div>

                        <div v-else-if="activeTab === 'colaboradores'" class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <input
                                    v-model="peopleSearch"
                                    type="search"
                                    placeholder="Buscar colaborador..."
                                    class="w-full max-w-md rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                <PrimaryButton type="button" :disabled="peopleLoading" @click="loadPeople">
                                    {{ peopleLoading ? 'Atualizando…' : 'Atualizar lista' }}
                                </PrimaryButton>
                            </div>
                            <p v-if="peopleError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                                {{ peopleError }}
                            </p>
                            <div class="max-h-[28rem] overflow-auto rounded-xl ring-1 ring-slate-100">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                        <tr>
                                            <th class="px-4 py-2">Nome</th>
                                            <th class="px-4 py-2">Matrícula</th>
                                            <th class="px-4 py-2">Departamento</th>
                                            <th class="px-4 py-2">Cargo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, idx) in filteredPeople"
                                            :key="`person-${row.id ?? idx}`"
                                            class="border-t border-slate-50"
                                        >
                                            <td class="px-4 py-2 font-medium text-slate-800">{{ row.name }}</td>
                                            <td class="px-4 py-2 text-slate-600">{{ row.registration || '—' }}</td>
                                            <td class="px-4 py-2 text-slate-600">{{ row.department || '—' }}</td>
                                            <td class="px-4 py-2 text-slate-600">{{ row.role || '—' }}</td>
                                        </tr>
                                        <tr v-if="!filteredPeople.length && !peopleLoading">
                                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                                Nenhum colaborador ativo encontrado.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <CompanyMetricsDetailTables
                            v-else-if="showMetricsTables && detailSections"
                            class="!mt-0"
                            :metrics="companyMetrics"
                            :sections="detailSections"
                        />
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
