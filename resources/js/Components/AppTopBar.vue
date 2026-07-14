<script setup>
import NewsFeedDrawer from '@/Components/NewsFeedDrawer.vue';
import NoticeBellDropdown from '@/Components/NoticeBellDropdown.vue';
import { FolderIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

defineProps({
    /** Texto opcional à esquerda (ex.: contexto da área) */
    title: {
        type: String,
        default: '',
    },
    showSearch: {
        type: Boolean,
        default: true,
    },
    showActions: {
        type: Boolean,
        default: true,
    },
    showFiles: {
        type: Boolean,
        default: true,
    },
    searchPlaceholder: {
        type: String,
        default: 'Buscar…',
    },
});
</script>

<template>
    <div
        class="flex shrink-0 flex-wrap items-center gap-3 border-b border-slate-200/50 bg-white/75 px-4 py-3 backdrop-blur-md sm:gap-4 sm:px-6 lg:rounded-t-shell lg:px-8"
    >
        <p
            v-if="title"
            class="hidden min-w-0 py-1 text-sm font-semibold tracking-tight text-slate-800 sm:block sm:max-w-[12rem] sm:truncate lg:max-w-xs"
        >
            {{ title }}
        </p>
        <div v-if="showSearch" class="relative min-w-0 flex-1 basis-[200px]">
            <MagnifyingGlassIcon
                class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                aria-hidden="true"
            />
            <input
                type="search"
                disabled
                :placeholder="searchPlaceholder"
                class="w-full cursor-not-allowed rounded-full border border-slate-200/90 bg-slate-50/90 py-2.5 pl-10 pr-4 text-sm text-slate-600 shadow-inner placeholder:text-slate-400"
                tabindex="-1"
                aria-disabled="true"
            />
        </div>
        <div v-if="showActions" class="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1">
            <button
                v-if="showFiles"
                type="button"
                class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-talents-500/30"
                aria-label="Arquivos"
            >
                <FolderIcon class="h-6 w-6" />
            </button>
            <NewsFeedDrawer />
            <NoticeBellDropdown />
        </div>
    </div>
</template>
