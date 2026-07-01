<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ColorPresetPicker from '@/Components/Tasks/ColorPresetPicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    cover_color: '',
    is_active: true,
});

function submit() {
    form.post(route('admin.tarefas.processos.store'));
}
</script>

<template>
    <Head title="Novo modelo de processo" />

    <AdminLayout>
        <template #header>
            <FormPageHeader :back-href="route('admin.tarefas.processos.index')" title="Novo modelo de processo" />
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
            <ColorPresetPicker v-model="form.cover_color" label="Cor de capa" />
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                Ativo
            </label>
            <PrimaryButton :disabled="form.processing">Guardar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
