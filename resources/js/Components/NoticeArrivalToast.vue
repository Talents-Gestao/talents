<script setup>
import { BellIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useUnreadNotices } from '@/composables/useUnreadNotices';

const {
    toast,
    open,
    assignedBadgeLabel,
    assignedArrivalLabel,
    showNotices,
    dismissToast,
    openFromToast,
    toggle,
} = useUnreadNotices();

const showDock = computed(
    () => showNotices.value && !open.value && Boolean(assignedBadgeLabel.value || toast.value),
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transform transition duration-200 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-if="showDock"
                class="fixed right-0 top-[28%] z-[220] flex max-w-[min(22rem,calc(100vw-1rem))] flex-col items-end gap-2 pr-0 sm:top-[32%]"
                role="status"
                aria-live="polite"
            >
                <div
                    v-if="toast"
                    class="mr-3 flex w-[min(20rem,calc(100vw-2rem))] gap-3 rounded-2xl bg-white p-4 shadow-2xl ring-1 ring-rose-200/80"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-600 text-white"
                    >
                        <BellIcon class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-rose-700">
                            Lead para você
                        </p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900">
                            {{ toast.title }}
                        </p>
                        <p
                            v-if="toast.body"
                            class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-600"
                        >
                            {{ toast.body }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Link
                                v-if="toast.href"
                                :href="toast.href"
                                class="rounded-lg bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-700"
                                @click="dismissToast"
                            >
                                Abrir
                            </Link>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                @click="openFromToast"
                            >
                                Ver avisos
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Dispensar aviso"
                        @click="dismissToast"
                    >
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>

                <button
                    type="button"
                    class="flex items-center gap-2 rounded-l-2xl bg-rose-600 py-3 pl-3.5 pr-3 text-white shadow-xl ring-1 ring-rose-700/30 transition hover:bg-rose-700"
                    :aria-label="assignedArrivalLabel || 'Leads para você'"
                    @click="toggle"
                >
                    <BellIcon class="h-5 w-5 shrink-0" :class="toast ? 'animate-pulse' : ''" />
                    <span class="flex flex-col items-start leading-tight">
                        <span class="text-sm font-bold tabular-nums">{{ assignedBadgeLabel || '!' }}</span>
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-rose-100">
                            para você
                        </span>
                    </span>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
