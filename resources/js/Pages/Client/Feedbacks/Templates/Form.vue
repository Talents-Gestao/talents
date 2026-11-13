<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    /** null ao criar. */
    template: { type: Object, default: null },
    /** Modelo já usado em feedbacks: seções e perguntas não mudam. */
    structureLocked: { type: Boolean, default: false },
    questionTypes: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.template !== null);
const pageTitle = computed(() => (isEdit.value ? 'Editar modelo de feedback' : 'Novo modelo de feedback'));

const newQuestion = () => ({ body: '', question_type: 'textarea', is_required: false, options: [] });
const newSection = () => ({ title: '', description: '', questions: [newQuestion()] });

const form = useForm({
    title: props.template?.title ?? '',
    description: props.template?.description ?? '',
    is_active: props.template?.is_active ?? true,
    sections: props.template?.sections?.length
        ? props.template.sections.map((section) => ({
              title: section.title,
              description: section.description ?? '',
              questions: section.questions.map((question) => ({ ...question, options: [...(question.options || [])] })),
          }))
        : [newSection()],
});

function addSection() {
    form.sections.push(newSection());
}

function removeSection(index) {
    form.sections.splice(index, 1);
}

function addQuestion(section) {
    section.questions.push(newQuestion());
}

function removeQuestion(section, index) {
    section.questions.splice(index, 1);
}

function onTypeChange(question) {
    if (question.question_type === 'single_choice' && question.options.length < 2) {
        question.options = ['', ''];
    }
}

function submit() {
    const options = { preserveScroll: true };
    const payload = (data) => (props.structureLocked ? { title: data.title, description: data.description, is_active: data.is_active } : data);

    if (isEdit.value) {
        form.transform(payload).put(feedbackRoute('templates.update', props.template.id), options);
    } else {
        form.transform(payload).post(feedbackRoute('templates.store'), options);
    }
}

const fieldClass =
    'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500';
</script>

<template>
    <Head :title="pageTitle" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader :back-href="feedbackRoute('templates.index')" back-label="Modelos" :title="pageTitle" />
        </template>

        <form class="max-w-3xl space-y-5" @submit.prevent="submit">
            <div class="surface-card space-y-4 p-6">
                <div>
                    <InputLabel for="template_title" value="Nome do modelo" />
                    <TextInput id="template_title" v-model="form.title" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel for="template_description" value="Descrição (opcional)" />
                    <textarea id="template_description" v-model="form.description" rows="2" :class="fieldClass" />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-talents-700 focus:ring-talents-500" />
                    Ativo (aparece em «Novo feedback»)
                </label>
            </div>

            <p
                v-if="structureLocked"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                role="status"
            >
                Este modelo já foi usado em feedbacks: as seções e perguntas não podem mais ser alteradas. Para mudar as
                perguntas, crie um novo modelo.
            </p>

            <fieldset :disabled="structureLocked" class="space-y-4">
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
                            v-if="!structureLocked && form.sections.length > 1"
                            type="button"
                            class="text-xs font-medium text-rose-700 hover:underline"
                            @click="removeSection(sectionIndex)"
                        >
                            Remover seção
                        </button>
                    </div>
                    <div>
                        <InputLabel :for="`section_title_${sectionIndex}`" value="Título da seção" />
                        <TextInput :id="`section_title_${sectionIndex}`" v-model="section.title" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors[`sections.${sectionIndex}.title`]" />
                    </div>
                    <div>
                        <InputLabel :for="`section_description_${sectionIndex}`" value="Orientação (opcional)" />
                        <textarea :id="`section_description_${sectionIndex}`" v-model="section.description" rows="2" :class="fieldClass" />
                    </div>

                    <div
                        v-for="(question, questionIndex) in section.questions"
                        :key="questionIndex"
                        class="space-y-3 rounded-lg border border-slate-200 bg-slate-50/60 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pergunta {{ questionIndex + 1 }}</p>
                            <button
                                v-if="!structureLocked && section.questions.length > 1"
                                type="button"
                                class="text-xs font-medium text-rose-700 hover:underline"
                                @click="removeQuestion(section, questionIndex)"
                            >
                                Remover
                            </button>
                        </div>
                        <div>
                            <label :for="`question_body_${sectionIndex}_${questionIndex}`" class="sr-only">Texto da pergunta</label>
                            <textarea
                                :id="`question_body_${sectionIndex}_${questionIndex}`"
                                v-model="question.body"
                                rows="2"
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
                                    :class="[fieldClass, 'w-48']"
                                    @change="onTypeChange(question)"
                                >
                                    <option v-for="type in questionTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                            </div>
                            <label class="mt-5 flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    v-model="question.is_required"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-talents-700 focus:ring-talents-500"
                                />
                                Obrigatória
                            </label>
                        </div>
                        <div v-if="question.question_type === 'single_choice'" class="space-y-2">
                            <p class="text-xs font-medium text-slate-600">Opções</p>
                            <div v-for="(_option, optionIndex) in question.options" :key="optionIndex" class="flex items-center gap-2">
                                <TextInput
                                    v-model="question.options[optionIndex]"
                                    class="block w-full"
                                    :aria-label="`Opção ${optionIndex + 1}`"
                                    :placeholder="`Opção ${optionIndex + 1}`"
                                />
                                <button
                                    v-if="!structureLocked && question.options.length > 2"
                                    type="button"
                                    class="shrink-0 text-xs font-medium text-rose-700 hover:underline"
                                    @click="question.options.splice(optionIndex, 1)"
                                >
                                    Remover
                                </button>
                            </div>
                            <button
                                v-if="!structureLocked"
                                type="button"
                                class="text-xs font-medium text-talents-700 hover:underline"
                                @click="question.options.push('')"
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
                        v-if="!structureLocked"
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="addQuestion(section)"
                    >
                        Adicionar pergunta
                    </button>
                </div>

                <button
                    v-if="!structureLocked"
                    type="button"
                    class="rounded-md border border-talents-300 px-3 py-2 text-sm font-medium text-talents-800 hover:bg-talents-50"
                    @click="addSection"
                >
                    Adicionar seção
                </button>
            </fieldset>

            <PrimaryButton :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Salvar modelo' }}</PrimaryButton>
        </form>
    </FeedbacksLayout>
</template>
