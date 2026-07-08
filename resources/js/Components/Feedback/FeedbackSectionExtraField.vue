<script setup>
import { feedbackFieldClass } from '@/utils/feedbackStatus';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ question: '', answer: '' }),
    },
});

const emit = defineEmits(['update:modelValue']);

const update = (field, value) => {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
};
</script>

<template>
    <div class="rounded-xl border border-dashed border-talents-200 bg-talents-50/30 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Pergunta extra</p>
        <p class="mt-1 text-xs text-slate-500">Opcional — adicione uma pergunta personalizada neste tópico.</p>

        <label class="mt-3 block text-sm font-medium text-slate-800">Pergunta</label>
        <input
            type="text"
            :class="feedbackFieldClass"
            :value="modelValue.question ?? ''"
            placeholder="Ex.: O que mais te motivou neste período?"
            @input="update('question', $event.target.value)"
        />

        <label class="mt-3 block text-sm font-medium text-slate-800">Resposta</label>
        <textarea
            rows="3"
            :class="feedbackFieldClass"
            :value="modelValue.answer ?? ''"
            placeholder="Resposta à pergunta extra"
            @input="update('answer', $event.target.value)"
        />
    </div>
</template>
