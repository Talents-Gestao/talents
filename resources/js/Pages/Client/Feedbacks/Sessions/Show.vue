<script setup>
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import FeedbackSessionSignaturesPanel from '@/Components/Feedback/FeedbackSessionSignaturesPanel.vue';
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDaysIcon, UserIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    session: Object,
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const formatAnswer = (val) => {
    if (val == null || val === '') return '—';
    if (Array.isArray(val)) return val.filter(Boolean).join('\n• ');
    if (typeof val === 'object') return JSON.stringify(val, null, 2);
    return val;
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—');
const formatDateShort = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

const sectionMeta = (section) => {
    const total = section.questions?.length ?? 0;
    if (!total) return '';

    const answered = section.questions.filter((q) => {
        const val = props.session.answers?.[q.id];
        if (val == null || val === '') return false;
        if (Array.isArray(val)) return val.some(Boolean);
        if (typeof val === 'object') return Object.values(val).some((v) => v != null && v !== '');
        return true;
    }).length;

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
                        <a :href="feedbackRoute('sessions.pdf', session.id)">
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
                :description="section.description"
                :meta="sectionMeta(section)"
                :default-open="index === 0"
            >
                <div v-if="section.questions?.length" class="divide-y divide-slate-100">
                    <div v-for="q in section.questions" :key="q.id" class="px-5 py-4">
                        <p class="text-sm font-medium text-slate-800">{{ q.body }}</p>
                        <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
                            {{ formatAnswer(session.answers?.[q.id]) }}
                        </p>
                    </div>
                </div>
                <p v-else class="px-5 py-4 text-sm text-slate-500">Sem perguntas nesta secção.</p>
            </FeedbackSectionAccordion>
        </div>
    </FeedbacksLayout>
</template>
