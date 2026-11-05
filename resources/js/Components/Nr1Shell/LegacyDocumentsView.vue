<script setup>
import ClientActionPlanBody from '@/Components/Nr1Legacy/ClientActionPlanBody.vue';
import LaudoBody from '@/Components/Nr1Legacy/LaudoBody.vue';
import { computed } from 'vue';
import LegacyNotice from './LegacyNotice.vue';
import Nr1Button from './Nr1Button.vue';

/**
 * Parecer e publicação (modelo anterior): Laudo 02 Parecer + 03 Publicar.
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
            <div>
                <div class="n1-eyebrow">Parecer e publicação</div>
                <h1>Parecer técnico e publicação.</h1>
                <p>
                    Parecer técnico com a Mia, relatório técnico + plano de ação e subsídio AEP/PGR. A publicação do
                    parecer exige o relatório técnico validado pela Talents.
                </p>
            </div>
            <LegacyNotice :text="notice" />
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

            <div v-if="documents.reports.length" class="n1-card no-print" style="margin-top: 17px">
                <div class="n1-sectiontop"><h2>Documentos liberados</h2></div>
                <p class="n1-tiny">
                    Relatórios automáticos desta campanha. O parecer técnico aparece acima quando a Talents publica.
                </p>
                <div class="n1-tools">
                    <Nr1Button
                        v-for="report in documents.reports"
                        :key="report.href"
                        size="sm"
                        :href="report.href"
                        external
                    >
                        {{ report.label }}
                    </Nr1Button>
                </div>
            </div>
        </template>
    </div>
</template>
