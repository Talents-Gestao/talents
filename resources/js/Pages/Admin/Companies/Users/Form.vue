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
import { computed, watch } from 'vue';

const props = defineProps({
    mode: String,
    company: Object,
    user: { type: Object, default: null },
    permissionModules: Array,
    permissionActions: Array,
    roleOptions: Array,
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'company_user',
    is_active: props.user?.is_active ?? true,
    permissions: props.user?.permissions ? [...props.user.permissions] : [],
});

watch(
    () => form.role,
    (r) => {
        if (r === 'company_admin') {
            form.permissions = [];
        }
    },
);

const showMatrix = computed(() => form.role === 'company_user');

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('admin.companies.users.store', props.company.id));
    } else {
        form.put(route('admin.companies.users.update', [props.company.id, props.user.id]));
    }
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo utilizador' : 'Editar utilizador'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.users.index', company.id)"
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
            <div>
                <InputLabel for="role" value="Papel" />
                <select
                    id="role"
                    v-model="form.role"
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:outline-none focus:ring-1 focus:ring-talents-500"
                >
                    <option v-for="o in roleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.role" />
            </div>
            <div class="flex items-center gap-2">
                <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-talents-700" />
                <InputLabel for="is_active" value="Conta ativa" class="!mb-0" />
            </div>
            <InputError class="mt-1" :message="form.errors.is_active" />

            <div v-if="showMatrix">
                <InputLabel value="Permissões por módulo" />
                <p class="mt-1 text-sm text-gray-600">Marque as ações permitidas para este utilizador (apenas módulos do plano).</p>
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
                <Link :href="route('admin.companies.users.index', company.id)">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
