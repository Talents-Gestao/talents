<script setup>
import DesligamentoLayout from '@/Components/Desligamento/DesligamentoLayout.vue';
import ExitInterviewAccordions from '@/Components/Desligamento/ExitInterviewAccordions.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { desligamentoRoute, isDesligamentoAdminContext } from '@/composables/useDesligamentoRoutes';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    interview: Object,
    sections: { type: Array, default: () => [] },
    consultantNoteFields: { type: Array, default: () => [] },
});

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');

const backHref = computed(() =>
    isDesligamentoAdminContext() ? route('admin.survey-templates.index') : desligamentoRoute('index'),
);

const backLabel = computed(() => (isDesligamentoAdminContext() ? 'Mapeamentos' : 'Desligamento'));
</script>

<template>
    <Head :title="`Desligamento — ${interview.employee?.name ?? ''}`" />

    <DesligamentoLayout>
        <template #header>
            <FormPageHeader
                :back-href="backHref"
                :back-label="backLabel"
                :title="interview.employee?.name ?? 'Pesquisa de desligamento'"
                :subtitle="`Entrevista em ${formatDate(interview.interview_date)} · ${interview.status_label}`"
            >
                <template #trailing>
                    <Link :href="desligamentoRoute('edit', interview.id)">
                        <PrimaryButton type="button">Editar</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div class="space-y-3">
            <ExitInterviewAccordions
                mode="show"
                :sections="sections"
                :consultant-note-fields="consultantNoteFields"
                :answers="interview.answers ?? {}"
                :consultant-notes="interview.consultant_notes ?? {}"
            />

            <div class="flex justify-end pt-3">
                <Link :href="backHref">
                    <SecondaryButton type="button">Voltar</SecondaryButton>
                </Link>
            </div>
        </div>
    </DesligamentoLayout>
</template>
