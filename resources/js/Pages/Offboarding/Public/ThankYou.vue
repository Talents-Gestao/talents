<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    employeeName: { type: String, default: '' },
    companyName: { type: String, default: '' },
});
</script>

<template>
    <Head title="Obrigado" />

    <div class="app-shell flex min-h-screen items-center justify-center px-4 text-slate-900">
        <div class="w-full max-w-md rounded-2xl border border-talents-100 bg-white p-8 text-center shadow-sm">
            <img src="/images/logo.png" alt="Talents" class="mx-auto h-12 w-auto" />
            <h1 class="mt-6 text-xl font-semibold text-talents-900">Respostas enviadas</h1>
            <p class="mt-2 text-sm text-slate-600">
                Obrigado{{ employeeName ? `, ${employeeName}` : '' }}. Sua pesquisa de desligamento
                {{ companyName ? `para ${companyName}` : '' }} foi registrada com sucesso.
            </p>
        </div>
    </div>
</template>
