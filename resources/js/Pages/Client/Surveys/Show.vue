<script setup>
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    survey: Object,
    publicUrl: String,
    stats: Object,
});
</script>

<template>
    <Head :title="survey.title" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">{{ survey.title }}</h2>
                <Link :href="route('client.surveys.edit', survey.id)" class="text-sm font-semibold text-talents-700 hover:underline">Editar</Link>
            </div>
        </template>

        <div class="surface-card p-6">
            <p class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <span>Status:</span>
                <SurveyStatusBadge :status="survey.status" />
            </p>
            <p class="mt-2 text-sm text-gray-600">
                Período: {{ survey.starts_at }} — {{ survey.ends_at }}
            </p>
            <div class="mt-4">
                <p class="text-sm font-medium text-gray-800">Link anônimo para colaboradores</p>
                <p class="mt-2 break-all rounded bg-gray-50 p-3 text-sm font-mono text-talents-800">{{ publicUrl }}</p>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-talents-50 p-4">
                    <p class="text-xs text-talents-700">Iniciaram</p>
                    <p class="text-2xl font-bold text-talents-900">{{ stats.started }}</p>
                </div>
                <div class="rounded-lg bg-talents-50 p-4">
                    <p class="text-xs text-talents-700">Concluíram</p>
                    <p class="text-2xl font-bold text-talents-900">{{ stats.completed }}</p>
                </div>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <Link
                    :href="route('client.surveys.results', survey.id)"
                    class="rounded-md bg-talents-700 px-4 py-2 text-sm font-semibold text-white"
                >
                    Resultados e gráficos
                </Link>
                <Link
                    :href="route('client.surveys.action-plan', survey.id)"
                    class="rounded-md border border-talents-300 px-4 py-2 text-sm font-semibold text-talents-900"
                >
                    Plano de ação
                </Link>
            </div>
        </div>
    </ClientLayout>
</template>
