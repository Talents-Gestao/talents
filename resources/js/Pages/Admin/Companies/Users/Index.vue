<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    company: Object,
    users: Array,
});

const resendingId = ref(null);

const roleLabel = (role) => {
    const map = {
        company_admin: 'Administrador da empresa',
        company_user: 'Usuário da empresa',
        super_admin: 'Administrador Talents',
    };
    const key = String(role || '').toLowerCase();

    return map[key] ?? role ?? '—';
};

const remove = (userId) => {
    if (confirm('Remover este utilizador?')) {
        router.delete(route('admin.companies.users.destroy', [props.company.id, userId]));
    }
};

const resendInvitation = (user) => {
    if (resendingId.value) {
        return;
    }
    const message = user.pending_registration
        ? `Reenviar o link de cadastro para ${user.email}?`
        : `Enviar link para redefinir a senha para ${user.email}?`;
    if (!confirm(message)) {
        return;
    }
    resendingId.value = user.id;
    router.post(route('admin.companies.users.resend-invitation', [props.company.id, user.id]), {}, {
        preserveScroll: true,
        onFinish: () => {
            resendingId.value = null;
        },
    });
};
</script>

<template>
    <Head :title="'Utilizadores — ' + company.name" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <Link :href="route('admin.companies.show', company.id)" class="text-sm font-medium text-talents-700 hover:underline">
                        ← Empresa
                    </Link>
                    <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-900">Utilizadores</h2>
                    <p class="text-sm text-gray-600">{{ company.name }}</p>
                </div>
                <Link :href="route('admin.companies.users.create', company.id)">
                    <PrimaryButton>Novo utilizador</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Nome</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">E-mail</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Papel</th>
                        <th class="px-4 py-2 text-left font-semibold text-slate-700">Estado</th>
                        <th class="px-4 py-2 text-right font-semibold text-slate-700">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="u in users" :key="u.id">
                        <td class="px-4 py-2 font-medium text-slate-900">{{ u.name }}</td>
                        <td class="px-4 py-2 text-slate-700">{{ u.email }}</td>
                        <td class="px-4 py-2 text-slate-700">{{ roleLabel(u.role) }}</td>
                        <td class="px-4 py-2">
                            <span v-if="u.pending_registration" class="text-amber-700">Aguarda cadastro</span>
                            <span v-else :class="u.is_active ? 'text-emerald-700' : 'text-red-600'">
                                {{ u.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <button
                                type="button"
                                class="font-medium hover:underline disabled:opacity-50"
                                :class="u.pending_registration ? 'text-amber-700' : 'text-talents-700'"
                                :disabled="resendingId === u.id"
                                @click="resendInvitation(u)"
                            >
                                {{
                                    resendingId === u.id
                                        ? 'Enviando…'
                                        : u.pending_registration
                                          ? 'Reenviar convite'
                                          : 'Redefinir senha'
                                }}
                            </button>
                            <Link
                                :href="route('admin.companies.users.edit', [company.id, u.id])"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                Editar
                            </Link>
                            <button type="button" class="font-medium text-red-600 hover:underline" @click="remove(u.id)">
                                Remover
                            </button>
                        </td>
                    </tr>
                    <TableEmptyRow v-if="!users.length" :colspan="5" message="Nenhum utilizador encontrado." />
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
