<script setup>
import HiringProcessStepper from '@/Components/HiringProcessStepper.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    Bars3Icon,
    BuildingOffice2Icon,
    MagnifyingGlassIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

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

const localProcesses = ref(cloneProcesses(props.processes));
watch(
    () => props.processes,
    (list) => {
        localProcesses.value = cloneProcesses(list);
    },
    { deep: true },
);

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');

const activeStageLabel = computed(() => {
    const s = props.stages.find((x) => x.value === currentStage.value);
    return s?.label ?? '';
});

const totalProcesses = computed(() =>
    Object.values(props.stage_counts || {}).reduce((sum, n) => sum + (Number(n) || 0), 0),
);

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

function cloneProcesses(list) {
    return JSON.parse(JSON.stringify(list ?? []));
}
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

const onListDragEnd = (evt) => {
    if (evt?.oldIndex === evt?.newIndex) {
        return;
    }

    router.post(
        route('admin.acompanhamento.reorder'),
        {
            stage: currentStage.value,
            ordered_ids: localProcesses.value.map((p) => p.id),
        },
        {
            preserveScroll: true,
            onError: () => {
                localProcesses.value = cloneProcesses(props.processes);
            },
        },
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
                <p class="mt-1 text-sm text-slate-600">
                    Lista por fase — arraste para cima ou para baixo para reordenar. O processo operacional continua na Sólides.
                </p>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            {{ $page.props.flash.error }}
        </div>

        <div class="space-y-5">
            <section
                class="overflow-hidden rounded-2xl border border-talents-200/80 bg-gradient-to-b from-talents-50/90 via-white to-white shadow-sm"
            >
                <div class="border-b border-talents-100/80 px-4 py-4 sm:px-6 sm:py-5">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-talents-600">Funil de contratação</p>
                            <p class="mt-0.5 text-sm text-slate-600">
                                {{ totalProcesses }} processo(s) no total · fase selecionada:
                                <span class="font-semibold text-talents-800">{{ activeStageLabel }}</span>
                            </p>
                        </div>
                        <Link
                            :href="route('admin.solides.curriculos.index')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-talents-200 bg-white px-3 py-2 text-xs font-semibold text-talents-800 shadow-sm transition hover:bg-talents-50"
                        >
                            Banco de talentos
                        </Link>
                    </div>
                    <HiringProcessStepper
                        :stages="stages"
                        :current-stage="currentStage"
                        :stage-counts="stage_counts"
                        @update:current-stage="onStageChange"
                    />
                </div>

                <div class="space-y-5 px-4 py-5 sm:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row">
                            <div class="relative flex-1">
                                <MagnifyingGlassIcon
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    v-model="searchQ"
                                    type="search"
                                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                                    placeholder="Busque por vaga ou empresa"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                            <select v-model="companyId" :class="fieldClass + ' sm:w-56'" class="!mt-0">
                                <option value="">Todas as empresas</option>
                                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                @click="applyFilters"
                            >
                                Filtrar
                            </button>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                            @click="showCreate = !showCreate"
                        >
                            <PlusIcon class="h-4 w-4" />
                            {{ showCreate ? 'Cancelar' : 'Novo processo' }}
                        </button>
                    </div>

                    <div
                        v-if="showCreate"
                        class="rounded-2xl border border-talents-200 bg-talents-50/40 p-4 sm:p-5"
                    >
                        <h4 class="text-sm font-semibold text-talents-900">Novo processo</h4>
                        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitCreate">
                            <div>
                                <InputLabel for="create_company" value="Empresa" />
                                <select id="create_company" v-model="createForm.company_id" :class="fieldClass" required>
                                    <option value="" disabled>Selecione</option>
                                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <InputError class="mt-1" :message="createForm.errors.company_id" />
                            </div>
                            <div>
                                <InputLabel for="create_title" value="Vaga / processo" />
                                <TextInput id="create_title" v-model="createForm.title" :class="fieldClass" required />
                                <InputError class="mt-1" :message="createForm.errors.title" />
                            </div>
                            <div>
                                <InputLabel for="create_stage" value="Fase inicial" />
                                <select id="create_stage" v-model="createForm.current_stage" :class="fieldClass">
                                    <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="create_notes" value="Notas (opcional)" />
                                <TextInput id="create_notes" v-model="createForm.notes" :class="fieldClass" />
                            </div>
                            <div class="sm:col-span-2">
                                <PrimaryButton :disabled="createForm.processing">Criar processo</PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <div class="flex items-baseline justify-between gap-2">
                        <h3 class="text-base font-semibold text-talents-900">Processos nesta fase</h3>
                        <span class="text-xs font-medium tabular-nums text-slate-500">
                            {{ localProcesses.length }} resultado(s)
                        </span>
                    </div>

                    <VueDraggable
                        v-if="localProcesses.length"
                        v-model="localProcesses"
                        item-key="id"
                        handle=".drag-handle"
                        class="grid gap-3"
                        ghost-class="opacity-40"
                        :animation="160"
                        @end="onListDragEnd"
                    >
                        <div
                            v-for="p in localProcesses"
                            :key="p.id"
                            class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm transition hover:border-talents-200 hover:shadow-md sm:p-5"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="flex min-w-0 flex-1 gap-3">
                                    <button
                                        type="button"
                                        class="drag-handle mt-0.5 shrink-0 cursor-grab rounded-lg p-1 text-slate-300 transition hover:bg-slate-50 hover:text-talents-600 active:cursor-grabbing"
                                        title="Arrastar para reordenar"
                                        aria-label="Arrastar para reordenar"
                                    >
                                        <Bars3Icon class="h-5 w-5" />
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-base font-semibold text-slate-900">{{ p.title }}</p>
                                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                                            <span class="inline-flex items-center gap-1">
                                                <BuildingOffice2Icon class="h-3.5 w-3.5 text-talents-600" />
                                                {{ p.company?.name ?? '—' }}
                                            </span>
                                            <span v-if="p.updated_by_name" class="text-slate-400">·</span>
                                            <span v-if="p.updated_by_name">atualizado por {{ p.updated_by_name }}</span>
                                        </p>
                                        <p
                                            v-if="p.notes"
                                            class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600"
                                        >
                                            {{ p.notes }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <select
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                                        :value="p.current_stage"
                                        @change="moveStage(p.id, $event.target.value)"
                                    >
                                        <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                                    </select>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-40"
                                        :disabled="!p.can_retreat"
                                        @click="retreat(p.id)"
                                    >
                                        <ArrowLeftIcon class="h-3.5 w-3.5" />
                                        Recuar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-xl bg-talents-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-talents-700 disabled:opacity-40"
                                        :disabled="!p.can_advance"
                                        @click="advance(p.id)"
                                    >
                                        Avançar
                                        <ArrowRightIcon class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-xl border border-red-100 bg-white px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                        @click="destroyProcess(p.id)"
                                    >
                                        <TrashIcon class="h-3.5 w-3.5" />
                                        Remover
                                    </button>
                                </div>
                            </div>
                        </div>
                    </VueDraggable>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-talents-200 bg-gradient-to-b from-talents-50/50 to-white px-6 py-14 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-talents-100 text-talents-700"
                        >
                            <BuildingOffice2Icon class="h-6 w-6" />
                        </div>
                        <p class="mt-4 font-semibold text-talents-900">Nenhum processo nesta fase</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-600">
                            Crie um processo ou avance itens de outras fases do funil.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
