<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CountUp from './CountUp.vue';
import EmptyState from './EmptyState.vue';
import Nr1Button from './Nr1Button.vue';
import OpportunityCard from './OpportunityCard.vue';

/**
 * Oportunidades (fila comercial): KPIs, filtros, fila em cartões e um quadro por estágio (apenas visual: mudar o estágio
 * é pela ação do cartão, que usa a rota atual). Só a Talents e o Comercial com a permissão do módulo chegam aqui.
 */
const props = defineProps({
    opportunities: { type: Object, required: true },
});

const view = ref('fila');
const filters = computed(() => props.opportunities.filters);

const apply = (changes) => {
    const next = { ...filters.value, ...changes };
    router.get(window.location.pathname, Object.fromEntries(Object.entries(next).filter(([, value]) => value)), { preserveState: true, preserveScroll: true, replace: true });
};

const columns = computed(() => props.opportunities.stages.map((stage) => ({ ...stage, rows: props.opportunities.queue.filter((row) => row.status === stage.key) })));
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Talents | Comercial</div>
                <h1>Oportunidades para qualificação.</h1>
                <p>Serviços sugeridos por necessidade organizacional, com histórico de contato e próxima ação comercial.</p>
            </div>
        </div>

        <div class="n1-g12">
            <div class="n1-card n1-stat purple c4 n1-lift">
                <div class="n1-cap">Oportunidades identificadas</div>
                <div class="n1-big"><CountUp :value="String(opportunities.counters.identified)" /></div>
            </div>
            <div class="n1-card n1-stat teal c4 n1-lift">
                <div class="n1-cap">Em contato</div>
                <div class="n1-big"><CountUp :value="String(opportunities.counters.in_contact)" /></div>
            </div>
            <div class="n1-card n1-stat gold c4 n1-lift">
                <div class="n1-cap">A qualificar</div>
                <div class="n1-big"><CountUp :value="String(opportunities.counters.to_qualify)" /></div>
            </div>

            <section class="n1-card c12" aria-labelledby="queue-title">
                <div class="n1-sectiontop">
                    <h2 id="queue-title">Fila comercial</h2>
                    <div class="n1-tools no-print" role="group" aria-label="Visão da fila">
                        <Nr1Button variant="chip" size="sm" :selected="view === 'fila'" @click="view = 'fila'">Fila</Nr1Button>
                        <Nr1Button variant="chip" size="sm" :selected="view === 'quadro'" @click="view = 'quadro'">Quadro por estágio</Nr1Button>
                    </div>
                </div>

                <div class="n1-filters no-print" role="group" aria-label="Filtros da fila">
                    <Nr1Button variant="chip" size="sm" :selected="!filters.status && !filters.origin" @click="apply({ status: '', origin: '' })">Todas</Nr1Button>
                    <Nr1Button v-for="stage in opportunities.stages" :key="stage.key" variant="chip" size="sm" :selected="filters.status === stage.key" @click="apply({ status: filters.status === stage.key ? '' : stage.key })">{{ stage.label }}</Nr1Button>
                    <label class="sr-only" for="opp-origin">Origem</label>
                    <select id="opp-origin" class="n1-select" :value="filters.origin" @change="apply({ origin: $event.target.value })">
                        <option value="">Todas as origens</option>
                        <option v-for="(label, key) in opportunities.originLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <EmptyState v-if="!opportunities.queue.length" title="Nenhuma oportunidade registrada" text="Elas surgem de pedidos de proposta de treinamento e das recomendações de solução quando uma campanha é publicada." />

                <div v-else-if="view === 'fila'" class="n1-oppgrid">
                    <OpportunityCard v-for="row in opportunities.queue" :key="row.id" :row="row" :can-work="opportunities.canWork" />
                </div>

                <div v-else class="n1-kanban" role="list" aria-label="Quadro por estágio (somente visual)">
                    <section v-for="column in columns" :key="column.key" class="n1-kcol" role="listitem" :aria-label="`${column.label}: ${column.rows.length}`">
                        <header><b>{{ column.label }}</b><span class="n1-pill gray">{{ column.rows.length }}</span></header>
                        <OpportunityCard v-for="row in column.rows" :key="row.id" :row="row" compact />
                        <p v-if="!column.rows.length" class="n1-tiny">Nenhuma</p>
                    </section>
                </div>
                <p v-if="view === 'quadro'" class="n1-tiny no-print" style="margin-top: 10px">O quadro é só visual: para mudar o estágio, use a ação no cartão da fila.</p>
            </section>
        </div>
    </div>
</template>
