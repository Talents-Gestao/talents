<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormPageHeader from '@/Components/FormPageHeader.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    evaluations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const searchQ = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

const fieldClass =
    'rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const applyFilters = () => {
    router.get(
        route('admin.client-evaluations.index'),
        {
            q: searchQ.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const formatDate = (iso) => {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleDateString('pt-BR');
    } catch {
        return '—';
    }
};

const statusClass = (value) => {
    if (value === 'answered') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    }
    if (value === 'pending') {
        return 'bg-amber-50 text-amber-800 ring-amber-200';
    }
    return 'bg-slate-100 text-slate-600 ring-slate-200';
};

const remove = async (item) => {
    const confirmed = await confirmDialog(
        `Excluir a avaliação de ${item.company_name}?\n\nO link público deixa de funcionar e a resposta, se existir, é removida.`,
    );
    if (!confirmed) {
        return;
    }
    router.delete(route('admin.client-evaluations.destroy', item.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Avaliações de clientes" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.companies.index')"
                back-label="Clientes"
                title="Avaliações de clientes"
                subtitle="Avaliações com link único para o cliente avaliar o trabalho da Talents"
            >
                <template #trailing>
                    <Link :href="route('admin.client-evaluations.create')">
                        <PrimaryButton type="button">Nova avaliação</PrimaryButton>
                    </Link>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ $page.props.flash.error }}
        </div>

        <form class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" @submit.prevent="applyFilters">
            <div class="relative min-w-0 flex-1">
                <MagnifyingGlassIcon
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                />
                <input
                    v-model="searchQ"
                    type="search"
                    placeholder="Empresa ou contato"
                    class="w-full pl-9"
                    :class="fieldClass"
                />
            </div>
            <select v-model="status" :class="fieldClass" @change="applyFilters">
                <option value="">Todos os status</option>
                <option v-for="item in statuses" :key="item.value" :value="item.value">
                    {{ item.label }}
                </option>
            </select>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div v-if="evaluations.data.length === 0" class="px-6 py-12 text-center text-sm text-slate-500">
                Nenhuma avaliação ainda. Gere o primeiro link para um cliente.
            </div>
            <table v-else class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Empresa</th>
                        <th class="px-4 py-3">Ciclo</th>
                        <th class="px-4 py-3">Contato</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">NPS</th>
                        <th class="px-4 py-3">Validade</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in evaluations.data" :key="item.id" class="hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <Link
                                :href="route('admin.client-evaluations.show', item.id)"
                                class="text-talents-700 hover:underline"
                            >
                                {{ item.company_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ item.cycle_label }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            <div>{{ item.respondent_name }}</div>
                            <div class="text-xs text-slate-500">{{ item.respondent_email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                                :class="statusClass(item.status)"
                            >
                                {{ item.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            <span v-if="item.nps_score !== null">{{ item.nps_score }} · {{ item.nps_band }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDate(item.expires_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                title="Excluir"
                                @click="remove(item)"
                            >
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <PaginationBar :paginator="evaluations" />
        </div>
    </AdminLayout>
</template>
