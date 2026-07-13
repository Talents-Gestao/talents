<script setup>
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import FeedbackSessionSignaturesPanel from '@/Components/Feedback/FeedbackSessionSignaturesPanel.vue';
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { feedbackSectionIcon } from '@/utils/feedbackSectionIcons';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDaysIcon, UserIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    session: Object,
    canExportPdf: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

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

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—');
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
                <template #trailing>
                    <div class="flex flex-wrap gap-2">
                        <a v-if="canExportPdf" :href="feedbackRoute('sessions.pdf', session.id)">
                            <SecondaryButton type="button">Exportar PDF</SecondaryButton>
                        </a>
                        <Link
                            v-if="session.status !== 'completed' && session.status !== 'cancelled'"
                            :href="feedbackRoute('sessions.edit', session.id)"
                        >
                            <PrimaryButton type="button">Editar</PrimaryButton>
                        </Link>
                    </div>
                </template>
            </FormPageHeader>
        </template>

        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Colaborador</p>
                <p class="mt-2 flex items-center gap-2 font-medium text-talents-900">
                    <UserIcon class="h-4 w-4 text-talents-600" />
                    {{ session.employee?.name ?? '—' }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Líder</p>
                <p class="mt-2 font-medium text-slate-800">{{ session.leader?.name ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alinhamento</p>
                <p class="mt-2 flex items-center gap-2 text-sm text-slate-800">
                    <CalendarDaysIcon class="h-4 w-4 text-talents-600" />
                    {{ formatDate(session.scheduled_at) }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                <div class="mt-2">
                    <FeedbackStatusBadge :status="session.status" :label="session.status_label" />
                </div>
                <p class="mt-2 text-xs text-slate-500">Próximo: {{ formatDateShort(session.next_alignment_at) }}</p>
            </div>
        </div>

        <div
            v-if="flashSuccess"
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-900"
        >
            {{ flashSuccess }}
        </div>

        <FeedbackSessionSignaturesPanel class="mb-8" :session="session" />

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
                        <div v-for="q in section.questions" :key="q.id" class="px-5 py-4">
                            <p class="text-sm font-medium text-slate-800">{{ q.body }}</p>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                                {{ formatAnswer(session.answers?.[q.id], q) }}
                            </p>
                        </div>
                        <div
                            v-if="session.section_extras?.[section.id]?.question || session.section_extras?.[section.id]?.answer"
                            class="px-5 py-4"
                        >
                            <p class="text-sm font-medium text-slate-800">
                                {{ session.section_extras[section.id].question || 'Pergunta extra' }}
                            </p>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                                {{ formatAnswer(session.section_extras[section.id].answer) }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="px-5 py-4 text-sm text-slate-500">Sem perguntas nesta seção.</p>
                </template>
            </FeedbackSectionAccordion>
        </div>
    </FeedbacksLayout>
</template>
