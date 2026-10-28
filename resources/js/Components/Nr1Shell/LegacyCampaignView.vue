<script setup>
import { ref } from 'vue';
import EmptyState from './EmptyState.vue';
import LegacyNotice from './LegacyNotice.vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/** Campanha NR-1 no modelo anterior: só o que existe (período, coleta, participação e link). Sem treinamentos nem elegíveis. */
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
                <p>Período, andamento e participação desta campanha no modelo anterior.</p>
            </div>
            <LegacyNotice :text="notice" />
        </div>

        <div class="n1-g12">
            <div class="n1-card c5 n1-lift">
                <h2>Participação na pesquisa</h2>
                <div class="n1-big">{{ campaign.completed }} <small>respostas</small></div>
                <p class="n1-tiny">Período: {{ campaign.period }}</p>
                <span class="n1-pill" :class="campaign.open ? 'ok' : 'gray'">{{ campaign.label }}</span>
            </div>
            <div class="n1-card c7">
                <h2>Link da campanha</h2>
                <template v-if="campaign.publicUrl">
                    <label class="n1-label" for="legacy-link">Link da campanha</label>
                    <div class="n1-fields">
                        <input id="legacy-link" type="text" readonly :value="campaign.publicUrl" class="n1-field" aria-label="Link da campanha" />
                        <Nr1Button variant="primary" block @click="copy(campaign.publicUrl)">{{ copied ? 'Link copiado' : 'Copiar link da pesquisa' }}</Nr1Button>
                    </div>
                </template>
                <EmptyState v-else title="Link indisponível no momento" text="O link fica disponível enquanto a campanha estiver aberta." />
            </div>
        </div>
    </div>
</template>
