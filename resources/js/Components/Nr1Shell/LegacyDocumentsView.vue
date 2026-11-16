<script setup>
import ClientActionPlanBody from '@/Components/Nr1Legacy/ClientActionPlanBody.vue';
import LaudoBody from '@/Components/Nr1Legacy/LaudoBody.vue';
import { computed } from 'vue';
import Nr1HelpTip from './Nr1HelpTip.vue';

/**
 * Parecer e publicação (modelo anterior): o texto, o plano e a publicação ficam na mesma tela.
 * O cadastro técnico abre pelo fluxo de publicação (prévia do Inventário de Riscos Psicossocial).
 * O resumo executivo fica inativo na UI do parecer legado.
 */
const props = defineProps({
    documents: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
});

const laudo = computed(() => props.documents.laudo ?? null);
</script>

<template>
    <div>
        <div class="n1-intro">
            <div v-if="laudo">
                <div class="n1-eyebrow">Parecer e publicação</div>
                <h1>Parecer técnico e publicação.<Nr1HelpTip label="o parecer e a publicação" text="Parecer técnico com a Mia e Inventário de Riscos Psicossocial. A publicação do parecer exige o inventário psicossocial no painel da empresa." /></h1>
            </div>
            <div v-else>
                <div class="n1-eyebrow">Parecer</div>
                <h1>Parecer da sua empresa.<Nr1HelpTip label="o parecer" text="O parecer técnico e os documentos publicados pela Talents aparecem aqui, para consulta da sua empresa." /></h1>
            </div>
        </div>

        <template v-if="laudo">
            <div class="nr1-theme">
                <LaudoBody v-bind="laudo" shell-mode />
            </div>
        </template>

        <template v-else>
            <div class="nr1-theme">
                <ClientActionPlanBody v-bind="documents.actionPlan" />
            </div>
        </template>
    </div>
</template>
