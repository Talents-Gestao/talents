<script setup>
import { onMounted, ref } from 'vue';

const emit = defineEmits(['update:modelValue']);

const props = defineProps({
    modelValue: { type: String, default: '' },
    height: { type: Number, default: 160 },
});

const canvasRef = ref(null);
let drawing = false;

const getPoint = (e) => {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
        x: clientX - rect.left,
        y: clientY - rect.top,
    };
};

const start = (e) => {
    drawing = true;
    const ctx = canvasRef.value.getContext('2d');
    const { x, y } = getPoint(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
    e.preventDefault();
};

const draw = (e) => {
    if (!drawing) return;
    const ctx = canvasRef.value.getContext('2d');
    const { x, y } = getPoint(e);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1e293b';
    ctx.lineTo(x, y);
    ctx.stroke();
    e.preventDefault();
};

const stop = () => {
    if (!drawing) return;
    drawing = false;
    emit('update:modelValue', canvasRef.value.toDataURL('image/png'));
};

const clear = () => {
    const canvas = canvasRef.value;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    emit('update:modelValue', '');
};

onMounted(() => {
    const canvas = canvasRef.value;
    canvas.width = canvas.offsetWidth;
    canvas.height = props.height;
});
</script>

<template>
    <div>
        <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-white">
            <canvas
                ref="canvasRef"
                class="w-full cursor-crosshair touch-none"
                :style="{ height: height + 'px' }"
                @mousedown="start"
                @mousemove="draw"
                @mouseup="stop"
                @mouseleave="stop"
                @touchstart="start"
                @touchmove="draw"
                @touchend="stop"
            />
            <p
                v-if="!modelValue"
                class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs text-slate-400"
            >
                Assine aqui
            </p>
        </div>
        <button
            type="button"
            class="mt-2 text-sm font-medium text-talents-700 transition hover:text-talents-900"
            @click="clear"
        >
            Limpar assinatura
        </button>
    </div>
</template>
