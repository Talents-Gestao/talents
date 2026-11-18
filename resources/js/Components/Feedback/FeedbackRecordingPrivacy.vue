<script setup>
import FeedbackDisclosure from '@/Components/Feedback/FeedbackDisclosure.vue';
import { LockClosedIcon } from '@heroicons/vue/24/outline';

/** Transparência da gravação (texto de LGPD da fatia 1): uma linha sempre visível e os detalhes recolhidos. */
</script>

<template>
    <FeedbackDisclosure
        label="Guardamos só o texto da transcrição, criptografado; o arquivo é apagado logo após a leitura."
        hint="Saiba mais: o que é guardado, quem vê e como excluir"
        data-feedback-recording-privacy
    >
        <template #icon><LockClosedIcon class="h-5 w-5 shrink-0 text-slate-600" aria-hidden="true" /></template>
        <ul class="list-disc space-y-1.5 pl-5 leading-relaxed">
            <li>Guardado: o texto extraído (criptografado), o hash e o tamanho do arquivo, quem enviou e quem confirmou o consentimento.</li>
            <li>Texto original: só administradores da empresa e a Talents veem; cada abertura fica registrada.</li>
            <li>Líder do feedback: vê a transcrição limpa e o relatório gerados por IA, não o original. O colaborador não vê nada disso.</li>
            <li>IA: só depois que a Talents aprova o provedor; a tela mostra quem recebe o texto. «Reflexão do líder» nunca é enviada.</li>
            <li>Excluir o feedback, o modelo ou a gravação exclui também o texto guardado.</li>
        </ul>
    </FeedbackDisclosure>
</template>
