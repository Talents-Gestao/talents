<script setup>
import AppTopBar from '@/Components/AppTopBar.vue';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, provide, ref, useSlots } from 'vue';

defineProps({
    /** Classes do invólucro exterior (gradiente em app.css) */
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
});

const slots = useSlots();

const asideHovered = ref(false);
const mobileOpen = ref(false);

const collapsed = computed(() => {
    if (mobileOpen.value) {
        return false;
    }
    return !asideHovered.value;
});

const closeMobileSidebar = () => {
    mobileOpen.value = false;
};

provide('closeMobileSidebar', closeMobileSidebar);

const asideWidthClass = computed(() =>
    asideHovered.value ? 'lg:w-64' : 'lg:w-16',
);

const asideTransformClass = computed(() =>
    mobileOpen.value ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
);

/** Margem esquerda = margem + largura da sidebar + gap (desktop) */
const mainMarginClass = computed(() =>
    asideHovered.value
        ? 'lg:ml-[calc(1rem+16rem+1rem)]'
        : 'lg:ml-[calc(1rem+4rem+1rem)]',
);

const hasAside = computed(() => Boolean(slots.aside));
</script>

<template>
    <div :class="shellClass">
        <!-- Backdrop mobile -->
        <div
            v-show="mobileOpen"
            class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
            @click="closeMobileSidebar"
        />

        <!-- Sidebar: drawer no mobile; no desktop “pill” com hover -->
        <aside
            :class="[
                asideWidthClass,
                asideTransformClass,
                'fixed z-50 flex w-64 flex-col overflow-hidden border border-white/80 bg-white/95 shadow-shell ring-1 ring-slate-200/40 transition-all duration-300 ease-in-out',
                'inset-y-0 left-0 max-h-screen rounded-none lg:inset-auto lg:left-4 lg:top-4 lg:h-[calc(100vh-2rem)] lg:max-h-[calc(100vh-2rem)] lg:rounded-3xl lg:shadow-shell',
            ]"
            @mouseenter="asideHovered = true"
            @mouseleave="asideHovered = false"
        >
            <div class="flex h-full min-h-0 flex-col lg:overflow-visible">
                <div
                    class="flex min-h-[3.25rem] shrink-0 items-center border-b border-slate-200/70 px-2 py-3"
                    :class="collapsed ? 'justify-center' : 'justify-start'"
                >
                    <slot name="logo" :collapsed="collapsed" />
                </div>

                <nav
                    class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-y-contain px-2 py-4 scrollbar-none"
                >
                    <div class="flex flex-col gap-1">
                        <slot name="navigation" :collapsed="collapsed" />
                    </div>
                </nav>

                <div class="shrink-0 overflow-visible border-t border-slate-200/70 p-2">
                    <slot name="user" :collapsed="collapsed" />
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

        <!-- Coluna principal + painel -->
        <div
            :class="[
                'flex min-h-screen flex-1 flex-col transition-[margin] duration-300 ease-in-out',
                mainMarginClass,
            ]"
        >
            <div
                class="app-shell-panel mx-3 mb-4 mt-0 flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden shadow-shell-inner sm:mx-4 lg:mx-4 lg:mb-6 lg:mt-4 lg:min-h-[calc(100vh-2rem)]"
            >
                <!-- Mobile: topo -->
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
                </div>

                <slot v-if="$slots.topbar" name="topbar" />
                <AppTopBar
                    v-else-if="showTopBar"
                    class="hidden sm:flex"
                    :title="topBarTitle"
                    :search-placeholder="topBarSearchPlaceholder"
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
