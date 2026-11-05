<script setup>
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

const props = defineProps({
    suggestions: {
        type: Object,
        default: () => ({ generated_at: null, pending: false, can_moderate: false, release_url: null, items: [] }),
    },
    canRequest: { type: Boolean, default: false },
});

const { push } = useNr1Toast();
const drafts = reactive({});

const textOf = (item) => drafts[item.id] ?? item.rationale;

const save = (item) => {
    router.patch(item.update_url, { rationale: textOf(item) }, {
        preserveScroll: true,
        onSuccess: () => push('Texto da sugestão atualizado.'),
    });
};

const dismiss = (item) => {
    router.post(item.dismiss_url, {}, {
        preserveScroll: true,
        onSuccess: () => push('Sugestão dispensada.'),
    });
};

const release = () => {
    if (!props.suggestions.release_url) {
        return;
    }
    router.post(props.suggestions.release_url, {}, {
        preserveScroll: true,
        onSuccess: () => push('Serviços liberados para a empresa.'),
    });
};

const request = (item) => {
    router.post(item.request_url, {}, {
        preserveScroll: true,
        onSuccess: () => push('Conversa solicitada. A Talents entrará em contato.'),
    });
};
</script>

<template>
    <section class="n1-card c12" aria-labelledby="catalog-suggestions-title">
        <div class="n1-sectiontop">
            <h2 id="catalog-suggestions-title">Serviços que a empresa pode contratar</h2>
            <Nr1Button
                v-if="suggestions.can_moderate && suggestions.items.some((item) => item.status === 'pending')"
                size="sm"
                variant="primary"
                @click="release"
            >
                Liberar para a empresa
            </Nr1Button>
        </div>
        <p class="n1-tiny">
            A Mia olha o catálogo de produtos e os resultados quando a pesquisa encerra. A Talents revisa o texto e só então a empresa vê a sugestão.
            <span v-if="suggestions.generated_at"> Gerado em {{ suggestions.generated_at }}.</span>
        </p>
        <p v-if="suggestions.pending" class="n1-notice" role="status" style="margin-top: 10px">Geração em andamento.</p>
        <p v-else-if="!suggestions.items.length" class="n1-tiny" style="margin-top: 10px">
            {{ suggestions.can_moderate ? 'Nenhuma sugestão ainda. Ela aparece depois que a pesquisa encerra.' : 'A Talents ainda não liberou serviços para esta campanha.' }}
        </p>

        <article v-for="item in suggestions.items" :key="item.id" class="n1-offer n1-lift" style="margin-top: 12px">
            <div class="n1-row" style="justify-content: space-between; gap: 8px">
                <h3 style="margin: 0">{{ item.product_name }}</h3>
                <span class="n1-pill" :class="item.status === 'approved' ? 'ok' : 'warn'">{{ item.status_label }}</span>
            </div>
            <textarea
                v-if="suggestions.can_moderate"
                :value="textOf(item)"
                rows="3"
                class="n1-field"
                style="margin-top: 8px"
                :aria-label="`Texto de ${item.product_name}`"
                @input="drafts[item.id] = $event.target.value"
            />
            <p v-else style="margin-top: 8px">{{ item.rationale }}</p>
            <div class="n1-tools" style="margin-top: 8px">
                <Nr1Button v-if="suggestions.can_moderate" size="sm" @click="save(item)">Salvar texto</Nr1Button>
                <Nr1Button v-if="item.dismiss_url" size="sm" @click="dismiss(item)">Dispensar</Nr1Button>
                <span v-if="item.requested" class="n1-pill warn">Conversa solicitada</span>
                <Nr1Button v-else-if="canRequest && item.request_url" size="sm" variant="primary" @click="request(item)">Solicitar conversa →</Nr1Button>
            </div>
        </article>
    </section>
</template>
