<script setup>
import TaskCardMeta from '@/Components/Tasks/TaskCardMeta.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    board: { type: Object, required: true },
    showRoute: { type: String, required: true },
    showCompanyOnCards: { type: Boolean, default: true },
});

function listStyle(list) {
    const color = list?.color?.trim();
    if (!color) {
        return {};
    }
    return {
        borderTopWidth: '3px',
        borderTopStyle: 'solid',
        borderTopColor: color,
        backgroundColor: `${color}18`,
    };
}
</script>

<template>
    <div class="border-t border-slate-100 bg-slate-50/50 px-3 py-3">
        <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <span>{{ board.lists_count }} lista(s)</span>
            <span>{{ board.cards_count }} tarefa(s)</span>
            <span v-if="board.updated_at">
                Atualizado
                {{
                    new Date(board.updated_at).toLocaleString('pt-BR', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    })
                }}
            </span>
        </div>

        <div v-if="!board.lists?.length" class="text-sm text-slate-500">Nenhuma lista neste quadro.</div>

        <div v-else class="flex gap-3 overflow-x-auto pb-1">
            <div
                v-for="list in board.lists"
                :key="list.id"
                class="flex w-64 shrink-0 flex-col rounded-lg p-2 ring-1 ring-slate-200"
                :class="list.color ? '' : 'bg-white'"
                :style="listStyle(list)"
            >
                <p class="mb-2 truncate px-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                    {{ list.name }}
                    <span class="font-normal text-slate-500">({{ list.cards?.length ?? 0 }})</span>
                </p>

                <ul v-if="list.cards?.length" class="space-y-2">
                    <li v-for="card in list.cards" :key="card.id">
                        <Link
                            :href="showRoute"
                            class="block rounded-md bg-white px-2.5 py-2 text-left shadow-sm ring-1 ring-slate-200 transition hover:ring-talents-300"
                        >
                            <div
                                v-if="card.cover_color"
                                class="-mx-2.5 -mt-2 mb-2 h-1.5 rounded-t-md"
                                :style="{ backgroundColor: card.cover_color }"
                            />

                            <div v-if="card.labels?.length" class="mb-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="lb in card.labels"
                                    :key="lb.id"
                                    class="inline-block h-2 min-w-[2rem] rounded-full"
                                    :style="{ backgroundColor: lb.color }"
                                    :title="lb.name || lb.color"
                                />
                            </div>

                            <p class="line-clamp-2 text-sm font-medium leading-snug text-slate-900">{{ card.title }}</p>
                            <p
                                v-if="showCompanyOnCards && card.company?.name"
                                class="mt-1 truncate text-[11px] text-slate-500"
                            >
                                {{ card.company.name }}
                            </p>

                            <TaskCardMeta :card="card" />
                        </Link>
                    </li>
                </ul>
                <p v-else class="px-1 text-xs italic text-slate-400">Sem cartões</p>
            </div>
        </div>
    </div>
</template>
