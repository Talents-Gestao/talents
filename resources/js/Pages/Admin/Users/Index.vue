<script setup>
import TableEmptyRow from '@/Components/TableEmptyRow.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useAdminPermissions } from '@/composables/useAdminPermissions';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Array,
});

const { canAdmin } = useAdminPermissions();
const page = usePage();

const resendingId = ref(null);

const remove = (user) => {
    if (user.is_owner || user.id === page.props.auth?.user?.id) {
        return;
    }
    if (confirm('Remover este utilizador?')) {
        router.delete(route('admin.users.destroy', user.id));
    }
};

function functionLabel(user) {
    if (user.is_owner) {
        return { text: 'Proprietário', class: 'bg-violet-100 text-violet-800' };
    }
    if (user.has_all_admin_permissions) {
        return { text: 'Administrador', class: 'bg-talents-100 text-talents-800' };
    }
    return { text: 'Equipe', class: 'bg-slate-100 text-slate-700' };
}

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
    router.post(route('admin.users.resend-invitation', user.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            resendingId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Equipe — Administradores" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-900">Equipe Talents</h2>
                    <p class="text-sm text-gray-600">Super administradores da plataforma</p>
                </div>
                <Link v-if="canAdmin('equipe', 'create')" :href="route('admin.users.create')">
                    <PrimaryButton>Novo administrador</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="surface-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Nome</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">E-mail</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Função</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Comercial</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Estado</th>
                            <th class="px-4 py-2 text-right font-semibold text-slate-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="u in users" :key="u.id">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ u.name }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ u.email }}</td>
                            <td class="px-4 py-2 text-slate-700">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="functionLabel(u).class"
                                >
                                    {{ functionLabel(u).text }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-700">
                                {{ u.is_commercial ? 'Sim' : 'Não' }}
                            </td>
                            <td class="px-4 py-2">
                                <span v-if="u.pending_registration" class="text-amber-700">Aguarda cadastro</span>
                                <span v-else :class="u.is_active ? 'text-emerald-700' : 'text-red-600'">
                                    {{ u.is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="space-x-3 px-4 py-2 text-right">
                                <button
                                    v-if="canAdmin('equipe', 'edit')"
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
                                    v-if="canAdmin('equipe', 'edit')"
                                    :href="route('admin.users.edit', u.id)"
                                    class="font-medium text-talents-700 hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    v-if="canAdmin('equipe', 'delete') && !u.is_owner && u.id !== page.props.auth?.user?.id"
                                    type="button"
                                    class="font-medium text-red-600 hover:underline"
                                    @click="remove(u)"
                                >
                                    Remover
                                </button>
                            </td>
                        </tr>
                        <TableEmptyRow v-if="!users.length" :colspan="6" message="Nenhum administrador encontrado." />
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
