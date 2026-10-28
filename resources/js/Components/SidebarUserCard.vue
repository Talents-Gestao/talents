<script setup>
import {
    ArrowsRightLeftIcon,
    CheckIcon,
    ChevronUpIcon,
    UserCircleIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    sublabel: {
        type: String,
        default: null,
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

const page = usePage();
const closeMobileSidebar = inject('closeMobileSidebar', null);

const menuOpen = ref(false);
const switching = ref(false);
const rootEl = ref(null);
const menuEl = ref(null);
const triggerEl = ref(null);
const menuStyle = ref({});

const workspaces = computed(() => {
    const list = page.props.auth?.user?.workspaces;
    return Array.isArray(list) ? list : [];
});

const activeWorkspaceId = computed(() => Number(page.props.auth?.user?.active_workspace_id ?? 0) || null);

const canSwitchWorkspace = computed(() => workspaces.value.length > 1);

const cardClasses = computed(() => {
    const base =
        'group flex min-h-10 w-full items-center overflow-hidden rounded-xl border bg-white text-left text-sm transition-[background-color,border-color,box-shadow] duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-talents-500/30';
    const layout = props.collapsed ? '' : 'pr-2.5';
    const state = props.active || menuOpen.value
        ? 'border-talents-200 shadow-sm ring-1 ring-talents-200/70'
        : 'border-slate-200 hover:border-talents-200 hover:bg-slate-50/70';

    return `${base} ${layout} ${state}`;
});

const updateMenuPosition = () => {
    const trigger = triggerEl.value;
    if (!trigger) {
        return;
    }

    const rect = trigger.getBoundingClientRect();
    const width = Math.max(rect.width, 224);
    const left = Math.min(rect.left, window.innerWidth - width - 8);

    menuStyle.value = {
        position: 'fixed',
        left: `${Math.max(8, left)}px`,
        width: `${width}px`,
        bottom: `${Math.max(8, window.innerHeight - rect.top + 8)}px`,
        zIndex: 80,
    };
};

const closeMenu = () => {
    menuOpen.value = false;
};

const onNavigate = () => {
    closeMenu();
    if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
    }
};

const toggleMenu = async () => {
    menuOpen.value = !menuOpen.value;
    if (menuOpen.value) {
        await nextTick();
        updateMenuPosition();
    }
};

const switchWorkspace = (workspaceId) => {
    if (switching.value || Number(workspaceId) === Number(activeWorkspaceId.value)) {
        return;
    }
    switching.value = true;
    closeMenu();
    if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
    }
    router.post(
        route('workspaces.select.store'),
        { workspace_id: workspaceId },
        {
            preserveState: false,
            preserveScroll: false,
            onFinish: () => {
                switching.value = false;
            },
            onError: () => {
                switching.value = false;
            },
        },
    );
};

const onDocumentPointerDown = (event) => {
    if (!menuOpen.value) {
        return;
    }
    const target = event.target;
    if (rootEl.value?.contains(target) || menuEl.value?.contains(target)) {
        return;
    }
    closeMenu();
};

const onEscape = (event) => {
    if (event.key === 'Escape') {
        closeMenu();
    }
};

const onViewportChange = () => {
    if (menuOpen.value) {
        updateMenuPosition();
    }
};

watch(
    () => props.collapsed,
    () => {
        if (menuOpen.value) {
            nextTick(updateMenuPosition);
        }
    },
);

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<template>
    <div ref="rootEl" class="relative">
        <button
            ref="triggerEl"
            type="button"
            :class="cardClasses"
            :title="collapsed ? label : undefined"
            :aria-expanded="menuOpen"
            aria-haspopup="menu"
            @click="toggleMenu"
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
                    class="min-w-0 flex-1 overflow-hidden"
                >
                    <span class="block truncate font-medium leading-snug text-slate-900">{{ label }}</span>
                    <span v-if="sublabel" class="block truncate text-xs leading-tight text-slate-500">{{ sublabel }}</span>
                </span>
            </Transition>

            <ChevronUpIcon
                v-if="!collapsed"
                class="ml-1 h-4 w-4 shrink-0 text-slate-400 transition"
                :class="menuOpen ? 'rotate-0' : 'rotate-180'"
                aria-hidden="true"
            />
        </button>

        <Teleport to="body">
            <div
                v-if="menuOpen"
                ref="menuEl"
                :style="menuStyle"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-slate-900/5"
                role="menu"
                aria-label="Conta e ambientes"
            >
                <Link
                    :href="href"
                    class="flex w-full items-center gap-2 px-3 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50"
                    role="menuitem"
                    @click="onNavigate"
                >
                    <UserIcon class="h-4 w-4 shrink-0 text-slate-400" />
                    <span>Meu perfil</span>
                </Link>

                <template v-if="canSwitchWorkspace">
                    <div class="my-1 border-t border-slate-100" />
                    <p class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Trocar ambiente
                    </p>
                    <button
                        v-for="workspace in workspaces"
                        :key="workspace.id"
                        type="button"
                        class="flex w-full items-start gap-2 px-3 py-2.5 text-left text-sm transition hover:bg-talents-50 disabled:opacity-60"
                        :class="
                            Number(workspace.id) === Number(activeWorkspaceId)
                                ? 'bg-talents-50/80 text-talents-900'
                                : 'text-slate-700'
                        "
                        role="menuitem"
                        :disabled="switching"
                        @click="switchWorkspace(workspace.id)"
                    >
                        <ArrowsRightLeftIcon
                            v-if="Number(workspace.id) !== Number(activeWorkspaceId)"
                            class="mt-0.5 h-4 w-4 shrink-0 text-slate-400"
                        />
                        <CheckIcon
                            v-else
                            class="mt-0.5 h-4 w-4 shrink-0 text-talents-600"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ workspace.workspace_label }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ workspace.role_label }}</span>
                        </span>
                    </button>
                </template>
            </div>
        </Teleport>
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
