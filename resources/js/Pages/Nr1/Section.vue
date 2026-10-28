<script setup>
import Nr1Button from '@/Components/Nr1Shell/Nr1Button.vue';
import CampaignView from '@/Components/Nr1Shell/CampaignView.vue';
import JourneyView from '@/Components/Nr1Shell/JourneyView.vue';
import EmptyState from '@/Components/Nr1Shell/EmptyState.vue';
import LegacyCampaignView from '@/Components/Nr1Shell/LegacyCampaignView.vue';
import LegacyDocumentsView from '@/Components/Nr1Shell/LegacyDocumentsView.vue';
import LegacyNotice from '@/Components/Nr1Shell/LegacyNotice.vue';
import LegacyOverviewView from '@/Components/Nr1Shell/LegacyOverviewView.vue';
import LegacyResultsView from '@/Components/Nr1Shell/LegacyResultsView.vue';
import DocumentsView from '@/Components/Nr1Shell/DocumentsView.vue';
import OpportunitiesView from '@/Components/Nr1Shell/OpportunitiesView.vue';
import OverviewView from '@/Components/Nr1Shell/OverviewView.vue';
import PlanView from '@/Components/Nr1Shell/PlanView.vue';
import ResultsView from '@/Components/Nr1Shell/ResultsView.vue';
import SolutionsView from '@/Components/Nr1Shell/SolutionsView.vue';
import Nr1Layout from '@/Layouts/Nr1Layout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    shell: { type: Object, required: true },
    context: { type: Object, required: true },
    section: { type: Object, required: true },
});

const menuUrl = (key) => props.shell.menu.find((item) => item.key === key)?.href ?? null;
const planUrl = computed(() => props.shell.menu.find((item) => item.key === 'plano-de-acao')?.href ?? null);

/** Seção que já reproduz o HTML aprovado (Etapa B): Visão geral, Campanha NR-1 e Minha jornada. */
const view = computed(() => {
    if (props.section.overview) return 'overview';
    if (props.section.campaign) return 'campaign';
    if (props.section.results) return 'results';
    if (props.section.plan) return 'plan';
    if (props.section.documents) return 'documents';
    if (props.section.solutions) return 'solutions';
    if (props.section.opportunities) return 'opportunities';
    if (props.section.journey) return 'journey';

    return null;
});
</script>

<template>
    <Head :title="`${section.label} · NR-1`" />

    <Nr1Layout :shell="shell" :context="context">
        <p
            v-if="context.awaitingPublication"
            class="n1-card"
            role="status"
            style="margin-bottom: 17px; background: #fff1d7; border-color: #f0d9a8; color: #925d0c"
        >
            Os resultados desta campanha ficam disponíveis assim que a Talents publicar. Você será avisado.
        </p>

        <template v-if="section.legacy && !section.legacy.embedded">
            <div class="n1-intro">
                <div>
                    <div class="n1-eyebrow">{{ shell.profileLabel }}</div>
                    <h1>{{ section.label }}</h1>
                </div>
                <LegacyNotice :text="section.legacy.notice" />
            </div>
            <EmptyState title="Esta pesquisa usa a tela do modelo anterior" text="O Laudo NR-1 desta campanha continua na tela atual, que segue funcionando como sempre. Use o botão abaixo para abri-la." />
            <div class="n1-tools" style="margin-top: 16px; justify-content: center">
                <Nr1Button variant="primary" :href="section.legacy.openUrl">{{ section.legacy.openLabel }} →</Nr1Button>
            </div>
        </template>

        <template v-else-if="section.legacy">
            <LegacyOverviewView v-if="section.key === 'visao-geral'" :overview="section.legacy.overview" :notice="section.legacy.notice" :results-url="menuUrl('resultados')" :documents-url="menuUrl('documentos')" />
            <LegacyCampaignView v-else-if="section.key === 'campanha'" :campaign="section.legacy.campaign" :notice="section.legacy.notice" />
            <LegacyResultsView v-else-if="section.key === 'resultados'" :results="section.legacy.results" :notice="section.legacy.notice" :min-per-sector="section.legacy.minPerSector" :for-talents="section.legacy.forTalents" />
            <LegacyDocumentsView v-else-if="section.key === 'documentos'" :documents="section.legacy.documents" :notice="section.legacy.notice" />
            <div v-else>
                <div class="n1-intro">
                    <div>
                        <div class="n1-eyebrow">{{ shell.profileLabel }}</div>
                        <h1>{{ section.label }}</h1>
                    </div>
                    <LegacyNotice :text="section.legacy.notice" />
                </div>
                <EmptyState title="Esta seção não existe no modelo anterior" text="Soluções, Oportunidades e o plano por medidas são da pesquisa V11. O plano desta campanha vive no parecer, em Documentos." />
            </div>
        </template>

        <OverviewView v-else-if="view === 'overview'" :overview="section.overview" :plan-url="planUrl" />
        <CampaignView v-else-if="view === 'campaign'" :campaign="section.campaign" :company-name="context.company?.name ?? ''" />
        <ResultsView v-else-if="view === 'results'" :results="section.results" />
        <PlanView v-else-if="view === 'plan'" :plan="section.plan" />
        <DocumentsView v-else-if="view === 'documents'" :documents="section.documents" />
        <SolutionsView v-else-if="view === 'solutions'" :solutions="section.solutions" :opportunities-url="menuUrl('oportunidades')" />
        <OpportunitiesView v-else-if="view === 'opportunities'" :opportunities="section.opportunities" />
        <JourneyView v-else-if="view === 'journey'" :journey="section.journey" :mode="section.key" />

        <template v-else>
            <div style="display: flex; justify-content: space-between; gap: 15px; margin-bottom: 20px; flex-wrap: wrap">
                <div>
                    <div class="n1-eyebrow">{{ shell.profileLabel }}</div>
                    <h1 style="font-size: 29px; font-weight: 800; letter-spacing: -0.045em; margin: 7px 0">{{ section.label }}</h1>
                </div>
            </div>

            <section v-if="section.kpis.length" class="n1-grid" style="margin-bottom: 17px" aria-label="Indicadores da campanha">
                <div v-for="kpi in section.kpis" :key="kpi.label" class="n1-card">
                    <div class="n1-cap">{{ kpi.label }}</div>
                    <div class="n1-big">{{ kpi.value }}</div>
                    <p v-if="kpi.hint" class="n1-tiny">{{ kpi.hint }}</p>
                </div>
            </section>

            <section v-if="section.links.length" class="n1-card" aria-label="Tela completa">
                <p class="n1-tiny" style="margin-bottom: 12px">
                    Esta seção reaproveita a tela atual, que continua disponível. O visual do fluxo NR-1 será aplicado por etapas.
                </p>
                <div style="display: flex; gap: 9px; flex-wrap: wrap">
                    <Nr1Button variant="primary" v-for="link in section.links" :key="link.href" :href="link.href">{{ link.label }} →</Nr1Button>
                </div>
            </section>
            <section v-else-if="!context.awaitingPublication" class="n1-card">
                <p class="n1-tiny">Nenhuma campanha disponível para exibir nesta seção.</p>
            </section>
        </template>
    </Nr1Layout>
</template>
