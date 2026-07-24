<script setup>
import EmptyState from '@/Components/Dashboard/EmptyState.vue';
import SectionHeader from '@/Components/Dashboard/SectionHeader.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import HealthBadge from '@/Components/Dashboard/HealthBadge.vue';
import {
    formatPortfolioBankAvg,
    operationalAlertClass,
    operationalAlertLabel,
} from '@/utils/rhidAdminMetrics';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

defineProps({
    companies: { type: Array, default: () => [] },
    segments: { type: Array, default: () => [] },
});

const selectedSegment = ref('');
const search = ref('');

const summaryLoading = ref(false);
const summaryError = ref(null);
const summaryData = ref(null);
const loadedAt = ref(null);

const summary = computed(() => summaryData.value?.summary ?? {});
const ranking = computed(() => summaryData.value?.ranking ?? []);
const bySegment = computed(() => summaryData.value?.by_segment ?? []);
const errors = computed(() => summaryData.value?.errors ?? []);
const partial = computed(() => Boolean(summaryData.value?.partial));

const portfolioCompanies = computed(() => {
    const rows = summaryData.value?.companies ?? [];
    const term = search.value.trim().toLowerCase();

    return rows
        .filter((row) => {
            if (!term) {
                return true;
            }
            return String(row.company_name ?? '')
                .toLowerCase()
                .includes(term);
        })
        .slice()
        .sort((a, b) => {
            const weight = (level) => {
                if (level === 'high') return 3;
                if (level === 'medium') return 2;
                return 1;
            };
            const byAlert = weight(b.operational_alert) - weight(a.operational_alert);
            if (byAlert !== 0) {
                return byAlert;
            }
            return String(a.company_name ?? '').localeCompare(String(b.company_name ?? ''), 'pt-BR');
        });
});

const pontoHref = (companyId) => `${route('admin.ponto.index')}?company=${companyId}`;

const loadSummary = async (refresh = false) => {
    summaryLoading.value = true;
    summaryError.value = null;

    try {
        const { data } = await axios.get(route('admin.rhid.summary'), {
            params: {
                ...(selectedSegment.value ? { segment: selectedSegment.value } : {}),
                ...(refresh ? { refresh: 1 } : {}),
            },
            timeout: 180000,
        });
        summaryData.value = data;
        loadedAt.value = new Date();
    } catch (e) {
        summaryError.value = e?.response?.data?.message || 'Não foi possível carregar o portfólio RHID.';
    } finally {
        summaryLoading.value = false;
    }
};

watch(selectedSegment, () => {
    loadSummary(false);
});

onMounted(() => {
    loadSummary(false);
});
</script>

<template>
    <Head title="Portfólio RHID" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Portfólio RHID</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-600">
                        Visão executiva das empresas com integração ativa: alertas, banco médio, NR-1 e priorização
                        para CS/consultoria. Para marcações, justificativas e colaboradores, use a Gestão de ponto.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.ponto.index')"
                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-talents-700 ring-1 ring-talents-200 transition hover:bg-talents-50"
                    >
                        Ir para Gestão de ponto
                    </Link>
                    <PrimaryButton type="button" :disabled="summaryLoading" @click="loadSummary(true)">
                        {{ summaryLoading ? 'Atualizando…' : 'Atualizar portfólio' }}
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <section class="dashboard-panel">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <SectionHeader
                    variant="panel"
                    title="Resumo do portfólio"
                    subtitle="Agregado das empresas ativas com credenciais RHID"
                />
                <p v-if="loadedAt" class="text-[11px] text-slate-500">
                    Atualizado
                    {{
                        loadedAt.toLocaleString('pt-BR', {
                            dateStyle: 'short',
                            timeStyle: 'medium',
                        })
                    }}
                </p>
            </div>

            <p
                v-if="summaryError"
                class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-100"
            >
                {{ summaryError }}
            </p>

            <div v-if="summaryLoading && !summaryData" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-xl bg-slate-100" />
            </div>

            <div v-else class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Empresas RHID ativas"
                    :value="summary.companies_rhid_configured ?? 0"
                    :hint="`${summary.companies_loaded ?? 0} carregadas nesta consulta`"
                />
                <StatCard
                    label="Banco médio do portfólio"
                    :value="formatPortfolioBankAvg(summary.portfolio_bank_avg_minutes)"
                    hint="Média das empresas com saldo numérico"
                />
                <StatCard
                    label="Alerta operacional alto"
                    :value="`${summary.high_alert_pct ?? 0}%`"
                    :hint="`${summary.high_alert_count ?? 0} empresa(s)`"
                />
                <StatCard
                    label="Risco duplo NR-1 + RHID"
                    :value="summary.dual_risk_count ?? 0"
                    hint="NR-1 crítico e alerta operacional alto"
                />
            </div>

            <p
                v-if="partial"
                class="mt-4 rounded-lg bg-amber-50 px-4 py-2 text-xs text-amber-900 ring-1 ring-amber-100"
            >
                Algumas empresas não puderam ser consultadas ({{ errors.length }} falha(s)). Os totais refletem apenas
                as empresas carregadas com sucesso.
            </p>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-2">
            <div class="surface-card p-5">
                <h3 class="text-sm font-semibold text-talents-800">Ranking — alerta operacional</h3>
                <p class="mt-0.5 text-xs text-slate-500">Top 10 empresas para priorização</p>
                <ul v-if="ranking.length" class="mt-4 divide-y divide-slate-100">
                    <li
                        v-for="row in ranking"
                        :key="row.company_id"
                        class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">{{ row.company_name }}</p>
                            <p v-if="row.segment" class="text-xs text-slate-500">{{ row.segment }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="row.dual_risk"
                                class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white"
                            >
                                Risco duplo
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
                                :class="operationalAlertClass(row.operational_alert)"
                            >
                                {{ operationalAlertLabel(row.operational_alert) }}
                            </span>
                            <Link
                                :href="pontoHref(row.company_id)"
                                class="text-xs font-semibold text-talents-700 hover:underline"
                            >
                                Abrir ponto
                            </Link>
                        </div>
                    </li>
                </ul>
                <EmptyState
                    v-else-if="!summaryLoading"
                    class="mt-4 border-0 bg-transparent"
                    title="Sem empresas no ranking"
                    description="Nenhuma empresa com RHID retornou métricas nesta consulta."
                />
            </div>

            <div class="surface-card p-5">
                <h3 class="text-sm font-semibold text-talents-800">Por segmento</h3>
                <p class="mt-0.5 text-xs text-slate-500">Empresas e alertas críticos por segmento</p>
                <ul v-if="bySegment.length" class="mt-4 space-y-2 text-sm">
                    <li
                        v-for="seg in bySegment"
                        :key="seg.segment"
                        class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-4 py-3"
                    >
                        <span class="font-medium text-slate-800">{{ seg.segment }}</span>
                        <span class="shrink-0 text-xs text-slate-600">
                            {{ seg.companies }} emp. · {{ seg.high_alert }} crítico(s)
                            <span v-if="seg.avg_bank_minutes != null" class="ml-1 text-slate-500">
                                · BH {{ formatPortfolioBankAvg(seg.avg_bank_minutes) }}
                            </span>
                        </span>
                    </li>
                </ul>
                <EmptyState
                    v-else-if="!summaryLoading"
                    class="mt-4 border-0 bg-transparent"
                    title="Sem segmentos"
                    description="Carregue métricas ou defina segmento nas empresas."
                />
            </div>
        </section>

        <section class="mt-8 surface-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-talents-800">Empresas do portfólio</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Situação agregada. Detalhe operacional (marcações, justificativas) na Gestão de ponto.
                    </p>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar empresa…"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500 sm:w-56"
                    >
                    <select
                        v-model="selectedSegment"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500 sm:w-48"
                    >
                        <option value="">Todos os segmentos</option>
                        <option v-for="segment in segments" :key="segment" :value="segment">
                            {{ segment }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Empresa</th>
                            <th class="px-4 py-3">Alerta</th>
                            <th class="px-4 py-3">Banco médio</th>
                            <th class="px-4 py-3">NR-1</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in portfolioCompanies" :key="row.company_id" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ row.company_name }}</p>
                                <p class="text-xs text-slate-500">{{ row.segment || 'Sem segmento' }}</p>
                                <p v-if="row.status === 'error'" class="mt-1 text-xs text-rose-700">
                                    {{ row.error || 'Falha na consulta' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="row.status === 'ok'">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
                                        :class="operationalAlertClass(row.operational_alert)"
                                    >
                                        {{ operationalAlertLabel(row.operational_alert) }}
                                    </span>
                                    <span
                                        v-if="row.dual_risk"
                                        class="ml-2 inline-flex rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white"
                                    >
                                        Duplo
                                    </span>
                                </template>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                {{
                                    row.status === 'ok'
                                        ? formatPortfolioBankAvg(row.bank?.avg_minutes)
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <HealthBadge
                                    v-if="row.status === 'ok' && row.nr1?.risk_level"
                                    :risk-level="row.nr1.risk_level"
                                />
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="pontoHref(row.company_id)"
                                    class="font-medium text-talents-700 hover:underline"
                                >
                                    Abrir ponto
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!portfolioCompanies.length && !summaryLoading">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                Nenhuma empresa no portfólio para os filtros atuais.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="errors.length" class="mt-6 surface-card p-5">
            <h3 class="text-sm font-semibold text-rose-800">Falhas nesta consulta</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li v-for="err in errors" :key="err.company_id">
                    <span class="font-medium">{{ err.company_name }}:</span>
                    {{ err.message }}
                </li>
            </ul>
        </section>
    </AdminLayout>
</template>
