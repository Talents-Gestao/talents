<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    companies: Array,
});

const form = useForm({
    company_id: '',
    title: '',
    body: '',
});

const submit = () => {
    form.post(route('admin.notices.store'));
};
</script>

<template>
    <Head title="Novo aviso" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Publicar aviso</h2>
                <p class="mt-1 text-sm text-slate-500">O aviso aparecerá para os utilizadores da empresa selecionada.</p>
            </div>
        </template>

        <form class="surface-card max-w-2xl space-y-5 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="company_id" value="Empresa" />
                <select
                    id="company_id"
                    v-model="form.company_id"
                    class="mt-1 block w-full rounded-md border border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    required
                >
                    <option value="" disabled>Selecione a empresa</option>
                    <option v-for="company in companies" :key="company.id" :value="company.id">
                        {{ company.name }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.company_id" />
            </div>

            <div>
                <InputLabel for="title" value="Título" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel for="body" value="Mensagem" />
                <textarea
                    id="body"
                    v-model="form.body"
                    rows="6"
                    class="mt-1 block w-full rounded-md border border-slate-200 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    required
                />
                <InputError class="mt-1" :message="form.errors.body" />
            </div>

            <div class="flex items-center gap-3">
                <PrimaryButton :disabled="form.processing">Publicar aviso</PrimaryButton>
                <Link :href="route('admin.notices.index')" class="text-sm text-slate-600 hover:text-slate-900">
                    Cancelar
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
