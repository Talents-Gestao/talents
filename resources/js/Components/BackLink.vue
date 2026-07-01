<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

defineProps({
    href: { type: String, required: true },
    label: { type: String, default: 'Voltar' },
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center gap-1 rounded-lg py-1.5 pr-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
    >
        <ChevronLeftIcon class="h-4 w-4 shrink-0" aria-hidden="true" />
        {{ label }}
    </Link>
</template>
