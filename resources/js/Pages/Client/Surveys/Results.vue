<script setup>
import ClientResultsBody from '@/Components/Nr1Legacy/ClientResultsBody.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

/**
 * Página antiga «Resultados»: layout do cliente + cabeçalho com as ações; o corpo (Mia, painel de resultados) vive em
 * Components/Nr1Legacy/ClientResultsBody.vue, o mesmo que o shell NR-1 usa.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps({
    survey: Object,
    overall: Object,
    riskScenarioLabel: { type: String, default: null },
    actionPlanPublished: { type: Boolean, default: false },
});

const recalculate = () => {
    router.post(route('client.surveys.recalculate', props.survey.id));
};
</script>

<template>
    <Head :title="`Resultados - ${survey.title}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Resultados</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1 text-sm" @click="recalculate">Recalcular</button>
                    <span
                        v-if="overall?.risk_level"
                        class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-medium text-gray-800"
                    >
                        {{ riskScenarioLabel }}
                    </span>
                    <a
                        :href="route('client.surveys.reports.executive', survey.id)"
                        class="rounded-md bg-talents-700 px-3 py-1 text-sm font-semibold text-white"
                        target="_blank"
                    >
                        Relatório executivo
                    </a>
                    <a
                        v-if="actionPlanPublished"
                        :href="route('client.surveys.reports.action-plan', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                        target="_blank"
                    >
                        Plano de ação
                    </a>
                    <a
                        :href="route('client.surveys.reports.referral', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                        target="_blank"
                    >
                        Encaminhamento técnico
                    </a>
                    <a
                        :href="route('client.surveys.reports.technical', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Dados técnicos (RH)
                    </a>
                    <a
                        :href="route('client.surveys.export.json', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Exportar JSON (agregado)
                    </a>
                    <a
                        :href="route('client.surveys.export.csv', survey.id)"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-800"
                        target="_blank"
                    >
                        Exportar CSV (agregado)
                    </a>
                    <Link
                        :href="route('client.surveys.psychosocial-dashboard', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                    >
                        Painel gerencial
                    </Link>
                    <Link :href="route('client.surveys.show', survey.id)" class="text-sm text-talents-700 hover:underline">Voltar</Link>
                </div>
            </div>
        </template>

        <ClientResultsBody v-bind="{ ...$props, ...$attrs }" />
    </ClientLayout>
</template>
