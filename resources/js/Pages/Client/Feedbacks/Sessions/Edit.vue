<script setup>
import FeedbackQuestionField from '@/Components/Feedback/FeedbackQuestionField.vue';
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import FeedbackSectionExtraField from '@/Components/Feedback/FeedbackSectionExtraField.vue';
import FeedbackStatusBadge from '@/Components/Feedback/FeedbackStatusBadge.vue';
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { feedbackFieldClass } from '@/utils/feedbackStatus';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { feedbackSectionIcon } from '@/utils/feedbackSectionIcons';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    session: Object,
});

const answers = reactive({ ...(props.session.answers ?? {}) });

const sectionExtras = reactive({ ...(props.session.section_extras ?? {}) });

const ensureSectionExtra = (sectionId) => {
    if (!sectionExtras[sectionId]) {
        sectionExtras[sectionId] = { question: '', answer: '' };
    }
    return sectionExtras[sectionId];
};

const form = useForm({
    scheduled_at: props.session.scheduled_at ? props.session.scheduled_at.slice(0, 16) : '',
    next_alignment_at: props.session.next_alignment_at ? props.session.next_alignment_at.slice(0, 10) : '',
    answers: {},
    section_extras: {},
    submit_for_signature: false,
});

const save = (forSignature = false) => {
    form.answers = { ...answers };
    form.section_extras = { ...sectionExtras };
    form.submit_for_signature = forSignature;
    form.patch(feedbackRoute('sessions.update', props.session.id));
};

const isExtraFilled = (sectionId) => {
    const extra = sectionExtras[sectionId];
    if (!extra) return false;
    return Boolean(extra.question?.trim() || extra.answer?.trim());
};

const sectionMeta = (section) => {
    if (section.section_type === 'intro') return '';

    const total = (section.questions?.length ?? 0) + 1;

    let answered = section.questions.filter((q) => {
        const val = answers[q.id];
        if (val == null || val === '') return false;
        if (Array.isArray(val)) return val.some(Boolean);
        if (typeof val === 'object') return Object.values(val).some((v) => v != null && v !== '');
        return true;
    }).length;

    if (isExtraFilled(section.id)) {
        answered += 1;
    }

    return `${answered}/${total} preenchidas`;
};
</script>

<template>
    <Head :title="'Preencher — ' + session.title" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('sessions.show', session.id)"
                back-label="Resumo"
                :title="session.title"
                :subtitle="session.employee?.name"
            >
                <template #trailing>
                    <FeedbackStatusBadge :status="session.status" :label="session.status_label" />
                </template>
            </FormPageHeader>
        </template>

        <div class="mb-8 grid gap-4 rounded-2xl border border-talents-100 bg-talents-50/40 p-5 sm:grid-cols-2">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data do alinhamento</label>
                <input v-model="form.scheduled_at" type="datetime-local" :class="feedbackFieldClass" />
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Próximo alinhamento</label>
                <input v-model="form.next_alignment_at" type="date" :class="feedbackFieldClass" />
            </div>
        </div>

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
                    <div class="space-y-4 p-5">
                        <FeedbackQuestionField
                            v-for="q in section.questions"
                            :key="q.id"
                            :question="q"
                            :model-value="answers[q.id]"
                            @update:model-value="answers[q.id] = $event"
                        />
                        <FeedbackSectionExtraField
                            :model-value="ensureSectionExtra(section.id)"
                            @update:model-value="sectionExtras[section.id] = $event"
                        />
                    </div>
                </template>
            </FeedbackSectionAccordion>
        </div>

        <div class="sticky bottom-4 z-10 mt-8 flex flex-wrap gap-3 rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur">
            <PrimaryButton type="button" :disabled="form.processing" @click="save(false)">Salvar rascunho</PrimaryButton>
            <PrimaryButton type="button" class="!bg-talents-800" :disabled="form.processing" @click="save(true)">
                Enviar para assinatura
            </PrimaryButton>
            <Link :href="feedbackRoute('index')">
                <SecondaryButton type="button">Cancelar</SecondaryButton>
            </Link>
        </div>
    </FeedbacksLayout>
</template>
