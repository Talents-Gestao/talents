<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PermissionsMatrix from '@/Components/PermissionsMatrix.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: String,
    user: { type: Object, default: null },
    permissionModules: { type: Array, default: () => [] },
    permissionActions: { type: Array, default: () => [] },
    defaultPermissions: { type: Array, default: () => [] },
});

const isOwner = props.user?.is_owner === true;

const initialPermissions = () => {
    if (isOwner) {
        return [];
    }
    if (props.mode === 'edit') {
        return [...(props.user?.permissions ?? [])];
    }
    return [...(props.defaultPermissions ?? [])];
};

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    is_active: props.user?.is_active ?? true,
    is_commercial: props.user?.is_commercial ?? false,
    permissions: initialPermissions(),
});

const grantAll = () => {
    if (isOwner) {
        return;
    }
    const next = [];
    for (const m of props.permissionModules) {
        for (const a of props.permissionActions) {
            next.push({ module: m.value, action: a.value });
        }
    }
    form.permissions = next;
};

const clearAll = () => {
    if (isOwner) {
        return;
    }
    form.permissions = [];
};

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

        <form class="max-w-5xl space-y-6 surface-card p-6" @submit.prevent="submit">
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
                A conta do proprietário permanece sempre ativa e com acesso total ao painel.
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

            <div v-if="isOwner" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p class="font-semibold">Proprietário — acesso total</p>
                <p class="mt-1 text-amber-900/90">
                    Esta conta não usa a matriz de ticks (vê todos os módulos). Para limitar o que cada pessoa vê,
                    volte à Equipe e edite um colaborador (ex.: karen@talents.local ou luciana@talents.local).
                </p>
            </div>

            <div v-else>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <InputLabel value="Permissões do painel Admin" />
                        <p class="mt-1 text-sm text-slate-600">
                            Marque o que este colaborador pode ver e fazer. Sem «Financeiro», o módulo não aparece no menu.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="grantAll"
                        >
                            Marcar tudo
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearAll"
                        >
                            Limpar
                        </button>
                    </div>
                </div>
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
