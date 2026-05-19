<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ColorPresetPicker from '@/Components/Tasks/ColorPresetPicker.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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
            <ColorPresetPicker
                v-model="form.cover_color"
                label="Cor de capa"
                hint="Aparece como faixa colorida na listagem de quadros."
            />
            <div class="flex flex-wrap gap-3">
                <PrimaryButton :disabled="form.processing">Criar quadro</PrimaryButton>
                <Link :href="route('admin.tarefas.quadros.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
