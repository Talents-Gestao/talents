<script setup>
import ClientActionPlanBody from '@/Components/Nr1Legacy/ClientActionPlanBody.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

/**
 * Página antiga «Plano de ação» (parecer publicado): layout do cliente + cabeçalho; o corpo vive em
 * Components/Nr1Legacy/ClientActionPlanBody.vue, o mesmo que o shell NR-1 usa.
 */
defineOptions({ inheritAttrs: false });

defineProps({
    survey: Object,
    plan: { type: Object, default: null },
    actionPlanLocked: { type: Boolean, default: true },
});
</script>

<template>
    <Head title="Plano de ação" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Plano de ação</h2>
                <div class="flex gap-2">
                    <a
                        v-if="!actionPlanLocked"
                        :href="route('client.surveys.reports.action-plan', survey.id)"
                        class="rounded-md border border-talents-300 px-3 py-1 text-sm font-semibold text-talents-900"
                        target="_blank"
                    >
                        PDF do plano
                    </a>
                    <Link :href="route('client.surveys.results', survey.id)" class="text-sm text-talents-700 hover:underline">Resultados</Link>
                    <Link :href="route('client.surveys.show', survey.id)" class="text-sm text-talents-700 hover:underline">Voltar</Link>
                </div>
            </div>
        </template>

        <ClientActionPlanBody v-bind="{ ...$props, ...$attrs }" />
    </ClientLayout>
</template>
