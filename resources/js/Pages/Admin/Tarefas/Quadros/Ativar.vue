<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    companies: Array,
    templates: Array,
});

const form = useForm({
    company_id: '',
    template_id: '',
    board_name: '',
});

function submit() {
    if (!form.template_id || !form.company_id) return;
    form.post(route('admin.tarefas.processos.ativar', form.template_id));
}
</script>

<template>
    <Head title="Ativar processo para empresa" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-900">Ativar processo para empresa</h2>
        </template>

        <form class="surface-card max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="template_id" value="Modelo de processo" />
                <select
                    id="template_id"
                    v-model="form.template_id"
                    required
                    class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                >
                    <option disabled value="">Selecione…</option>
                    <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="company_id" value="Empresa" />
                <select
                    id="company_id"
                    v-model="form.company_id"
                    required
                    class="mt-1 block w-full rounded-md border border-slate-300 text-sm"
                >
                    <option disabled value="">Selecione…</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <InputLabel for="board_name" value="Nome do quadro (opcional)" />
                <TextInput id="board_name" v-model="form.board_name" class="mt-1 w-full" />
            </div>
            <PrimaryButton :disabled="form.processing">Ativar</PrimaryButton>
        </form>
    </AdminLayout>
</template>
