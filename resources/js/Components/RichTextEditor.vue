<script setup>
/**
 * Editor rich text TipTap (barra estilo CKEditor) para documentos HTML.
 */
import { EditorContent, useEditor } from '@tiptap/vue-3';
import Color from '@tiptap/extension-color';
import Link from '@tiptap/extension-link';
import TextStyle from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import {
    BoldIcon,
    ItalicIcon,
    LinkIcon,
    ListBulletIcon,
    UnderlineIcon,
} from '@heroicons/vue/24/outline';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Escreva o conteúdo do regulamento…' },
    minHeightClass: { type: String, default: 'min-h-[280px]' },
});

const emit = defineEmits(['update:modelValue']);

function toEditorHtml(value) {
    const text = String(value || '').trim();
    if (!text) {
        return '<p></p>';
    }
    if (text.startsWith('<')) {
        return text;
    }
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    return `<p>${escaped.replace(/\n/g, '<br>')}</p>`;
}

function fromEditorHtml(html) {
    const value = String(html || '').trim();
    if (!value || value === '<p></p>') {
        return '';
    }
    return value;
}

const editor = useEditor({
    extensions: [
        StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
        Underline,
        TextStyle,
        Color,
        Link.configure({ openOnClick: false, autolink: true }),
    ],
    content: toEditorHtml(props.modelValue),
    editorProps: {
        attributes: {
            class: `${props.minHeightClass} max-h-[520px] overflow-y-auto px-4 py-3 text-sm text-slate-800 focus:outline-none prose prose-sm max-w-none [&_p]:my-2 [&_h2]:mt-4 [&_h2]:mb-2 [&_h3]:mt-3 [&_ul]:my-2 [&_ol]:my-2`,
            'data-placeholder': props.placeholder,
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', fromEditorHtml(ed.getHTML()));
    },
});

watch(
    () => props.modelValue,
    (value) => {
        const current = fromEditorHtml(editor.value?.getHTML());
        const next = String(value || '');
        if (current !== next) {
            editor.value?.commands.setContent(toEditorHtml(next), false);
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

const toolbarBtn =
    'rounded px-2 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-slate-900';
const toolbarBtnActive = 'bg-white text-talents-700 ring-1 ring-slate-200';

function setLink() {
    const previous = editor.value?.getAttributes('link').href || '';
    const url = window.prompt('URL do link', previous);
    if (url === null) {
        return;
    }
    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50/90 px-2 py-1.5">
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('bold') ? toolbarBtnActive : '']"
                title="Negrito"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <BoldIcon class="h-4 w-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('italic') ? toolbarBtnActive : '']"
                title="Itálico"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <ItalicIcon class="h-4 w-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('underline') ? toolbarBtnActive : '']"
                title="Sublinhado"
                @click="editor?.chain().focus().toggleUnderline().run()"
            >
                <UnderlineIcon class="h-4 w-4" aria-hidden="true" />
            </button>
            <span class="mx-1 h-5 w-px bg-slate-200" aria-hidden="true" />
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('heading', { level: 2 }) ? toolbarBtnActive : '']"
                title="Título"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                H2
            </button>
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('heading', { level: 3 }) ? toolbarBtnActive : '']"
                title="Subtítulo"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                H3
            </button>
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('bulletList') ? toolbarBtnActive : '']"
                title="Lista"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <ListBulletIcon class="h-4 w-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('orderedList') ? toolbarBtnActive : '']"
                title="Lista numerada"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                1.
            </button>
            <button
                type="button"
                :class="[toolbarBtn, editor?.isActive('link') ? toolbarBtnActive : '']"
                title="Link"
                @click="setLink"
            >
                <LinkIcon class="h-4 w-4" aria-hidden="true" />
            </button>
            <label
                class="ml-auto inline-flex cursor-pointer items-center gap-1 rounded border border-slate-200 bg-white px-1.5 py-1 text-[11px] text-slate-600"
                title="Cor do texto"
            >
                Cor
                <input
                    type="color"
                    class="h-5 w-6 cursor-pointer border-0 bg-transparent p-0"
                    @input="editor?.chain().focus().setColor($event.target.value).run()"
                >
            </label>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
:deep(.ProseMirror p.is-editor-empty:first-child::before) {
    color: #94a3b8;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
