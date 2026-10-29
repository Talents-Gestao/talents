<script setup>
import { CheckIcon } from '@heroicons/vue/24/solid';

/**
 * Etapas e configuração da campanha (V11 e modelo anterior), lidas do que está gravado: Configuração → Coleta →
 * Resultados → Publicação, período, grupo mínimo de respostas e situação da publicação. Só leitura.
 */
defineProps({
    facts: { type: Object, required: true },
});

const HINT = { done: 'Concluída', current: 'Em andamento', todo: 'A seguir', na: 'Não exigida' };
</script>

<template>
    <section class="n1-card c12" aria-labelledby="campaign-steps-title">
        <div class="n1-sectiontop">
            <h2 id="campaign-steps-title">Etapas da campanha</h2>
            <span class="n1-pill" :class="facts.publication.published ? 'ok' : 'gray'">{{ facts.publication.label }}</span>
        </div>
        <ol class="n1-stepper" aria-label="Etapas da campanha">
            <li
                v-for="(step, index) in facts.steps"
                :key="step.key"
                :class="[step.state === 'na' ? 'todo skipped' : step.state]"
                :aria-current="step.state === 'current' ? 'step' : undefined"
            >
                <span class="n1-step-dot" aria-hidden="true">
                    <CheckIcon v-if="step.state === 'done'" class="n1-step-check" />
                    <template v-else>{{ index + 1 }}</template>
                </span>
                <span class="n1-step-text">
                    <b>{{ step.label }}</b>
                    <small>{{ HINT[step.state] }}</small>
                </span>
            </li>
        </ol>
        <dl class="n1-laudo-grid" style="margin-top: 14px">
            <div><dt class="n1-cap">Período</dt><dd>{{ facts.period.starts ?? '—' }} a {{ facts.period.ends ?? '—' }}</dd></div>
            <div><dt class="n1-cap">Mínimo de respostas por grupo</dt><dd>{{ facts.minGroup }}</dd></div>
            <div><dt class="n1-cap">Publicação</dt><dd>{{ facts.publication.at ?? (facts.publication.required ? 'Ainda não publicada' : 'Não exigida') }}</dd></div>
        </dl>
        <p class="n1-tiny" style="margin-top: 10px">Abaixo do mínimo, a empresa não vê número nem percentual de participação, e grupos pequenos entram em «Outros Setores».</p>
    </section>
</template>
