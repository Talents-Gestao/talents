<script setup>
import { ref } from 'vue';
import CampaignStatus from './CampaignStatus.vue';
import EmptyState from './EmptyState.vue';
import LegacyNotice from './LegacyNotice.vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Campanha NR-1 no modelo anterior, no mesmo desenho da V11: questionário, link com QR, participação (com os elegíveis do
 * cadastro técnico, quando houver) e etapas. Sem orientação nem treinamentos por público (não existem neste modelo).
 */
defineProps({
    campaign: { type: Object, required: true },
    notice: { type: String, default: 'Pesquisa no modelo anterior' },
});

const { push } = useNr1Toast();
const copied = ref(false);
const copy = async (url) => {
    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        push('Link copiado para a área de transferência.');
    } catch {
        push('Não foi possível copiar. Selecione o link e copie manualmente.', 'warn');
    }
};
</script>

<template>
    <div>
        <div class="n1-intro">
            <div>
                <div class="n1-eyebrow">Campanha NR-1</div>
                <h1>Coleta da pesquisa.</h1>
                <p>Período, andamento e participação desta campanha. Orientação e treinamentos por público existem só nas campanhas do questionário de 39 perguntas.</p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                <span class="n1-pill" :class="campaign.open ? 'ok' : 'gray'">{{ campaign.label }}</span>
                <LegacyNotice :text="notice" />
            </div>
        </div>

        <div class="n1-g12">
            <div class="n1-card c7">
                <h2>Questionário psicossocial</h2>
                <div class="n1-g12">
                    <div class="c6 n1-smallbox"><div class="n1-cap">Questões</div><div class="n1-big">{{ campaign.questionnaire.questions }}</div></div>
                    <div class="c6 n1-smallbox"><div class="n1-cap">Dimensões</div><div class="n1-big">{{ campaign.questionnaire.dimensions }}</div></div>
                </div>
                <div class="n1-divider"></div>
                <template v-if="campaign.publicUrl">
                    <label class="n1-label" for="legacy-link">Link da campanha</label>
                    <div class="n1-fields">
                        <input id="legacy-link" type="text" readonly :value="campaign.publicUrl" class="n1-field" aria-label="Link da campanha" />
                        <Nr1Button variant="primary" block @click="copy(campaign.publicUrl)">{{ copied ? 'Link copiado' : 'Copiar link da pesquisa' }}</Nr1Button>
                    </div>
                    <div v-if="campaign.publicQr" class="n1-qr">
                        <img :src="campaign.publicQr" alt="QR Code do link da campanha" width="112" height="112" />
                        <a :href="campaign.publicQr" download="qr-campanha.svg" class="n1-tiny">Baixar QR Code</a>
                    </div>
                </template>
                <EmptyState v-else title="Link indisponível no momento" text="O link fica disponível enquanto a campanha estiver aberta." />
            </div>

            <div class="n1-card c5">
                <h2>Participação na pesquisa</h2>
                <template v-if="campaign.participation">
                    <div class="n1-big">{{ campaign.participation.percent_label ?? campaign.participation.completed }} <small v-if="!campaign.participation.percent_label">respostas</small></div>
                    <div v-if="campaign.participation.percent !== null" class="n1-progress"><span :style="{ width: Math.min(100, campaign.participation.percent) + '%' }"></span></div>
                    <p class="n1-tiny" style="margin-top: 8px">
                        {{ campaign.participation.completed }} respostas
                        <template v-if="campaign.participation.eligible">de <b>{{ campaign.participation.eligible }}</b> colaboradores elegíveis</template>
                        <template v-else> · elegíveis não informados no cadastro técnico</template>
                    </p>
                </template>
                <p v-else class="n1-tiny">{{ campaign.insufficientMessage }}</p>
                <p v-if="campaign.registrationUrl" class="n1-tiny no-print" style="margin: 10px 0">
                    <a :href="campaign.registrationUrl">Editar elegíveis no cadastro técnico</a>
                </p>
                <span class="n1-pill" :class="campaign.open ? 'ok' : 'gray'">{{ campaign.open ? 'Coleta em andamento' : 'Coleta finalizada' }}</span>
            </div>

            <CampaignStatus :facts="campaign.facts" />
        </div>
    </div>
</template>
