<script setup>
import Nr1DimensionBars from '@/Components/Nr1Dashboard/Nr1DimensionBars.vue';
import Nr1GroupParticipation from '@/Components/Nr1Dashboard/Nr1GroupParticipation.vue';
import Nr1ItemCards from '@/Components/Nr1Dashboard/Nr1ItemCards.vue';
import { bandBadgeClass } from '@/utils/nr1Bands';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
    campaigns: { type: Array, default: () => [] },
});

const TABS = [
    { id: 'dimensions', label: 'Dimensões' },
    { id: 'sectors', label: 'Setores' },
    { id: 'items', label: '39 perguntas' },
];

const tab = ref('dimensions');
const period = ref('');
const unit = ref('');
const sectionId = ref('');

const current = computed(() => props.campaigns.find((campaign) => campaign.current) ?? null);

const periods = computed(() => [...new Set(props.campaigns.map((campaign) => campaign.starts_at).filter(Boolean))]);
const units = computed(() => [...new Set(props.campaigns.map((campaign) => campaign.unit_name).filter(Boolean))]);

// Período e estabelecimento estreitam a lista de campanhas; a campanha aberta sempre continua na lista.
const campaignOptions = computed(() =>
    props.campaigns.filter(
        (campaign) =>
            campaign.current ||
            ((period.value === '' || campaign.starts_at === period.value) && (unit.value === '' || campaign.unit_name === unit.value)),
    ),
);

const monthLabel = (value) => {
    const [year, month] = value.split('-');

    return `${month}/${year}`;
};

const goToCampaign = (event) => {
    const target = props.campaigns.find((campaign) => String(campaign.id) === event.target.value);
    if (target && !target.current) {
        router.visit(target.url);
    }
};

const dimensions = computed(() =>
    sectionId.value === '' ? props.dashboard.dimensions : props.dashboard.dimensions.filter((dimension) => String(dimension.section_id) === sectionId.value),
);

const items = computed(() =>
    sectionId.value === '' ? props.dashboard.items : props.dashboard.items.filter((item) => String(item.section_id) === sectionId.value),
);

const sectors = computed(() =>
    props.dashboard.sectors.map((sector) => ({
        ...sector,
        dimensions: sectionId.value === '' ? sector.dimensions : sector.dimensions.filter((dimension) => String(dimension.section_id) === sectionId.value),
    })),
);

const onKeydown = (event) => {
    const index = TABS.findIndex((item) => item.id === tab.value);
    const next = event.key === 'ArrowRight' ? index + 1 : event.key === 'ArrowLeft' ? index - 1 : null;
    if (next === null) {
        return;
    }
    event.preventDefault();
    tab.value = TABS[(next + TABS.length) % TABS.length].id;
    document.getElementById(`results-tab-${tab.value}`)?.focus();
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm" role="group" aria-label="Filtros dos resultados">
            <div>
                <label for="filter-campaign" class="block text-xs font-semibold text-gray-600">Campanha</label>
                <select id="filter-campaign" class="mt-1 rounded-md border-gray-300 text-sm" :value="current?.id" @change="goToCampaign">
                    <option v-if="!current" :value="dashboard.survey.id">{{ dashboard.survey.title }}</option>
                    <option v-for="campaign in campaignOptions" :key="campaign.id" :value="campaign.id">{{ campaign.title }}</option>
                </select>
            </div>
            <div>
                <label for="filter-period" class="block text-xs font-semibold text-gray-600">Período</label>
                <select id="filter-period" v-model="period" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">Todos os períodos</option>
                    <option v-for="value in periods" :key="value" :value="value">{{ monthLabel(value) }}</option>
                </select>
            </div>
            <div>
                <label for="filter-unit" class="block text-xs font-semibold text-gray-600">Estabelecimento</label>
                <select id="filter-unit" v-model="unit" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">Todos os estabelecimentos</option>
                    <option v-for="value in units" :key="value" :value="value">{{ value }}</option>
                </select>
            </div>
            <div>
                <label for="filter-dimension" class="block text-xs font-semibold text-gray-600">Dimensão</label>
                <select id="filter-dimension" v-model="sectionId" class="mt-1 rounded-md border-gray-300 text-sm">
                    <option value="">Todas as dimensões</option>
                    <option v-for="dimension in dashboard.dimensions" :key="dimension.section_id" :value="String(dimension.section_id)">{{ dimension.title }}</option>
                </select>
            </div>
        </div>

        <div role="tablist" aria-label="Resultados da pesquisa" class="flex flex-wrap gap-2 border-b border-gray-200" @keydown="onKeydown">
            <button
                v-for="item in TABS"
                :id="`results-tab-${item.id}`"
                :key="item.id"
                type="button"
                role="tab"
                :aria-selected="tab === item.id"
                :aria-controls="`results-panel-${item.id}`"
                :tabindex="tab === item.id ? 0 : -1"
                class="-mb-px border-b-2 px-4 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-700"
                :class="tab === item.id ? 'border-talents-700 text-talents-900' : 'border-transparent text-gray-600 hover:text-gray-900'"
                @click="tab = item.id"
            >
                {{ item.label }}
            </button>
        </div>

        <div v-show="tab === 'dimensions'" id="results-panel-dimensions" role="tabpanel" aria-labelledby="results-tab-dimensions" class="space-y-4">
            <Nr1DimensionBars :dimensions="dimensions" :legend="''" />
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="results-legend-title">
                <h2 id="results-legend-title" class="text-base font-semibold text-talents-900">Interpretação dos indicadores</h2>
                <p class="mt-2 text-sm text-gray-700">{{ dashboard.method.legend }}</p>
                <p class="mt-2 text-xs text-gray-500">{{ dashboard.band_disclaimer }}</p>
            </section>
            <slot name="dimensions-extra" />
        </div>

        <div v-show="tab === 'sectors'" id="results-panel-sectors" role="tabpanel" aria-labelledby="results-tab-sectors" class="space-y-4">
            <Nr1GroupParticipation :groups="dashboard.groups" :respondents="dashboard.respondents" />

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="results-sectors-title">
                <h2 id="results-sectors-title" class="text-base font-semibold text-talents-900">Setores</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Setores com menos de {{ dashboard.min_group_size }} respondentes não são exibidos, e outros setores pequenos podem ser
                    ocultados para impedir a identificação por subtração.
                </p>
                <p v-if="sectors.length === 0" class="mt-3 text-sm text-gray-600">Nenhum setor pode ser exibido nesta campanha.</p>
                <div v-for="sector in sectors" :key="sector.department_id" class="mt-4 rounded-lg border border-gray-100 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="font-semibold text-talents-900">{{ sector.department_name }}</p>
                        <span class="text-xs text-gray-500">n = {{ sector.n }}</span>
                        <span class="font-semibold">{{ sector.score_label }}</span>
                        <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandBadgeClass(sector.risk_level)">{{ sector.band_label }}</span>
                    </div>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li v-for="dimension in sector.dimensions" :key="dimension.section_id" class="flex flex-wrap items-center gap-2">
                            <span class="text-gray-700">{{ dimension.title }}</span>
                            <span class="font-semibold">{{ dimension.score_label }}</span>
                            <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="bandBadgeClass(dimension.risk_level)">{{ dimension.band_label }}</span>
                        </li>
                    </ul>
                </div>
                <p class="mt-3 text-xs text-gray-500">{{ dashboard.band_disclaimer }}</p>
            </section>
        </div>

        <div v-show="tab === 'items'" id="results-panel-items" role="tabpanel" aria-labelledby="results-tab-items">
            <Nr1ItemCards :items="items" :respondents="dashboard.respondents" />
        </div>
    </div>
</template>
