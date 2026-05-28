<script setup>
import CompanyMetricsView from '@/Components/Admin/Rhid/CompanyMetricsView.vue';
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatPortfolioBankAvg } from '@/utils/rhidAdminMetrics';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    companies: { type: Array, default: () => [] },
    segments: { type: Array, default: () => [] },
});

const selectedCompanyId = ref(props.companies[0]?.id ?? null);
const search = ref('');
const selectedSegment = ref('');

const summaryLoading = ref(false);
const summaryError = ref(null);
const summaryData = ref(null);

const companyLoading = ref(false);
const companyError = ref(null);
const companyMetrics = ref(null);
const companyLoadedAt = ref(null);

const filteredCompanies = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.companies.filter((company) => {
        const bySegment = !selectedSegment.value || company.segment === selectedSegment.value;
        const byTerm = !term || company.name?.toLowerCase().includes(term);
        return bySegment && byTerm;
    });
});

const selectedCompany = computed(() =>
    props.companies.find((company) => Number(company.id) === Number(selectedCompanyId.value)) ?? null,
);

const summary = computed(() => summaryData.value?.summary ?? {});

const loadSummary = async (refresh = false) => {
    summaryLoading.value = true;
    summaryError.value = null;

    try {
        const { data } = await axios.get(route('admin.rhid.summary'), {
            params: {
                ...(selectedSegment.value ? { segment: selectedSegment.value } : {}),
                ...(refresh ? { refresh: 1 } : {}),
            },
        });
        summaryData.value = data;
    } catch (e) {
        summaryError.value = e?.response?.data?.message || 'Nao foi possivel carregar o resumo RHID.';
    } finally {
        summaryLoading.value = false;
    }
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
        const { data } = await axios.get(route('admin.rhid.companies.metrics', selectedCompanyId.value), {
            params: refresh ? { refresh: 1 } : {},
            timeout: 120000,
        });
        companyMetrics.value = data;
        companyLoadedAt.value = new Date();

        if (data?.status === 'error') {
            companyError.value = data.error || 'Falha ao carregar indicadores da empresa.';
        } else if (data?.status === 'not_configured') {
            companyError.value = data.message || 'Integracao RHID nao configurada para esta empresa.';
        }
    } catch (e) {
        companyMetrics.value = null;
        companyError.value = e?.response?.data?.message || 'Falha ao carregar indicadores da empresa.';
    } finally {
        companyLoading.value = false;
    }
};

watch(selectedCompanyId, () => {
    loadCompanyMetrics(false);
});

watch(selectedSegment, async () => {
    if (
        selectedCompanyId.value &&
        !filteredCompanies.value.some((company) => company.id === selectedCompanyId.value)
    ) {
        selectedCompanyId.value = filteredCompanies.value[0]?.id ?? null;
    }
    await loadSummary(false);
});

onMounted(async () => {
    await loadSummary(false);
    await loadCompanyMetrics(false);
});
</script>

<template>
    <Head title="RHID" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">RHID - Empresas integradas</h2>
                <PrimaryButton type="button" :disabled="summaryLoading || companyLoading" @click="loadSummary(true); loadCompanyMetrics(true)">
                    Atualizar indicadores
                </PrimaryButton>
            </div>
        </template>

        <section class="dashboard-panel">
            <SectionHeader
                variant="panel"
                title="Resumo do portfólio RHID"
                subtitle="Empresas ativas com credenciais RHID configuradas"
            />

            <p
                v-if="summaryError"
                class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-100"
            >
                {{ summaryError }}
            </p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Empresas RHID ativas"
                    :value="summary.companies_rhid_configured ?? 0"
                    :hint="`${summary.companies_loaded ?? 0} carregadas`"
                />
                <StatCard
                    label="Banco medio do portfolio"
                    :value="formatPortfolioBankAvg(summary.portfolio_bank_avg_minutes)"
                    hint="Media das empresas com saldo numerico"
                />
                <StatCard
                    label="Alerta operacional alto"
                    :value="`${summary.high_alert_pct ?? 0}%`"
                    :hint="`${summary.high_alert_count ?? 0} empresa(s)`"
                />
                <StatCard
                    label="Risco duplo NR-1 + RHID"
                    :value="summary.dual_risk_count ?? 0"
                    hint="NR-1 critico e alerta operacional alto"
                />
            </div>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-12">
            <aside class="surface-card p-5 lg:col-span-4">
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
                            :class="company.id === selectedCompanyId
                                ? 'border-talents-300 bg-talents-50'
                                : 'border-slate-200 bg-white hover:border-talents-200 hover:bg-slate-50'"
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
                    description="Ajuste os filtros para localizar empresas com RHID."
                />
            </aside>

            <div class="lg:col-span-8">
                <CompanyMetricsView
                    :title="selectedCompany ? `Indicadores RHID - ${selectedCompany.name}` : 'Indicadores RHID'"
                    :rhid-configured="Boolean(selectedCompany)"
                    :loading="companyLoading"
                    :error="companyError"
                    :metrics="companyMetrics"
                    :loaded-at="companyLoadedAt"
                    :show-refresh="Boolean(selectedCompany)"
                    refresh-label="Atualizar empresa"
                    @refresh="loadCompanyMetrics(true)"
                />
            </div>
        </section>
    </AdminLayout>
</template>
