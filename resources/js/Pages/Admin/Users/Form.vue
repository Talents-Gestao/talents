<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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

const isOwner = props.user?.is_owner === true;

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    is_active: props.user?.is_active ?? true,
    is_commercial: props.user?.is_commercial ?? false,
    permissions: props.user?.permissions ? [...props.user.permissions] : [],
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('admin.users.store'));
    } else {
        form.put(route('admin.users.update', props.user.id));
    }
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo administrador' : 'Editar administrador'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.users.index')"
                back-label="Equipe"
                :title="mode === 'create' ? 'Novo administrador' : 'Editar administrador'"
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
                <input
                    id="is_active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="rounded border-gray-300 text-talents-700"
                    :disabled="isOwner"
                />
                <InputLabel for="is_active" value="Conta ativa" class="!mb-0" />
            </div>
            <InputError class="mt-1" :message="form.errors.is_active" />
            <p v-if="isOwner" class="text-sm text-amber-800">
                A conta do proprietário permanece sempre ativa.
            </p>

            <div class="flex items-center gap-2">
                <input
                    id="is_commercial"
                    v-model="form.is_commercial"
                    type="checkbox"
                    class="rounded border-gray-300 text-talents-700"
                />
                <InputLabel for="is_commercial" value="Pode aparecer como vendedor comercial (propostas)" class="!mb-0" />
            </div>
            <InputError class="mt-1" :message="form.errors.is_commercial" />

            <div v-if="isOwner" class="rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm text-violet-900">
                <strong>Proprietário:</strong> acesso total à administração. As permissões por módulo não se aplicam a esta conta.
            </div>

            <div v-else>
                <InputLabel value="Permissões por módulo (administração)" />
                <p class="mt-1 text-sm text-gray-600">
                    Marque as ações permitidas para este administrador em cada área do painel.
                </p>
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
                <Link :href="route('admin.users.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
