<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    cover_color: '',
});

function submit() {
    form.post(route('admin.tarefas.quadros.store'));
}
</script>

<template>
    <Head title="Novo quadro interno" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Novo quadro interno</h2>
        </template>

        <form class="surface-card max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput id="name" v-model="form.name" class="mt-1 w-full" required />
            </div>
            <div>
                <InputLabel for="description" value="Descrição" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="mt-1 w-full rounded-md border border-slate-300 text-sm"
                />
            </div>
            <div>
                <InputLabel for="cover_color" value="Cor de capa (hex)" />
                <TextInput id="cover_color" v-model="form.cover_color" class="mt-1 w-full" placeholder="#4F46E5" />
            </div>
            <PrimaryButton :disabled="form.processing">Criar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
