<script setup>
import { ref } from 'vue';
import LevelPill from './LevelPill.vue';
import { GROUP_COLORS } from './nr1Theme';

/**
 * «Onde estão os participantes»: grupos de visualização com todas as respostas contabilizadas. As cores diferenciam
 * grupos, NÃO níveis de risco (o nível vai em chip com ícone e texto). Setores com menos respostas que o grupo mínimo
 * ficam reunidos em «Outros Setores»; nenhum setor isolado aparece e a composição do grupo não é exibida.
 */
const props = defineProps({
    sectors: { type: Array, required: true },
    note: { type: String, default: null },
    minGroupSize: { type: Number, default: 5 },
    respondents: { type: Number, default: null },
    message: { type: String, default: null },
});

const openName = ref(null);
const color = (index) => GROUP_COLORS[index % GROUP_COLORS.length];
const percentOf = (sector) => parseFloat(String(sector.percent_label ?? '0').replace(',', '.').replace('%', '')) || 0;
</script>

<template>
    <div class="n1-card">
        <div class="n1-sectiontop">
            <div>
                <h2 style="margin-bottom: 3px">Onde estão os participantes</h2>
                <p class="n1-tiny" style="margin: 0">Três grupos de visualização, com todas as respostas contabilizadas. As cores diferenciam grupos, não níveis de risco.</p>
            </div>
            <span v-if="respondents" class="n1-pill blue">{{ respondents }} respostas</span>
        </div>

        <p v-if="message" class="n1-tiny" role="status">{{ message }}</p>

        <div v-else class="n1-sectorgrid">
            <article
                v-for="(sector, index) in sectors"
                :key="sector.name"
                class="n1-sector n1-lift"
                :style="{ '--sector': color(index) }"
            >
                <svg v-if="sector.others" class="n1-sicon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" /><path d="M9 12l2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <svg v-else class="n1-sicon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12M9 20v-5h6v5M8 10h2M14 10h2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                <h3>{{ sector.name }}</h3>
                <div class="n1-scount">{{ sector.n }} <small>respostas</small></div>
                <div class="n1-sribbon" role="img" :aria-label="`${sector.name}: ${sector.percent_label} das respostas`">
                    <span><i :style="{ width: percentOf(sector) + '%' }"></i></span>{{ sector.percent_label }}
                </div>
                <div style="margin-top: 10px"><LevelPill :risk="sector.risk_level" :label="`Índice ${sector.score_label} · ${sector.band_label}`" /></div>
                <button type="button" class="n1-linkbtn" :aria-expanded="openName === sector.name" @click="openName = openName === sector.name ? null : sector.name">
                    {{ openName === sector.name ? 'Ocultar as 7 dimensões' : 'Ver as 7 dimensões' }}
                </button>
                <ul v-if="openName === sector.name" class="n1-sdims">
                    <li v-for="dimension in sector.dimensions" :key="dimension.title">
                        <span>{{ dimension.title }}</span>
                        <b>{{ dimension.score_label }}</b>
                        <LevelPill :risk="dimension.risk_level" :label="dimension.band_label" />
                    </li>
                </ul>
            </article>
        </div>

        <div v-if="!message" class="n1-sectorfoot">
            <strong>Outros Setores, sem perder respostas</strong>
            <p>{{ note ?? 'Reúne setores menores para proteger a confidencialidade. Os indicadores são recalculados a partir das respostas originais; a composição do grupo não é exibida.' }}</p>
            <p><b>Privacidade:</b> grupos com menos de {{ minGroupSize }} respostas nunca aparecem isolados; as respostas são consideradas em conjunto e não é possível ver indicadores de um setor pequeno em separado. Os indicadores de cada grupo são recalculados a partir das respostas individuais, sem estimativas.</p>
        </div>
    </div>
</template>
