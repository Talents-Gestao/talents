<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    regulation: { type: Object, default: null },
    companies: { type: Array, default: () => [] },
    selected_company_id: { type: Number, default: null },
});

const form = useForm({
    company_id: props.regulation?.company_id ?? props.selected_company_id ?? '',
    title: props.regulation?.title ?? '',
    body_html: props.regulation?.body_html ?? '',
    is_published: props.regulation?.is_published ?? false,
});

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('admin.regulamento-interno.store'));
        return;
    }
    form.put(route('admin.regulamento-interno.update', props.regulation.id));
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo regulamento' : 'Editar regulamento'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.regulamento-interno.index')"
                back-label="Regulamento interno"
                :title="mode === 'create' ? 'Novo regulamento interno' : 'Editar regulamento interno'"
                :subtitle="
                    mode === 'edit' && regulation?.company?.name
                        ? regulation.company.name
                        : 'Conteúdo formatado com editor rich text'
                "
            />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <form class="surface-card space-y-5 p-6 sm:p-7" @submit.prevent="submit">
            <div>
                <InputLabel for="company_id" value="Empresa" />
                <select
                    id="company_id"
                    v-model="form.company_id"
                    class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    required
                >
                    <option value="" disabled>Selecione a empresa…</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <InputError class="mt-1" :message="form.errors.company_id" />
            </div>

            <div>
                <InputLabel for="title" value="Título" />
                <TextInput
                    id="title"
                    v-model="form.title"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    maxlength="255"
                />
                <InputError class="mt-1" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel value="Conteúdo" />
                <div class="mt-1">
                    <RichTextEditor
                        v-model="form.body_html"
                        placeholder="Escreva o regulamento interno (títulos, listas, links, formatação)…"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.body_html" />
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input
                    v-model="form.is_published"
                    type="checkbox"
                    class="rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                >
                Publicado (visível para uso operacional)
            </label>

            <div class="flex flex-wrap gap-3 pt-2">
                <PrimaryButton type="submit" :disabled="form.processing">
                    {{ form.processing ? 'A guardar…' : mode === 'create' ? 'Criar' : 'Guardar' }}
                </PrimaryButton>
                <Link :href="route('admin.regulamento-interno.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
