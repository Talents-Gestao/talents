<script setup>
import { computed, provide, ref } from 'vue';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';

defineProps({
    /** Classes for the outer shell */
    shellClass: {
        type: String,
        default: 'min-h-screen bg-zinc-50',
    },
});

const asideHovered = ref(false);
const mobileOpen = ref(false);

/** Desktop: barra estreita com ícones; expandida ao passar o mouse. Mobile (drawer): sempre com rótulos. */
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

const mainPaddingClass = computed(() =>
    asideHovered.value ? 'lg:pl-64' : 'lg:pl-16',
);
</script>

<template>
    <div :class="shellClass">
        <!-- Mobile backdrop -->
        <div
            v-show="mobileOpen"
            class="fixed inset-0 z-40 bg-zinc-950/25 backdrop-blur-[2px] lg:hidden"
            aria-hidden="true"
            @click="closeMobileSidebar"
        />

        <!-- Sidebar: em desktop expande ao hover; sem toggle por clique -->
        <aside
            :class="[
                asideWidthClass,
                asideTransformClass,
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-zinc-200/90 bg-white transition-all duration-300 ease-in-out lg:overflow-visible',
            ]"
            @mouseenter="asideHovered = true"
            @mouseleave="asideHovered = false"
        >
            <div class="flex h-full min-h-0 flex-col lg:overflow-visible">
                <!-- Logo: sem overflow para não cortar a imagem -->
                <div
                    class="flex min-h-[3.25rem] shrink-0 items-center border-b border-zinc-200/90 px-2 py-2"
                    :class="collapsed ? 'justify-center' : 'justify-start'"
                >
                    <slot name="logo" :collapsed="collapsed" />
                </div>

                <!-- Nav -->
                <nav class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-2 py-4">
                    <div class="flex flex-col gap-0.5">
                        <slot name="navigation" :collapsed="collapsed" />
                    </div>
                </nav>

                <!-- Footer: utilizador (overflow visível para menus absolutos acima do botão) -->
                <div class="shrink-0 overflow-visible border-t border-zinc-200/90 p-2">
                    <slot name="user" :collapsed="collapsed" />
                </div>
            </div>

            <!-- Mobile close inside drawer -->
            <button
                type="button"
                class="absolute right-2 top-3 rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 lg:hidden"
                @click="closeMobileSidebar"
            >
                <span class="sr-only">Fechar menu</span>
                <XMarkIcon class="h-6 w-6" />
            </button>
        </aside>

        <!-- Main -->
        <div :class="['transition-[padding] duration-300 ease-in-out', mainPaddingClass]">
            <!-- Mobile top bar -->
            <div
                class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-zinc-200/90 bg-white/95 px-4 backdrop-blur-sm lg:hidden"
            >
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg p-2 text-zinc-700 transition hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-talents-500/35"
                    @click="mobileOpen = true"
                >
                    <span class="sr-only">Abrir menu</span>
                    <Bars3Icon class="h-6 w-6" />
                </button>
                <span class="text-sm font-medium tracking-tight text-zinc-900">Menu</span>
            </div>

            <header v-if="$slots.header" class="border-b border-zinc-200/80 bg-white">
                <div class="px-4 py-5 sm:px-6 sm:py-6 lg:px-10">
                    <slot name="header" />
                </div>
            </header>

            <main class="px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
                <slot />
            </main>
        </div>
    </div>
</template>
