<script setup>
import { computed, ref } from 'vue';
import DimensionBadge from './DimensionBadge.vue';
import EmptyState from './EmptyState.vue';
import Nr1Button from './Nr1Button.vue';
import { ANSWERS, dimensionMeta } from './nr1Theme';

/**
 * «O que dizem as 39 perguntas»: cartões com a distribuição Nunca → Sempre (barra empilhada com tooltip e texto),
 * filtro por dimensão, ordenação (Ordem, Mais favoráveis, Mais pontos de atenção) e busca. Dados agregados do painel.
 */
const props = defineProps({
    items: { type: Array, required: true },
    dimensions: { type: Array, required: true },
});

const dimension = ref('all');
const sort = ref('order');
const search = ref('');
const showAll = ref(false);

const titleById = computed(() => Object.fromEntries(props.dimensions.map((d) => [d.id, d.title])));
const SORTS = [
    { key: 'order', label: 'Ordem das perguntas' },
    { key: 'strength', label: 'Mais favoráveis' },
    { key: 'attention', label: 'Mais pontos de atenção' },
];

const list = computed(() => {
    const term = search.value.trim().toLowerCase();
    let rows = props.items.filter((item) => (dimension.value === 'all' || String(item.section_id) === String(dimension.value)) && (!term || item.body.toLowerCase().includes(term)));
    if (sort.value === 'strength') rows = [...rows].sort((a, b) => b.favorable_pct - a.favorable_pct);
    if (sort.value === 'attention') rows = [...rows].sort((a, b) => b.unfavorable_pct - a.unfavorable_pct);

    return rows;
});
const visible = computed(() => (showAll.value || search.value.trim() ? list.value : list.value.slice(0, 12)));
const pct = (value) => `${String(value).replace('.', ',')}%`;
const describe = (item) => ANSWERS.map((answer, index) => `${answer.label}: ${item.counts[index + 1]} (${pct(item.distribution[index + 1])})`).join('; ');
const reset = () => {
    dimension.value = 'all';
    sort.value = 'order';
    search.value = '';
};
const isDefault = computed(() => dimension.value === 'all' && sort.value === 'order' && !search.value);
</script>

<template>
    <div class="n1-card">
        <div class="n1-sectiontop">
            <h2>O que dizem as 39 perguntas</h2>
            <span class="n1-pill blue">Visualização agregada</span>
        </div>
        <p class="n1-tiny" style="margin-top: 0">Cada cartão mostra a distribuição das respostas. Os percentuais favoráveis respeitam o sentido da afirmação.</p>

        <div class="n1-filters" role="group" aria-label="Filtros das perguntas">
            <Nr1Button variant="chip" size="sm" :selected="isDefault" @click="reset">Todas</Nr1Button>
            <Nr1Button v-for="option in SORTS" :key="option.key" variant="chip" size="sm" :selected="sort === option.key" @click="sort = option.key">{{ option.label }}</Nr1Button>
            <label class="sr-only" for="q-dimension">Filtrar dimensão</label>
            <select id="q-dimension" v-model="dimension" class="n1-select">
                <option value="all">Todas as dimensões</option>
                <option v-for="d in dimensions" :key="d.id" :value="d.id">{{ d.title }}</option>
            </select>
            <label class="sr-only" for="q-search">Buscar uma pergunta</label>
            <input id="q-search" v-model="search" type="search" placeholder="Buscar uma pergunta" class="n1-select" style="min-width: 200px" />
        </div>

        <div class="n1-legend" aria-hidden="true" style="margin: 12px 0">
            <span v-for="answer in ANSWERS" :key="answer.label"><i :style="{ background: answer.color }"></i>{{ answer.label }}</span>
        </div>
        <p class="n1-tiny" role="status">{{ list.length }} de {{ items.length }} perguntas</p>

        <EmptyState v-if="!list.length" title="Nenhuma pergunta encontrada" text="Ajuste a busca ou o filtro de dimensão." />
        <TransitionGroup v-else name="n1-qlist" tag="div" class="n1-qgrid">
            <article v-for="item in visible" :key="item.position" class="n1-qcard n1-lift">
                <div class="n1-q-top">
                    <span class="n1-q-num" :style="{ background: dimensionMeta(titleById[item.section_id]).tint, color: dimensionMeta(titleById[item.section_id]).color }">Q{{ String(item.position).padStart(2, '0') }}</span>
                    <span class="n1-qdim"><DimensionBadge :title="titleById[item.section_id] ?? ''" :size="20" />{{ titleById[item.section_id] }}</span>
                </div>
                <h3>{{ item.body }}</h3>
                <div class="n1-stack" role="img" :aria-label="`Distribuição de respostas. ${describe(item)}`">
                    <span
                        v-for="(answer, index) in ANSWERS"
                        :key="answer.label"
                        :style="{ width: item.distribution[index + 1] + '%', background: answer.color }"
                        :title="`${answer.label}: ${item.counts[index + 1]} (${pct(item.distribution[index + 1])})`"
                    ></span>
                </div>
                <div class="n1-q-bottom">
                    <b style="color: #17765f">✓ {{ item.favorable_pct_label }} favoráveis</b>
                    <span class="n1-q-unfav">{{ item.unfavorable_pct_label }} desfavoráveis</span>
                </div>
                <div class="n1-tiny" style="margin-top: 5px">
                    {{ item.statement === 'unfavorable' ? 'Enunciado desfavorável: resposta favorável = Nunca/Raramente.' : 'Enunciado favorável: resposta favorável = Frequentemente/Sempre.' }}
                </div>
            </article>
        </TransitionGroup>

        <div v-if="!showAll && !search.trim() && list.length > 12" style="text-align: center; margin-top: 17px">
            <Nr1Button variant="primary" @click="showAll = true">Ver todas as {{ list.length }} perguntas ↓</Nr1Button>
        </div>
    </div>
</template>
