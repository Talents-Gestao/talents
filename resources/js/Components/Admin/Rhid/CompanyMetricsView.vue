<script setup>
import HealthBadge from '@/Components/Dashboard/HealthBadge.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RhidOverviewKpiCards from '@/Components/Rhid/RhidOverviewKpiCards.vue';
import { metricsToKpiProps, operationalAlertClass, operationalAlertLabel } from '@/utils/rhidAdminMetrics';
import { formatRhidBankBalanceMinutes } from '@/utils/rhidDate';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: 'Indicadores RHID (mês atual)' },
    description: {
        type: String,
        default: 'Banco de horas do dia anterior (referencia consolidada), aderencia e justificativas no mes civil corrente.',
    },
    rhidConfigured: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
    metrics: { type: Object, default: null },
    loadedAt: { type: Date, default: null },
    refreshLabel: { type: String, default: 'Atualizar' },
    showRefresh: { type: Boolean, default: false },
});

const emit = defineEmits(['refresh']);

const kpiProps = computed(() => metricsToKpiProps(props.metrics));

const bankHoursLabel = computed(() => {
    const label = props.metrics?.bank?.reference_date_label;
    return label ? `Banco de horas (${label})` : 'Banco de horas (dia anterior)';
});

const periodHint = computed(() => {
    const ref = props.metrics?.bank?.reference_date_label;
    if (!ref) {
        return props.description;
    }
    return `Referencia de banco de horas: ${ref} (dia anterior ao consultado). Aderencia e justificativas: mes civil corrente.`;
});

const apiErrorMessage = computed(() => {
    if (props.metrics?.status === 'error') {
        return props.metrics.error || 'Falha ao consultar indicadores RHID.';
    }
    if (props.metrics?.status === 'not_configured') {
        return props.metrics.message || 'Integracao RHID nao configurada para esta empresa.';
    }
    return null;
});

const showMetricsContent = computed(() => props.metrics?.status === 'ok');

const showLoadingSkeleton = computed(() => props.loading && !props.metrics);
</script>

<template>
    <div class="surface-card p-6 text-slate-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="font-semibold text-talents-700">{{ title }}</h3>
                <p class="mt-1 text-xs text-slate-500">
                    {{ periodHint }}
                </p>
            </div>
            <PrimaryButton
                v-if="showRefresh && rhidConfigured"
                type="button"
                :disabled="loading"
                class="shrink-0"
                @click="emit('refresh')"
            >
                {{ loading ? 'Atualizando...' : refreshLabel }}
            </PrimaryButton>
        </div>

        <p v-if="!rhidConfigured" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-100">
            Integração RHID não configurada. O cliente deve informar credenciais em RHID / Ponto no portal.
        </p>

        <p
            v-else-if="error || apiErrorMessage"
            class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-100"
        >
            {{ error || apiErrorMessage }}
        </p>

        <div
            v-else-if="showLoadingSkeleton"
            class="mt-5"
        >
            <RhidOverviewKpiCards
                :loading="true"
                :interactive="false"
                :format-rhid-bank-balance-minutes="formatRhidBankBalanceMinutes"
            />
            <p class="mt-3 text-xs text-slate-500">
                Consultando API RHID. Isso pode levar alguns instantes...
            </p>
        </div>

        <template v-else-if="rhidConfigured && showMetricsContent">
            <p
                v-if="loading"
                class="mt-4 text-xs font-medium text-talents-700"
            >
                Atualizando indicadores...
            </p>

            <div
                class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm"
            >
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium uppercase text-slate-500">Alerta operacional</span>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
                        :class="operationalAlertClass(metrics.operational_alert)"
                    >
                        {{ operationalAlertLabel(metrics.operational_alert) }}
                    </span>
                </div>
                <div v-if="metrics.nr1?.risk_level" class="flex items-center gap-2">
                    <span class="text-xs font-medium uppercase text-slate-500">NR-1 (ultima campanha)</span>
                    <HealthBadge :risk-level="metrics.nr1.risk_level" />
                    <span v-if="metrics.nr1.average_score != null" class="text-xs tabular-nums text-slate-600">
                        {{ Number(metrics.nr1.average_score).toFixed(1) }}
                    </span>
                </div>
                <span
                    v-if="metrics.dual_risk"
                    class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white"
                >
                    Risco duplo
                </span>
                <span v-if="!metrics.integration?.ok" class="text-xs text-amber-800">
                    Ultima falha API: {{ metrics.integration?.last_error || 'verifique credenciais' }}
                </span>
            </div>

            <p v-if="metrics?.adherence?.diagnostics_hint" class="mt-3 text-xs text-amber-800">
                {{ metrics.adherence.diagnostics_hint }}
            </p>

            <div v-if="kpiProps" class="mt-5">
                <RhidOverviewKpiCards
                    :loading="false"
                    :interactive="false"
                    v-bind="kpiProps"
                    :bank-hours-label="bankHoursLabel"
                    :format-rhid-bank-balance-minutes="formatRhidBankBalanceMinutes"
                />
            </div>

            <p v-if="loadedAt" class="mt-4 text-[11px] text-slate-400">
                Atualizado
                {{
                    loadedAt.toLocaleString('pt-BR', {
                        dateStyle: 'short',
                        timeStyle: 'medium',
                    })
                }}
            </p>
        </template>

        <p
            v-else-if="rhidConfigured"
            class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-100"
        >
            Nenhum indicador carregado. Clique em atualizar para consultar a API RHID.
        </p>
    </div>
</template>
