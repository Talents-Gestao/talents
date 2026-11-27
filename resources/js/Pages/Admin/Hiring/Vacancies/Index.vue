<script setup>
import CompanySearchSelect from '@/Components/CompanySearchSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BriefcaseIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    vacancies: { type: Object, required: true },
    processes: { type: Array, required: true },
    companies: { type: Array, required: true },
    statuses: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');
const processId = ref(props.filters.hiring_process_id ? String(props.filters.hiring_process_id) : '');
const status = ref(props.filters.status ?? '');

const filterProcesses = computed(() =>
    companyId.value
        ? props.processes.filter((p) => String(p.company_id) === companyId.value)
        : props.processes,
);

const applyFilters = () => {
    router.get(
        route('admin.contratacao.vagas.index'),
        {
            q: searchQ.value || undefined,
            company_id: companyId.value || undefined,
            hiring_process_id: processId.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const clearFilters = () => {
    searchQ.value = '';
    companyId.value = '';
    processId.value = '';
    status.value = '';
    applyFilters();
};

const eligibleProcesses = computed(() => props.processes.filter((p) => p.can_receive_vacancy));

const showCreate = ref(false);
const createForm = useForm({
    hiring_process_id: '',
    title: '',
    description: '',
});

const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    const preselected = eligibleProcesses.value.find((p) => String(p.id) === processId.value);
    createForm.hiring_process_id = preselected ? String(preselected.id) : '';
    showCreate.value = true;
};

const submitCreate = () => {
    createForm.post(route('admin.contratacao.vagas.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
        },
    });
};

const statusBadgeClass = (value) =>
    ({
        open: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        paused: 'bg-amber-50 text-amber-800 ring-amber-200',
        filled: 'bg-talents-50 text-talents-800 ring-talents-200',
        cancelled: 'bg-slate-100 text-slate-600 ring-slate-200',
    })[value] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
</script>

<template>
    <Head title="Vagas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-talents-900">Vagas</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Vagas dos processos de acompanhamento, com etapas próprias e candidatos. Uso interno: a empresa
                        vê só a fase do processo.
                    </p>
                </div>
                <PrimaryButton v-if="can.create" type="button" class="gap-1.5" @click="openCreate">
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    Nova vaga
                </PrimaryButton>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <section class="surface-card p-4 sm:p-5">
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,0.8fr)_auto]" @submit.prevent="applyFilters">
                <div class="relative">
                    <label for="vacancy-search" class="sr-only">Buscar</label>
                    <MagnifyingGlassIcon
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        id="vacancy-search"
                        v-model="searchQ"
                        type="search"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                        placeholder="Buscar por vaga, empresa ou processo…"
                    />
                </div>
                <div>
                    <label for="vacancy-company" class="sr-only">Empresa</label>
                    <CompanySearchSelect
                        id="vacancy-company"
                        v-model="companyId"
                        :companies="companies"
                        as-string
                        empty-label="Todas as empresas"
                        input-class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    />
                </div>
                <div>
                    <label for="vacancy-process" class="sr-only">Processo</label>
                    <select
                        id="vacancy-process"
                        v-model="processId"
                        class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    >
                        <option value="">Todos os processos</option>
                        <option v-for="p in filterProcesses" :key="p.id" :value="String(p.id)">
                            {{ p.company_name ? p.company_name + ' · ' : '' }}{{ p.title }}
                        </option>
                    </select>
                </div>
                <div>
                    <label for="vacancy-status" class="sr-only">Situação</label>
                    <select
                        id="vacancy-status"
                        v-model="status"
                        class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    >
                        <option value="">Todas as situações</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Filtrar
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800"
                        @click="clearFilters"
                    >
                        Limpar
                    </button>
                </div>
            </form>
        </section>

        <section class="surface-card mt-5 overflow-hidden">
            <div v-if="vacancies.data.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Vaga</th>
                            <th scope="col" class="px-4 py-3">Empresa · processo</th>
                            <th scope="col" class="px-4 py-3">Situação</th>
                            <th scope="col" class="px-4 py-3 text-right">Candidatos</th>
                            <th scope="col" class="px-4 py-3 text-right">Contratados</th>
                            <th scope="col" class="px-4 py-3">Atualizada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="v in vacancies.data" :key="v.id" class="hover:bg-talents-50/30">
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.contratacao.vagas.show', v.id)"
                                    class="font-semibold text-talents-800 hover:text-talents-900 hover:underline"
                                >
                                    {{ v.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <p class="font-medium text-slate-800">{{ v.company?.name ?? '—' }}</p>
                                <p v-if="v.process" class="text-xs text-slate-500">
                                    {{ v.process.title }} · {{ v.process.stage_label }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="statusBadgeClass(v.status)"
                                >
                                    {{ v.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ v.applications_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ v.hired_count }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ formatDateTime(v.updated_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-talents-100 text-talents-700">
                    <BriefcaseIcon class="h-6 w-6" aria-hidden="true" />
                </div>
                <p class="mt-4 font-semibold text-talents-900">Nenhuma vaga encontrada</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">
                    {{ can.create ? 'Crie uma vaga num processo de acompanhamento.' : 'Ajuste os filtros para ver outras vagas.' }}
                </p>
            </div>
        </section>

        <div class="mt-4">
            <PaginationBar :paginator="vacancies" />
        </div>

        <Modal :show="showCreate" max-width="lg" @close="showCreate = false">
            <form class="p-6" @submit.prevent="submitCreate">
                <h3 class="text-lg font-semibold text-talents-900">Nova vaga</h3>
                <p class="mt-1 text-sm text-slate-600">
                    A vaga começa com as etapas Triagem, Entrevista, Finalistas e Contratado. Você pode editá-las depois.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="create-process" value="Processo de acompanhamento" />
                        <select id="create-process" v-model="createForm.hiring_process_id" :class="fieldClass" required>
                            <option value="" disabled>Selecione</option>
                            <option v-for="p in eligibleProcesses" :key="p.id" :value="String(p.id)">
                                {{ p.company_name ? p.company_name + ' · ' : '' }}{{ p.title }} ({{ p.stage_label }})
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Processos na fase Contratação não recebem vagas novas.</p>
                        <InputError class="mt-1" :message="createForm.errors.hiring_process_id" />
                    </div>
                    <div>
                        <InputLabel for="create-title" value="Título da vaga" />
                        <TextInput id="create-title" v-model="createForm.title" :class="fieldClass" maxlength="255" required />
                        <InputError class="mt-1" :message="createForm.errors.title" />
                    </div>
                    <div>
                        <InputLabel for="create-description" value="Descrição (opcional)" />
                        <textarea
                            id="create-description"
                            v-model="createForm.description"
                            rows="3"
                            maxlength="5000"
                            :class="fieldClass"
                        />
                        <InputError class="mt-1" :message="createForm.errors.description" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreate = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">Criar vaga</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>
