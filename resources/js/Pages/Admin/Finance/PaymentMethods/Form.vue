<script setup>
import FinanceModuleNav from '@/Components/Finance/FinanceModuleNav.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    method: { type: Object, default: null },
});

const form = useForm({
    name: props.method?.name ?? '',
    slug: props.method?.slug ?? '',
    sort_order: props.method?.sort_order ?? 0,
    is_active: props.method?.is_active ?? true,
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(route('admin.financeiro.formas-pagamento.update', props.method.id));
    } else {
        form.post(route('admin.financeiro.formas-pagamento.store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar forma de pagamento' : 'Nova forma de pagamento'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.financeiro.formas-pagamento.index')"
                back-label="Formas de pagamento"
                :title="mode === 'edit' ? 'Editar forma' : 'Nova forma'"
                subtitle="Cadastro usado no controle financeiro manual"
            />
        </template>

        <FinanceModuleNav />

        <form class="surface-card max-w-xl space-y-4 p-6" @submit.prevent="submit">
            <div>
                <InputLabel for="name" value="Nome" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>
            <div>
                <InputLabel for="slug" value="Slug (opcional)" />
                <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" placeholder="Gerado a partir do nome" />
                <InputError class="mt-1" :message="form.errors.slug" />
            </div>
            <div>
                <InputLabel for="sort_order" value="Ordem de exibição" />
                <TextInput id="sort_order" v-model="form.sort_order" type="number" min="0" class="mt-1 block w-full" />
                <InputError class="mt-1" :message="form.errors.sort_order" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-talents-600" />
                Ativa
            </label>
            <PrimaryButton :disabled="form.processing">
                {{ mode === 'edit' ? 'Salvar' : 'Cadastrar' }}
            </PrimaryButton>
        </form>
    </AdminLayout>
</template>
