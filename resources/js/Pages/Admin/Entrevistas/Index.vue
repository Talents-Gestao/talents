<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    interviews: Object,
    filters: Object,
});

const q = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? '');

const statusClass = (value) => {
    const map = {
        queued: 'bg-slate-100 text-slate-800',
        transcribing: 'bg-amber-100 text-amber-900',
        extracting: 'bg-blue-100 text-blue-900',
        completed: 'bg-emerald-100 text-emerald-900',
        failed: 'bg-red-100 text-red-900',
    };
    return map[value] ?? 'bg-slate-100 text-slate-800';
};

const applyFilters = () => {
    router.get(
        route('admin.entrevistas.index'),
        { q: q.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );
};

let pollTimer = null;

const startPollingIfNeeded = () => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (!props.interviews?.data?.some((r) => r.is_processing)) {
        return;
    }
    pollTimer = setInterval(() => {
        router.reload({ only: ['interviews'], preserveScroll: true });
    }, 5000);
};

onMounted(startPollingIfNeeded);
onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <Head title="Entrevistas (IA)" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Entrevistas (IA)</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Upload de gravações, transcrição automática e relatório estruturado por roteiro.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.entrevistas.roteiros.index')">
                        <PrimaryButton type="button" class="!bg-white !text-talents-800 ring-1 ring-talents-200">
                            Roteiros
                        </PrimaryButton>
                    </Link>
                    <Link :href="route('admin.entrevistas.create')">
                        <PrimaryButton>Nova entrevista</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>

        <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <div>
                <label class="text-xs font-medium text-gray-600">Buscar</label>
                <TextInput v-model="q" class="mt-1 block w-56" placeholder="Candidato ou vaga" />
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select
                    v-model="status"
                    class="mt-1 block w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Todos</option>
                    <option value="queued">Na fila</option>
                    <option value="transcribing">Transcrevendo</option>
                    <option value="extracting">Gerando relatório</option>
                    <option value="completed">Concluída</option>
                    <option value="failed">Falhou</option>
                </select>
            </div>
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div v-if="!interviews.data.length" class="px-4 py-10 text-center text-sm text-gray-600">
                Nenhuma entrevista encontrada.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Candidato</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Vaga</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Roteiro</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Data</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="item in interviews.data" :key="item.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ item.candidate_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ item.position_title || '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ item.questionnaire?.name || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusClass(item.status)"
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{
                                    item.created_at
                                        ? new Date(item.created_at).toLocaleString('pt-BR', {
                                              dateStyle: 'short',
                                              timeStyle: 'short',
                                          })
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('admin.entrevistas.show', item.id)"
                                    class="font-medium text-talents-700 hover:underline"
                                >
                                    Abrir
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
