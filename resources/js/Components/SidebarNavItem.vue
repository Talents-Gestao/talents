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
        'group flex items-center gap-3 rounded-lg py-2.5 text-sm font-medium transition duration-150 ease-in-out';
    const padding = props.collapsed ? 'justify-center px-2' : 'px-3';
    if (props.active) {
        return `${base} ${padding} bg-talents-50 text-talents-700`;
    }
    return `${base} ${padding} text-gray-600 hover:bg-talents-50 hover:text-talents-900`;
});

const iconClasses = computed(() =>
    props.active ? 'text-talents-600' : 'text-gray-400 group-hover:text-talents-600',
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
            class="ml-auto shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900"
        >
            {{ badge }}
        </span>
    </Link>
</template>
