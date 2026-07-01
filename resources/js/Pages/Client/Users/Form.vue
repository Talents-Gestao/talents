<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import ClientLayout from '@/Layouts/ClientLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PermissionsMatrix from '@/Components/PermissionsMatrix.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: String,
    user: { type: Object, default: null },
    permissionModules: Array,
    permissionActions: Array,
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    is_active: props.user?.is_active ?? true,
    permissions: props.user?.permissions ? [...props.user.permissions] : [],
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('client.usuarios.store'));
    } else {
        form.put(route('client.usuarios.update', props.user.id));
    }
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo utilizador' : 'Editar utilizador'" />

    <ClientLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('client.usuarios.index')"
                back-label="Utilizadores"
                :title="mode === 'create' ? 'Novo utilizador' : 'Editar utilizador'"
            />
        </template>

        <form class="max-w-3xl space-y-6 surface-card p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.email" />
            </div>
            <div class="flex items-center gap-2">
                <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-talents-700" />
                <InputLabel for="is_active" value="Conta ativa" class="!mb-0" />
            </div>

            <div>
                <InputLabel value="Permissões por módulo" />
                <p class="mt-1 text-sm text-gray-600">O novo utilizador terá o papel <strong>Usuário da empresa</strong> com as permissões abaixo.</p>
                <div class="mt-3">
                    <PermissionsMatrix
                        v-model="form.permissions"
                        :permission-modules="permissionModules"
                        :permission-actions="permissionActions"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.permissions" />
            </div>

            <div class="flex gap-2">
                <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>
                <Link :href="route('client.usuarios.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </ClientLayout>
</template>
