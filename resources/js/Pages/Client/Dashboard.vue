<script setup>
import DashboardBootstrapCalendar from '@/Components/DashboardBootstrapCalendar.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

import '../../../css/dashboard-calendar-bootstrap.css';

const copied = ref(false);

const copyDenuncia = async (url) => {
    if (!url || !navigator.clipboard) return;
    await navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

defineProps({
    activeSurveys: Number,
    lastSurvey: Object,
    overallRisk: Object,
    complaintsPublicUrl: { type: String, default: null },
    dashboardCalendar: { type: Object, default: null },
});

const healthLevelLabel = (level) => {
    if (level === 'green') return 'Saudável';
    if (level === 'yellow') return 'Atenção';
    return 'Crítico';
};
</script>

<template>
    <Head title="Painel" />

    <ClientLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-talents-900">Painel NR-1</h2>
        </template>

        <div v-if="dashboardCalendar" class="mb-10">
            <DashboardBootstrapCalendar
                :items="dashboardCalendar.items"
                :year="dashboardCalendar.year"
                :month="dashboardCalendar.month"
                :kind-labels="dashboardCalendar.kindLabels"
                title="Calendário estratégico"
                subtitle="Suas datas e orientações do mês"
                :full-page-href="route('client.strategic-calendar.index')"
                full-page-label="Ver detalhes"
                dashboard-route="client.dashboard"
            />
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <div class="rounded-xl border border-talents-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-500">Pesquisas ativas</p>
                <p class="mt-2 text-3xl font-bold text-talents-800">{{ activeSurveys }}</p>
            </div>
            <div class="rounded-xl border border-talents-200 bg-white p-6 shadow-sm sm:col-span-2">
                <p class="text-sm text-slate-500">Última campanha</p>
                <p v-if="lastSurvey" class="mt-2 text-lg font-semibold text-talents-900">{{ lastSurvey.title }}</p>
                <p v-else class="mt-2 text-slate-500">Nenhuma campanha ainda.</p>
                <div v-if="overallRisk" class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-talents-100 px-3 py-1 text-sm font-medium text-talents-900">
                        Média de saúde (0–100): {{ Number(overallRisk.average_score).toFixed(1) }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ healthLevelLabel(overallRisk.risk_level) }}</span>
                    <Link
                        v-if="lastSurvey"
                        :href="route('client.surveys.results', lastSurvey.id)"
                        class="text-sm font-semibold text-talents-700 hover:underline"
                    >
                        Ver resultados
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="complaintsPublicUrl" class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-talents-900">Link público — Canal de denúncias</h3>
            <p class="mt-1 text-xs text-gray-600">Compartilhe com colaboradores (Lei 14.457/2022). Acesso sigiloso com protocolo.</p>
            <p class="mt-3 break-all rounded-md bg-gray-50 p-2 font-mono text-xs text-gray-800">{{ complaintsPublicUrl }}</p>
            <button
                type="button"
                class="mt-3 rounded-md border border-talents-300 px-3 py-1.5 text-sm font-medium text-talents-800 hover:bg-talents-50"
                @click="copyDenuncia(complaintsPublicUrl)"
            >
                {{ copied ? 'Copiado!' : 'Copiar link' }}
            </button>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <Link
                v-if="$page.props.auth.user?.company?.has_strategic_calendar"
                :href="route('client.strategic-calendar.index')"
                class="inline-flex rounded-md border border-talents-300 bg-white px-4 py-2 text-sm font-semibold text-talents-800 hover:bg-talents-50"
            >
                Calendário estratégico
            </Link>
            <Link
                :href="route('client.surveys.index')"
                class="inline-flex rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-800"
            >
                Ir para pesquisas NR1
            </Link>
        </div>
    </ClientLayout>
</template>
