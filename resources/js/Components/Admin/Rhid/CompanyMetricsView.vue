<script setup>
import InfoTooltip from '@/Components/InfoTooltip.vue';
import PontoPeopleSnapshot from '@/Components/Rhid/PontoPeopleSnapshot.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: 'Gestão de ponto' },
    description: {
        type: String,
        default: 'Pontualidade, faltas não justificadas e atestados do mês, a partir dos espelhos importados.',
    },
    rhidConfigured: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    importing: { type: Boolean, default: false },
    importMessage: { type: String, default: null },
    error: { type: String, default: null },
    metrics: { type: Object, default: null },
    loadedAt: { type: Date, default: null },
    refreshLabel: { type: String, default: 'Atualizar' },
    showRefresh: { type: Boolean, default: false },
    showMonthPicker: { type: Boolean, default: false },
    monthValue: { type: String, default: '' },
    monthMax: { type: String, default: '' },
});

const emit = defineEmits(['refresh', 'month-change']);

const periodHint = computed(() => {
    const label = props.metrics?.period?.month_label;
    if (label) {
        return `Mês de referência: ${label}. Só entram dias já fechados (até ontem). Feriados, atestados e férias saem do denominador de pontualidade. Ao trocar o mês sem espelhos, a importação começa sozinha.`;
    }
    return props.description;
});

const apiErrorMessage = computed(() => {
    if (props.metrics?.status === 'error') {
        return props.metrics.error || 'Falha ao consultar indicadores de ponto.';
    }
    if (props.metrics?.status === 'not_configured') {
        return props.metrics.message || 'Integração RHID não configurada para esta empresa.';
    }
    return null;
});

const showMetricsContent = computed(() => props.metrics?.status === 'ok');

const showLoadingSkeleton = computed(() => props.loading && !props.metrics);

const onMonthChange = (event) => {
    emit('month-change', event?.target?.value);
};
</script>

<template>
    <div class="surface-card p-6 text-slate-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="flex items-center gap-1.5 font-semibold text-talents-700">
                    {{ title }}
                    <InfoTooltip text="Pontualidade média, faltas sem justificativa e atestados do mês, calculados só com os dias de espelho já importados (até o dia fechado)." />
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    {{ periodHint }}
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <label
                    v-if="showMonthPicker"
                    class="inline-flex items-center gap-2 text-xs font-medium text-slate-600"
                >
                    <span class="sr-only">Mês de referência</span>
                    <input
                        type="month"
                        class="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs font-medium text-slate-700 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-1 focus:ring-talents-400"
                        :value="monthValue"
                        :max="monthMax"
                        :disabled="loading || importing"
                        @change="onMonthChange"
                    >
                </label>
                <PrimaryButton
                    v-if="showRefresh && rhidConfigured"
                    type="button"
                    :disabled="loading || importing"
                    class="shrink-0"
                    @click="emit('refresh')"
                >
                    {{ loading ? 'Atualizando...' : refreshLabel }}
                </PrimaryButton>
            </div>
        </div>

        <p v-if="!rhidConfigured" class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-100">
            Integração RHID sem credenciais. Cadastre e-mail e senha na ficha da empresa (aba Gestão de ponto) ou no portal do cliente.
        </p>

        <p
            v-else-if="error || apiErrorMessage"
            class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-100"
        >
            {{ error || apiErrorMessage }}
        </p>

        <div v-else-if="showLoadingSkeleton" class="mt-5">
            <PontoPeopleSnapshot :loading="true" :people="null" />
            <p class="mt-3 text-xs text-slate-500">
                Consultando espelhos e justificativas. Isso pode levar alguns instantes...
            </p>
        </div>

        <template v-else-if="rhidConfigured && showMetricsContent">
            <span
                v-if="metrics && !metrics.integration?.ok"
                class="mt-3 inline-block text-xs text-amber-800"
            >
                Última falha API: {{ metrics.integration?.last_error || 'verifique credenciais' }}
            </span>

            <div class="mt-5">
                <PontoPeopleSnapshot
                    :people="metrics.people"
                    :loading="loading"
                    :month-label="metrics.period?.month_label || ''"
                    :diagnostics-hint="metrics?.diagnostics?.hint || metrics?.adherence?.diagnostics_hint || ''"
                    :detail-url="
                        metrics?.company_id
                            ? route('admin.ponto.companies.monthly-compliance.detail', metrics.company_id)
                            : null
                    "
                    :period-ini="metrics?.period?.month_ini || metrics?.people?.resumo?.ini || ''"
                    :period-fim="metrics?.period?.month_fim || metrics?.people?.resumo?.fim || ''"
                    :importing-month="importing"
                    :import-status-message="importMessage || ''"
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
            Nenhum indicador carregado. Clique em atualizar para consultar os espelhos.
        </p>
    </div>
</template>
