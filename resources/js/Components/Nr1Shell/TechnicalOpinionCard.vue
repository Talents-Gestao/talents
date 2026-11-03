<script setup>
import { router, useForm } from '@inertiajs/vue3';
import Underline from '@tiptap/extension-underline';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { computed, onBeforeUnmount, onUnmounted, ref, watch } from 'vue';
import Nr1Button from './Nr1Button.vue';
import { useNr1Toast } from './useNr1Toast';

/**
 * Parecer técnico da campanha V11 na Central de documentos. A Talents redige (com a sugestão da Mia, quando ligada) e salva
 * pela rota do plano. Só Talents e PASSEG o veem até a decisão da responsável técnica; os PDFs congelados não mudam.
 */
const props = defineProps({
    opinion: { type: Object, required: true },
});

const { push } = useNr1Toast();
const initial = props.opinion.html ?? '';
const form = useForm({ technical_opinion: initial });
const requesting = ref(false);

const editor = useEditor({
    extensions: [StarterKit.configure({ heading: { levels: [2, 3, 4] } }), Underline],
    content: initial,
    editorProps: { attributes: { class: 'n1-editor', role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Texto do parecer técnico' } },
});
onBeforeUnmount(() => editor.value?.destroy());

const ai = computed(() => props.opinion.ai ?? null);
const save = () => {
    form.technical_opinion = editor.value?.getHTML() ?? '';
    form.put(props.opinion.saveUrl, { preserveScroll: true, onSuccess: () => push('Parecer técnico salvo.') });
};
const requestDraft = () => {
    router.post(ai.value.postUrl, {}, {
        preserveScroll: true,
        onStart: () => { requesting.value = true; },
        onFinish: () => { requesting.value = false; },
        onSuccess: () => push('Sugestão de parecer solicitada à Mia.'),
    });
};
const insertDraft = () => editor.value?.commands.setContent(ai.value.content);

let poll = null;
const stopPoll = () => {
    clearInterval(poll);
    poll = null;
};
watch(
    () => ai.value?.pending,
    (pending) => {
        stopPoll();
        if (pending) poll = setInterval(() => router.reload({ only: ['section'] }), 5000);
    },
    { immediate: true },
);
onUnmounted(stopPoll);
</script>

<template>
    <section class="n1-card c12" aria-labelledby="opinion-title">
        <div class="n1-sectiontop">
            <h2 id="opinion-title">Parecer técnico</h2>
            <span class="n1-pill gray">Só Talents e PASSEG</span>
        </div>

            <p class="n1-tiny">A empresa não vê este texto. Ele não muda os PDFs já emitidos.</p>
            <editor-content v-if="editor" :editor="editor" />
            <p v-if="form.errors.technical_opinion" class="n1-tiny" role="alert">{{ form.errors.technical_opinion }}</p>
            <div class="n1-docactions no-print">
                <Nr1Button size="sm" variant="primary" :loading="form.processing" @click="save">Salvar parecer</Nr1Button>
                <Nr1Button v-if="ai" size="sm" :loading="requesting" :disabled="ai.pending" @click="requestDraft">Pedir sugestão à Mia</Nr1Button>
                <Nr1Button v-if="ai?.content && !ai.pending" size="sm" @click="insertDraft">Inserir sugestão no editor</Nr1Button>
            </div>
            <p v-if="ai?.pending" class="n1-notice" role="status" aria-live="polite" style="margin-top: 10px">Mia está redigindo a sugestão…</p>
    </section>
</template>
