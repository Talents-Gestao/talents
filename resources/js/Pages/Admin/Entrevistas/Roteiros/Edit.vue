<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    questionnaire: { type: Object, default: null },
});

const emptySection = () => ({
    title: '',
    questions: [{ text: '', question_key: '' }],
});

const initialSections =
    props.questionnaire?.sections?.length > 0
        ? props.questionnaire.sections.map((s) => ({
              title: s.title,
              questions: s.questions.map((q) => ({
                  text: q.text,
                  question_key: q.question_key,
              })),
          }))
        : [emptySection()];

const form = useForm({
    name: props.questionnaire?.name ?? '',
    description: props.questionnaire?.description ?? '',
    is_default: props.questionnaire?.is_default ?? false,
    sections: initialSections,
});

const addSection = () => {
    form.sections.push(emptySection());
};

const removeSection = (index) => {
    if (form.sections.length <= 1) {
        return;
    }
    form.sections.splice(index, 1);
};

const addQuestion = (sectionIndex) => {
    form.sections[sectionIndex].questions.push({ text: '', question_key: '' });
};

const removeQuestion = (sectionIndex, questionIndex) => {
    if (form.sections[sectionIndex].questions.length <= 1) {
        return;
    }
    form.sections[sectionIndex].questions.splice(questionIndex, 1);
};

const submit = () => {
    if (props.questionnaire?.id) {
        form.put(route('admin.entrevistas.roteiros.update', props.questionnaire.id));
    } else {
        form.post(route('admin.entrevistas.roteiros.store'));
    }
};
</script>

<template>
    <Head :title="questionnaire ? 'Editar roteiro' : 'Novo roteiro'" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.entrevistas.roteiros.index')"
                :title="questionnaire ? 'Editar roteiro' : 'Novo roteiro'"
            />
        </template>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="surface-card max-w-3xl space-y-4 p-6">
                <div>
                    <InputLabel for="name" value="Nome do roteiro" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                </div>
                <div>
                    <InputLabel for="description" value="Descrição" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-talents-600" />
                    Definir como roteiro padrão
                </label>
            </div>

            <div v-for="(section, sIndex) in form.sections" :key="sIndex" class="surface-card p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-900">Seção {{ sIndex + 1 }}</h3>
                    <button
                        v-if="form.sections.length > 1"
                        type="button"
                        class="text-sm text-red-600 hover:underline"
                        @click="removeSection(sIndex)"
                    >
                        Remover seção
                    </button>
                </div>
                <div class="mb-4">
                    <InputLabel :for="`section-${sIndex}`" value="Título da seção" />
                    <TextInput :id="`section-${sIndex}`" v-model="section.title" class="mt-1 block w-full" required />
                </div>

                <div
                    v-for="(question, qIndex) in section.questions"
                    :key="qIndex"
                    class="mb-4 rounded-lg border border-slate-200 bg-slate-50/80 p-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Pergunta {{ qIndex + 1 }}</span>
                        <button
                            v-if="section.questions.length > 1"
                            type="button"
                            class="text-xs text-red-600 hover:underline"
                            @click="removeQuestion(sIndex, qIndex)"
                        >
                            Remover
                        </button>
                    </div>
                    <TextInput v-model="question.text" class="block w-full" required placeholder="Texto da pergunta" />
                    <TextInput
                        v-model="question.question_key"
                        class="mt-2 block w-full text-xs"
                        placeholder="Chave técnica (opcional, ex.: exp_motivo_saida)"
                    />
                </div>

                <SecondaryButton type="button" @click="addQuestion(sIndex)">+ Pergunta</SecondaryButton>
            </div>

            <div class="flex flex-wrap gap-3">
                <SecondaryButton type="button" @click="addSection">+ Seção</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Salvar roteiro</PrimaryButton>
                <Link :href="route('admin.entrevistas.roteiros.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
