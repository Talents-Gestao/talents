<script setup>
import InterviewCandidatePicker from '@/Components/Hiring/InterviewCandidatePicker.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import { confirmationPayload, confirmationText } from '@/utils/interviewLink';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    interview: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const statusClass = (value) =>
    ({
        queued: 'bg-slate-100 text-slate-800',
        transcribing: 'bg-amber-100 text-amber-900',
        extracting: 'bg-blue-100 text-blue-900',
        completed: 'bg-emerald-100 text-emerald-900',
        failed: 'bg-red-100 text-red-900',
    })[value] ?? 'bg-slate-100 text-slate-800';

const editable = computed(
    () => props.can.edit && !props.interview.deleted && !props.interview.is_processing,
);

/* ── Rascunho das respostas ── */
const buildDraft = () => {
    const draft = {};
    for (const section of props.sections) {
        for (const q of section.questions) {
            draft[q.id] = {
                answer: q.answer ?? '',
                raw_quote: q.raw_quote ?? '',
                reviewer_note: q.reviewer_note ?? '',
            };
        }
    }
    return draft;
};
const draft = ref(buildDraft());
watch(() => props.sections, () => (draft.value = buildDraft()), { deep: true });

const dirty = computed(() =>
    props.sections.some((s) =>
        s.questions.some((q) => {
            const d = draft.value[q.id];
            return (
                d &&
                ((d.answer ?? '') !== (q.answer ?? '') ||
                    (d.raw_quote ?? '') !== (q.raw_quote ?? '') ||
                    (d.reviewer_note ?? '') !== (q.reviewer_note ?? ''))
            );
        }),
    ),
);

const answersForm = useForm({ answers: [] });
const saveDraft = (onDone = null) => {
    answersForm.answers = Object.entries(draft.value).map(([questionId, d]) => ({
        question_id: Number(questionId),
        answer: d.answer || null,
        raw_quote: d.raw_quote || null,
        reviewer_note: d.reviewer_note || null,
    }));
    answersForm.patch(route('admin.entrevistas.answers.update', props.interview.id), {
        preserveScroll: true,
        onSuccess: () => onDone?.(),
    });
};

const concludeReview = async () => {
    const ok = await confirmDialog({
        title: 'Concluir a revisão?',
        message: 'Você confirma que conferiu as respostas, as evidências e as observações. Seu nome e a data ficam registrados no relatório.',
        confirmLabel: 'Concluir revisão',
        variant: 'primary',
    });
    if (!ok) {
        return;
    }
    const conclude = () =>
        router.post(route('admin.entrevistas.review.conclude', props.interview.id), {}, { preserveScroll: true });
    if (dirty.value) {
        saveDraft(conclude);
    } else {
        conclude();
    }
};

/* ── Ações ── */
const reprocess = async () => {
    if (!(await confirmDialog('Reprocessar esta entrevista? As respostas atuais e a revisão serão substituídas.'))) {
        return;
    }
    router.post(route('admin.entrevistas.reprocess', props.interview.id), {}, { preserveScroll: true });
};

const destroyInterview = async () => {
    const ok = await confirmDialog({
        title: 'Excluir esta entrevista?',
        message: 'Ela vai para o filtro «Excluídas» e pode ser restaurada. O candidato não é excluído.',
        confirmLabel: 'Excluir',
    });
    if (ok) {
        router.delete(route('admin.entrevistas.destroy', props.interview.id));
    }
};

const restore = () => router.post(route('admin.entrevistas.restore', props.interview.id));

/* ── Vincular a candidato ── */
const showLink = ref(false);
const linkCandidate = ref(null);
const linkApplicationId = ref('');
const linkConfirmed = ref(false);
const linkForm = useForm({});
watch([linkCandidate, linkApplicationId], () => (linkConfirmed.value = false));

const openLink = () => {
    linkCandidate.value = null;
    linkApplicationId.value = '';
    linkConfirmed.value = false;
    linkForm.clearErrors();
    showLink.value = true;
};

const submitLink = () => {
    if (!linkCandidate.value || !linkConfirmed.value) {
        return;
    }
    linkForm
        .transform(() => ({
            talent_candidate_id: linkCandidate.value.id,
            hiring_application_id: linkApplicationId.value || null,
            ...confirmationPayload(linkCandidate.value, linkApplicationId.value),
        }))
        .post(route('admin.entrevistas.link', props.interview.id), {
            preserveScroll: true,
            onSuccess: () => (showLink.value = false),
        });
};

/* ── Atualização automática durante o processamento ── */
let pollTimer = null;
onMounted(() => {
    if (!props.interview.is_processing) {
        return;
    }
    pollTimer = setInterval(() => {
        router.reload({ only: ['interview', 'sections'], preserveScroll: true });
    }, 5000);
});
onUnmounted(() => pollTimer && clearInterval(pollTimer));

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
</script>

<template>
    <Head :title="`Entrevista — ${interview.candidate_name}`" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.entrevistas.index')"
                back-label="Entrevistas"
                :title="interview.candidate_name"
                :subtitle="interview.position_title ? `Vaga: ${interview.position_title}` : null"
                :prefer-history="true"
            >
                <template #trailing>
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="interview.status === 'completed' && !interview.deleted">
                            <a
                                :href="route('admin.entrevistas.report.pdf', interview.id)"
                                class="inline-flex items-center rounded-md bg-talents-600 px-3 py-2 text-sm font-medium text-white hover:bg-talents-700"
                            >
                                PDF
                            </a>
                            <a
                                :href="route('admin.entrevistas.report.docx', interview.id)"
                                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-talents-800 ring-1 ring-talents-200 hover:bg-talents-50"
                            >
                                DOCX
                            </a>
                        </template>
                        <SecondaryButton
                            v-if="can.edit && !interview.deleted && interview.status === 'failed'"
                            type="button"
                            @click="reprocess"
                        >
                            Reprocessar
                        </SecondaryButton>
                        <SecondaryButton v-if="editable" type="button" @click="openLink">
                            {{ interview.candidate ? 'Trocar vínculo' : 'Vincular a candidato' }}
                        </SecondaryButton>
                        <PrimaryButton v-if="can.delete && interview.deleted" type="button" @click="restore">Restaurar</PrimaryButton>
                        <SecondaryButton
                            v-if="can.delete && !interview.deleted && !interview.is_processing"
                            type="button"
                            class="!text-red-700 ring-red-200"
                            @click="destroyInterview"
                        >
                            Excluir
                        </SecondaryButton>
                    </div>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-for="key in ['interview', 'review', 'reprocess', 'answers']"
            v-show="$page.props.errors?.[key]"
            :key="key"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.errors?.[key] }}
        </div>
        <div v-if="interview.deleted" class="mb-4 rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-800">
            Entrevista excluída em {{ formatDateTime(interview.deleted_at) }}. Restaure-a para editar ou gerar relatórios.
        </div>

        <div class="mb-6 surface-card space-y-3 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(interview.status)">
                    {{ interview.status_label }}
                </span>
                <span class="text-sm text-slate-600">Roteiro: {{ interview.questionnaire?.name }} · v{{ interview.questionnaire?.version }}</span>
                <span class="text-sm text-slate-600">Fonte: {{ interview.transcript_source_label }}</span>
                <span v-if="interview.company" class="text-sm text-slate-600">Empresa: {{ interview.company.name }}</span>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <p>
                    <span class="text-slate-500">Candidato:</span>
                    <Link v-if="interview.candidate?.url" :href="interview.candidate.url" class="font-semibold text-talents-800 hover:underline">
                        {{ interview.candidate.name }}
                    </Link>
                    <span v-else-if="interview.candidate" class="font-semibold text-slate-800">{{ interview.candidate.name }}</span>
                    <span v-else class="font-semibold text-amber-800">sem vínculo (entrevista antiga)</span>
                </p>
                <p v-if="interview.application">
                    <span class="text-slate-500">Vaga:</span>
                    <Link v-if="interview.application.url" :href="interview.application.url" class="font-semibold text-talents-800 hover:underline">
                        {{ interview.application.vacancy_title }}
                    </Link>
                    <span v-else class="font-semibold text-slate-800">{{ interview.application.vacancy_title }}</span>
                    <span v-if="interview.application.company_name" class="text-slate-500"> · {{ interview.application.company_name }}</span>
                </p>
                <p>
                    <span class="text-slate-500">Revisão humana:</span>
                    <span v-if="interview.reviewed_at" class="font-semibold text-emerald-700">
                        concluída em {{ formatDateTime(interview.reviewed_at) }}<template v-if="interview.reviewed_by"> por {{ interview.reviewed_by }}</template>
                    </span>
                    <span v-else class="font-semibold text-amber-800">pendente</span>
                </p>
            </div>
            <p v-if="interview.is_processing" class="text-sm text-amber-800">
                Processamento em andamento… esta página atualiza automaticamente a cada 5 segundos. Enquanto isso, a entrevista não pode
                ser excluída nem revinculada.
            </p>
            <p v-if="interview.failure_reason" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                {{ interview.failure_reason }}
            </p>
            <p v-if="interview.has_unreviewed_ai" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Há respostas marcadas como «Sugestão da IA não revisada». Confira cada uma com a evidência antes de concluir a revisão.
            </p>
        </div>

        <div v-if="sections.length" class="space-y-6">
            <section v-for="section in sections" :key="section.id" class="surface-card p-5">
                <h3 class="text-base font-semibold text-slate-900">{{ section.title }}</h3>
                <div class="mt-4 space-y-5">
                    <article v-for="question in section.questions" :key="question.id" class="border-b border-slate-100 pb-5 last:border-0">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-sm font-medium text-slate-800">{{ question.text }}</p>
                            <span
                                v-if="question.origin === 'ai_suggestion' && !interview.reviewed_at"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900"
                            >
                                Sugestão da IA não revisada
                            </span>
                            <span v-else-if="question.origin === 'human'" class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                Revisão humana
                            </span>
                        </div>
                        <p v-if="question.evidence_missing && !draft[question.id]?.answer" class="mt-1 text-xs font-medium text-slate-500">
                            Sem evidência na transcrição: a IA não preencheu esta resposta.
                        </p>

                        <template v-if="editable && draft[question.id]">
                            <label :for="`answer-${question.id}`" class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500">Resposta</label>
                            <textarea :id="`answer-${question.id}`" v-model="draft[question.id].answer" rows="3" :class="fieldClass" />
                            <label :for="`quote-${question.id}`" class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Evidência (trecho da transcrição)
                            </label>
                            <textarea :id="`quote-${question.id}`" v-model="draft[question.id].raw_quote" rows="2" :class="fieldClass" />
                            <label :for="`note-${question.id}`" class="mt-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Observação
                            </label>
                            <textarea :id="`note-${question.id}`" v-model="draft[question.id].reviewer_note" rows="2" :class="fieldClass" />
                        </template>
                        <template v-else>
                            <p v-if="interview.is_processing" class="mt-2 text-sm italic text-slate-500">Aguardando processamento…</p>
                            <p v-else class="mt-2 whitespace-pre-wrap text-sm text-slate-700">{{ question.answer || '—' }}</p>
                            <p v-if="question.raw_quote" class="mt-2 text-xs italic text-slate-500">“{{ question.raw_quote }}”</p>
                            <p v-if="question.reviewer_note" class="mt-2 text-xs text-slate-600">Observação: {{ question.reviewer_note }}</p>
                        </template>
                    </article>
                </div>
            </section>

            <div v-if="editable" class="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg">
                <span v-if="dirty" class="text-sm text-amber-800">Há alterações não salvas.</span>
                <InputError :message="answersForm.errors.answers" />
                <SecondaryButton type="button" :disabled="answersForm.processing || !dirty" @click="saveDraft()">Salvar rascunho</SecondaryButton>
                <PrimaryButton
                    v-if="interview.status === 'completed'"
                    type="button"
                    :disabled="answersForm.processing"
                    @click="concludeReview"
                >
                    {{ interview.reviewed_at ? 'Concluir revisão novamente' : 'Concluir revisão' }}
                </PrimaryButton>
            </div>
        </div>

        <details v-if="interview.transcript_text" class="mt-6 surface-card p-5">
            <summary class="cursor-pointer text-sm font-semibold text-talents-800">Transcrição completa</summary>
            <p class="mt-3 whitespace-pre-wrap text-sm text-slate-700">{{ interview.transcript_text }}</p>
        </details>

        <Modal :show="showLink" max-width="xl" @close="showLink = false">
            <form class="space-y-4 p-6" @submit.prevent="submitLink">
                <h3 class="text-lg font-semibold text-talents-900">Vincular a candidato</h3>
                <p class="text-sm text-slate-600">
                    Escolha o cadastro do candidato e, se for o caso, a vaga. Nada é vinculado pelo nome: confirme a pessoa e a vaga.
                </p>
                <InterviewCandidatePicker
                    v-model:candidate="linkCandidate"
                    v-model:application-id="linkApplicationId"
                    id-prefix="link"
                    :error="linkForm.errors.talent_candidate_id"
                    :application-error="linkForm.errors.hiring_application_id"
                />
                <div v-if="linkCandidate" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <label class="flex items-start gap-2 text-sm text-amber-900">
                        <input
                            v-model="linkConfirmed"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-amber-300 text-talents-600 focus:ring-talents-500"
                        />
                        {{ confirmationText(linkCandidate, linkApplicationId) }}
                    </label>
                    <InputError class="mt-1" :message="linkForm.errors.confirm || linkForm.errors.interview" />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showLink = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="linkForm.processing || !linkCandidate || !linkConfirmed">Vincular</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
