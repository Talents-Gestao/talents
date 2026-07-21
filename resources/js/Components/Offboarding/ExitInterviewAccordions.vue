<script setup>
import DesligamentoCompanyPicker from '@/Components/Offboarding/DesligamentoCompanyPicker.vue';
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import { desligamentoRoute } from '@/composables/useDesligamentoRoutes';
import { Link } from '@inertiajs/vue3';
import { EyeIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
    /** @type {'preview'|'form'|'show'} */
    mode: { type: String, default: 'preview' },
    sections: { type: Array, default: () => [] },
    consultantNoteFields: { type: Array, default: () => [] },
    answers: { type: Object, default: null },
    consultantNotes: { type: Object, default: null },
    interviews: { type: Object, default: null },
    showManage: { type: Boolean, default: false },
    hideConsultantNotes: { type: Boolean, default: false },
    companyPicker: { type: Array, default: null },
    activeCompanyId: { type: [Number, String], default: null },
    needsCompanySelection: { type: Boolean, default: false },
    fieldClass: {
        type: String,
        default:
            'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60',
    },
});

const emit = defineEmits(['remove']);

const totalQuestions = computed(() =>
    props.sections.reduce((sum, section) => sum + (section.questions?.length ?? 0), 0),
);

const showCompanyPicker = computed(
    () => props.mode === 'preview' && Array.isArray(props.companyPicker) && props.companyPicker.length > 0,
);

const isEditable = computed(() => props.mode === 'form');
const isShow = computed(() => props.mode === 'show');

const display = (value) => {
    const text = typeof value === 'string' ? value.trim() : '';
    return text || '—';
};

const statusBadgeClass = (status) => {
    const map = {
        draft: 'bg-slate-100 text-slate-700 ring-slate-200',
        completed: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    };
    return map[status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
};

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <FeedbackSectionAccordion
        title="Pesquisa de Desligamento"
        description="Roteiro preenchido presencialmente com o colaborador"
        :default-open="true"
        :meta="`${totalQuestions} perguntas`"
    >
        <div class="space-y-6 p-5">
            <div v-if="showCompanyPicker && needsCompanySelection" class="mx-auto max-w-xl">
                <DesligamentoCompanyPicker :companies="companyPicker" />
            </div>

            <DesligamentoCompanyPicker
                v-else-if="showCompanyPicker && !needsCompanySelection"
                compact
                :companies="companyPicker"
                :active-company-id="activeCompanyId"
            />

            <div v-if="showManage" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">
                        Registros de entrevistas por colaborador. Abra ou crie uma pesquisa para preencher o
                        roteiro.
                    </p>
                    <Link
                        :href="desligamentoRoute('create')"
                        class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                    >
                        Nova pesquisa
                    </Link>
                </div>

                <div
                    v-if="interviews?.data?.length"
                    class="overflow-hidden rounded-xl border border-slate-200"
                >
                    <table class="min-w-full text-sm">
                        <thead
                            class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            <tr>
                                <th class="px-4 py-2.5">Colaborador</th>
                                <th class="px-4 py-2.5">Data</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in interviews.data" :key="item.id">
                                <td class="px-4 py-2.5">
                                    <Link
                                        :href="desligamentoRoute('show', item.id)"
                                        class="font-medium text-talents-800 hover:text-talents-600"
                                    >
                                        {{ item.employee?.name ?? '—' }}
                                    </Link>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700">
                                    {{ formatDate(item.interview_date) }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                        :class="statusBadgeClass(item.status)"
                                    >
                                        {{ item.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="desligamentoRoute('show', item.id)"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                            title="Ver"
                                        >
                                            <EyeIcon class="h-4 w-4" />
                                        </Link>
                                        <Link
                                            :href="desligamentoRoute('edit', item.id)"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                            title="Editar"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                            title="Excluir"
                                            @click="emit('remove', item.id)"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-500"
                >
                    Nenhuma pesquisa cadastrada ainda para esta empresa.
                </p>

                <div class="border-t border-slate-100 pt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Roteiro da entrevista
                    </p>
                </div>
            </div>

            <div v-for="section in sections" :key="section.key" class="space-y-4">
                <h4 class="text-sm font-semibold text-talents-900">{{ section.title }}</h4>
                <div v-for="question in section.questions" :key="question.key" class="space-y-1">
                    <label class="block text-sm font-medium text-slate-800">{{ question.body }}</label>
                    <p v-if="question.hint" class="text-xs text-slate-500">{{ question.hint }}</p>
                    <textarea
                        v-if="isEditable"
                        v-model="answers[question.key]"
                        rows="3"
                        :class="fieldClass"
                    />
                    <p
                        v-else-if="isShow"
                        class="whitespace-pre-wrap rounded-lg bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-700"
                    >
                        {{ display(answers?.[question.key]) }}
                    </p>
                    <p v-else class="text-xs italic text-slate-400">
                        Resposta preenchida na entrevista com o colaborador.
                    </p>
                </div>
            </div>

            <FeedbackSectionAccordion
                v-if="!hideConsultantNotes"
                title="Anotações da Consultora (preenchimento interno da Talents)"
                description="Uso interno — não compartilhado com o colaborador"
                :default-open="false"
                :meta="`${consultantNoteFields.length} campos`"
            >
                <div class="space-y-5 p-5">
                    <div v-for="field in consultantNoteFields" :key="field.key" class="space-y-1">
                        <label class="block text-sm font-medium text-slate-800">{{ field.label }}</label>
                        <textarea
                            v-if="isEditable"
                            v-model="consultantNotes[field.key]"
                            rows="3"
                            :class="fieldClass"
                        />
                        <p
                            v-else-if="isShow"
                            class="whitespace-pre-wrap rounded-lg bg-slate-50 px-3 py-2 text-sm leading-relaxed text-slate-700"
                        >
                            {{ display(consultantNotes?.[field.key]) }}
                        </p>
                        <p v-else class="text-xs italic text-slate-400">
                            Campo preenchido internamente pela consultora durante/após a entrevista.
                        </p>
                    </div>
                </div>
            </FeedbackSectionAccordion>
        </div>
    </FeedbackSectionAccordion>
</template>
