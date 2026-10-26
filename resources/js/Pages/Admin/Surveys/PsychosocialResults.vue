<script setup>
import Nr1DashboardHeader from '@/Components/Nr1Dashboard/Nr1DashboardHeader.vue';
import Nr1InternalReview from '@/Components/Nr1Dashboard/Nr1InternalReview.vue';
import Nr1PanelTabs from '@/Components/Nr1Dashboard/Nr1PanelTabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    company: { type: Object, required: true },
    dashboard: { type: Object, required: true },
    nextActions: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    links: { type: Object, required: true },
    published: { type: String, default: null },
    internalReview: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Resultados — ${dashboard.survey.title}`" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Resultados da pesquisa · consultoria</p>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ dashboard.survey.title }}</h2>
                    <p class="text-sm text-gray-600">
                        {{ company.name }} ·
                        <span v-if="published">Publicado para a empresa em {{ published }}</span>
                        <span v-else>Ainda não publicado: a empresa não vê estes resultados</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="links.documents" class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800">Documentos</Link>
                    <Link :href="links.plan" class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800">Plano de ação</Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $page.props.flash.error }}
            </div>
            <div v-if="!dashboard.has_results" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-sm text-gray-600">
                Os resultados desta campanha ainda não foram calculados.
            </div>

            <Nr1DashboardHeader :header="dashboard.header" />

            <Nr1PanelTabs :dashboard="dashboard" :next-actions="nextActions" :campaigns="campaigns" :plan-url="links.plan">
                <template #dimensions-extra>
                    <Nr1InternalReview :review="internalReview" />
                </template>
            </Nr1PanelTabs>
        </div>
    </AdminLayout>
</template>
