<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    token: String,
    companyName: String,
    protocol: String,
});
</script>

<template>
    <Head title="Denúncia registrada" />

    <div class="app-shell min-h-screen px-4 py-12 text-slate-900">
        <div class="surface-card mx-auto max-w-lg border-emerald-200/80 p-8">
            <h1 class="text-xl font-semibold text-emerald-800">Registro concluído</h1>
            <p class="mt-2 text-sm text-slate-600">{{ companyName }}</p>
            <p class="mt-6 text-sm text-slate-700">Guarde o número de protocolo:</p>
            <p class="mt-2 rounded-xl bg-emerald-50/80 px-4 py-3 text-center text-lg font-semibold tracking-wide text-talents-900">{{ protocol }}</p>
            <p class="mt-4 text-xs text-slate-500">
                Use-o para acompanhar o andamento, sem precisar informar nome ou e-mail (se optou por anonimato).
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link
                    :href="route('denuncia.protocol', { token, protocol })"
                    class="inline-flex justify-center rounded-full bg-talents-700 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-talents-800"
                >
                    Acompanhar agora
                </Link>
                <Link :href="route('denuncia.create', token)" class="text-center text-sm text-talents-700 hover:underline">Nova denúncia</Link>
            </div>
        </div>
    </div>
</template>
