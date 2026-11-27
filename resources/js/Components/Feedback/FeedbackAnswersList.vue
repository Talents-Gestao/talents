<script setup>
import FeedbackDisclosure from '@/Components/Feedback/FeedbackDisclosure.vue';
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { feedbackSectionIcon } from '@/utils/feedbackSectionIcons';
import { firstIncompleteSectionId, sectionProgress, splitSections } from '@/utils/feedbackSessionView';
import { ArchiveBoxIcon, InformationCircleIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

/**
 * Aba «Respostas» (padrão Task list): «Sobre este modelo» recolhido com o Objetivo; cada seção com ícone, título,
 * estado em texto e barra fina; a primeira seção incompleta em destaque e aberta; o Encerramento como nota de
 * rodapé. Perguntas e respostas com medida de leitura de ~70 caracteres. Só leitura (preencher é em «Editar»).
 * As seções «Reflexão do líder» já chegam filtradas do servidor para quem não pode vê-las.
 */
const props = defineProps({
    session: { type: Object, required: true },
    /** Mostrar «Preencher esta seção» (mesma regra do «Editar»). */
    canEdit: { type: Boolean, default: false },
});

const sections = computed(() => props.session.template?.sections ?? []);
const parts = computed(() => splitSections(sections.value));
const answers = computed(() => props.session.answers ?? {});
const firstIncomplete = computed(() => firstIncompleteSectionId(sections.value, answers.value));
const openId = computed(() => firstIncomplete.value ?? parts.value.body.find((s) => s.section_type !== 'intro')?.id ?? null);

const accordions = ref({});

/** «Continuar preenchimento»: abre a primeira seção incompleta, rola até ela e leva o foco ao título. */
async function focusFirstIncomplete() {
    const id = firstIncomplete.value;
    if (id == null) return;
    await nextTick();
    const el = accordions.value[id]?.open?.();
    el?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    el?.querySelector('summary')?.focus({ preventScroll: true });
}
defineExpose({ focusFirstIncomplete });

const ccmLabels = { start: 'Começar', continue: 'Continuar', improve: 'Melhorar', stop: 'Cessar' };
const isActionRow = (item) => item != null && typeof item === 'object' && !Array.isArray(item) && ('action' in item || 'responsible' in item || 'deadline' in item);

function formatAnswer(val, question = null) {
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
        const items = val.filter((item) => item != null && item !== '').map((item) => (typeof item === 'object' ? JSON.stringify(item) : String(item)));
        return items.length ? items.map((item) => `• ${item}`).join('\n') : '—';
    }
    if (typeof val === 'object') {
        const keys = Object.keys(ccmLabels);
        if (question?.question_type === 'ccm_block' || keys.some((key) => key in val)) {
            return keys.map((key) => `${ccmLabels[key]}: ${String(val[key] ?? '').trim() || '—'}`).join('\n');
        }
        return JSON.stringify(val, null, 2);
    }
    if (question?.question_type === 'single_choice' && Array.isArray(question.options)) {
        const option = question.options.find((opt) => opt.value === val);
        if (option?.label) return option.label;
    }
    return String(val);
}

const extraOf = (section) => props.session.section_extras?.[section.id];
const hasExtra = (section) => !!(extraOf(section)?.question || extraOf(section)?.answer);

// Respostas e perguntas extras de uma versão anterior do modelo (o feedback foi atualizado de versão).
const outOfVersion = computed(() => props.session.out_of_version_answers ?? []);
const outOfVersionExtras = computed(() => (props.session.out_of_version_extras ?? []).filter((e) => e.question || e.answer));
</script>

<template>
    <div class="space-y-4" data-feedback-answers>
        <!-- Objetivo e outras introduções do modelo: recolhido -->
        <FeedbackDisclosure v-if="parts.about.length" label="Sobre este modelo" data-feedback-answers-about>
            <template #icon><InformationCircleIcon class="h-5 w-5 shrink-0 text-talents-600" aria-hidden="true" /></template>
            <div v-for="intro in parts.about" :key="intro.id" class="max-w-[70ch] space-y-1">
                <p v-if="parts.about.length > 1" class="font-semibold text-slate-900">{{ intro.title }}</p>
                <p class="whitespace-pre-wrap leading-relaxed">{{ intro.description }}</p>
            </div>
        </FeedbackDisclosure>

        <p v-if="!parts.body.length" class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-700">
            Este modelo não tem perguntas.
        </p>

        <ol class="space-y-3">
            <li v-for="section in parts.body" :key="section.id">
                <!-- Introdução no meio do modelo: nota discreta, não um cartão -->
                <div v-if="section.section_type === 'intro'" class="max-w-[70ch] border-l-2 border-slate-200 pl-3 text-sm text-slate-700">
                    <p class="font-semibold text-slate-800">{{ section.title }}</p>
                    <p class="whitespace-pre-wrap leading-relaxed">{{ section.description }}</p>
                </div>
                <FeedbackSectionAccordion
                    v-else
                    :id="`feedback-section-${section.id}`"
                    :ref="(el) => (accordions[section.id] = el)"
                    :title="section.title"
                    :icon="feedbackSectionIcon(section.key)"
                    :description="section.description"
                    :status="sectionProgress(section, answers).total ? sectionProgress(section, answers).label : ''"
                    :progress="sectionProgress(section, answers)"
                    :highlight="section.id === firstIncomplete"
                    :default-open="section.id === openId"
                    :data-feedback-section-state="sectionProgress(section, answers).status"
                    :data-feedback-first-incomplete="section.id === firstIncomplete ? 'true' : undefined"
                >
                    <div v-if="section.questions?.length || hasExtra(section)" class="divide-y divide-slate-100">
                        <div v-for="q in section.questions" :key="q.id" class="px-4 py-4 sm:px-6">
                            <p class="max-w-[70ch] text-sm font-semibold text-slate-900">{{ q.body }}</p>
                            <p class="mt-2 max-w-[70ch] whitespace-pre-wrap border-l-2 border-talents-100 pl-3 text-sm leading-relaxed text-slate-800">{{ formatAnswer(answers[q.id], q) }}</p>
                        </div>
                        <div v-if="hasExtra(section)" class="px-4 py-4 sm:px-6">
                            <p class="max-w-[70ch] text-sm font-semibold text-slate-900">{{ extraOf(section).question || 'Pergunta extra' }}</p>
                            <p class="mt-2 max-w-[70ch] whitespace-pre-wrap border-l-2 border-talents-100 pl-3 text-sm leading-relaxed text-slate-800">{{ formatAnswer(extraOf(section).answer) }}</p>
                        </div>
                    </div>
                    <p v-else class="px-4 py-4 text-sm text-slate-700 sm:px-6">Sem perguntas nesta seção.</p>
                    <div v-if="canEdit && section.id === firstIncomplete" class="border-t border-slate-100 px-4 py-3 sm:px-6">
                        <Link
                            :href="feedbackRoute('sessions.edit', session.id)"
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-talents-200 bg-white px-4 text-sm font-semibold text-talents-800 hover:bg-talents-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                            data-feedback-fill-link
                        >
                            <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                            Preencher no formulário
                        </Link>
                    </div>
                </FeedbackSectionAccordion>
            </li>
        </ol>

        <section
            v-if="outOfVersion.length || outOfVersionExtras.length"
            class="rounded-2xl border border-slate-300 border-dashed bg-slate-50/60"
            aria-labelledby="feedback-out-of-version-title"
            data-feedback-out-of-version
        >
            <div class="flex items-start gap-2 px-4 py-3 sm:px-6">
                <ArchiveBoxIcon class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" aria-hidden="true" />
                <div>
                    <h3 id="feedback-out-of-version-title" class="text-sm font-semibold text-slate-900">Fora da versão atual</h3>
                    <p class="text-xs text-slate-600">
                        Respostas guardadas de uma versão anterior do modelo. Estas perguntas não estão na versão {{ session.template?.version_number }}.
                    </p>
                </div>
            </div>
            <div class="divide-y divide-slate-200 border-t border-slate-200">
                <div v-for="item in outOfVersion" :key="`ov-${item.question_id}`" class="px-4 py-4 sm:px-6">
                    <p class="max-w-[70ch] text-sm font-semibold text-slate-900">
                        {{ item.body }}<span v-if="item.section_title" class="font-normal text-slate-500"> · {{ item.section_title }}</span>
                    </p>
                    <p class="mt-2 max-w-[70ch] whitespace-pre-wrap border-l-2 border-slate-300 pl-3 text-sm leading-relaxed text-slate-800">{{ formatAnswer(item.value, item) }}</p>
                </div>
                <div v-for="extra in outOfVersionExtras" :key="`ox-${extra.section_id}`" class="px-4 py-4 sm:px-6">
                    <p class="max-w-[70ch] text-sm font-semibold text-slate-900">
                        {{ extra.question || 'Pergunta extra' }}<span v-if="extra.section_title" class="font-normal text-slate-500"> · {{ extra.section_title }}</span>
                    </p>
                    <p class="mt-2 max-w-[70ch] whitespace-pre-wrap border-l-2 border-slate-300 pl-3 text-sm leading-relaxed text-slate-800">{{ formatAnswer(extra.answer) }}</p>
                </div>
            </div>
        </section>

        <!-- Encerramento: nota de rodapé -->
        <footer v-for="note in parts.closing" :key="note.id" class="max-w-[70ch] border-t border-slate-200 pt-4 text-[13px] leading-relaxed text-slate-700" data-feedback-answers-closing>
            <p class="font-semibold text-slate-800">{{ note.title }}</p>
            <p class="whitespace-pre-wrap">{{ note.description }}</p>
        </footer>
    </div>
</template>
