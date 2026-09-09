<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    processes: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const searchQ = ref(props.filters.q ?? '');

const fieldClass =
    'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const applyFilters = () => {
    router.get(
        route('admin.comercial.processos.index'),
        { q: searchQ.value || undefined },
        { preserveState: true, replace: true },
    );
};

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch {
        return '—';
    }
};

const remove = async (id) => {
    if (await confirmDialog('Remover este processo comercial?')) {
        router.delete(route('admin.comercial.processos.destroy', id));
    }
};
</script>

<template>
    <Head title="Comercial — Processos" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.comercial.propostas.index')"
                back-label="Comercial"
                title="Processos"
                subtitle="Documentos oficiais do processo comercial da Talents"
            >
                <template #trailing>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('admin.comercial.processos.create')">
                            <PrimaryButton type="button">Novo processo</PrimaryButton>
                        </Link>
                    </div>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative min-w-0 flex-1">
                <MagnifyingGlassIcon
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="searchQ"
                    type="search"
                    :class="fieldClass + ' w-full pl-9'"
                    placeholder="Buscar por título ou resumo…"
                    @keydown.enter.prevent="applyFilters"
                >
            </div>
            <PrimaryButton type="button" @click="applyFilters">Filtrar</PrimaryButton>
        </div>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Título</th>
                            <th class="px-4 py-3">Anexo</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Atualizado</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 bg-white">
                        <tr v-for="row in processes.data" :key="row.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 align-middle">
                                <Link
                                    :href="route('admin.comercial.processos.show', row.id)"
                                    class="font-medium text-slate-900 hover:text-talents-700 hover:underline"
                                >
                                    {{ row.title }}
                                </Link>
                                <p v-if="row.summary" class="mt-0.5 line-clamp-2 text-xs text-slate-500">
                                    {{ row.summary }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-slate-600">
                                <span v-if="row.has_file">{{ row.file_name || 'Anexo' }}</span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                    :class="
                                        row.is_published
                                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                >
                                    {{ row.is_published ? 'Publicado' : 'Rascunho' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-slate-600">
                                {{ formatDate(row.updated_at) }}
                                <span v-if="row.updated_by?.name" class="block text-xs text-slate-400">
                                    {{ row.updated_by.name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <Link
                                        :href="route('admin.comercial.processos.show', row.id)"
                                        class="font-medium text-talents-700 hover:underline"
                                    >
                                        Ver
                                    </Link>
                                    <Link
                                        :href="route('admin.comercial.processos.edit', row.id)"
                                        class="inline-flex text-slate-500 hover:text-talents-700"
                                        title="Editar"
                                    >
                                        <PencilSquareIcon class="h-5 w-5" />
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex text-slate-400 hover:text-rose-600"
                                        title="Remover"
                                        @click="remove(row.id)"
                                    >
                                        <TrashIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!processes.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Nenhum processo cadastrado. Crie um novo e importe um DOCX, ou digite o conteúdo no editor.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
