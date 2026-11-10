<script setup>
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Modo Lista de um quadro: todas as tarefas numa tabela (clique abre o cartão). */
const props = defineProps({
    boardPayload: { type: Object, required: true },
    isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(['open-card', 'refresh']);

const listCards = computed(() =>
    (props.boardPayload?.lists || []).flatMap((list) =>
        (list.cards || []).map((card) => ({
            ...card,
            list_name: list.name,
        })),
    ),
);

function formatDateTitle(value) {
    if (!value) return undefined;
    const relative = formatRelativeDate(value);
    const absolute = formatDateNumeric(value);
    if (!absolute || relative === absolute) return absolute;
    return `${relative} (${absolute})`;
}

async function deleteCard(card, event) {
    event?.stopPropagation?.();
    const title = card.title || 'esta tarefa';
    if (
        !(await confirmDialog(
            `Excluir "${title}"?\n\nA tarefa e todos os seus anexos, comentários e checklists serão removidos.`,
        ))
    ) {
        return;
    }
    router.delete(route('admin.tarefas.cards.destroy', card.id), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
}
</script>

<template>
    <div class="max-h-[min(70vh,46rem)] overflow-auto">
        <table class="min-w-full text-left text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-slate-600">
                    <th class="px-2 py-2">Tarefa</th>
                    <th class="px-2 py-2">Coluna</th>
                    <th v-if="isAdmin" class="px-2 py-2">Cliente</th>
                    <th class="px-2 py-2">Vencimento</th>
                    <th class="px-2 py-2">Status</th>
                    <th v-if="isAdmin" class="w-24 px-2 py-2 text-right">Ações</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="card in listCards"
                    :key="card.id"
                    class="cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                    @click="emit('open-card', card)"
                >
                    <td class="px-2 py-2 font-medium text-slate-900">{{ card.title }}</td>
                    <td class="px-2 py-2 text-slate-600">{{ card.list_name }}</td>
                    <td v-if="isAdmin" class="px-2 py-2 text-slate-600">{{ card.company?.name || '—' }}</td>
                    <td class="px-2 py-2 text-slate-600" :title="formatDateTitle(card.due_date)">
                        {{ formatRelativeDate(card.due_date) }}
                    </td>
                    <td class="px-2 py-2 text-slate-600">
                        {{ card.completed_at ? 'Concluída' : 'Aberta' }}
                    </td>
                    <td v-if="isAdmin" class="px-2 py-2 text-right">
                        <button
                            type="button"
                            class="text-xs font-medium text-rose-600 hover:underline"
                            @click="deleteCard(card, $event)"
                        >
                            Excluir
                        </button>
                    </td>
                </tr>
                <tr v-if="!listCards.length">
                    <td :colspan="isAdmin ? 6 : 4" class="px-2 py-6 text-center text-sm text-slate-500">
                        Nenhuma tarefa neste quadro.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
