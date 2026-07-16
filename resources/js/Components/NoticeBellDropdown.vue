<script setup>
import { BellIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { formatRelativeDate } from '@/utils/dateOnly';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();

const POLL_INTERVAL_MS = 60000;

const open = ref(false);
const loading = ref(false);
const notices = ref([]);
const unreadCount = ref(Number(page.props.nav?.unread_notices_count ?? 0));

let pollTimer = null;

// 'company' → portal do cliente; 'talents' → portal admin; null → sem avisos.
const noticesContext = computed(() => page.props.nav?.notices_context ?? null);
const isTalentsContext = computed(() => noticesContext.value === 'talents');
const showNotices = computed(() => noticesContext.value !== null);

const routes = computed(() => {
    const prefix = isTalentsContext.value ? 'admin.notices' : 'client.notices';
    return {
        recent: () => route(`${prefix}.recent`),
        markRead: (id) => route(`${prefix}.mark-read`, id),
        markAllRead: () => route(`${prefix}.mark-all-read`),
    };
});

const badgeLabel = computed(() => {
    if (unreadCount.value <= 0) return null;
    return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
});

watch(
    () => page.props.nav?.unread_notices_count,
    (value) => {
        unreadCount.value = Number(value ?? 0);
    },
);

function formatPublished(iso) {
    if (!iso) return '';
    return formatRelativeDate(iso.slice(0, 10));
}

async function fetchNotices() {
    loading.value = true;
    try {
        const { data } = await axios.get(routes.value.recent());
        notices.value = data.notices ?? [];
        unreadCount.value = Number(data.unread_count ?? 0);
    } catch {
        notices.value = [];
    } finally {
        loading.value = false;
    }
}

async function refreshUnreadCount() {
    if (!showNotices.value || open.value) return;

    try {
        const { data } = await axios.get(routes.value.recent());
        unreadCount.value = Number(data.unread_count ?? unreadCount.value);
    } catch {
        // silencioso — o badge continua com o último valor conhecido
    }
}

async function markRead(notice) {
    if (notice.read) return;

    try {
        const { data } = await axios.post(routes.value.markRead(notice.id));
        notice.read = true;
        unreadCount.value = Number(data.unread_count ?? unreadCount.value);
    } catch {
        // silencioso
    }
}

async function markAllRead() {
    if (unreadCount.value <= 0) return;

    try {
        const { data } = await axios.post(routes.value.markAllRead());
        notices.value = notices.value.map((notice) => ({ ...notice, read: true }));
        unreadCount.value = Number(data.unread_count ?? 0);
    } catch {
        // silencioso
    }
}

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function closeOnEscape(event) {
    if (open.value && event.key === 'Escape') {
        close();
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        fetchNotices();
    }
});

function startPolling() {
    if (pollTimer || !showNotices.value) return;
    pollTimer = window.setInterval(() => {
        if (!document.hidden) {
            refreshUnreadCount();
        }
    }, POLL_INTERVAL_MS);
}

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    startPolling();
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
});
</script>

<template>
    <div v-if="showNotices" class="relative">
        <button
            type="button"
            class="relative rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-label="Avisos"
            @click.stop="toggle"
        >
            <BellIcon class="h-6 w-6" />
            <span
                v-if="badgeLabel"
                class="absolute right-1 top-1 flex min-h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-none text-white"
            >
                {{ badgeLabel }}
            </span>
        </button>

        <Teleport to="body">
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
                    class="fixed inset-y-0 right-0 z-[210] flex w-full max-w-sm flex-col overflow-hidden rounded-l-2xl bg-white shadow-2xl ring-1 ring-slate-200/70"
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

                    <div class="flex-1 overflow-y-auto">
                        <div v-if="loading" class="px-5 py-12 text-center text-sm text-slate-500">
                            A carregar…
                        </div>

                        <ul v-else-if="notices.length" class="divide-y divide-slate-100">
                            <li v-for="notice in notices" :key="notice.id">
                                <button
                                    type="button"
                                    class="flex w-full gap-3 px-5 py-4 text-left transition hover:bg-slate-50"
                                    :class="notice.read ? 'opacity-80' : 'bg-rose-50/40'"
                                    @click="markRead(notice)"
                                >
                                    <span
                                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                        :class="notice.read ? 'bg-transparent' : 'bg-rose-600'"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0 flex-1">
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
                                </button>
                            </li>
                        </ul>

                        <div v-else class="flex flex-col items-center justify-center px-5 py-16 text-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <BellIcon class="h-6 w-6" />
                            </span>
                            <p class="mt-3 text-sm text-slate-500">Nenhum aviso por enquanto.</p>
                        </div>
                    </div>

                    <footer
                        v-if="unreadCount > 0"
                        class="shrink-0 border-t border-slate-100 bg-slate-50/80 px-5 py-3"
                    >
                        <button
                            type="button"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-talents-700 shadow-sm transition hover:border-talents-200 hover:bg-talents-50 hover:text-talents-900"
                            @click="markAllRead"
                        >
                            Marcar todos como lidos
                        </button>
                    </footer>
                </aside>
            </Transition>
        </Teleport>
    </div>
</template>
