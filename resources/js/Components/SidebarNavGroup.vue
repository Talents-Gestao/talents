<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: [Object, Function],
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
    active: {
        type: Boolean,
        default: false,
    },
    /** Quando a sidebar está colapsada, clicar no ícone navega para este href. */
    fallbackHref: {
        type: String,
        default: null,
    },
});

const open = ref(props.active);

watch(
    () => props.active,
    (isActive) => {
        if (isActive) {
            open.value = true;
        }
    },
);

const toggle = () => {
    open.value = !open.value;
};

const onHeaderClick = () => {
    if (props.collapsed) {
        return;
    }
    toggle();
};

const headerClasses = computed(() => {
    const base =
        'group flex w-full items-center rounded-2xl border border-transparent text-sm font-medium transition duration-150 ease-in-out';
    if (props.collapsed) {
        if (props.active) {
            return `${base} justify-center bg-talents-100/90 px-2 py-2.5 text-talents-900 shadow-sm ring-1 ring-talents-200/60`;
        }
        return `${base} justify-center px-2 py-2.5 text-slate-600 hover:bg-slate-100/90 hover:text-slate-900`;
    }
    if (props.active) {
        return `${base} gap-3 bg-talents-100/90 px-3 py-2.5 text-talents-900 shadow-sm ring-1 ring-talents-300/50`;
    }
    return `${base} gap-3 px-3 py-2.5 text-slate-600 hover:bg-slate-100/80 hover:text-slate-900`;
});

const iconClasses = computed(() =>
    props.active ? 'text-talents-700' : 'text-slate-400 group-hover:text-talents-600',
);

const chevronClasses = computed(() => (open.value ? 'rotate-180' : ''));
</script>

<template>
    <div>
        <Link
            v-if="collapsed && fallbackHref"
            :href="fallbackHref"
            :class="headerClasses"
            :title="label"
        >
            <component :is="icon" class="h-5 w-5 shrink-0" :class="iconClasses" />
        </Link>
        <button
            v-else
            type="button"
            :class="headerClasses"
            :title="collapsed ? label : undefined"
            :aria-expanded="collapsed ? undefined : open"
            @click="onHeaderClick"
        >
            <component :is="icon" class="h-5 w-5 shrink-0" :class="iconClasses" />
            <span v-if="!collapsed" class="min-w-0 flex-1 truncate text-left">{{ label }}</span>
            <ChevronDownIcon
                v-if="!collapsed"
                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                :class="chevronClasses"
            />
        </button>

        <div
            v-if="!collapsed && open"
            class="mt-0.5 space-y-0.5 border-l border-slate-200/80 pl-2 ml-4"
        >
            <slot />
        </div>
    </div>
</template>
