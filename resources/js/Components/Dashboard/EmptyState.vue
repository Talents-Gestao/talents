<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Sem dados' },
    description: { type: String, default: '' },
    ctaLabel: { type: String, default: '' },
    ctaHref: { type: String, default: '' },
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-rose-100/45 bg-rose-50/20 px-4 py-10 text-center"
    >
        <slot name="icon">
            <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"
                />
            </svg>
        </slot>
        <p class="mt-3 text-sm font-semibold text-slate-800">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-xs text-slate-500">{{ description }}</p>
        <Link
            v-if="ctaHref && ctaLabel"
            :href="ctaHref"
            class="mt-4 text-sm font-semibold text-talents-700 hover:text-talents-800 hover:underline"
        >
            {{ ctaLabel }}
        </Link>
    </div>
</template>
