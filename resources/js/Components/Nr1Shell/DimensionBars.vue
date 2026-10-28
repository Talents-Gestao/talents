<script setup>
import { computed, onMounted, ref } from 'vue';
import DimensionBadge from './DimensionBadge.vue';
import LevelPill from './LevelPill.vue';
import { ANSWERS, dimensionMeta, reducedMotion } from './nr1Theme';

/**
 * As sete dimensões: barras que crescem ao entrar, ordenadas por índice (maior primeiro) e clicáveis. Ao abrir, mostra
 * as perguntas da dimensão com a distribuição Nunca → Sempre em barra empilhada (tooltip e texto acessíveis).
 * Usa só os dados agregados que o back-end já filtrou.
 */
const props = defineProps({
    dimensions: { type: Array, required: true },
    items: { type: Array, default: () => [] },
});

const open = ref(null);
const grown = ref(reducedMotion());
onMounted(() => {
    if (!grown.value) requestAnimationFrame(() => requestAnimationFrame(() => (grown.value = true)));
});

const sorted = computed(() => [...props.dimensions].sort((a, b) => parseFloat(b.score_label.replace(',', '.')) - parseFloat(a.score_label.replace(',', '.'))));
const itemsOf = (dimension) => props.items.filter((item) => item.section_id === dimension.id);
const toggle = (dimension) => (open.value = open.value === dimension.id ? null : dimension.id);
const fillClass = (risk) => (risk === 'green' ? 'ok' : risk === 'yellow' ? 'warn' : 'high');
const pct = (value) => `${String(value).replace('.', ',')}%`;
const describe = (item) => ANSWERS.map((answer, index) => `${answer.label}: ${item.counts[index + 1]} (${pct(item.distribution[index + 1])})`).join('; ');
</script>

<template>
    <div class="n1-dimlist">
        <div v-for="dimension in sorted" :key="dimension.id" class="n1-dimrow" :class="{ open: open === dimension.id }">
            <button
                type="button"
                class="n1-dimhead"
                :aria-expanded="open === dimension.id"
                :aria-controls="'dim-' + dimension.id"
                @click="toggle(dimension)"
            >
                <span class="n1-dimname"><DimensionBadge :title="dimension.title" :size="26" />{{ dimension.title }}</span>
                <span class="n1-track" role="img" :aria-label="`${dimension.title}: índice ${dimension.score_label} de 5, faixa ${dimension.band_label}`">
                    <span class="n1-fill" :class="fillClass(dimension.risk_level)" :style="{ width: grown ? dimension.bar_percent + '%' : '0%' }"></span>
                </span>
                <b class="n1-score">{{ dimension.score_label }}</b>
                <LevelPill :risk="dimension.risk_level" :label="dimension.band_label" />
                <span class="n1-chev" aria-hidden="true">{{ open === dimension.id ? '▴' : '▾' }}</span>
            </button>

            <div v-if="open === dimension.id" :id="'dim-' + dimension.id" class="n1-dimbody">
                <p v-if="!itemsOf(dimension).length" class="n1-tiny">Sem perguntas agregadas para exibir nesta dimensão.</p>
                <div v-for="item in itemsOf(dimension)" :key="item.position" class="n1-q">
                    <div class="n1-q-top">
                        <span class="n1-q-num" :style="{ background: dimensionMeta(dimension.title).tint, color: dimensionMeta(dimension.title).color }">Q{{ String(item.position).padStart(2, '0') }}</span>
                        <span class="n1-q-text">{{ item.body }}</span>
                    </div>
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
                </div>
                <div v-if="itemsOf(dimension).length" class="n1-legend" aria-hidden="true">
                    <span v-for="answer in ANSWERS" :key="answer.label"><i :style="{ background: answer.color }"></i>{{ answer.label }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
