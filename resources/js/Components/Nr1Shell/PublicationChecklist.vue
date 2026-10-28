<script setup>
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

/** Checklist de publicação: o que ainda bloqueia (campo, problema e onde corrigir) ou «sem pendências». Só leitura. */
defineProps({
    pending: { type: Array, required: true },
    registrationUrl: { type: String, default: null },
});
</script>

<template>
    <div class="n1-checklist" :class="pending.length ? 'blocked' : 'clear'" role="status">
        <template v-if="pending.length">
            <div class="n1-checklist-head"><ExclamationTriangleIcon class="n1-checklist-icon" aria-hidden="true" /><b>Pendências que bloqueiam o envio e a publicação</b></div>
            <ul>
                <li v-for="item in pending" :key="item.field"><b>{{ item.label }}</b>: {{ item.problem }}{{ item.where ? ` (${item.where})` : '' }}</li>
            </ul>
            <a v-if="registrationUrl" :href="registrationUrl" class="n1-linkbtn">Abrir o cadastro técnico →</a>
        </template>
        <div v-else class="n1-checklist-head"><CheckCircleIcon class="n1-checklist-icon" aria-hidden="true" /><b>Sem pendências nos documentos gerados.</b></div>
    </div>
</template>
