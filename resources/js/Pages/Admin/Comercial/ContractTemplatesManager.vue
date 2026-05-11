<script setup>
import { EditorContent, useEditor } from '@tiptap/vue-3';
import Link from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, reactive } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
});

const PLACEHOLDERS = [
    '{{cliente_nome}}',
    '{{cliente_cnpj}}',
    '{{cliente_email}}',
    '{{cliente_telefone}}',
    '{{cliente_endereco}}',
    '{{numero_funcionarios}}',
    '{{servicos_lista}}',
    '{{servicos_detalhada_html}}',
    '{{total_reais}}',
    '{{total_extenso}}',
    '{{empresa_nome}}',
    '{{empresa_cnpj}}',
    '{{empresa_endereco}}',
    '{{empresa_telefone}}',
    '{{empresa_email}}',
    '{{cidade_estado}}',
    '{{forma_pagamento}}',
    '{{prazo_dias}}',
    '{{vendedor_nome}}',
    '{{validade_data}}',
    '{{data_hoje}}',
    '{{proposta_codigo}}',
];

const modal = reactive({
    open: false,
    editing: null,
    name: '',
    source_type: 'html',
    is_active: true,
    docx_file: null,
});

const editor = useEditor({
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
        Underline,
        Link.configure({ openOnClick: false, autolink: true }),
    ],
    content: '<p></p>',
    editorProps: {
        attributes: {
            class: 'min-h-[200px] px-3 py-2 focus:outline-none border border-slate-200 rounded-b-xl rounded-t-none text-sm',
        },
    },
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const openCreate = () => {
    modal.editing = null;
    modal.name = '';
    modal.source_type = 'html';
    modal.is_active = true;
    modal.docx_file = null;
    modal.open = true;
    queueMicrotask(() => editor.value?.commands.setContent('<p></p>'));
};

const openEdit = (t) => {
    modal.editing = t;
    modal.name = t.name;
    modal.source_type = t.source_type;
    modal.is_active = !!t.is_active;
    modal.docx_file = null;
    modal.open = true;
    queueMicrotask(() => {
        const html = t.body_html && t.source_type === 'html' ? t.body_html : '<p></p>';
        editor.value?.commands.setContent(html);
    });
};

const closeModal = () => {
    modal.open = false;
};

const copyPh = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        // ignore
    }
};

const submitModal = () => {
    const fd = new FormData();
    fd.append('name', modal.name);
    fd.append('source_type', modal.source_type);
    fd.append('is_active', modal.is_active ? '1' : '0');
    if (modal.source_type === 'html') {
        fd.append('body_html', editor.value?.getHTML() ?? '');
    }
    if (modal.docx_file) {
        fd.append('docx_file', modal.docx_file);
    }

    if (modal.editing) {
        fd.append('_method', 'put');
        router.post(route('admin.comercial.contract-templates.update', modal.editing.id), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: closeModal,
        });
    } else {
        router.post(route('admin.comercial.contract-templates.store'), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: closeModal,
        });
    }
};

const destroyTemplate = (t) => {
    if (!confirm(`Excluir o modelo "${t.name}"? Contratos já gerados permanecem no histórico.`)) return;
    router.delete(route('admin.comercial.contract-templates.destroy', t.id), { preserveScroll: true });
};

const docxUrl = (t) => route('admin.comercial.contract-templates.docx', t.id);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Use placeholders no texto, por exemplo
                <code v-pre class="rounded bg-slate-100 px-1 text-xs">{{cliente_nome}}</code>
                . HTML com editor ou importação de <strong>.docx</strong> (conversão automática).
            </p>
            <button
                type="button"
                class="inline-flex items-center rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                @click="openCreate"
            >
                Novo modelo
            </button>
        </div>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Nome</th>
                        <th class="px-4 py-3 text-left font-medium">Tipo</th>
                        <th class="px-4 py-3 text-left font-medium">Ativo</th>
                        <th class="px-4 py-3 text-right font-medium">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="t in templates" :key="t.id">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ t.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ t.source_type === 'docx' ? 'DOCX' : 'HTML' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="t.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ t.is_active ? 'Sim' : 'Não' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="rounded-lg px-2 py-1 text-sm text-talents-700 hover:bg-talents-50"
                                @click="openEdit(t)"
                            >
                                Editar
                            </button>
                            <a
                                v-if="t.has_docx"
                                :href="docxUrl(t)"
                                class="ml-2 rounded-lg px-2 py-1 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                DOCX
                            </a>
                            <button
                                type="button"
                                class="ml-2 rounded-lg px-2 py-1 text-sm text-rose-600 hover:bg-rose-50"
                                @click="destroyTemplate(t)"
                            >
                                Excluir
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!templates.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-500">Nenhum modelo cadastrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="modal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeModal"
        >
            <div class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ modal.editing ? 'Editar modelo' : 'Novo modelo' }}
                </h3>

                <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_220px]">
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome *</label>
                            <input
                                v-model="modal.name"
                                type="text"
                                class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="modal.is_active" type="checkbox" class="rounded border-slate-300 text-talents-600" />
                            Modelo ativo (aparece na geração de contratos)
                        </label>
                        <div class="flex gap-4 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="modal.source_type" type="radio" value="html" class="text-talents-600" />
                                HTML (editor)
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input v-model="modal.source_type" type="radio" value="docx" class="text-talents-600" />
                                Arquivo .docx
                            </label>
                        </div>

                        <div v-if="modal.source_type === 'html'" class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Conteúdo</label>
                            <div v-if="editor" class="flex flex-wrap gap-1 rounded-t-xl border border-b-0 border-slate-200 bg-slate-50 p-2">
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleBold().run()"
                                >
                                    Negrito
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleItalic().run()"
                                >
                                    Itálico
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleUnderline().run()"
                                >
                                    Sublinhado
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                                >
                                    H2
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
                                >
                                    H3
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleBulletList().run()"
                                >
                                    Lista
                                </button>
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-slate-700 hover:bg-white"
                                    @click="editor.chain().focus().toggleOrderedList().run()"
                                >
                                    Num.
                                </button>
                            </div>
                            <editor-content v-if="editor" :editor="editor" />
                        </div>

                        <div v-else class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Arquivo .docx</label>
                            <input
                                type="file"
                                accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                @change="modal.docx_file = $event.target.files?.[0] ?? null"
                            />
                            <p v-if="modal.editing?.has_docx" class="text-xs text-slate-500">
                                Já existe um arquivo enviado. Envie outro apenas se quiser substituir.
                            </p>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                @click="closeModal"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-talents-600 px-4 py-2 text-sm font-semibold text-white hover:bg-talents-700"
                                @click="submitModal"
                            >
                                Salvar modelo
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Placeholders</p>
                        <p class="mt-1 text-xs text-slate-500">Clique para copiar.</p>
                        <ul class="mt-2 max-h-[50vh] space-y-1 overflow-y-auto text-xs">
                            <li v-for="ph in PLACEHOLDERS" :key="ph">
                                <button
                                    type="button"
                                    class="w-full truncate rounded px-1 py-0.5 text-left font-mono text-slate-700 hover:bg-white"
                                    :title="ph"
                                    @click="copyPh(ph)"
                                >
                                    {{ ph }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
