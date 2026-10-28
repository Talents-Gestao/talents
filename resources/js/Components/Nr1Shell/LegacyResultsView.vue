<script setup>
import ClientResultsBody from '@/Components/Nr1Legacy/ClientResultsBody.vue';
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';
import { router } from '@inertiajs/vue3';
import LegacyNotice from './LegacyNotice.vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Resultados no modelo anterior = Laudo 01 Diagnóstico: o MESMO painel do legado (indicador geral, dimensões com radar,
 * participação por setor, risco por setor, dimensões por setor, tabela de calor, detalhamento por pergunta, insights),
 * com o padrão visual do shell por `.nr1-theme`. A empresa vê o corpo da tela antiga de Resultados (Mia incluída).
 */
const props = defineProps({
    results: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
    minPerSector: { type: Number, default: 1 },
    forTalents: { type: Boolean, default: false },
});

const { push } = useNr1Toast();
const print = () => window.print();
const recalculate = () => router.post(props.results.recalculateUrl, {}, { preserveScroll: true, onSuccess: () => push('Recálculo solicitado.') });
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Diagnóstico organizacional</div>
                <h1>Resultados que mostram o todo.</h1>
                <p>Laudo 01 · Diagnóstico: indicadores consolidados por dimensão, setor e pergunta.</p>
            </div>
            <div class="n1-legacybar no-print">
                <LegacyNotice :text="notice" />
                <Nr1Button v-if="results.recalculateUrl" size="sm" @click="recalculate">Recalcular</Nr1Button>
                <Nr1Button variant="primary" size="sm" @click="print">Imprimir resultados ↓</Nr1Button>
            </div>
        </div>

        <p v-if="!forTalents" class="n1-legacy-privacy" role="note">
            <b>Resultados por setor neste modelo:</b> seguem a regra da pesquisa anterior (mínimo de {{ minPerSector }} por setor). A regra de grupo mínimo e supressão da V11 não se aplica a esta campanha.
        </p>

        <div class="nr1-theme">
            <ClientResultsBody v-if="results.client" v-bind="{ ...results.panel, ...results.client }" />
            <Nr1SurveyResultsPanel v-else v-bind="{
                survey: results.panel.survey,
                overall: results.panel.overall,
                bySection: results.panel.bySection,
                deptOveralls: results.panel.deptOveralls,
                deptSectionsByDepartment: results.panel.deptSectionsByDepartment,
                insights: results.panel.insights,
                questionDistributions: results.panel.questionDistributions,
                departmentParticipation: results.panel.departmentParticipation,
                questionDistributionsByDepartment: results.panel.questionDistributionsByDepartment,
            }" />
        </div>

        <div v-if="results.reports.length" class="n1-card no-print" style="margin-top: 17px">
            <div class="n1-sectiontop"><h2>Relatórios e exportações</h2></div>
            <div class="n1-tools">
                <Nr1Button v-for="report in results.reports" :key="report.href" size="sm" :href="report.href" external>{{ report.label }}</Nr1Button>
            </div>
        </div>

        <div class="n1-tools no-print" style="margin-top: 16px; justify-content: flex-end">
            <Nr1Button variant="primary" inertia :href="results.nextUrl">Continuar para o parecer →</Nr1Button>
        </div>
    </div>
</template>
