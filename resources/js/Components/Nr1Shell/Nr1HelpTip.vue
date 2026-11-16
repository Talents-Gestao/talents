<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import Nr1Button from './Nr1Button.vue';
import { helpTipId } from './nr1HelpTip';

/**
 * Ícone de ajuda ao lado do título de uma seção do painel NR-1: o texto explicativo fica num tooltip.
 * Abre por hover, foco ou toque; fecha com Esc, ao sair ou ao tocar de novo. O texto fica sempre no DOM
 * (ligado por aria-describedby), então leitores de tela o anunciam mesmo com o balão fechado.
 */
const props = defineProps({
    text: { type: String, required: true },
    /** Nome curto do assunto, para o rótulo do botão («Sobre …»). */
    label: { type: String, default: 'esta seção' },
});

const id = helpTipId();
const trigger = ref(null);
const open = ref(false);
const position = ref({ top: '0px', left: '0px' });
const WIDTH = 288;
const GAP = 12;

const ariaLabel = computed(() => `Sobre ${props.label}`);

const place = () => {
    // Nr1Button é componente: o elemento real está em $el.
    const el = trigger.value?.$el ?? trigger.value;
    if (!el?.getBoundingClientRect) return;
    const rect = el.getBoundingClientRect();
    const left = Math.min(Math.max(rect.left - 8, GAP), Math.max(GAP, window.innerWidth - WIDTH - GAP));
    position.value = { top: `${rect.bottom + 6}px`, left: `${left}px` };
};

const onMove = () => {
    if (open.value) place();
};
const onKeydown = (event) => {
    if (event.key === 'Escape') hide();
};

const show = () => {
    place();
    if (open.value) return;
    open.value = true;
    window.addEventListener('scroll', onMove, true);
    window.addEventListener('resize', onMove);
    window.addEventListener('keydown', onKeydown);
};

function hide() {
    open.value = false;
    window.removeEventListener('scroll', onMove, true);
    window.removeEventListener('resize', onMove);
    window.removeEventListener('keydown', onKeydown);
}

// Toque/clique abre (no celular não há hover); fecha ao tocar fora (blur), sair ou com Esc.
const toggle = () => show();

onBeforeUnmount(hide);
</script>

<template>
    <span class="n1-helptip">
        <Nr1Button
            ref="trigger"
            variant="icon"
            size="sm"
            class="n1-helptip-btn"
            :aria-label="ariaLabel"
            :aria-describedby="id"
            :aria-expanded="open ? 'true' : 'false'"
            data-help-tip
            @click.stop.prevent="toggle"
            @mouseenter="show"
            @mouseleave="hide"
            @focus="show"
            @blur="hide"
        >
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.25v3.25a.75.75 0 0 0 1.5 0V9.75A.75.75 0 0 0 10 9H9Z"
                    clip-rule="evenodd"
                />
            </svg>
        </Nr1Button>
        <Teleport to="body">
            <span v-show="open" :id="id" role="tooltip" class="n1-helptip-bubble" :style="position">{{ text }}</span>
        </Teleport>
    </span>
</template>

<style>
.n1-helptip {
    display: inline-flex;
    vertical-align: middle;
    margin-left: 6px;
}
.n1-helptip .n1-helptip-btn,
.nr1-shell .n1-helptip .n1b.n1-helptip-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 999px;
    color: #8a7d97;
    background: transparent;
    border: 0;
    padding: 0;
    cursor: help;
    box-shadow: none;
    min-height: 0;
}
.n1-helptip .n1-helptip-btn:hover,
.nr1-shell .n1-helptip .n1b.n1-helptip-btn:hover {
    color: #5b2a86;
}
.n1-helptip .n1-helptip-btn:focus-visible,
.nr1-shell .n1-helptip .n1b.n1-helptip-btn:focus-visible {
    outline: 2px solid #8b5cf6;
    outline-offset: 2px;
}
.n1-helptip-btn svg {
    width: 17px;
    height: 17px;
}
.n1-helptip-bubble {
    position: fixed;
    z-index: 90;
    width: 288px;
    max-width: calc(100vw - 24px);
    border-radius: 10px;
    background: #1f1630;
    color: #fff;
    padding: 9px 12px;
    font-size: 12.5px;
    font-weight: 400;
    line-height: 1.45;
    letter-spacing: normal;
    text-transform: none;
    text-align: left;
    box-shadow: 0 10px 30px rgb(15 23 42 / 0.25);
    pointer-events: none;
}
</style>
