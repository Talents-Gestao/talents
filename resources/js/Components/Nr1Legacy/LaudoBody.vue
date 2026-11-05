<script setup>
import CompanyShowAccordion from '@/Components/Admin/Companies/CompanyShowAccordion.vue';
import InfoTooltip from '@/Components/InfoTooltip.vue';
import ParecerMeasures from '@/Components/Nr1Legacy/ParecerMeasures.vue';
import SurveyStatusBadge from '@/Components/SurveyStatusBadge.vue';
import Nr1SurveyResultsPanel from '@/Components/Nr1SurveyResultsPanel.vue';
import Nr1DocumentPreviewModal from '@/Components/Nr1Legacy/Nr1DocumentPreviewModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
import { Link, router, useForm } from '@inertiajs/vue3';
import { marked } from 'marked';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { formatDateNumeric, formatDateTime } from '@/utils/dateOnly';

marked.setOptions({ breaks: true, gfm: true });

const props = defineProps({
    company: Object,
    survey: Object,
    bandLabels: { type: Object, required: true },
    overall: { type: Object, default: null },
    bySection: { type: Array, default: () => [] },
    deptOveralls: { type: Array, default: () => [] },
    deptSectionsByDepartment: { type: Array, default: () => [] },
    insights: { type: Array, default: () => [] },
    questionDistributions: { type: Array, default: () => [] },
    departmentParticipation: { type: Array, default: () => [] },
    questionDistributionsByDepartment: { type: Array, default: () => [] },
    plan: { type: Object, default: null },
    publication: {
        type: Object,
        default: () => ({
            requires_publication: false,
            published_at: null,
            uses_review_flow: false,
            documents_url: null,
        }),
    },
    technical_opinion: { type: String, default: '' },
    aiEnabled: { type: Boolean, default: false },
    aiAnalysis: { type: Object, default: null },
    aiAnalysisPending: { type: Boolean, default: false },
    aiGeneratePostUrl: { type: String, required: true },
    technicalOpinionAi: { type: Object, default: null },
    technicalOpinionAiPending: { type: Boolean, default: false },
    technicalOpinionGeneratePostUrl: { type: String, required: true },
    actionMeasures: {
        type: Object,
        default: () => ({
            measures: [],
            statuses: {},
            canEditSuggestion: true,
            dueOrigins: {},
            measuresAi: { generated_at: null, pending: false, refresh_url: null },
        }),
    },
    riskScenario: { type: String, default: null },
    riskScenarioLabel: { type: String, default: null },
    nr1Reports: { type: Object, default: () => ({ executive: null, technical_referral: null }) },
    executiveBrief: { type: Object, default: null },
    technicalReport: { type: Object, default: null },
    pgrSubsidy: { type: Object, default: null },
    psychosocialDocuments: { type: Array, default: () => [] },
    /** Shell NR-1: sem cabeçalho do laudo e sem a etapa 01 (Diagnóstico). A página antiga não passa esta prop. */
    shellMode: { type: Boolean, default: false },
});

const { canAdmin } = useAdminPermissions();
const showDeleteSurveyModal = ref(false);
const showExportPdfModal = ref(false);
const showPublishConfirmModal = ref(false);
const showTechnicalReportModal = ref(false);
const showPgrSubsidyModal = ref(false);
const previewingPlanPdf = ref(false);
const publishPreviewPdfUrl = ref(null);
const publishPreviewError = ref(null);
const showDangerZone = ref(false);
const opinionEditable = ref(null);
const opinionSection = ref(null);
const annexSection = ref(null);
const appliedAiContent = ref(null);

const activeStep = ref(props.shellMode ? 'opinion' : 'results');
const steps = [
    { id: 'results', number: '01', label: 'Diagnóstico' },
    { id: 'opinion', number: '02', label: 'Parecer' },
];

const visibleSteps = computed(() => (props.shellMode ? steps.filter((step) => step.id !== 'results') : steps));

const isPublished = computed(() => Boolean(props.plan?.admin_published_at));

const publicationStatusLabel = computed(() =>
    isPublished.value ? 'Publicado para a empresa' : 'Rascunho — ainda não enviado',
);

const technicalReportPublished = computed(() => props.technicalReport?.ready === true);

const canPublishParecer = computed(() => {
    if (props.publication?.uses_review_flow) {
        return true;
    }
    return technicalReportPublished.value;
});

const measureSectionDescription = computed(() => {
    const count = props.actionMeasures.measures?.length ?? 0;
    if (count === 0) {
        return 'Nenhuma medida ainda';
    }

    return count === 1 ? '1 medida' : `${count} medidas`;
});

const technicalReportReleaseUrl = computed(
    () =>
        props.technicalReport?.urls?.release ??
        route('admin.companies.surveys.technical-report.release', [props.company.id, props.survey.id]),
);

const technicalReportDownloadUrl = computed(
    () =>
        props.technicalReport?.urls?.download ??
        route('admin.companies.surveys.psychosocial-documents.download', [
            props.company.id,
            props.survey.id,
            'technical_report',
        ]),
);

const technicalReportPreviewUrl = computed(
    () =>
        props.technicalReport?.urls?.preview ??
        route('admin.companies.surveys.psychosocial-documents.preview', [
            props.company.id,
            props.survey.id,
            'technical_report',
        ]),
);

const pgrSubsidyReleaseUrl = computed(
    () =>
        props.pgrSubsidy?.urls?.release ??
        route('admin.companies.surveys.pgr-subsidy.release', [props.company.id, props.survey.id]),
);

const pgrSubsidyDownloadUrl = computed(
    () =>
        props.pgrSubsidy?.urls?.download ??
        route('admin.companies.surveys.psychosocial-documents.download', [
            props.company.id,
            props.survey.id,
            'pgr_subsidy',
        ]),
);

const pgrSubsidyPreviewUrl = computed(
    () =>
        props.pgrSubsidy?.urls?.preview ??
        route('admin.companies.surveys.psychosocial-documents.preview', [
            props.company.id,
            props.survey.id,
            'pgr_subsidy',
        ]),
);

const restoreDocModalAfterRedirect = () => {
    if (typeof sessionStorage === 'undefined') {
        return;
    }
    const key = sessionStorage.getItem('nr1-legacy-doc-modal');
    if (!key) {
        return;
    }
    sessionStorage.removeItem('nr1-legacy-doc-modal');
    if (key === 'technical_report') {
        showTechnicalReportModal.value = true;
    } else if (key === 'pgr_subsidy') {
        showPgrSubsidyModal.value = true;
    }
};

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

watch(
    () => form.errors.technical_opinion,
    (message) => {
        if (message) {
            opinionSection.value?.open();
        }
    },
);

watch(
    () => [form.errors.technical_opinion_file, form.errors.executive_report_file, form.errors.technical_referral_file],
    (messages) => {
        if (messages.some(Boolean)) {
            annexSection.value?.open();
        }
    },
);

const existingOpinionFileName = ref(props.plan?.technical_opinion_file_name ?? null);
const existingOpinionFileUrl = ref(props.plan?.technical_opinion_file_url ?? null);
const opinionFileInput = ref(null);

const existingExecutiveFileName = ref(props.nr1Reports?.executive?.file_name ?? null);
const existingExecutiveFileUrl = ref(props.nr1Reports?.executive?.download_url ?? null);
const executiveFileInput = ref(null);

const existingReferralFileName = ref(props.nr1Reports?.technical_referral?.file_name ?? null);
const existingReferralFileUrl = ref(props.nr1Reports?.technical_referral?.download_url ?? null);
const referralFileInput = ref(null);

const markdownToHtml = (text) => {
    if (!text) {
        return '';
    }
    const spaced = String(text)
        .replace(/\r\n/g, '\n')
        .replace(/^(#{1,3}[^\n]+)\n(?!\n)/gm, '$1\n\n')
        .replace(/([^\n])\n(?!\n)/g, '$1\n\n');
    try {
        return marked.parse(spaced, { breaks: false, gfm: true });
    } catch {
        return `<p>${String(text).replace(/\n+/g, '</p><p>')}</p>`;
    }
};

const syncEditableToForm = () => {
    const html = opinionEditable.value?.innerHTML ?? '';
    form.technical_opinion = html === '<p></p>' || html === '<br>' ? '' : html;
};

const setOpinionHtml = async (html) => {
    const normalized = !html || html === '<p></p>' ? '' : html;
    form.technical_opinion = normalized;
    await nextTick();
    if (opinionEditable.value) {
        opinionEditable.value.innerHTML = normalized || '<p><br></p>';
    }
};

const applyAiContentToEditor = async (content) => {
    if (!content || content === appliedAiContent.value) {
        return;
    }
    appliedAiContent.value = content;
    await setOpinionHtml(markdownToHtml(content));
};

const loadPublishPreviewPdf = async () => {
    syncEditableToForm();
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
    if (!canPublishParecer.value) {
        return;
    }
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

const registrationUrl = computed(() =>
    route('admin.companies.surveys.registration.edit', [props.company.id, props.survey.id]),
);

const openTechnicalReportModal = () => {
    showTechnicalReportModal.value = true;
};

const openPgrSubsidyModal = () => {
    showPgrSubsidyModal.value = true;
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

const requestTechnicalOpinionAi = () => {
    router.post(props.technicalOpinionGeneratePostUrl);
};

const publishedFiles = computed(() => props.technicalReport?.ready === true || props.pgrSubsidy?.ready === true);
const updatingFiles = ref(false);

const updatePublishedFiles = () => {
    if (!publishedFiles.value || updatingFiles.value) {
        return;
    }
    updatingFiles.value = true;
    router.post(
        route('admin.companies.surveys.documents.replace-published', [props.company.id, props.survey.id]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                updatingFiles.value = false;
            },
        },
    );
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
                only: [
                    'section',
                    'technicalOpinionAi',
                    'technicalOpinionAiPending',
                    'technicalReport',
                    'pgrSubsidy',
                    'flash',
                ],
            });
        }, 5000);
    }
};

onMounted(async () => {
    await setOpinionHtml(form.technical_opinion || '');
    if (props.technicalOpinionAi?.content) {
        await applyAiContentToEditor(props.technicalOpinionAi.content);
    }
    startTechnicalPollIfPending();
    restoreDocModalAfterRedirect();
});

watch(
    () => props.technicalOpinionAiPending,
    () => {
        startTechnicalPollIfPending();
    },
);

watch(
    () => props.technicalOpinionAi?.content,
    async (content) => {
        if (content && !props.technicalOpinionAiPending) {
            await applyAiContentToEditor(content);
        }
    },
);

onUnmounted(() => {
    if (technicalPollTimer) {
        clearInterval(technicalPollTimer);
    }
    revokePublishPreviewUrl();
});

const submit = () => {
    syncEditableToForm();
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

defineExpose({
    openTechnicalReportModal,
    openPgrSubsidyModal,
    openExportPdfModal,
});
</script>

<template>
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

    <section
        v-if="publication.uses_review_flow"
        class="mb-6 rounded-xl border border-talents-200 bg-talents-50/50 p-5 text-sm text-talents-950"
    >
        <p class="font-semibold">Documentos desta campanha</p>
        <p class="mt-1">
            Os dois documentos finais (relatório executivo e relatório técnico + plano de ação) seguem o fluxo de revisão:
            Em edição, Em revisão PASSEG, Alteração solicitada ou Aprovado pela PASSEG, e Publicado.
        </p>
        <Link :href="publication.documents_url" class="mt-3 inline-block rounded-md bg-talents-700 px-4 py-2 font-semibold text-white">
            Abrir a Central de documentos
        </Link>
    </section>

    <section v-if="!shellMode" class="mb-6 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm">
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
                <dd class="mt-1 text-slate-800">
                    {{ plan?.admin_published_at ? formatDateTime(plan.admin_published_at) : 'Ainda não publicada' }}
                </dd>
            </div>
        </dl>
    </section>

    <nav v-if="visibleSteps.length > 1" class="mb-8" aria-label="Etapas do parecer">
        <ol class="grid gap-2 sm:grid-cols-3">
            <li v-for="step in visibleSteps" :key="step.id">
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
                        :class="activeStep === step.id ? 'bg-talents-700 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ step.number }}
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">{{ step.label }}</span>
                        <span class="block text-xs text-slate-500">
                            <template v-if="step.id === 'results'">Indicadores e detalhe</template>
                            <template v-else>Texto e publicação</template>
                        </span>
                    </span>
                </button>
            </li>
        </ol>
    </nav>

    <div v-if="!shellMode" v-show="activeStep === 'results'" class="pb-8">
        <div class="mb-4">
            <h3 class="text-lg font-semibold text-talents-900">Diagnóstico dos resultados</h3>
            <p class="mt-1 text-sm text-gray-600">
                Indicadores e gráficos da pesquisa — os mesmos dados agregados que a empresa vê em Resultados.
            </p>
        </div>

        <Nr1SurveyResultsPanel
            :survey="survey"
            :band-labels="bandLabels"
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
            Ainda não há resultados agregados para esta pesquisa. Verifique se há respostas concluídas e se o recálculo foi
            feito no portal da empresa.
        </div>

        <div class="mt-8 flex justify-end">
            <PrimaryButton type="button" @click="selectStep('opinion')">Continuar para o parecer</PrimaryButton>
        </div>
    </div>

    <div class="space-y-8">
        <div v-show="activeStep === 'opinion'" class="space-y-8 pb-8">
            <div>
                <h3 class="text-lg font-semibold text-talents-900">Parecer técnico</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Abra cada seção. O parecer começa aberto; o plano de ação e os anexos ficam recolhidos até você expandir.
                </p>
            </div>

            <div
                v-if="overall && riskScenarioLabel"
                class="rounded-lg border border-violet-200 bg-violet-50/80 p-4 text-sm text-violet-950"
            >
                <strong>{{ riskScenarioLabel }}</strong>
                <span class="mt-1 block text-violet-900/90">
                    Os relatórios NR-1 (executivo e encaminhamento técnico) são gerados automaticamente conforme este cenário.
                    Você pode substituí-los por arquivos personalizados abaixo.
                </span>
            </div>

            <div class="flex flex-wrap gap-2">
                <SecondaryButton type="button" @click="openTechnicalReportModal">
                    <svg
                        v-if="technicalReport?.ready"
                        class="mr-2 h-4 w-4 text-emerald-600"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.3a1 1 0 0 1-1.42.002l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.54 2.54 6.542-6.586a1 1 0 0 1 1.418-.006Z" clip-rule="evenodd" />
                    </svg>
                    <span class="sr-only" v-if="technicalReport?.ready">Publicado no painel NR-1 da empresa. </span>
                    Relatório técnico + plano de ação
                </SecondaryButton>
                <SecondaryButton type="button" @click="openPgrSubsidyModal">
                    <svg
                        v-if="pgrSubsidy?.ready"
                        class="mr-2 h-4 w-4 text-emerald-600"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.3a1 1 0 0 1-1.42.002l-3.25-3.25a1 1 0 1 1 1.414-1.414l2.54 2.54 6.542-6.586a1 1 0 0 1 1.418-.006Z" clip-rule="evenodd" />
                    </svg>
                    <span class="sr-only" v-if="pgrSubsidy?.ready">Publicado no painel NR-1 da empresa. </span>
                    Subsídio AEP/PGR
                </SecondaryButton>
                <SecondaryButton type="button" disabled title="Inativo por enquanto">
                    Plano com resultados + parecer
                </SecondaryButton>
            </div>

            <div class="space-y-4">
                <CompanyShowAccordion
                    ref="opinionSection"
                    title="Parecer técnico"
                    description="Edite o texto. A Mia preenche esta mesma área."
                    default-open
                >
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <InfoTooltip
                        text="A Mia só preenche este texto. Os PDFs já publicados no painel da empresa não mudam até você atualizar os arquivos."
                    />
                    <SecondaryButton
                        v-if="publishedFiles"
                        type="button"
                        :disabled="updatingFiles || technicalOpinionAiPending"
                        @click="updatePublishedFiles"
                    >
                        {{ updatingFiles ? 'Atualizando…' : 'Atualizar arquivos publicados' }}
                    </SecondaryButton>
                    <PrimaryButton
                        type="button"
                        :disabled="!aiEnabled || technicalOpinionAiPending"
                        @click="requestTechnicalOpinionAi"
                    >
                        {{ technicalOpinionAiPending ? 'Gerando…' : 'Gerar parecer com IA' }}
                    </PrimaryButton>
                </div>

                <p v-if="!aiEnabled" class="text-sm text-amber-900">
                    A Mia não está disponível (API desativada ou sem chave). Configure em
                    <Link :href="route('admin.settings.edit')" class="font-medium text-talents-800 underline">Configurações</Link>.
                </p>
                <p v-else-if="technicalOpinionAiPending" class="text-sm text-sky-800">
                    Geração em andamento — o texto entra nesta área. Os PDFs publicados só mudam se você atualizar os arquivos.
                </p>

                <div
                    ref="opinionEditable"
                    class="parecer-editor min-h-[280px] rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-talents-500/30"
                    contenteditable="true"
                    role="textbox"
                    aria-label="Parecer técnico"
                    @input="syncEditableToForm"
                    @blur="syncEditableToForm"
                />

                <div v-if="form.errors.technical_opinion" class="text-sm text-red-600">
                    {{ form.errors.technical_opinion }}
                </div>
                </CompanyShowAccordion>

                <CompanyShowAccordion
                    title="Plano de ação"
                    :description="measureSectionDescription"
                >
                <ParecerMeasures
                    :company-id="company.id"
                    :survey-id="survey.id"
                    :measures="actionMeasures.measures"
                    :statuses="actionMeasures.statuses"
                    :can-edit-suggestion="actionMeasures.canEditSuggestion"
                    :due-origins="actionMeasures.dueOrigins"
                    :measures-ai="actionMeasures.measuresAi"
                />
                </CompanyShowAccordion>

                <CompanyShowAccordion
                    ref="annexSection"
                    title="Anexos"
                    description="Opcional. PDF, DOC ou DOCX, até 20 MB cada."
                >
                <div class="space-y-4">

                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <h5 class="text-sm font-medium text-gray-900">Arquivo do parecer</h5>
                        <div
                            v-if="existingOpinionFileName"
                            class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
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
                            <p v-if="form.errors.technical_opinion_file" class="mt-2 text-sm text-red-600">
                                {{ form.errors.technical_opinion_file }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <h5 class="text-sm font-medium text-gray-900">Relatório executivo</h5>
                        <p class="mt-1 text-xs text-gray-600">Substitua a versão gerada automaticamente, se precisar.</p>
                        <div
                            v-if="existingExecutiveFileName"
                            class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
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
                                class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-talents-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white"
                                @change="onExecutiveFileChange"
                            />
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <h5 class="text-sm font-medium text-gray-900">Encaminhamento técnico</h5>
                        <p class="mt-1 text-xs text-gray-600">Substitua a versão gerada automaticamente, se precisar.</p>
                        <div
                            v-if="existingReferralFileName"
                            class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
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
                                class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-talents-700 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white"
                                @change="onReferralFileChange"
                            />
                        </div>
                    </div>

                </div>
                </CompanyShowAccordion>
            </div>
        </div>
    </div>

    <section v-show="activeStep === 'opinion'" class="mt-8 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="publish-title">
        <h3 id="publish-title" class="text-lg font-semibold text-talents-900">Publicação</h3>
        <p class="mt-1 text-sm text-gray-600">
            {{ publicationStatusLabel }}
            <span v-if="plan?.admin_published_at"> · última em {{ formatDateTime(plan.admin_published_at) }}</span>
        </p>
        <p class="mt-3 text-sm text-slate-700">
            Relatório técnico + plano de ação:
            <strong>{{ technicalReportPublished ? 'publicado no painel da empresa' : 'ainda não publicado' }}</strong>.
            <button type="button" class="font-medium text-talents-800 underline" @click="openTechnicalReportModal">
                Abrir relatório técnico
            </button>
        </p>
        <p
            v-if="!publication.uses_review_flow && !canPublishParecer"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
        >
            Publique o relatório técnico e plano de ação antes de publicar o parecer.
        </p>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <SecondaryButton v-if="!shellMode" type="button" @click="selectStep('results')">
                Voltar
            </SecondaryButton>
            <SecondaryButton
                v-if="publication.requires_publication && !publication.uses_review_flow && !publication.published_at"
                type="button"
                @click="router.post(route('admin.companies.surveys.publication.store', [company.id, survey.id]), {}, { preserveScroll: true })"
            >
                Publicar resultados para a empresa
            </SecondaryButton>
            <PrimaryButton
                type="button"
                :disabled="form.processing || previewingPlanPdf || !canPublishParecer"
                @click="openPublishConfirmModal"
            >
                Salvar e publicar para a empresa
            </PrimaryButton>
        </div>
    </section>

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
                Excluir a pesquisa e todos os resultados. A empresa deixará de ver esta pesquisa; os dados serão arquivados
                internamente.
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
            </div>

            <div class="sticky bottom-0 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-white px-6 py-4">
                <SecondaryButton type="button" :disabled="form.processing" @click="closePublishConfirmModal">
                    Cancelar
                </SecondaryButton>
                <PrimaryButton
                    type="button"
                    :disabled="form.processing || previewingPlanPdf || !canPublishParecer"
                    @click="confirmPublish"
                >
                    {{ form.processing ? 'Publicando…' : 'Confirmar e publicar' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>

    <Nr1DocumentPreviewModal
        :show="showTechnicalReportModal"
        title="Relatório técnico + plano de ação"
        description="O PDF abre na hora. Publicar envia esta versão ao painel NR-1 da empresa. Depois você pode editar e substituir."
        :document="technicalReport"
        :download-url="technicalReportDownloadUrl"
        :preview-url="technicalReportPreviewUrl"
        :release-url="technicalReportReleaseUrl"
        :registration-url="registrationUrl"
        persist-key="technical_report"
        publish-mode
        @close="showTechnicalReportModal = false"
    />

    <Nr1DocumentPreviewModal
        :show="showPgrSubsidyModal"
        title="Subsídio AEP/PGR"
        description="O PDF abre na hora. Complete o cadastro técnico para preencher Unidade, AEP e responsável. Publicar envia esta versão ao painel NR-1 da empresa. Depois você pode editar e substituir."
        :document="pgrSubsidy"
        :download-url="pgrSubsidyDownloadUrl"
        :preview-url="pgrSubsidyPreviewUrl"
        :release-url="pgrSubsidyReleaseUrl"
        :registration-url="registrationUrl"
        persist-key="pgr_subsidy"
        publish-mode
        @close="showPgrSubsidyModal = false"
    />

    <Modal :show="showDeleteSurveyModal" @close="showDeleteSurveyModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Confirmar exclusão</h2>
            <p class="mt-2 text-sm text-gray-600">
                Excluir a pesquisa <strong>{{ survey.title }}</strong> e todos os resultados?
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <SecondaryButton type="button" @click="showDeleteSurveyModal = false">Cancelar</SecondaryButton>
                <DangerButton type="button" @click="deleteSurvey">Sim, excluir pesquisa</DangerButton>
            </div>
        </div>
    </Modal>

    <Modal :show="showExportPdfModal" max-width="md" @close="showExportPdfModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Plano com resultados + parecer</h2>
            <p class="mt-2 text-sm text-gray-600">
                Gera o PDF com indicadores, gráficos e o parecer técnico gravado nesta campanha.
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <SecondaryButton type="button" @click="showExportPdfModal = false">Cancelar</SecondaryButton>
                <PrimaryButton type="button" @click="confirmExportPdf">Abrir PDF</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.parecer-editor :deep(p) {
    margin: 0 0 1.25rem;
    line-height: 1.75;
}
.parecer-editor :deep(h2) {
    margin: 2rem 0 0.9rem;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
}
.parecer-editor :deep(h3) {
    margin: 1.6rem 0 0.75rem;
    font-weight: 700;
    line-height: 1.35;
}
.parecer-editor :deep(h2:first-child),
.parecer-editor :deep(h3:first-child) {
    margin-top: 0;
}
.parecer-editor :deep(ul),
.parecer-editor :deep(ol) {
    margin: 0 0 1.25rem;
    padding-left: 1.25rem;
}
.parecer-editor :deep(li + li) {
    margin-top: 0.45rem;
}
.parecer-editor :deep(br) {
    display: block;
    margin-bottom: 1rem;
    content: '';
}
</style>
