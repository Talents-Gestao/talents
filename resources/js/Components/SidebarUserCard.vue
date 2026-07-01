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
        'group flex min-h-10 w-full items-center overflow-hidden rounded-xl border bg-white text-left text-sm transition-[background-color,border-color,box-shadow] duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-talents-500/30';
    const layout = props.collapsed ? '' : 'pr-2.5';
    const state = props.active
        ? 'border-talents-200 shadow-sm ring-1 ring-talents-200/70'
        : 'border-slate-200 hover:border-talents-200 hover:bg-slate-50/70';

    return `${base} ${layout} ${state}`;
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
            class="flex h-10 w-[2.7rem] shrink-0 items-center justify-center"
            aria-hidden="true"
        >
            <UserCircleIcon class="size-6 shrink-0 text-talents-700" />
        </span>

        <Transition name="fade">
            <span
                v-if="!collapsed"
                class="min-w-0 flex-1 truncate font-medium leading-snug text-slate-900"
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
