<script setup>
import { completionPercent } from '@/utils/taskBoards';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { computed } from 'vue';

/**
 * Resumo de um quadro recolhido, em uma linha: listas, cartões por lista, progresso de concluídos,
 * atrasados e próximo prazo. Vem pronto do servidor (sem carregar listas e cartões).
 */
const props = defineProps({
    board: { type: Object, required: true },
});

const summary = computed(() => props.board.summary ?? {});
const listsCount = computed(() => Number(props.board.lists_count) || 0);
const cardsCount = computed(() => Number(props.board.cards_count) || 0);
const completed = computed(() => Number(summary.value.completed_count) || 0);
const percent = computed(() => completionPercent(cardsCount.value, completed.value));
const lists = computed(() => summary.value.lists ?? []);
const overdue = computed(() => Number(summary.value.overdue_count) || 0);
const nextDue = computed(() => summary.value.next_due_date || null);

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

const listsTitle = computed(() =>
    lists.value
        .map((list) => `${list.name}: ${plural(list.cards_count, 'cartão', 'cartões')} (${list.completed_count} concluído(s))`)
        .join('\n'),
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-x-3 gap-y-1 overflow-hidden whitespace-nowrap text-xs text-slate-600" data-board-summary>
        <span class="shrink-0 font-medium text-slate-700">
            {{ plural(listsCount, 'lista', 'listas') }} · {{ plural(cardsCount, 'cartão', 'cartões') }}
        </span>

        <span v-if="cardsCount" class="inline-flex shrink-0 items-center gap-1.5" :title="`${completed} de ${cardsCount} concluídos`">
            <span
                class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200"
                role="progressbar"
                :aria-valuenow="percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="`${percent}% dos cartões concluídos`"
            >
                <span class="block h-full rounded-full bg-emerald-500" :style="{ width: `${percent}%` }" />
            </span>
            <span>{{ percent }}% concluídos</span>
        </span>

        <span
            v-if="overdue"
            class="shrink-0 rounded-md bg-rose-50 px-1.5 py-0.5 font-medium text-rose-700"
        >
            {{ plural(overdue, 'atrasado', 'atrasados') }}
        </span>

        <span v-if="nextDue" class="shrink-0" :title="formatDateNumeric(nextDue)">
            Próximo prazo: <span class="font-medium text-slate-700">{{ formatRelativeDate(nextDue) }}</span>
        </span>

        <span v-if="lists.length" class="min-w-0 truncate text-slate-500" :title="listsTitle">
            <template v-for="(list, index) in lists" :key="list.id">
                <span v-if="index" aria-hidden="true"> · </span>
                <span
                    v-if="list.color"
                    class="mr-1 inline-block h-2 w-2 rounded-full align-middle"
                    :style="{ backgroundColor: list.color }"
                    aria-hidden="true"
                />{{ list.name }} {{ list.cards_count }}
            </template>
        </span>

        <span v-if="!cardsCount && !listsCount" class="text-slate-500">Quadro vazio.</span>
    </div>
</template>
