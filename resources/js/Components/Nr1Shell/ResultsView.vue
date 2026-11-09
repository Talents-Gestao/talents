<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DimensionBars from './DimensionBars.vue';
import EmptyState from './EmptyState.vue';
import InternalReviewPanel from './InternalReviewPanel.vue';
import LevelPill from './LevelPill.vue';
import Nr1Button from './Nr1Button.vue';
import QuestionsExplorer from './QuestionsExplorer.vue';
import SectorsPanel from './SectorsPanel.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Resultados da pesquisa: abas Dimensões / Setores / 39 perguntas (teclado: setas, Home e End) e, só para a
 * consultoria, a Revisão interna. Tudo vem do payload do back-end (já filtrado pelo grupo mínimo).
 */
const props = defineProps({
    results: { type: Object, required: true },
});

const TABS = [
    { key: 'dimensoes', label: 'Dimensões' },
    { key: 'setores', label: 'Setores' },
    { key: 'perguntas', label: '39 perguntas' },
];
const tab = ref('dimensoes');
const tabButtons = ref([]);

const onKey = (event, index) => {
    const move = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
    const target = event.key === 'Home' ? 0 : event.key === 'End' ? TABS.length - 1 : move !== undefined ? (index + move + TABS.length) % TABS.length : null;
    if (target === null) return;
    event.preventDefault();
    tab.value = TABS[target].key;
    tabButtons.value[target]?.focus();
};
const print = () => window.print();

// Filtros: período e estabelecimento estreitam a lista de campanhas; dimensão recorta dados já agregados pelo servidor.
const period = ref('');
const unit = ref('');
const sectionId = ref('');
const campaigns = computed(() => props.results.campaigns ?? []);
const periods = computed(() => [...new Set(campaigns.value.map((item) => item.starts_at).filter(Boolean))]);
const units = computed(() => [...new Set(campaigns.value.map((item) => item.unit_name).filter(Boolean))]);
const campaignOptions = computed(() => campaigns.value.filter((item) => item.current || ((!period.value || item.starts_at === period.value) && (!unit.value || item.unit_name === unit.value))));
const monthLabel = (value) => value.split('-').reverse().join('/');
const goToCampaign = (event) => {
    const target = campaigns.value.find((item) => String(item.id) === event.target.value);
    if (target && !target.current) router.visit(target.url);
};
const shownDimensions = computed(() => (sectionId.value ? props.results.dimensions.filter((item) => String(item.id) === sectionId.value) : props.results.dimensions));
const shownItems = computed(() => (sectionId.value ? props.results.items.filter((item) => String(item.section_id) === sectionId.value) : props.results.items));
const shownSectors = computed(() => {
    if (!sectionId.value) return props.results.sectors;
    const title = shownDimensions.value[0]?.title;

    return props.results.sectors.map((sector) => ({ ...sector, dimensions: (sector.dimensions ?? []).filter((item) => item.title === title) }));
});

const { push } = useNr1Toast();
const recalculating = ref(false);
const recalculate = () => router.post(props.results.recalculateUrl, {}, {
    preserveScroll: true,
    onStart: () => { recalculating.value = true; },
    onFinish: () => { recalculating.value = false; },
    onSuccess: () => push('Resultados recalculados.'),
});
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Diagnóstico organizacional</div>
                <h1>Resultados que mostram o todo.</h1>
                <p>Indicadores consolidados com leitura por dimensão, grupo e pergunta.</p>
            </div>
            <div class="n1-tools no-print">
                <Nr1Button v-if="results.recalculateUrl" :loading="recalculating" @click="recalculate">Recalcular</Nr1Button>
                <Nr1Button variant="primary" @click="print">Imprimir resultados ↓</Nr1Button>
            </div>
        </div>

        <EmptyState
            v-if="!results.hasResults"
            title="Os resultados aparecem aqui em breve"
            text="Eles são exibidos quando houver respostas suficientes para proteger o anonimato dos participantes."
        />

        <template v-else>
            <p v-for="warning in results.warnings" :key="warning" class="n1-notice warn" role="status" style="margin-bottom: 12px">{{ warning }}</p>

            <div class="n1-filters no-print" role="group" aria-label="Filtros dos resultados" style="margin-bottom: 12px">
                <label class="n1-label" for="f-campaign">Campanha</label>
                <select id="f-campaign" class="n1-select" :value="campaigns.find((item) => item.current)?.id" @change="goToCampaign">
                    <option v-for="item in campaignOptions" :key="item.id" :value="item.id">{{ item.title }}</option>
                </select>
                <label class="n1-label" for="f-period">Período</label>
                <select id="f-period" v-model="period" class="n1-select">
                    <option value="">Todos os períodos</option>
                    <option v-for="value in periods" :key="value" :value="value">{{ monthLabel(value) }}</option>
                </select>
                <label class="n1-label" for="f-unit">Estabelecimento</label>
                <select id="f-unit" v-model="unit" class="n1-select">
                    <option value="">Todos os estabelecimentos</option>
                    <option v-for="value in units" :key="value" :value="value">{{ value }}</option>
                </select>
                <label class="n1-label" for="f-dimension">Dimensão</label>
                <select id="f-dimension" v-model="sectionId" class="n1-select">
                    <option value="">Todas as dimensões</option>
                    <option v-for="item in results.dimensions" :key="item.id" :value="String(item.id)">{{ item.title }}</option>
                </select>
            </div>
            <p v-if="results.unitName" class="n1-tiny" style="margin-bottom: 10px">Estabelecimento: {{ results.unitName }}</p>

            <div class="n1-tabbar no-print" role="tablist" aria-label="Resultados da pesquisa">
                <button
                    v-for="(item, index) in TABS"
                    :id="'tab-' + item.key"
                    :key="item.key"
                    :ref="(el) => (tabButtons[index] = el)"
                    type="button"
                    role="tab"
                    class="n1-tab"
                    :class="{ on: tab === item.key }"
                    :aria-selected="tab === item.key"
                    :aria-controls="'panel-' + item.key"
                    :tabindex="tab === item.key ? 0 : -1"
                    @click="tab = item.key"
                    @keydown="onKey($event, index)"
                >
                    {{ item.label }}
                </button>
            </div>

            <Transition name="n1-tabfade" mode="out-in">
                <div v-if="tab === 'dimensoes'" id="panel-dimensoes" key="d" role="tabpanel" aria-labelledby="tab-dimensoes" class="n1-g12">
                    <div class="n1-card c8">
                        <h2>Resultados por dimensão</h2>
                        <DimensionBars :dimensions="shownDimensions" :items="shownItems" />
                        <p class="n1-tiny" style="margin-top: 10px">Clique em uma dimensão para ver as perguntas e a distribuição das respostas.</p>
                    </div>
                    <div class="n1-card c4">
                        <h2>Interpretação dos indicadores</h2>
                        <div v-for="band in results.bands" :key="band.risk" class="n1-sln">
                            <LevelPill :risk="band.risk" :label="`${band.label} • ${band.range}`" />
                            <p>{{ band.text }}</p>
                        </div>
                        <p v-if="results.legend" class="n1-tiny" style="margin-top: 10px">{{ results.legend }}</p>
                        <p v-if="results.disclaimer" class="n1-tiny">{{ results.disclaimer }}</p>
                    </div>
                </div>

                <div v-else-if="tab === 'setores'" id="panel-setores" key="s" role="tabpanel" aria-labelledby="tab-setores">
                    <SectorsPanel
                        :sectors="shownSectors"
                        :note="results.groupsNote"
                        :min-group-size="results.minGroupSize"
                        :respondents="results.respondents"
                        :message="results.insufficientMessage"
                    />
                </div>

                <div v-else id="panel-perguntas" key="q" role="tabpanel" aria-labelledby="tab-perguntas">
                    <QuestionsExplorer :items="shownItems" :dimensions="results.dimensions" />
                </div>
            </Transition>

            <section v-if="results.exports.length" class="n1-card no-print" style="margin-top: 17px" aria-labelledby="exports-title">
                <h2 id="exports-title">Exportações</h2>
                <p class="n1-tiny">Só dados agregados, com a mesma regra de grupo mínimo da tela.</p>
                <div class="n1-tools">
                    <Nr1Button v-for="item in results.exports" :key="item.href" size="sm" :href="item.href" external>{{ item.label }}</Nr1Button>
                </div>
            </section>

            <InternalReviewPanel v-if="results.internalReview" :review="results.internalReview" style="margin-top: 17px" />
        </template>
    </div>
</template>
