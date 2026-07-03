<script setup>
import { BellIcon } from '@heroicons/vue/24/outline';
import { formatRelativeDate } from '@/utils/dateOnly';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();

const open = ref(false);
const loading = ref(false);
const notices = ref([]);
const unreadCount = ref(Number(page.props.nav?.unread_notices_count ?? 0));

const rootRef = ref(null);
const menuStyle = ref({});

const showNotices = computed(() => Boolean(page.props.auth?.user?.company_id));

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
        const { data } = await axios.get(route('client.notices.recent'));
        notices.value = data.notices ?? [];
        unreadCount.value = Number(data.unread_count ?? 0);
    } catch {
        notices.value = [];
    } finally {
        loading.value = false;
    }
}

async function markRead(notice) {
    if (notice.read) return;

    try {
        const { data } = await axios.post(route('client.notices.mark-read', notice.id));
        notice.read = true;
        unreadCount.value = Number(data.unread_count ?? unreadCount.value);
    } catch {
        // silencioso — o utilizador pode marcar na página completa
    }
}

async function markAllRead() {
    if (unreadCount.value <= 0) return;

    try {
        const { data } = await axios.post(route('client.notices.mark-all-read'));
        notices.value = notices.value.map((notice) => ({ ...notice, read: true }));
        unreadCount.value = Number(data.unread_count ?? 0);
    } catch {
        // silencioso
    }
}

function positionMenu() {
    const root = rootRef.value;
    if (!root || !open.value) return;

    const triggerEl = root.querySelector('[data-notice-bell-trigger]');
    if (!triggerEl) return;

    const rect = triggerEl.getBoundingClientRect();
    const panelWidth = 360;
    const gap = 8;
    let left = rect.right - panelWidth;
    left = Math.max(8, Math.min(left, window.innerWidth - panelWidth - 8));

    menuStyle.value = {
        left: `${left}px`,
        top: `${rect.bottom + gap}px`,
        width: `${panelWidth}px`,
    };
}

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function onViewportChange() {
    if (open.value) positionMenu();
}

function closeOnEscape(event) {
    if (open.value && event.key === 'Escape') {
        close();
    }
}

watch(open, async (isOpen) => {
    if (isOpen) {
        await fetchNotices();
        await nextTick();
        positionMenu();
    }
});

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<template>
    <div v-if="showNotices" ref="rootRef" class="relative">
        <button
            type="button"
            data-notice-bell-trigger
            class="relative rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
            :aria-expanded="open"
            aria-haspopup="true"
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
            <div
                v-show="open"
                class="fixed inset-0 z-[200]"
                aria-hidden="true"
                @click="close"
            />

            <div
                v-show="open"
                class="fixed z-[210] overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200/80"
                :style="menuStyle"
                role="menu"
            >
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-900">Avisos</h3>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-talents-700 transition hover:text-talents-900"
                        @click.stop="markAllRead"
                    >
                        Marcar todos como lidos
                    </button>
                </div>

                <div class="max-h-[min(24rem,60vh)] overflow-y-auto">
                    <div v-if="loading" class="px-4 py-8 text-center text-sm text-slate-500">
                        A carregar…
                    </div>

                    <ul v-else-if="notices.length" class="divide-y divide-slate-100">
                        <li v-for="notice in notices" :key="notice.id">
                            <button
                                type="button"
                                class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
                                :class="notice.read ? 'opacity-80' : 'bg-rose-50/40'"
                                @click.stop="markRead(notice)"
                            >
                                <span
                                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                                    :class="notice.read ? 'bg-transparent' : 'bg-rose-600'"
                                    aria-hidden="true"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-900">
                                        {{ notice.title }}
                                    </span>
                                    <span class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-600">
                                        {{ notice.body }}
                                    </span>
                                    <time
                                        class="mt-1 block text-[11px] text-slate-400"
                                        :datetime="notice.published_at"
                                    >
                                        {{ formatPublished(notice.published_at) }}
                                    </time>
                                </span>
                            </button>
                        </li>
                    </ul>

                    <p v-else class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum aviso por enquanto.
                    </p>
                </div>

                <div class="border-t border-slate-100 px-4 py-2.5">
                    <Link
                        :href="route('client.notices.index')"
                        class="block rounded-lg py-2 text-center text-sm font-medium text-talents-700 transition hover:bg-slate-50 hover:text-talents-900"
                        @click="close"
                    >
                        Ver todos os avisos
                    </Link>
                </div>
            </div>
        </Teleport>
    </div>
</template>
