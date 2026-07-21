<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    questionnaires: Array,
});

const destroyQuestionnaire = (id, name) => {
    if (!confirm(`Excluir o roteiro "${name}"?`)) {
        return;
    }
    router.delete(route('admin.entrevistas.roteiros.destroy', id));
};
</script>

<template>
    <Head title="Roteiros de entrevista" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Roteiros de entrevista</h2>
                    <p class="mt-1 text-sm text-gray-600">Templates de perguntas usados na extração automática de respostas.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('admin.entrevistas.index')">
                        <PrimaryButton type="button" class="!bg-white !text-talents-800 ring-1 ring-talents-200">
                            Voltar
                        </PrimaryButton>
                    </Link>
                    <Link :href="route('admin.entrevistas.roteiros.create')">
                        <PrimaryButton>Novo roteiro</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div
            v-if="!questionnaires?.length"
            class="surface-card p-8 text-center text-sm text-gray-600"
        >
            Nenhum roteiro encontrado.
        </div>

        <div v-else class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Seções</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Entrevistas</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="q in questionnaires" :key="q.id">
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900">{{ q.name }}</span>
                            <span
                                v-if="q.is_default"
                                class="ml-2 inline-flex rounded-full bg-talents-100 px-2 py-0.5 text-xs text-talents-900"
                            >
                                Padrão
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ q.sections_count }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ q.interviews_count }}</td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link
                                :href="route('admin.entrevistas.roteiros.edit', q.id)"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                Editar
                            </Link>
                            <button
                                v-if="!q.is_default"
                                type="button"
                                class="text-sm text-red-600 hover:underline"
                                @click="destroyQuestionnaire(q.id, q.name)"
                            >
                                Excluir
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
