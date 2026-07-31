<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    companies: Array,
});

const form = useForm({
    title: '',
    company_id: '',
    participants_text: '',
});

const submit = () => {
    form.post(route('admin.reunioes.store'));
};
</script>

<template>
    <Head title="Nova reunião" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.reunioes.index')"
                title="Nova reunião"
                subtitle="Informe os dados básicos. Na próxima tela você grava o áudio e a ata é gerada automaticamente."
            />
        </template>

        <form class="surface-card max-w-2xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="title" value="Título da reunião" />
                <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel for="company_id" value="Empresa (opcional)" />
                <select
                    id="company_id"
                    v-model="form.company_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Nenhuma</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.company_id" />
            </div>

            <div>
                <InputLabel for="participants_text" value="Participantes (opcional)" />
                <textarea
                    id="participants_text"
                    v-model="form.participants_text"
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    placeholder="Ex.: Ana (RH), Carlos (gestor), Maria (cliente)"
                />
                <InputError class="mt-1" :message="form.errors.participants_text" />
            </div>

            <PrimaryButton :disabled="form.processing">Criar e gravar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
