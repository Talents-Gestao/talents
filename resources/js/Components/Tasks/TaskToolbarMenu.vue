<script setup>
import { nextTick, onBeforeUnmount, ref, useId } from 'vue';

/**
 * Menu suspenso da barra de Tarefas. O painel vai para o <body> (posição fixa), então a barra pode
 * rolar na horizontal no celular sem cortar o menu. Fecha com Esc, clique fora, rolagem da página ou redimensionamento.
 */
const props = defineProps({
    /** Rótulo acessível do botão (quando o conteúdo do botão é só ícone). */
    label: { type: String, default: '' },
    title: { type: String, default: '' },
    align: { type: String, default: 'left' },
    width: { type: Number, default: 224 },
    buttonClass: { type: [String, Array, Object], default: '' },
    panelClass: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['open', 'close']);

const open = ref(false);
const trigger = ref(null);
const panel = ref(null);
const position = ref({ top: 0, left: 0, maxHeight: 400 });
const panelId = `task-toolbar-menu-${useId()}`;

function place() {
    const rect = trigger.value?.getBoundingClientRect?.();
    if (!rect) return;
    const width = Math.min(props.width, window.innerWidth - 16);
    const preferred = props.align === 'right' ? rect.right - width : rect.left;
    const left = Math.max(8, Math.min(preferred, window.innerWidth - width - 8));
    const top = rect.bottom + 6;
    position.value = { top, left, width, maxHeight: Math.max(160, window.innerHeight - top - 12) };
}

function onPointerDown(event) {
    if (panel.value?.contains(event.target) || trigger.value?.contains(event.target)) return;
    close();
}

function onScroll(event) {
    if (panel.value?.contains(event.target)) return;
    close();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        close();
        trigger.value?.focus();
    }
}

function listen(on) {
    const method = on ? 'addEventListener' : 'removeEventListener';
    document[method]('pointerdown', onPointerDown, true);
    document[method]('keydown', onKeydown);
    window[method]('scroll', onScroll, true);
    window[method]('resize', close);
}

function show() {
    place();
    open.value = true;
    listen(true);
    emit('open');
    nextTick(() => panel.value?.querySelector('[data-autofocus]')?.focus());
}

function close() {
    if (!open.value) return;
    open.value = false;
    listen(false);
    emit('close');
}

function toggle() {
    open.value ? close() : show();
}

onBeforeUnmount(() => listen(false));

defineExpose({ close });
</script>

<template>
    <button
        ref="trigger"
        type="button"
        :class="buttonClass"
        :title="title || undefined"
        :aria-label="label || undefined"
        aria-haspopup="true"
        :aria-expanded="open ? 'true' : 'false'"
        :aria-controls="panelId"
        @click.stop="toggle"
    >
        <slot name="trigger" :open="open" />
    </button>
    <Teleport to="body">
        <div
            v-if="open"
            :id="panelId"
            ref="panel"
            class="fixed z-[100] overflow-y-auto rounded-xl bg-white py-1.5 shadow-xl ring-1 ring-slate-200"
            :class="panelClass"
            :style="{
                top: `${position.top}px`,
                left: `${position.left}px`,
                width: `${position.width}px`,
                maxHeight: `${position.maxHeight}px`,
            }"
            @click.stop
        >
            <slot :close="close" />
        </div>
    </Teleport>
</template>
