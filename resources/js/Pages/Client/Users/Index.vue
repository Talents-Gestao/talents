<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    users: Array,
});

const remove = (id) => {
    if (confirm('Remover este utilizador?')) {
        router.delete(route('client.usuarios.destroy', id));
    }
};
</script>

<template>
    <Head title="Utilizadores" />

    <ClientLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Utilizadores</h2>
                <Link :href="route('client.usuarios.create')">
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
                        <td class="px-4 py-2 text-slate-700">{{ u.role }}</td>
                        <td class="px-4 py-2">
                            <span :class="u.is_active ? 'text-emerald-700' : 'text-red-600'">
                                {{ u.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <Link
                                v-if="u.role === 'company_user'"
                                :href="route('client.usuarios.edit', u.id)"
                                class="font-medium text-talents-700 hover:underline"
                            >
                                Editar
                            </Link>
                            <button
                                v-if="u.role === 'company_user'"
                                type="button"
                                class="font-medium text-red-600 hover:underline"
                                @click="remove(u.id)"
                            >
                                Remover
                            </button>
                            <span v-if="u.role === 'company_admin'" class="text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ClientLayout>
</template>
