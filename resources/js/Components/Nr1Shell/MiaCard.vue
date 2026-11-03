<script setup>
import { router } from '@inertiajs/vue3';
import { marked } from 'marked';
import { computed, onUnmounted, ref, watch } from 'vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Mia em Resultados da pesquisa: análise a partir dos dados agregados da campanha. Só apresentação: pedir a análise posta
 * para a rota de sempre (permissão, limite por hora e fila) e, enquanto ela está na fila, a seção é recarregada.
 */
const props = defineProps({
    ai: { type: Object, required: true },
});

const { push } = useNr1Toast();
const requesting = ref(false);
const failed = ref(false);

const html = computed(() => {
    try {
        return marked.parse(props.ai.content ?? '', { breaks: true, gfm: true });
    } catch {
        return '';
    }
});

const request = () => {
    failed.value = false;
    router.post(props.ai.postUrl, {}, {
        preserveScroll: true,
        onStart: () => { requesting.value = true; },
        onFinish: () => { requesting.value = false; },
        onSuccess: () => push('Análise solicitada à Mia.'),
        onError: () => { failed.value = true; },
    });
};

let poll = null;
const stopPoll = () => {
    clearInterval(poll);
    poll = null;
};
watch(
    () => props.ai.pending,
    (pending) => {
        stopPoll();
        if (pending) poll = setInterval(() => router.reload({ only: ['section'] }), 5000);
    },
    { immediate: true },
);
onUnmounted(stopPoll);
</script>

<template>
    <section class="n1-card no-print" aria-labelledby="mia-title" style="margin-top: 17px">
        <div class="n1-sectiontop">
            <h2 id="mia-title">Mia · Assistente NR-1</h2>
            <Nr1Button v-if="ai.canRequest" size="sm" variant="primary" :loading="requesting" :disabled="ai.pending" @click="request">
                {{ ai.content ? 'Pedir nova análise' : 'Perguntar à Mia' }}
            </Nr1Button>
        </div>
        <p class="n1-tiny">
            A Mia avalia o cenário a partir dos dados agregados desta pesquisa. Não substitui parecer com especialista nem a avaliação por profissionais habilitados.
        </p>
        <p v-if="failed" class="n1-notice warn" role="alert">Não foi possível pedir a análise agora. Tente de novo em instantes.</p>
        <p v-if="ai.pending" class="n1-notice" role="status" aria-live="polite">Mia está analisando os resultados…</p>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-else-if="ai.content" class="n1-mia-text" v-html="html"></div>
        <p v-else class="n1-tiny" style="margin-top: 10px">Nenhuma análise ainda.</p>
    </section>
</template>
