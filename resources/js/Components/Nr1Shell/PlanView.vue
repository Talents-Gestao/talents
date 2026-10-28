<script setup>
import EmptyState from './EmptyState.vue';
import CountUp from './CountUp.vue';
import MeasureCard from './MeasureCard.vue';
import Nr1Button from './Nr1Button.vue';

defineProps({
    plan: { type: Object, required: true },
});

const print = () => window.print();
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Plano de ação</div>
                <h1>Da análise à execução.</h1>
                <p>Responsáveis sugeridos pela Talents e prazos iniciais ajustáveis. A empresa confirma quem fará cada medida e acompanha os resultados.</p>
            </div>
            <Nr1Button variant="primary" class="no-print" @click="print">Ver impressão do plano ↓</Nr1Button>
        </div>

        <div class="n1-g12">
            <div class="n1-card n1-stat teal c4 n1-lift">
                <div class="n1-cap">Ações propostas</div>
                <div class="n1-big"><CountUp :value="String(plan.counters.total)" /></div>
            </div>
            <div class="n1-card n1-stat purple c4 n1-lift">
                <div class="n1-cap">Responsáveis confirmados</div>
                <div class="n1-big"><CountUp :value="String(plan.counters.confirmed)" /> <small>/ {{ plan.counters.total }}</small></div>
            </div>
            <div class="n1-card n1-stat gold c4 n1-lift">
                <div class="n1-cap">Aguardando confirmação</div>
                <div class="n1-big"><CountUp :value="String(plan.counters.pending)" /></div>
            </div>

            <div class="n1-card c12">
                <div class="n1-sectiontop">
                    <h2>Medidas preventivas e acompanhamento</h2>
                    <span class="n1-pill blue">Prazo sugerido editável</span>
                </div>
                <EmptyState v-if="!plan.measures.length" title="Ainda não há medidas no plano" text="As ações sugeridas para cada prioridade aparecem aqui assim que o plano for gerado." />
                <MeasureCard v-for="measure in plan.measures" :key="measure.id" :measure="measure" :users="plan.users" />
                <p v-if="plan.measures.length" class="n1-tiny no-print">
                    Para registrar evidências, eficácia ou replanejar uma medida, use a
                    <a :href="plan.fullUrl">tela completa do plano</a>.
                </p>
            </div>
        </div>
    </div>
</template>
