<script setup>
import CompanySearchSelect from '@/Components/CompanySearchSelect.vue';
import FollowupSendModal from '@/Components/Hiring/FollowupSendModal.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { followupStatusClass } from '@/utils/hiringFollowup';
import { Head, Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    cycles: { type: Object, required: true },
    statuses: { type: Array, required: true },
    companies: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
    emails_remaining_today: { type: Number, default: 0 },
});

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');
const status = ref(props.filters.status ?? '');

const applyFilters = () => {
    router.get(
        route('admin.contratacao.pos.index'),
        { q: searchQ.value || undefined, company_id: companyId.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );
};

const sending = ref(null);
const fmtDate = (d) => (d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR') : '—');
</script>

<template>
    <Head title="Pós-contratação" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Pós-contratação</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Acompanhamento D15, D30, D60 e D90 de quem foi contratado. Nada é enviado sozinho: marco vencido pede revisão.
                </p>
            </div>
        </template>

        <div v-if="$page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ $page.props.flash.success }}
        </div>

        <section class="surface-card p-4 sm:p-5">
            <form class="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto]" @submit.prevent="applyFilters">
                <div class="relative">
                    <label for="followup-q" class="sr-only">Buscar</label>
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        id="followup-q"
                        v-model="searchQ"
                        type="search"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                        placeholder="Colaborador, empresa ou cargo…"
                    />
                </div>
                <div>
                    <label for="followup-company" class="sr-only">Empresa</label>
                    <CompanySearchSelect
                        id="followup-company"
                        v-model="companyId"
                        :companies="companies"
                        as-string
                        empty-label="Todas as empresas"
                        input-class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                    />
                </div>
                <div>
                    <label for="followup-status" class="sr-only">Status</label>
                    <select id="followup-status" v-model="status" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-sm">
                        <option value="">Todos os status</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Filtrar</button>
            </form>
        </section>

        <section class="surface-card mt-5 overflow-hidden">
            <div v-if="cycles.data.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Colaborador</th>
                            <th scope="col" class="px-4 py-3">Empresa</th>
                            <th scope="col" class="px-4 py-3">Cargo</th>
                            <th scope="col" class="px-4 py-3">Gestor</th>
                            <th scope="col" class="px-4 py-3">Admissão</th>
                            <th scope="col" class="px-4 py-3">Próximo marco</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="c in cycles.data" :key="c.id" class="hover:bg-talents-50/30">
                            <td class="px-4 py-3">
                                <Link :href="c.url" class="font-semibold text-talents-800 hover:underline">{{ c.employee_name }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ c.company?.name }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ c.role_title || '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="c.manager_name" class="font-medium text-slate-800">{{ c.manager_name }}</span>
                                <span v-else class="text-amber-800">Não definido</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ fmtDate(c.admission_date) }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <template v-if="c.next_milestone">
                                    {{ c.next_milestone.moment_label }} · {{ fmtDate(c.next_milestone.due_on) }}
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="followupStatusClass(c.status)">{{ c.status_label }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    v-if="can.edit && c.next_milestone && ['due', 'overdue', 'scheduled'].includes(c.status)"
                                    type="button"
                                    class="whitespace-nowrap rounded-lg bg-talents-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-talents-700"
                                    @click="sending = c"
                                >
                                    Enviar acompanhamento
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="px-6 py-14 text-center text-sm text-slate-500">Nenhum acompanhamento encontrado.</p>
        </section>

        <div class="mt-4"><PaginationBar :paginator="cycles" /></div>

        <FollowupSendModal
            :show="sending !== null"
            :milestone="sending?.next_milestone ?? null"
            :employee-name="sending?.employee_name ?? ''"
            :emails-remaining="emails_remaining_today"
            @close="sending = null"
        />
    </AdminLayout>
</template>
