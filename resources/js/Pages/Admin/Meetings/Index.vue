<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    meetings: Object,
    filters: Object,
});

const q = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? '');

const statusClass = (value) => {
    const map = {
        draft: 'bg-slate-100 text-slate-800',
        queued: 'bg-slate-100 text-slate-800',
        transcribing: 'bg-amber-100 text-amber-900',
        generating_minutes: 'bg-blue-100 text-blue-900',
        completed: 'bg-emerald-100 text-emerald-900',
        failed: 'bg-red-100 text-red-900',
    };
    return map[value] ?? 'bg-slate-100 text-slate-800';
};

const applyFilters = () => {
    router.get(
        route('admin.reunioes.index'),
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
    if (!props.meetings?.data?.some((r) => r.is_processing)) {
        return;
    }
    pollTimer = setInterval(() => {
        router.reload({ only: ['meetings'], preserveScroll: true });
    }, 5000);
};

onMounted(startPollingIfNeeded);
watch(
    () => props.meetings?.data,
    () => startPollingIfNeeded(),
    { deep: true },
);
onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <Head title="Reuniões" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Reuniões</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Grave alinhamentos na página, gere a ata automaticamente e revise quando precisar.
                    </p>
                </div>
                <Link :href="route('admin.reunioes.create')">
                    <PrimaryButton>Nova reunião</PrimaryButton>
                </Link>
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
                <TextInput v-model="q" class="mt-1 block w-56" placeholder="Título ou participantes" />
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Status</label>
                <select
                    v-model="status"
                    class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Todos</option>
                    <option value="draft">Rascunho</option>
                    <option value="queued">Na fila</option>
                    <option value="transcribing">Transcrevendo</option>
                    <option value="generating_minutes">Gerando ata</option>
                    <option value="completed">Concluída</option>
                    <option value="failed">Falhou</option>
                </select>
            </div>
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div v-if="!meetings.data.length" class="px-4 py-10 text-center text-sm text-gray-600">
                Nenhuma reunião encontrada.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Título</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Empresa</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Data</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="item in meetings.data" :key="item.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ item.title }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ item.company?.name || '—' }}</td>
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
                                    :href="route('admin.reunioes.show', item.id)"
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
