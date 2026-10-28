<script setup>
import Nr1Button from './Nr1Button.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    journey: { type: Object, required: true },
    mode: { type: String, default: 'minha-jornada' },
});

const ICON = { online: '▣', presencial: '♧' };
const content = ref(null);

const audience = computed(() => props.journey.audience_label);
const audienceBadge = computed(() => (audience.value ? `Trilha para ${audience.value === 'Líderes' ? 'Líderes' : 'o Time'}` : null));

const showOrientation = () => {
    content.value = { title: props.journey.orientation.title, paragraphs: props.journey.orientation.paragraphs };
};
const showTraining = (training) => {
    const lines = [training.scope];
    if (training.period) lines.push(`Período: ${training.period}`);
    if (training.location) lines.push(`Local: ${training.location}`);
    if (training.event_at) lines.push(`Data e hora: ${training.event_at}`);
    if (training.facilitator) lines.push(`Facilitador: ${training.facilitator}`);
    content.value = { title: `${training.label}${audience.value ? ' · ' + audience.value : ''}`, paragraphs: lines.filter(Boolean), training };
};
</script>

<template>
    <div>
        <div v-if="mode === 'minha-jornada'" class="n1-view-intro">
            <div class="n1-eyebrow">Talents • Sua participação faz diferença</div>
            <h1>Bem-vindo à Campanha NR-1</h1>
            <p>Conheça as orientações da pesquisa, acesse seus treinamentos e responda com tranquilidade. Sua participação ajuda a compreender as condições de trabalho da equipe.</p>
            <span v-if="audienceBadge" class="n1-pill" style="background: #ffffff25; color: #fff">{{ audienceBadge }}</span>
        </div>
        <div v-else class="n1-intro">
            <div>
                <div class="n1-eyebrow">Campanha NR-1</div>
                <h1>Prepare-se para participar.</h1>
                <p>Orientações e treinamentos disponíveis para o seu grupo nesta campanha.</p>
            </div>
            <span v-if="audienceBadge" class="n1-pill ok">{{ audienceBadge }}</span>
        </div>

        <div class="n1-banner" style="margin: 17px 0">
            As respostas são tratadas de forma coletiva e os relatórios gerenciais não mostram respostas individuais. Responda de acordo com sua experiência no trabalho.
        </div>

        <div class="n1-g12">
            <div class="c6">
                <div class="n1-emp-card">
                    <div class="n1-row" style="justify-content: flex-start">
                        <span class="n1-emp-step" aria-hidden="true">▶</span>
                        <div>
                            <h2>Orientação NR-1</h2>
                            <span class="n1-pill ok">Disponível para todos</span>
                        </div>
                    </div>
                    <p>Entenda o objetivo da pesquisa, o sigilo das respostas e como preencher.</p>
                    <Nr1Button variant="primary" type="button" @click="showOrientation">Ver orientações</Nr1Button>
                </div>
            </div>

            <div v-for="training in journey.trainings" :key="training.id" class="c6">
                <div class="n1-emp-card">
                    <div class="n1-row" style="justify-content: flex-start">
                        <span class="n1-emp-step" aria-hidden="true">{{ ICON[training.modality] }}</span>
                        <div>
                            <h2>{{ training.label }}<template v-if="audience"> · {{ audience }}</template></h2>
                            <span class="n1-pill ok">Disponível na sua campanha</span>
                        </div>
                    </div>
                    <p>{{ training.scope }}</p>
                    <div class="n1-tools">
                        <Nr1Button variant="secondary" external v-if="training.link_url" :href="training.link_url">Acessar treinamento</Nr1Button>
                        <Nr1Button variant="secondary" v-if="training.material_url" :href="training.material_url">Baixar material</Nr1Button>
                        <Nr1Button variant="secondary" type="button" @click="showTraining(training)">Ver informações</Nr1Button>
                    </div>
                </div>
            </div>

            <div v-if="!journey.trainings.length" class="c6">
                <div class="n1-emp-card" style="border-style: dashed; background: #faf8fc">
                    <div class="n1-row" style="justify-content: flex-start">
                        <span class="n1-emp-step" aria-hidden="true">♡</span>
                        <div>
                            <h2>Preparação para a pesquisa</h2>
                            <span class="n1-pill gray">Tudo pronto</span>
                        </div>
                    </div>
                    <p>Assista à orientação e siga para o questionário quando estiver preparado.</p>
                </div>
            </div>
        </div>

        <div v-if="content" class="n1-card" style="margin-top: 16px" role="region" aria-live="polite" aria-label="Material">
            <div class="n1-row">
                <h2>{{ content.title }}</h2>
                <Nr1Button variant="secondary" size="sm" type="button" @click="content = null">Fechar</Nr1Button>
            </div>
            <p v-for="line in content.paragraphs" :key="line" class="n1-bodycopy">{{ line }}</p>
        </div>

        <div class="n1-card" style="margin-top: 16px">
            <div class="n1-row">
                <div>
                    <h2 style="margin: 0 0 5px">Pesquisa sobre o trabalho</h2>
                    <p class="n1-tiny" style="margin: 0">{{ journey.questionnaire.questions }} afirmações • {{ journey.questionnaire.dimensions }} dimensões • Escala de frequência</p>
                </div>
                <span class="n1-pill ok">Disponível</span>
            </div>
            <div class="n1-divider"></div>
            <p class="n1-bodycopy">Não existem respostas certas ou erradas. Considere sua rotina de trabalho ao responder cada afirmação.</p>
            <Nr1Button variant="primary" :href="journey.surveyUrl">Responder questionário →</Nr1Button>
        </div>
    </div>
</template>
