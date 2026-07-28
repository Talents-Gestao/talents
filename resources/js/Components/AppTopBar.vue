<script setup>
import NewsFeedDrawer from '@/Components/NewsFeedDrawer.vue';
import NoticeBellDropdown from '@/Components/NoticeBellDropdown.vue';

defineProps({
    /** Texto opcional à esquerda (ex.: contexto da área) */
    title: {
        type: String,
        default: '',
    },
    showActions: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div
        class="flex shrink-0 flex-wrap items-center gap-3 border-b border-slate-200/50 bg-white/75 px-4 py-3 backdrop-blur-md sm:gap-4 sm:px-6 lg:rounded-t-shell lg:px-8"
    >
        <p
            v-if="title"
            class="hidden min-w-0 flex-1 py-1 text-sm font-semibold tracking-tight text-slate-800 sm:block sm:truncate"
        >
            {{ title }}
        </p>
        <div v-if="showActions" class="ml-auto flex shrink-0 items-center gap-0.5 sm:gap-1">
            <NewsFeedDrawer />
            <NoticeBellDropdown />
        </div>
    </div>
</template>
