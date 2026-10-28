<script setup>
import { computed } from 'vue';
import CampaignSelect from './CampaignSelect.vue';
import CompanyAvatar from './CompanyAvatar.vue';
import Nr1Button from './Nr1Button.vue';
import StatusChip from './StatusChip.vue';

/**
 * Cabeçalho do shell NR-1: monograma + «NR-1 · PESQUISA PSICOSSOCIAL», nome da empresa em destaque, linha de contexto,
 * e à direita voltar, seletor de campanha, estado e ações contextuais. O colaborador não vê o nome da empresa.
 */
const props = defineProps({
    shell: { type: Object, required: true },
    context: { type: Object, required: true },
    menuOpen: { type: Boolean, default: false },
});
defineEmits(['toggle-menu']);

const PRINTABLE = ['visao-geral', 'resultados', 'plano-de-acao', 'documentos'];
const canPrint = computed(() => !props.context.collaborator && PRINTABLE.includes(props.shell.current));
const print = () => window.print();

const contextLine = computed(() => {
    const survey = props.context.survey;
    if (props.context.collaborator) return 'Sua campanha de participação';
    const parts = [];
    if (survey) {
        if (survey.insufficientSample) parts.push('Amostra insuficiente');
        else if (survey.respondents !== null && survey.respondents !== undefined) parts.push(`${survey.respondents} respostas`);
        parts.push(survey.title);
    }

    return parts.join(' · ');
});
const title = computed(() => (props.context.collaborator ? 'Campanha NR-1' : (props.context.company?.name ?? 'NR-1')));
</script>

<template>
    <header class="n1-header">
        <div class="n1-header-main">
            <Nr1Button variant="secondary" size="sm" class="n1-menu-toggle" type="button" :aria-expanded="menuOpen" aria-controls="nr1-menu" aria-label="Abrir o menu do NR-1" @click="$emit('toggle-menu')">
                ☰
            </Nr1Button>
            <CompanyAvatar v-if="!context.collaborator && context.company" :name="context.company.name" :logo-url="context.company.logo_url" />
            <CompanyAvatar v-else name="NR" />
            <div class="n1-header-titles">
                <div class="n1-header-eyebrow">NR-1 · Pesquisa psicossocial</div>
                <h1 class="n1-header-title" :title="title">{{ title }}</h1>
                <div v-if="contextLine" class="n1-header-context" :title="contextLine">{{ contextLine }}</div>
            </div>
        </div>

        <div class="n1-header-controls" role="group" aria-label="Contexto da campanha">
            <Nr1Button v-if="shell.backUrl" variant="ghost-on-light" size="sm" :href="shell.backUrl">{{ shell.backLabel ?? '← Voltar ao Talents' }}</Nr1Button>
            <CampaignSelect v-if="context.survey && !context.collaborator" :context="context" />
            <StatusChip v-if="context.survey?.statusBadge" :label="context.survey.statusBadge.label" :tone="context.survey.statusBadge.tone" />
            <Nr1Button v-if="canPrint" variant="ghost-on-light" size="sm" class="no-print" @click="print">Imprimir ↓</Nr1Button>
        </div>
    </header>
</template>
