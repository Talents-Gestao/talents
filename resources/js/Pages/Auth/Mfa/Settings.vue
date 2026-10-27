<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: 'none' },
    active: { type: Boolean, default: false },
    remainingCodes: { type: Number, default: 0 },
    reviewer: { type: Boolean, default: false },
    verifiedRecently: { type: Boolean, default: false },
});

const regenerate = () => router.post(route('mfa.codes.regenerate'));
const disable = () => {
    if (window.confirm('Desativar a verificação em duas etapas? As ações sensíveis do NR-1 voltarão a pedir a ativação.')) {
        router.post(route('mfa.disable'));
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Verificação em duas etapas" />

        <h1 class="text-lg font-semibold text-talents-900">Verificação em duas etapas</h1>

        <div v-if="$page.props.flash?.success" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
        <div v-if="$page.props.flash?.error" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">{{ $page.props.flash.error }}</div>

        <template v-if="active">
            <p class="mt-3 text-sm text-gray-700">
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900">Ativa</span>
                Códigos de recuperação restantes: <strong>{{ remainingCodes }}</strong>.
            </p>
            <p v-if="!verifiedRecently" class="mt-2 text-xs text-gray-500">Para gerar novos códigos ou desativar, o sistema pede o código do aplicativo antes.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-800" @click="regenerate">Gerar novos códigos</button>
                <button v-if="!reviewer" type="button" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-800" @click="disable">Desativar</button>
            </div>
            <p v-if="reviewer" class="mt-2 text-xs text-gray-500">Para o revisor externo a verificação é obrigatória.</p>
        </template>

        <template v-else>
            <p class="mt-3 text-sm text-gray-700">
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ status === 'broken' ? 'Precisa de nova inscrição' : 'Não ativada' }}</span>
                <span v-if="status === 'broken'"> O segredo anterior não pôde ser lido; faça uma nova inscrição.</span>
            </p>
            <Link :href="route('mfa.enroll')" class="mt-4 inline-block rounded-md bg-talents-700 px-3 py-1.5 text-sm font-semibold text-white">Ativar</Link>
        </template>

        <p class="mt-6 text-sm"><Link href="/" class="text-talents-700 underline">Voltar</Link></p>
    </GuestLayout>
</template>
