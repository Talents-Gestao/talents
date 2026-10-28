<script setup>
import { useForm } from '@inertiajs/vue3';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Revisão interna da Talents (só a consultoria; a empresa e o revisor nunca recebem este bloco). Categorias de
 * verificação, encaminhamento e anotações; salvar passa pelo step-up de MFA da rota existente.
 */
const props = defineProps({
    review: { type: Object, required: true },
});

const { push } = useNr1Toast();
const form = useForm({
    decision: props.review.current?.decision ?? 'documental',
    note: props.review.current?.note ?? '',
    checked_items: [...(props.review.current?.checked_items ?? [])],
});

const submit = () => form.post(props.review.save_url, { preserveScroll: true, onSuccess: () => push('Análise interna salva.') });
</script>

<template>
    <section class="n1-card n1-internal" aria-labelledby="n1-internal-title">
        <div class="n1-sectiontop">
            <h2 id="n1-internal-title">Revisão interna da Talents</h2>
            <span class="n1-pill gray">Somente consultoria</span>
        </div>
        <p class="n1-bodycopy">
            Registre apenas situações que precisem de verificação antes de emitir o relatório. Não gera contratação automática nem substitui a análise profissional.
        </p>

        <form class="n1-g12" @submit.prevent="submit">
            <fieldset class="c6 n1-fieldset">
                <legend class="n1-label">Itens de verificação</legend>
                <label v-for="item in review.items" :key="item.key" class="n1-check n1-mini">
                    <input v-model="form.checked_items" type="checkbox" :value="item.key" />
                    <span><b>{{ item.title }}</b><br />{{ item.hint }}</span>
                </label>
            </fieldset>

            <div class="c6">
                <label class="n1-label" for="triageDecision">Encaminhamento</label>
                <select id="triageDecision" v-model="form.decision" class="n1-field">
                    <option v-for="(label, value) in review.decisions" :key="value" :value="value">{{ label }}</option>
                </select>
                <p v-if="form.errors.decision" class="n1-tiny" style="color: #a6334b">{{ form.errors.decision }}</p>

                <label class="n1-label" for="triageNote" style="margin-top: 12px">Anotações da consultoria</label>
                <textarea id="triageNote" v-model="form.note" rows="4" class="n1-field" placeholder="Registre o motivo e os próximos passos."></textarea>
                <p v-if="form.errors.note" class="n1-tiny" style="color: #a6334b">{{ form.errors.note }}</p>

                <div style="margin-top: 10px">
                    <Nr1Button type="submit" variant="primary" size="sm" :loading="form.processing">Salvar análise interna</Nr1Button>
                </div>
                <p class="n1-tiny" style="margin-top: 8px" role="status">
                    <template v-if="review.current">Última análise: <b>{{ review.current.decision_label }}</b> · {{ review.current.author }} em {{ review.current.at }}.</template>
                    <template v-else>Sem pendências registradas até agora.</template>
                </p>
            </div>
        </form>

        <details v-if="review.history.length > 1" class="n1-history">
            <summary>Histórico de análises ({{ review.history.length }})</summary>
            <ol>
                <li v-for="entry in review.history" :key="entry.id"><b>{{ entry.decision_label }}</b> · {{ entry.author }} · {{ entry.at }}<template v-if="entry.note"> — {{ entry.note }}</template></li>
            </ol>
        </details>
    </section>
</template>
