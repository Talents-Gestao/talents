<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    token: String,
    companyName: String,
});
</script>

<template>
    <Head title="Mensagem enviada" />

    <div class="app-shell min-h-screen px-4 py-12 text-slate-900">
        <div class="surface-card mx-auto max-w-lg border-emerald-200/80 p-8">
            <h1 class="text-xl font-semibold text-emerald-800">Obrigado por contribuir!</h1>
            <p class="mt-2 text-sm text-slate-600">{{ companyName }}</p>
            <p class="mt-6 text-sm leading-relaxed text-slate-700">
                Sua sugestão, dúvida ou feedback é muito importante para continuarmos melhorando o ambiente de trabalho.
            </p>
            <div class="mt-8">
                <Link :href="route('sugestao.create', token)" class="text-sm font-medium text-talents-700 hover:underline">
                    Enviar outra mensagem
                </Link>
            </div>
        </div>
    </div>
</template>
