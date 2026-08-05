<script setup>
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';

defineProps({
    href: {
        type: String,
        required: true,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    isolatedIcon: {
        type: Boolean,
        default: false,
    },
    iconSrc: {
        type: String,
        default: '/images/logo.png',
    },
});

const closeMobileSidebar = inject('closeMobileSidebar', null);

const onNavigate = () => {
    if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
    }
};
</script>

<template>
    <Link
        :href="href"
        class="group flex h-10 w-full min-w-0 cursor-pointer items-center overflow-hidden rounded-xl transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500/40"
        :title="collapsed ? 'Ir para a home' : undefined"
        aria-label="Ir para a home"
        @click="onNavigate"
    >
        <span
            :class="isolatedIcon ? 'flex h-10 w-[2.7rem] items-center justify-center' : 'relative h-10 w-[2.7rem] overflow-hidden'"
            class="shrink-0"
            aria-hidden="true"
        >
            <img
                :src="iconSrc"
                alt=""
                :class="isolatedIcon ? 'block h-10 w-10 object-contain' : 'block h-10 w-[6.375rem]'"
            />
        </span>

        <Transition name="fade">
            <span
                v-if="!collapsed"
                class="ml-3 min-w-0 whitespace-nowrap leading-none"
            >
                <span class="block truncate text-base font-semibold tracking-tight text-talents-800">
                    Talents
                </span>
                <span class="mt-0.5 block truncate text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                    GESTÃO DE PESSOAS
                </span>
            </span>
        </Transition>
    </Link>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 100ms ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
