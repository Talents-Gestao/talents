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
        default: null,
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
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'minimal'].includes(value),
    },
});

const closeMobileSidebar = inject('closeMobileSidebar', null);

const isMinimal = computed(() => props.variant === 'minimal');

const linkClasses = computed(() => {
    if (isMinimal.value) {
        const base =
            'group flex items-center gap-3 py-2.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-900 transition duration-150 ease-in-out';
        if (props.collapsed) {
            return `${base} justify-center px-2`;
        }
        if (props.active) {
            return `${base} px-1`;
        }
        return `${base} px-1 hover:text-slate-600`;
    }

    const base =
        'group relative flex items-center gap-3 rounded-2xl border border-transparent py-2.5 text-sm font-medium transition duration-150 ease-in-out';
    if (props.collapsed) {
        if (props.active) {
            return `${base} justify-center bg-talents-100/90 px-2 text-talents-900 shadow-sm ring-1 ring-talents-200/60`;
        }
        return `${base} justify-center px-2 text-slate-600 hover:bg-slate-100/90 hover:text-slate-900`;
    }
    if (props.active) {
        return `${base} bg-talents-100/90 px-3 text-talents-900 shadow-sm ring-1 ring-talents-300/50`;
    }
    return `${base} px-3 text-slate-600 hover:bg-slate-100/80 hover:text-slate-900`;
});

const iconClasses = computed(() =>
    props.active ? 'text-talents-700' : 'text-slate-400 group-hover:text-talents-600',
);

const indicatorClasses = computed(() =>
    props.active ? 'bg-slate-900' : 'bg-slate-200 group-hover:bg-slate-400',
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
        <span
            v-if="isMinimal"
            class="h-5 w-1 shrink-0 rounded-full transition-colors"
            :class="indicatorClasses"
            aria-hidden="true"
        />
        <component
            v-else-if="icon"
            :is="icon"
            class="h-5 w-5 shrink-0"
            :class="iconClasses"
        />
        <span v-if="!collapsed" class="min-w-0 flex-1 leading-snug">{{ label }}</span>
        <span
            v-if="badge && !collapsed"
            class="ml-auto shrink-0 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold normal-case tracking-normal text-amber-900 ring-1 ring-amber-200/80"
        >
            {{ badge }}
        </span>
    </Link>
</template>
