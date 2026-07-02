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
    compact: {
        type: [Boolean, null],
        default: null,
    },
    badge: {
        type: [String, Number],
        default: null,
    },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'minimal'].includes(value),
    },
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: undefined,
    },
});

const closeMobileSidebar = inject('closeMobileSidebar', null);

const isMinimal = computed(() => props.variant === 'minimal');

const labelOpacityClass = computed(() =>
    props.collapsed
        ? 'opacity-0'
        : 'opacity-100',
);

const numericBadge = computed(() => {
    const value = Number(props.badge);
    return Number.isFinite(value) && value > 0 ? value : null;
});

const textBadge = computed(() => {
    if (numericBadge.value) return null;
    return props.badge ? String(props.badge) : null;
});

const showCollapsedUnreadDot = computed(() => props.collapsed && numericBadge.value !== null);

const linkClasses = computed(() => {
    if (isMinimal.value) {
        const base =
            'group relative flex min-h-10 w-full items-center overflow-hidden text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-900 transition-colors duration-200 ease-in-out';
        if (props.active) {
            return `${base} text-slate-900`;
        }
        return `${base} hover:text-slate-600`;
    }

    const base =
        'group relative flex min-h-10 w-full items-center overflow-hidden rounded-2xl border border-transparent text-sm font-medium transition-[background-color,border-color,box-shadow,color] duration-200 ease-in-out';
    if (props.active) {
        return `${base} bg-talents-100/90 text-talents-900 shadow-sm ring-1 ring-talents-300/50`;
    }
    return `${base} text-slate-600 hover:bg-slate-100/80 hover:text-slate-900`;
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
        :method="method"
        :as="as"
        :prefetch="method === 'get' && as !== 'button'"
        :class="linkClasses"
        :title="collapsed ? label : undefined"
        @click="onNavigate"
    >
        <span
            v-if="isMinimal"
            class="flex h-10 w-[2.7rem] shrink-0 items-center justify-center"
            aria-hidden="true"
        >
            <span
                class="h-5 w-1 rounded-full transition-colors duration-200"
                :class="indicatorClasses"
            />
        </span>
        <span
            v-else-if="icon"
            class="relative flex h-10 w-[2.7rem] shrink-0 items-center justify-center"
            aria-hidden="true"
        >
            <component
                :is="icon"
                class="h-5 w-5 shrink-0"
                :class="iconClasses"
            />
            <span
                v-if="showCollapsedUnreadDot"
                class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-600 ring-2 ring-white"
                aria-hidden="true"
            />
        </span>
        <Transition name="fade">
            <span
                v-if="!collapsed"
                class="flex min-w-0 items-center overflow-hidden whitespace-nowrap leading-snug transition-opacity"
                :class="labelOpacityClass"
            >
                <span class="truncate">{{ label }}</span>
                <span
                    v-if="numericBadge"
                    class="ml-2 inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold normal-case tracking-normal text-white"
                >
                    {{ numericBadge > 99 ? '99+' : numericBadge }}
                </span>
                <span
                    v-else-if="textBadge"
                    class="ml-2 shrink-0 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold normal-case tracking-normal text-amber-900 ring-1 ring-amber-200/80"
                >
                    {{ textBadge }}
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
