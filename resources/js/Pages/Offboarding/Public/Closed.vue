<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: { type: String, required: true },
    employeeName: { type: String, default: '' },
    companyName: { type: String, default: '' },
});
</script>

<template>
    <Head title="Link indisponível" />

    <div class="app-shell flex min-h-screen items-center justify-center px-4 text-slate-900">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <img src="/images/logo.png" alt="Talents" class="mx-auto h-12 w-auto" />
            <h1 class="mt-6 text-xl font-semibold text-slate-900">Pesquisa indisponível</h1>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <p v-if="companyName" class="mt-4 text-xs text-slate-400">{{ companyName }}</p>
        </div>
    </div>
</template>
