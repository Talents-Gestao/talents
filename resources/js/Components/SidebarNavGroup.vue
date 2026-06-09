<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
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
    active: {
        type: Boolean,
        default: false,
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

const headerClasses = computed(() => {
    const base =
        'group flex w-full items-center gap-3 rounded-2xl border border-transparent py-2.5 text-sm font-medium transition duration-150 ease-in-out';
    if (props.active) {
        return `${base} bg-talents-100/90 px-3 text-talents-900 shadow-sm ring-1 ring-talents-300/50`;
    }
    return `${base} px-3 text-slate-600 hover:bg-slate-100/80 hover:text-slate-900`;
});

const iconClasses = computed(() =>
    props.active ? 'text-talents-700' : 'text-slate-400 group-hover:text-talents-600',
);

const chevronClasses = computed(() =>
    open.value ? 'rotate-180' : '',
);
</script>

<template>
    <div class="mt-1 first:mt-0">
        <template v-if="collapsed">
            <div class="my-2 border-t border-slate-200/80" aria-hidden="true" />
            <div class="space-y-0.5">
                <slot />
            </div>
        </template>

        <template v-else>
            <button
                type="button"
                :class="headerClasses"
                :aria-expanded="open"
                @click="toggle"
            >
                <component :is="icon" class="h-5 w-5 shrink-0" :class="iconClasses" />
                <span class="min-w-0 flex-1 truncate text-left">{{ label }}</span>
                <ChevronDownIcon
                    class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                    :class="chevronClasses"
                />
            </button>

            <div
                v-show="open"
                class="mt-0.5 space-y-0.5 border-l border-slate-200/80 pl-2 ml-4"
            >
                <slot />
            </div>
        </template>
    </div>
</template>
