<script setup>
import PaginationBar from '@/Components/PaginationBar.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/utils/dateOnly';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    interviews: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const q = ref(props.filters?.q ?? '');
const status = ref(props.filters?.status ?? '');
const showingDeleted = computed(() => props.filters?.status === 'deleted');

const statusClass = (value) =>
    ({
        queued: 'bg-slate-100 text-slate-800',
        transcribing: 'bg-amber-100 text-amber-900',
        extracting: 'bg-blue-100 text-blue-900',
        completed: 'bg-emerald-100 text-emerald-900',
        failed: 'bg-red-100 text-red-900',
    })[value] ?? 'bg-slate-100 text-slate-800';

const applyFilters = () => {
    router.get(
        route('admin.entrevistas.index'),
        {
            q: q.value || undefined,
            status: status.value || undefined,
            hiring_application_id: props.filters?.hiring_application_id || undefined,
            talent_candidate_id: props.filters?.talent_candidate_id || undefined,
        },
        { preserveState: true, replace: true },
    );
};

const clearScope = () => {
    router.get(route('admin.entrevistas.index'), { q: q.value || undefined, status: status.value || undefined });
};

const restore = (item) => router.post(route('admin.entrevistas.restore', item.id), {}, { preserveScroll: true });

const createHref = computed(() =>
    props.filters?.hiring_application_id
        ? route('admin.entrevistas.create', { hiring_application_id: props.filters.hiring_application_id })
        : route('admin.entrevistas.create'),
);

let pollTimer = null;
onMounted(() => {
    if (props.interviews?.data?.some((r) => r.is_processing)) {
        pollTimer = setInterval(() => router.reload({ only: ['interviews'], preserveScroll: true }), 5000);
    }
});
onUnmounted(() => pollTimer && clearInterval(pollTimer));
</script>

<template>
    <Head title="Entrevistas" />

    <AdminLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-900">Entrevistas</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Entrevistas vinculadas aos candidatos, com transcrição por áudio, texto ou Plaud e revisão humana das respostas.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.entrevistas.roteiros.index')">
                        <PrimaryButton type="button" class="!bg-white !text-talents-800 ring-1 ring-talents-200">Roteiros</PrimaryButton>
                    </Link>
                    <Link v-if="can.create" :href="createHref">
                        <PrimaryButton>Nova entrevista</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>

        <div
            v-if="filters?.hiring_application_id || filters?.talent_candidate_id"
            class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-talents-200 bg-talents-50/60 px-4 py-2 text-sm text-talents-900"
        >
            <span>{{ filters?.hiring_application_id ? 'Mostrando as entrevistas de uma candidatura.' : 'Mostrando as entrevistas de um candidato.' }}</span>
            <button type="button" class="font-semibold underline" @click="clearScope">Ver todas</button>
        </div>

        <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <div>
                <label for="interview-q" class="text-xs font-medium text-gray-600">Buscar</label>
                <TextInput id="interview-q" v-model="q" class="mt-1 block w-56" placeholder="Candidato ou vaga" />
            </div>
            <div>
                <label for="interview-status" class="text-xs font-medium text-gray-600">Status</label>
                <select
                    id="interview-status"
                    v-model="status"
                    class="mt-1 block w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                >
                    <option value="">Todas (ativas)</option>
                    <option value="queued">Na fila</option>
                    <option value="transcribing">Transcrevendo</option>
                    <option value="extracting">Gerando relatório</option>
                    <option value="completed">Concluída</option>
                    <option value="failed">Falhou</option>
                    <option value="deleted">Excluídas</option>
                </select>
            </div>
            <PrimaryButton type="submit">Filtrar</PrimaryButton>
        </form>

        <div class="surface-card overflow-hidden">
            <div v-if="!interviews.data.length" class="px-4 py-10 text-center text-sm text-gray-600">
                {{ showingDeleted ? 'Nenhuma entrevista excluída.' : 'Nenhuma entrevista encontrada.' }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Candidato</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Vaga</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Roteiro</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Revisada</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">Data</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="item in interviews.data" :key="item.id">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ item.candidate_name }}
                                <span v-if="!item.is_linked" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">
                                    Sem vínculo
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ item.position_title || '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ item.questionnaire ? `${item.questionnaire.name} · v${item.questionnaire.version}` : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ item.is_reviewed ? 'Sim' : 'Não' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ formatDateTime(item.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-3">
                                    <Link :href="route('admin.entrevistas.show', item.id)" class="font-medium text-talents-700 hover:underline">
                                        Abrir
                                    </Link>
                                    <button
                                        v-if="item.deleted && can.delete"
                                        type="button"
                                        class="font-medium text-talents-700 hover:underline"
                                        @click="restore(item)"
                                    >
                                        Restaurar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationBar :paginator="interviews" />
        </div>
    </AdminLayout>
</template>
