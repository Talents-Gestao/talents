<script setup>
import FeriasCompanyPicker from '@/Components/Ferias/FeriasCompanyPicker.vue';
import FeriasLayout from '@/Components/Ferias/FeriasLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import ListEmptyState from '@/Components/ListEmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { feriasRoute } from '@/composables/useFeriasRoutes';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    leaves: Object,
    companyPicker: { type: Array, default: null },
    activeCompany: { type: Object, default: null },
    isAdminContext: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({ q: '', status: '' }) },
    statusOptions: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const needsPicker = computed(() => props.isAdminContext && !props.activeCompany);

const localFilters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

watch(
    () => props.filters,
    (value) => {
        localFilters.q = value?.q ?? '';
        localFilters.status = value?.status ?? '';
    },
    { deep: true },
);

const applyFilters = () => {
    router.get(
        feriasRoute('index'),
        {
            q: localFilters.q || undefined,
            status: localFilters.status || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const statusBadgeClass = (status) => {
    const map = {
        scheduled: 'bg-sky-50 text-sky-800 ring-sky-200',
        in_progress: 'bg-amber-50 text-amber-800 ring-amber-200',
        completed: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        cancelled: 'bg-slate-100 text-slate-600 ring-slate-200',
    };

    return map[status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
};

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');

const remove = (id) => {
    if (confirm('Remover este período de férias?')) {
        router.delete(feriasRoute('destroy', id));
    }
};
</script>

<template>
    <Head title="Férias" />

    <FeriasLayout>
        <template #header>
            <FormPageHeader
                title="Férias"
                :subtitle="activeCompany ? `Empresa: ${activeCompany.name}` : 'Gestão de períodos de férias dos colaboradores'"
            >
                <template v-if="!needsPicker" #trailing>
                    <Link :href="feriasRoute('create')">
                        <PrimaryButton type="button">Novo período</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="flashSuccess"
            class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-900"
        >
            {{ flashSuccess }}
        </div>

        <div v-if="needsPicker" class="mx-auto max-w-xl">
            <FeriasCompanyPicker :companies="companyPicker || []" />
        </div>

        <template v-else>
            <FeriasCompanyPicker
                v-if="isAdminContext && companyPicker?.length"
                class="mb-6"
                compact
                :companies="companyPicker"
                :active-company-id="activeCompany?.id"
            />

            <form
                class="mb-6 grid gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:grid-cols-[1fr_12rem_auto]"
                @submit.prevent="applyFilters"
            >
                <input
                    v-model="localFilters.q"
                    type="search"
                    placeholder="Buscar colaborador"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
                />
                <select
                    v-model="localFilters.status"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/60"
                >
                    <option value="">Todos os status</option>
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <PrimaryButton type="submit">Filtrar</PrimaryButton>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Colaborador</th>
                                <th class="px-5 py-3">Início</th>
                                <th class="px-5 py-3">Fim</th>
                                <th class="px-5 py-3">Dias</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="leave in leaves.data" :key="leave.id" class="transition hover:bg-talents-50/30">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-talents-900">{{ leave.employee?.name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ leave.employee?.email }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">{{ formatDate(leave.start_date) }}</td>
                                <td class="px-5 py-3.5 text-slate-700">{{ formatDate(leave.end_date) }}</td>
                                <td class="px-5 py-3.5 text-slate-700">{{ leave.days }}</td>
                                <td class="px-5 py-3.5">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                        :class="statusBadgeClass(leave.status)"
                                    >
                                        {{ leave.status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="feriasRoute('edit', leave.id)"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                            title="Editar"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                            title="Excluir"
                                            @click="remove(leave.id)"
                                        >
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ListEmptyState
                    v-if="!leaves.data?.length"
                    message="Nenhum período de férias cadastrado. Cadastre colaboradores em Feedbacks internos, se ainda não existirem."
                />
            </div>
        </template>
    </FeriasLayout>
</template>
