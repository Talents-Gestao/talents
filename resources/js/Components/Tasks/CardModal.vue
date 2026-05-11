<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    ChatBubbleOvalLeftEllipsisIcon,
    CheckCircleIcon,
    PaperClipIcon,
} from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    card: { type: Object, default: null },
    boardPayload: { type: Object, required: true },
    companyUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    visibilityCardOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'refresh', 'sync-card']);
const activeTab = ref('details');
/** Evita voltar para "Detalhes" quando o mesmo cartão é só recarregado (ex.: após criar etiqueta). */
const lastOpenedCardId = ref(null);

const cardUpdate = useForm({
    title: '',
    description: '',
    visibility: 'inherit',
    due_date: '',
    start_date: '',
    company_id: '',
    member_ids: [],
    label_ids: [],
});

const commentForm = useForm({ body: '', mentioned_user_ids: [] });
const checklistForm = useForm({ name: '' });
const checklistBulkProcessing = ref({});
const editingChecklistItemId = ref(null);
const editingChecklistItemText = ref('');

const showNewLabelForm = ref(false);
const newLabelDraft = ref({ name: '', color: '#3b82f6' });
const editingLabelId = ref(null);
const labelEditDraft = ref({ name: '', color: '' });

watch(
    () => props.card,
    (c) => {
        if (!c) {
            lastOpenedCardId.value = null;
            return;
        }
        const id = Number(c.id);
        if (lastOpenedCardId.value !== id) {
            lastOpenedCardId.value = id;
            activeTab.value = 'details';
            showNewLabelForm.value = false;
            editingLabelId.value = null;
        }
        cardUpdate.title = c.title || '';
        cardUpdate.description = c.description || '';
        cardUpdate.visibility = c.visibility || 'inherit';
        cardUpdate.due_date = c.due_date || '';
        cardUpdate.start_date = c.start_date || '';
        cardUpdate.company_id = c.company_id || '';
        cardUpdate.member_ids = (c.members || []).map((m) => m.id);
        cardUpdate.label_ids = (c.labels || []).map((l) => l.id);
    },
    { immediate: true },
);

const usersForSelectedCompany = computed(() => {
    if (!props.isAdmin) return props.companyUsers || [];
    if (!cardUpdate.company_id) return [];

    return (props.companyUsers || []).filter(
        (u) => Number(u.company_id) === Number(cardUpdate.company_id),
    );
});

function checklistStats(checklist) {
    const items = checklist?.items || [];
    const total = items.length;
    const completed = items.filter((item) => item.is_completed).length;
    const doneByItems = total > 0 && completed === total;
    const done = total > 0 ? doneByItems : !!checklist?.is_completed;
    const percent = done ? 100 : total ? Math.round((completed / total) * 100) : 0;

    return { total, completed, percent, done };
}

const overallChecklistStats = computed(() => {
    const checklists = props.card?.checklists || [];
    const totals = checklists.reduce(
        (acc, checklist) => {
            const stats = checklistStats(checklist);
            if (stats.total > 0) {
                acc.total += stats.total;
                acc.completed += stats.completed;
            } else {
                // Checklist sem itens também conta como uma etapa no progresso geral.
                acc.total += 1;
                acc.completed += stats.done ? 1 : 0;
            }
            return acc;
        },
        { total: 0, completed: 0 },
    );

    const percent = totals.total ? Math.round((totals.completed / totals.total) * 100) : 0;
    return { ...totals, percent };
});

function saveCard() {
    if (!props.card) return;
    const url = props.isAdmin
        ? route('admin.tarefas.cards.update', props.card.id)
        : route('client.tarefas.cards.update', props.card.id);

    if (props.isAdmin) {
        cardUpdate
            .transform((data) => ({
                ...data,
                company_id: data.company_id || null,
                due_date: data.due_date || null,
                start_date: data.start_date || null,
            }))
            .patch(url, {
                preserveScroll: true,
                onSuccess: () => emit('refresh'),
            });
    } else {
        cardUpdate
            .transform((data) => ({
                title: data.title,
                description: data.description,
                due_date: data.due_date || null,
                start_date: data.start_date || null,
            }))
            .patch(url, {
                preserveScroll: true,
                onSuccess: () => emit('refresh'),
            });
    }
}

function reloadBoardPayloadAndSyncCard() {
    if (!props.card) return;
    router.reload({
        only: ['boardPayload'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => emit('sync-card', props.card.id),
    });
}

function submitComment() {
    if (!props.card || !commentForm.body.trim()) return;
    const url = props.isAdmin
        ? route('admin.tarefas.cards.comentarios.store', props.card.id)
        : route('client.tarefas.cards.comentarios.store', props.card.id);

    commentForm.post(url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            commentForm.reset('body');
            reloadBoardPayloadAndSyncCard();
        },
    });
}

function toggleItem(item) {
    const url = props.isAdmin
        ? route('admin.tarefas.checklist-itens.update', item.id)
        : route('client.tarefas.checklist-itens.update', item.id);

    router.patch(
        url,
        { is_completed: !item.is_completed },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => reloadBoardPayloadAndSyncCard(),
        },
    );
}

function createChecklist() {
    if (!props.isAdmin || !props.card || !checklistForm.name.trim()) return;

    checklistForm.post(route('admin.tarefas.cards.checklists.store', props.card.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            checklistForm.reset('name');
            reloadBoardPayloadAndSyncCard();
        },
    });
}

function startInlineEditItem(item) {
    if (!item || !item.id) return;
    editingChecklistItemId.value = item.id;
    editingChecklistItemText.value = item.text || '';
}

function cancelInlineEditItem() {
    editingChecklistItemId.value = null;
    editingChecklistItemText.value = '';
}

function saveInlineEditItem(item) {
    if (!item || !item.id) return;
    const text = editingChecklistItemText.value.trim();
    if (!text || text === item.text) {
        cancelInlineEditItem();
        return;
    }

    const url = props.isAdmin
        ? route('admin.tarefas.checklist-itens.update', item.id)
        : route('client.tarefas.checklist-itens.update', item.id);

    router.patch(
        url,
        { text },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                cancelInlineEditItem();
                reloadBoardPayloadAndSyncCard();
            },
        },
    );
}

function toggleChecklistCompletion(checklist) {
    if (!checklist || !checklist.id) return;
    const stats = checklistStats(checklist);

    if (!stats.total) {
        if (!props.isAdmin) return;
        router.patch(
            route('admin.tarefas.checklists.update', checklist.id),
            { is_completed: !stats.done },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => reloadBoardPayloadAndSyncCard(),
            },
        );
        return;
    }

    const targetValue = !stats.done;
    const items = checklist.items || [];
    checklistBulkProcessing.value[checklist.id] = true;

    let pending = items.length;
    for (const item of items) {
        const url = props.isAdmin
            ? route('admin.tarefas.checklist-itens.update', item.id)
            : route('client.tarefas.checklist-itens.update', item.id);

        router.patch(
            url,
            { is_completed: targetValue },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    pending -= 1;
                    if (pending === 0) {
                        checklistBulkProcessing.value[checklist.id] = false;
                        reloadBoardPayloadAndSyncCard();
                    }
                },
            },
        );
    }
}

function uploadAttachment(e) {
    const file = e.target.files?.[0];
    if (!file || !props.card) return;

    const url = props.isAdmin
        ? route('admin.tarefas.cards.anexos.store', props.card.id)
        : route('client.tarefas.cards.anexos.store', props.card.id);

    const fd = new FormData();
    fd.append('file', file);

    router.post(url, fd, {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => reloadBoardPayloadAndSyncCard(),
    });
    e.target.value = '';
}

function randomLabelColor() {
    return `#${Math.floor(Math.random() * 0xffffff)
        .toString(16)
        .padStart(6, '0')}`;
}

function openNewLabelForm() {
    if (!props.isAdmin || !props.card) return;
    newLabelDraft.value = { name: '', color: randomLabelColor() };
    showNewLabelForm.value = true;
}

function cancelNewLabelForm() {
    showNewLabelForm.value = false;
}

function submitNewLabel() {
    if (!props.isAdmin || !props.card) return;
    const name = newLabelDraft.value.name.trim();
    if (!name) return;

    router.post(
        route('admin.tarefas.quadros.labels.store', props.boardPayload.id),
        { name, color: newLabelDraft.value.color },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showNewLabelForm.value = false;
                reloadBoardPayloadAndSyncCard();
            },
        },
    );
}

function startLabelEdit(label) {
    if (!label?.id) return;
    editingLabelId.value = label.id;
    labelEditDraft.value = {
        name: label.name || '',
        color: label.color || '#64748b',
    };
}

function cancelLabelEdit() {
    editingLabelId.value = null;
    labelEditDraft.value = { name: '', color: '' };
}

function saveLabelEdit(label) {
    if (!label?.id) return;
    const name = labelEditDraft.value.name.trim();

    router.patch(
        route('admin.tarefas.labels.update', label.id),
        { name: name || null, color: labelEditDraft.value.color },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                cancelLabelEdit();
                reloadBoardPayloadAndSyncCard();
            },
        },
    );
}

function deleteLabel(label) {
    if (!label?.id || !props.isAdmin) return;
    const display = (label.name || '').trim() || 'sem nome';
    if (
        !window.confirm(
            `Excluir a etiqueta "${display}"? Ela será removida de todas as tarefas deste quadro.`,
        )
    ) {
        return;
    }

    router.delete(route('admin.tarefas.labels.destroy', label.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            cardUpdate.label_ids = (cardUpdate.label_ids || []).filter((id) => Number(id) !== Number(label.id));
            if (editingLabelId.value === label.id) cancelLabelEdit();
            reloadBoardPayloadAndSyncCard();
        },
    });
}

function formatDateTime(value) {
    if (!value) return '';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return value;
    return dt.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div v-if="card" class="flex max-h-[85vh] flex-col overflow-hidden rounded-xl bg-white">
            <div class="border-b border-slate-100 px-6 pt-5">
                <p class="text-sm font-semibold text-slate-900">Planejar atividade</p>
                <div class="mt-4 flex items-center gap-5 text-xs">
                    <button
                        type="button"
                        class="border-b-2 pb-2 font-medium transition"
                        :class="activeTab === 'details' ? 'border-talents-600 text-talents-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'details'"
                    >
                        Detalhes
                    </button>
                    <button
                        v-if="isAdmin"
                        type="button"
                        class="border-b-2 pb-2 font-medium transition"
                        :class="activeTab === 'settings' ? 'border-talents-600 text-talents-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'settings'"
                    >
                        Configurações
                    </button>
                    <button
                        type="button"
                        class="border-b-2 pb-2 font-medium transition"
                        :class="activeTab === 'comments' ? 'border-talents-600 text-talents-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        @click="activeTab = 'comments'"
                    >
                        Comentários {{ (card.comments || []).length ? `${(card.comments || []).length}` : '' }}
                    </button>
                </div>
            </div>

            <div v-if="activeTab === 'details'" class="space-y-4 overflow-y-auto p-6">
                <div class="space-y-4 rounded-lg border border-slate-100 p-4">
                    <div class="space-y-1">
                        <InputLabel value="Título" />
                        <TextInput
                            v-model="cardUpdate.title"
                            class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="space-y-1">
                        <InputLabel value="Descrição" />
                        <textarea
                            v-model="cardUpdate.description"
                            rows="4"
                            class="mt-1 w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <InputLabel value="Início" />
                            <TextInput
                                v-model="cardUpdate.start_date"
                                type="date"
                                class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none"
                            />
                        </div>
                        <div>
                            <InputLabel value="Vencimento" />
                            <TextInput
                                v-model="cardUpdate.due_date"
                                type="date"
                                class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none"
                            />
                        </div>
                    </div>

                </div>

                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <CheckCircleIcon class="h-4 w-4 text-slate-500" />
                        Checklist
                    </h4>
                    <div class="rounded-md border border-slate-200 bg-slate-50/60 p-2">
                        <div class="mb-1 flex items-center justify-between text-[11px] text-slate-600">
                            <span>Progresso geral</span>
                            <span>
                                {{ overallChecklistStats.completed }}/{{ overallChecklistStats.total }}
                                ({{ overallChecklistStats.percent }}%)
                            </span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                            <div
                                class="h-full rounded-full bg-talents-600 transition-all duration-200"
                                :style="{ width: `${overallChecklistStats.percent}%` }"
                            />
                        </div>
                    </div>
                    <div v-if="isAdmin" class="flex gap-2">
                        <TextInput
                            v-model="checklistForm.name"
                            class="w-full border-slate-200 bg-white text-sm shadow-none"
                            placeholder="Nome da checklist"
                        />
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            @click="createChecklist"
                        >
                            Criar
                        </button>
                    </div>

                    <div class="mt-2 space-y-3">
                        <div
                            v-for="cl in card.checklists || []"
                            :key="cl.id"
                            class="rounded-md border border-slate-200 p-2"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-700">{{ cl.name }}</p>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="checklistBulkProcessing[cl.id]"
                                    @click="toggleChecklistCompletion(cl)"
                                >
                                    {{ checklistStats(cl).done ? 'Reabrir' : 'Concluir' }}
                                </button>
                            </div>
                            <div class="mt-2">
                                <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500">
                                    <span>Progresso</span>
                                    <span>{{ checklistStats(cl).completed }}/{{ checklistStats(cl).total }} ({{ checklistStats(cl).percent }}%)</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full bg-emerald-500 transition-all duration-200"
                                        :style="{ width: `${checklistStats(cl).percent}%` }"
                                    />
                                </div>
                            </div>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li
                                    v-for="it in cl.items || []"
                                    :key="it.id"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="it.is_completed"
                                        class="rounded border-slate-300"
                                        @change="toggleItem(it)"
                                    />
                                    <TextInput
                                        v-if="editingChecklistItemId === it.id"
                                        v-model="editingChecklistItemText"
                                        class="h-8 w-full border-slate-200 bg-white text-sm shadow-none"
                                        @keydown.enter.prevent="saveInlineEditItem(it)"
                                        @keydown.esc.prevent="cancelInlineEditItem"
                                        @blur="saveInlineEditItem(it)"
                                    />
                                    <button
                                        v-else
                                        type="button"
                                        class="w-full text-left"
                                        @click="startInlineEditItem(it)"
                                    >
                                        <span :class="it.is_completed ? 'text-slate-400 line-through' : ''">{{ it.text }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <p v-if="!(card.checklists || []).length" class="text-xs text-slate-500">
                        Nenhuma checklist criada ainda.
                    </p>
                </div>

                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <PaperClipIcon class="h-4 w-4 text-slate-500" />
                        Anexos
                    </h4>
                    <input type="file" class="mt-2 block text-sm" @change="uploadAttachment" />
                    <ul class="mt-2 space-y-1 text-xs">
                        <li v-for="a in card.attachments || []" :key="a.id">
                            <a :href="a.url" target="_blank" class="text-talents-700 underline">{{ a.original_name }}</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else-if="activeTab === 'settings'" class="overflow-y-auto p-6">
                <div class="space-y-4 rounded-lg border border-slate-100 p-4">
                    <div v-if="isAdmin && visibilityCardOptions.length">
                        <InputLabel value="Visibilidade do cartão" />
                        <select
                            v-model="cardUpdate.visibility"
                            class="mt-1 block w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        >
                            <option v-for="o in visibilityCardOptions" :key="o.value" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="isAdmin" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Empresa responsável (cliente)" />
                            <select
                                v-model="cardUpdate.company_id"
                                class="mt-1 block w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                            >
                                <option value="">Não compartilhar com empresa</option>
                                <option v-for="c in companies" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Quando definida, a tarefa aparece para essa empresa no portal cliente.
                            </p>
                        </div>
                        <div>
                            <InputLabel value="Membros" />
                            <div class="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/60 p-2 text-sm">
                                <label
                                    v-for="u in usersForSelectedCompany"
                                    :key="u.id"
                                    class="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-white"
                                >
                                    <input v-model="cardUpdate.member_ids" type="checkbox" :value="u.id" />
                                    {{ u.name }}
                                </label>
                                <p v-if="!usersForSelectedCompany.length" class="text-xs text-slate-500">
                                    Selecione uma empresa para listar os responsáveis.
                                </p>
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Etiquetas" />
                            <div class="mt-1 max-h-52 space-y-2 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/60 p-2 text-sm">
                                <div
                                    v-for="l in boardPayload.labels"
                                    :key="l.id"
                                    class="rounded px-1 py-1 hover:bg-white"
                                >
                                    <div v-if="editingLabelId === l.id" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                        <input
                                            v-model="labelEditDraft.color"
                                            type="color"
                                            class="h-9 w-14 cursor-pointer rounded border border-slate-200 bg-white p-0.5"
                                        />
                                        <TextInput
                                            v-model="labelEditDraft.name"
                                            class="min-w-0 flex-1 border-slate-200 bg-white text-sm shadow-none"
                                            placeholder="Nome"
                                        />
                                        <div class="flex shrink-0 gap-1">
                                            <button
                                                type="button"
                                                class="rounded border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50"
                                                @click="saveLabelEdit(l)"
                                            >
                                                Salvar
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50"
                                                @click="cancelLabelEdit"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="flex items-start gap-2">
                                        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 rounded py-0.5">
                                            <input v-model="cardUpdate.label_ids" type="checkbox" :value="l.id" />
                                            <span
                                                class="inline-block h-3 w-3 shrink-0 rounded"
                                                :style="{ backgroundColor: l.color }"
                                            />
                                            <span class="truncate">{{ l.name || l.color }}</span>
                                        </label>
                                        <div class="flex shrink-0 gap-1">
                                            <button
                                                type="button"
                                                class="text-[11px] text-talents-700 underline"
                                                @click="startLabelEdit(l)"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                type="button"
                                                class="text-[11px] text-red-600 underline"
                                                @click="deleteLabel(l)"
                                            >
                                                Excluir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="!(boardPayload.labels || []).length" class="text-xs text-slate-500">
                                    Nenhuma etiqueta no quadro.
                                </p>
                            </div>
                            <div v-if="showNewLabelForm" class="mt-2 space-y-2 rounded-md border border-slate-200 bg-white p-2">
                                <TextInput
                                    v-model="newLabelDraft.name"
                                    class="w-full border-slate-200 text-sm shadow-none"
                                    placeholder="Nome da etiqueta"
                                />
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-600">Cor</span>
                                    <input
                                        v-model="newLabelDraft.color"
                                        type="color"
                                        class="h-9 w-14 cursor-pointer rounded border border-slate-200 p-0.5"
                                    />
                                </div>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                        @click="submitNewLabel"
                                    >
                                        Criar etiqueta
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50"
                                        @click="cancelNewLabelForm"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-500">Informe um nome antes de criar.</p>
                            </div>
                            <button
                                v-else
                                type="button"
                                class="mt-1 text-xs text-talents-700 underline"
                                @click="openNewLabelForm"
                            >
                                + Nova etiqueta
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="overflow-y-auto p-6">
                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <ChatBubbleOvalLeftEllipsisIcon class="h-4 w-4 text-slate-500" />
                        Comentários
                    </h4>
                    <ul class="mt-2 max-h-52 space-y-2 overflow-y-auto text-sm">
                        <li v-for="c in card.comments || []" :key="c.id" class="rounded-md border border-slate-200 bg-slate-50/70 p-2">
                            <span class="font-medium">{{ c.user?.name }}</span>
                            <span class="text-xs text-slate-500"> · {{ formatDateTime(c.created_at) }}</span>
                            <p class="mt-1 whitespace-pre-wrap text-slate-800">{{ c.body }}</p>
                        </li>
                    </ul>
                    <textarea
                        v-model="commentForm.body"
                        rows="3"
                        class="mt-2 w-full rounded-md border border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        placeholder="Escrever comentário..."
                    />
                    <div class="flex justify-end">
                        <PrimaryButton
                            type="button"
                            class="mt-1"
                            :disabled="commentForm.processing"
                            @click="submitComment"
                        >
                            Comentar
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 flex items-center justify-end gap-2 border-t border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="emit('close')"
                >
                    Cancelar
                </button>
                <PrimaryButton type="button" :disabled="cardUpdate.processing" @click="saveCard">
                    Salvar alterações
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
