<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    companyName: { type: String, default: '' },
});
</script>

<template>
    <Head title="Obrigado" />

    <div class="app-shell flex min-h-screen flex-col items-center justify-center px-4 text-center text-slate-900">
        <div class="surface-glass max-w-md px-8 py-10">
            <img src="/images/logo.png" alt="Talents" class="mx-auto h-14 w-auto" />
            <h1 class="mt-6 text-2xl font-bold text-talents-700">Obrigado!</h1>
            <p class="mt-3 text-sm text-slate-600">
                A avaliação
                <span v-if="companyName" class="font-semibold text-slate-900">de {{ companyName }}</span>
                foi registrada. Você pode fechar esta página.
            </p>
        </div>
    </div>
</template>
