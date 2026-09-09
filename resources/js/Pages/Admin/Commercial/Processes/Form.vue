<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ArrowUpTrayIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    process: { type: Object, default: null },
});

const form = useForm({
    title: props.process?.title ?? '',
    summary: props.process?.summary ?? '',
    body_html: props.process?.body_html ?? '',
    sort_order: props.process?.sort_order ?? 0,
    is_published: props.process?.is_published ?? true,
    file: null,
    remove_file: false,
    replace_body_from_file: false,
});

const fileInput = ref(null);
const selectedFileName = ref('');
const titleTouched = ref(Boolean(props.process?.title));

const existingFileName = computed(() => {
    if (form.remove_file) {
        return null;
    }
    return props.process?.file_name ?? null;
});

const isWordFile = computed(() => {
    const name = (selectedFileName.value || '').toLowerCase();
    return name.endsWith('.doc') || name.endsWith('.docx');
});

const hasPendingWordImport = computed(
    () => Boolean(selectedFileName.value && isWordFile.value && form.replace_body_from_file),
);

const fileStatusLabel = computed(() => {
    if (selectedFileName.value) {
        return selectedFileName.value;
    }
    if (existingFileName.value) {
        return existingFileName.value;
    }
    return 'Arraste ou escolha PDF, DOC ou DOCX (máx. 20 MB)';
});

const titleFromFileName = (fileName) => {
    const base = String(fileName || '')
        .replace(/\.(pdf|docx?|PDF|DOCX?)$/u, '')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    return base.slice(0, 255);
};

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.file = file;
    form.remove_file = false;
    selectedFileName.value = file?.name ?? '';

    if (!file) {
        form.replace_body_from_file = false;
        return;
    }

    const name = file.name.toLowerCase();
    const isWord = name.endsWith('.doc') || name.endsWith('.docx');
    form.replace_body_from_file = isWord;

    if (!titleTouched.value || !String(form.title || '').trim()) {
        form.title = titleFromFileName(file.name);
    }
};

const onTitleInput = () => {
    titleTouched.value = true;
};

const clearFile = () => {
    form.file = null;
    selectedFileName.value = '';
    form.replace_body_from_file = false;
    form.remove_file = Boolean(props.process?.has_file);
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const openFilePicker = () => {
    fileInput.value?.click();
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('admin.comercial.processos.store'), { forceFormData: true });
        return;
    }
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.comercial.processos.update', props.process.id), {
            forceFormData: true,
        });
};
</script>

<template>
    <Head :title="mode === 'create' ? 'Novo processo' : 'Editar processo'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.comercial.processos.index')"
                back-label="Processos"
                :title="mode === 'create' ? 'Novo processo comercial' : 'Editar processo comercial'"
                subtitle="Envie um Word/PDF ou edite o texto diretamente no sistema"
            />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <form class="surface-card space-y-5 p-6 sm:p-7" @submit.prevent="submit">
            <div class="rounded-2xl border border-dashed border-talents-300 bg-talents-50/40 p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-sm font-semibold text-talents-900">
                            <ArrowUpTrayIcon class="h-5 w-5 shrink-0" />
                            Importar documento (sem digitar)
                        </div>
                        <p class="mt-1 text-sm text-slate-600">
                            Envie um <strong>DOC</strong> ou <strong>DOCX</strong> para preencher o conteúdo
                            automaticamente. O PDF fica só como anexo para download.
                        </p>
                        <p class="mt-2 text-xs text-slate-500">{{ fileStatusLabel }}</p>
                        <p
                            v-if="hasPendingWordImport"
                            class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200"
                        >
                            <DocumentTextIcon class="h-4 w-4" />
                            Ao salvar, o texto do Word será carregado no editor.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                        <input
                            id="file"
                            ref="fileInput"
                            type="file"
                            class="sr-only"
                            accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            @change="onFileChange"
                        >
                        <PrimaryButton type="button" @click="openFilePicker">
                            Escolher arquivo
                        </PrimaryButton>
                        <button
                            v-if="existingFileName || selectedFileName"
                            type="button"
                            class="text-sm font-medium text-rose-600 hover:underline"
                            @click="clearFile"
                        >
                            Remover arquivo
                        </button>
                        <a
                            v-if="mode === 'edit' && existingFileName && !selectedFileName"
                            :href="route('admin.comercial.processos.download', process.id)"
                            class="text-sm font-medium text-talents-700 hover:underline"
                        >
                            Baixar anexo atual
                        </a>
                    </div>
                </div>

                <label
                    v-if="isWordFile"
                    class="mt-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700"
                >
                    <input
                        v-model="form.replace_body_from_file"
                        type="checkbox"
                        class="mt-0.5 rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                    >
                    <span>
                        Substituir o conteúdo do editor pelo texto do arquivo
                        <span class="block text-xs font-normal text-slate-500">
                            Recomendado ao importar um processo novo ou atualizar a versão oficial.
                        </span>
                    </span>
                </label>
                <InputError class="mt-2" :message="form.errors.file" />
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
                    @input="onTitleInput"
                />
                <p class="mt-1 text-xs text-slate-500">
                    Se você importar um arquivo, o título é sugerido pelo nome do documento.
                </p>
                <InputError class="mt-1" :message="form.errors.title" />
            </div>

            <div>
                <InputLabel for="summary" value="Resumo (opcional)" />
                <textarea
                    id="summary"
                    v-model="form.summary"
                    rows="2"
                    maxlength="2000"
                    class="mt-1 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    placeholder="Breve descrição do processo para a listagem"
                />
                <InputError class="mt-1" :message="form.errors.summary" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="sort_order" value="Ordem" />
                    <TextInput
                        id="sort_order"
                        v-model="form.sort_order"
                        type="number"
                        min="0"
                        max="9999"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.sort_order" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input
                            v-model="form.is_published"
                            type="checkbox"
                            class="rounded border-slate-300 text-talents-600 focus:ring-talents-500"
                        >
                        Publicado (visível na listagem operacional)
                    </label>
                </div>
            </div>

            <div>
                <InputLabel value="Conteúdo (edição manual ou após importação)" />
                <p class="mt-0.5 text-xs text-slate-500">
                    Opcional se você for importar um Word ao salvar. Depois da importação, pode ajustar o texto aqui.
                </p>
                <div class="mt-1">
                    <RichTextEditor
                        v-model="form.body_html"
                        min-height-class="min-h-[420px]"
                        placeholder="Deixe em branco e importe um DOCX, ou escreva o processo aqui…"
                    />
                </div>
                <InputError class="mt-1" :message="form.errors.body_html" />
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                <PrimaryButton type="submit" :disabled="form.processing">
                    {{
                        mode === 'create'
                            ? hasPendingWordImport
                                ? 'Importar e criar processo'
                                : 'Criar processo'
                            : hasPendingWordImport
                              ? 'Importar e salvar'
                              : 'Salvar alterações'
                    }}
                </PrimaryButton>
                <Link :href="route('admin.comercial.processos.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
