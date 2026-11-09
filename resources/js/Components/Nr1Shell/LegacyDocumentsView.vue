<script setup>
import ClientActionPlanBody from '@/Components/Nr1Legacy/ClientActionPlanBody.vue';
import LaudoBody from '@/Components/Nr1Legacy/LaudoBody.vue';
import { computed } from 'vue';
import Nr1Button from './Nr1Button.vue';

/**
 * Parecer e publicação (modelo anterior): o texto, o plano e a publicação ficam na mesma tela.
 * A barra acima do parecer só leva ao cadastro técnico. Relatório, subsídio e PDF ficam no próprio parecer.
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
                <h1>Parecer técnico e publicação.</h1>
                <p>
                    Parecer técnico com a Mia e Inventário de Riscos Psicossocial. A publicação do
                    parecer exige o inventário psicossocial no painel da empresa.
                </p>
            </div>
            <div v-else>
                <div class="n1-eyebrow">Parecer</div>
                <h1>Parecer da sua empresa.</h1>
                <p>
                    O parecer técnico e os documentos publicados pela Talents aparecem aqui, para consulta da sua empresa.
                </p>
            </div>
        </div>

        <template v-if="laudo">
            <div class="n1-card n1-legacybar no-print" style="margin-bottom: 15px" role="toolbar" aria-label="Ações do laudo">
                <Nr1Button
                    size="sm"
                    inertia
                    :href="route('admin.companies.surveys.registration.edit', [laudo.company.id, laudo.survey.id])"
                >
                    Cadastro técnico
                </Nr1Button>
            </div>

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
