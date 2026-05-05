<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    configured: Boolean,
    filters: Object,
    page: Number,
    curricula: Array,
    pagination: Object,
    error: { type: String, default: null },
});

const filterForm = useForm({
    data_inicial: props.filters.data_inicial ?? '',
    data_final: props.filters.data_final ?? '',
});

const applyFilters = () => {
    filterForm
        .transform((data) => ({
            ...data,
            page: 1,
        }))
        .get(route('admin.solides.curriculos.index'), {
            preserveState: true,
            replace: true,
        });
};
</script>

<template>
    <Head title="Sólides — Currículos" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-900">Sólides — Currículos</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Lista sincronizada em tempo real com a API Sólides Gestão (
                    <code class="rounded bg-gray-100 px-1 text-xs">GET /curriculos</code>
                    ).
                </p>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <div v-if="!configured" class="surface-card max-w-2xl space-y-3 p-6 text-slate-900">
            <p class="text-sm text-gray-700">Configure o token da API Sólides em Configurações para carregar os currículos.</p>
            <Link
                :href="route('admin.settings.edit', { tab: 'solides' })"
                class="inline-flex text-sm font-medium text-talents-700 hover:underline"
            >
                Abrir Configurações → Sólides
            </Link>
        </div>

        <template v-else>
            <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ error }}
            </div>

            <form
                class="surface-card mb-6 max-w-4xl space-y-4 p-6 text-slate-900"
                @submit.prevent="applyFilters"
            >
                <h3 class="text-sm font-semibold text-gray-900">Filtros (opcional)</h3>
                <p class="text-xs text-gray-600">
                    Datas no formato <strong>dd/mm/aaaa</strong>, conforme documentação Sólides (
                    <code class="rounded bg-gray-100 px-1">data_inicial</code> /
                    <code class="rounded bg-gray-100 px-1">data_final</code>
                    ).
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="data_inicial" value="Data inicial" />
                        <TextInput
                            id="data_inicial"
                            v-model="filterForm.data_inicial"
                            class="mt-1 block w-full"
                            placeholder="ex.: 01/01/2024"
                            autocomplete="off"
                        />
                    </div>
                    <div>
                        <InputLabel for="data_final" value="Data final" />
                        <TextInput
                            id="data_final"
                            v-model="filterForm.data_final"
                            class="mt-1 block w-full"
                            placeholder="ex.: 31/12/2024"
                            autocomplete="off"
                        />
                    </div>
                </div>
                <PrimaryButton type="submit" :disabled="filterForm.processing">Aplicar filtros</PrimaryButton>
            </form>

            <div class="surface-card overflow-hidden">
                <div v-if="!curricula.length" class="px-4 py-10 text-center text-sm text-gray-600">
                    Nenhum currículo retornado nesta página.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-900">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">ID</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Nome</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">E-mail</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">CPF</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Celular</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Nascimento</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Senioridade</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Origem</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700">Cidade/UF</th>
                                <th class="whitespace-nowrap px-4 py-3 text-center font-medium text-gray-700">Exp.</th>
                                <th class="whitespace-nowrap px-4 py-3 text-center font-medium text-gray-700">Form.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="c in curricula" :key="c.id">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ c.id }}</td>
                                <td class="max-w-[12rem] px-4 py-3 font-medium">{{ c.fullName || '—' }}</td>
                                <td class="px-4 py-3">
                                    <a
                                        v-if="c.mainEmail"
                                        :href="'mailto:' + c.mainEmail"
                                        class="text-talents-700 hover:underline"
                                    >
                                        {{ c.mainEmail }}
                                    </a>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ c.idNumber || '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ c.mobile || c.phone || '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ c.birthDate || '—' }}</td>
                                <td class="max-w-[8rem] truncate px-4 py-3 text-gray-700" :title="c.seniority || ''">
                                    {{ c.seniority || '—' }}
                                </td>
                                <td class="max-w-[10rem] truncate px-4 py-3 text-gray-700" :title="c.origin || ''">
                                    {{ c.origin || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                    <span v-if="c.city || c.state">{{ [c.city, c.state].filter(Boolean).join(' / ') }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700">{{ c.experiences_count }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700">{{ c.education_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="pagination.has_prev || pagination.has_next"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 text-sm"
                >
                    <span class="text-gray-600">Página {{ pagination.current_page }}</span>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="pagination.prev_url"
                            :href="pagination.prev_url"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50"
                            preserve-scroll
                        >
                            Anterior
                        </Link>
                        <Link
                            v-if="pagination.next_url"
                            :href="pagination.next_url"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-50"
                            preserve-scroll
                        >
                            Próxima
                        </Link>
                    </div>
                </div>
            </div>
        </template>
    </AdminLayout>
</template>
