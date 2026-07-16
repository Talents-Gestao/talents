<script setup>
import HiringProcessStepper from '@/Components/HiringProcessStepper.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    stages: { type: Array, required: true },
    active_stage: { type: String, required: true },
    stage_counts: { type: Object, required: true },
    processes: { type: Array, required: true },
    companies: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const currentStage = ref(props.active_stage);
watch(
    () => props.active_stage,
    (v) => {
        currentStage.value = v;
    },
);

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');

const activeStageLabel = computed(() => {
    const s = props.stages.find((x) => x.value === currentStage.value);
    return s?.label ?? '';
});

const navigate = (overrides = {}) => {
    const params = {
        stage: overrides.stage ?? currentStage.value,
        company_id: overrides.company_id !== undefined ? overrides.company_id : companyId.value || undefined,
        q: overrides.q !== undefined ? overrides.q : searchQ.value || undefined,
    };
    router.get(route('admin.acompanhamento.index'), params, {
        preserveState: true,
        replace: true,
    });
};

const onStageChange = (stage) => {
    currentStage.value = stage;
    navigate({ stage });
};

const applyFilters = () => {
    navigate({});
};

const showCreate = ref(false);
const createForm = useForm({
    company_id: '',
    title: '',
    current_stage: props.active_stage,
    notes: '',
});

const submitCreate = () => {
    createForm.post(route('admin.acompanhamento.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.current_stage = props.active_stage;
            showCreate.value = false;
        },
    });
};

const moveStage = (processId, stage) => {
    router.patch(
        route('admin.acompanhamento.update', processId),
        { current_stage: stage },
        { preserveScroll: true },
    );
};

const advance = (processId) => {
    router.post(route('admin.acompanhamento.advance', processId), {}, { preserveScroll: true });
};

const retreat = (processId) => {
    router.post(route('admin.acompanhamento.retreat', processId), {}, { preserveScroll: true });
};

const destroyProcess = (processId) => {
    if (!confirm('Remover este processo de acompanhamento?')) {
        return;
    }
    router.delete(route('admin.acompanhamento.destroy', processId), { preserveScroll: true });
};
</script>

<template>
    <Head title="Acompanhamento" />

    <AdminLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-talents-900">Acompanhamento</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Status visual das fases de contratação para as empresas. O processo operacional continua na Sólides.
                </p>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            {{ $page.props.flash.error }}
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-talents-200 bg-white p-4 shadow-sm sm:p-6">
                <HiringProcessStepper
                    :stages="stages"
                    :current-stage="currentStage"
                    :stage-counts="stage_counts"
                    @update:current-stage="onStageChange"
                />

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-talents-900">Processos</h3>
                        <p class="text-sm text-gray-600">
                            Fase atual: <strong>{{ activeStageLabel }}</strong>
                            — {{ processes.length }} processo(s)
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-lg border border-talents-300 bg-white px-3 py-2 text-sm font-semibold text-talents-800 hover:bg-talents-50"
                            @click="showCreate = !showCreate"
                        >
                            {{ showCreate ? 'Cancelar' : 'Novo processo' }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <TextInput
                        v-model="searchQ"
                        type="search"
                        class="w-full sm:flex-1"
                        placeholder="Busque por vaga ou empresa"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="companyId"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                    >
                        <option value="">Todas as empresas</option>
                        <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                    <PrimaryButton type="button" @click="applyFilters">Filtrar</PrimaryButton>
                </div>

                <div v-if="showCreate" class="mt-6 rounded-xl border border-talents-200 bg-talents-50/50 p-4">
                    <h4 class="font-semibold text-talents-900">Novo processo</h4>
                    <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitCreate">
                        <div>
                            <InputLabel for="create_company" value="Empresa" />
                            <select
                                id="create_company"
                                v-model="createForm.company_id"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                required
                            >
                                <option value="" disabled>Selecione</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="createForm.errors.company_id" />
                        </div>
                        <div>
                            <InputLabel for="create_title" value="Vaga / processo" />
                            <TextInput id="create_title" v-model="createForm.title" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="createForm.errors.title" />
                        </div>
                        <div>
                            <InputLabel for="create_stage" value="Fase inicial" />
                            <select
                                id="create_stage"
                                v-model="createForm.current_stage"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="create_notes" value="Notas (opcional)" />
                            <TextInput id="create_notes" v-model="createForm.notes" class="mt-1 block w-full" />
                        </div>
                        <div class="sm:col-span-2">
                            <PrimaryButton :disabled="createForm.processing">Criar</PrimaryButton>
                        </div>
                    </form>
                </div>

                <ul v-if="processes.length" class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <li
                        v-for="p in processes"
                        :key="p.id"
                        class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ p.title }}</p>
                            <p class="text-sm text-slate-600">
                                {{ p.company?.name ?? '—' }}
                                <span v-if="p.updated_by_name"> · atualizado por {{ p.updated_by_name }}</span>
                            </p>
                            <p v-if="p.notes" class="mt-1 text-sm text-slate-500">{{ p.notes }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                :value="p.current_stage"
                                @change="moveStage(p.id, $event.target.value)"
                            >
                                <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                                :disabled="!p.can_retreat"
                                @click="retreat(p.id)"
                            >
                                Recuar
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-talents-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-talents-700 disabled:opacity-40"
                                :disabled="!p.can_advance"
                                @click="advance(p.id)"
                            >
                                Avançar
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                @click="destroyProcess(p.id)"
                            >
                                Remover
                            </button>
                        </div>
                    </li>
                </ul>
                <div
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-talents-200 bg-talents-50/40 px-6 py-12 text-center"
                >
                    <p class="font-medium text-talents-900">Nenhum processo nesta fase</p>
                    <p class="mt-1 text-sm text-gray-600">Crie um processo ou avance processos de outras fases.</p>
                </div>
            </div>

            <p class="text-xs text-slate-500">
                <Link :href="route('admin.solides.curriculos.index')" class="text-talents-700 hover:underline">
                    Abrir Banco de talentos (Sólides)
                </Link>
            </p>
        </div>
    </AdminLayout>
</template>
