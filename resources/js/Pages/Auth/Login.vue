<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    sessionExpired: {
        type: Boolean,
        default: false,
    },
});

const expiredModalVisible = ref(false);

onMounted(() => {
    if (props.sessionExpired) {
        expiredModalVisible.value = true;
    }
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const closeExpiredModal = () => {
    expiredModalVisible.value = false;
    window.history.replaceState({}, '', route('login'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <Modal
            :show="expiredModalVisible"
            max-width="md"
            @close="closeExpiredModal"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">
                    Sessão expirada
                </h2>
                <p class="mt-3 text-sm text-gray-600">
                    Sua sessão expirou por inatividade. É necessário fazer
                    login novamente para continuar.
                </p>
                <div class="mt-6 flex justify-end">
                    <PrimaryButton type="button" @click="closeExpiredModal">
                        Entendi
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

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
                <InputLabel for="password" value="Password" />

                <div class="relative mt-1">
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pe-10"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
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

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >Remember me</span
                    >
                </label>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-slate-600 underline hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-talents-500/40 focus:ring-offset-2"
                >
                    Forgot your password?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Log in
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
