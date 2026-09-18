<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import MiaNr1AdminPanel from '@/Components/MiaNr1AdminPanel.vue';
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import { marked } from 'marked';
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';
import { formatDateNumeric } from '@/utils/dateOnly';

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
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
    aiGeneratePostUrl: { type: String, required: true },
    technicalOpinionAi: { type: Object, default: null },
    technicalOpinionAiPending: { type: Boolean, default: false },
    technicalOpinionGeneratePostUrl: { type: String, required: true },
    riskScenario: { type: String, default: null },
    riskScenarioLabel: { type: String, default: null },
    nr1Reports: { type: Object, default: () => ({ executive: null, technical_referral: null }) },
});

const { canAdmin } = useAdminPermissions();
const showDeleteSurveyModal = ref(false);
const showExportPdfModal = ref(false);
const showPublishConfirmModal = ref(false);
const previewingPlanPdf = ref(false);
const publishPreviewPdfUrl = ref(null);
const publishPreviewError = ref(null);
const showDangerZone = ref(false);

const activeStep = ref('results');
const steps = [
    { id: 'results', number: '01', label: 'Diagnóstico' },
    { id: 'opinion', number: '02', label: 'Parecer' },
    { id: 'publish', number: '03', label: 'Publicar' },
];

const isPublished = computed(() => Boolean(props.plan?.admin_published_at));

const publicationStatusLabel = computed(() =>
    isPublished.value ? 'Publicado para a empresa' : 'Rascunho — ainda não enviado',
);

const selectStep = (stepId) => {
    activeStep.value = stepId;
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const revokePublishPreviewUrl = () => {
    if (publishPreviewPdfUrl.value) {
        URL.revokeObjectURL(publishPreviewPdfUrl.value);
        publishPreviewPdfUrl.value = null;
    }
};

const closePublishConfirmModal = () => {
    showPublishConfirmModal.value = false;
    publishPreviewError.value = null;
    revokePublishPreviewUrl();
};

const loadPublishPreviewPdf = async () => {
    syncEditorToForm();
    previewingPlanPdf.value = true;
    publishPreviewError.value = null;
    revokePublishPreviewUrl();

    try {
        const response = await window.axios.post(
            route('admin.companies.surveys.action-plan.document-pdf', [props.company.id, props.survey.id]),
            {
                technical_opinion: form.technical_opinion || null,
                is_draft: false,
            },
            { responseType: 'blob' },
        );
        const contentType = String(response.headers['content-type'] ?? '');
        if (!contentType.includes('pdf')) {
            throw new Error('Resposta inválida ao gerar o PDF.');
        }
        const blob = new Blob([response.data], { type: 'application/pdf' });
        publishPreviewPdfUrl.value = URL.createObjectURL(blob);
    } catch {
        publishPreviewError.value =
            'Não foi possível gerar a pré-visualização do PDF. Você ainda pode confirmar a publicação.';
    } finally {
        previewingPlanPdf.value = false;
    }
};

const openPublishConfirmModal = async () => {
    showPublishConfirmModal.value = true;
    await loadPublishPreviewPdf();
};

const confirmPublish = () => {
    closePublishConfirmModal();
    submit();
};

const openExportPdfModal = () => {
    showExportPdfModal.value = true;
};

const confirmExportPdf = () => {
    const url = route('admin.companies.surveys.action-plan.pdf', {
        company: props.company.id,
        survey: props.survey.id,
    });
    showExportPdfModal.value = false;
    window.open(url, '_blank', 'noopener');
};

const deleteSurvey = () => {
    router.delete(route('admin.companies.surveys.destroy', [props.company.id, props.survey.id]), {
        onFinish: () => {
            showDeleteSurveyModal.value = false;
        },
    });
};

const initialOpinionHtml =
    props.technical_opinion?.trim() || props.plan?.technical_opinion?.trim() || '<p></p>';

const form = useForm({
    technical_opinion: initialOpinionHtml === '<p></p>' ? '' : initialOpinionHtml,
    technical_opinion_file: null,
    remove_technical_opinion_file: false,
    executive_report_file: null,
    remove_executive_report_file: false,
    technical_referral_file: null,
    remove_technical_referral_file: false,
});

const existingOpinionFileName = ref(props.plan?.technical_opinion_file_name ?? null);
const existingOpinionFileUrl = ref(props.plan?.technical_opinion_file_url ?? null);
const opinionFileInput = ref(null);

const existingExecutiveFileName = ref(props.nr1Reports?.executive?.file_name ?? null);
const existingExecutiveFileUrl = ref(props.nr1Reports?.executive?.download_url ?? null);
const executiveFileInput = ref(null);

const existingReferralFileName = ref(props.nr1Reports?.technical_referral?.file_name ?? null);
const existingReferralFileUrl = ref(props.nr1Reports?.technical_referral?.download_url ?? null);
const referralFileInput = ref(null);

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

const onExecutiveFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.executive_report_file = file;
    if (file) {
        form.remove_executive_report_file = false;
    }
};

const clearSelectedExecutiveFile = () => {
    form.executive_report_file = null;
    if (executiveFileInput.value) {
        executiveFileInput.value.value = '';
    }
};

const removeExistingExecutiveFile = () => {
    form.remove_executive_report_file = true;
    existingExecutiveFileName.value = null;
    existingExecutiveFileUrl.value = null;
    clearSelectedExecutiveFile();
};

const onReferralFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.technical_referral_file = file;
    if (file) {
        form.remove_technical_referral_file = false;
    }
};

const clearSelectedReferralFile = () => {
    form.technical_referral_file = null;
    if (referralFileInput.value) {
        referralFileInput.value.value = '';
    }
};

const removeExistingReferralFile = () => {
    form.remove_technical_referral_file = true;
    existingReferralFileName.value = null;
    existingReferralFileUrl.value = null;
    clearSelectedReferralFile();
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
    revokePublishPreviewUrl();
});

const submit = () => {
    syncEditorToForm();
    form
        .transform((data) => ({
            technical_opinion: data.technical_opinion || null,
            technical_opinion_file: data.technical_opinion_file,
            remove_technical_opinion_file: data.remove_technical_opinion_file,
            executive_report_file: data.executive_report_file,
            remove_executive_report_file: data.remove_executive_report_file,
            technical_referral_file: data.technical_referral_file,
            remove_technical_referral_file: data.remove_technical_referral_file,
        }))
        .put(route('admin.companies.surveys.action-plan.update', [props.company.id, props.survey.id]), {
            onSuccess: () => {
                clearSelectedOpinionFile();
                clearSelectedExecutiveFile();
                clearSelectedReferralFile();
                form.remove_technical_opinion_file = false;
                form.remove_executive_report_file = false;
                form.remove_technical_referral_file = false;
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
            >
                <template #trailing>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        @click="openExportPdfModal"
                    >
                        Exportar resultados
                    </button>
                </template>
            </FormPageHeader>
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

        <!-- Cabeçalho de laudo -->
        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-gradient-to-r from-talents-50/70 via-white to-white px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-talents-700">Laudo NR-1</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ survey.title }}</h2>
                        <p class="mt-0.5 text-sm text-slate-600">{{ company.name }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                            :class="
                                isPublished
                                    ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                    : 'bg-amber-50 text-amber-900 ring-amber-200'
                            "
                        >
                            {{ publicationStatusLabel }}
                        </span>
                        <SurveyStatusBadge :status="survey.status" />
                    </div>
                </div>
            </div>
            <dl class="grid gap-4 px-5 py-4 text-sm sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Período</dt>
                    <dd class="mt-1 text-slate-800">
                        {{ formatDateNumeric(survey.starts_at) || '—' }} — {{ formatDateNumeric(survey.ends_at) || '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Cenário de risco</dt>
                    <dd class="mt-1 text-slate-800">{{ riskScenarioLabel || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Mín. por setor</dt>
                    <dd class="mt-1 text-slate-800">{{ survey.min_responses_for_breakdown }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Última publicação</dt>
                    <dd class="mt-1 text-slate-800">{{ plan?.admin_published_at || 'Ainda não publicada' }}</dd>
                </div>
            </dl>
        </section>

        <!-- Stepper -->
        <nav class="mb-8" aria-label="Etapas do parecer">
            <ol class="grid gap-2 sm:grid-cols-3">
                <li v-for="step in steps" :key="step.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition"
                        :class="
                            activeStep === step.id
                                ? 'border-talents-300 bg-talents-50 shadow-sm ring-1 ring-talents-200'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                        "
                        :aria-current="activeStep === step.id ? 'step' : undefined"
                        @click="selectStep(step.id)"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="
                                activeStep === step.id
                                    ? 'bg-talents-700 text-white'
                                    : 'bg-slate-100 text-slate-600'
                            "
                        >
                            {{ step.number }}
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ step.label }}</span>
                            <span class="block text-xs text-slate-500">
                                <template v-if="step.id === 'results'">Indicadores e detalhe</template>
                                <template v-else-if="step.id === 'opinion'">Texto, IA e anexos</template>
                                <template v-else>Revisar e enviar</template>
                            </span>
                        </span>
                    </button>
                </li>
            </ol>
        </nav>

        <!-- Etapa 01: Diagnóstico -->
        <div v-show="activeStep === 'results'" class="pb-8">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-talents-900">Diagnóstico dos resultados</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Indicadores e gráficos da pesquisa — os mesmos dados agregados que a empresa vê em Resultados.
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

            <div v-if="!overall" class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                Ainda não há resultados agregados para esta pesquisa. Verifique se há respostas concluídas e se o recálculo foi feito no portal da
                empresa.
            </div>

            <div class="mt-8 flex justify-end">
                <PrimaryButton type="button" @click="selectStep('opinion')">Continuar para o parecer</PrimaryButton>
            </div>
        </div>

        <form class="space-y-8" @submit.prevent="openPublishConfirmModal">
            <div v-show="activeStep === 'opinion'" class="space-y-8 pb-28">
                <div>
                    <h3 class="text-lg font-semibold text-talents-900">02 · Parecer técnico</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Elabore o documento entregue à empresa. Use a Mia para rascunho e revise antes de publicar.
                    </p>
                </div>

                <div
                    v-if="overall && riskScenarioLabel"
                    class="rounded-lg border border-violet-200 bg-violet-50/80 p-4 text-sm text-violet-950"
                >
                    <strong>{{ riskScenarioLabel }}</strong>
                    <span class="mt-1 block text-violet-900/90">
                        Os relatórios NR-1 (executivo e encaminhamento técnico) são gerados automaticamente conforme este cenário.
                        Você pode substituir o executivo ou o encaminhamento por arquivos personalizados abaixo.
                    </span>
                </div>

                <MiaNr1AdminPanel
                    v-if="aiEnabled"
                    :generate-post-url="aiGeneratePostUrl"
                    :ai-enabled="aiEnabled"
                    :ai-analysis="aiAnalysis"
                    :ai-analysis-pending="aiAnalysisPending"
                />
                <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    A Mia não está disponível (API desativada ou sem chave). Configure em
                    <Link :href="route('admin.settings.edit')" class="font-medium text-talents-800 underline">Configurações</Link>
                    para gerar análise automática e apoiar o preenchimento do parecer técnico.
                </div>

            <div class="surface-card space-y-6 p-6 text-slate-900">
                <div>
                    <h3 class="font-semibold text-talents-800">Relatórios NR-1 personalizados (opcional)</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Envie versões revisadas do relatório executivo ou do encaminhamento técnico. Quando publicados, a empresa baixa estes
                        arquivos em vez do PDF gerado automaticamente.
                    </p>
                </div>

                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/60 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">Relatório executivo</h4>
                    <div
                        v-if="existingExecutiveFileName"
                        class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-2 text-gray-800">
                            <DocumentTextIcon class="h-5 w-5 text-talents-700" aria-hidden="true" />
                            <a
                                v-if="existingExecutiveFileUrl"
                                :href="existingExecutiveFileUrl"
                                class="font-medium text-talents-800 hover:underline"
                            >
                                {{ existingExecutiveFileName }}
                            </a>
                            <span v-else class="font-medium">{{ existingExecutiveFileName }}</span>
                            <span v-if="nr1Reports?.executive?.published_at" class="text-xs text-gray-500">
                                (publicado em {{ nr1Reports.executive.published_at }})
                            </span>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                            @click="removeExistingExecutiveFile"
                        >
                            Remover
                        </button>
                    </div>
                    <div class="mt-3">
                        <input
                            ref="executiveFileInput"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-talents-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-talents-800"
                            @change="onExecutiveFileChange"
                        />
                        <p v-if="form.errors.executive_report_file" class="mt-2 text-sm text-red-600">
                            {{ form.errors.executive_report_file }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/60 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">Encaminhamento técnico</h4>
                    <div
                        v-if="existingReferralFileName"
                        class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm"
                    >
                        <div class="flex items-center gap-2 text-gray-800">
                            <DocumentTextIcon class="h-5 w-5 text-talents-700" aria-hidden="true" />
                            <a
                                v-if="existingReferralFileUrl"
                                :href="existingReferralFileUrl"
                                class="font-medium text-talents-800 hover:underline"
                            >
                                {{ existingReferralFileName }}
                            </a>
                            <span v-else class="font-medium">{{ existingReferralFileName }}</span>
                            <span v-if="nr1Reports?.technical_referral?.published_at" class="text-xs text-gray-500">
                                (publicado em {{ nr1Reports.technical_referral.published_at }})
                            </span>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                            @click="removeExistingReferralFile"
                        >
                            Remover
                        </button>
                    </div>
                    <div class="mt-3">
                        <input
                            ref="referralFileInput"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-talents-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-talents-800"
                            @change="onReferralFileChange"
                        />
                        <p v-if="form.errors.technical_referral_file" class="mt-2 text-sm text-red-600">
                            {{ form.errors.technical_referral_file }}
                        </p>
                    </div>
                </div>
            </div>

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

                <div v-else-if="technicalOpinionAi" class="mt-4">
                    <p class="mb-2 text-xs font-medium text-gray-500">
                        Pré-visualização do rascunho da IA (clique em Inserir no editor):
                    </p>
                    <div
                        class="mia-prose max-h-[min(70vh,40rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 text-sm leading-relaxed text-gray-800 shadow-sm sm:p-6 sm:text-[15px] sm:leading-7"
                    >
                        <div
                            class="[&_h1]:mb-3 [&_h1]:mt-5 [&_h1]:text-xl [&_h1]:font-semibold [&_h1]:text-slate-900 [&_h2]:mb-2.5 [&_h2]:mt-5 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-slate-900 [&_h3]:mb-2 [&_h3]:mt-4 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-slate-900 [&_li]:my-1 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2.5 [&_strong]:font-semibold [&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-5 [&_h1:first-child]:mt-0 [&_h2:first-child]:mt-0 [&_h3:first-child]:mt-0 [&_p:first-child]:mt-0"
                            v-html="technicalOpinionPreviewHtml"
                        />
                    </div>
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
            </div>

            <!-- Etapa 03: Publicar -->
            <div v-show="activeStep === 'publish'" class="space-y-6 pb-28">
                <div>
                    <h3 class="text-lg font-semibold text-talents-900">03 · Revisar e publicar</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Ao salvar, você verá o PDF do parecer como a empresa receberá e poderá confirmar o envio.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status atual</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">{{ publicationStatusLabel }}</p>
                        <p v-if="plan?.admin_published_at" class="mt-1 text-sm text-slate-600">
                            Última publicação: {{ plan.admin_published_at }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Entrega à empresa</p>
                        <ul class="mt-2 space-y-1 text-sm text-slate-700">
                            <li>· Parecer em texto (HTML) na página do cliente</li>
                            <li>· PDF do plano gerado a partir deste parecer</li>
                            <li v-if="existingOpinionFileName || form.technical_opinion_file">· Arquivo anexo do parecer</li>
                        </ul>
                    </div>
                </div>

                <div class="rounded-xl border border-talents-200 bg-talents-50/50 p-5 text-sm text-talents-950">
                    <p class="font-medium">Próximo passo</p>
                    <p class="mt-1 text-talents-900/90">
                        Clique em <strong>Salvar e publicar para a empresa</strong> no rodapé. Um modal mostrará a
                        pré-visualização do PDF; só após confirmar o parecer será enviado.
                    </p>
                    <div class="mt-4">
                        <SecondaryButton type="button" @click="selectStep('opinion')">Voltar ao editor</SecondaryButton>
                    </div>
                </div>
            </div>

            <!-- Sticky actions -->
            <div
                v-show="activeStep === 'opinion' || activeStep === 'publish'"
                class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur supports-[backdrop-filter]:bg-white/80"
            >
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500 sm:text-sm">
                        <template v-if="activeStep === 'opinion'">
                            Revise o texto e avance para publicar quando estiver pronto.
                        </template>
                        <template v-else>
                            A confirmação no modal publica o parecer para a empresa.
                        </template>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <SecondaryButton
                            v-if="activeStep === 'opinion'"
                            type="button"
                            @click="selectStep('results')"
                        >
                            Voltar
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="activeStep === 'publish'"
                            type="button"
                            @click="selectStep('opinion')"
                        >
                            Voltar
                        </SecondaryButton>
                        <PrimaryButton
                            v-if="activeStep === 'opinion'"
                            type="button"
                            @click="selectStep('publish')"
                        >
                            Revisar e publicar
                        </PrimaryButton>
                        <PrimaryButton
                            v-else
                            type="button"
                            :disabled="form.processing || previewingPlanPdf"
                            @click="openPublishConfirmModal"
                        >
                            Salvar e publicar para a empresa
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </form>

        <div
            v-if="canAdmin('companies', 'delete')"
            class="mt-10 overflow-hidden rounded-xl border border-slate-200 bg-white"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left text-sm font-medium text-slate-700 hover:bg-slate-50"
                :aria-expanded="showDangerZone"
                @click="showDangerZone = !showDangerZone"
            >
                <span>Zona de perigo</span>
                <span class="text-xs font-normal text-slate-500">{{ showDangerZone ? 'Ocultar' : 'Mostrar' }}</span>
            </button>
            <div v-show="showDangerZone" class="border-t border-red-100 bg-red-50/50 px-5 py-5">
                <h3 class="font-semibold text-red-900">Excluir pesquisa</h3>
                <p class="mt-2 text-sm text-red-900/80">
                    Excluir a pesquisa e todos os resultados. A empresa deixará de ver esta pesquisa; os dados serão
                    arquivados internamente.
                </p>
                <DangerButton type="button" class="mt-4" @click="showDeleteSurveyModal = true">
                    Excluir pesquisa
                </DangerButton>
            </div>
        </div>

        <Modal :show="showPublishConfirmModal" max-width="5xl" @close="closePublishConfirmModal">
            <div class="flex max-h-[min(90vh,52rem)] flex-col">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Confirmar publicação do parecer</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Revise o PDF abaixo — é o documento que a empresa poderá baixar após a publicação.
                    </p>
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                    <div
                        v-if="previewingPlanPdf"
                        class="flex h-[min(60vh,36rem)] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-600"
                    >
                        Gerando pré-visualização do PDF…
                    </div>
                    <div
                        v-else-if="publishPreviewError"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                    >
                        {{ publishPreviewError }}
                    </div>
                    <iframe
                        v-else-if="publishPreviewPdfUrl"
                        :src="publishPreviewPdfUrl"
                        title="Pré-visualização do PDF do parecer técnico"
                        class="h-[min(60vh,36rem)] w-full rounded-xl border border-slate-200 bg-white"
                    />
                    <div
                        v-else
                        class="flex h-40 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-500"
                    >
                        Nenhuma pré-visualização disponível.
                    </div>

                    <ul class="mt-4 space-y-1 text-sm text-slate-600">
                        <li>· A empresa verá o parecer na página Plano de ação.</li>
                        <li>· O PDF do plano será gerado a partir deste conteúdo.</li>
                        <li v-if="existingOpinionFileName || form.technical_opinion_file">
                            · O arquivo anexo do parecer também ficará disponível para download.
                        </li>
                    </ul>
                </div>

                <div
                    class="sticky bottom-0 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4"
                >
                    <SecondaryButton type="button" :disabled="form.processing" @click="closePublishConfirmModal">
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        type="button"
                        :disabled="form.processing || previewingPlanPdf"
                        @click="confirmPublish"
                    >
                        {{ form.processing ? 'Publicando…' : 'Confirmar e publicar' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showDeleteSurveyModal" @close="showDeleteSurveyModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Confirmar exclusão</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Excluir a pesquisa <strong>{{ survey.title }}</strong> e todos os resultados? A empresa deixará de
                    ver esta pesquisa. Os dados serão arquivados internamente.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showDeleteSurveyModal = false">Cancelar</SecondaryButton>
                    <DangerButton type="button" @click="deleteSurvey">Sim, excluir pesquisa</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showExportPdfModal" max-width="md" @close="showExportPdfModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Exportar PDF dos resultados</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Gera o relatório com indicadores e gráficos da pesquisa. Diferente do PDF do parecer técnico, que é
                    confirmado no fluxo de publicação.
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showExportPdfModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton type="button" @click="confirmExportPdf">Gerar PDF</PrimaryButton>
                </div>
            </div>
        </Modal>
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
