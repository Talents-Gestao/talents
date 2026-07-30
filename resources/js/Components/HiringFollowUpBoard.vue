<script setup>
import HiringProcessObservations from '@/Components/HiringProcessObservations.vue';
import HiringProcessStepper from '@/Components/HiringProcessStepper.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
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
    companies: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    can_create: { type: Boolean, default: true },
    can_manage: { type: Boolean, default: true },
    can_delete: { type: Boolean, default: true },
    show_company_filter: { type: Boolean, default: false },
    show_company_on_create: { type: Boolean, default: false },
    show_talent_bank_link: { type: Boolean, default: false },
    show_company_on_card: { type: Boolean, default: true },
    routes: { type: Object, required: true },
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
        q: overrides.q !== undefined ? overrides.q : searchQ.value || undefined,
    };
    if (props.show_company_filter) {
        params.company_id =
            overrides.company_id !== undefined ? overrides.company_id : companyId.value || undefined;
    }
    router.get(route(props.routes.index), params, {
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
    candidates_count: '',
});

watch(
    () => props.active_stage,
    (v) => {
        if (!showCreate.value) {
            createForm.current_stage = v;
        }
    },
);

const formatNotesAt = (iso) => {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('pt-BR', {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch {
        return '';
    }
};

const submitCreate = () => {
    createForm
        .transform((data) => {
            const payload = {
                title: data.title,
                current_stage: data.current_stage,
                notes: data.notes || null,
                candidates_count:
                    data.candidates_count === '' || data.candidates_count === null
                        ? null
                        : Number(data.candidates_count),
            };
            if (props.show_company_on_create) {
                payload.company_id = data.company_id;
            }
            return payload;
        })
        .post(route(props.routes.store), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                createForm.current_stage = props.active_stage;
                showCreate.value = false;
            },
            onFinish: () => {
                createForm.transform((data) => data);
            },
        });
};

const moveStage = (processId, stage) => {
    if (!props.can_manage) {
        return;
    }
    router.patch(
        route(props.routes.update, processId),
        { current_stage: stage },
        { preserveScroll: true },
    );
};

const onListDragEnd = (evt) => {
    if (!props.can_manage || evt?.oldIndex === evt?.newIndex) {
        return;
    }

    router.post(
        route(props.routes.reorder),
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
    if (!props.can_manage) {
        return;
    }
    router.post(route(props.routes.advance, processId), {}, { preserveScroll: true });
};

const retreat = (processId) => {
    if (!props.can_manage) {
        return;
    }
    router.post(route(props.routes.retreat, processId), {}, { preserveScroll: true });
};

const destroyProcess = (processId) => {
    if (!props.can_delete) {
        return;
    }
    if (!confirm('Remover este processo de acompanhamento?')) {
        return;
    }
    router.delete(route(props.routes.destroy, processId), { preserveScroll: true });
};

const commentsStoreUrl = (processId) => route(props.routes.comments_store, processId);
</script>

<template>
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
            <div class="border-b border-talents-100/80 px-4 py-5 sm:px-6 sm:py-6">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-talents-600">Funil de contratação</p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ totalProcesses }} processo(s) no total · fase selecionada:
                            <span class="font-semibold text-talents-800">{{ activeStageLabel }}</span>
                        </p>
                    </div>
                    <Link
                        v-if="show_talent_bank_link"
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

            <div class="space-y-5 px-4 py-6 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="searchQ"
                                type="search"
                                class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                                :placeholder="
                                    show_company_filter ? 'Busque por vaga ou empresa' : 'Busque por vaga'
                                "
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <select
                            v-if="show_company_filter"
                            v-model="companyId"
                            :class="fieldClass + ' sm:w-56'"
                            class="!mt-0"
                        >
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
                        v-if="can_create"
                        type="button"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-talents-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-talents-700"
                        @click="showCreate = !showCreate"
                    >
                        <PlusIcon class="h-4 w-4" />
                        {{ showCreate ? 'Cancelar' : 'Novo processo' }}
                    </button>
                </div>

                <div
                    v-if="can_create && showCreate"
                    class="rounded-2xl border border-talents-200 bg-talents-50/40 p-4 sm:p-5"
                >
                    <h4 class="text-sm font-semibold text-talents-900">Novo processo</h4>
                    <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitCreate">
                        <div v-if="show_company_on_create">
                            <InputLabel for="create_company" value="Empresa" />
                            <select id="create_company" v-model="createForm.company_id" :class="fieldClass" required>
                                <option value="" disabled>Selecione</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="createForm.errors.company_id" />
                        </div>
                        <div :class="show_company_on_create ? '' : 'sm:col-span-2'">
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
                            <InputLabel for="create_candidates" value="Nº de candidatos" />
                            <TextInput
                                id="create_candidates"
                                v-model="createForm.candidates_count"
                                type="number"
                                min="0"
                                step="1"
                                :class="fieldClass"
                                placeholder="Ex.: 12"
                            />
                            <InputError class="mt-1" :message="createForm.errors.candidates_count" />
                        </div>
                        <div class="sm:col-span-2">
                            <InputLabel for="create_notes" value="Notas (opcional)" />
                            <textarea
                                id="create_notes"
                                v-model="createForm.notes"
                                rows="2"
                                :class="fieldClass"
                                placeholder="Observações sobre a vaga…"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                A data e hora da nota são registradas automaticamente.
                            </p>
                            <InputError class="mt-1" :message="createForm.errors.notes" />
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
                    :disabled="!can_manage"
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
                                    v-if="can_manage"
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
                                        <span v-if="show_company_on_card" class="inline-flex items-center gap-1">
                                            <BuildingOffice2Icon class="h-3.5 w-3.5 text-talents-600" />
                                            {{ p.company?.name ?? '—' }}
                                        </span>
                                        <span v-if="show_company_on_card && p.updated_by_name" class="text-slate-400">·</span>
                                        <span v-if="p.updated_by_name">atualizado por {{ p.updated_by_name }}</span>
                                        <span
                                            v-if="p.candidates_count !== null && p.candidates_count !== undefined"
                                            class="inline-flex items-center rounded-full bg-talents-50 px-2 py-0.5 text-xs font-semibold text-talents-800"
                                        >
                                            {{ p.candidates_count }}
                                            {{ Number(p.candidates_count) === 1 ? 'candidato' : 'candidatos' }}
                                        </span>
                                    </p>
                                    <div
                                        v-if="p.notes"
                                        class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600"
                                    >
                                        <p class="whitespace-pre-wrap">{{ p.notes }}</p>
                                        <p v-if="p.notes_at" class="mt-1 text-xs font-medium text-slate-500">
                                            {{ formatNotesAt(p.notes_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="can_manage || can_delete" class="flex flex-wrap items-center gap-2 lg:justify-end">
                                <select
                                    v-if="can_manage"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                                    :value="p.current_stage"
                                    @change="moveStage(p.id, $event.target.value)"
                                >
                                    <option v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                                <button
                                    v-if="can_manage"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-40"
                                    :disabled="!p.can_retreat"
                                    @click="retreat(p.id)"
                                >
                                    <ArrowLeftIcon class="h-3.5 w-3.5" />
                                    Recuar
                                </button>
                                <button
                                    v-if="can_manage"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-xl bg-talents-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-talents-700 disabled:opacity-40"
                                    :disabled="!p.can_advance"
                                    @click="advance(p.id)"
                                >
                                    Avançar
                                    <ArrowRightIcon class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    v-if="can_delete"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-xl border border-red-100 bg-white px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                    @click="destroyProcess(p.id)"
                                >
                                    <TrashIcon class="h-3.5 w-3.5" />
                                    Remover
                                </button>
                            </div>
                        </div>

                        <HiringProcessObservations
                            :process-id="p.id"
                            :comments="p.comments ?? []"
                            :store-url="commentsStoreUrl(p.id)"
                        />
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
                        {{
                            can_create
                                ? 'Crie um processo ou avance itens de outras fases do funil.'
                                : 'Quando um processo for criado ou avançado, ele aparecerá aqui.'
                        }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
