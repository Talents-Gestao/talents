<script setup>
import PaginationBar from '@/Components/PaginationBar.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArchiveBoxArrowDownIcon,
    ArrowUturnLeftIcon,
    EyeIcon,
    MagnifyingGlassIcon,
    PaperClipIcon,
    PencilSquareIcon,
    PlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    candidates: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const searchQ = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? 'active');

const statusOptions = [
    { value: 'active', label: 'Ativos' },
    { value: 'archived', label: 'Arquivados' },
    { value: 'all', label: 'Todos' },
];

const applyFilters = () => {
    router.get(
        route('admin.contratacao.candidatos.index'),
        { q: searchQ.value || undefined, status: status.value === 'active' ? undefined : status.value },
        { preserveState: true, replace: true },
    );
};

const setStatus = (value) => {
    status.value = value;
    applyFilters();
};

const archive = async (candidate) => {
    const ok = await confirmDialog({
        title: `Arquivar o cadastro de ${candidate.name}?`,
        message: 'Candidaturas, entrevistas e anexos são preservados. O cadastro pode ser restaurado depois e não entra em vagas novas enquanto estiver arquivado.',
        confirmLabel: 'Arquivar',
    });
    if (ok) {
        router.post(route('admin.contratacao.candidatos.archive', candidate.id), {}, { preserveScroll: true });
    }
};

const restore = (candidate) => {
    router.post(route('admin.contratacao.candidatos.restore', candidate.id), {}, { preserveScroll: true });
};

const iconButton =
    'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-talents-50 hover:text-talents-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-300';
</script>

<template>
    <Head title="Candidatos" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Candidatos</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Cadastro de candidatos usado nas vagas. Excluir arquiva e permite restaurar; o nome não identifica a
                        pessoa.
                    </p>
                </div>
                <Link v-if="can.create" :href="route('admin.contratacao.candidatos.create')">
                    <PrimaryButton type="button" class="gap-1.5">
                        <PlusIcon class="h-4 w-4" aria-hidden="true" />
                        Novo candidato
                    </PrimaryButton>
                </Link>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.errors?.candidate"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.errors.candidate }}
        </div>

        <section class="surface-card p-4 sm:p-5">
            <form class="flex flex-col gap-3 lg:flex-row lg:items-center" @submit.prevent="applyFilters">
                <div class="relative min-w-0 flex-1">
                    <label for="candidate-search" class="sr-only">Buscar por nome, e-mail ou telefone</label>
                    <MagnifyingGlassIcon
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        id="candidate-search"
                        v-model="searchQ"
                        type="search"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                        placeholder="Nome, e-mail ou telefone (só os dígitos já bastam)"
                    />
                </div>
                <div class="flex gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold" role="group" aria-label="Situação do cadastro">
                    <button
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-lg px-3 py-1.5"
                        :class="status === opt.value ? 'bg-white text-talents-800 shadow-sm' : 'text-slate-600'"
                        :aria-pressed="status === opt.value"
                        @click="setStatus(opt.value)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                >
                    Buscar
                </button>
            </form>
        </section>

        <section class="surface-card mt-5 overflow-hidden">
            <div v-if="candidates.data.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nome</th>
                            <th scope="col" class="px-4 py-3">Contato</th>
                            <th scope="col" class="px-4 py-3">Cargo · empresa atual</th>
                            <th scope="col" class="px-4 py-3 text-right">Vagas</th>
                            <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="c in candidates.data" :key="c.id" :class="c.archived ? 'bg-slate-50/60' : 'hover:bg-talents-50/30'">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.contratacao.candidatos.show', c.id)"
                                    class="font-semibold text-talents-800 hover:text-talents-900 hover:underline"
                                >
                                    {{ c.name }}
                                </Link>
                                <span
                                    v-if="c.archived"
                                    class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700"
                                >
                                    Arquivado
                                </span>
                                <p v-if="c.source" class="text-xs text-slate-500">{{ c.source }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <p v-if="c.email">{{ c.email }}</p>
                                <p v-if="c.phone" class="text-xs text-slate-500">{{ c.phone }}</p>
                                <p v-if="!c.email && !c.phone" class="text-slate-400">—</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ [c.current_role, c.current_company].filter(Boolean).join(' · ') || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ c.applications_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Link
                                        :href="route('admin.contratacao.candidatos.show', c.id)"
                                        :class="iconButton"
                                        :title="`Visualizar ${c.name}`"
                                        :aria-label="`Visualizar ${c.name}`"
                                    >
                                        <EyeIcon class="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                    <Link
                                        v-if="can.edit"
                                        :href="route('admin.contratacao.candidatos.edit', c.id)"
                                        :class="iconButton"
                                        :title="`Editar ${c.name}`"
                                        :aria-label="`Editar ${c.name}`"
                                    >
                                        <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                    <template v-if="can.archive">
                                        <button
                                            v-if="!c.archived"
                                            type="button"
                                            :class="iconButton"
                                            :title="`Arquivar ${c.name}`"
                                            :aria-label="`Arquivar ${c.name}`"
                                            @click="archive(c)"
                                        >
                                            <ArchiveBoxArrowDownIcon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            :class="iconButton"
                                            :title="`Restaurar ${c.name}`"
                                            :aria-label="`Restaurar ${c.name}`"
                                            @click="restore(c)"
                                        >
                                            <ArrowUturnLeftIcon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </template>
                                    <Link
                                        v-if="can.view_attachments"
                                        :href="route('admin.contratacao.candidatos.anexos.index', c.id)"
                                        :class="iconButton"
                                        :title="`Anexos internos de ${c.name}`"
                                        :aria-label="`Anexos internos de ${c.name}`"
                                    >
                                        <PaperClipIcon class="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-talents-100 text-talents-700">
                    <UsersIcon class="h-6 w-6" aria-hidden="true" />
                </div>
                <p class="mt-4 font-semibold text-talents-900">Nenhum candidato encontrado</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">Ajuste a busca ou a situação do cadastro.</p>
            </div>
        </section>

        <div class="mt-4">
            <PaginationBar :paginator="candidates" />
        </div>
    </AdminLayout>
</template>
