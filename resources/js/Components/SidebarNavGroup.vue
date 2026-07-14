<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';

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

const accordion = inject('sidebarNavAccordion', null);
const groupId = props.label;

const open = ref(false);

const claimOpen = () => {
    accordion?.setOpenGroup(groupId);
};

const releaseOpen = () => {
    accordion?.clearOpenGroup(groupId);
};

const setOpen = (value) => {
    if (value && props.collapsed) {
        return;
    }

    open.value = value;
    if (value) {
        claimOpen();
    } else {
        releaseOpen();
    }
};

watch(
    () => props.collapsed,
    (isCollapsed) => {
        if (isCollapsed) {
            open.value = false;
            releaseOpen();
        }
        // Não reabre pelo fato da rota estar ativa — só por clique do utilizador.
    },
    { immediate: true },
);

watch(
    () => props.active,
    (isActive, wasActive) => {
        // Só abre ao navegar para a secção, nunca ao recolher/expandir a barra.
        if (isActive && wasActive === false && !props.collapsed) {
            setOpen(true);
        }
    },
);

if (accordion) {
    watch(accordion.openGroupId, (id) => {
        if (id !== groupId && open.value) {
            open.value = false;
        }
    });
}

const toggle = () => {
    setOpen(!open.value);
};

const onHeaderClick = () => {
    if (props.collapsed) {
        return;
    }
    toggle();
};

const submenuExpanded = computed(() => !props.collapsed && open.value);

// Mesmo eixo horizontal dos SidebarNavItem (sem justify-center no recolhido).
const headerClasses = computed(() => {
    const base =
        'group flex min-h-10 w-full items-center overflow-hidden rounded-2xl border border-transparent text-sm font-medium transition-[background-color,border-color,box-shadow,color] duration-200 ease-in-out';
    if (props.active) {
        return `${base} bg-talents-100/90 text-talents-900 shadow-sm ring-1 ring-talents-300/50`;
    }
    return `${base} text-slate-600 hover:bg-slate-100/80 hover:text-slate-900`;
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
            <span class="flex h-10 w-[2.7rem] shrink-0 items-center justify-center" aria-hidden="true">
                <component :is="icon" class="h-5 w-5 shrink-0" :class="iconClasses" />
            </span>
        </Link>
        <button
            v-else
            type="button"
            :class="headerClasses"
            :title="collapsed ? label : undefined"
            :aria-expanded="collapsed ? undefined : open"
            @click="onHeaderClick"
        >
            <span class="flex h-10 w-[2.7rem] shrink-0 items-center justify-center" aria-hidden="true">
                <component :is="icon" class="h-5 w-5 shrink-0" :class="iconClasses" />
            </span>
            <Transition name="fade">
                <span
                    v-if="!collapsed"
                    class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden pr-2.5"
                >
                    <span class="min-w-0 flex-1 truncate text-left leading-snug">{{ label }}</span>
                    <ChevronDownIcon
                        class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                        :class="chevronClasses"
                    />
                </span>
            </Transition>
        </button>

        <div
            class="grid transition-[grid-template-rows,opacity,margin] ease-in-out"
            :class="[
                submenuExpanded ? 'mt-0.5 grid-rows-[1fr] opacity-100 duration-200' : 'mt-0 grid-rows-[0fr] opacity-0',
                collapsed ? 'pointer-events-none duration-75' : 'duration-200',
            ]"
            :aria-hidden="!submenuExpanded"
        >
            <div class="min-h-0 overflow-hidden">
                <div class="space-y-0.5 border-l border-slate-200/80 pl-2 ml-4">
                    <slot />
                </div>
            </div>
        </div>
    </div>
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
