<script setup>
import { Link } from '@inertiajs/vue3';

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
</script>

<template>
    <Link
        :href="href"
        class="flex h-10 w-full min-w-0 items-center overflow-hidden rounded-xl"
        :title="collapsed ? 'Talents' : undefined"
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
