<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({ campaignTitle: String });
</script>

<template>
    <Head title="Obrigado" />

    <div class="app-shell flex min-h-screen flex-col items-center justify-center px-4 text-center text-slate-900">
        <div class="surface-glass max-w-md px-8 py-10">
            <img src="/images/logo.png" alt="Talents" class="mx-auto h-14 w-auto" />
            <h1 class="mt-6 text-2xl font-bold text-talents-700">Obrigado!</h1>
            <p class="mt-3 text-sm text-slate-600">
                Suas respostas foram registradas de forma anónima no
                <span class="font-semibold text-slate-900">{{ campaignTitle }}</span>.
            </p>
            <p class="mt-4 text-xs text-slate-500">Você pode fechar esta página.</p>
        </div>
    </div>
</template>
