<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    module: { type: String, default: null },
});
</script>

<template>
    <Head :title="`${title} — Em breve`" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">{{ title }}</h2>
        </template>

        <div class="surface-card p-10 text-center">
            <span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                Em breve
            </span>
            <p class="mx-auto mt-4 max-w-lg text-gray-600">{{ description }}</p>
        </div>
    </AdminLayout>
</template>
