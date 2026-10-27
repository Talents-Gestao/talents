<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    reviewer: { type: Boolean, default: false },
    remainingCodes: { type: Number, default: 0 },
});

const useRecovery = ref(false);
const codeForm = useForm({ code: '' });
const recoveryForm = useForm({ recovery_code: '' });

const submitCode = () => codeForm.post(route('mfa.verify'), { onFinish: () => codeForm.reset('code') });
const submitRecovery = () => recoveryForm.post(route('mfa.recovery'), { onFinish: () => recoveryForm.reset('recovery_code') });
</script>

<template>
    <GuestLayout>
        <Head title="Verificação em duas etapas" />

        <h1 class="text-lg font-semibold text-talents-900">Verificação em duas etapas</h1>
        <p class="mt-2 text-sm text-gray-600">
            {{ useRecovery ? 'Digite um dos seus códigos de recuperação. Cada código vale uma vez.' : 'Digite o código de 6 dígitos do seu aplicativo autenticador.' }}
        </p>

        <form v-if="!useRecovery" class="mt-4" @submit.prevent="submitCode">
            <InputLabel for="code" value="Código de 6 dígitos" />
            <TextInput
                id="code"
                v-model="codeForm.code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="7"
                class="mt-1 block w-full tracking-widest"
                required
                autofocus
            />
            <InputError class="mt-2" :message="codeForm.errors.code" />
            <div class="mt-4 flex items-center justify-between">
                <button type="button" class="text-sm text-talents-700 underline" @click="useRecovery = true">Usar código de recuperação</button>
                <PrimaryButton :class="{ 'opacity-25': codeForm.processing }" :disabled="codeForm.processing">Verificar</PrimaryButton>
            </div>
        </form>

        <form v-else class="mt-4" @submit.prevent="submitRecovery">
            <InputLabel for="recovery" value="Código de recuperação" />
            <TextInput
                id="recovery"
                v-model="recoveryForm.recovery_code"
                type="text"
                autocomplete="off"
                maxlength="12"
                class="mt-1 block w-full uppercase tracking-widest"
                placeholder="XXXXX-XXXXX"
                required
                autofocus
            />
            <InputError class="mt-2" :message="recoveryForm.errors.recovery_code" />
            <p class="mt-2 text-xs text-gray-500">Você ainda tem {{ remainingCodes }} {{ remainingCodes === 1 ? 'código' : 'códigos' }}.</p>
            <div class="mt-4 flex items-center justify-between">
                <button type="button" class="text-sm text-talents-700 underline" @click="useRecovery = false">Voltar ao código do aplicativo</button>
                <PrimaryButton :class="{ 'opacity-25': recoveryForm.processing }" :disabled="recoveryForm.processing">Entrar</PrimaryButton>
            </div>
        </form>

        <p class="mt-6 text-xs text-gray-500">
            Perdeu o aplicativo e os códigos? Peça a um administrador Talents proprietário para resetar a sua verificação.
        </p>
    </GuestLayout>
</template>
