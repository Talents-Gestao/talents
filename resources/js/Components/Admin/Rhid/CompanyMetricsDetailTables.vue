<script setup>
import { formatRhidDotNetDate, formatRhidDotNetDateRange } from '@/utils/rhidDate';
import { computed, ref } from 'vue';

const props = defineProps({
    metrics: { type: Object, required: true },
});

const bankSearch = ref('');
const justSearch = ref('');

const bankRows = computed(() => props.metrics?.bank?.rows ?? []);

const filteredBankRows = computed(() => {
    const term = bankSearch.value.trim().toLowerCase();
    if (!term) {
        return bankRows.value;
    }
    return bankRows.value.filter((row) =>
        [row.name, row.department, row.role, row.registration]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(term)),
    );
});

const justificationItems = computed(() => props.metrics?.justifications?.items ?? []);

const filteredJustifications = computed(() => {
    const term = justSearch.value.trim().toLowerCase();
    if (!term) {
        return justificationItems.value;
    }
    return justificationItems.value.filter((row) => {
        const period = formatRhidDotNetDateRange(row.start, row.end);
        return [row.person_name, row.type, row.description, period]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(term));
    });
});

const formatMinutes = (minutes) => {
    if (minutes == null || Number.isNaN(Number(minutes))) {
        return '—';
    }
    const sign = minutes < 0 ? '-' : '';
    const abs = Math.abs(Math.round(Number(minutes)));
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    return `${sign}${h}:${String(m).padStart(2, '0')}`;
};

const formatPunchDateTime = (val) => formatRhidDotNetDate(val, { withTime: true }) || '—';
</script>

<template>
    <div class="mt-8 space-y-8">
        <section class="surface-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4">
                <h4 class="text-sm font-semibold text-talents-800">
                    Banco de horas por colaborador
                </h4>
                <p class="mt-1 text-xs text-slate-500">
                    Referencia: {{ metrics.bank?.reference_date_label || 'dia anterior' }}
                    <span v-if="metrics.bank?.mom_date_label">
                        · comparacao MoM: {{ metrics.bank.mom_date_label }}
                    </span>
                    · {{ bankRows.length }} colaborador(es) com saldo numerico
                </p>
                <input
                    v-model="bankSearch"
                    type="search"
                    placeholder="Buscar por nome, departamento ou cargo..."
                    class="mt-3 w-full max-w-md rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
            </div>
            <div class="max-h-[28rem] overflow-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Colaborador</th>
                            <th class="px-4 py-3">Departamento</th>
                            <th class="px-4 py-3">Cargo</th>
                            <th class="px-4 py-3">Matricula</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="(row, idx) in filteredBankRows" :key="`${row.name}-${idx}`" class="hover:bg-slate-50/80">
                            <td class="px-4 py-2.5 font-medium text-slate-800">{{ row.name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ row.department || '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ row.role || '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ row.registration || '—' }}</td>
                            <td
                                class="px-4 py-2.5 text-right font-mono font-semibold"
                                :class="row.minutes < 0 ? 'text-rose-700' : 'text-slate-800'"
                            >
                                {{ row.balance_display }}
                            </td>
                        </tr>
                        <tr v-if="!filteredBankRows.length">
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                Nenhum colaborador encontrado para os filtros atuais.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="surface-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h4 class="text-sm font-semibold text-talents-800">Maiores atrasos na entrada</h4>
                    <p class="mt-1 text-xs text-slate-500">Mes civil corrente · aderencia ao horario</p>
                </div>
                <div class="max-h-80 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Colaborador</th>
                                <th class="px-4 py-2 text-right">Total atraso</th>
                                <th class="px-4 py-2 text-right">Maior atraso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, idx) in metrics.adherence?.ranking_atrasos_entrada ?? []"
                                :key="`atraso-${idx}`"
                                class="border-t border-slate-50"
                            >
                                <td class="px-4 py-2 font-medium text-slate-800">{{ row.nome }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMinutes(row.total_atraso_entrada_minutos) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMinutes(row.maior_atraso_entrada_minutos) }}</td>
                            </tr>
                            <tr v-if="!(metrics.adherence?.ranking_atrasos_entrada ?? []).length">
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">Sem dados no periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="surface-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h4 class="text-sm font-semibold text-talents-800">Infracoes de almoco</h4>
                </div>
                <div class="max-h-80 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Colaborador</th>
                                <th class="px-4 py-2 text-right">Dias c/ infracao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, idx) in metrics.adherence?.ranking_infracoes_almoco ?? []"
                                :key="`almoco-${idx}`"
                                class="border-t border-slate-50"
                            >
                                <td class="px-4 py-2 font-medium text-slate-800">{{ row.nome }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ row.dias_com_infracao_almoco ?? 0 }}</td>
                            </tr>
                            <tr v-if="!(metrics.adherence?.ranking_infracoes_almoco ?? []).length">
                                <td colspan="2" class="px-4 py-6 text-center text-slate-500">Sem dados no periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="surface-card overflow-hidden xl:col-span-2">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h4 class="text-sm font-semibold text-talents-800">Pior aderencia de marcacoes</h4>
                </div>
                <div class="max-h-80 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Colaborador</th>
                                <th class="px-4 py-2 text-right">Penalidade total</th>
                                <th class="px-4 py-2 text-right">Dias c/ infracao almoco</th>
                                <th class="px-4 py-2 text-right">Dias analisados</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, idx) in metrics.adherence?.ranking_pior_aderencia ?? []"
                                :key="`aderencia-${idx}`"
                                class="border-t border-slate-50"
                            >
                                <td class="px-4 py-2 font-medium text-slate-800">{{ row.nome }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ formatMinutes(row.total_minutos_penalidade) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ row.dias_com_infracao_almoco ?? 0 }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ row.dias_analisados ?? 0 }}</td>
                            </tr>
                            <tr v-if="!(metrics.adherence?.ranking_pior_aderencia ?? []).length">
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">Sem dados no periodo.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="surface-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h4 class="text-sm font-semibold text-talents-800">Justificativas (amostra)</h4>
                    <p class="mt-1 text-xs text-slate-500">
                        Primeira pagina da API · {{ justificationItems.length }} registro(s) exibidos
                    </p>
                    <input
                        v-model="justSearch"
                        type="search"
                        placeholder="Buscar justificativa..."
                        class="mt-3 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                </div>
                <div class="max-h-80 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Colaborador</th>
                                <th class="px-4 py-2">Tipo</th>
                                <th class="px-4 py-2">Periodo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, idx) in filteredJustifications"
                                :key="`just-${idx}`"
                                class="border-t border-slate-50"
                            >
                                <td class="px-4 py-2">
                                    <p class="font-medium text-slate-800">{{ row.person_name }}</p>
                                    <p v-if="row.is_atestado" class="text-[10px] font-semibold uppercase text-rose-600">Atestado</p>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ row.type }}</td>
                                <td class="px-4 py-2 text-xs text-slate-600">
                                    {{ formatRhidDotNetDateRange(row.start, row.end) }}
                                </td>
                            </tr>
                            <tr v-if="!filteredJustifications.length">
                                <td colspan="3" class="px-4 py-6 text-center text-slate-500">Sem justificativas na amostra.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="surface-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h4 class="text-sm font-semibold text-talents-800">Ultimas marcacoes RHID</h4>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ metrics.punches?.count ?? 0 }} registro(s) · {{ metrics.punches?.distinct_collaborators ?? 0 }} colaborador(es)
                    </p>
                </div>
                <div class="max-h-80 overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Colaborador</th>
                                <th class="px-4 py-2">Data/hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, idx) in metrics.punches?.items ?? []"
                                :key="`punch-${idx}`"
                                class="border-t border-slate-50"
                            >
                                <td class="px-4 py-2 font-medium text-slate-800">{{ row.name }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ formatPunchDateTime(row.datetime) }}</td>
                            </tr>
                            <tr v-if="!(metrics.punches?.items ?? []).length">
                                <td colspan="2" class="px-4 py-6 text-center text-slate-500">Sem marcacoes retornadas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
