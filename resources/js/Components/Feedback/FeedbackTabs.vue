<script setup>
import { nextTick, ref } from 'vue';

/**
 * Abas de Feedbacks (padrão Tabs do WAI-ARIA APG, ativação automática): role tablist/tab, setas, Home e End movem e
 * ativam; só a aba ativa entra no Tab. O painel fica com quem usa (id `${idPrefix}-panel-${key}`). No celular, a lista
 * rola na horizontal sem quebrar. Item: { key, label, hint? } (hint: estado curto ao lado do rótulo).
 */
const props = defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    label: { type: String, required: true },
    idPrefix: { type: String, default: 'feedback-tabs' },
});

const emit = defineEmits(['update:modelValue']);

const refs = ref({});
const tabId = (key) => `${props.idPrefix}-tab-${key}`;
const panelId = (key) => `${props.idPrefix}-panel-${key}`;

async function onKeydown(event, index) {
    const keys = props.tabs.map((t) => t.key);
    const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: keys.length - 1 }[event.key];
    if (next === undefined) return;
    event.preventDefault();
    const key = keys[(next + keys.length) % keys.length];
    emit('update:modelValue', key);
    await nextTick();
    refs.value[key]?.focus();
    refs.value[key]?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
}
</script>

<template>
    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div role="tablist" :aria-label="label" class="flex min-w-max gap-1 border-b border-slate-200">
            <button
                v-for="(tab, i) in tabs"
                :id="tabId(tab.key)"
                :key="tab.key"
                :ref="(el) => (refs[tab.key] = el)"
                type="button"
                role="tab"
                :aria-selected="modelValue === tab.key ? 'true' : 'false'"
                :aria-controls="panelId(tab.key)"
                :tabindex="modelValue === tab.key ? 0 : -1"
                :data-feedback-tab="tab.key"
                class="-mb-px inline-flex min-h-[44px] items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:rounded-t-lg focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-talents-500 sm:px-4"
                :class="modelValue === tab.key ? 'border-talents-600 text-talents-800' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'"
                @click="emit('update:modelValue', tab.key)"
                @keydown="onKeydown($event, i)"
            >
                {{ tab.label }}
                <span
                    v-if="tab.hint"
                    class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                    :class="tab.hintClass || 'bg-slate-100 text-slate-700 ring-slate-200'"
                >{{ tab.hint }}</span>
            </button>
        </div>
    </div>
</template>
