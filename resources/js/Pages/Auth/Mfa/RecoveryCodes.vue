<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    codes: { type: Array, required: true },
    continueUrl: { type: String, required: true },
});

const download = () => {
    const blob = new Blob([`Códigos de recuperação — Talents\n\n${props.codes.join('\n')}\n`], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'codigos-de-recuperacao.txt';
    link.click();
    URL.revokeObjectURL(link.href);
};
</script>

<template>
    <GuestLayout>
        <Head title="Códigos de recuperação" />

        <h1 class="text-lg font-semibold text-talents-900">Guarde seus códigos de recuperação</h1>
        <p class="mt-2 text-sm text-gray-600">
            Use um deles se perder o aplicativo. Cada código vale <strong>uma vez</strong> e esta é a <strong>única vez</strong> em que
            aparecem: guarde em local seguro. Gerar novos códigos invalida estes.
        </p>

        <ul class="mt-4 grid grid-cols-2 gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 font-mono text-sm text-amber-950" aria-label="Códigos de recuperação">
            <li v-for="code in codes" :key="code">{{ code }}</li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-800" @click="download">Baixar .txt</button>
            <Link :href="continueUrl" class="rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white">Já guardei, continuar</Link>
        </div>
    </GuestLayout>
</template>
