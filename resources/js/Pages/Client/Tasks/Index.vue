<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    boards: Array,
});

const STORAGE_KEY = 'talents-client-tarefas-boards-collapsed';

const search = ref('');
const collapsedIds = ref(new Set());

onMounted(() => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const ids = JSON.parse(raw);
            if (Array.isArray(ids)) {
                collapsedIds.value = new Set(ids.map(Number));
            }
        }
    } catch (_e) {
        collapsedIds.value = new Set();
    }
});

function persistCollapsed() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsedIds.value]));
}

function isCollapsed(boardId) {
    return collapsedIds.value.has(boardId);
}

function toggleCollapsed(boardId) {
    if (collapsedIds.value.has(boardId)) {
        collapsedIds.value.delete(boardId);
    } else {
        collapsedIds.value.add(boardId);
    }
    collapsedIds.value = new Set(collapsedIds.value);
    persistCollapsed();
}

const filteredBoards = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) {
        return props.boards || [];
    }
    return (props.boards || []).filter((board) =>
        String(board.name || '')
            .toLowerCase()
            .includes(term),
    );
});
</script>

<template>
    <Head title="Tarefas" />

    <ClientLayout>
        <template #header>
            <div class="flex w-full items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-900">Tarefas</h2>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Pesquisar quadro..."
                    class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                />
            </div>
        </template>

        <div class="space-y-4 p-4">
            <section
                class="rounded-2xl bg-gradient-to-r from-slate-900 via-violet-900 to-slate-900 px-5 py-6 text-white shadow-sm"
            >
                <p class="text-sm font-medium text-white/80">Módulo de processos e tarefas</p>
                <h3 class="mt-1 text-2xl font-semibold">Seus quadros</h3>
                <p class="mt-2 text-sm text-white/80">
                    Clique na seta para recolher ou expandir cada quadro. Abra o quadro para ver as colunas e tarefas.
                </p>
            </section>

            <section class="surface-card p-4">
                <p class="mb-3 text-sm text-slate-600">Quadros compartilhados com sua empresa pela Talents.</p>
                <ul class="space-y-2">
                    <li
                        v-for="b in filteredBoards"
                        :key="b.id"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white ring-1 ring-slate-100"
                    >
                        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                            <button
                                type="button"
                                class="rounded p-1 text-slate-600 hover:bg-slate-200"
                                :title="isCollapsed(b.id) ? 'Expandir' : 'Recolher'"
                                @click="toggleCollapsed(b.id)"
                            >
                                <ChevronDownIcon v-if="!isCollapsed(b.id)" class="h-5 w-5" />
                                <ChevronRightIcon v-else class="h-5 w-5" />
                            </button>
                            <div
                                v-if="b.cover_color"
                                class="h-8 w-1 shrink-0 rounded-full"
                                :style="{ backgroundColor: b.cover_color }"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-900">{{ b.name }}</p>
                                <p v-if="!isCollapsed(b.id)" class="truncate text-xs text-slate-500">
                                    <span v-if="b.is_internal">Quadro Talents</span>
                                    <span v-else-if="b.company">{{ b.company.name }}</span>
                                </p>
                            </div>
                            <Link
                                :href="route('client.tarefas.show', b.id)"
                                class="shrink-0 rounded-md bg-talents-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-talents-700"
                            >
                                Abrir
                            </Link>
                        </div>
                        <div v-show="!isCollapsed(b.id)" class="flex flex-wrap gap-4 px-4 py-3 text-sm text-slate-600">
                            <span>{{ b.lists_count }} lista(s)</span>
                            <span>{{ b.cards_count }} tarefa(s) sua empresa</span>
                        </div>
                    </li>
                </ul>

                <p v-if="!filteredBoards?.length" class="mt-2 text-sm text-slate-500">
                    Nenhum quadro encontrado.
                </p>
            </section>
        </div>
    </ClientLayout>
</template>
