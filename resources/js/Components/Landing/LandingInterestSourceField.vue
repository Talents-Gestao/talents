<script setup>
import { LANDING_INTEREST_SOURCE_OPTIONS } from '@/utils/landingInterestSources';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: 'interest-source',
    },
    error: {
        type: String,
        default: '',
    },
    /** Admin: opção vazia “Selecione…”; público: sem placeholder e valor pré-selecionado no form. */
    emptyOption: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-slate-700" :for="id">Origem do contato</label>
        <select
            :id="id"
            class="field-input"
            required
            :value="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-if="emptyOption" value="" disabled>Selecione a origem</option>
            <option
                v-for="opt in LANDING_INTEREST_SOURCE_OPTIONS"
                :key="opt.value"
                :value="opt.value"
            >
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
