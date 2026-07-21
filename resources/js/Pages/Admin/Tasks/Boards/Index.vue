<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BoardIndexPreview from '@/Components/Tasks/BoardIndexPreview.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    boards: { type: Array, default: () => [] },
});

const STORAGE_KEY = 'talents-tarefas-boards-collapsed';

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

function expandAll() {
    collapsedIds.value = new Set();
    persistCollapsed();
}

function collapseAll() {
    collapsedIds.value = new Set(filteredBoards.value.map((b) => b.id));
    persistCollapsed();
}

const filteredBoards = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) {
        return props.boards;
    }
    return props.boards.filter(
        (b) =>
            String(b.name || '')
                .toLowerCase()
                .includes(term) ||
            String(b.company?.name || '')
                .toLowerCase()
                .includes(term),
    );
});

const internalBoards = computed(() => filteredBoards.value.filter((b) => b.is_internal));
const companyBoards = computed(() => filteredBoards.value.filter((b) => !b.is_internal));
</script>

<template>
    <Head title="Tarefas — Quadros" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Quadros de tarefas</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Gerencie vários quadros internos ou abra quadros vinculados a empresas.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('admin.tarefas.processos.index')"
                        class="inline-flex items-center rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                    >
                        Modelos de processo
                    </Link>
                    <Link :href="route('admin.tarefas.quadros.create')">
                        <PrimaryButton class="inline-flex items-center gap-1.5">
                            <PlusIcon class="h-4 w-4" />
                            Novo quadro
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <TextInput v-model="search" type="search" placeholder="Buscar quadro…" class="w-full max-w-xs" />
            <button
                type="button"
                class="text-sm font-medium text-talents-700 hover:underline"
                @click="expandAll"
            >
                Expandir todos
            </button>
            <button
                type="button"
                class="text-sm font-medium text-slate-600 hover:underline"
                @click="collapseAll"
            >
                Recolher todos
            </button>
        </div>

        <section v-if="internalBoards.length" class="mb-8">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Quadros internos</h3>
            <ul class="space-y-2">
                <li
                    v-for="board in internalBoards"
                    :key="board.id"
                    class="surface-card overflow-hidden ring-1 ring-slate-200"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                        <button
                            type="button"
                            class="rounded p-1 text-slate-600 hover:bg-slate-200"
                            :title="isCollapsed(board.id) ? 'Expandir' : 'Recolher'"
                            @click="toggleCollapsed(board.id)"
                        >
                            <ChevronDownIcon v-if="!isCollapsed(board.id)" class="h-5 w-5" />
                            <ChevronRightIcon v-else class="h-5 w-5" />
                        </button>
                        <div
                            v-if="board.cover_color"
                            class="h-8 w-1 shrink-0 rounded-full"
                            :style="{ backgroundColor: board.cover_color }"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900">{{ board.name }}</p>
                            <p v-if="!isCollapsed(board.id) && board.description" class="truncate text-xs text-slate-500">
                                {{ board.description }}
                            </p>
                        </div>
                        <Link
                            :href="route('admin.tarefas.quadros.show', board.id)"
                            class="shrink-0 rounded-md bg-talents-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-talents-700"
                        >
                            Abrir
                        </Link>
                    </div>
                    <BoardIndexPreview
                        v-show="!isCollapsed(board.id)"
                        :board="board"
                        :show-route="route('admin.tarefas.quadros.show', board.id)"
                    />
                </li>
            </ul>
        </section>

        <section v-if="companyBoards.length">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Quadros por empresa</h3>
            <ul class="space-y-2">
                <li
                    v-for="board in companyBoards"
                    :key="board.id"
                    class="surface-card overflow-hidden ring-1 ring-slate-200"
                >
                    <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2">
                        <button
                            type="button"
                            class="rounded p-1 text-slate-600 hover:bg-slate-200"
                            :title="isCollapsed(board.id) ? 'Expandir' : 'Recolher'"
                            @click="toggleCollapsed(board.id)"
                        >
                            <ChevronDownIcon v-if="!isCollapsed(board.id)" class="h-5 w-5" />
                            <ChevronRightIcon v-else class="h-5 w-5" />
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900">{{ board.name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ board.company?.name }}</p>
                        </div>
                        <Link
                            :href="route('admin.tarefas.quadros.show', board.id)"
                            class="shrink-0 rounded-md bg-talents-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-talents-700"
                        >
                            Abrir
                        </Link>
                    </div>
                    <BoardIndexPreview
                        v-show="!isCollapsed(board.id)"
                        :board="board"
                        :show-route="route('admin.tarefas.quadros.show', board.id)"
                    />
                </li>
            </ul>
        </section>

        <p v-if="!filteredBoards.length" class="text-sm text-slate-500">Nenhum quadro encontrado.</p>
    </AdminLayout>
</template>
