<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    surveyTitle: String,
});
</script>

<template>
    <Head title="Obrigado" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-talents-50 to-white px-4 py-12">
        <div class="text-center">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-talents-100 text-talents-600">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h1 class="mt-6 text-2xl font-bold text-talents-900">Obrigado!</h1>
            <p class="mt-3 max-w-md text-gray-600">Sua participação na pesquisa <strong>{{ surveyTitle }}</strong> foi registrada com sucesso.</p>
        </div>
    </div>
</template>
