<script setup>
import CountUp from './CountUp.vue';
import DimensionBadge from './DimensionBadge.vue';
import EmptyState from './EmptyState.vue';
import LegacyNotice from './LegacyNotice.vue';
import LevelPill from './LevelPill.vue';
import Nr1Button from './Nr1Button.vue';
import Nr1OverviewCards from './Nr1OverviewCards.vue';

/**
 * Visão geral de uma campanha do modelo anterior: cabeçalho do laudo, os quatro cartões, pontos fortes
 * (com base nas respostas favoráveis da pesquisa efetuada) e as dimensões.
 */
defineProps({
    overview: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
    resultsUrl: { type: String, default: null },
    documentsUrl: { type: String, default: null },
});

const fillClass = (risk) => (risk === 'green' ? 'ok' : risk === 'yellow' ? 'warn' : 'high');
const SURVEY_STATUS = { active: 'Em coleta', closed: 'Encerrada', draft: 'Rascunho' };
const STRENGTH_TONES = ['ok', 'purple', 'blue'];
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Laudo NR-1</div>
                <h1>{{ overview.title }}</h1>
                <p>Panorama da campanha com os resultados já calculados no modelo anterior.</p>
            </div>
            <LegacyNotice :text="notice" />
        </div>

        <section class="n1-card" style="margin-bottom: 15px" aria-label="Cabeçalho do laudo">
            <div class="n1-sectiontop">
                <h2>Cabeçalho do laudo</h2>
                <span class="n1-pill" :class="overview.published ? 'ok' : 'warn'">{{ overview.publicationLabel }}</span>
            </div>
            <dl class="n1-laudo-grid">
                <div><dt class="n1-cap">Período</dt><dd>{{ overview.period }}</dd></div>
                <div><dt class="n1-cap">Cenário de risco</dt><dd>{{ overview.riskScenario ?? '—' }}</dd></div>
                <div><dt class="n1-cap">Mín. por setor</dt><dd>{{ overview.minPerSector }}</dd></div>
                <div><dt class="n1-cap">Última publicação</dt><dd>{{ overview.lastPublication ?? 'Ainda não publicada' }}</dd></div>
                <div><dt class="n1-cap">Coleta</dt><dd>{{ SURVEY_STATUS[overview.surveyStatus] ?? overview.surveyStatus }}</dd></div>
            </dl>
        </section>

        <EmptyState v-if="!overview.overall || !overview.cards" title="Ainda não há resultados calculados" text="Os indicadores aparecem aqui quando houver respostas concluídas e o cálculo for feito." />

        <div v-else class="n1-g12">
            <Nr1OverviewCards :cards="overview.cards" />

            <div class="n1-card c12">
                <div class="n1-sectiontop">
                    <h2>O que a equipe reconhece como positivo</h2>
                    <span class="n1-pill ok">Pontos fortes da pesquisa</span>
                </div>
                <EmptyState
                    v-if="!(overview.strengths || []).length"
                    title="Pontos fortes indisponíveis"
                    :text="overview.strengthsEmpty ?? 'Ainda não há respostas suficientes para calcular os pontos fortes.'"
                />
                <template v-else>
                    <div class="n1-positive">
                        <div
                            v-for="(strength, index) in overview.strengths"
                            :key="`${strength.item}-${index}`"
                            class="n1-tile n1-strength n1-lift"
                            :class="STRENGTH_TONES[index % STRENGTH_TONES.length]"
                        >
                            <span class="n1-overline">Ponto forte · Pergunta {{ strength.item }}</span>
                            <div style="margin-top: 6px"><strong><CountUp :value="strength.pct_label" /></strong></div>
                            <p>{{ strength.body }}</p>
                            <div class="n1-progress">
                                <span :style="{ width: String(strength.pct_label || '').replace(',', '.').replace('%', '') + '%' }" />
                            </div>
                            <small>{{ strength.favorable }} de {{ strength.n }} respostas favoráveis</small>
                        </div>
                    </div>
                    <p class="n1-tiny" style="margin-top: 13px">
                        Percentuais de respostas favoráveis: “Frequentemente” ou “Sempre” nos enunciados positivos; “Nunca” ou “Raramente” nos enunciados desfavoráveis.
                        <template v-if="overview.strengthsBase">Base: {{ overview.strengthsBase }} respostas por pergunta.</template>
                    </p>
                </template>
            </div>

            <div class="n1-card c12">
                <div class="n1-sectiontop">
                    <h2>Indicadores por dimensão</h2>
                    <span class="n1-badge-proj">Pesquisa 1–5</span>
                </div>
                <div v-for="dimension in overview.dimensions" :key="dimension.title" class="n1-dimhead" style="cursor: default">
                    <span class="n1-dimname"><DimensionBadge :title="dimension.title" :size="26" />{{ dimension.title }}</span>
                    <span class="n1-track" role="img" :aria-label="`${dimension.title}: índice ${dimension.score_label} de 5, faixa ${dimension.band_label}`">
                        <span class="n1-fill" :class="fillClass(dimension.risk_level)" :style="{ width: dimension.bar_percent + '%' }"></span>
                    </span>
                    <b class="n1-score">{{ dimension.score_label }}</b>
                    <LevelPill :risk="dimension.risk_level" :label="dimension.band_label" />
                    <span></span>
                </div>
            </div>
        </div>

        <div class="n1-tools no-print" style="margin-top: 16px">
            <Nr1Button v-if="resultsUrl" variant="primary" inertia :href="resultsUrl">Ver o diagnóstico completo →</Nr1Button>
            <Nr1Button v-if="documentsUrl" inertia :href="documentsUrl">Parecer e publicação →</Nr1Button>
        </div>
    </div>
</template>
