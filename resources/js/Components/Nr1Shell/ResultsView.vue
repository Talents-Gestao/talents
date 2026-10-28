<script setup>
import { ref } from 'vue';
import DimensionBars from './DimensionBars.vue';
import EmptyState from './EmptyState.vue';
import InternalReviewPanel from './InternalReviewPanel.vue';
import LevelPill from './LevelPill.vue';
import Nr1Button from './Nr1Button.vue';
import QuestionsExplorer from './QuestionsExplorer.vue';
import SectorsPanel from './SectorsPanel.vue';

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
                <Nr1Button variant="primary" @click="print">Imprimir resultados ↓</Nr1Button>
            </div>
        </div>

        <EmptyState
            v-if="!results.hasResults"
            title="Os resultados aparecem aqui em breve"
            text="Eles são exibidos quando houver respostas suficientes para proteger o anonimato dos participantes."
        />

        <template v-else>
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
                        <DimensionBars :dimensions="results.dimensions" :items="results.items" />
                        <p class="n1-tiny" style="margin-top: 10px">Clique em uma dimensão para ver as perguntas e a distribuição das respostas.</p>
                    </div>
                    <div class="n1-card c4">
                        <h2>Interpretação dos indicadores</h2>
                        <div v-for="band in results.bands" :key="band.risk" class="n1-sln">
                            <LevelPill :risk="band.risk" :label="`${band.label} • ${band.range}`" />
                            <p>{{ band.text }}</p>
                        </div>
                    </div>
                </div>

                <div v-else-if="tab === 'setores'" id="panel-setores" key="s" role="tabpanel" aria-labelledby="tab-setores">
                    <SectorsPanel
                        :sectors="results.sectors"
                        :note="results.groupsNote"
                        :min-group-size="results.minGroupSize"
                        :respondents="results.respondents"
                        :message="results.insufficientMessage"
                    />
                </div>

                <div v-else id="panel-perguntas" key="q" role="tabpanel" aria-labelledby="tab-perguntas">
                    <QuestionsExplorer :items="results.items" :dimensions="results.dimensions" />
                </div>
            </Transition>

            <InternalReviewPanel v-if="results.internalReview" :review="results.internalReview" style="margin-top: 17px" />
        </template>
    </div>
</template>
