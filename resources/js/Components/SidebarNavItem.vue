<script setup>
import { computed, inject } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
    icon: {
        type: [Object, Function],
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    badge: {
        type: String,
        default: null,
    },
});

const closeMobileSidebar = inject('closeMobileSidebar', null);

const linkClasses = computed(() => {
    const base =
        'group relative flex items-center gap-3 rounded-lg border-l-2 py-2 text-sm font-medium transition duration-150 ease-in-out';
    if (props.collapsed) {
        if (props.active) {
            return `${base} justify-center border-transparent bg-talents-50/90 px-2 text-talents-800`;
        }
        return `${base} justify-center border-transparent px-2 text-zinc-600 hover:bg-zinc-100/90 hover:text-zinc-900`;
    }
    if (props.active) {
        return `${base} border-talents-600 bg-talents-50/80 px-3 text-talents-800`;
    }
    return `${base} border-transparent px-3 text-zinc-600 hover:border-zinc-200 hover:bg-zinc-50 hover:text-zinc-900`;
});

const iconClasses = computed(() =>
    props.active ? 'text-talents-700' : 'text-zinc-400 group-hover:text-zinc-700',
);

const onNavigate = () => {
    if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
    }
};
</script>

<template>
    <Link
        :href="href"
        :class="linkClasses"
        :title="collapsed ? label : undefined"
        @click="onNavigate"
    >
        <component :is="icon" class="h-5 w-5 shrink-0" :class="iconClasses" />
        <span v-if="!collapsed" class="min-w-0 flex-1 truncate">{{ label }}</span>
        <span
            v-if="badge && !collapsed"
            class="ml-auto shrink-0 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-900 ring-1 ring-amber-200/80"
        >
            {{ badge }}
        </span>
    </Link>
</template>
