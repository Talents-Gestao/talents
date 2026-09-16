<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Redefinir senha" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="E-mail" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Senha" />

                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pe-10"
                        required
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 end-0 flex items-center pe-3 text-slate-400 transition hover:text-slate-600 focus:outline-none focus:text-slate-600"
                        :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                        @click="showPassword = !showPassword"
                    >
                        <EyeSlashIcon v-if="showPassword" class="h-5 w-5" aria-hidden="true" />
                        <EyeIcon v-else class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar senha"
                />

                <div class="relative mt-1">
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPasswordConfirmation ? 'text' : 'password'"
                        class="block w-full pe-10"
                        required
                        autocomplete="new-password"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 end-0 flex items-center pe-3 text-slate-400 transition hover:text-slate-600 focus:outline-none focus:text-slate-600"
                        :aria-label="showPasswordConfirmation ? 'Ocultar senha' : 'Mostrar senha'"
                        @click="showPasswordConfirmation = !showPasswordConfirmation"
                    >
                        <EyeSlashIcon v-if="showPasswordConfirmation" class="h-5 w-5" aria-hidden="true" />
                        <EyeIcon v-else class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Redefinir senha
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
