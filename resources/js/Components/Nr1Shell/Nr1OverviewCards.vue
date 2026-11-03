<script setup>
import { computed } from 'vue';
import CountUp from './CountUp.vue';
import LevelPill from './LevelPill.vue';
import ProgressRing from './ProgressRing.vue';
import ScoreGauge from './ScoreGauge.vue';
import { overviewCardsText } from './nr1OverviewCards';

/**
 * Os quatro cartões da Visão geral (HTML aprovado), iguais na V11 e no modelo anterior: Índice geral, Participação,
 * Dimensões em atenção e Plano de ação, cada um com o seu acento de cor. Sem dado, estado honesto (nunca zero inventado).
 */
const props = defineProps({
    cards: { type: Object, required: true },
    // Quantas dimensões em atenção aparecem na lista de prioridades (null: a tela não mostra a lista).
    prioritiesCount: { type: Number, default: null },
});

const text = computed(() => overviewCardsText(props.cards, props.prioritiesCount));
const score = computed(() => parseFloat(String(props.cards.overall?.score_label ?? '0').replace(',', '.')));
const participationPercent = computed(() => parseFloat(String(props.cards.participation?.percent_label ?? '0').replace(',', '.')) || 0);
</script>

<template>
    <div class="n1-card n1-stat purple c3 n1-lift">
        <div class="n1-cap">Índice geral da pesquisa</div>
        <div class="n1-stat-row">
            <div>
                <div class="n1-big"><CountUp :value="cards.overall.score_label" /> <small>/ 5</small></div>
                <LevelPill :risk="cards.overall.risk_level" :label="cards.overall.band_label" />
            </div>
            <ScoreGauge :score="score" :score-label="cards.overall.score_label" :risk="cards.overall.risk_level" :band-label="cards.overall.band_label" :band-labels="cards.overall.band_labels" />
        </div>
    </div>

    <div class="n1-card n1-stat teal c3 n1-lift">
        <div class="n1-cap">Participação</div>
        <template v-if="!cards.participation.hidden">
            <div class="n1-stat-row">
                <div>
                    <div v-if="cards.participation.percent_label" class="n1-big"><CountUp :value="cards.participation.percent_label" /></div>
                    <div v-else class="n1-big"><CountUp :value="String(cards.participation.respondents)" /> <small>respostas</small></div>
                    <p class="n1-tiny">
                        <b>{{ cards.participation.respondents }}</b> respostas · {{ text.eligible }}
                        <i v-if="cards.participation.estimated">(exemplo)</i>
                    </p>
                </div>
                <ProgressRing v-if="cards.participation.percent_label" :percent="participationPercent" :label="`Participação de ${cards.participation.percent_label}`" />
            </div>
        </template>
        <template v-else>
            <div class="n1-big">—</div>
            <p class="n1-tiny">{{ cards.participation.message }}</p>
        </template>
    </div>

    <div class="n1-card n1-stat gold c3 n1-lift">
        <div class="n1-cap">Dimensões em atenção</div>
        <div class="n1-big"><CountUp :value="String(cards.attention.count ?? 0)" /> <small>/ {{ cards.attention.total }}</small></div>
        <div class="n1-dots" role="img" :aria-label="`${cards.attention.count ?? 0} de ${cards.attention.total} dimensões em atenção`">
            <i v-for="n in cards.attention.total" :key="n" :class="{ on: n <= (cards.attention.count ?? 0) }"></i>
        </div>
        <p v-if="text.attention" class="n1-tiny">{{ text.attention }}</p>
    </div>

    <div class="n1-card n1-stat rose c3 n1-lift">
        <div class="n1-cap">Plano de ação</div>
        <template v-if="text.plan">
            <div class="n1-big"><CountUp :value="String(cards.plan.confirmed)" /> <small>/ {{ cards.plan.total }}</small></div>
            <div class="n1-progress" role="img" :aria-label="`${cards.plan.confirmed} de ${cards.plan.total} medidas com responsável confirmado`"><span :style="{ width: text.plan.progress + '%' }"></span></div>
            <p class="n1-tiny" style="margin-top: 8px">Medidas com responsável confirmado</p>
        </template>
        <template v-else>
            <div class="n1-big">—</div>
            <p class="n1-tiny">{{ text.planEmpty }}</p>
        </template>
    </div>
</template>
