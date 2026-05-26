<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({
    workspaces: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    workspace_id: null,
});

const select = (workspaceId) => {
    form.workspace_id = workspaceId;
    form.post(route('workspaces.select.store'));
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Selecionar ambiente" />

        <div class="mb-6 text-center">
            <h1 class="text-xl font-semibold text-slate-900">
                Selecione o ambiente
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                A sua conta tem acesso a mais de um ambiente. Escolha para onde
                deseja entrar.
            </p>
        </div>

        <div class="space-y-3">
            <button
                v-for="workspace in workspaces"
                :key="workspace.id"
                type="button"
                class="flex w-full flex-col rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-talents-500 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-talents-500/40"
                :disabled="form.processing"
                @click="select(workspace.id)"
            >
                <span class="text-base font-semibold text-slate-900">
                    {{ workspace.workspace_label }}
                </span>
                <span class="mt-1 text-sm text-slate-600">
                    {{ workspace.role_label }}
                </span>
            </button>
        </div>

        <div v-if="form.errors.workspace_id" class="mt-4 text-sm text-red-600">
            {{ form.errors.workspace_id }}
        </div>

        <div class="mt-6 flex justify-center">
            <button
                type="button"
                class="text-sm text-slate-600 underline hover:text-slate-900"
                @click="logout"
            >
                Sair da conta
            </button>
        </div>
    </GuestLayout>
</template>
