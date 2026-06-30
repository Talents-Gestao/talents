<script setup>
import { UserCircleIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, inject } from 'vue';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: [Boolean, null],
        default: null,
    },
});

const closeMobileSidebar = inject('closeMobileSidebar', null);

const cardClasses = computed(() => {
    const base =
        'group flex min-h-12 w-full items-center overflow-hidden rounded-2xl border bg-white text-left text-sm transition-[background-color,border-color,box-shadow] duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-talents-500/30';
    const state = props.active
        ? 'border-talents-200 shadow-sm ring-1 ring-talents-200/70'
        : 'border-slate-200 shadow-sm hover:border-talents-200 hover:bg-slate-50/70';

    return `${base} ${state}`;
});

const onNavigate = () => {
    if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
    }
};
</script>

<template>
    <Link
        :href="href"
        :class="cardClasses"
        :title="collapsed ? label : undefined"
        @click="onNavigate"
    >
        <span
            class="flex h-12 w-12 shrink-0 items-center justify-center"
            aria-hidden="true"
        >
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-talents-300 bg-talents-50 text-talents-700 shadow-sm transition-colors duration-200 group-hover:border-talents-400">
                <UserCircleIcon class="h-5 w-5" />
            </span>
        </span>

        <Transition name="fade">
            <span
                v-if="!collapsed"
                class="min-w-0 truncate font-medium leading-snug text-slate-800"
            >
                {{ label }}
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
