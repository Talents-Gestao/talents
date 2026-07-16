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
    columns: { type: Array, required: true },
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

const localColumns = ref(cloneColumns(props.columns));
watch(
    () => props.columns,
    (cols) => {
        localColumns.value = cloneColumns(cols);
    },
    { deep: true },
);

const searchQ = ref(props.filters.q ?? '');
const companyId = ref(props.filters.company_id ? String(props.filters.company_id) : '');
const movingId = ref(null);

const totalProcesses = computed(() =>
    Object.values(props.stage_counts || {}).reduce((sum, n) => sum + (Number(n) || 0), 0),
);

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

function cloneColumns(columns) {
    return JSON.parse(JSON.stringify(columns ?? []));
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
    document.getElementById(`stage-col-${stage}`)?.scrollIntoView({
        behavior: 'smooth',
        inline: 'center',
        block: 'nearest',
    });
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
    createForm.transform((data) => ({
        ...data,
        company_id: data.company_id,
        q: searchQ.value || undefined,
    })).post(route('admin.acompanhamento.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.current_stage = props.active_stage;
            showCreate.value = false;
        },
    });
};

const persistStage = (processId, stage) => {
    movingId.value = processId;
    router.patch(
        route('admin.acompanhamento.update', processId),
        {
            current_stage: stage,
            from_board: true,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                movingId.value = null;
            },
            onError: () => {
                localColumns.value = cloneColumns(props.columns);
            },
        },
    );
};

const onCardDragEnd = (_columnStage, evt) => {
    const cardEl = evt?.item;
    const processId = Number(cardEl?.dataset?.processId);
    if (!processId) {
        return;
    }

    const fromEl = evt?.from?.closest?.('[data-stage]');
    const toEl = evt?.to?.closest?.('[data-stage]');
    const fromStage = fromEl?.dataset?.stage;
    const toStage = toEl?.dataset?.stage;
    if (!toStage || fromStage === toStage) {
        return;
    }

    persistStage(processId, toStage);
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
                    Arraste os processos entre as fases. O trabalho operacional continua na Sólides.
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
                                {{ totalProcesses }} processo(s) · arraste os cards entre as colunas
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

                <div class="space-y-4 px-4 py-5 sm:px-6">
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

                    <div class="flex items-start gap-3 overflow-x-auto pb-2">
                        <div
                            v-for="col in localColumns"
                            :id="`stage-col-${col.value}`"
                            :key="col.value"
                            class="flex w-72 shrink-0 flex-col rounded-2xl bg-slate-100/80 p-2.5 ring-1 ring-slate-200/80"
                            :class="currentStage === col.value ? 'ring-2 ring-talents-300 bg-talents-50/50' : ''"
                        >
                            <header class="mb-2 flex items-start justify-between gap-2 px-1.5 pt-1">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-900" :title="col.label">
                                        {{ col.label }}
                                    </h3>
                                    <p class="mt-0.5 text-[11px] font-medium tabular-nums text-slate-500">
                                        {{ col.processes.length }} processo(s)
                                    </p>
                                </div>
                            </header>

                            <div
                                :data-stage="col.value"
                                class="relative flex min-h-[14rem] flex-1 flex-col"
                            >
                                <VueDraggable
                                    v-model="col.processes"
                                    group="acompanhamento-processes"
                                    item-key="id"
                                    class="flex min-h-[12rem] flex-1 flex-col gap-2 rounded-xl px-0.5 py-0.5"
                                    ghost-class="opacity-40"
                                    :animation="160"
                                    @end="(e) => onCardDragEnd(col.value, e)"
                                >
                                    <div
                                        v-for="p in col.processes"
                                        :key="p.id"
                                        :data-process-id="p.id"
                                        class="group cursor-grab rounded-xl bg-white p-3 text-left shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:ring-talents-300 active:cursor-grabbing"
                                        :class="movingId === p.id ? 'opacity-60' : ''"
                                    >
                                        <div class="flex items-start gap-2">
                                            <Bars3Icon class="mt-0.5 h-4 w-4 shrink-0 text-slate-300 group-hover:text-talents-500" />
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold leading-snug text-slate-900">{{ p.title }}</p>
                                                <p class="mt-1 flex items-center gap-1 text-[11px] text-slate-500">
                                                    <BuildingOffice2Icon class="h-3 w-3 text-talents-600" />
                                                    <span class="truncate">{{ p.company?.name ?? '—' }}</span>
                                                </p>
                                                <p v-if="p.notes" class="mt-2 line-clamp-2 text-[11px] text-slate-500">
                                                    {{ p.notes }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="mt-3 flex flex-wrap gap-1 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-white disabled:opacity-40"
                                                :disabled="!p.can_retreat"
                                                title="Recuar"
                                                @click.stop="retreat(p.id)"
                                            >
                                                <ArrowLeftIcon class="h-3 w-3" />
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg bg-talents-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-talents-700 disabled:opacity-40"
                                                :disabled="!p.can_advance"
                                                title="Avançar"
                                                @click.stop="advance(p.id)"
                                            >
                                                <ArrowRightIcon class="h-3 w-3" />
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-lg border border-red-100 bg-white px-2 py-1 text-[10px] font-semibold text-red-600 hover:bg-red-50"
                                                title="Remover"
                                                @click.stop="destroyProcess(p.id)"
                                            >
                                                <TrashIcon class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </div>
                                </VueDraggable>

                                <p
                                    v-if="!col.processes.length"
                                    class="pointer-events-none absolute inset-x-1 top-8 rounded-xl border border-dashed border-slate-300 px-2 py-10 text-center text-xs text-slate-400"
                                >
                                    Solte aqui
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
