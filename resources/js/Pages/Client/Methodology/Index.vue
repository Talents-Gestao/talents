<script setup>
import MethodologyStepper from '@/Components/MethodologyStepper.vue';
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    recentSurveys: Array,
});

const selectedStep = ref(2);
</script>

<template>
    <Head title="Direcionamento Estratégico" />

    <ClientLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Direcionamento Estratégico</h2>
                <p class="mt-1 text-sm text-gray-600">Acompanhe as etapas da jornada de diagnóstico e engajamento.</p>
            </div>
        </template>

        <div class="rounded-2xl border border-talents-200 bg-gradient-to-b from-talents-50/80 to-white p-6 shadow-sm sm:p-8">
            <MethodologyStepper v-model="selectedStep" />
            <div class="mt-10 border-t border-talents-100 pt-8">
                <div v-if="selectedStep === 2" class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-talents-900">Pesquisa de satisfação</h3>
                        <Link
                            :href="route('client.metodologia.pesquisa-satisfacao.create')"
                            class="rounded-lg bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                        >
                            Nova pesquisa
                        </Link>
                    </div>
                    <Link
                        :href="route('client.metodologia.pesquisa-satisfacao.index')"
                        class="inline-block text-sm font-medium text-talents-700 hover:underline"
                    >
                        Ver todas as pesquisas →
                    </Link>
                    <div v-if="recentSurveys?.length" class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="s in recentSurveys"
                            :key="s.id"
                            class="surface-card p-4 transition hover:border-talents-200"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-gray-900">{{ s.title }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ s.template?.title }}</p>
                                </div>
                                <SurveyStatusBadge :status="s.status" />
                            </div>
                            <p class="mt-3 text-sm text-gray-600">{{ s.completed_responses_count }} respostas</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link
                                    :href="route('client.metodologia.pesquisa-satisfacao.show', s.id)"
                                    class="text-sm font-medium text-talents-700 hover:underline"
                                >
                                    Detalhes
                                </Link>
                                <Link
                                    :href="route('client.metodologia.pesquisa-satisfacao.results', s.id)"
                                    class="text-sm font-medium text-talents-700 hover:underline"
                                >
                                    Resultados
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-talents-200 bg-white/80 p-8 text-center text-sm text-gray-600">
                        Nenhuma pesquisa encontrada.
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-talents-200 bg-white/60 p-10 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-talents-100 text-talents-500">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655-5.653a2.548 2.548 0 010-3.586L11.42 15.17z"
                            />
                        </svg>
                    </div>
                    <p class="mt-4 text-lg font-medium text-talents-900">Etapa em desenvolvimento</p>
                    <p class="mt-2 text-sm text-gray-600">Estamos preparando esta fase do Direcionamento Estratégico.</p>
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
