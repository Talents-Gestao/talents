<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatCnpj } from '@/utils/formatCnpj';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';

const props = defineProps({
    companies: Object,
    filters: Object,
    rhidConfiguredIds: { type: Array, default: () => [] },
    pendingRegistrationIds: { type: Array, default: () => [] },
});

const rhidIdSet = new Set(props.rhidConfiguredIds.map((id) => Number(id)));
const pendingRegistrationIdSet = new Set(props.pendingRegistrationIds.map((id) => Number(id)));

const isRhidConfigured = (companyId) => rhidIdSet.has(Number(companyId));
const hasPendingRegistration = (companyId) => pendingRegistrationIdSet.has(Number(companyId));

const resendingId = ref(null);

const resendInvitation = async (company) => {
    if (resendingId.value || !hasPendingRegistration(company.id)) {
        return;
    }
    const email = company.contact_email || 'o e-mail de contato';
    if (!(await confirmDialog(`Reenviar o convite de cadastro para ${email}?`))) {
        return;
    }
    resendingId.value = company.id;
    router.post(route('admin.companies.resend-invitation', company.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            resendingId.value = null;
        },
    });
};

const form = useForm({
    search: props.filters?.search ?? '',
});

const submit = () => {
    form.get(route('admin.companies.index'), { preserveState: true });
};
</script>

<template>
    <Head title="Empresas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Empresas</h2>
                <Link
                    :href="route('admin.companies.create')"
                    class="rounded-md bg-talents-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-talents-700"
                >
                    Nova empresa
                </Link>
            </div>
        </template>

        <form class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center" @submit.prevent="submit">
            <TextInput v-model="form.search" class="w-full max-w-md" placeholder="Buscar por nome ou CNPJ" />
            <PrimaryButton type="submit" class="shrink-0 justify-center">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm text-slate-900">
                    <thead class="bg-slate-50">
                        <tr class="border-b border-slate-200">
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">
                                Nome
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">
                                CNPJ
                            </th>
                            <th
                                scope="col"
                                class="min-w-[12rem] max-w-[18rem] px-4 py-3 text-left font-medium text-slate-700"
                            >
                                Segmento
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">
                                Ativa
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">
                                RHID
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-medium text-slate-700">
                                Cadastro
                            </th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-700">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in companies.data"
                            :key="c.id"
                            class="border-b border-slate-100 last:border-b-0 hover:bg-slate-50/70"
                        >
                            <td class="max-w-[16rem] px-4 py-3 align-middle">
                                <p class="truncate font-medium text-slate-900" :title="c.name">
                                    {{ c.name }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span class="font-mono text-[13px] tabular-nums text-slate-700">
                                    {{ formatCnpj(c.cnpj) || '—' }}
                                </span>
                            </td>
                            <td class="min-w-[12rem] max-w-[18rem] px-4 py-3 align-middle">
                                <p class="truncate text-slate-600" :title="c.segment || undefined">
                                    {{ c.segment || '—' }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                    :class="
                                        c.is_active
                                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                                            : 'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                >
                                    {{ c.is_active ? 'Sim' : 'Não' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span
                                    v-if="isRhidConfigured(c.id)"
                                    class="inline-flex rounded-full bg-talents-100 px-2 py-0.5 text-[11px] font-semibold text-talents-800 ring-1 ring-talents-200"
                                >
                                    Configurado
                                </span>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle">
                                <span
                                    v-if="hasPendingRegistration(c.id)"
                                    class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200"
                                >
                                    Aguarda cadastro
                                </span>
                                <span
                                    v-else
                                    class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 ring-1 ring-slate-200"
                                >
                                    Concluído
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-right">
                                <div class="inline-flex items-center justify-end gap-3">
                                    <Link
                                        :href="route('admin.companies.show', c.id)"
                                        class="font-medium text-talents-700 hover:underline"
                                    >
                                        Ver
                                    </Link>
                                    <button
                                        v-if="hasPendingRegistration(c.id)"
                                        type="button"
                                        class="font-medium text-amber-700 hover:underline disabled:opacity-50"
                                        :disabled="resendingId === c.id"
                                        @click="resendInvitation(c)"
                                    >
                                        {{ resendingId === c.id ? 'Enviando…' : 'Reenviar convite' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <TableEmptyRow v-if="!companies.data.length" :colspan="7" message="Nenhuma empresa encontrada." />
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
