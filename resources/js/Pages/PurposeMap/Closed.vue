<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({ message: String });
</script>

<template>
    <Head title="Mapa de Propósito" />

    <div class="app-shell flex min-h-screen flex-col items-center justify-center px-4 text-center text-slate-900">
        <div class="surface-glass max-w-md px-8 py-10">
            <img src="/images/logo.png" alt="Talents" class="mx-auto h-14 w-auto" />
            <h1 class="mt-6 text-xl font-bold text-talents-800">Indisponível</h1>
            <p class="mt-3 text-sm text-slate-600">{{ message }}</p>
        </div>
    </div>
</template>
