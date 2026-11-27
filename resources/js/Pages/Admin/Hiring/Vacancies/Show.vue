<script setup>
import FormPageHeader from '@/Components/FormPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import CommunicationHistory from '@/Components/Hiring/CommunicationHistory.vue';
import StageCommunicationModal from '@/Components/Hiring/StageCommunicationModal.vue';
import AdmissionModal from '@/Components/Hiring/AdmissionModal.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirmDialog } from '@/composables/useConfirmDialog';
import { formatDateTime } from '@/utils/dateOnly';
import {
    describeApplicationEvent,
    groupApplicationsByStage,
    shiftItem,
    stageDeleteBlockReason,
} from '@/utils/hiringVacancyBoard';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    Bars3Icon,
    CheckBadgeIcon,
    ClockIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
    UserMinusIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    vacancy: { type: Object, required: true },
    stages: { type: Array, required: true },
    applications: { type: Array, required: true },
    statuses: { type: Array, required: true },
    can: { type: Object, default: () => ({}) },
    communications: { type: Array, default: () => [] },
    communication: { type: Object, default: null },
});

const showCommunicate = ref(false);
const admitting = ref(null);

const fieldClass =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';
const smallSelectClass =
    'block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800 focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70';

const routeParams = (extra = {}) => ({ vacancy: props.vacancy.id, ...extra });

/* ── Quadro ── */
const columns = ref(groupApplicationsByStage(props.stages, props.applications));
watch(
    () => [props.stages, props.applications],
    () => {
        columns.value = groupApplicationsByStage(props.stages, props.applications);
    },
    { deep: true },
);

const resetColumns = () => {
    columns.value = groupApplicationsByStage(props.stages, props.applications);
};

const moveApplication = (applicationId, stageId, position = null) => {
    router.patch(
        route('admin.contratacao.vagas.applications.move', routeParams({ application: applicationId })),
        { stage_id: stageId, position },
        { preserveScroll: true, onError: resetColumns },
    );
};

const onDragEnd = (evt) => {
    const applicationId = Number(evt.item?.dataset?.applicationId);
    const toStageId = Number(evt.to?.dataset?.stageId);
    if (!applicationId || !toStageId) {
        return;
    }
    if (evt.from === evt.to && evt.oldIndex === evt.newIndex) {
        return;
    }
    moveApplication(applicationId, toStageId, evt.newIndex ?? null);
};

const onSelectStage = (application, event) => {
    const stageId = Number(event.target.value);
    if (stageId && stageId !== application.stage_id) {
        moveApplication(application.id, stageId, null);
    }
};

const removeApplication = async (application) => {
    const ok = await confirmDialog({
        title: 'Retirar candidato da vaga?',
        message: `${application.candidate.name} sai desta vaga e o histórico dela é descartado. O cadastro do candidato continua.`,
        confirmLabel: 'Retirar',
    });
    if (!ok) {
        return;
    }
    router.delete(route('admin.contratacao.vagas.applications.destroy', routeParams({ application: application.id })), {
        preserveScroll: true,
    });
};

const statusLabel = computed(() => props.statuses.find((s) => s.value === props.vacancy.status)?.label ?? '');
const totalApplications = computed(() => props.applications.length);

/* ── Situação e dados da vaga ── */
const onStatusChange = (event) => {
    router.patch(
        route('admin.contratacao.vagas.update', routeParams()),
        { status: event.target.value },
        { preserveScroll: true },
    );
};

const showEdit = ref(false);
const editForm = useForm({ title: props.vacancy.title, description: props.vacancy.description ?? '' });
const openEdit = () => {
    editForm.title = props.vacancy.title;
    editForm.description = props.vacancy.description ?? '';
    editForm.clearErrors();
    showEdit.value = true;
};
const submitEdit = () => {
    editForm.patch(route('admin.contratacao.vagas.update', routeParams()), {
        preserveScroll: true,
        onSuccess: () => {
            showEdit.value = false;
        },
    });
};

/* ── Incluir candidato ── */
const showAdd = ref(false);
const addMode = ref('search');
const searchTerm = ref('');
const searchResults = ref([]);
const searching = ref(false);
const searchError = ref(null);
const addStageId = ref('');
let searchTimer = null;
let searchSeq = 0;

const quickForm = useForm({ name: '', email: '', phone: '', stage_id: '' });
const pickForm = useForm({ talent_candidate_id: null, stage_id: '' });

const runSearch = async () => {
    const seq = ++searchSeq;
    searching.value = true;
    searchError.value = null;
    try {
        const { data } = await window.axios.get(
            route('admin.contratacao.vagas.candidates.available', routeParams()),
            { params: { q: searchTerm.value || undefined } },
        );
        if (seq === searchSeq) {
            searchResults.value = data?.data ?? [];
        }
    } catch {
        if (seq === searchSeq) {
            searchError.value = 'Não foi possível buscar candidatos.';
        }
    } finally {
        if (seq === searchSeq) {
            searching.value = false;
        }
    }
};

watch(searchTerm, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 300);
});

const openAdd = () => {
    addMode.value = 'search';
    searchTerm.value = '';
    searchResults.value = [];
    addStageId.value = props.stages[0] ? String(props.stages[0].id) : '';
    quickForm.reset();
    quickForm.clearErrors();
    pickForm.clearErrors();
    showAdd.value = true;
    runSearch();
};

const pickCandidate = (candidate) => {
    pickForm.talent_candidate_id = candidate.id;
    pickForm.stage_id = addStageId.value || null;
    pickForm.post(route('admin.contratacao.vagas.applications.store', routeParams()), {
        preserveScroll: true,
        onSuccess: () => {
            searchResults.value = searchResults.value.filter((c) => c.id !== candidate.id);
            showAdd.value = false;
        },
    });
};

const submitQuick = () => {
    quickForm.stage_id = addStageId.value || null;
    quickForm.post(route('admin.contratacao.vagas.applications.store', routeParams()), {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
        },
    });
};

/* ── Histórico e anotação ── */
const historyApplicationId = ref(null);
const historyApplication = computed(
    () => props.applications.find((a) => a.id === historyApplicationId.value) ?? null,
);
const noteForm = useForm({ note: '' });
const openHistory = (application) => {
    historyApplicationId.value = application.id;
    noteForm.reset();
    noteForm.clearErrors();
};
const closeHistory = () => {
    historyApplicationId.value = null;
};
const submitNote = () => {
    if (!historyApplication.value) {
        return;
    }
    noteForm.post(
        route('admin.contratacao.vagas.applications.notes.store', routeParams({ application: historyApplication.value.id })),
        {
            preserveScroll: true,
            onSuccess: () => noteForm.reset(),
        },
    );
};

/* ── Editar etapas ── */
const showStages = ref(false);
const stageList = ref([]);
const stageNames = ref({});
const stageErrors = ref({});
const newStageForm = useForm({ name: '' });

const syncStageList = () => {
    stageList.value = props.stages.map((s) => ({ ...s }));
    const names = {};
    for (const s of props.stages) {
        names[s.id] = s.name;
    }
    stageNames.value = names;
};
watch(() => props.stages, syncStageList, { deep: true, immediate: true });

const openStages = () => {
    syncStageList();
    stageErrors.value = {};
    newStageForm.reset();
    newStageForm.clearErrors();
    showStages.value = true;
};

const persistStageOrder = () => {
    router.post(
        route('admin.contratacao.vagas.stages.reorder', routeParams()),
        { ordered_ids: stageList.value.map((s) => s.id) },
        {
            preserveScroll: true,
            onError: (errors) => {
                stageErrors.value = { _order: errors.ordered_ids ?? 'Não foi possível reordenar.' };
                syncStageList();
            },
        },
    );
};

const onStageDragEnd = (evt) => {
    if (evt.oldIndex !== evt.newIndex) {
        persistStageOrder();
    }
};

const shiftStage = (index, delta) => {
    const next = shiftItem(stageList.value, index, delta);
    if (next.every((s, i) => s.id === stageList.value[i].id)) {
        return;
    }
    stageList.value = next;
    persistStageOrder();
};

const renameStage = (stage) => {
    const name = (stageNames.value[stage.id] ?? '').trim();
    if (name === stage.name) {
        return;
    }
    router.patch(
        route('admin.contratacao.vagas.stages.update', routeParams({ stage: stage.id })),
        { name },
        {
            preserveScroll: true,
            onSuccess: () => {
                stageErrors.value = { ...stageErrors.value, [stage.id]: null };
            },
            onError: (errors) => {
                stageErrors.value = { ...stageErrors.value, [stage.id]: errors.name ?? 'Não foi possível renomear.' };
            },
        },
    );
};

const markHired = async (stage) => {
    if (stage.is_hired) {
        return;
    }
    const ok = await confirmDialog({
        title: `Marcar «${stage.name}» como etapa de contratado?`,
        message: 'Só uma etapa pode ser a de contratado. Quem estiver nela passa a constar como contratado.',
        confirmLabel: 'Marcar',
        variant: 'primary',
    });
    if (!ok) {
        return;
    }
    router.patch(
        route('admin.contratacao.vagas.stages.update', routeParams({ stage: stage.id })),
        { is_hired: true },
        { preserveScroll: true },
    );
};

const deleteStage = async (stage) => {
    const reason = stageDeleteBlockReason(stage, stageList.value.length);
    if (reason) {
        stageErrors.value = { ...stageErrors.value, [stage.id]: reason };
        return;
    }
    if (!(await confirmDialog(`Excluir a etapa «${stage.name}»?`))) {
        return;
    }
    router.delete(route('admin.contratacao.vagas.stages.destroy', routeParams({ stage: stage.id })), {
        preserveScroll: true,
        onError: (errors) => {
            stageErrors.value = { ...stageErrors.value, [stage.id]: errors.stage ?? 'Não foi possível excluir.' };
        },
    });
};

const submitNewStage = () => {
    newStageForm.post(route('admin.contratacao.vagas.stages.store', routeParams()), {
        preserveScroll: true,
        onSuccess: () => newStageForm.reset(),
    });
};

const stageNameById = (id) => props.stages.find((s) => s.id === id)?.name ?? '';
</script>

<template>
    <Head :title="vacancy.title" />

    <AdminLayout>
        <template #header>
            <FormPageHeader
                :back-href="route('admin.contratacao.vagas.index')"
                back-label="Vagas"
                :title="vacancy.title"
                :subtitle="[vacancy.company?.name, vacancy.process?.title].filter(Boolean).join(' · ')"
            >
                <template #trailing>
                    <div class="flex flex-wrap items-center gap-2">
                        <SecondaryButton v-if="communication" type="button" class="gap-1.5" @click="showCommunicate = true">
                            Comunicar etapa
                        </SecondaryButton>
                        <SecondaryButton v-if="can.edit" type="button" class="gap-1.5" @click="openStages">
                            <Bars3Icon class="h-4 w-4" aria-hidden="true" />
                            Editar etapas
                        </SecondaryButton>
                        <PrimaryButton v-if="can.create" type="button" class="gap-1.5" @click="openAdd">
                            <UserPlusIcon class="h-4 w-4" aria-hidden="true" />
                            Incluir candidato
                        </PrimaryButton>
                    </div>
                </template>
            </FormPageHeader>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.flash.error }}
        </div>
        <div
            v-if="$page.props.errors?.stage || $page.props.errors?.stage_id || $page.props.errors?.talent_candidate_id"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="alert"
        >
            {{ $page.props.errors.stage || $page.props.errors.stage_id || $page.props.errors.talent_candidate_id }}
        </div>

        <section class="surface-card p-4 sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-talents-600">Processo</p>
                    <p class="mt-1 text-sm text-slate-700">
                        {{ vacancy.process?.title ?? '—' }}
                        <span v-if="vacancy.process" class="text-slate-400">· fase {{ vacancy.process.stage_label }}</span>
                    </p>
                    <p v-if="vacancy.description" class="mt-3 whitespace-pre-wrap text-sm text-slate-600">
                        {{ vacancy.description }}
                    </p>
                    <p class="mt-3 text-xs text-slate-500">
                        {{ totalApplications }} {{ totalApplications === 1 ? 'candidato' : 'candidatos' }} na vaga
                        <template v-if="vacancy.closed_at"> · encerrada em {{ formatDateTime(vacancy.closed_at) }}</template>
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="vacancy-status" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Situação
                        </label>
                        <select
                            v-if="can.edit"
                            id="vacancy-status"
                            :value="vacancy.status"
                            class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                            @change="onStatusChange"
                        >
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <p v-else id="vacancy-status" class="mt-1 text-sm font-semibold text-slate-800">{{ statusLabel }}</p>
                    </div>
                    <button
                        v-if="can.edit"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        @click="openEdit"
                    >
                        <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
                        Editar vaga
                    </button>
                </div>
            </div>
        </section>

        <!-- Quadro por etapa -->
        <section class="mt-5" aria-label="Quadro de candidatos por etapa">
            <div class="flex gap-4 overflow-x-auto pb-3">
                <div
                    v-for="stage in stages"
                    :key="stage.id"
                    class="flex w-72 shrink-0 flex-col rounded-2xl border bg-slate-50/70"
                    :class="stage.is_hired ? 'border-emerald-200' : 'border-slate-200/90'"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-slate-200/70 px-3 py-2.5">
                        <h3 class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-talents-900">
                            <CheckBadgeIcon v-if="stage.is_hired" class="h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" />
                            <span class="truncate">{{ stage.name }}</span>
                            <span v-if="stage.is_hired" class="sr-only">(etapa de contratado)</span>
                        </h3>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200">
                            {{ (columns[stage.id] ?? []).length }}
                        </span>
                    </div>

                    <VueDraggable
                        v-model="columns[stage.id]"
                        :data-stage-id="stage.id"
                        group="vacancy-applications"
                        item-key="id"
                        handle=".application-drag"
                        class="flex min-h-24 flex-1 flex-col gap-2 p-2"
                        ghost-class="opacity-40"
                        :animation="160"
                        :disabled="!can.edit"
                        @end="onDragEnd"
                    >
                        <article
                            v-for="application in columns[stage.id]"
                            :key="application.id"
                            :data-application-id="application.id"
                            class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm"
                        >
                            <div class="flex items-start gap-2">
                                <button
                                    v-if="can.edit"
                                    type="button"
                                    class="application-drag mt-0.5 shrink-0 cursor-grab rounded p-0.5 text-slate-300 hover:text-talents-600 active:cursor-grabbing"
                                    :aria-label="`Arrastar ${application.candidate.name}`"
                                    title="Arrastar para outra etapa"
                                >
                                    <Bars3Icon class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <div class="min-w-0 flex-1">
                                    <Link
                                        v-if="application.candidate.url"
                                        :href="application.candidate.url"
                                        class="block truncate text-sm font-semibold text-slate-900 hover:text-talents-700 hover:underline"
                                        :title="`Abrir a ficha de ${application.candidate.name}`"
                                    >
                                        {{ application.candidate.name }}
                                    </Link>
                                    <p v-else class="truncate text-sm font-semibold text-slate-900">{{ application.candidate.name }}</p>
                                    <p v-if="application.candidate.current_role" class="truncate text-xs text-slate-500">
                                        {{ application.candidate.current_role }}
                                    </p>
                                    <p v-if="application.candidate.email" class="truncate text-xs text-slate-500">
                                        {{ application.candidate.email }}
                                    </p>
                                    <p v-if="application.candidate.phone" class="truncate text-xs text-slate-500">
                                        {{ application.candidate.phone }}
                                    </p>
                                    <p v-if="application.hired_at" class="mt-1 text-xs font-medium text-emerald-700">
                                        Contratado em {{ formatDateTime(application.hired_at) }}
                                    </p>
                                    <p v-if="application.candidate.archived" class="mt-1 text-xs font-medium text-amber-700">
                                        Cadastro arquivado
                                    </p>
                                </div>
                            </div>

                            <div class="mt-2 space-y-2">
                                <div v-if="can.edit">
                                    <label :for="`move-${application.id}`" class="sr-only">
                                        Etapa de {{ application.candidate.name }}
                                    </label>
                                    <select
                                        :id="`move-${application.id}`"
                                        :value="application.stage_id"
                                        :class="smallSelectClass"
                                        @change="onSelectStage(application, $event)"
                                    >
                                        <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                                    </select>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-talents-700 hover:text-talents-900"
                                        @click="openHistory(application)"
                                    >
                                        <ClockIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                        Histórico ({{ application.events.length }})
                                    </button>
                                    <Link
                                        v-if="application.interviews_url"
                                        :href="application.interviews_url"
                                        class="text-xs font-semibold text-talents-700 hover:text-talents-900"
                                        :aria-label="`Entrevistas de ${application.candidate.name}`"
                                    >
                                        Entrevistas ({{ application.interviews_count }})
                                    </Link>
                                    <Link
                                        :href="application.assessment_url"
                                        class="text-xs font-semibold text-talents-700 hover:text-talents-900"
                                        :aria-label="`Avaliação de ${application.candidate.name}`"
                                        :title="application.assessment_label ?? 'Avaliação ainda não iniciada'"
                                    >
                                        Avaliação
                                    </Link>
                                    <Link
                                        v-if="application.cycle?.url"
                                        :href="application.cycle.url"
                                        class="text-xs font-semibold text-emerald-700 hover:text-emerald-900"
                                        :aria-label="`Pós-contratação de ${application.candidate.name}`"
                                    >
                                        Vínculo · {{ application.cycle.status_label }}
                                    </Link>
                                    <span v-else-if="application.cycle" class="text-xs font-semibold text-emerald-700">
                                        Vínculo · {{ application.cycle.status_label }}
                                    </span>
                                    <button
                                        v-else-if="application.admission"
                                        type="button"
                                        class="text-xs font-semibold text-emerald-700 hover:text-emerald-900"
                                        @click="admitting = application"
                                    >
                                        Registrar admissão
                                    </button>
                                    <button
                                        v-if="can.delete"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg p-1 text-xs font-semibold text-red-600 hover:bg-red-50"
                                        :aria-label="`Retirar ${application.candidate.name} da vaga`"
                                        title="Retirar da vaga"
                                        @click="removeApplication(application)"
                                    >
                                        <UserMinusIcon class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    </VueDraggable>
                </div>
            </div>
            <p v-if="!totalApplications" class="mt-2 text-sm text-slate-500">
                Nenhum candidato nesta vaga ainda.
                <template v-if="can.create">Use «Incluir candidato» para começar.</template>
            </p>
        </section>

        <!-- Comunicações aos contatos da empresa -->
        <section class="surface-card mt-5 p-4 sm:p-5" aria-labelledby="communications-title">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="communications-title" class="text-base font-semibold text-talents-900">Comunicações com a empresa</h3>
                <p class="text-xs text-slate-500">
                    «Enviado ao servidor de e-mail» não confirma entrega nem leitura. No WhatsApp, o sistema só registra a cópia do texto.
                </p>
            </div>
            <div class="mt-3">
                <CommunicationHistory :items="communications" :can-copy="Boolean(communication)" />
            </div>
        </section>

        <AdmissionModal :show="admitting !== null" :application="admitting" @close="admitting = null" />

        <StageCommunicationModal
            v-if="communication"
            :show="showCommunicate"
            :vacancy-id="vacancy.id"
            :stages="stages"
            :audience="communication"
            @close="showCommunicate = false"
        />

        <!-- Editar vaga -->
        <Modal :show="showEdit" max-width="lg" @close="showEdit = false">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-semibold text-talents-900">Editar vaga</h3>
                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel for="edit-title" value="Título da vaga" />
                        <TextInput id="edit-title" v-model="editForm.title" :class="fieldClass" maxlength="255" required />
                        <InputError class="mt-1" :message="editForm.errors.title" />
                    </div>
                    <div>
                        <InputLabel for="edit-description" value="Descrição (opcional)" />
                        <textarea id="edit-description" v-model="editForm.description" rows="4" maxlength="5000" :class="fieldClass" />
                        <InputError class="mt-1" :message="editForm.errors.description" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showEdit = false">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Incluir candidato -->
        <Modal :show="showAdd" max-width="xl" @close="showAdd = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-talents-900">Incluir candidato</h3>

                <div class="mt-4">
                    <InputLabel for="add-stage" value="Etapa inicial" />
                    <select id="add-stage" v-model="addStageId" :class="fieldClass">
                        <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                    </select>
                </div>

                <div class="mt-4 flex gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="addMode === 'search'"
                        class="flex-1 rounded-lg px-3 py-1.5"
                        :class="addMode === 'search' ? 'bg-white text-talents-800 shadow-sm' : 'text-slate-600'"
                        @click="addMode = 'search'"
                    >
                        Buscar cadastrados
                    </button>
                    <button
                        v-if="can.create_candidate"
                        type="button"
                        role="tab"
                        :aria-selected="addMode === 'quick'"
                        class="flex-1 rounded-lg px-3 py-1.5"
                        :class="addMode === 'quick' ? 'bg-white text-talents-800 shadow-sm' : 'text-slate-600'"
                        @click="addMode = 'quick'"
                    >
                        Cadastro rápido
                    </button>
                </div>

                <div v-if="addMode === 'search'" class="mt-4">
                    <label for="add-search" class="sr-only">Buscar por nome, e-mail ou telefone</label>
                    <TextInput
                        id="add-search"
                        v-model="searchTerm"
                        type="search"
                        :class="fieldClass"
                        placeholder="Nome, e-mail ou telefone"
                        autocomplete="off"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Até 10 candidatos ativos que ainda não estão nesta vaga.
                    </p>
                    <InputError class="mt-2" :message="pickForm.errors.talent_candidate_id || pickForm.errors.email" />
                    <p v-if="searchError" class="mt-2 text-sm text-red-600">{{ searchError }}</p>
                    <p v-else-if="searching" class="mt-3 text-sm text-slate-500" role="status">Buscando…</p>
                    <ul v-else-if="searchResults.length" class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200">
                        <li v-for="c in searchResults" :key="c.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ c.name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ [c.email, c.phone, c.current_role].filter(Boolean).join(' · ') || 'Sem contato informado' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-talents-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                                :disabled="pickForm.processing"
                                @click="pickCandidate(c)"
                            >
                                <PlusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                Incluir
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-slate-500">Nenhum candidato encontrado.</p>
                </div>

                <form v-else class="mt-4 space-y-4" @submit.prevent="submitQuick">
                    <p class="text-xs text-slate-500">
                        Se o e-mail já estiver cadastrado, o candidato existente é usado. O nome não identifica a pessoa.
                    </p>
                    <div>
                        <InputLabel for="quick-name" value="Nome" />
                        <TextInput id="quick-name" v-model="quickForm.name" :class="fieldClass" maxlength="255" required />
                        <InputError class="mt-1" :message="quickForm.errors.name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="quick-email" value="E-mail (opcional)" />
                            <TextInput id="quick-email" v-model="quickForm.email" type="email" :class="fieldClass" maxlength="255" />
                            <InputError class="mt-1" :message="quickForm.errors.email" />
                        </div>
                        <div>
                            <InputLabel for="quick-phone" value="Telefone (opcional)" />
                            <TextInput id="quick-phone" v-model="quickForm.phone" type="tel" :class="fieldClass" maxlength="40" />
                            <InputError class="mt-1" :message="quickForm.errors.phone" />
                        </div>
                    </div>
                    <InputError :message="quickForm.errors.talent_candidate_id || quickForm.errors.stage_id" />
                    <Link
                        v-if="quickForm.errors.existing_candidate_url"
                        :href="quickForm.errors.existing_candidate_url"
                        class="inline-block text-sm font-semibold text-talents-700 underline"
                    >
                        Abrir o cadastro para restaurar
                    </Link>
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="quickForm.processing">Cadastrar e incluir</PrimaryButton>
                    </div>
                </form>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="showAdd = false">Fechar</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Histórico da candidatura -->
        <Modal :show="historyApplication !== null" max-width="lg" @close="closeHistory">
            <div v-if="historyApplication" class="p-6">
                <h3 class="text-lg font-semibold text-talents-900">{{ historyApplication.candidate.name }}</h3>
                <p class="mt-1 text-sm text-slate-600">Etapa atual: {{ stageNameById(historyApplication.stage_id) }}</p>

                <form v-if="can.edit" class="mt-4" @submit.prevent="submitNote">
                    <InputLabel for="history-note" value="Nova anotação" />
                    <textarea id="history-note" v-model="noteForm.note" rows="3" maxlength="2000" :class="fieldClass" required />
                    <InputError class="mt-1" :message="noteForm.errors.note" />
                    <div class="mt-2 flex justify-end">
                        <PrimaryButton :disabled="noteForm.processing">Registrar anotação</PrimaryButton>
                    </div>
                </form>

                <ol class="mt-5 space-y-2">
                    <li
                        v-for="event in historyApplication.events"
                        :key="event.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5"
                    >
                        <div class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                            <span class="font-semibold text-slate-800">{{ event.type_label }}</span>
                            <span v-if="event.user_name">· {{ event.user_name }}</span>
                            <span v-if="event.created_at">· {{ formatDateTime(event.created_at) }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ describeApplicationEvent(event) }}</p>
                    </li>
                </ol>
                <p v-if="!historyApplication.events.length" class="mt-5 text-sm text-slate-500">Sem registros.</p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="closeHistory">Fechar</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Editar etapas -->
        <Modal :show="showStages" max-width="xl" @close="showStages = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-talents-900">Etapas da vaga</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Arraste ou use as setas para reordenar. Só uma etapa é a de contratado. Etapas com candidatos não podem
                    ser excluídas.
                </p>
                <p v-if="stageErrors._order" class="mt-2 text-sm text-red-600" role="alert">{{ stageErrors._order }}</p>

                <VueDraggable
                    v-model="stageList"
                    item-key="id"
                    handle=".stage-drag"
                    tag="ol"
                    class="mt-4 space-y-2"
                    ghost-class="opacity-40"
                    :animation="160"
                    @end="onStageDragEnd"
                >
                    <li
                        v-for="(stage, index) in stageList"
                        :key="stage.id"
                        class="rounded-xl border bg-white p-3"
                        :class="stage.is_hired ? 'border-emerald-200' : 'border-slate-200'"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="stage-drag cursor-grab rounded p-1 text-slate-300 hover:text-talents-600 active:cursor-grabbing"
                                :aria-label="`Arrastar etapa ${stage.name}`"
                            >
                                <Bars3Icon class="h-4 w-4" aria-hidden="true" />
                            </button>
                            <div class="flex flex-col">
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-slate-400 hover:text-talents-700 disabled:opacity-30"
                                    :disabled="index === 0"
                                    :aria-label="`Subir etapa ${stage.name}`"
                                    @click="shiftStage(index, -1)"
                                >
                                    <ArrowUpIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-slate-400 hover:text-talents-700 disabled:opacity-30"
                                    :disabled="index === stageList.length - 1"
                                    :aria-label="`Descer etapa ${stage.name}`"
                                    @click="shiftStage(index, 1)"
                                >
                                    <ArrowDownIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                            </div>
                            <label :for="`stage-name-${stage.id}`" class="sr-only">Nome da etapa</label>
                            <input
                                :id="`stage-name-${stage.id}`"
                                v-model="stageNames[stage.id]"
                                type="text"
                                maxlength="80"
                                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm focus:border-talents-400 focus:outline-none focus:ring-2 focus:ring-talents-200/70"
                                @keydown.enter.prevent="renameStage(stage)"
                                @blur="renameStage(stage)"
                            />
                            <span class="text-xs tabular-nums text-slate-500">
                                {{ stage.applications_count }} {{ stage.applications_count === 1 ? 'candidato' : 'candidatos' }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold"
                                :class="stage.is_hired ? 'bg-emerald-50 text-emerald-800' : 'text-slate-600 hover:bg-slate-50'"
                                :aria-pressed="stage.is_hired"
                                @click="markHired(stage)"
                            >
                                <CheckBadgeIcon class="h-4 w-4" aria-hidden="true" />
                                {{ stage.is_hired ? 'Contratado' : 'Marcar contratado' }}
                            </button>
                            <button
                                v-if="can.delete"
                                type="button"
                                class="rounded-lg p-1.5 text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-30"
                                :disabled="stageDeleteBlockReason(stage, stageList.length) !== null"
                                :title="stageDeleteBlockReason(stage, stageList.length) ?? 'Excluir etapa'"
                                :aria-label="`Excluir etapa ${stage.name}`"
                                @click="deleteStage(stage)"
                            >
                                <TrashIcon class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                        <p v-if="stageErrors[stage.id]" class="mt-1 text-xs text-red-600" role="alert">{{ stageErrors[stage.id] }}</p>
                    </li>
                </VueDraggable>

                <form class="mt-4 flex flex-wrap items-end gap-2" @submit.prevent="submitNewStage">
                    <div class="min-w-0 flex-1">
                        <InputLabel for="new-stage" value="Nova etapa (entra antes da etapa de contratado)" />
                        <TextInput id="new-stage" v-model="newStageForm.name" :class="fieldClass" maxlength="80" required />
                        <InputError class="mt-1" :message="newStageForm.errors.name" />
                    </div>
                    <PrimaryButton :disabled="newStageForm.processing" class="mb-px">Adicionar</PrimaryButton>
                </form>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="showStages = false">Fechar</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
