<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    qrSvg: { type: String, required: true },
    secret: { type: String, required: true },
    reviewer: { type: Boolean, default: false },
});

const form = useForm({ code: '' });

const submit = () => {
    form.post(route('mfa.enroll.confirm'), { onFinish: () => form.reset('code') });
};
</script>

<template>
    <GuestLayout>
        <Head title="Ativar verificação em duas etapas" />

        <div v-if="$page.props.flash?.error" class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="alert">{{ $page.props.flash.error }}</div>
        <h1 class="text-lg font-semibold text-talents-900">Ativar verificação em duas etapas</h1>
        <p v-if="reviewer" class="mt-2 text-sm text-gray-600">
            Para revisar documentos, a verificação em duas etapas é obrigatória.
        </p>
        <p v-else class="mt-2 text-sm text-gray-600">
            Ela protege as ações sensíveis do NR-1 (publicar documentos, dado bruto e Revisão interna).
        </p>

        <ol class="mt-4 list-decimal space-y-3 ps-5 text-sm text-gray-700">
            <li>Instale um aplicativo autenticador (Google Authenticator, Microsoft Authenticator, Authy ou similar).</li>
            <li>
                Leia o QR code abaixo com o aplicativo.
                <div class="mt-2 flex justify-center rounded-lg border border-gray-200 bg-white p-3" role="img" aria-label="QR code para o aplicativo autenticador" v-html="qrSvg" />
                <p class="mt-2 text-xs text-gray-600">
                    Sem câmera? Digite esta chave no aplicativo:
                    <code class="break-all rounded bg-gray-100 px-1 py-0.5 text-gray-900">{{ secret }}</code>
                </p>
            </li>
            <li>Digite o código de 6 dígitos que o aplicativo mostra para confirmar.</li>
        </ol>

        <form class="mt-4" @submit.prevent="submit">
            <InputLabel for="code" value="Código de 6 dígitos" />
            <TextInput
                id="code"
                v-model="form.code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="7"
                class="mt-1 block w-full tracking-widest"
                required
                autofocus
            />
            <InputError class="mt-2" :message="form.errors.code" />

            <div class="mt-4 flex justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">Ativar</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
