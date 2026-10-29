<script setup>
import Nr1Button from './Nr1Button.vue';
import CountUp from './CountUp.vue';
import DimensionBadge from './DimensionBadge.vue';
import DimensionBars from './DimensionBars.vue';
import EmptyState from './EmptyState.vue';
import GroupDonut from './GroupDonut.vue';
import LevelPill from './LevelPill.vue';
import Nr1OverviewCards from './Nr1OverviewCards.vue';
import { dimensionMeta } from './nr1Theme';

defineProps({
    overview: { type: Object, required: true },
    planUrl: { type: String, default: null },
});

const print = () => window.print();
// Três cartões de pontos fortes com acento próprio (verde, roxo, azul), como no HTML aprovado.
const STRENGTH_TONES = ['ok', 'purple', 'blue'];
</script>

<template>
    <div>
        <div class="n1-card n1-hero" style="margin-bottom: 17px">
            <svg class="n1-hero-art" viewBox="0 0 320 200" aria-hidden="true">
                <circle cx="250" cy="70" r="62" fill="#ffffff10" />
                <circle cx="292" cy="150" r="38" fill="#ffffff0d" />
                <path d="M170 30l26 44-26 44-26-44z" fill="#ffffff12" />
                <path d="M40 170c40-50 90-60 150-20" fill="none" stroke="#ffffff1c" stroke-width="3" stroke-linecap="round" />
            </svg>
            <div class="n1-eyebrow">Inteligência de pessoas • NR-1</div>
            <h1>Enxergue o que importa.<br />Cuide do que faz diferença.</h1>
            <p>Resultados objetivos, prioridades claras e um plano de ação conectado às necessidades da empresa.</p>
            <div class="n1-tools no-print" style="margin-top: 20px">
                <Nr1Button variant="inverse" inertia v-if="planUrl" :href="planUrl">Acompanhar ações →</Nr1Button>
                <Nr1Button variant="ghost-on-dark" type="button" @click="print">Imprimir panorama ↓</Nr1Button>
            </div>
        </div>

        <EmptyState
            v-if="!overview.hasResults"
            title="Os resultados aparecem aqui em breve"
            text="Eles são exibidos quando houver respostas suficientes para proteger o anonimato dos participantes."
        />

        <div v-else class="n1-g12">
            <Nr1OverviewCards :cards="overview.cards" :priorities-count="overview.attention.length" />

            <div class="n1-card c12">
                <div class="n1-sectiontop">
                    <h2>O que a equipe reconhece como positivo</h2>
                    <span class="n1-pill ok">Pontos fortes da pesquisa</span>
                </div>
                <EmptyState v-if="!overview.strengths.length" title="Pontos fortes indisponíveis" :text="overview.strengthsEmpty ?? 'Ainda não há respostas suficientes para calcular os pontos fortes.'" />
                <template v-else>
                    <div class="n1-positive">
                        <div v-for="(strength, index) in overview.strengths" :key="strength.item" class="n1-tile n1-strength n1-lift" :class="STRENGTH_TONES[index % STRENGTH_TONES.length]">
                            <span class="n1-overline">Ponto forte · Pergunta {{ strength.item }}</span>
                            <div style="margin-top: 6px"><strong><CountUp :value="strength.pct_label" /></strong></div>
                            <p>{{ strength.body }}</p>
                            <div class="n1-progress"><span :style="{ width: strength.pct_label.replace(',', '.').replace('%', '') + '%' }"></span></div>
                            <small>{{ strength.favorable }} de {{ strength.n }} respostas favoráveis</small>
                        </div>
                    </div>
                    <p class="n1-tiny" style="margin-top: 13px">
                        Percentuais de respostas favoráveis: “Frequentemente” ou “Sempre” nos enunciados positivos; “Nunca” ou “Raramente” nos enunciados desfavoráveis.
                        <template v-if="overview.strengthsBase">Base: {{ overview.strengthsBase }} respostas por pergunta.</template>
                    </p>
                </template>
            </div>

            <div class="n1-card c7">
                <div class="n1-sectiontop">
                    <h2>Indicadores das sete dimensões</h2>
                    <span class="n1-badge-proj">Pesquisa 1–5</span>
                </div>
                <DimensionBars :dimensions="overview.dimensions" :items="overview.items" />
                <p class="n1-tiny" style="margin-top: 10px">Clique em uma dimensão para ver as perguntas e a distribuição das respostas.</p>
            </div>

            <div class="n1-card c5">
                <div class="n1-sectiontop">
                    <h2>O que merece atenção</h2>
                    <span class="n1-badge-total">{{ overview.attention.length }} prioridades</span>
                </div>
                <EmptyState v-if="!overview.attention.length" title="Nenhuma dimensão em atenção" text="Quando houver prioridades, elas aparecem aqui em ordem de índice." />
                <div v-for="(item, index) in overview.attention" :key="item.title" class="n1-priority n1-lift" :style="{ '--dim': dimensionMeta(item.title).color }">
                    <span class="n1-priority-n" :style="{ background: dimensionMeta(item.title).tint }">{{ index + 1 }}</span>
                    <div>
                        <b class="n1-dimname"><DimensionBadge :title="item.title" :size="22" />{{ item.title }}</b>
                        <p>Índice {{ item.score_label }} / 5</p>
                    </div>
                    <LevelPill risk="yellow" :label="item.band_label" />
                </div>
            </div>

            <div class="n1-card c7">
                <div class="n1-sectiontop">
                    <h2>Próximas ações</h2>
                    <Nr1Button variant="secondary" size="sm" inertia class="no-print" v-if="planUrl" :href="planUrl">Ver plano completo →</Nr1Button>
                </div>
                <EmptyState v-if="!overview.nextActions.length" title="Ainda não há medidas no plano" text="As ações sugeridas para cada prioridade aparecem aqui assim que o plano for gerado." />
                <div v-for="(action, index) in overview.nextActions" :key="action.id" class="n1-highlight n1-lift" :style="{ '--dim': dimensionMeta(action.factor).color }">
                    <div class="n1-row">
                        <strong class="n1-dimname"><DimensionBadge :title="action.factor" :size="22" />{{ index + 1 }}. {{ action.factor }}</strong>
                        <span class="n1-chip" :class="{ done: action.confirmed }">{{ action.confirmed ? '✓ ' : '' }}{{ action.chip }}</span>
                    </div>
                    <p>
                        {{ action.description }}
                        <template v-if="action.responsible"> · {{ action.responsible }}</template>
                        <template v-if="action.due_date"> · prazo {{ action.due_date }}</template>
                    </p>
                </div>
            </div>

            <div v-if="overview.groups.length" class="n1-card c5">
                <h2>Participação por grupo</h2>
                <GroupDonut :groups="overview.groups" />
                <div v-if="overview.othersCount" class="n1-smallbox n1-protect">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z" fill="#e4f4ee" stroke="#168a80" stroke-width="1.6" stroke-linejoin="round" /><path d="M9 12l2 2 4-4" fill="none" stroke="#168a80" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <div>
                        <div class="n1-mini-head">Outros Setores</div>
                        <p class="n1-tiny">
                            {{ overview.othersCount }} respostas de setores menores reunidas para proteger a confidencialidade. Indicadores recalculados a partir das respostas originais no sistema.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
