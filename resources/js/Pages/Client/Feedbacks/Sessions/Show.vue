<script setup>
import FeedbackActionsMenu from '@/Components/Feedback/FeedbackActionsMenu.vue';
import FeedbackFlash from '@/Components/Feedback/FeedbackFlash.vue';
import FeedbackPanel from '@/Components/Feedback/FeedbackPanel.vue';
import FeedbackRecordingPanel from '@/Components/Feedback/FeedbackRecordingPanel.vue';
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import FeedbackSessionDeleteModal from '@/Components/Feedback/FeedbackSessionDeleteModal.vue';
import FeedbackSessionSignaturesPanel from '@/Components/Feedback/FeedbackSessionSignaturesPanel.vue';
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { feedbackSectionIcon } from '@/utils/feedbackSectionIcons';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    CalendarDaysIcon,
    PencilSquareIcon,
    TrashIcon,
    UserCircleIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    session: Object,
    canExportPdf: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    recording: { type: Object, default: null },
    recordingAccess: { type: Object, default: () => ({}) },
    recordingAi: { type: Object, default: null },
});

const deletingSession = ref(null);

// Todas as ações do cabeçalho num menu: Editar (mesma regra do antigo botão), Exportar PDF e,
// separado e por último, Excluir. Sem nenhum item, o botão «Ações» não aparece.
const canEdit = computed(() => props.session.status !== 'completed' && props.session.status !== 'cancelled');
const canRegenerateReport = computed(
    () =>
        !!props.recordingAi?.clean
        && props.recordingAi?.gate?.allowed
        && !props.recordingAi?.limit_reached
        && ['ready', 'failed'].includes(props.recordingAi?.status)
        && props.recordingAccess?.canProcess
        && !props.recordingAccess?.locked,
);
const headerMenuItems = computed(() => [
    ...(canEdit.value
        ? [{ key: 'edit', label: 'Editar', icon: PencilSquareIcon, href: feedbackRoute('sessions.edit', props.session.id) }]
        : []),
    ...(props.canExportPdf
        ? [{ key: 'pdf', label: 'Exportar PDF', icon: ArrowDownTrayIcon, href: feedbackRoute('sessions.pdf', props.session.id), external: true }]
        : []),
    // Novo relatório da gravação (a IA pode estar travada; o servidor confere de novo a trava e o limite).
    ...(canRegenerateReport.value
        ? [{ key: 'report', label: 'Gerar relatório novamente', icon: ArrowPathIcon, onSelect: () => router.post(feedbackRoute('sessions.recording.report', props.session.id), {}, { preserveScroll: true }) }]
        : []),
    ...(props.canDelete
        ? [{ key: 'delete', label: 'Excluir feedback', icon: TrashIcon, danger: true, onSelect: () => (deletingSession.value = props.session) }]
        : []),
]);

const ccmLabels = {
    start: 'Começar',
    continue: 'Continuar',
    improve: 'Melhorar',
    stop: 'Cessar',
};

const isActionRow = (item) =>
    item != null
    && typeof item === 'object'
    && !Array.isArray(item)
    && ('action' in item || 'responsible' in item || 'deadline' in item);

const formatAnswer = (val, question = null) => {
    if (val == null || val === '') return '—';

    if (Array.isArray(val)) {
        if (val.length === 0) return '—';

        if (question?.question_type === 'action_table' || val.some(isActionRow)) {
            const lines = val
                .filter((row) => row != null && typeof row === 'object')
                .map((row, index) => {
                    const action = String(row.action ?? '').trim();
                    const responsible = String(row.responsible ?? '').trim();
                    const deadline = String(row.deadline ?? '').trim();
                    if (!action && !responsible && !deadline) return null;

                    return `${index + 1}. ${action || '—'} · Responsável: ${responsible || '—'} · Prazo: ${deadline || '—'}`;
                })
                .filter(Boolean);

            return lines.length ? lines.join('\n') : '—';
        }

        const items = val
            .filter((item) => item != null && item !== '')
            .map((item) => (typeof item === 'object' ? JSON.stringify(item) : String(item)));

        if (!items.length) return '—';

        return items.map((item) => `• ${item}`).join('\n');
    }

    if (typeof val === 'object') {
        const keys = Object.keys(ccmLabels);
        if (question?.question_type === 'ccm_block' || keys.some((key) => key in val)) {
            return keys
                .map((key) => `${ccmLabels[key]}: ${String(val[key] ?? '').trim() || '—'}`)
                .join('\n');
        }

        return JSON.stringify(val, null, 2);
    }

    if (question?.question_type === 'single_choice' && Array.isArray(question.options)) {
        const option = question.options.find((opt) => opt.value === val);
        if (option?.label) return option.label;
    }

    return String(val);
};

const formatDate = (iso) => formatDateTime(iso);
const formatDateShort = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const sectionMeta = (section) => {
    if (section.section_type === 'intro') return '';

    const total = (section.questions?.length ?? 0) + 1;

    let answered = section.questions.filter((q) => {
        const val = props.session.answers?.[q.id];
        if (val == null || val === '') return false;
        if (Array.isArray(val)) return val.some(Boolean);
        if (typeof val === 'object') return Object.values(val).some((v) => v != null && v !== '');
        return true;
    }).length;

    const extra = props.session.section_extras?.[section.id];
    if (extra?.question?.trim() || extra?.answer?.trim()) {
        answered += 1;
    }

    return `${answered}/${total} respondidas`;
};
</script>

<template>
    <Head :title="session.title" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('index')"
                back-label="Feedbacks"
                :title="session.title"
                :subtitle="session.employee?.name"
            >
                <template v-if="headerMenuItems.length" #trailing>
                    <FeedbackActionsMenu :items="headerMenuItems" show-label label="Ações" variant="primary" />
                </template>
            </FormPageHeader>
        </template>

        <FeedbackFlash class="mb-6" />

        <FeedbackPanel title="Resumo" class="mb-6">
            <template #actions>
                <FeedbackStatusBadge :status="session.status" :label="session.status_label" />
            </template>
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="min-w-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Colaborador</dt>
                    <dd class="mt-1 flex items-center gap-2 font-medium text-talents-900">
                        <UserIcon class="h-4 w-4 shrink-0 text-talents-600" aria-hidden="true" />
                        <span class="truncate">{{ session.employee?.name ?? '—' }}</span>
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Líder</dt>
                    <dd class="mt-1 flex items-center gap-2 font-medium text-slate-800">
                        <UserCircleIcon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                        <span class="truncate">{{ session.leader?.name ?? '—' }}</span>
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alinhamento</dt>
                    <dd class="mt-1 flex items-center gap-2 text-sm text-slate-800">
                        <CalendarDaysIcon class="h-4 w-4 shrink-0 text-talents-600" aria-hidden="true" />
                        {{ formatDate(session.scheduled_at) }}
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Próximo alinhamento</dt>
                    <dd class="mt-1 flex items-center gap-2 text-sm text-slate-800">
                        <ArrowPathIcon class="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                        {{ formatDateShort(session.next_alignment_at) }}
                    </dd>
                </div>
            </dl>
        </FeedbackPanel>

        <FeedbackSessionSignaturesPanel class="mb-6" :session="session" />

        <div class="mb-8">
            <FeedbackRecordingPanel :session-id="session.id" :recording="recording" :ai="recordingAi" :access="recordingAccess" />
        </div>

        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Respostas por seção</h3>
        <div class="space-y-3">
            <FeedbackSectionAccordion
                v-for="(section, index) in session.template?.sections || []"
                :key="section.id"
                :title="section.title"
                :icon="feedbackSectionIcon(section.key)"
                :description="section.description"
                :meta="sectionMeta(section)"
                :collapsible="section.section_type !== 'intro'"
                :default-open="index === 0"
            >
                <template v-if="section.section_type !== 'intro'">
                    <div v-if="section.questions?.length" class="divide-y divide-slate-100">
                        <div v-for="q in section.questions" :key="q.id" class="px-5 py-4 sm:px-6">
                            <p class="max-w-prose text-sm font-semibold text-slate-800">{{ q.body }}</p>
                            <p
                                class="mt-2 max-w-prose whitespace-pre-wrap border-l-2 border-talents-100 pl-3 text-sm leading-relaxed text-slate-700"
                            >{{ formatAnswer(session.answers?.[q.id], q) }}</p>
                        </div>
                        <div
                            v-if="session.section_extras?.[section.id]?.question || session.section_extras?.[section.id]?.answer"
                            class="px-5 py-4 sm:px-6"
                        >
                            <p class="max-w-prose text-sm font-semibold text-slate-800">
                                {{ session.section_extras[section.id].question || 'Pergunta extra' }}
                            </p>
                            <p
                                class="mt-2 max-w-prose whitespace-pre-wrap border-l-2 border-talents-100 pl-3 text-sm leading-relaxed text-slate-700"
                            >{{ formatAnswer(session.section_extras[section.id].answer) }}</p>
                        </div>
                    </div>
                    <p v-else class="px-5 py-4 text-sm text-slate-500">Sem perguntas nesta seção.</p>
                </template>
            </FeedbackSectionAccordion>
        </div>

        <FeedbackSessionDeleteModal :session="deletingSession" @close="deletingSession = null" />
    </FeedbacksLayout>
</template>
