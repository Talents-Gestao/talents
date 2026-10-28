<script setup>
import MeasureCard from '@/Components/Nr1Plan/MeasureCard.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    survey: { type: Object, required: true },
    measures: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    users: { type: Array, default: () => [] },
    transitions: { type: Object, default: () => ({}) },
    effectivenessResults: { type: Object, default: () => ({}) },
    cardLabel: { type: String, default: 'Medidas com responsável confirmado' },
});

const confirmedCount = computed(() => props.measures.filter((measure) => measure.confirmed !== null).length);
</script>

<template>
    <Head :title="`Plano de ação - ${survey.title}`" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Plano de ação</h2>
                    <p class="text-sm text-gray-600">{{ survey.title }}</p>
                </div>
                <Link :href="route('client.surveys.psychosocial-dashboard', survey.id)" class="text-sm text-talents-700 hover:underline">
                    Voltar ao painel
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ $page.props.flash.error }}
            </div>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-label="Resumo do plano">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ cardLabel }}</p>
                <p class="mt-2 text-3xl font-bold text-talents-900">
                    {{ confirmedCount }}<span class="ml-1 text-base font-medium text-gray-500">/ {{ measures.length }}</span>
                </p>
                <p class="mt-2 text-xs text-gray-500">
                    A ordem das medidas não é prioridade legal de risco ocupacional. Uma medida só aparece como concluída depois que a empresa a conclui com evidência.
                </p>
                <p v-if="!canEdit" class="mt-2 text-xs text-gray-600">Você pode consultar o plano. Confirmar e atualizar medidas exige a permissão de edição em Planos de ação.</p>
            </section>

            <p v-if="!measures.length" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-sm text-gray-600">
                Ainda não há medidas propostas para esta campanha.
            </p>

            <MeasureCard
                v-for="measure in measures"
                :key="measure.id"
                :survey-id="survey.id"
                :measure="measure"
                :users="users"
                :can-edit="canEdit"
                :transitions="transitions"
                :effectiveness-results="effectivenessResults"
            />
        </div>
    </ClientLayout>
</template>
