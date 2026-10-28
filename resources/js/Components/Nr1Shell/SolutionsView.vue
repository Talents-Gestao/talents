<script setup>
import { router } from '@inertiajs/vue3';
import CountUp from './CountUp.vue';
import DimensionBadge from './DimensionBadge.vue';
import EmptyState from './EmptyState.vue';
import LevelPill from './LevelPill.vue';
import Nr1Button from './Nr1Button.vue';
import { BAND_BY_RISK } from './nr1Theme';
import { useNr1Toast } from './useNr1Toast';

/**
 * Soluções Talents: os três serviços (01 realizado, 02 e 03 sob proposta) e as recomendações geradas pelas regras
 * configuráveis (textos ainda provisórios, pendentes da Suzane). Antes da publicação: a mensagem do HTML.
 */
const props = defineProps({
    solutions: { type: Object, required: true },
    opportunitiesUrl: { type: String, default: null },
});

const { push } = useNr1Toast();
const TONES = { ok: 'ok', info: 'blue', warn: 'warn' };
const request = (item) => {
    const url = props.solutions.requestUrlBase.replace('__MATCH__', item.id);
    router.post(url, {}, { preserveScroll: true, onSuccess: () => push('Conversa solicitada. A Talents entrará em contato.') });
};
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Talents para sua empresa</div>
                <h1>Soluções conectadas às necessidades.</h1>
                <p>Serviços que podem apoiar o plano de ação, a partir dos fatores sinalizados na pesquisa.</p>
            </div>
        </div>

        <p v-if="solutions.locked" class="n1-notice warn" role="status" style="margin-bottom: 15px">Recomendações disponíveis quando o relatório for publicado.</p>

        <div class="n1-g12">
            <article v-for="card in solutions.cards" :key="card.number" class="n1-card c4 n1-servicecard n1-lift" :class="TONES[card.tone]">
                <span class="n1-svc-num" aria-hidden="true">{{ card.number }}</span>
                <h3>{{ card.title }}</h3>
                <span class="n1-pill" :class="TONES[card.tone]" style="align-self: flex-start">{{ card.status }}</span>
                <p>{{ card.text }}</p>
            </article>

            <section class="n1-card c12" aria-labelledby="recs-title">
                <div class="n1-sectiontop">
                    <h2 id="recs-title">Recomendações relacionadas aos resultados</h2>
                    <span v-if="solutions.recommendations.length" class="n1-badge-total"><CountUp :value="String(solutions.recommendations.length)" /> {{ solutions.recommendations.length === 1 ? 'frente de atenção' : 'frentes de atenção' }}</span>
                </div>

                <EmptyState v-if="solutions.noSurvey" title="Escolha uma campanha para ver as recomendações" text="As recomendações são geradas a partir dos resultados de cada campanha publicada; aqui o Comercial acompanha os serviços e as regras." />
                <EmptyState v-else-if="solutions.locked" title="Recomendações ainda bloqueadas" text="Elas aparecem para a empresa e para a consultoria quando o relatório da campanha for publicado." />
                <EmptyState v-else-if="!solutions.recommendations.length" title="Ainda não há recomendações para esta campanha" text="Elas aparecem quando uma dimensão fica em atenção e a Talents libera o serviço correspondente." />

                <div v-else class="n1-g12">
                    <article v-for="item in solutions.recommendations" :key="item.id" class="n1-offer c6 n1-lift" :style="{ '--dim': '#7c4dbd' }">
                        <div class="n1-row" style="justify-content: flex-start; flex-wrap: wrap; gap: 8px">
                            <DimensionBadge :title="item.factor" :size="26" />
                            <LevelPill :risk="item.risk_level" :label="`${item.factor} · ${item.score_label}/5 · ${BAND_BY_RISK[item.risk_level]}`" />
                        </div>
                        <h3>{{ item.service_name }}</h3>
                        <p>{{ item.text }}</p>
                        <p><b>{{ item.eligible_services }}</b></p>
                        <p class="n1-tiny">Confiança da recomendação: {{ item.confidence_label }}. Sugestão consultiva a partir de uma faixa do questionário; não é diagnóstico nem classificação de risco do PGR.</p>
                        <div class="n1-tools">
                            <span v-if="item.requested" class="n1-pill warn">Conversa solicitada</span>
                            <Nr1Button v-else-if="solutions.canRequest" size="sm" @click="request(item)">Solicitar conversa →</Nr1Button>
                            <Nr1Button v-else-if="solutions.mode === 'staff' && opportunitiesUrl" size="sm" inertia :href="opportunitiesUrl">Consultar oportunidade →</Nr1Button>
                        </div>
                    </article>
                </div>
                <p v-if="solutions.rulesUrl" class="n1-tiny no-print" style="margin-top: 12px">Regras de recomendação (revisão dos textos): <a :href="solutions.rulesUrl">abrir as regras das Soluções</a>.</p>
            </section>

            <section v-if="solutions.requests.length" class="n1-card c12" aria-labelledby="req-title">
                <div class="n1-sectiontop"><h2 id="req-title">Seus pedidos à Talents</h2></div>
                <ul class="n1-reqlist">
                    <li v-for="row in solutions.requests" :key="row.id">
                        <div><b>{{ row.subject }}</b><small>{{ row.kind }} · pedido em {{ row.requested_at }}</small></div>
                        <span class="n1-pill blue">{{ row.status_label }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
