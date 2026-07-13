<script setup>
defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="mt-3 border-t border-talents-100 pt-3">
        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Observação</label>
        <textarea
            :value="modelValue"
            rows="2"
            maxlength="2000"
            placeholder="Texto opcional exibido no PDF deste produto…"
            class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <p class="mt-1 text-[11px] text-slate-400">
            Aparece no PDF abaixo dos detalhes do produto, quando preenchida.
        </p>
    </div>
</template>
