<script setup>
import ProposalKanbanCard from '@/Components/Commercial/ProposalKanbanCard.vue';
import { formatBRL } from '@/composables/useCommercialPricing';
import {
    ArrowPathIcon,
    BanknotesIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    kanban: {
        type: Object,
        required: true,
    },
    reopeningId: { type: [Number, String], default: null },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits([
    'move',
    'edit-status',
    'reopen',
    'convert',
    'contract',
    'destroy',
]);

const COLUMN_THEME = {
    open: {
        header: 'bg-gradient-to-br from-amber-50 to-orange-50/80',
        bar: 'bg-amber-400',
        badge: 'bg-amber-100 text-amber-900 ring-amber-200/80',
        iconWrap: 'bg-amber-100 text-amber-700',
        empty: 'border-amber-200/60 bg-amber-50/40 text-amber-800/70',
        Icon: ArrowPathIcon,
    },
    closed: {
        header: 'bg-gradient-to-br from-emerald-50 to-teal-50/70',
        bar: 'bg-emerald-500',
        badge: 'bg-emerald-100 text-emerald-900 ring-emerald-200/80',
        iconWrap: 'bg-emerald-100 text-emerald-700',
        empty: 'border-emerald-200/60 bg-emerald-50/40 text-emerald-800/70',
        Icon: CheckCircleIcon,
    },
    ended: {
        header: 'bg-gradient-to-br from-slate-100 to-slate-50',
        bar: 'bg-slate-400',
        badge: 'bg-slate-200/90 text-slate-800 ring-slate-300/70',
        iconWrap: 'bg-slate-200 text-slate-600',
        empty: 'border-slate-200 bg-slate-50/80 text-slate-500',
        Icon: XCircleIcon,
    },
};

/** @type {import('vue').Ref<Array>} */
const localColumns = ref(cloneColumns(props.kanban?.columns));

watch(
    () => props.kanban,
    (next) => {
        localColumns.value = cloneColumns(next?.columns);
    },
    { deep: true },
);

const pipelineLabel = computed(() => formatBRL(props.kanban?.pipeline_open_cents ?? 0));

const totalCards = computed(() =>
    (localColumns.value || []).reduce((sum, col) => sum + Number(col.count || 0), 0),
);

function columnTheme(key) {
    return COLUMN_THEME[key] || COLUMN_THEME.ended;
}

function cloneColumns(columns) {
    return (columns || []).map((col) => ({
        ...col,
        items: (col.items || []).map((item) => ({ ...item })),
    }));
}

function findProposal(proposalId) {
    for (const col of localColumns.value) {
        const found = col.items.find((p) => Number(p.id) === Number(proposalId));
        if (found) {
            return { proposal: found, columnKey: col.key };
        }
    }
    return null;
}

/**
 * @param {string} columnKey
 * @param {{ item?: HTMLElement, from?: HTMLElement, to?: HTMLElement }} evt
 */
function onCardDragEnd(columnKey, evt) {
    if (props.busy) {
        localColumns.value = cloneColumns(props.kanban?.columns);
        return;
    }

    const cardEl = evt.item;
    const proposalId = Number(cardEl?.dataset?.proposalId);
    if (!proposalId) {
        return;
    }

    const fromKey = evt.from?.closest?.('[data-column-key]')?.dataset?.columnKey;
    const toKey = evt.to?.closest?.('[data-column-key]')?.dataset?.columnKey
        ?? columnKey;

    if (!fromKey || !toKey) {
        localColumns.value = cloneColumns(props.kanban?.columns);
        return;
    }

    if (fromKey === toKey) {
        localColumns.value = cloneColumns(props.kanban?.columns);
        return;
    }

    const located = findProposal(proposalId);
    const proposal = located?.proposal;
    if (!proposal) {
        localColumns.value = cloneColumns(props.kanban?.columns);
        return;
    }

    emit('move', {
        proposal,
        fromStatus: fromKey,
        toStatus: toKey,
        revert: () => {
            localColumns.value = cloneColumns(props.kanban?.columns);
        },
    });
}
</script>

<template>
    <div class="proposals-kanban space-y-5">
        <div
            class="relative overflow-hidden rounded-2xl border border-talents-100/80 bg-gradient-to-r from-white via-talents-50/40 to-violet-50/50 px-5 py-4 shadow-sm ring-1 ring-talents-100/40"
        >
            <div
                class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-talents-200/20 blur-2xl"
                aria-hidden="true"
            />
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-talents-600 to-violet-700 text-white shadow-md shadow-talents-600/25"
                    >
                        <BanknotesIcon class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-talents-700/80">
                            Pipeline em aberto
                        </p>
                        <p class="mt-0.5 text-2xl font-semibold tabular-nums tracking-tight text-slate-900">
                            {{ pipelineLabel }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200/80"
                    >
                        <span class="tabular-nums text-talents-800">{{ totalCards }}</span>
                        {{ totalCards === 1 ? 'proposta' : 'propostas' }}
                    </span>
                    <p class="max-w-xs text-xs leading-relaxed text-slate-500 sm:text-sm">
                        Arraste os cards entre as colunas para atualizar o status.
                    </p>
                </div>
            </div>
        </div>

        <div
            class="proposals-kanban__track flex items-start gap-4 overflow-x-auto pb-3"
            data-proposals-kanban-scroll
        >
            <section
                v-for="column in localColumns"
                :key="column.key"
                class="proposals-kanban__column group/col flex w-[22rem] shrink-0 flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-slate-50/90 shadow-sm transition hover:shadow-md"
                :data-column-key="column.key"
            >
                <div
                    class="h-1 w-full"
                    :class="columnTheme(column.key).bar"
                    aria-hidden="true"
                />
                <header
                    class="flex items-start justify-between gap-3 px-3.5 py-3.5"
                    :class="columnTheme(column.key).header"
                >
                    <div class="flex min-w-0 items-start gap-2.5">
                        <div
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                            :class="columnTheme(column.key).iconWrap"
                        >
                            <component
                                :is="columnTheme(column.key).Icon"
                                class="h-4 w-4"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold tracking-tight text-slate-900">
                                {{ column.label }}
                            </h3>
                            <p class="mt-0.5 text-xs tabular-nums text-slate-600">
                                {{ formatBRL(column.total_cents) }}
                            </p>
                            <p
                                v-if="column.truncated"
                                class="mt-1 text-[11px] font-medium text-amber-800"
                            >
                                Mostrando {{ column.items.length }} de {{ column.count }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="inline-flex h-7 min-w-7 shrink-0 items-center justify-center rounded-full px-2 text-xs font-bold tabular-nums ring-1"
                        :class="columnTheme(column.key).badge"
                    >
                        {{ column.count }}
                    </span>
                </header>

                <div class="relative flex max-h-[min(68vh,700px)] min-h-[14rem] flex-1 flex-col">
                    <VueDraggable
                        v-model="column.items"
                        item-key="id"
                        class="proposals-kanban__list flex min-h-[14rem] flex-1 flex-col gap-2.5 overflow-y-auto p-2.5"
                        group="proposal-kanban-cards"
                        :animation="200"
                        :disabled="busy"
                        ghost-class="proposals-kanban__ghost"
                        drag-class="proposals-kanban__drag"
                        @end="(e) => onCardDragEnd(column.key, e)"
                    >
                        <ProposalKanbanCard
                            v-for="proposal in column.items"
                            :key="proposal.id"
                            :proposal="proposal"
                            :status-key="column.key"
                            :reopening-id="reopeningId"
                            @edit-status="emit('edit-status', $event)"
                            @reopen="emit('reopen', $event)"
                            @convert="emit('convert', $event)"
                            @contract="emit('contract', $event)"
                            @destroy="emit('destroy', $event)"
                        />
                    </VueDraggable>

                    <div
                        v-if="!column.items.length"
                        class="pointer-events-none absolute inset-2.5 flex flex-col items-center justify-center rounded-xl border border-dashed px-4 text-center"
                        :class="columnTheme(column.key).empty"
                    >
                        <component
                            :is="columnTheme(column.key).Icon"
                            class="mb-2 h-6 w-6 opacity-50"
                            aria-hidden="true"
                        />
                        <p class="text-sm font-medium">Nenhuma proposta</p>
                        <p class="mt-0.5 text-xs opacity-80">Solte um card aqui</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.proposals-kanban__track {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

.proposals-kanban__list {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

.proposals-kanban__ghost {
    opacity: 0.45;
    transform: scale(0.98);
}

.proposals-kanban__drag {
    cursor: grabbing;
    opacity: 0.95;
    box-shadow:
        0 12px 28px -8px rgb(99 42 126 / 0.28),
        0 4px 10px -4px rgb(15 23 42 / 0.12);
}
</style>
