<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import MiaNr1AdminPanel from '@/Components/MiaNr1AdminPanel.vue';
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import { marked } from 'marked';
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';

marked.setOptions({ breaks: true, gfm: true });

const props = defineProps({
    company: Object,
    survey: Object,
    overall: { type: Object, default: null },
    bySection: { type: Array, default: () => [] },
    deptOveralls: { type: Array, default: () => [] },
    deptSectionsByDepartment: { type: Array, default: () => [] },
    insights: { type: Array, default: () => [] },
    questionDistributions: { type: Array, default: () => [] },
    departmentParticipation: { type: Array, default: () => [] },
    questionDistributionsByDepartment: { type: Array, default: () => [] },
    plan: { type: Object, default: null },
    technical_opinion: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
    aiGeneratePostUrl: { type: String, required: true },
    technicalOpinionAi: { type: Object, default: null },
    technicalOpinionAiPending: { type: Boolean, default: false },
    technicalOpinionGeneratePostUrl: { type: String, required: true },
});

const initialOpinionHtml =
    props.technical_opinion?.trim() || props.plan?.technical_opinion?.trim() || '<p></p>';

const form = useForm({
    items:
        props.items.length > 0
            ? props.items.map((i) => ({ title: i.title, description: i.description ?? '' }))
            : [{ title: '', description: '' }],
    technical_opinion: initialOpinionHtml === '<p></p>' ? '' : initialOpinionHtml,
    technical_opinion_file: null,
    remove_technical_opinion_file: false,
});

const existingOpinionFileName = ref(props.plan?.technical_opinion_file_name ?? null);
const existingOpinionFileUrl = ref(props.plan?.technical_opinion_file_url ?? null);
const opinionFileInput = ref(null);

const onOpinionFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.technical_opinion_file = file;
    if (file) {
        form.remove_technical_opinion_file = false;
    }
};

const clearSelectedOpinionFile = () => {
    form.technical_opinion_file = null;
    if (opinionFileInput.value) {
        opinionFileInput.value.value = '';
    }
};

const removeExistingOpinionFile = () => {
    form.remove_technical_opinion_file = true;
    existingOpinionFileName.value = null;
    existingOpinionFileUrl.value = null;
    clearSelectedOpinionFile();
};

const opinionEditor = useEditor({
    extensions: [StarterKit.configure({ heading: { levels: [2, 3, 4] } }), Underline],
    content: initialOpinionHtml,
    editorProps: {
        attributes: {
            class: 'min-h-[280px] px-3 py-2 focus:outline-none border border-gray-200 rounded-b-xl rounded-t-none text-sm prose prose-sm max-w-none',
        },
    },
});

onBeforeUnmount(() => {
    opinionEditor.value?.destroy();
});

const syncEditorToForm = () => {
    const html = opinionEditor.value?.getHTML() ?? '';
    form.technical_opinion = html === '<p></p>' ? '' : html;
};

const technicalOpinionPreviewHtml = computed(() => {
    const text = props.technicalOpinionAi?.content ?? '';
    if (!text) {
        return '';
    }
    try {
        return marked.parse(text);
    } catch {
        return '';
    }
});

const requestTechnicalOpinionAi = () => {
    router.post(props.technicalOpinionGeneratePostUrl);
};

const insertTechnicalOpinionFromAi = () => {
    const text = props.technicalOpinionAi?.content;
    if (!text || !opinionEditor.value) {
        return;
    }
    try {
        const html = marked.parse(text);
        opinionEditor.value.commands.setContent(html);
        syncEditorToForm();
    } catch {
        opinionEditor.value.commands.setContent(`<p>${text.replace(/\n/g, '<br>')}</p>`);
        syncEditorToForm();
    }
};

let technicalPollTimer = null;

const startTechnicalPollIfPending = () => {
    if (technicalPollTimer) {
        clearInterval(technicalPollTimer);
        technicalPollTimer = null;
    }
    if (props.technicalOpinionAiPending) {
        technicalPollTimer = setInterval(() => {
            router.reload({
                only: ['technicalOpinionAi', 'technicalOpinionAiPending', 'flash'],
            });
        }, 5000);
    }
};

onMounted(() => {
    startTechnicalPollIfPending();
});

watch(
    () => props.technicalOpinionAiPending,
    () => {
        startTechnicalPollIfPending();
    },
);

onUnmounted(() => {
    if (technicalPollTimer) {
        clearInterval(technicalPollTimer);
    }
});

const addRow = () => {
    form.items.push({ title: '', description: '' });
};

const removeRow = (index) => {
    form.items.splice(index, 1);
    if (form.items.length === 0) {
        form.items.push({ title: '', description: '' });
    }
};

const submit = () => {
    syncEditorToForm();
    form
        .transform((data) => ({
            items: data.items.filter((row) => String(row.title).trim() !== ''),
            technical_opinion: data.technical_opinion || null,
            technical_opinion_file: data.technical_opinion_file,
            remove_technical_opinion_file: data.remove_technical_opinion_file,
        }))
        .put(route('admin.companies.surveys.action-plan.update', [props.company.id, props.survey.id]), {
            onSuccess: () => {
                clearSelectedOpinionFile();
                form.remove_technical_opinion_file = false;
            },
        });
};
</script>

<template>
    <Head :title="`Parecer e plano — ${survey.title}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.show', company.id)"
                back-label="Voltar à empresa"
                title="Parecer técnico e plano de ação (NR-1)"
                :subtitle="`${company.name} — ${survey.title}`"
            />
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
        >
            {{ $page.props.flash.error }}
        </div>
        <div
            v-if="$page.props.flash?.info"
            class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900"
        >
            {{ $page.props.flash.info }}
        </div>

        <div class="mb-6 surface-card p-5">
            <h3 class="text-sm font-semibold text-gray-900">Pesquisa</h3>
            <dl class="mt-3 grid gap-2 text-sm text-gray-700 sm:grid-cols-2">
                <div><span class="text-gray-500">Status:</span> {{ survey.status }}</div>
                <div>
                    <span class="text-gray-500">Período:</span>
                    {{ survey.starts_at }} — {{ survey.ends_at }}
                </div>
                <div class="sm:col-span-2">
                    <span class="text-gray-500">Mín. respondentes por setor (quebra):</span>
                    {{ survey.min_responses_for_breakdown }}
                </div>
            </dl>
        </div>

        <div class="mb-4">
            <h3 class="text-lg font-semibold text-talents-900">Detalhamento dos resultados</h3>
            <p class="mt-1 text-sm text-gray-600">
                Visão completa da pesquisa — os mesmos dados agregados que a empresa vê em Resultados.
            </p>
        </div>

        <Nr1SurveyResultsPanel
            :survey="survey"
            :overall="overall"
            :by-section="bySection"
            :dept-overalls="deptOveralls"
            :dept-sections-by-department="deptSectionsByDepartment"
            :insights="insights"
            :question-distributions="questionDistributions"
            :department-participation="departmentParticipation"
            :question-distributions-by-department="questionDistributionsByDepartment"
        />

        <div v-if="!overall" class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            Ainda não há resultados agregados para esta pesquisa. Verifique se há respostas concluídas e se o recálculo foi feito no portal da
            empresa.
        </div>

        <MiaNr1AdminPanel
            v-if="aiEnabled"
            :generate-post-url="aiGeneratePostUrl"
            :ai-enabled="aiEnabled"
            :ai-analysis="aiAnalysis"
            :ai-analysis-pending="aiAnalysisPending"
        />
        <div v-else class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            A Mia não está disponível (API desativada ou sem chave). Configure em
            <Link :href="route('admin.settings.edit')" class="font-medium text-talents-800 underline">Configurações</Link>
            para gerar análise automática e apoiar o preenchimento do parecer e do plano.
        </div>

        <div class="mb-6 rounded-lg border border-sky-100 bg-sky-50/80 p-4 text-sm text-sky-950">
            <p>
                Redija o <strong>parecer técnico</strong> e/ou os <strong>itens do plano de ação</strong>. Ao salvar com conteúdo em qualquer
                um deles, o material fica <strong>visível para a empresa</strong> na página Plano de ação. Para ocultar tudo do cliente, limpe
                o parecer e remova todos os itens e salve.
            </p>
            <p v-if="plan?.admin_published_at" class="mt-2 text-xs text-sky-900/80">
                Última publicação: {{ plan.admin_published_at }}
            </p>
        </div>

        <form class="space-y-8" @submit.prevent="submit">
            <div class="surface-card p-6 text-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-talents-800">Parecer técnico</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Documento entregue à empresa. Use a Mia para gerar um rascunho com recomendações e edite antes de publicar.
                        </p>
                    </div>
                    <div v-if="aiEnabled" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full bg-talents-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-talents-800 disabled:opacity-50"
                            :disabled="technicalOpinionAiPending"
                            @click="requestTechnicalOpinionAi"
                        >
                            {{ technicalOpinionAi ? 'Gerar novo parecer com IA' : 'Gerar parecer com IA' }}
                        </button>
                        <button
                            v-if="technicalOpinionAiPending"
                            type="button"
                            class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="router.reload({ only: ['technicalOpinionAi', 'technicalOpinionAiPending', 'flash'] })"
                        >
                            Atualizar agora
                        </button>
                        <button
                            v-if="technicalOpinionAi && !technicalOpinionAiPending"
                            type="button"
                            class="rounded-full border border-talents-300 bg-white px-4 py-2 text-sm font-medium text-talents-800 hover:bg-talents-50"
                            @click="insertTechnicalOpinionFromAi"
                        >
                            Inserir no editor
                        </button>
                    </div>
                </div>

                <div
                    v-if="technicalOpinionAiPending"
                    class="mt-4 flex items-center gap-3 rounded-xl border border-talents-100 bg-talents-50/50 px-4 py-3 text-sm text-talents-900"
                >
                    Mia está elaborando o parecer técnico…
                </div>

                <div
                    v-else-if="technicalOpinionAi"
                    class="mia-prose prose prose-sm mt-4 max-h-48 overflow-y-auto rounded-lg border border-gray-100 bg-gray-50/80 p-4 text-gray-800"
                >
                    <p class="mb-2 text-xs font-medium text-gray-500">Pré-visualização do rascunho da IA (clique em Inserir no editor):</p>
                    <div v-html="technicalOpinionPreviewHtml" />
                </div>

                <div v-if="opinionEditor" class="mt-4">
                    <div class="flex flex-wrap gap-1 rounded-t-xl border border-b-0 border-gray-200 bg-gray-50 p-2">
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-gray-700 hover:bg-white"
                            @click="opinionEditor.chain().focus().toggleBold().run()"
                        >
                            Negrito
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-gray-700 hover:bg-white"
                            @click="opinionEditor.chain().focus().toggleItalic().run()"
                        >
                            Itálico
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-gray-700 hover:bg-white"
                            @click="opinionEditor.chain().focus().toggleUnderline().run()"
                        >
                            Sublinhado
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-gray-700 hover:bg-white"
                            @click="opinionEditor.chain().focus().toggleHeading({ level: 2 }).run()"
                        >
                            Título
                        </button>
                        <button
                            type="button"
                            class="rounded px-2 py-1 text-xs font-medium text-gray-700 hover:bg-white"
                            @click="opinionEditor.chain().focus().toggleBulletList().run()"
                        >
                            Lista
                        </button>
                    </div>
                    <editor-content :editor="opinionEditor" />
                </div>

                <div v-if="form.errors.technical_opinion" class="mt-2 text-sm text-red-600">
                    {{ form.errors.technical_opinion }}
                </div>

                <div class="mt-6 rounded-xl border border-dashed border-gray-300 bg-gray-50/60 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">Anexar arquivo do parecer (opcional)</h4>
                    <p class="mt-1 text-xs text-gray-600">
                        Em vez de (ou além de) digitar acima, você pode enviar um arquivo PDF, DOC ou DOCX. A empresa poderá baixá-lo na
                        página Plano de ação. Tamanho máximo: 20 MB.
                    </p>

                    <div
                        v-if="existingOpinionFileName"
                        class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-2 text-gray-800">
                            <DocumentTextIcon class="h-5 w-5 text-talents-700" aria-hidden="true" />
                            <a
                                v-if="existingOpinionFileUrl"
                                :href="existingOpinionFileUrl"
                                class="font-medium text-talents-800 hover:underline"
                            >
                                {{ existingOpinionFileName }}
                            </a>
                            <span v-else class="font-medium">{{ existingOpinionFileName }}</span>
                            <span class="text-xs text-gray-500">(arquivo atual)</span>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                            @click="removeExistingOpinionFile"
                        >
                            Remover arquivo
                        </button>
                    </div>

                    <div class="mt-3">
                        <input
                            ref="opinionFileInput"
                            type="file"
                            accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-talents-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-talents-800"
                            @change="onOpinionFileChange"
                        />
                        <p v-if="form.technical_opinion_file" class="mt-2 text-xs text-emerald-700">
                            Novo arquivo selecionado: {{ form.technical_opinion_file.name }}
                            <button type="button" class="ml-2 text-red-600 hover:underline" @click="clearSelectedOpinionFile">
                                cancelar
                            </button>
                        </p>
                        <p v-if="form.progress" class="mt-2 text-xs text-gray-500">
                            Enviando… {{ form.progress.percentage }}%
                        </p>
                        <p v-if="form.errors.technical_opinion_file" class="mt-2 text-sm text-red-600">
                            {{ form.errors.technical_opinion_file }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-6 surface-card p-6 text-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-talents-800">Itens do plano de ação</h3>
                    <button type="button" class="text-sm font-medium text-talents-700 hover:underline" @click="addRow">+ Adicionar item</button>
                </div>

                <div v-for="(row, index) in form.items" :key="index" class="rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="grid flex-1 gap-3 sm:grid-cols-1">
                            <div>
                                <InputLabel :for="'title-' + index" value="Título" />
                                <TextInput
                                    :id="'title-' + index"
                                    v-model="row.title"
                                    class="mt-1 block w-full"
                                    placeholder="Ex.: Revisar dimensão demanda psicológica"
                                />
                            </div>
                            <div>
                                <InputLabel :for="'desc-' + index" value="Descrição" />
                                <textarea
                                    :id="'desc-' + index"
                                    v-model="row.description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    placeholder="Orientações e próximos passos para a empresa..."
                                />
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50"
                            @click="removeRow(index)"
                        >
                            Remover
                        </button>
                    </div>
                </div>

                <div v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</div>
            </div>

            <div class="flex gap-3">
                <PrimaryButton :disabled="form.processing">Salvar e publicar para a empresa</PrimaryButton>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.mia-prose :deep(h2) {
    margin-top: 1em;
    margin-bottom: 0.4em;
    font-weight: 700;
    font-size: 1rem;
}
.mia-prose :deep(p) {
    margin-top: 0.5em;
    line-height: 1.5;
    font-size: 0.875rem;
}
</style>
