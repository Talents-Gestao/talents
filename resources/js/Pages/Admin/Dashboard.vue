<script setup>
import DashboardBootstrapCalendar from '@/Components/DashboardBootstrapCalendar.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

import '../../../css/dashboard-calendar-bootstrap.css';

defineProps({
    stats: Object,
    riskBySegment: Array,
    criticalCompanies: Array,
    dashboardCalendar: Object,
});
</script>

<template>
    <Head title="Painel Admin" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">
                Visão geral Talents
            </h2>
        </template>

        <div v-if="dashboardCalendar" class="mb-10">
            <DashboardBootstrapCalendar
                :items="dashboardCalendar.items"
                :year="dashboardCalendar.year"
                :month="dashboardCalendar.month"
                :kind-labels="dashboardCalendar.kindLabels"
                title="Calendário estratégico"
                subtitle="Eventos e ritos do mês — visão completa"
                :full-page-href="route('admin.strategic-calendar.index')"
                full-page-label="Gerenciar itens"
                dashboard-route="admin.dashboard"
            />
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <p class="text-sm text-gray-500">Empresas</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.companies_total }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <p class="text-sm text-gray-500">Empresas ativas</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.companies_active }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <p class="text-sm text-gray-500">Campanhas</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.surveys_total }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <p class="text-sm text-gray-500">Respostas concluídas</p>
                <p class="mt-2 text-3xl font-bold">{{ stats.responses_completed }}</p>
            </div>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <h3 class="text-lg font-semibold text-talents-700">Média de saúde por segmento (0–100)</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <li v-for="row in riskBySegment" :key="row.segment" class="flex justify-between">
                        <span>{{ row.segment }}</span>
                        <span class="font-mono">{{ Number(row.avg_score).toFixed(1) }}</span>
                    </li>
                    <li v-if="!riskBySegment?.length" class="text-gray-500">Sem dados ainda.</li>
                </ul>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm">
                <h3 class="text-lg font-semibold text-talents-700">Empresas com saúde crítica (última campanha)</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <li v-for="c in criticalCompanies" :key="c.id">
                        {{ c.name }} <span v-if="c.segment" class="text-gray-500">({{ c.segment }})</span>
                    </li>
                    <li v-if="!criticalCompanies?.length" class="text-gray-500">Nenhuma no momento.</li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>
