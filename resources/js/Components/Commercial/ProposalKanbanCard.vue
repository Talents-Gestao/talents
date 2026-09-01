<script setup>
import { formatBRL } from '@/composables/useCommercialPricing';
import { formatCnpj } from '@/utils/formatCnpj';
import {
    ArrowPathIcon,
    BanknotesIcon,
    DocumentArrowDownIcon,
    DocumentTextIcon,
    EllipsisHorizontalIcon,
    PencilSquareIcon,
    TrashIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import {
    activeProposalKanbanMenuId,
    claimProposalKanbanMenu,
    releaseProposalKanbanMenu,
} from '@/composables/useExclusiveProposalKanbanMenu';
import { positionAnchoredMenu } from '@/utils/positionAnchoredMenu';
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    proposal: { type: Object, required: true },
    statusKey: { type: String, default: 'open' },
    reopeningId: { type: [Number, String], default: null },
});

const emit = defineEmits([
    'edit-status',
    'reopen',
    'convert',
    'contract',
    'destroy',
]);

const menuOpen = ref(false);
const menuPosition = ref(null);
const menuButtonEl = ref(null);
const menuPanelEl = ref(null);

const MENU_WIDTH = 176;
const MENU_HEIGHT_FALLBACK = 200;

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const canConvert = computed(() => {
    const p = props.proposal;
    const status = p.list_status ?? (p.is_closed ? 'closed' : 'open');
    return (status === 'closed' || status === 'approved') && !p.sale;
});

const installmentsLabel = computed(() => {
    const paid = props.proposal.paid_installments;
    const total = props.proposal.total_installments;
    if (paid == null || total == null || Number(total) < 1) {
        return null;
    }
    return `${paid}/${total} pagas`;
});

const showStagnantAlert = computed(() => Boolean(props.proposal.is_stagnant));
const showClosedWithoutSaleAlert = computed(() => Boolean(props.proposal.closed_without_sale));
const showZapsignPendingAlert = computed(() => Boolean(props.proposal.zapsign_pending));

const accentClass = computed(() => {
    if (props.statusKey === 'closed') {
        return 'bg-emerald-500';
    }
    if (props.statusKey === 'ended') {
        return 'bg-slate-400';
    }
    return 'bg-amber-400';
});

const statusBadgeClass = computed(() => {
    if (props.statusKey === 'closed') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200/80 hover:bg-emerald-100';
    }
    if (props.statusKey === 'ended') {
        return 'bg-slate-100 text-slate-700 ring-slate-200 hover:bg-slate-200/80';
    }
    return 'bg-amber-50 text-amber-900 ring-amber-200/80 hover:bg-amber-100';
});

const ellipsisButtonEl = ref(null);
const cardEl = ref(null);
const dragMoved = ref(false);
const pointerOrigin = ref(null);

const closeMenu = ({ release = true } = {}) => {
    menuOpen.value = false;
    menuPosition.value = null;
    if (release) {
        releaseProposalKanbanMenu(props.proposal.id);
    }
};

watch(activeProposalKanbanMenuId, (activeId) => {
    if (activeId !== props.proposal.id && menuOpen.value) {
        closeMenu({ release: false });
    }
});

const resolveAnchorEl = (anchorEl) => {
    const candidate = anchorEl ?? ellipsisButtonEl.value ?? cardEl.value;
    const rect = candidate?.getBoundingClientRect?.();
    if (rect && (rect.width > 0 || rect.height > 0)) {
        return candidate;
    }

    return cardEl.value ?? candidate;
};

const positionMenu = (anchorEl, menuHeight = MENU_HEIGHT_FALLBACK) => {
    const el = resolveAnchorEl(anchorEl);
    const rect = el?.getBoundingClientRect?.();
    if (!rect) {
        return null;
    }

    return positionAnchoredMenu({
        anchorRect: rect,
        menuWidth: MENU_WIDTH,
        menuHeight,
        viewportWidth: window.innerWidth,
        viewportHeight: window.innerHeight,
    });
};

const openMenu = async (anchorEl) => {
    const button = resolveAnchorEl(anchorEl);
    menuButtonEl.value = button;
    claimProposalKanbanMenu(props.proposal.id);
    menuOpen.value = true;
    // Fora do ecrã até medir a altura real — evita o salto para cima com altura estimada.
    menuPosition.value = { top: -9999, left: -9999 };
    await nextTick();
    const measured = menuPanelEl.value?.getBoundingClientRect?.()?.height || MENU_HEIGHT_FALLBACK;
    menuPosition.value = positionMenu(button, measured) ?? { top: 8, left: 8 };
};

const toggleMenu = async (event) => {
    event?.stopPropagation?.();
    if (menuOpen.value) {
        closeMenu();
        return;
    }

    await openMenu(event?.currentTarget);
};

const isInteractiveTarget = (target) => target instanceof Element && Boolean(target.closest('a, button'));

const onCardPointerDown = (event) => {
    if (event.button !== undefined && event.button !== 0) {
        return;
    }
    if (isInteractiveTarget(event.target)) {
        pointerOrigin.value = null;
        dragMoved.value = false;
        return;
    }
    pointerOrigin.value = { x: event.clientX, y: event.clientY };
    dragMoved.value = false;
};

const onCardPointerMove = (event) => {
    // Só conta arrasto com o botão pressionado. Mover o rato após o clique
    // (para chegar ao menu) não pode fechar o painel.
    if (!pointerOrigin.value || dragMoved.value || event.buttons !== 1) {
        return;
    }
    const dx = event.clientX - pointerOrigin.value.x;
    const dy = event.clientY - pointerOrigin.value.y;
    if (Math.hypot(dx, dy) > 6) {
        dragMoved.value = true;
    }
};

const onCardPointerUp = () => {
    pointerOrigin.value = null;
};

const onCardClick = async (event) => {
    if (dragMoved.value) {
        dragMoved.value = false;
        return;
    }

    if (isInteractiveTarget(event.target)) {
        return;
    }

    event.stopPropagation();
    if (menuOpen.value) {
        closeMenu();
        return;
    }

    await openMenu(ellipsisButtonEl.value);
};

const onDocumentClick = (event) => {
    if (!menuOpen.value) {
        return;
    }
    const target = event.target;
    if (menuButtonEl.value?.contains?.(target) || menuPanelEl.value?.contains?.(target)) {
        return;
    }
    closeMenu();
};

const onViewportChange = () => {
    if (menuOpen.value) {
        closeMenu();
    }
};

const runAction = (action) => {
    closeMenu();
    action();
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange);
    closeMenu();
});
</script>

<template>
    <article
        ref="cardEl"
        class="proposal-kanban-card group relative cursor-grab rounded-xl border border-slate-200/90 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-talents-200 hover:shadow-md active:cursor-grabbing"
        :data-proposal-id="proposal.id"
        title="Clique para ações · arraste para mudar o status"
        @pointerdown="onCardPointerDown"
        @pointermove="onCardPointerMove"
        @pointerup="onCardPointerUp"
        @pointercancel="onCardPointerUp"
        @click="onCardClick"
    >
        <div
            class="absolute inset-y-3 left-0 w-1 rounded-full"
            :class="accentClass"
            aria-hidden="true"
        />

        <div class="pl-3.5 pr-3 pt-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        {{ proposal.code }}
                    </p>
                    <h4
                        class="mt-0.5 truncate text-sm font-semibold leading-snug text-slate-900"
                        :title="proposal.client_name"
                    >
                        {{ proposal.client_name }}
                    </h4>
                    <p v-if="proposal.client_cnpj" class="mt-0.5 text-xs text-slate-500">
                        {{ formatCnpj(proposal.client_cnpj) }}
                    </p>
                </div>

                <button
                    ref="ellipsisButtonEl"
                    type="button"
                    class="shrink-0 rounded-lg p-1.5 text-slate-400 opacity-70 transition hover:bg-slate-100 hover:text-slate-700 group-hover:opacity-100"
                    :class="menuOpen ? 'bg-slate-100 text-slate-700 opacity-100' : ''"
                    title="Mais ações"
                    aria-label="Mais ações"
                    aria-haspopup="true"
                    :aria-expanded="menuOpen"
                    @click="toggleMenu"
                >
                    <EllipsisHorizontalIcon class="h-4 w-4" />
                </button>
            </div>

            <p class="mt-3 text-base font-semibold tabular-nums tracking-tight text-slate-900">
                {{ formatBRL(proposal.total_final_cents) }}
                <span
                    v-if="proposal.is_recurring"
                    class="ml-1 align-middle text-[10px] font-semibold uppercase tracking-wide text-talents-700"
                >
                    Recorrente
                </span>
            </p>

            <div class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                <UserIcon class="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                <span class="truncate">{{ proposal.seller?.name || 'Sem vendedor' }}</span>
                <span class="text-slate-300">·</span>
                <span class="shrink-0 tabular-nums">{{ formatDate(proposal.created_at) }}</span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-1.5 pb-3">
                <button
                    type="button"
                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 transition"
                    :class="statusBadgeClass"
                    title="Alterar status"
                    @click.stop="emit('edit-status', proposal)"
                >
                    {{ proposal.list_status_label ?? 'Status' }}
                </button>
                <span
                    v-if="showStagnantAlert"
                    class="rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-800 ring-1 ring-orange-200/80"
                    title="Sem movimento há 30 dias ou mais"
                >
                    Sem movimento
                </span>
                <span
                    v-if="showClosedWithoutSaleAlert"
                    class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-300/80"
                    title="Proposta fechada sem venda vinculada"
                >
                    Sem venda
                </span>
                <span
                    v-if="showZapsignPendingAlert"
                    class="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-900 ring-1 ring-sky-200/80"
                    title="Contrato enviado no ZapSign, aguardando assinatura"
                >
                    ZapSign pendente
                </span>
                <span
                    v-if="proposal.sale"
                    class="rounded-full bg-talents-50 px-2 py-0.5 text-[11px] font-semibold text-talents-800 ring-1 ring-talents-200/70"
                >
                    Venda
                </span>
                <span
                    v-if="installmentsLabel"
                    class="rounded-full bg-slate-50 px-2 py-0.5 text-[11px] tabular-nums text-slate-600 ring-1 ring-slate-200/80"
                >
                    {{ installmentsLabel }}
                </span>
                <span
                    v-if="proposal.lost_reason_label"
                    class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 ring-1 ring-rose-200/70"
                    :title="proposal.lost_reason_notes || undefined"
                >
                    {{ proposal.lost_reason_label }}
                </span>
                <Link
                    v-if="proposal.sale"
                    :href="route('admin.financeiro.vendas.show', proposal.sale.id)"
                    class="ml-auto text-[11px] font-semibold text-talents-700 hover:text-talents-900 hover:underline"
                    @click.stop
                >
                    Ver venda
                </Link>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="menuOpen && menuPosition"
                ref="menuPanelEl"
                class="fixed z-[200] w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5"
                role="menu"
                :style="{
                    top: `${menuPosition.top}px`,
                    left: `${menuPosition.left}px`,
                    visibility: menuPosition.top < 0 ? 'hidden' : 'visible',
                }"
                @click.stop
            >
                <Link
                    :href="route('admin.comercial.propostas.edit', proposal.id)"
                    class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    role="menuitem"
                    @click="closeMenu"
                >
                    <PencilSquareIcon class="h-3.5 w-3.5 text-slate-400" />
                    Editar
                </Link>
                <a
                    :href="route('admin.comercial.propostas.pdf', proposal.id)"
                    class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    role="menuitem"
                    target="_blank"
                    rel="noopener"
                    @click="closeMenu"
                >
                    <DocumentArrowDownIcon class="h-3.5 w-3.5 text-slate-400" />
                    PDF da proposta
                </a>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                    role="menuitem"
                    @click="runAction(() => emit('contract', proposal))"
                >
                    <DocumentTextIcon class="h-3.5 w-3.5 text-slate-400" />
                    Gerar contrato
                </button>
                <button
                    v-if="canConvert"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-emerald-800 transition hover:bg-emerald-50"
                    role="menuitem"
                    @click="runAction(() => emit('convert', proposal))"
                >
                    <BanknotesIcon class="h-3.5 w-3.5 text-emerald-600" />
                    Converter em venda
                </button>
                <button
                    v-if="proposal.can_reopen"
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-sky-800 transition hover:bg-sky-50 disabled:opacity-50"
                    role="menuitem"
                    :disabled="reopeningId === proposal.id"
                    @click="runAction(() => emit('reopen', proposal))"
                >
                    <ArrowPathIcon
                        class="h-3.5 w-3.5 text-sky-600"
                        :class="reopeningId === proposal.id ? 'animate-spin' : ''"
                    />
                    Reabrir
                </button>
                <div class="my-1 border-t border-slate-100" />
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                    role="menuitem"
                    @click="runAction(() => emit('destroy', proposal))"
                >
                    <TrashIcon class="h-3.5 w-3.5" />
                    Excluir
                </button>
            </div>
        </Teleport>
    </article>
</template>
