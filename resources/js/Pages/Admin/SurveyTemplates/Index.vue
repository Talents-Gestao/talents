<script setup>
import ExitInterviewAccordions from '@/Components/Offboarding/ExitInterviewAccordions.vue';
import FeedbackSectionAccordion from '@/Components/Feedback/FeedbackSectionAccordion.vue';
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { desligamentoRoute } from '@/composables/useDesligamentoRoutes';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    templates: Object,
    exitInterview: {
        type: Object,
        default: () => ({
            sections: [],
            consultantNoteFields: [],
            canManage: false,
            needsCompanySelection: false,
            companyPicker: null,
            activeCompany: null,
            interviews: null,
        }),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const templatesCount = computed(() => props.templates?.data?.length ?? 0);

const removeInterview = (id) => {
    if (confirm('Remover esta pesquisa de desligamento?')) {
        router.delete(desligamentoRoute('destroy', id));
    }
};
</script>

<template>
    <Head title="Mapeamentos" />

    <AdminLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-900">Mapeamentos</h2>
        </template>

        <div
            v-if="flashSuccess"
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-900"
        >
            {{ flashSuccess }}
        </div>

        <div class="space-y-4">
            <FeedbackSectionAccordion
                title="Mapeamentos"
                description="Modelos de pesquisa NR-1 e versões vinculadas"
                :default-open="true"
                :meta="`${templatesCount} modelo${templatesCount === 1 ? '' : 's'}`"
            >
                <div class="space-y-4 p-5">
                    <div class="flex justify-end">
                        <Link
                            :href="route('admin.survey-templates.create')"
                            class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                        >
                            Novo mapeamento
                        </Link>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-700">Título</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-700">Seções</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-700">Ativo</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="t in templates.data" :key="t.id">
                                        <td class="px-4 py-3">
                                            <span>{{ t.title }}</span>
                                            <span
                                                v-if="t.forked_from_id"
                                                class="ml-2 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
                                            >
                                                Versão de #{{ t.forked_from_id }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">{{ t.sections_count }}</td>
                                        <td class="px-4 py-3">{{ t.is_active ? 'Sim' : 'Não' }}</td>
                                        <td class="px-4 py-3 text-right space-x-3">
                                            <Link
                                                :href="route('admin.survey-templates.show', t.id)"
                                                class="font-medium text-talents-700 hover:underline"
                                            >
                                                Ver
                                            </Link>
                                            <Link
                                                :href="route('admin.survey-templates.edit', t.id)"
                                                class="font-medium text-talents-700 hover:underline"
                                            >
                                                Editar
                                            </Link>
                                        </td>
                                    </tr>
                                    <TableEmptyRow
                                        v-if="!templates.data.length"
                                        :colspan="4"
                                        message="Nenhum mapeamento encontrado."
                                    />
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </FeedbackSectionAccordion>

            <ExitInterviewAccordions
                mode="preview"
                :sections="exitInterview.sections"
                :consultant-note-fields="exitInterview.consultantNoteFields"
                :show-manage="exitInterview.canManage && !!exitInterview.activeCompany"
                :interviews="exitInterview.interviews"
                :company-picker="exitInterview.canManage ? exitInterview.companyPicker : null"
                :active-company-id="exitInterview.activeCompany?.id"
                :needs-company-selection="exitInterview.needsCompanySelection"
                @remove="removeInterview"
            />
        </div>
    </AdminLayout>
</template>
