<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    surveys: { type: Array, default: () => [] },
    queue: { type: Array, default: () => [] },
    counters: { type: Object, default: () => ({ identified: 0, in_contact: 0, to_qualify: 0 }) },
    filters: { type: Object, default: () => ({ origin: '', status: '' }) },
    originLabels: { type: Object, default: () => ({}) },
    statusLabels: { type: Object, default: () => ({}) },
    canWork: { type: Boolean, default: false },
});

const applyFilter = (key, value) => {
    router.get(route('admin.nr1-opportunities.index'), { ...props.filters, [key]: value || undefined }, { preserveState: true, replace: true });
};

const assume = (row) => router.post(route('admin.nr1-opportunities.assume', row.id), {}, { preserveScroll: true });

const statusClass = (row) => {
    if (row.status === 'won') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    if (row.status === 'lost') return 'bg-gray-100 text-gray-700 border-gray-200';
    if (row.status === 'new' || row.status === 'to_qualify') return 'bg-amber-50 text-amber-800 border-amber-200';

    return 'bg-sky-50 text-sky-800 border-sky-200';
};
</script>

<template>
    <Head title="Oportunidades NR-1" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Oportunidades NR-1</h2>
                <Link :href="route('admin.service-matches.index')" class="text-sm text-talents-700 underline">Regras de recomendação das soluções</Link>
            </div>
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $page.props.flash.error }}</div>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Resumo da fila">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Oportunidades identificadas</p>
                    <p class="mt-1 text-3xl font-bold text-talents-900">{{ counters.identified }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Em contato</p>
                    <p class="mt-1 text-3xl font-bold text-talents-900">{{ counters.in_contact }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">A qualificar</p>
                    <p class="mt-1 text-3xl font-bold text-talents-900">{{ counters.to_qualify }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="queue-title">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h3 id="queue-title" class="text-base font-semibold text-talents-900">Fila de oportunidades</h3>
                    <div class="flex flex-wrap gap-3">
                        <div>
                            <label for="filter-origin" class="block text-xs font-semibold text-gray-600">Origem</label>
                            <select id="filter-origin" class="mt-1 rounded-md border-gray-300 text-sm" :value="filters.origin" @change="applyFilter('origin', $event.target.value)">
                                <option value="">Todas</option>
                                <option v-for="(label, value) in originLabels" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="filter-status" class="block text-xs font-semibold text-gray-600">Estado</label>
                            <select id="filter-status" class="mt-1 rounded-md border-gray-300 text-sm" :value="filters.status" @change="applyFilter('status', $event.target.value)">
                                <option value="">Todos</option>
                                <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <p v-if="!queue.length" class="mt-4 text-sm text-gray-600">Nenhuma oportunidade na fila.</p>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">Oportunidades NR-1 com origem, cliente, estado, responsável e próxima ação</caption>
                        <thead class="border-b text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th scope="col" class="py-2 pr-4">Origem</th>
                                <th scope="col" class="py-2 pr-4">Cliente e campanha</th>
                                <th scope="col" class="py-2 pr-4">Serviço</th>
                                <th scope="col" class="py-2 pr-4">Estado</th>
                                <th scope="col" class="py-2 pr-4">Responsável</th>
                                <th scope="col" class="py-2 pr-4">Última atividade</th>
                                <th scope="col" class="py-2 pr-4">Próxima ação</th>
                                <th scope="col" class="py-2"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in queue" :key="row.id">
                                <td class="py-3 pr-4">{{ row.origin_label }}</td>
                                <td class="py-3 pr-4">
                                    <p class="font-medium text-gray-900">{{ row.company_name }}</p>
                                    <p class="text-xs text-gray-500">{{ row.survey_title }}</p>
                                </td>
                                <td class="py-3 pr-4">{{ row.service }}</td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-semibold" :class="statusClass(row)">{{ row.status_label }}</span>
                                </td>
                                <td class="py-3 pr-4">{{ row.assignee ?? '—' }}</td>
                                <td class="py-3 pr-4">{{ row.last_activity_at }}</td>
                                <td class="py-3 pr-4">
                                    <span v-if="row.next_action">{{ row.next_action }}<span v-if="row.next_action_at" class="text-xs text-gray-500"> · {{ row.next_action_at }}</span></span>
                                    <span v-else class="text-gray-500">—</span>
                                </td>
                                <td class="whitespace-nowrap py-3">
                                    <button
                                        v-if="canWork && row.open && !row.assigned_to"
                                        type="button"
                                        class="mr-3 rounded-md bg-talents-700 px-3 py-1 text-xs font-semibold text-white"
                                        @click="assume(row)"
                                    >
                                        Assumir oportunidade
                                    </button>
                                    <Link :href="route('admin.nr1-opportunities.opportunity', row.id)" class="text-sm text-talents-700 underline">Abrir</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="published-title">
                <h3 id="published-title" class="text-base font-semibold text-talents-900">Campanhas publicadas</h3>
                <p class="mt-1 text-sm text-gray-600">Resultados agregados de campanhas publicadas. Respostas individuais nunca aparecem aqui.</p>

                <p v-if="!surveys.length" class="mt-3 text-sm text-gray-600">Nenhuma campanha publicada.</p>

                <ul v-else class="mt-3 divide-y">
                    <li v-for="survey in surveys" :key="survey.id" class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ survey.company_name }} · {{ survey.title }}</p>
                            <p class="text-sm text-gray-600">{{ survey.starts_at }} a {{ survey.ends_at }} · publicada em {{ survey.published_at }}</p>
                        </div>
                        <Link :href="route('admin.nr1-opportunities.show', survey.id)" class="shrink-0 text-sm underline">Ver resultado</Link>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
