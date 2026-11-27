<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    questionnaire: { type: Object, default: null },
    can: { type: Object, default: () => ({}) },
});

// Roteiro com entrevistas: seções e perguntas ficam travadas; só nome, descrição e «padrão» mudam.
const locked = computed(() => Boolean(props.questionnaire?.locked));

const duplicate = () => {
    router.post(route('admin.entrevistas.roteiros.duplicate', props.questionnaire.id));
};

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
                :subtitle="questionnaire ? `Versão ${questionnaire.version}` : null"
            >
                <template v-if="questionnaire && can.duplicate" #trailing>
                    <SecondaryButton type="button" @click="duplicate">Duplicar como nova versão</SecondaryButton>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 max-w-3xl rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>
        <div v-if="locked" class="mb-4 max-w-3xl rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Este roteiro já tem entrevistas. Seções e perguntas não podem ser alteradas nem removidas aqui, para não apagar respostas.
            <template v-if="can.duplicate">Use «Duplicar como nova versão» para mudar o roteiro; </template>
            <template v-else>Para mudar o roteiro, peça a quem pode criar roteiros que o duplique como nova versão; </template>
            nome, descrição e «padrão» continuam editáveis.
        </div>
        <p
            v-if="form.errors.sections"
            class="mb-4 max-w-3xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            {{ form.errors.sections }}
        </p>

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

            <fieldset
                v-for="(section, sIndex) in form.sections"
                :key="sIndex"
                class="surface-card p-6"
                :disabled="locked"
                :class="locked ? 'opacity-80' : ''"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-900">Seção {{ sIndex + 1 }}</h3>
                    <button
                        v-if="form.sections.length > 1 && !locked"
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
                            v-if="section.questions.length > 1 && !locked"
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

                <SecondaryButton v-if="!locked" type="button" @click="addQuestion(sIndex)">+ Pergunta</SecondaryButton>
            </fieldset>

            <div class="flex flex-wrap gap-3">
                <SecondaryButton v-if="!locked" type="button" @click="addSection">+ Seção</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Salvar roteiro</PrimaryButton>
                <Link :href="route('admin.entrevistas.roteiros.index')">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
