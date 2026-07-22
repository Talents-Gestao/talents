<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const resendInvitation = (company) => {
    if (resendingId.value || !hasPendingRegistration(company.id)) {
        return;
    }
    const email = company.contact_email || 'o e-mail de contacto';
    if (!confirm(`Reenviar o convite de cadastro para ${email}?`)) {
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

        <form class="mb-6 flex gap-2" @submit.prevent="submit">
            <TextInput v-model="form.search" class="w-full max-w-md" placeholder="Buscar por nome ou CNPJ" />
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">CNPJ</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Segmento</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Ativa</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">RHID</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Cadastro</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="c in companies.data" :key="c.id">
                            <td class="px-4 py-3">{{ c.name }}</td>
                            <td class="px-4 py-3">{{ c.cnpj || '—' }}</td>
                            <td class="px-4 py-3">{{ c.segment || '—' }}</td>
                            <td class="px-4 py-3">{{ c.is_active ? 'Sim' : 'Não' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="isRhidConfigured(c.id)"
                                    class="inline-flex rounded-full bg-talents-100 px-2 py-0.5 text-[11px] font-semibold text-talents-800 ring-1 ring-talents-200"
                                >
                                    Configurado
                                </span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="hasPendingRegistration(c.id)" class="text-xs font-medium text-amber-700">Aguarda cadastro</span>
                                <span v-else class="text-xs text-gray-400">Concluído</span>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <button
                                    v-if="hasPendingRegistration(c.id)"
                                    type="button"
                                    class="text-sm font-medium text-amber-700 hover:underline disabled:opacity-50"
                                    :disabled="resendingId === c.id"
                                    @click="resendInvitation(c)"
                                >
                                    {{ resendingId === c.id ? 'Enviando…' : 'Reenviar convite' }}
                                </button>
                                <Link :href="route('admin.companies.show', c.id)" class="font-medium text-talents-700 hover:underline">Ver</Link>
                            </td>
                        </tr>
                        <TableEmptyRow v-if="!companies.data.length" :colspan="7" message="Nenhuma empresa encontrada." />
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
