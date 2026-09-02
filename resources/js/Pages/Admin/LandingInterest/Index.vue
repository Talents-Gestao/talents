<script setup>
import FullScreenOverlay from '@/Components/FullScreenOverlay.vue';
import LandingInterestSourceField from '@/Components/Landing/LandingInterestSourceField.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    submissions: Object,
    sourceOptions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            source: '',
            qualified: '',
            created_from: '',
            created_to: '',
        }),
    },
});

const page = usePage();
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedLead = ref(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company: '',
    message: '',
    source: '',
});

const notesForm = useForm({
    admin_notes: '',
    is_qualified: '',
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const filterErrors = computed(() => page.props.errors ?? {});

const filterState = reactive({
    search: props.filters.search ?? '',
    source: props.filters.source ?? '',
    qualified: props.filters.qualified ?? '',
    created_from: props.filters.created_from ?? '',
    created_to: props.filters.created_to ?? '',
});

watch(
    () => props.filters,
    (filters) => {
        filterState.search = filters.search ?? '';
        filterState.source = filters.source ?? '';
        filterState.qualified = filters.qualified ?? '';
        filterState.created_from = filters.created_from ?? '';
        filterState.created_to = filters.created_to ?? '';
    },
    { deep: true },
);

const filterQuery = () => {
    const params = {};
    if (String(filterState.search ?? '').trim() !== '') {
        params.search = String(filterState.search).trim();
    }
    if (String(filterState.source ?? '') !== '') {
        params.source = filterState.source;
    }
    if (String(filterState.qualified ?? '') !== '') {
        params.qualified = filterState.qualified;
    }
    if (String(filterState.created_from ?? '') !== '') {
        params.created_from = filterState.created_from;
    }
    if (String(filterState.created_to ?? '') !== '') {
        params.created_to = filterState.created_to;
    }
    return params;
};

const applyFilters = () => {
    router.get(route('admin.landing-interest.index'), filterQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterState.search = '';
    filterState.source = '';
    filterState.qualified = '';
    filterState.created_from = '';
    filterState.created_to = '';
    applyFilters();
};

const formatFilterDate = (ymd) => {
    const raw = String(ymd ?? '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw;
    }
    const [y, m, d] = raw.split('-');
    return `${d}/${m}/${y}`;
};

const sourceFilterLabel = (value) => {
    const found = props.sourceOptions.find((option) => option.value === value);
    return found?.label ?? value;
};

const qualifiedFilterLabel = (value) => {
    if (value === 'yes') {
        return 'Sim';
    }
    if (value === 'no') {
        return 'Não';
    }
    if (value === 'pending') {
        return 'Ainda não avaliado';
    }
    return '';
};

const activeFilterChips = computed(() => {
    const chips = [];
    if (String(filterState.search ?? '').trim() !== '') {
        chips.push({
            key: 'search',
            label: `Busca: ${String(filterState.search).trim()}`,
        });
    }
    if (String(filterState.source ?? '') !== '') {
        chips.push({
            key: 'source',
            label: `Origem: ${sourceFilterLabel(filterState.source)}`,
        });
    }
    if (String(filterState.qualified ?? '') !== '') {
        chips.push({
            key: 'qualified',
            label: `Qualificado: ${qualifiedFilterLabel(filterState.qualified)}`,
        });
    }
    if (filterState.created_from || filterState.created_to) {
        const from = filterState.created_from ? formatFilterDate(filterState.created_from) : '…';
        const to = filterState.created_to ? formatFilterDate(filterState.created_to) : '…';
        chips.push({
            key: 'created',
            label: `Data: ${from} – ${to}`,
        });
    }
    return chips;
});

const hasActiveFilters = computed(() => activeFilterChips.value.length > 0);

const clearActiveFilter = (key) => {
    if (key === 'search') {
        filterState.search = '';
    }
    if (key === 'source') {
        filterState.source = '';
    }
    if (key === 'qualified') {
        filterState.qualified = '';
    }
    if (key === 'created') {
        filterState.created_from = '';
        filterState.created_to = '';
    }
    applyFilters();
};

function qualifiedLabel(value) {
    if (value === true) {
        return 'Sim';
    }
    if (value === false) {
        return 'Não';
    }
    return '—';
}

function qualifiedBadgeClass(value) {
    if (value === true) {
        return 'bg-emerald-100 text-emerald-800';
    }
    if (value === false) {
        return 'bg-slate-100 text-slate-600';
    }
    return 'bg-amber-50 text-amber-800';
}

function mailErrorPresent(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

function mailErrorText(value) {
    if (value === null || value === undefined) {
        return '';
    }
    return typeof value === 'string' ? value : String(value);
}

function formatDateTime(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function openCreateModal() {
    form.clearErrors();
    form.reset();
    form.source = '';
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

function openDetailModal(lead) {
    selectedLead.value = lead;
    notesForm.clearErrors();
    notesForm.admin_notes = lead.admin_notes ?? '';
    notesForm.is_qualified = lead.is_qualified === true ? '1' : lead.is_qualified === false ? '0' : '';
    showDetailModal.value = true;
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedLead.value = null;
    notesForm.reset();
    notesForm.clearErrors();
}

watch(
    () => page.props.submissions,
    (submissions) => {
        if (!showDetailModal.value || !selectedLead.value) {
            return;
        }
        const fresh = (submissions?.data ?? []).find((row) => row.id === selectedLead.value.id);
        if (fresh) {
            selectedLead.value = fresh;
            if (!notesForm.isDirty) {
                notesForm.admin_notes = fresh.admin_notes ?? '';
                notesForm.is_qualified = fresh.is_qualified === true
                    ? '1'
                    : fresh.is_qualified === false
                        ? '0'
                        : '';
            }
        }
    },
    { deep: true },
);

function submitLead() {
    form.post(route('admin.landing-interest.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
            form.reset();
            form.source = '';
        },
    });
}

function saveNotes() {
    if (!selectedLead.value) {
        return;
    }
    notesForm
        .transform((data) => ({
            admin_notes: data.admin_notes,
            is_qualified: data.is_qualified === '' ? null : data.is_qualified === '1',
        }))
        .patch(route('admin.landing-interest.update', selectedLead.value.id), {
            preserveScroll: true,
        });
}

function destroyLead(lead, event) {
    event?.stopPropagation?.();
    if (!window.confirm(`Excluir o lead «${lead.name}»? Esta ação não pode ser desfeita.`)) {
        return;
    }
    if (selectedLead.value?.id === lead.id) {
        closeDetailModal();
    }
    router.delete(route('admin.landing-interest.destroy', lead.id), {
        preserveScroll: true,
    });
}

function onKeydown(e) {
    if (e.key !== 'Escape') {
        return;
    }
    if (showDetailModal.value) {
        closeDetailModal();
        return;
    }
    if (showCreateModal.value) {
        closeCreateModal();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Leads" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Leads</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Interessados captados pelo site ou cadastrados manualmente pelo time comercial.
                    </p>
                </div>
                <button type="button" class="btn-primary shrink-0" @click="openCreateModal">Novo Lead</button>
            </div>
        </template>

        <div
            v-if="flashSuccess"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ flashSuccess }}
        </div>

        <div class="surface-card p-6">
            <form class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6" @submit.prevent="applyFilters">
                <div class="xl:col-span-2">
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="leads-filter-search">
                        Buscar
                    </label>
                    <input
                        id="leads-filter-search"
                        v-model="filterState.search"
                        type="search"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Nome, e-mail, empresa ou telefone"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="leads-filter-source">
                        Origem
                    </label>
                    <select
                        id="leads-filter-source"
                        v-model="filterState.source"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todas</option>
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="leads-filter-qualified">
                        Qualificado
                    </label>
                    <select
                        id="leads-filter-qualified"
                        v-model="filterState.qualified"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todos</option>
                        <option value="yes">Sim</option>
                        <option value="no">Não</option>
                        <option value="pending">Ainda não avaliado</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="leads-filter-from">
                        Data de
                    </label>
                    <input
                        id="leads-filter-from"
                        v-model="filterState.created_from"
                        type="date"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="filterErrors.created_from" class="mt-1 text-xs text-rose-600">
                        {{ filterErrors.created_from }}
                    </p>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="leads-filter-to">
                        até
                    </label>
                    <input
                        id="leads-filter-to"
                        v-model="filterState.created_to"
                        type="date"
                        class="mt-1 w-full rounded-xl border-slate-300 shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    />
                    <p v-if="filterErrors.created_to" class="mt-1 text-xs text-rose-600">
                        {{ filterErrors.created_to }}
                    </p>
                </div>
                <div class="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-6">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="clearFilters"
                    >
                        Limpar
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                    >
                        Filtrar
                    </button>
                </div>
            </form>

            <div
                v-if="activeFilterChips.length"
                class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4"
            >
                <button
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border border-talents-200 bg-talents-50 px-2.5 py-1 text-xs font-medium text-talents-800 transition hover:bg-talents-100"
                    :title="`Remover filtro ${chip.label}`"
                    @click="clearActiveFilter(chip.key)"
                >
                    <span>{{ chip.label }}</span>
                    <XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="text-xs font-semibold text-slate-500 underline-offset-2 transition hover:text-talents-700 hover:underline"
                    @click="clearFilters"
                >
                    Limpar tudo
                </button>
            </div>
        </div>

        <div class="mt-6 surface-card overflow-hidden">
            <div v-if="!submissions.data.length" class="px-4 py-10 text-center text-sm text-gray-600">
                {{ hasActiveFilters ? 'Nenhum lead encontrado para os filtros selecionados.' : 'Nenhum lead encontrado.' }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Data</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">E-mail</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Telefone</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Empresa</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Origem</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Qualificado</th>
                            <th class="min-w-[12rem] px-4 py-3 text-left font-medium text-gray-700">Mensagem</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">E-mail aviso</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right font-medium text-gray-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr
                            v-for="s in submissions.data"
                            :key="s.id"
                            class="cursor-pointer transition hover:bg-talents-50/60"
                            @click="openDetailModal(s)"
                        >
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-gray-600">
                                {{ formatDateTime(s.created_at) }}
                            </td>
                            <td class="px-4 py-3 align-middle font-medium text-talents-800">{{ s.name }}</td>
                            <td class="px-4 py-3 align-middle">
                                <a
                                    :href="'mailto:' + s.email"
                                    class="font-medium text-talents-700 hover:underline"
                                    @click.stop
                                >
                                    {{ s.email }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-gray-700">{{ s.phone || '—' }}</td>
                            <td class="max-w-xs truncate px-4 py-3 align-middle">{{ s.company || '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span
                                    class="inline-flex rounded-full bg-talents-50 px-2 py-0.5 text-xs font-medium text-talents-800 ring-1 ring-talents-100"
                                >
                                    {{ s.source_label || '—' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="qualifiedBadgeClass(s.is_qualified)"
                                >
                                    {{ qualifiedLabel(s.is_qualified) }}
                                </span>
                            </td>
                            <td class="max-w-md px-4 py-3 align-middle">
                                <span class="line-clamp-3 whitespace-pre-wrap">{{ s.message || '—' }}</span>
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <span
                                    v-if="s.mail_sent_at"
                                    class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                                >
                                    Enviado
                                </span>
                                <span
                                    v-else-if="mailErrorPresent(s.mail_error)"
                                    class="inline-flex max-w-[14rem] flex-col gap-1"
                                    :title="mailErrorText(s.mail_error)"
                                >
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900"
                                    >
                                        Falha SMTP
                                    </span>
                                    <span class="line-clamp-2 text-xs text-gray-500">{{ mailErrorText(s.mail_error) }}</span>
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-right">
                                <button
                                    type="button"
                                    class="mr-2 font-medium text-talents-700 hover:underline"
                                    @click.stop="openDetailModal(s)"
                                >
                                    Ver
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-700"
                                    title="Excluir lead"
                                    aria-label="Excluir lead"
                                    @click="destroyLead(s, $event)"
                                >
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="submissions.data.length && submissions.links && submissions.links.length > 3"
                class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-4 py-3"
            >
                <template v-for="(link, i) in submissions.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded px-2 py-1 text-sm"
                        :class="link.active ? 'bg-talents-600 text-white' : 'text-talents-700 hover:bg-talents-50'"
                        preserve-scroll
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="cursor-not-allowed rounded px-2 py-1 text-sm text-gray-400"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>

        <FullScreenOverlay :show="showCreateModal" @close="closeCreateModal">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-gray-900">Novo Lead</h3>
                <p class="mt-1 text-sm text-gray-600">Cadastro manual para leads captados fora do site.</p>

                <form class="mt-5 space-y-4" @submit.prevent="submitLead">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-name">Nome</label>
                        <input id="lead-name" v-model="form.name" type="text" required class="field-input" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-email">E-mail</label>
                        <input id="lead-email" v-model="form.email" type="email" required class="field-input" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-phone">
                            Telefone / WhatsApp <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <input id="lead-phone" v-model="form.phone" type="tel" class="field-input" />
                        <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-company">
                            Empresa <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <input id="lead-company" v-model="form.company" type="text" class="field-input" />
                        <p v-if="form.errors.company" class="mt-1 text-sm text-red-600">{{ form.errors.company }}</p>
                    </div>
                    <LandingInterestSourceField
                        id="lead-source"
                        v-model="form.source"
                        empty-option
                        :error="form.errors.source"
                    />
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-message">
                            Mensagem <span class="font-normal text-gray-500">(opcional)</span>
                        </label>
                        <textarea id="lead-message" v-model="form.message" rows="3" class="field-input" />
                        <p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary" @click="closeCreateModal">Cancelar</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Salvando…' : 'Salvar lead' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>

        <FullScreenOverlay :show="showDetailModal" @close="closeDetailModal">
            <div
                v-if="selectedLead"
                class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="lead-detail-title"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 id="lead-detail-title" class="text-lg font-semibold text-gray-900">
                            {{ selectedLead.name }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Detalhe do lead</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-700"
                        title="Excluir lead"
                        aria-label="Excluir lead"
                        @click="destroyLead(selectedLead, $event)"
                    >
                        <TrashIcon class="h-5 w-5" />
                    </button>
                </div>

                <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Data</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(selectedLead.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Origem</dt>
                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded-full bg-talents-50 px-2 py-0.5 text-xs font-medium text-talents-800 ring-1 ring-talents-100"
                            >
                                {{ selectedLead.source_label || '—' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail</dt>
                        <dd class="mt-1 text-sm">
                            <a :href="'mailto:' + selectedLead.email" class="font-medium text-talents-700 hover:underline">
                                {{ selectedLead.email }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Telefone / WhatsApp</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ selectedLead.phone || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Empresa</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ selectedLead.company || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Mensagem</dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-slate-900">
                            {{ selectedLead.message || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Cadastrado por</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ selectedLead.created_by_name || 'Site / formulário público' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">E-mail aviso</dt>
                        <dd class="mt-1 text-sm">
                            <span
                                v-if="selectedLead.mail_sent_at"
                                class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                            >
                                Enviado em {{ formatDateTime(selectedLead.mail_sent_at) }}
                            </span>
                            <span
                                v-else-if="mailErrorPresent(selectedLead.mail_error)"
                                class="text-amber-800"
                            >
                                Falha SMTP — {{ mailErrorText(selectedLead.mail_error) }}
                            </span>
                            <span v-else class="text-slate-500">—</span>
                        </dd>
                    </div>
                </dl>

                <form class="mt-6 border-t border-slate-100 pt-5 space-y-4" @submit.prevent="saveNotes">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-is-qualified">
                            Qualificado
                        </label>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Após a reunião com o cliente, indique se o lead foi qualificado.
                        </p>
                        <select
                            id="lead-is-qualified"
                            v-model="notesForm.is_qualified"
                            class="field-input mt-2"
                        >
                            <option value="">Ainda não avaliado</option>
                            <option value="1">Sim</option>
                            <option value="0">Não</option>
                        </select>
                        <p v-if="notesForm.errors.is_qualified" class="mt-1 text-sm text-red-600">
                            {{ notesForm.errors.is_qualified }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="lead-admin-notes">
                            Anotações internas
                        </label>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Use este campo para registar follow-ups, combinações e observações da equipe.
                        </p>
                        <textarea
                            id="lead-admin-notes"
                            v-model="notesForm.admin_notes"
                            rows="5"
                            class="field-input mt-2"
                            placeholder="Ex.: Ligar na sexta; pediu proposta de NR-1…"
                        />
                        <p v-if="notesForm.errors.admin_notes" class="mt-1 text-sm text-red-600">
                            {{ notesForm.errors.admin_notes }}
                        </p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary" @click="closeDetailModal">Fechar</button>
                        <button type="submit" class="btn-primary" :disabled="notesForm.processing">
                            {{ notesForm.processing ? 'Salvando…' : 'Salvar' }}
                        </button>
                    </div>
                </form>
            </div>
        </FullScreenOverlay>
    </AdminLayout>
</template>
