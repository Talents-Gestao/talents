<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    configured: Boolean,
    view_mode: { type: String, default: 'list' },
    filters: Object,
    page: Number,
    curricula: Array,
    pagination: Object,
    grouped_summary: { type: Object, default: null },
    grouped_meta: { type: Object, default: null },
    grouped: { type: Array, default: () => [] },
    error: { type: String, default: null },
});

const filterForm = useForm({
    data_inicial: props.filters.data_inicial ?? '',
    data_final: props.filters.data_final ?? '',
    origem_contains: props.filters.origem_contains ?? '',
    grupo_contains: props.filters.grupo_contains ?? '',
});

const queryForView = (view) => {
    const q = { view };
    if (filterForm.data_inicial) q.data_inicial = filterForm.data_inicial;
    if (filterForm.data_final) q.data_final = filterForm.data_final;
    if (filterForm.origem_contains) q.origem_contains = filterForm.origem_contains;
    if (filterForm.grupo_contains) q.grupo_contains = filterForm.grupo_contains;
    if (view === 'list') {
        q.page = 1;
    }
    return q;
};

const listModeHref = computed(() => route('admin.solides.curriculos.index', queryForView('list')));
const groupedModeHref = computed(() => route('admin.solides.curriculos.index', queryForView('grouped')));

const applyFilters = () => {
    filterForm
        .transform((data) => {
            const out = {
                view: props.view_mode,
                page: props.view_mode === 'list' ? 1 : undefined,
                data_inicial: data.data_inicial || undefined,
                data_final: data.data_final || undefined,
                origem_contains: data.origem_contains || undefined,
                grupo_contains: data.grupo_contains || undefined,
            };
            return Object.fromEntries(Object.entries(out).filter(([, v]) => v !== undefined && v !== ''));
        })
        .get(route('admin.solides.curriculos.index'), {
            preserveState: true,
            replace: true,
        });
};
</script>

<template>
    <Head title="Sólides — Currículos" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Sólides — Currículos e candidatos</h2>
                <p class="mt-1 text-sm text-gray-600">
                    <span v-if="view_mode === 'list'">
                        Lista paginada via
                        <code class="rounded bg-gray-100 px-1 text-xs">GET /curriculos</code>
                        .
                    </span>
                    <span v-else>
                        Visão agregada por
                        <strong>vaga inferida</strong>
                        (origem ou senioridade no currículo; perfil no passaporte). Não há endpoint de vagas abertas na
                        API Gestão — trata-se de heurística operacional.
                    </span>
                </p>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div v-if="!configured" class="surface-card max-w-2xl space-y-3 p-6 text-slate-900">
            <p class="text-sm text-gray-700">Configure o token da API Sólides em Configurações para carregar os currículos.</p>
            <Link
                :href="route('admin.settings.edit', { tab: 'solides' })"
                class="inline-flex text-sm font-medium text-talents-700 hover:underline"
            >
                Abrir Configurações → Sólides
            </Link>
        </div>

        <template v-else>
            <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ error }}
            </div>

            <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-4">
                <Link
                    :href="listModeHref"
                    class="rounded-md px-4 py-2 text-sm font-medium"
                    :class="
                        view_mode === 'list'
                            ? 'bg-talents-100 text-talents-900'
                            : 'text-gray-600 hover:bg-gray-100'
                    "
                    preserve-scroll
                >
                    Lista (paginada)
                </Link>
                <Link
                    :href="groupedModeHref"
                    class="rounded-md px-4 py-2 text-sm font-medium"
                    :class="
                        view_mode === 'grouped'
                            ? 'bg-talents-100 text-talents-900'
                            : 'text-gray-600 hover:bg-gray-100'
                    "
                    preserve-scroll
                >
                    Por vaga inferida
                </Link>
            </div>

            <form
                class="surface-card mb-6 max-w-4xl space-y-4 p-6 text-slate-900"
                @submit.prevent="applyFilters"
            >
                <h3 class="text-sm font-semibold text-gray-900">Filtros</h3>
                <p class="text-xs text-gray-600">
                    Período opcional no formato <strong>dd/mm/aaaa</strong> (
                    <code class="rounded bg-gray-100 px-1">data_inicial</code> /
                    <code class="rounded bg-gray-100 px-1">data_final</code>
                    na API de currículos).
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="data_inicial" value="Data inicial" />
                        <TextInput
                            id="data_inicial"
                            v-model="filterForm.data_inicial"
                            class="mt-1 block w-full"
                            placeholder="ex.: 01/01/2024"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <InputLabel for="data_final" value="Data final" />
                        <TextInput
                            id="data_final"
                            v-model="filterForm.data_final"
                            class="mt-1 block w-full"
                            placeholder="ex.: 31/12/2024"
                            autocomplete="off"
                        />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="origem_contains" value="Texto em origem / perfil (contém)" />
                        <TextInput
                            id="origem_contains"
                            v-model="filterForm.origem_contains"
                            class="mt-1 block w-full"
                            placeholder="ex.: Jobs"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <InputLabel for="grupo_contains" value="Chave do grupo (contém)" />
                        <TextInput
                            id="grupo_contains"
                            v-model="filterForm.grupo_contains"
                            class="mt-1 block w-full"
                            placeholder="Filtra grupos na visão agregada"
                            autocomplete="off"
                        />
                    </div>
                </div>
                <PrimaryButton type="submit" :disabled="filterForm.processing">Aplicar filtros</PrimaryButton>
            </form>

            <template v-if="view_mode === 'grouped' && grouped_summary">
                <div class="mb-6 grid gap-3 sm:grid-cols-3">
                    <div class="surface-card p-4 text-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Candidatos (visíveis)</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ grouped_summary.total }}</p>
                    </div>
                    <div class="surface-card p-4 text-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Com rótulo inferido</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-800">{{ grouped_summary.with_vacancy_label }}</p>
                    </div>
                    <div class="surface-card p-4 text-slate-900">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sem vaga identificada</p>
                        <p class="mt-1 text-2xl font-semibold text-amber-900">{{ grouped_summary.unidentified }}</p>
                    </div>
                </div>
                <p v-if="grouped_meta" class="mb-4 text-xs text-gray-600">
                    Páginas de currículos consultadas: {{ grouped_meta.curriculos_pages_fetched }} · Passaportes recebidos:
                    {{ grouped_meta.passaportes_count }}
                </p>

                <div v-if="!grouped.length" class="surface-card px-4 py-10 text-center text-sm text-gray-600">
                    Nenhum grupo para os filtros atuais.
                </div>
                <div v-else class="space-y-4">
                    <details v-for="g in grouped" :key="g.inferred_key" class="surface-card overflow-hidden">
                        <summary
                            class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-gray-900 marker:content-none [&::-webkit-details-marker]:hidden"
                        >
                            <span class="inline-flex items-center gap-2">
                                <span class="rounded-full bg-talents-100 px-2 py-0.5 text-xs font-bold text-talents-900">{{
                                    g.count
                                }}</span>
                                {{ g.inferred_key }}
                            </span>
                        </summary>
                        <div class="border-t border-gray-200 px-2 pb-3">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">Fonte</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">ID</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">Nome</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">E-mail</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">CPF</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">Origem</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-700">Senioridade / perfil</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="(c, idx) in g.candidates" :key="idx">
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ c.source }}</td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ c.solides_id }}</td>
                                            <td class="max-w-[10rem] px-3 py-2">{{ c.name || '—' }}</td>
                                            <td class="px-3 py-2">
                                                <a
                                                    v-if="c.email"
                                                    :href="'mailto:' + c.email"
                                                    class="text-talents-700 hover:underline"
                                                >
                                                    {{ c.email }}
                                                </a>
                                                <span v-else class="text-gray-400">—</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ c.cpf || '—' }}</td>
                                            <td class="max-w-[8rem] truncate px-3 py-2 text-gray-700" :title="c.origin || ''">
                                                {{ c.origin || '—' }}
                                            </td>
                                            <td class="max-w-[8rem] truncate px-3 py-2 text-gray-700" :title="c.seniority_or_profile || ''">
                                                {{ c.seniority_or_profile || '—' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>
                </div>
            </template>

            <template v-else-if="view_mode === 'list'">
                <div class="surface-card overflow-hidden">
                    <div v-if="!curricula.length" class="px-4 py-10 text-center text-sm text-gray-600">
                        Nenhum currículo retornado nesta página.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">ID</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">E-mail</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">CPF</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Celular</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Nascimento</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Senioridade</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Origem</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Cidade/UF</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-center font-medium text-gray-700">Exp.</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-center font-medium text-gray-700">Form.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="c in curricula" :key="c.id">
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ c.id }}</td>
                                    <td class="max-w-[12rem] px-4 py-3 font-medium">{{ c.fullName || '—' }}</td>
                                    <td class="px-4 py-3">
                                        <a
                                            v-if="c.mainEmail"
                                            :href="'mailto:' + c.mainEmail"
                                            class="text-talents-700 hover:underline"
                                        >
                                            {{ c.mainEmail }}
                                        </a>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ c.idNumber || '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ c.mobile || c.phone || '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ c.birthDate || '—' }}</td>
                                    <td class="max-w-[8rem] truncate px-4 py-3 text-gray-700" :title="c.seniority || ''">
                                        {{ c.seniority || '—' }}
                                    </td>
                                    <td class="max-w-[10rem] truncate px-4 py-3 text-gray-700" :title="c.origin || ''">
                                        {{ c.origin || '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                        <span v-if="c.city || c.state">{{ [c.city, c.state].filter(Boolean).join(' / ') }}</span>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700">{{ c.experiences_count }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700">{{ c.education_count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="pagination.has_prev || pagination.has_next"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 text-sm"
                    >
                        <span class="text-gray-600">Página {{ pagination.current_page }}</span>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-if="pagination.prev_url"
                                :href="pagination.prev_url"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50"
                                preserve-scroll
                            >
                                Anterior
                            </Link>
                            <Link
                                v-if="pagination.next_url"
                                :href="pagination.next_url"
                                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50"
                                preserve-scroll
                            >
                                Próxima
                            </Link>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </AdminLayout>
</template>
