<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * «Registrar pagamento» da comissão. Bloqueado com aria-disabled (continua focável), e o motivo
 * fica num tooltip ligado por aria-describedby, que abre por hover, foco ou toque e fecha com Esc.
 */
const props = defineProps({
    commissionId: { type: [Number, String], required: true },
    /** Motivo do bloqueio; null/vazio = botão ativo. */
    blockedReason: { type: String, default: null },
});

const emit = defineEmits(['pay']);

const blocked = computed(() => Boolean(props.blockedReason));
const tooltipId = computed(() => `comissao-${props.commissionId}-motivo`);

const trigger = ref(null);
const open = ref(false);
const position = ref({ top: '0px', left: '0px' });

const TOOLTIP_WIDTH = 288;
const VIEWPORT_GAP = 12;

const place = () => {
    const el = trigger.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    // Alinha pela direita do botão (última coluna da tabela), sem sair da tela.
    const left = Math.min(
        Math.max(rect.right - TOOLTIP_WIDTH, VIEWPORT_GAP),
        Math.max(VIEWPORT_GAP, window.innerWidth - TOOLTIP_WIDTH - VIEWPORT_GAP),
    );
    position.value = { top: `${rect.bottom + 6}px`, left: `${left}px` };
};

const onMove = () => {
    if (open.value) place();
};

const onKeydown = (event) => {
    if (event.key === 'Escape') hide();
};

const show = () => {
    if (!blocked.value) return;
    place();
    if (open.value) return;
    open.value = true;
    window.addEventListener('scroll', onMove, true);
    window.addEventListener('resize', onMove);
    window.addEventListener('keydown', onKeydown);
};

const hide = () => {
    open.value = false;
    window.removeEventListener('scroll', onMove, true);
    window.removeEventListener('resize', onMove);
    window.removeEventListener('keydown', onKeydown);
};

const onClick = () => {
    if (blocked.value) {
        // Toque no celular (ou clique): mostra o motivo em vez de abrir o pagamento; fecha ao tocar fora (blur) ou com Esc.
        show();
        return;
    }
    emit('pay');
};

onBeforeUnmount(hide);
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="rounded-lg bg-talents-600 px-2.5 py-1 text-xs font-semibold text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-400 focus-visible:ring-offset-1"
        :class="blocked ? 'cursor-not-allowed opacity-40' : 'hover:bg-talents-700'"
        :aria-disabled="blocked ? 'true' : undefined"
        :aria-describedby="blocked ? tooltipId : undefined"
        :data-blocked="blocked ? 'true' : undefined"
        @click="onClick"
        @mouseenter="show"
        @mouseleave="hide"
        @focus="show"
        @blur="hide"
    >
        Registrar pagamento
    </button>
    <Teleport v-if="blocked" to="body">
        <span
            v-show="open"
            :id="tooltipId"
            role="tooltip"
            class="pointer-events-none fixed z-[80] w-72 rounded-lg bg-slate-900 px-3 py-2 text-left text-xs font-normal leading-snug text-white shadow-lg"
            :style="position"
        >
            {{ blockedReason }}
        </span>
    </Teleport>
</template>
