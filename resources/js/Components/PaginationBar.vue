<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    paginator: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div
        v-if="paginator?.links?.length > 3"
        class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm"
    >
        <p class="text-xs text-slate-500">
            {{ paginator.from }}–{{ paginator.to }} de {{ paginator.total }}
        </p>
        <div class="flex flex-wrap items-center justify-end gap-1">
            <template v-for="link in paginator.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-lg px-3 py-1 text-slate-700 transition hover:bg-white"
                    :class="link.active ? 'bg-talents-600 text-white hover:bg-talents-600' : ''"
                    preserve-scroll
                    v-html="link.label"
                />
                <span v-else class="cursor-not-allowed rounded-lg px-3 py-1 text-slate-400" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
