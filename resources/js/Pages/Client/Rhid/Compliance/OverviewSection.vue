<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    overviewLoading: { type: Boolean, required: true },
    overviewLoadedAt: { type: Object, default: null },
    overviewCalendarRangeLabel: { type: String, required: true },
    overviewPunchRowsLength: { type: Number, required: true },
    overviewPunchDistinct: { type: Number, required: true },
    overviewPunchPreviewRows: {
        type: Array,
        required: true,
    },
    overviewBankNumericRowsLength: { type: Number, required: true },
    overviewBankNegativeCount: { type: Number, required: true },
    overviewBankAvgMinutes: { type: [Number, null], default: null },
    overviewBankWorstThree: { type: Array, required: true },
    overviewAdherence: { type: [Object, null], default: null },
    overviewAdherenceWorstEntrada: { type: Array, required: true },
    overviewJustTotal: { type: [Number, null], default: null },
    overviewJustAtestados: { type: [Number, null], default: null },
    overviewJustNote: { type: String, default: '' },
    isAdmin: { type: Boolean, required: true },
    formatRhidBankBalanceMinutes: { type: Function, required: true },
    bankDisplayName: { type: Function, required: true },
    bankDisplayValue: { type: Function, required: true },
    rhidPersonId: { type: Function, required: true },
});

const emit = defineEmits([
    'refresh',
    'go-punches-dashboard',
    'go-punches-adherence',
    'go-bank',
    'go-justifications',
    'go-espelho',
    'go-collaborators',
]);
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-slate-600">
            Indicadores rápidos alinhados ao <span class="font-medium text-slate-800">mês corrente</span>
            ({{ overviewCalendarRangeLabel }}) para aderência e justificativas; banco de horas na
            <span class="font-medium text-slate-800">data de hoje</span>; marcações pela última leitura do RHID.
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <PrimaryButton type="button" :disabled="overviewLoading" @click="emit('refresh')">
                Atualizar visão geral
            </PrimaryButton>
            <p v-if="overviewLoadedAt" class="text-xs text-slate-500">
                Atualizado em
                {{
                    overviewLoadedAt.toLocaleString('pt-BR', {
                        dateStyle: 'short',
                        timeStyle: 'medium',
                    })
                }}
            </p>
        </div>
        <p v-if="overviewLoading" class="text-sm text-slate-500">Carregando indicadores…</p>
        <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase text-slate-500">Últimas marcações (RHID)</p>
                <p class="mt-1 text-xs text-slate-500">
                    Amostra de {{ overviewPunchRowsLength }} registro(s) · {{ overviewPunchDistinct }} colaborador(es)
                    distinto(s)
                </p>
                <ul v-if="overviewPunchPreviewRows.length" class="mt-3 space-y-2 border-t border-slate-100 pt-3 text-sm">
                    <li
                        v-for="(pr, pi) in overviewPunchPreviewRows"
                        :key="pi"
                        class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5"
                    >
                        <span class="min-w-0 font-medium text-slate-800">
                            <Link
                                v-if="pr.personId != null"
                                :href="route('client.rhid.collaborators.show', pr.personId)"
                                class="text-talents-800 hover:underline"
                            >
                                {{ pr.nome }}
                            </Link>
                            <span v-else>{{ pr.nome }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums text-xs text-slate-600">{{ pr.dataDisplay }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Nenhuma marcação na amostra.</p>
                <PrimaryButton type="button" class="mt-3" @click="emit('go-punches-dashboard')">
                    Ver painel de marcações
                </PrimaryButton>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase text-slate-500">Banco de horas (hoje)</p>
                <p v-if="overviewBankNumericRowsLength" class="mt-1 text-2xl font-semibold text-slate-900">
                    {{ formatRhidBankBalanceMinutes(overviewBankAvgMinutes ?? 0) }}
                </p>
                <p v-else class="mt-1 text-sm text-slate-500">Sem saldos numéricos na consulta rápida.</p>
                <p class="mt-1 text-xs text-slate-500">
                    Saldo médio entre {{ overviewBankNumericRowsLength }} colaborador(es) com valor numérico
                </p>
                <p
                    v-if="overviewBankNegativeCount > 0"
                    class="mt-2 text-xs font-medium text-rose-800"
                >
                    {{ overviewBankNegativeCount }} com saldo negativo — vale rever na aba Banco de horas
                </p>
                <div v-if="overviewBankWorstThree.length" class="mt-2 text-sm text-slate-700">
                    <p class="text-xs font-medium text-rose-800">Piores saldos (top 3)</p>
                    <ul class="mt-1 list-inside list-disc">
                        <li v-for="(row, wi) in overviewBankWorstThree" :key="wi">
                            <Link
                                v-if="rhidPersonId(row) != null"
                                :href="route('client.rhid.collaborators.show', rhidPersonId(row))"
                                class="text-talents-800 hover:underline"
                            >
                                {{ bankDisplayName(row) }}
                            </Link>
                            <span v-else>{{ bankDisplayName(row) }}</span>
                            — {{ bankDisplayValue(row) }}
                        </li>
                    </ul>
                </div>
                <PrimaryButton type="button" class="mt-3" @click="emit('go-bank')">Abrir banco de horas</PrimaryButton>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase text-slate-500">Aderência (mês corrente)</p>
                <p class="mt-1 text-xs text-slate-500">Período: {{ overviewCalendarRangeLabel }}</p>
                <p v-if="overviewAdherence?.resumo" class="mt-2 text-sm text-slate-700">
                    {{ overviewAdherence.resumo.dias_registro_analisados }} dia(s) de registro analisados ·
                    {{ overviewAdherence.resumo.colaboradores_com_dados ?? '—' }} colaborador(es) com dados
                </p>
                <p v-else class="mt-2 text-sm text-slate-500">
                    Sem agregado para este período — importe espelhos e analise na sub-aba Aderência.
                </p>
                <ul v-if="overviewAdherenceWorstEntrada.length" class="mt-2 space-y-1 text-sm text-slate-700">
                    <li v-for="(rw, ri) in overviewAdherenceWorstEntrada" :key="ri" class="flex flex-wrap gap-x-1">
                        <Link
                            v-if="rw.id_person != null"
                            :href="route('client.rhid.collaborators.show', rw.id_person)"
                            class="font-medium text-talents-800 hover:underline"
                        >
                            {{ rw.nome }}
                        </Link>
                        <span v-else class="font-medium">{{ rw.nome }}</span>
                        <span class="text-slate-600">
                            — {{ rw.total_atraso_entrada_minutos ?? 0 }} min (atraso na entrada)
                        </span>
                    </li>
                </ul>
                <PrimaryButton type="button" class="mt-3" @click="emit('go-punches-adherence')">
                    Ver aderência
                </PrimaryButton>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase text-slate-500">Justificativas (mês corrente)</p>
                <p class="mt-1 text-xs text-slate-500">Período: {{ overviewCalendarRangeLabel }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ overviewJustTotal != null ? overviewJustTotal : '—' }}
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    Atestados na primeira página carregada:
                    {{ overviewJustAtestados != null ? overviewJustAtestados : '—' }}
                </p>
                <p v-if="overviewJustNote" class="mt-1 text-xs text-amber-800">{{ overviewJustNote }}</p>
                <PrimaryButton type="button" class="mt-3" @click="emit('go-justifications')">
                    Ver justificativas
                </PrimaryButton>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:col-span-2 xl:col-span-3">
                <p class="text-sm font-medium text-slate-800">Atalhos</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <SecondaryButton type="button" @click="emit('go-espelho')">Importar espelho / PDF</SecondaryButton>
                    <SecondaryButton type="button" @click="emit('go-collaborators')">Lista de colaboradores</SecondaryButton>
                    <Link
                        v-if="isAdmin"
                        :href="route('client.rhid.settings.edit')"
                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Configurar horários da empresa
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
