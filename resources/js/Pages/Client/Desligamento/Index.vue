<script setup>
import DesligamentoCompanyPicker from '@/Components/Desligamento/DesligamentoCompanyPicker.vue';
import DesligamentoLayout from '@/Components/Desligamento/DesligamentoLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import ListEmptyState from '@/Components/ListEmptyState.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { desligamentoRoute } from '@/composables/useDesligamentoRoutes';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { EyeIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    interviews: Object,
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
        desligamentoRoute('index'),
        {
            q: localFilters.q || undefined,
            status: localFilters.status || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const statusBadgeClass = (status) => {
    const map = {
        draft: 'bg-slate-100 text-slate-700 ring-slate-200',
        completed: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    };
    return map[status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
};

const formatDate = (iso) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');

const remove = (id) => {
    if (confirm('Remover esta pesquisa de desligamento?')) {
        router.delete(desligamentoRoute('destroy', id));
    }
};
</script>

<template>
    <Head title="Pesquisa de Desligamento" />

    <DesligamentoLayout>
        <template #header>
            <FormPageHeader
                title="Pesquisa de Desligamento"
                :subtitle="
                    activeCompany
                        ? `Empresa: ${activeCompany.name}`
                        : 'Roteiro preenchido presencialmente com o colaborador'
                "
            >
                <template v-if="!needsPicker" #trailing>
                    <Link :href="desligamentoRoute('create')">
                        <PrimaryButton type="button">Nova pesquisa</PrimaryButton>
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
            <DesligamentoCompanyPicker :companies="companyPicker || []" />
        </div>

        <template v-else>
            <DesligamentoCompanyPicker
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
                                <th class="px-5 py-3">Data</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in interviews.data"
                                :key="item.id"
                                class="transition hover:bg-talents-50/30"
                            >
                                <td class="px-5 py-3.5">
                                    <Link
                                        :href="desligamentoRoute('show', item.id)"
                                        class="font-medium text-talents-800 hover:text-talents-600"
                                    >
                                        {{ item.employee?.name ?? '—' }}
                                    </Link>
                                    <p class="text-xs text-slate-500">{{ item.employee?.email }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">{{ formatDate(item.interview_date) }}</td>
                                <td class="px-5 py-3.5">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                        :class="statusBadgeClass(item.status)"
                                    >
                                        {{ item.status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="desligamentoRoute('show', item.id)"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                            title="Ver"
                                        >
                                            <EyeIcon class="h-4 w-4" />
                                        </Link>
                                        <Link
                                            :href="desligamentoRoute('edit', item.id)"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-talents-50 hover:text-talents-700"
                                            title="Editar"
                                        >
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                            title="Excluir"
                                            @click="remove(item.id)"
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
                    v-if="!interviews.data?.length"
                    message="Nenhuma pesquisa cadastrada. Os colaboradores vêm do RHID (Control iD)."
                />
            </div>
        </template>
    </DesligamentoLayout>
</template>
