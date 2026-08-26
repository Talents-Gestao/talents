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
import { computed, ref } from 'vue';

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
    file: null,
    remove_file: false,
});

const fileInput = ref(null);
const selectedFileName = ref('');

const existingFileName = computed(() => {
    if (form.remove_file) {
        return null;
    }
    return props.regulation?.file_name ?? null;
});

const fileStatusLabel = computed(() => {
    if (selectedFileName.value) {
        return selectedFileName.value;
    }
    if (existingFileName.value) {
        return existingFileName.value;
    }
    return 'Opcional — PDF, DOC ou DOCX (máx. 20 MB)';
});

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.file = file;
    form.remove_file = false;
    selectedFileName.value = file?.name ?? '';
};

const clearFile = () => {
    form.file = null;
    selectedFileName.value = '';
    form.remove_file = Boolean(props.regulation?.has_file);
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('admin.regulamento-interno.store'), { forceFormData: true });
        return;
    }
    form.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(route('admin.regulamento-interno.update', props.regulation.id), {
        forceFormData: true,
    });
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

            <div>
                <InputLabel for="file" value="Anexo (arquivo)" />
                <input
                    id="file"
                    ref="fileInput"
                    type="file"
                    class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-talents-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-talents-800 hover:file:bg-talents-100"
                    accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    @change="onFileChange"
                >
                <p class="mt-1.5 text-xs text-slate-500">{{ fileStatusLabel }}</p>
                <div v-if="existingFileName || selectedFileName" class="mt-2 flex flex-wrap items-center gap-3">
                    <a
                        v-if="mode === 'edit' && existingFileName && !selectedFileName"
                        :href="route('admin.regulamento-interno.download', regulation.id)"
                        class="text-sm font-medium text-talents-700 hover:underline"
                    >
                        Descarregar anexo atual
                    </a>
                    <button
                        type="button"
                        class="text-sm font-medium text-rose-600 hover:underline"
                        @click="clearFile"
                    >
                        Remover anexo
                    </button>
                </div>
                <InputError class="mt-1" :message="form.errors.file" />
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
                    {{ form.processing ? 'Salvando…' : mode === 'create' ? 'Criar' : 'Salvar' }}
                </PrimaryButton>
                <Link :href="route('admin.regulamento-interno.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
