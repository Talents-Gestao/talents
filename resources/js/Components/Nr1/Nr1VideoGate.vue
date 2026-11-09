<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, required: true },
    title: { type: String, default: 'Vídeo obrigatório' },
    storageKey: { type: String, default: '' },
    threshold: { type: Number, default: 0.95 },
});

const emit = defineEmits(['completed']);

const completed = ref(false);
const videoEl = ref(null);

const markCompleted = () => {
    if (completed.value) return;
    completed.value = true;
    if (props.storageKey) {
        try {
            window.localStorage.setItem(props.storageKey, '1');
        } catch {
            // sem armazenamento: o gate vale só nesta sessão
        }
    }
    emit('completed');
};

const onTimeUpdate = () => {
    const el = videoEl.value;
    if (!el || !el.duration || Number.isNaN(el.duration)) return;
    if (el.currentTime / el.duration >= props.threshold) {
        markCompleted();
    }
};

onMounted(() => {
    if (props.storageKey) {
        try {
            if (window.localStorage.getItem(props.storageKey) === '1') {
                completed.value = true;
                emit('completed');
            }
        } catch {
            // segue sem persistência
        }
    }
});

watch(
    () => props.src,
    () => {
        completed.value = false;
    },
);

defineExpose({ completed });
</script>

<template>
    <div class="space-y-2">
        <p class="text-sm font-semibold text-slate-900">{{ title }}</p>
        <p class="text-xs text-slate-600">Assista até o fim para continuar. O avanço só é liberado após a conclusão do vídeo.</p>
        <video
            ref="videoEl"
            :src="src"
            controls
            playsinline
            preload="metadata"
            class="w-full rounded-lg border border-slate-200 bg-black"
            @timeupdate="onTimeUpdate"
            @ended="markCompleted"
        >
            Seu navegador não reproduz este vídeo.
        </video>
        <p v-if="completed" class="text-xs font-medium text-emerald-700">Vídeo concluído. Você pode avançar.</p>
        <p v-else class="text-xs font-medium text-amber-700">Aguarde o vídeo terminar para liberar a próxima etapa.</p>
    </div>
</template>
