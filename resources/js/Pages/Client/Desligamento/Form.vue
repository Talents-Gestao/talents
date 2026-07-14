<script setup>
import DesligamentoLayout from '@/Components/Desligamento/DesligamentoLayout.vue';
import ExitInterviewAccordions from '@/Components/Desligamento/ExitInterviewAccordions.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { desligamentoRoute, isDesligamentoAdminContext } from '@/composables/useDesligamentoRoutes';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    mode: String,
    interview: Object,
    employees: { type: Array, default: () => [] },
    rhidReady: { type: Boolean, default: false },
    statusOptions: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    consultantNoteFields: { type: Array, default: () => [] },
});

const fieldClass =
    'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60';

const emptyAnswers = () => {
    const answers = {};
    props.sections.forEach((section) => {
        section.questions.forEach((q) => {
            answers[q.key] = props.interview?.answers?.[q.key] ?? '';
        });
    });
    return answers;
};

const emptyNotes = () => {
    const notes = {};
    props.consultantNoteFields.forEach((field) => {
        notes[field.key] = props.interview?.consultant_notes?.[field.key] ?? '';
    });
    return notes;
};

const form = useForm({
    rhid_person_id: props.interview?.rhid_person_id ?? '',
    interview_date: props.interview?.interview_date ?? '',
    status: props.interview?.status ?? 'draft',
    answers: reactive(emptyAnswers()),
    consultant_notes: reactive(emptyNotes()),
});

const emptyHint = computed(() => {
    if (!props.rhidReady) {
        return 'Configure a integração RHID (Control iD) da empresa para listar colaboradores.';
    }
    return 'Nenhum colaborador ativo encontrado no RHID/Control iD.';
});

const backHref = computed(() =>
    isDesligamentoAdminContext() ? route('admin.survey-templates.index') : desligamentoRoute('index'),
);

const backLabel = computed(() => (isDesligamentoAdminContext() ? 'Mapeamentos' : 'Desligamento'));

const submit = () => {
    if (props.mode === 'edit') {
        form.put(desligamentoRoute('update', props.interview.id));
    } else {
        form.post(desligamentoRoute('store'));
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Editar desligamento' : 'Nova pesquisa de desligamento'" />

    <DesligamentoLayout>
        <template #header>
            <FormPageHeader
                :back-href="backHref"
                :back-label="backLabel"
                :title="mode === 'edit' ? 'Editar pesquisa' : 'Nova pesquisa'"
                subtitle="Preencha o roteiro frente a frente com o colaborador"
            />
        </template>

        <form class="space-y-3" @submit.prevent="submit">
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="space-y-5 p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Colaborador" />
                            <select
                                v-model="form.rhid_person_id"
                                required
                                :disabled="!employees.length"
                                :class="fieldClass"
                            >
                                <option value="" disabled>Selecione</option>
                                <option v-for="employee in employees" :key="employee.id" :value="employee.id">
                                    {{ employee.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.rhid_person_id" />
                            <p v-if="!employees.length" class="mt-2 text-xs text-amber-700">{{ emptyHint }}</p>
                        </div>
                        <div>
                            <InputLabel value="Data da entrevista" />
                            <input v-model="form.interview_date" type="date" :class="fieldClass" />
                            <InputError :message="form.errors.interview_date" />
                        </div>
                    </div>
                    <div class="max-w-xs">
                        <InputLabel value="Status" />
                        <select v-model="form.status" required :class="fieldClass">
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </div>
            </div>

            <ExitInterviewAccordions
                mode="form"
                :sections="sections"
                :consultant-note-fields="consultantNoteFields"
                :answers="form.answers"
                :consultant-notes="form.consultant_notes"
                :field-class="fieldClass"
            />

            <div class="flex items-center justify-end gap-3 pt-3">
                <Link :href="backHref">
                    <SecondaryButton type="button">Cancelar</SecondaryButton>
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing || !employees.length">
                    {{ mode === 'edit' ? 'Salvar' : 'Cadastrar' }}
                </PrimaryButton>
            </div>
        </form>
    </DesligamentoLayout>
</template>
