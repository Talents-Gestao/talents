<script setup>
import { CheckIcon } from '@heroicons/vue/24/solid';
import { computed } from 'vue';

/**
 * Fluxo de revisão em 4 passos: Em edição → Em revisão PASSEG → Alteração solicitada | Aprovado pela PASSEG → Publicado.
 * A etapa atual vai destacada; as concluídas ganham o ícone de check; sem revisão PASSEG exigida, os dois passos do meio
 * aparecem como «Não exigida». O estado também vai em texto (aria-current e legenda).
 */
const props = defineProps({
    status: { type: String, required: true },
    requiresPassegReview: { type: Boolean, default: true },
});

const ORDER = { draft: 0, in_review: 1, changes_requested: 2, approved: 2, published: 3 };
const current = computed(() => ORDER[props.status] ?? 0);

const steps = computed(() => {
    const result = props.status === 'changes_requested' ? 'Alteração solicitada' : props.status === 'approved' ? 'Aprovado pela PASSEG' : 'Aprovado ou alteração';
    const skipped = !props.requiresPassegReview;

    return [
        { key: 'draft', label: 'Em edição', hint: 'Talents' },
        { key: 'in_review', label: 'Em revisão PASSEG', hint: skipped ? 'Não exigida' : 'Revisão documental', skipped },
        { key: 'result', label: result, hint: skipped ? 'Não exigida' : 'Decisão da PASSEG', skipped, warn: props.status === 'changes_requested' },
        { key: 'published', label: 'Publicado', hint: 'Disponível para a empresa' },
    ];
});

const stateOf = (index) => (index < current.value ? 'done' : index === current.value ? 'current' : 'todo');
</script>

<template>
    <ol class="n1-stepper" aria-label="Fluxo de revisão do relatório">
        <li
            v-for="(step, index) in steps"
            :key="step.key"
            :class="[stateOf(index), { skipped: step.skipped, warn: step.warn && stateOf(index) === 'current' }]"
            :aria-current="stateOf(index) === 'current' ? 'step' : undefined"
        >
            <span class="n1-step-dot" aria-hidden="true">
                <CheckIcon v-if="stateOf(index) === 'done' && !step.skipped" class="n1-step-check" />
                <template v-else>{{ index + 1 }}</template>
            </span>
            <span class="n1-step-text">
                <b>{{ step.label }}</b>
                <small>{{ step.hint }}</small>
            </span>
        </li>
    </ol>
</template>
