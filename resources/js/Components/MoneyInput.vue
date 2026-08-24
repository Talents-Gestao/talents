<script setup>
import {
    formatMoneyDisplay,
    formatMoneyModel,
} from '@/utils/moneyMask';
import { ref, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    id: { type: String, default: undefined },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    placeholder: { type: String, default: '0,00' },
    /** Classes extras no input (além do estilo padrão). */
    inputClass: { type: String, default: '' },
});

/** Modelo canónico para API: "1234.56" ou "" (ponto decimal). */
const model = defineModel({ type: [String, Number], default: '' });

const display = ref('');
const syncingFromInput = ref(false);

const syncDisplayFromModel = () => {
    display.value = formatMoneyDisplay(model.value);
};

watch(
    () => model.value,
    () => {
        if (!syncingFromInput.value) {
            syncDisplayFromModel();
        }
    },
    { immediate: true },
);

const onInput = (event) => {
    if (props.readonly || props.disabled) {
        return;
    }

    const digits = String(event.target.value ?? '').replace(/\D/g, '').slice(0, 15);
    syncingFromInput.value = true;

    if (!digits) {
        display.value = '';
        model.value = '';
        syncingFromInput.value = false;
        return;
    }

    const amount = Number.parseInt(digits, 10) / 100;
    display.value = formatMoneyDisplay(amount);
    model.value = formatMoneyModel(amount);
    syncingFromInput.value = false;
};

const onBlur = () => {
    syncDisplayFromModel();
};

const baseClass =
    'rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500 tabular-nums';
</script>

<template>
    <input
        :id="id"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        :value="display"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        :class="[
            baseClass,
            inputClass,
            readonly || disabled ? 'bg-slate-50 text-slate-600' : '',
            $attrs.class,
        ]"
        v-bind="{
            ...$attrs,
            class: undefined,
            onInput: undefined,
            onBlur: undefined,
        }"
        @input="onInput"
        @blur="onBlur"
    />
</template>
