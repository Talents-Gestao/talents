<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AutoGrowTextarea from '@/Components/AutoGrowTextarea.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import {
    ensureTwoOptions,
    normalizeTemplateOptions,
    templateFormPayload,
    templateLockMessage,
} from '@/utils/feedbackTemplateForm';
import { EyeIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** null ao criar. */
    template: { type: Object, default: null },
    /** Modelo já usado em feedbacks: seções e perguntas não mudam. */
    structureLocked: { type: Boolean, default: false },
    /** Quantos feedbacks usam o modelo (só na edição). */
    sessionsCount: { type: Number, default: 0 },
    /** Admin da Talents só com permissão de ver: tudo desabilitado e sem salvar. */
    readOnly: { type: Boolean, default: false },
    permissions: { type: Object, default: () => ({ create: true, edit: true, delete: true }) },
    questionTypes: { type: Array, default: () => [] },
    audiences: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.template !== null);
const pageTitle = computed(() => {
    if (!isEdit.value) return 'Novo modelo de feedback';
    return props.readOnly ? 'Modelo de feedback' : 'Editar modelo de feedback';
});
const lockMessage = computed(() => templateLockMessage(props.sessionsCount));
/** Seções e perguntas podem ser alteradas (adicionar, remover, digitar). */
const structureEditable = computed(() => !props.structureLocked && !props.readOnly);

const newQuestion = () => ({ key: '', body: '', question_type: 'textarea', is_required: false, options: [] });
const newSection = () => ({
    key: '',
    section_type: 'questions',
    audience: 'both',
    title: '',
    description: '',
    questions: [newQuestion()],
});

const form = useForm({
    title: props.template?.title ?? '',
    description: props.template?.description ?? '',
    is_active: props.template?.is_active ?? true,
    sections: props.template?.sections?.length
        ? props.template.sections.map((section) => ({
              key: section.key ?? '',
              section_type: section.section_type ?? 'questions',
              audience: section.audience ?? 'both',
              title: section.title,
              description: section.description ?? '',
              questions: (section.questions || []).map((question) => ({
                  ...question,
                  key: question.key ?? '',
                  options: normalizeTemplateOptions(question.options),
              })),
          }))
        : [newSection()],
});

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

function addSection() {
    form.sections.push(newSection());
}

function removeSection(index) {
    form.sections.splice(index, 1);
}

function onSectionTypeChange(section) {
    if (section.section_type === 'questions' && section.questions.length === 0) {
        section.questions.push(newQuestion());
    }
}

function addQuestion(section) {
    section.questions.push(newQuestion());
}

function removeQuestion(section, index) {
    section.questions.splice(index, 1);
}

function onTypeChange(question) {
    if (question.question_type === 'single_choice') {
        question.options = ensureTwoOptions(question.options);
    }
}

function submit() {
    if (props.readOnly) return;

    // Com erro, rola para o topo, onde fica o resumo; os campos com problema aparecem destacados abaixo.
    const options = { preserveScroll: (page) => Object.keys(page.props.errors || {}).length === 0 };
    const payload = (data) => templateFormPayload(data, props.structureLocked);

    if (isEdit.value) {
        form.transform(payload).put(feedbackRoute('templates.update', props.template.id), options);
    } else {
        form.transform(payload).post(feedbackRoute('templates.store'), options);
    }
}

const duplicating = ref(false);
function duplicateTemplate() {
    router.post(
        feedbackRoute('templates.duplicate', props.template.id),
        {},
        { onStart: () => (duplicating.value = true), onFinish: () => (duplicating.value = false) },
    );
}

// Estado desabilitado visível: dentro de <fieldset disabled> os campos não aceitam digitação.
const disabledClass = 'disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500';
const fieldClass = `mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500 ${disabledClass}`;
const checkboxClass = `rounded border-slate-300 text-talents-700 focus:ring-talents-500 ${disabledClass}`;
</script>

<template>
    <Head :title="pageTitle" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader :back-href="feedbackRoute('templates.index')" back-label="Modelos" :title="pageTitle" />
        </template>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <div
                v-if="$page.props.flash?.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900"
                role="status"
            >
                {{ $page.props.flash.success }}
            </div>

            <div
                v-if="hasErrors"
                class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
                role="alert"
                data-template-errors
            >
                <p>O modelo não foi salvo. Revise os campos destacados abaixo.</p>
                <p v-if="form.errors.sections" class="mt-1 font-medium">{{ form.errors.sections }}</p>
            </div>

            <div
                v-if="readOnly"
                class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
                role="status"
                data-template-readonly
            >
                <EyeIcon class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                <p>Somente leitura: seu acesso a Feedbacks não inclui editar modelos.</p>
            </div>

            <div
                v-else-if="structureLocked"
                class="flex flex-wrap items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                role="status"
                data-template-locked
            >
                <LockClosedIcon class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                <p class="min-w-0 flex-1">{{ lockMessage }}</p>
                <button
                    v-if="permissions.create"
                    type="button"
                    class="shrink-0 rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100 disabled:opacity-60"
                    :disabled="duplicating"
                    @click="duplicateTemplate"
                >
                    {{ duplicating ? 'Duplicando…' : 'Duplicar modelo' }}
                </button>
            </div>

            <fieldset :disabled="readOnly" class="space-y-5">
                <legend class="sr-only">Modelo de feedback</legend>

                <div class="surface-card space-y-4 p-6">
                    <div>
                        <InputLabel for="template_title" value="Nome do modelo" />
                        <TextInput id="template_title" v-model="form.title" :class="['mt-1 block w-full', disabledClass]" required />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>
                    <div>
                        <InputLabel for="template_description" value="Descrição (opcional)" />
                        <AutoGrowTextarea id="template_description" v-model="form.description" :min-rows="8" :class="fieldClass" />
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" :class="checkboxClass" />
                        Ativo (aparece em «Novo feedback»)
                    </label>
                </div>

                <fieldset :disabled="!structureEditable" class="space-y-4" :class="{ 'opacity-90': !structureEditable }">
                    <legend class="sr-only">Seções e perguntas</legend>

                    <div
                        v-for="(section, sectionIndex) in form.sections"
                        :key="sectionIndex"
                        class="surface-card space-y-4 p-6"
                        data-template-section
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900">Seção {{ sectionIndex + 1 }}</h3>
                            <button
                                v-if="structureEditable && form.sections.length > 1"
                                type="button"
                                class="text-xs font-medium text-rose-700 hover:underline"
                                @click="removeSection(sectionIndex)"
                            >
                                Remover seção
                            </button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label :for="`section_type_${sectionIndex}`" class="text-xs font-medium text-slate-600">Conteúdo</label>
                                <select
                                    :id="`section_type_${sectionIndex}`"
                                    v-model="section.section_type"
                                    :class="fieldClass"
                                    @change="onSectionTypeChange(section)"
                                >
                                    <option value="questions">Perguntas</option>
                                    <option value="intro">Só texto de orientação</option>
                                </select>
                            </div>
                            <div>
                                <label :for="`section_audience_${sectionIndex}`" class="text-xs font-medium text-slate-600">Quem responde</label>
                                <select :id="`section_audience_${sectionIndex}`" v-model="section.audience" :class="fieldClass">
                                    <option v-for="audience in audiences" :key="audience.value" :value="audience.value">{{ audience.label }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors[`sections.${sectionIndex}.audience`]" />
                            </div>
                        </div>
                        <div>
                            <InputLabel :for="`section_title_${sectionIndex}`" value="Título da seção" />
                            <TextInput :id="`section_title_${sectionIndex}`" v-model="section.title" :class="['mt-1 block w-full', disabledClass]" />
                            <InputError class="mt-2" :message="form.errors[`sections.${sectionIndex}.title`]" />
                        </div>
                        <div>
                            <InputLabel
                                :for="`section_description_${sectionIndex}`"
                                :value="section.section_type === 'intro' ? 'Texto de orientação' : 'Orientação (opcional)'"
                            />
                            <AutoGrowTextarea
                                :id="`section_description_${sectionIndex}`"
                                v-model="section.description"
                                :min-rows="section.section_type === 'intro' ? 8 : 4"
                                :class="fieldClass"
                            />
                            <InputError class="mt-2" :message="form.errors[`sections.${sectionIndex}.description`]" />
                        </div>

                        <template v-if="section.section_type !== 'intro'">
                            <div
                                v-for="(question, questionIndex) in section.questions"
                                :key="questionIndex"
                                class="space-y-3 rounded-lg border border-slate-200 bg-slate-50/60 p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pergunta {{ questionIndex + 1 }}</p>
                                    <button
                                        v-if="structureEditable && section.questions.length > 1"
                                        type="button"
                                        class="text-xs font-medium text-rose-700 hover:underline"
                                        @click="removeQuestion(section, questionIndex)"
                                    >
                                        Remover
                                    </button>
                                </div>
                                <div>
                                    <label :for="`question_body_${sectionIndex}_${questionIndex}`" class="sr-only">Texto da pergunta</label>
                                    <AutoGrowTextarea
                                        :id="`question_body_${sectionIndex}_${questionIndex}`"
                                        v-model="question.body"
                                        :min-rows="3"
                                        :class="fieldClass"
                                        placeholder="Texto da pergunta"
                                    />
                                    <InputError
                                        class="mt-2"
                                        :message="form.errors[`sections.${sectionIndex}.questions.${questionIndex}.body`]"
                                    />
                                </div>
                                <div class="flex flex-wrap items-center gap-4">
                                    <div>
                                        <label :for="`question_type_${sectionIndex}_${questionIndex}`" class="text-xs font-medium text-slate-600">
                                            Tipo de resposta
                                        </label>
                                        <select
                                            :id="`question_type_${sectionIndex}_${questionIndex}`"
                                            v-model="question.question_type"
                                            :class="[fieldClass, 'w-72']"
                                            @change="onTypeChange(question)"
                                        >
                                            <option v-for="type in questionTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                        </select>
                                        <InputError
                                            class="mt-2"
                                            :message="form.errors[`sections.${sectionIndex}.questions.${questionIndex}.question_type`]"
                                        />
                                    </div>
                                    <label class="mt-5 flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="question.is_required" type="checkbox" :class="checkboxClass" />
                                        Obrigatória
                                    </label>
                                </div>
                                <div v-if="question.question_type === 'single_choice'" class="space-y-2">
                                    <p class="text-xs font-medium text-slate-600">Opções</p>
                                    <div v-for="(option, optionIndex) in question.options" :key="optionIndex">
                                        <div class="flex items-center gap-2">
                                            <TextInput
                                                v-model="option.label"
                                                :class="['block w-full', disabledClass]"
                                                :aria-label="`Opção ${optionIndex + 1}`"
                                                :placeholder="`Opção ${optionIndex + 1}`"
                                            />
                                            <button
                                                v-if="structureEditable && question.options.length > 2"
                                                type="button"
                                                class="shrink-0 text-xs font-medium text-rose-700 hover:underline"
                                                @click="question.options.splice(optionIndex, 1)"
                                            >
                                                Remover
                                            </button>
                                        </div>
                                        <InputError
                                            class="mt-1"
                                            :message="form.errors[`sections.${sectionIndex}.questions.${questionIndex}.options.${optionIndex}.label`]"
                                        />
                                    </div>
                                    <button
                                        v-if="structureEditable"
                                        type="button"
                                        class="text-xs font-medium text-talents-700 hover:underline"
                                        @click="question.options.push({ value: '', label: '' })"
                                    >
                                        Adicionar opção
                                    </button>
                                    <InputError
                                        :message="form.errors[`sections.${sectionIndex}.questions.${questionIndex}.options`]"
                                    />
                                </div>
                            </div>
                            <InputError :message="form.errors[`sections.${sectionIndex}.questions`]" />

                            <button
                                v-if="structureEditable"
                                type="button"
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                @click="addQuestion(section)"
                            >
                                Adicionar pergunta
                            </button>
                        </template>
                    </div>

                    <button
                        v-if="structureEditable"
                        type="button"
                        class="rounded-md border border-talents-300 px-3 py-2 text-sm font-medium text-talents-800 hover:bg-talents-50"
                        @click="addSection"
                    >
                        Adicionar seção
                    </button>
                </fieldset>
            </fieldset>

            <PrimaryButton v-if="!readOnly" :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Salvar modelo' }}</PrimaryButton>
        </form>
    </FeedbacksLayout>
</template>
