<script setup>
import Nr1AttentionList from '@/Components/Nr1Dashboard/Nr1AttentionList.vue';
import Nr1DimensionBars from '@/Components/Nr1Dashboard/Nr1DimensionBars.vue';
import Nr1GroupParticipation from '@/Components/Nr1Dashboard/Nr1GroupParticipation.vue';
import Nr1NextActions from '@/Components/Nr1Dashboard/Nr1NextActions.vue';
import Nr1ResultsTabs from '@/Components/Nr1Dashboard/Nr1ResultsTabs.vue';
import Nr1StrengthsList from '@/Components/Nr1Dashboard/Nr1StrengthsList.vue';
import Nr1SummaryCards from '@/Components/Nr1Dashboard/Nr1SummaryCards.vue';
import Nr1WelcomeStrip from '@/Components/Nr1Dashboard/Nr1WelcomeStrip.vue';
import { ref } from 'vue';

defineProps({
    dashboard: { type: Object, required: true },
    nextActions: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    planUrl: { type: String, default: null },
    printUrl: { type: String, default: null },
});

const TABS = [
    { id: 'overview', label: 'Visão geral' },
    { id: 'results', label: 'Resultados da pesquisa' },
];

const activeTab = ref('overview');

const onKeydown = (event) => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
        return;
    }
    event.preventDefault();
    activeTab.value = activeTab.value === 'overview' ? 'results' : 'overview';
    document.getElementById(`tab-${activeTab.value}`)?.focus();
};
</script>

<template>
    <div class="space-y-6">
        <div role="tablist" aria-label="Seções do painel" class="flex gap-2 border-b border-gray-200" @keydown="onKeydown">
            <button
                v-for="tab in TABS"
                :id="`tab-${tab.id}`"
                :key="tab.id"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.id"
                :aria-controls="`panel-${tab.id}`"
                :tabindex="activeTab === tab.id ? 0 : -1"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-700"
                :class="activeTab === tab.id ? 'border-talents-700 text-talents-900' : 'border-transparent text-gray-600 hover:text-gray-900'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-show="activeTab === 'overview'" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" class="space-y-6">
            <Nr1WelcomeStrip :plan-url="planUrl" :print-url="printUrl" />

            <Nr1SummaryCards :cards="dashboard.cards" :band-disclaimer="dashboard.band_disclaimer" />

            <div class="grid gap-6 lg:grid-cols-2">
                <Nr1StrengthsList :strengths="dashboard.strengths" />
                <Nr1DimensionBars :dimensions="dashboard.dimensions" :legend="dashboard.method.legend" />
            </div>

            <Nr1AttentionList :attention="dashboard.attention" :has-results="dashboard.has_results" />

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <Nr1NextActions :actions="nextActions" :plan-url="planUrl" />
                </div>
                <div class="lg:col-span-2">
                    <Nr1GroupParticipation :groups="dashboard.groups" :respondents="dashboard.respondents" />
                </div>
            </div>
        </div>

        <div v-show="activeTab === 'results'" id="panel-results" role="tabpanel" aria-labelledby="tab-results">
            <Nr1ResultsTabs :dashboard="dashboard" :campaigns="campaigns">
                <template #dimensions-extra>
                    <slot name="dimensions-extra" />
                </template>
            </Nr1ResultsTabs>
        </div>
    </div>
</template>
