<script setup>
/**
 * Textarea que cresce sozinho com o conteúdo (ao digitar, ao carregar texto existente e ao mudar a largura),
 * com mínimo de linhas, máximo de altura (vh) e alça vertical manual. Regras em utils/autoGrowTextarea.js.
 * Atributos (id, class, placeholder, disabled…) vão direto ao <textarea>.
 */
import { applyAutoGrow } from '@/utils/autoGrowTextarea';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const model = defineModel({ type: [String, null], default: '' });

const props = defineProps({
    /** Altura mínima em linhas. */
    minRows: { type: Number, default: 8 },
    /** Altura máxima em % da altura da janela; acima disso aparece a rolagem interna. */
    maxVh: { type: Number, default: 60 },
});

const el = ref(null);
let userHeight = 0;
let lastApplied = 0;
let lastWidth = 0;
let observer = null;

function resize() {
    if (!el.value || typeof window === 'undefined') return;
    lastApplied = applyAutoGrow(el.value, { minRows: props.minRows, maxVh: props.maxVh, userHeight });
}

function onResize() {
    const node = el.value;
    if (!node) return;
    const width = node.offsetWidth;
    const height = node.offsetHeight;

    if (width !== lastWidth) {
        // Largura mudou (janela, painel): o texto reflui, então mede de novo.
        lastWidth = width;
        resize();
    } else if (Math.abs(height - lastApplied) > 1) {
        // Altura mudou sem ter sido aplicada por nós: arraste da alça. Vira o novo piso.
        userHeight = height;
        resize();
    }
}

onMounted(() => {
    lastWidth = el.value?.offsetWidth ?? 0;
    resize();
    if (typeof ResizeObserver !== 'undefined' && el.value) {
        observer = new ResizeObserver(onResize);
        observer.observe(el.value);
    }
    // Fontes da web podem mudar a altura das linhas depois do primeiro paint.
    document.fonts?.ready?.then(resize);
});

onBeforeUnmount(() => observer?.disconnect());

// Texto trocado por fora (carregar, limpar, desfazer) também ajusta.
watch(model, () => nextTick(resize));
watch(() => [props.minRows, props.maxVh], () => nextTick(resize));
</script>

<template>
    <textarea ref="el" v-model="model" :rows="minRows" class="resize-y" @input="resize" />
</template>
