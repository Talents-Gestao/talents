<script setup>
import { BellIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { formatRelativeDate } from '@/utils/dateOnly';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { useUnreadNotices } from '@/composables/useUnreadNotices';

const {
    unreadCount,
    notices,
    hasMore,
    loading,
    loadingMore,
    open,
    toast,
    showNotices,
    isPanelHost,
    routes,
    badgeLabel,
    toggle,
    close,
    fetchNotices,
    applyCountsFromPayload,
} = useUnreadNotices({ panel: true });

const deletingId = ref(null);
const deletingAll = ref(false);

function formatPublished(iso) {
    if (!iso) return '';
    return formatRelativeDate(iso.slice(0, 10));
}

async function markAllRead() {
    if (unreadCount.value <= 0) return;

    try {
        const { data } = await axios.post(routes.value.markAllRead());
        notices.value = notices.value.map((notice) => ({ ...notice, read: true }));
        applyCountsFromPayload(data);
    } catch {
        // silencioso
    }
}

async function destroyNotice(notice) {
    if (deletingId.value || deletingAll.value) {
        return;
    }
    if (!(await confirmDialog('Excluir este aviso? Esta ação não pode ser desfeita.'))) {
        return;
    }

    deletingId.value = notice.id;
    try {
        const { data } = await axios.delete(routes.value.destroy(notice.id));
        notices.value = notices.value.filter((item) => item.id !== notice.id);
        applyCountsFromPayload(data);
        if (!notices.value.length && hasMore.value) {
            await fetchNotices();
        }
    } catch {
        // silencioso
    } finally {
        deletingId.value = null;
    }
}

async function destroyAll() {
    if (!notices.value.length || deletingAll.value) {
        return;
    }
    if (!(await confirmDialog('Excluir todos os avisos? Esta ação não pode ser desfeita.'))) {
        return;
    }

    deletingAll.value = true;
    try {
        const { data } = await axios.post(routes.value.destroyAll());
        notices.value = [];
        hasMore.value = false;
        applyCountsFromPayload(data);
    } catch {
        // silencioso
    } finally {
        deletingAll.value = false;
    }
}

function closeOnEscape(event) {
    if (open.value && event.key === 'Escape') {
        close();
    }
}

watch(open, (isOpen) => {
    if (isOpen && isPanelHost.value) {
        fetchNotices();
    }
});

function onListScroll(event) {
    const el = event.target;
    if (!(el instanceof HTMLElement)) {
        return;
    }
    const remaining = el.scrollHeight - el.scrollTop - el.clientHeight;
    if (remaining < 96) {
        fetchNotices({ append: true });
    }
}

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <div v-if="showNotices" class="relative">
        <button
            type="button"
            class="relative rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
            :class="toast ? 'text-rose-700 ring-2 ring-rose-300/70' : ''"
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="Avisos"
            @click.stop="toggle"
        >
            <BellIcon class="h-6 w-6" :class="toast ? 'animate-pulse' : ''" />
            <span
                v-if="badgeLabel"
                class="absolute right-1 top-1 flex min-h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
            >
                {{ badgeLabel }}
            </span>
        </button>

        <Teleport v-if="isPanelHost" to="body">
            <Transition
                enter-active-class="transition-opacity duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-[200] bg-slate-900/40 backdrop-blur-sm"
                    aria-hidden="true"
                    @click="close"
                />
            </Transition>

            <Transition
                enter-active-class="transform transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transform transition-transform duration-200 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="open"
                    class="fixed inset-y-0 right-0 z-[210] flex w-full flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-slate-200/70 sm:max-w-sm sm:rounded-l-2xl"
                    role="dialog"
                    aria-label="Avisos"
                >
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-talents-50 text-talents-700">
                                <BellIcon class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Avisos</h2>
                                <p class="text-xs text-slate-500">
                                    {{ unreadCount > 0 ? `${unreadCount} não lido(s)` : 'Tudo em dia' }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
                            aria-label="Fechar"
                            @click="close"
                        >
                            <XMarkIcon class="h-5 w-5" />
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto" @scroll.passive="onListScroll">
                        <div v-if="loading" class="px-5 py-12 text-center text-sm text-slate-500">
                            A carregar…
                        </div>

                        <ul v-else-if="notices.length" class="divide-y divide-slate-100">
                            <li v-for="notice in notices" :key="notice.id" class="flex items-stretch">
                                <Link
                                    :href="routes.open(notice.id)"
                                    class="flex min-w-0 flex-1 gap-3 px-5 py-4 text-left transition hover:bg-slate-50"
                                    :class="notice.read ? 'opacity-80' : 'bg-rose-50/40'"
                                    @click="close"
                                >
                                    <span
                                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                        :class="notice.read ? 'bg-transparent' : 'bg-rose-600'"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span
                                            v-if="notice.company_name"
                                            class="mb-0.5 block text-[11px] font-medium text-slate-500"
                                        >
                                            {{ notice.company_name }}
                                        </span>
                                        <span class="block text-sm font-medium text-slate-900">
                                            {{ notice.title }}
                                        </span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-slate-600">
                                            {{ notice.body }}
                                        </span>
                                        <time
                                            class="mt-1.5 block text-[11px] text-slate-400"
                                            :datetime="notice.published_at"
                                        >
                                            {{ formatPublished(notice.published_at) }}
                                        </time>
                                    </span>
                                </Link>
                                <button
                                    type="button"
                                    class="shrink-0 px-3 text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-40"
                                    :disabled="deletingId === notice.id || deletingAll"
                                    title="Excluir aviso"
                                    aria-label="Excluir aviso"
                                    @click.stop="destroyNotice(notice)"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </li>
                        </ul>

                        <p
                            v-if="!loading && notices.length && (loadingMore || hasMore)"
                            class="px-5 py-3 text-center text-xs text-slate-400"
                        >
                            {{ loadingMore ? 'A carregar mais…' : 'Role para ver mais avisos' }}
                        </p>

                        <div
                            v-if="!loading && !notices.length"
                            class="flex flex-col items-center justify-center px-5 py-16 text-center"
                        >
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <BellIcon class="h-6 w-6" />
                            </span>
                            <p class="mt-3 text-sm text-slate-500">Nenhum aviso por enquanto.</p>
                        </div>
                    </div>

                    <footer
                        v-if="notices.length || unreadCount > 0"
                        class="shrink-0 space-y-2 border-t border-slate-100 bg-slate-50/80 px-5 py-3"
                    >
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-talents-700 shadow-sm transition hover:border-talents-200 hover:bg-talents-50 hover:text-talents-900"
                            @click="markAllRead"
                        >
                            Marcar todos como lidos
                        </button>
                        <button
                            type="button"
                            class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 shadow-sm transition hover:border-rose-300 hover:bg-rose-50 disabled:opacity-50"
                            :disabled="deletingAll || !notices.length"
                            @click="destroyAll"
                        >
                            {{ deletingAll ? 'A excluir…' : 'Excluir todos' }}
                        </button>
                    </footer>
                </aside>
            </Transition>
        </Teleport>
    </div>
</template>
