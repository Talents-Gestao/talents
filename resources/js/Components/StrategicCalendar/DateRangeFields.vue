<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';

const props = defineProps({
    occursOn: { type: String, required: true },
    endsOn: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    disableEndsOn: { type: Boolean, default: false },
    disableRecurrenceHint: { type: Boolean, default: false },
});

const emit = defineEmits(['update:occursOn', 'update:endsOn']);

const endsOnMin = computed(() => props.occursOn || undefined);

const inputClass = computed(() => (
    props.compact
        ? 'mt-1 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm'
        : 'mt-1 block w-full'
));

function updateOccursOn(value) {
    emit('update:occursOn', value);
    if (props.endsOn && value && props.endsOn < value) {
        emit('update:endsOn', value);
    }
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <InputLabel :for="compact ? undefined : 'occurs_on'" value="Data de início" />
            <TextInput
                :id="compact ? undefined : 'occurs_on'"
                :model-value="occursOn"
                type="date"
                :class="inputClass"
                required
                @update:model-value="updateOccursOn"
            />
        </div>
        <div>
            <InputLabel :for="compact ? undefined : 'ends_on'" value="Data de término (opcional)" />
            <TextInput
                :id="compact ? undefined : 'ends_on'"
                :model-value="endsOn"
                type="date"
                :min="endsOnMin"
                :disabled="disableEndsOn"
                :class="[inputClass, disableEndsOn ? 'disabled:bg-slate-100' : '']"
                @update:model-value="emit('update:endsOn', $event)"
            />
        </div>
        <p
            v-if="!disableRecurrenceHint && !compact"
            class="text-xs text-gray-500 sm:col-span-2"
        >
            Deixe em branco para um único dia. Use intervalo para feriados prolongados ou recessos.
        </p>
        <p
            v-else-if="disableEndsOn && !compact"
            class="text-xs text-gray-500 sm:col-span-2"
        >
            Intervalo indisponível quando há repetição configurada.
        </p>
    </div>
</template>
