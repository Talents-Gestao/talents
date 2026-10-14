<script setup>
import CompanyMetricsView from '@/Components/Admin/Rhid/CompanyMetricsView.vue';
import CompanyMetricsDetailTables from '@/Components/Admin/Rhid/CompanyMetricsDetailTables.vue';
import JustificationsCrudPanel from '@/Components/Admin/TimeClock/JustificationsCrudPanel.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatRhidDotNetDate } from '@/utils/rhidDate';
import { BuildingOffice2Icon } from '@heroicons/vue/24/outline';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
});

const TAB_IDS = ['hoje', 'banco', 'justificativas', 'equipe'];

const tabs = [
    { id: 'hoje', label: 'Hoje' },
    { id: 'banco', label: 'Banco de horas' },
    { id: 'justificativas', label: 'Justificativas' },
    { id: 'equipe', label: 'Equipe' },
];

const selectedCompanyId = ref(props.companies[0]?.id ?? null);
const search = ref('');
const activeTab = ref('hoje');

(() => {
    try {
        const params = new URLSearchParams(window.location.search);
        const fromQuery = Number(params.get('company'));
        if (fromQuery && props.companies.some((c) => Number(c.id) === fromQuery)) {
            selectedCompanyId.value = fromQuery;
        }
        const tabQuery = params.get('tab');
        if (tabQuery && TAB_IDS.includes(tabQuery)) {
            activeTab.value = tabQuery;
        }
    } catch {
        /* ignore */
    }
})();

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
const justificationsPanel = ref(null);
const pageRefreshing = ref(false);

const isRefreshing = computed(
    () =>
        pageRefreshing.value
        || companyLoading.value
        || punchesLoading.value
        || peopleLoading.value,
);

const filteredCompanies = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.companies.filter((company) => !term || company.name?.toLowerCase().includes(term));
});

const selectedCompany = computed(
    () => props.companies.find((company) => Number(company.id) === Number(selectedCompanyId.value)) ?? null,
);

const isSelectedCompany = (company) => Number(company?.id) === Number(selectedCompanyId.value);

const pageTitle = computed(() =>
    selectedCompany.value
        ? `Gestão de ponto — ${selectedCompany.value.name}`
        : 'Gestão de ponto',
);

const syncHubQuery = (companyId, tab) => {
    try {
        const url = new URL(window.location.href);
        if (companyId) {
            url.searchParams.set('company', String(companyId));
        } else {
            url.searchParams.delete('company');
        }
        if (tab) {
            url.searchParams.set('tab', tab);
        } else {
            url.searchParams.delete('tab');
        }
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
    } catch {
        /* ignore */
    }
};

const isCompanyConfigured = (company) => Boolean(company?.rhid_configured);

const showBankTable = computed(
    () => companyMetrics.value?.status === 'ok' && !companyLoading.value && activeTab.value === 'banco',
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
    if (!selectedCompanyId.value || !isCompanyConfigured(selectedCompany.value)) {
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
    if (!selectedCompanyId.value || !isCompanyConfigured(selectedCompany.value)) {
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
    if (!selectedCompanyId.value || !isCompanyConfigured(selectedCompany.value)) {
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

watch(selectedCompanyId, async (companyId) => {
    syncHubQuery(companyId, activeTab.value);
    livePunches.value = [];
    peopleRows.value = [];
    await loadCompanyMetrics(false);
    if (isCompanyConfigured(selectedCompany.value) && activeTab.value === 'hoje') {
        await loadLivePunches();
    }
    if (isCompanyConfigured(selectedCompany.value) && activeTab.value === 'equipe') {
        await loadPeople();
    }
});

watch(activeTab, async (tab) => {
    syncHubQuery(selectedCompanyId.value, tab);
    if (!isCompanyConfigured(selectedCompany.value)) {
        return;
    }
    if (tab === 'hoje' && selectedCompanyId.value && !livePunches.value.length && !punchesLoading.value) {
        await loadLivePunches();
    }
    if (tab === 'equipe' && selectedCompanyId.value && !peopleRows.value.length && !peopleLoading.value) {
        await loadPeople();
    }
});

const refreshAll = async () => {
    if (!selectedCompanyId.value || isRefreshing.value) {
        return;
    }

    pageRefreshing.value = true;
    try {
        await Promise.all([
            loadCompanyMetrics(true),
            loadLivePunches(),
            loadPeople(),
            justificationsPanel.value?.refresh?.() ?? Promise.resolve(),
        ]);
    } finally {
        pageRefreshing.value = false;
    }
};

onMounted(async () => {
    syncHubQuery(selectedCompanyId.value, activeTab.value);
    await loadCompanyMetrics(false);
    if (isCompanyConfigured(selectedCompany.value) && selectedCompanyId.value) {
        await loadLivePunches();
    }
});
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Gestão de ponto</h2>
                    <p v-if="selectedCompany" class="mt-1 text-sm text-slate-600">
                        Empresa em visualização:
                        <span class="font-semibold text-talents-800">{{ selectedCompany.name }}</span>
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-600">
                        Selecione uma empresa à esquerda para ver pontualidade, faltas e atestados.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        v-if="selectedCompany"
                        :href="route('admin.ponto.companies.settings.edit', selectedCompany.id)"
                        class="text-sm font-medium text-talents-700 hover:underline"
                    >
                        Configuração RHID
                    </Link>
                    <PrimaryButton
                        type="button"
                        :disabled="isRefreshing || !selectedCompanyId"
                        @click="refreshAll"
                    >
                        {{ isRefreshing ? 'Atualizando…' : 'Atualizar' }}
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
                </div>

                <ul v-if="filteredCompanies.length" class="mt-4 max-h-[30rem] space-y-2 overflow-auto pr-1">
                    <li v-for="company in filteredCompanies" :key="company.id">
                        <button
                            type="button"
                            class="w-full rounded-xl border px-3 py-2.5 text-left transition"
                            :class="
                                isSelectedCompany(company)
                                    ? 'border-talents-700 bg-talents-700 text-white shadow-md ring-2 ring-talents-300'
                                    : 'border-slate-200 bg-white hover:border-talents-200 hover:bg-slate-50'
                            "
                            :aria-current="isSelectedCompany(company) ? 'true' : undefined"
                            @click="selectedCompanyId = company.id"
                        >
                            <p
                                class="truncate text-sm font-semibold"
                                :class="isSelectedCompany(company) ? 'text-white' : 'text-slate-800'"
                            >
                                {{ company.name }}
                            </p>
                            <p
                                class="mt-0.5 text-xs"
                                :class="isSelectedCompany(company) ? 'text-white/80' : 'text-slate-500'"
                            >
                                {{ company.segment || 'Sem segmento' }}
                            </p>
                            <p
                                v-if="!isCompanyConfigured(company)"
                                class="mt-1.5 text-[11px] font-semibold uppercase tracking-wide"
                                :class="isSelectedCompany(company) ? 'text-amber-100' : 'text-amber-800'"
                            >
                                Credenciais em falta
                            </p>
                            <p
                                v-else-if="isSelectedCompany(company)"
                                class="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-white/90"
                            >
                                Em visualização
                            </p>
                        </button>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    class="mt-4 border-0 bg-transparent"
                    title="Nenhuma empresa encontrada"
                    description="Só entram empresas ativas com o módulo RHID ligado ou com credenciais já gravadas. Configure em Configuração RHID."
                />
            </aside>

            <div class="space-y-5 xl:col-span-9">
                <div
                    v-if="selectedCompany"
                    class="overflow-hidden rounded-2xl bg-gradient-to-r from-talents-800 via-talents-700 to-talents-600 p-5 text-white shadow-md"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                                <BuildingOffice2Icon class="h-6 w-6" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">
                                    Empresa em visualização
                                </p>
                                <h3 class="mt-0.5 truncate text-2xl font-bold tracking-tight">
                                    {{ selectedCompany.name }}
                                </h3>
                                <p class="mt-1 text-sm text-white/80">
                                    {{ selectedCompany.segment || 'Sem segmento' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <Link
                                :href="route('admin.ponto.companies.settings.edit', selectedCompany.id)"
                                class="inline-flex items-center justify-center rounded-full bg-white px-4 py-2 text-sm font-semibold text-talents-800 transition hover:bg-white/90"
                            >
                                Configuração RHID
                            </Link>
                            <Link
                                :href="route('admin.companies.show', { company: selectedCompany.id, tab: 'ponto' })"
                                class="inline-flex items-center justify-center rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25"
                            >
                                Ver ficha da empresa
                            </Link>
                        </div>
                    </div>
                </div>

                <CompanyMetricsView
                    title="Indicadores da equipe"
                    description="Pontualidade, faltas não justificadas e atestados do mês, a partir dos espelhos importados."
                    :rhid-configured="isCompanyConfigured(selectedCompany)"
                    :loading="companyLoading"
                    :error="companyError"
                    :metrics="companyMetrics"
                    :loaded-at="companyLoadedAt"
                />

                <div v-if="selectedCompany" class="surface-card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2 sm:px-4">
                        <p class="min-w-0 truncate text-xs font-medium text-slate-500">
                            Operação de
                            <span class="font-semibold text-talents-800">{{ selectedCompany.name }}</span>
                        </p>
                    </div>
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
                        <div
                            v-if="!isCompanyConfigured(selectedCompany)"
                            class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        >
                            Configure as credenciais em
                            <Link
                                :href="route('admin.ponto.companies.settings.edit', selectedCompany.id)"
                                class="font-semibold underline"
                            >
                                Configuração RHID
                            </Link>
                            para ver indicadores, marcações e importação.
                        </div>

                        <div v-else-if="activeTab === 'hoje'" class="space-y-4">
                            <p class="text-sm text-slate-600">
                                Últimas marcações na RHID. Os indicadores da equipe estão no cartão acima.
                            </p>
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
                        </div>

                        <div v-else-if="activeTab === 'justificativas'">
                            <JustificationsCrudPanel ref="justificationsPanel" :company-id="selectedCompanyId" />
                        </div>

                        <div v-else-if="activeTab === 'equipe'" class="space-y-4">
                            <input
                                v-model="peopleSearch"
                                type="search"
                                placeholder="Buscar colaborador..."
                                class="w-full max-w-md rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
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
                            v-else-if="showBankTable"
                            class="!mt-0"
                            :metrics="companyMetrics"
                            :sections="['bank']"
                        />
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
