<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
});

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

// O cabeçalho é fixo (sticky): publica a altura em --n1-sticky-offset no .nr1-shell, para barras fixas abaixo
// dele (ex.: «Ir para» dos resultados) não ficarem cobertas, e para as âncoras pararem logo abaixo.
const headerEl = ref(null);
let observer = null;
const publishOffset = () => {
    const el = headerEl.value;
    const shell = el?.closest('.nr1-shell');
    if (!el || !shell) return;
    shell.style.setProperty('--n1-sticky-offset', `${Math.ceil(el.getBoundingClientRect().height) + 8}px`);
};
onMounted(() => {
    publishOffset();
    if (typeof ResizeObserver !== 'undefined' && headerEl.value) {
        observer = new ResizeObserver(publishOffset);
        observer.observe(headerEl.value);
    }
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <header ref="headerEl" class="n1-header">
        <div class="n1-header-main">
            <CompanyAvatar v-if="!context.collaborator && context.company" :name="context.company.name" :logo-url="context.company.logo_url" />
            <CompanyAvatar v-else name="NR" />
            <div class="n1-header-titles">
                <div class="n1-header-eyebrow">NR-1 · Pesquisa psicossocial</div>
                <div class="n1-header-title" :title="title">{{ title }}</div>
                <div v-if="contextLine" class="n1-header-context" :title="contextLine">{{ contextLine }}</div>
            </div>
        </div>

        <div class="n1-header-controls" role="group" aria-label="Contexto da campanha">
            <Nr1Button v-if="shell.backUrl" variant="ghost-on-light" size="sm" :href="shell.backUrl">{{ shell.backLabel ?? '← Voltar ao Talents' }}</Nr1Button>
            <CampaignSelect v-if="context.survey && !context.collaborator" :context="context" :shell="shell" />
            <StatusChip v-if="context.survey?.statusBadge" :label="context.survey.statusBadge.label" :tone="context.survey.statusBadge.tone" />
            <Nr1Button v-if="canPrint" variant="ghost-on-light" size="sm" class="no-print" @click="print">Imprimir ↓</Nr1Button>
        </div>
    </header>
</template>
