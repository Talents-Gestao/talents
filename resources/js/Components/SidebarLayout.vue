<script setup>
import AppTopBar from '@/Components/AppTopBar.vue';
import NoticeBellDropdown from '@/Components/NoticeBellDropdown.vue';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    useSlots,
    watch,
} from 'vue';

const LABELS_SHOW_DELAY_MS = 80;
const WIDTH_COLLAPSE_START_MS = 120;

defineProps({
    shellClass: {
        type: String,
        default: 'app-shell',
    },
    showTopBar: {
        type: Boolean,
        default: true,
    },
    topBarTitle: {
        type: String,
        default: '',
    },
    topBarSearchPlaceholder: {
        type: String,
        default: 'Buscar…',
    },
    topBarShowSearch: {
        type: Boolean,
        default: true,
    },
    topBarShowActions: {
        type: Boolean,
        default: true,
    },
    topBarShowFiles: {
        type: Boolean,
        default: true,
    },
});

const slots = useSlots();

const asideHovered = ref(false);
const labelsVisible = ref(false);
const mobileOpen = ref(false);

const navEl = ref(null);
const canScrollMore = ref(false);

let showLabelsTimer = null;
let hideWidthTimer = null;
let navResizeObserver = null;
let navMutationObserver = null;

const clearSidebarTimers = () => {
    if (showLabelsTimer) {
        clearTimeout(showLabelsTimer);
        showLabelsTimer = null;
    }
    if (hideWidthTimer) {
        clearTimeout(hideWidthTimer);
        hideWidthTimer = null;
    }
};

const updateScrollHints = () => {
    const el = navEl.value;
    if (!el) {
        canScrollMore.value = false;
        return;
    }

    const overflow = el.scrollHeight - el.clientHeight > 2;
    const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 2;
    canScrollMore.value = overflow && !atBottom;
};

const onAsideEnter = () => {
    clearSidebarTimers();
    asideHovered.value = true;
    showLabelsTimer = setTimeout(() => {
        labelsVisible.value = true;
        nextTick(updateScrollHints);
    }, LABELS_SHOW_DELAY_MS);
};

const onAsideLeave = () => {
    if (mobileOpen.value) {
        return;
    }
    clearSidebarTimers();
    labelsVisible.value = false;
    hideWidthTimer = setTimeout(() => {
        asideHovered.value = false;
        nextTick(updateScrollHints);
    }, WIDTH_COLLAPSE_START_MS);
};

onMounted(() => {
    const el = navEl.value;
    if (!el) {
        return;
    }

    if (typeof ResizeObserver !== 'undefined') {
        navResizeObserver = new ResizeObserver(() => updateScrollHints());
        navResizeObserver.observe(el);
    }

    if (typeof MutationObserver !== 'undefined') {
        navMutationObserver = new MutationObserver(() => updateScrollHints());
        navMutationObserver.observe(el, { childList: true, subtree: true });
    }

    nextTick(updateScrollHints);
});

onBeforeUnmount(() => {
    clearSidebarTimers();
    navResizeObserver?.disconnect();
    navMutationObserver?.disconnect();
});

watch(mobileOpen, (open) => {
    clearSidebarTimers();
    if (open) {
        asideHovered.value = true;
        labelsVisible.value = true;
        nextTick(updateScrollHints);
        return;
    }
    labelsVisible.value = false;
    asideHovered.value = false;
    nextTick(updateScrollHints);
});

const collapsed = computed(() => {
    if (mobileOpen.value) {
        return false;
    }
    return !labelsVisible.value;
});

watch(collapsed, () => nextTick(updateScrollHints));

const asideWide = computed(() => asideHovered.value || mobileOpen.value);
const compact = computed(() => !asideWide.value && !mobileOpen.value);

const closeMobileSidebar = () => {
    mobileOpen.value = false;
};

provide('closeMobileSidebar', closeMobileSidebar);
provide('sidebarCompact', compact);

/** Acordeão: no máximo um SidebarNavGroup aberto de cada vez. */
const openNavGroupId = ref(null);
provide('sidebarNavAccordion', {
    openGroupId: openNavGroupId,
    setOpenGroup(id) {
        openNavGroupId.value = id;
    },
    clearOpenGroup(id) {
        if (openNavGroupId.value === id) {
            openNavGroupId.value = null;
        }
    },
});

watch(openNavGroupId, () => {
    // Aguarda animação de max-height do submenu (~200ms).
    window.setTimeout(() => updateScrollHints(), 220);
});

const asideWidthClass = computed(() => (asideWide.value ? 'lg:w-64' : 'lg:w-16'));

const asideTransformClass = computed(() =>
    mobileOpen.value ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
);

const sidebarTransitionClass =
    'transition-[width,transform] duration-[240ms] ease-in-out';

const mainTransitionClass =
    'transition-[margin-left] duration-[240ms] ease-in-out';

const mainMarginClass = computed(() =>
    asideWide.value
        ? 'lg:ml-[calc(1rem+16rem+1rem)]'
        : 'lg:ml-[calc(1rem+4rem+1rem)]',
);

const hasAside = computed(() => Boolean(slots.aside));
</script>

<template>
    <div :class="shellClass">
        <div
            v-show="mobileOpen"
            class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-sm transition-opacity duration-300 lg:hidden"
            aria-hidden="true"
            @click="closeMobileSidebar"
        />

        <aside
            :class="[
                asideWidthClass,
                asideTransformClass,
                sidebarTransitionClass,
                'fixed z-50 flex w-64 flex-col overflow-hidden border border-white/80 bg-white/95 shadow-shell ring-1 ring-slate-200/40 will-change-[width,transform]',
                'inset-y-0 left-0 max-h-screen rounded-none lg:inset-auto lg:left-4 lg:top-4 lg:h-[calc(100vh-2rem)] lg:max-h-[calc(100vh-2rem)] lg:rounded-3xl lg:shadow-shell',
            ]"
            @mouseenter="onAsideEnter"
            @mouseleave="onAsideLeave"
        >
            <div class="flex h-full min-h-0 flex-col lg:overflow-visible">
                <div
                    class="flex min-h-[3.25rem] shrink-0 items-center border-b border-slate-200/70 px-2 py-3"
                >
                    <slot name="logo" :collapsed="collapsed" :compact="compact" />
                </div>

                <div class="relative min-h-0 flex-1">
                    <nav
                        ref="navEl"
                        class="h-full overflow-y-auto overflow-x-hidden overscroll-y-contain py-4 scrollbar-none"
                        @scroll.passive="updateScrollHints"
                    >
                        <div class="flex flex-col gap-0.5 px-2">
                            <slot name="navigation" :collapsed="collapsed" :compact="compact" />
                        </div>
                    </nav>

                    <div
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-7 bg-gradient-to-t from-white/70 via-white/25 to-transparent transition-opacity duration-200"
                        :class="canScrollMore ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    />
                </div>

                <div class="shrink-0 overflow-hidden border-t border-slate-200/70 px-2 py-2">
                    <slot name="user" :collapsed="collapsed" :compact="compact" />
                </div>
            </div>

            <button
                type="button"
                class="absolute right-2 top-3 rounded-lg p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 lg:hidden"
                @click="closeMobileSidebar"
            >
                <span class="sr-only">Fechar menu</span>
                <XMarkIcon class="h-6 w-6" />
            </button>
        </aside>

        <div :class="['flex min-h-screen flex-1 flex-col', mainTransitionClass, mainMarginClass]">
            <div
                class="app-shell-panel mx-3 mb-4 mt-0 flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden shadow-shell-inner sm:mx-4 lg:mx-4 lg:mb-6 lg:mt-4 lg:min-h-[calc(100vh-2rem)]"
            >
                <div
                    class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-slate-200/60 bg-white/90 px-4 backdrop-blur-md lg:hidden"
                >
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl p-2 text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-talents-500/40"
                        @click="mobileOpen = true"
                    >
                        <span class="sr-only">Abrir menu</span>
                        <Bars3Icon class="h-6 w-6" />
                    </button>
                    <span class="text-sm font-semibold tracking-tight text-slate-900">Menu</span>
                    <div class="ml-auto">
                        <NoticeBellDropdown />
                    </div>
                </div>

                <slot v-if="$slots.topbar" name="topbar" />
                <AppTopBar
                    v-else-if="showTopBar"
                    class="hidden sm:flex"
                    :title="topBarTitle"
                    :search-placeholder="topBarSearchPlaceholder"
                    :show-search="topBarShowSearch"
                    :show-actions="topBarShowActions"
                    :show-files="topBarShowFiles"
                />

                <header
                    v-if="$slots.header"
                    class="shrink-0 border-b border-slate-200/50 bg-white/60 px-4 py-4 backdrop-blur-sm sm:px-6 sm:py-5 lg:px-8"
                >
                    <slot name="header" />
                </header>

                <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
                    <main class="min-h-0 flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                        <slot />
                    </main>

                    <aside
                        v-if="hasAside"
                        class="shrink-0 border-t border-slate-200/50 bg-slate-50/40 px-4 py-5 backdrop-blur-sm lg:w-80 lg:border-l lg:border-t-0 lg:px-5"
                    >
                        <slot name="aside" />
                    </aside>
                </div>
            </div>
        </div>
    </div>
</template>
