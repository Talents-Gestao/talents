import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const POLL_INTERVAL_MS = 30000;

const unreadCount = ref(0);
const unreadAssignedLeadsCount = ref(0);
const notices = ref([]);
const pageNumber = ref(1);
const hasMore = ref(false);
const loading = ref(false);
const loadingMore = ref(false);
const open = ref(false);
const toast = ref(null);
const hydrated = ref(false);

let pollTimer = null;
let pollSubscribers = 0;
let panelHostAssigned = false;
let inertiaWatchStarted = false;
let toastTimer = null;

function noticesContextFromPage(page) {
    return page.props.nav?.notices_context ?? null;
}

function routesFor(page) {
    const isTalents = noticesContextFromPage(page) === 'talents';
    const prefix = isTalents ? 'admin.notices' : 'client.notices';

    return {
        recent: () => route(`${prefix}.recent`),
        markAllRead: () => route(`${prefix}.mark-all-read`),
        open: (id) => route(`${prefix}.open`, id),
        destroy: (id) => route(`${prefix}.destroy`, id),
        destroyAll: () => route(`${prefix}.destroy-all`),
    };
}

function currentUserId(page) {
    return Number(page.props.auth?.user?.id ?? 0);
}

function isAssignedLeadForMe(notice, page) {
    const uid = currentUserId(page);
    return Boolean(
        notice
        && !notice.read
        && uid > 0
        && Number(notice.target_user_id) === uid,
    );
}

function showToastFor(notice, page) {
    if (!isAssignedLeadForMe(notice, page)) {
        return;
    }

    toast.value = {
        id: notice.id,
        title: notice.title,
        body: notice.body,
        href: notice.url || routesFor(page).open(notice.id),
    };

    if (toastTimer) {
        window.clearTimeout(toastTimer);
    }
    toastTimer = window.setTimeout(() => {
        toast.value = null;
        toastTimer = null;
    }, 8000);
}

function pickNewestAssignedLead(page) {
    return notices.value.find((item) => isAssignedLeadForMe(item, page)) ?? null;
}

function applyUnreadCount(next) {
    const count = Number(next ?? 0);
    unreadCount.value = Number.isFinite(count) ? count : 0;
}

function applyAssignedLeadsCount(next, page, { announce = false } = {}) {
    const previous = unreadAssignedLeadsCount.value;
    const count = Number(next ?? 0);
    unreadAssignedLeadsCount.value = Number.isFinite(count) ? count : 0;

    if (unreadAssignedLeadsCount.value <= 0) {
        toast.value = null;
        if (toastTimer) {
            window.clearTimeout(toastTimer);
            toastTimer = null;
        }
    }

    if (!hydrated.value) {
        hydrated.value = true;
        return;
    }

    if (!announce || unreadAssignedLeadsCount.value <= previous) {
        return;
    }

    const newest = pickNewestAssignedLead(page);
    if (newest) {
        showToastFor(newest, page);
        return;
    }

    fetchRecent(page, { announce: false }).then(() => {
        const loaded = pickNewestAssignedLead(page);
        if (loaded) {
            showToastFor(loaded, page);
        }
    });
}

function applyCountsFromPayload(data, page, { announce = false } = {}) {
    applyUnreadCount(data.unread_count ?? unreadCount.value);
    applyAssignedLeadsCount(
        data.unread_assigned_leads_count ?? unreadAssignedLeadsCount.value,
        page,
        { announce },
    );
}

async function fetchRecent(page, { append = false, announce = false } = {}) {
    if (noticesContextFromPage(page) === null) {
        return;
    }

    if (append) {
        if (!hasMore.value || loadingMore.value || loading.value) {
            return;
        }
        loadingMore.value = true;
    } else {
        loading.value = true;
    }

    const nextPage = append ? pageNumber.value + 1 : 1;

    try {
        const { data } = await axios.get(routesFor(page).recent(), {
            params: { page: nextPage },
        });
        const incoming = data.notices ?? [];
        notices.value = append ? [...notices.value, ...incoming] : incoming;
        hasMore.value = Boolean(data.has_more);
        pageNumber.value = nextPage;
        applyCountsFromPayload(data, page, { announce });
    } catch {
        if (!append) {
            notices.value = [];
        }
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function startPolling(page) {
    if (pollTimer || noticesContextFromPage(page) === null) {
        return;
    }
    pollTimer = window.setInterval(() => {
        if (!document.hidden && !open.value) {
            fetchRecent(page, { announce: true });
        }
    }, POLL_INTERVAL_MS);
}

function stopPolling() {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

export function useUnreadNotices({ panel = false } = {}) {
    const page = usePage();
    const isPanelHost = ref(false);

    const showNotices = computed(() => noticesContextFromPage(page) !== null);
    const isTalentsContext = computed(() => noticesContextFromPage(page) === 'talents');
    const routes = computed(() => routesFor(page));
    const badgeLabel = computed(() => {
        if (unreadCount.value <= 0) {
            return null;
        }
        return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
    });
    const assignedBadgeLabel = computed(() => {
        if (unreadAssignedLeadsCount.value <= 0) {
            return null;
        }
        return unreadAssignedLeadsCount.value > 99
            ? '99+'
            : String(unreadAssignedLeadsCount.value);
    });
    const assignedArrivalLabel = computed(() => {
        if (unreadAssignedLeadsCount.value <= 0) {
            return null;
        }
        return unreadAssignedLeadsCount.value === 1
            ? '1 lead para você'
            : `${assignedBadgeLabel.value} leads para você`;
    });

    if (!inertiaWatchStarted) {
        inertiaWatchStarted = true;
        unreadCount.value = Number(page.props.nav?.unread_notices_count ?? 0);
        unreadAssignedLeadsCount.value = Number(
            page.props.nav?.unread_assigned_leads_count ?? 0,
        );
        hydrated.value = true;
        watch(
            () => page.props.nav?.unread_notices_count,
            (value) => {
                applyUnreadCount(value);
            },
        );
        watch(
            () => page.props.nav?.unread_assigned_leads_count,
            (value) => {
                applyAssignedLeadsCount(value, page, { announce: true });
            },
        );
    }

    onMounted(() => {
        pollSubscribers += 1;
        if (panel && !panelHostAssigned) {
            panelHostAssigned = true;
            isPanelHost.value = true;
        }
        if (pollSubscribers === 1) {
            startPolling(page);
        }
    });

    onUnmounted(() => {
        pollSubscribers = Math.max(0, pollSubscribers - 1);
        if (isPanelHost.value) {
            panelHostAssigned = false;
            isPanelHost.value = false;
        }
        if (pollSubscribers === 0) {
            stopPolling();
        }
    });

    const toggle = () => {
        open.value = !open.value;
    };

    const close = () => {
        open.value = false;
    };

    const dismissToast = () => {
        toast.value = null;
        if (toastTimer) {
            window.clearTimeout(toastTimer);
            toastTimer = null;
        }
    };

    const openFromToast = () => {
        dismissToast();
        open.value = true;
    };

    return {
        unreadCount,
        unreadAssignedLeadsCount,
        notices,
        pageNumber,
        hasMore,
        loading,
        loadingMore,
        open,
        toast,
        showNotices,
        isTalentsContext,
        isPanelHost,
        routes,
        badgeLabel,
        assignedBadgeLabel,
        assignedArrivalLabel,
        toggle,
        close,
        dismissToast,
        openFromToast,
        fetchNotices: (options = {}) => fetchRecent(page, options),
        applyUnreadCount: (next) => applyUnreadCount(next),
        applyCountsFromPayload: (data, options = {}) =>
            applyCountsFromPayload(data, page, options),
    };
}
