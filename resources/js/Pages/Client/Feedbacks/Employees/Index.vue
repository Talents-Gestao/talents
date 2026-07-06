<script setup>
import FeedbacksLayout from '@/Components/Feedback/FeedbacksLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import ListEmptyState from '@/Components/ListEmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { feedbackRoute } from '@/composables/useFeedbackRoutes';
import { Head, Link, router } from '@inertiajs/vue3';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ employees: Object });

const remove = (id) => {
    if (confirm('Remover este colaborador?')) {
        router.delete(feedbackRoute('employees.destroy', id));
    }
};
</script>

<template>
    <Head title="Colaboradores" />

    <FeedbacksLayout>
        <template #header>
            <FormPageHeader
                :back-href="feedbackRoute('index')"
                back-label="Feedbacks"
                title="Colaboradores"
                subtitle="Cadastro da equipe para alinhamentos e feedbacks"
            >
                <template #trailing>
                    <Link :href="feedbackRoute('employees.create')">
                        <PrimaryButton type="button">Cadastrar</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Nome</th>
                            <th class="px-5 py-3">E-mail</th>
                            <th class="px-5 py-3">Cargo</th>
                            <th class="px-5 py-3">Líder</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="e in employees.data" :key="e.id" class="transition hover:bg-talents-50/30">
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="feedbackRoute('employees.show', e.id)"
                                    class="font-medium text-talents-800 transition hover:text-talents-600"
                                >
                                    {{ e.name }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ e.email }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ e.position?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-600">{{ e.leader?.name ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <Link
                                        :href="feedbackRoute('employees.edit', e.id)"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                        title="Editar"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                        title="Excluir"
                                        @click="remove(e.id)"
                                    >
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ListEmptyState v-if="!employees.data?.length" message="Nenhum colaborador cadastrado." />
        </div>
    </FeedbacksLayout>
</template>
