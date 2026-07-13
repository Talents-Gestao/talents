<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TaskDescriptionEditor from '@/Components/Tasks/TaskDescriptionEditor.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    ArchiveBoxArrowDownIcon,
    ArrowUturnLeftIcon,
    Bars3Icon,
    CalendarDaysIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    ClipboardDocumentListIcon,
    PaperClipIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import { formatDateNumeric, formatRelativeDate } from '@/utils/dateOnly';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    card: { type: Object, default: null },
    boardPayload: { type: Object, required: true },
    companyUsers: { type: Array, default: () => [] },
    teamUsers: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    isAdmin: { type: Boolean, default: false },
    visibilityCardOptions: { type: Array, default: () => [] },
    recurrenceOptions: { type: Array, default: () => [] },
});

const isReadOnly = computed(() => !props.isAdmin);
const showRecurrenceEnd = computed(() => Boolean(cardUpdate.recurrence));

const emit = defineEmits(['close', 'refresh', 'sync-card']);
const activeTab = ref('details');
/** Evita voltar para "Detalhes" quando o mesmo cartão é só recarregado (ex.: após criar etiqueta). */
const lastOpenedCardId = ref(null);

const cardUpdate = useForm({
    title: '',
    description: '',
    visibility: 'inherit',
    cover_color: '',
    due_date: '',
    start_date: '',
    recurrence: '',
    recurrence_ends_on: '',
    company_id: '',
    member_ids: [],
    label_ids: [],
});

const COVER_COLOR_PRESETS = [
    { value: '#ef4444', label: 'Vermelho' },
    { value: '#f97316', label: 'Laranja' },
    { value: '#eab308', label: 'Amarelo' },
    { value: '#22c55e', label: 'Verde' },
    { value: '#14b8a6', label: 'Turquesa' },
    { value: '#3b82f6', label: 'Azul' },
    { value: '#8b5cf6', label: 'Roxo' },
    { value: '#ec4899', label: 'Rosa' },
];

function setCoverColor(color) {
    cardUpdate.cover_color = color || '';
}

const commentForm = useForm({ body: '', mentioned_user_ids: [] });
const checklistForm = useForm({ name: '', items_text: '' });
const pendingFocusChecklistItemInputId = ref(null);
const checklistIdsBeforeCreate = ref(null);
const checklistBulkProcessing = ref({});
const editingChecklistId = ref(null);
const editingChecklistName = ref('');
const editingChecklistItemId = ref(null);
const editingChecklistItemText = ref('');
/** Rascunhos locais de descrição por item (edição inline sem perder foco). */
const itemDescriptionDrafts = ref({});
const newChecklistItems = ref({});
/** Itens por checklist (ordem local para arrastar). */
const localChecklistItemsById = ref({});
/** Checklists em ordem local para arrastar. */
const localChecklists = ref([]);

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
            cancelInlineEditChecklist();
            cancelInlineEditItem();
        }
        cardUpdate.title = c.title || '';
        cardUpdate.description = c.description || '';
        cardUpdate.visibility = c.visibility || 'inherit';
        cardUpdate.cover_color = c.cover_color || '';
        cardUpdate.due_date = c.due_date || '';
        cardUpdate.start_date = c.start_date || '';
        cardUpdate.recurrence = c.recurrence || '';
        cardUpdate.recurrence_ends_on = c.recurrence_ends_on || '';
        cardUpdate.company_id = c.company_id || '';
        cardUpdate.member_ids = (c.members || []).map((m) => m.id);
        cardUpdate.label_ids = (c.labels || []).map((l) => l.id);
        syncLocalChecklistItems(c);
        syncLocalChecklists(c);
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

const assignableTeamUsers = computed(() => {
    if (!props.isAdmin) return [];
    return props.teamUsers || [];
});

function sortChecklistItems(items) {
    return [...(items || [])].sort((a, b) => {
        const posA = Number(a.position ?? 0);
        const posB = Number(b.position ?? 0);
        if (posA !== posB) return posA - posB;
        return Number(a.id) - Number(b.id);
    });
}

function sortChecklists(checklists) {
    return [...(checklists || [])].sort((a, b) => {
        const posA = Number(a.position ?? 0);
        const posB = Number(b.position ?? 0);
        if (posA !== posB) return posA - posB;
        return Number(a.id) - Number(b.id);
    });
}

function syncLocalChecklistItems(card) {
    const map = {};
    const descriptions = {};
    for (const cl of card?.checklists || []) {
        map[cl.id] = sortChecklistItems(cl.items);
        for (const item of cl.items || []) {
            descriptions[item.id] = item.description || '';
        }
    }
    localChecklistItemsById.value = map;
    itemDescriptionDrafts.value = descriptions;
}

function syncLocalChecklists(card) {
    localChecklists.value = sortChecklists(card?.checklists);
}

function checklistItemsFor(checklist) {
    if (!checklist?.id) return [];
    return localChecklistItemsById.value[checklist.id] ?? sortChecklistItems(checklist.items);
}

function checklistStats(checklist) {
    const items = checklistItemsFor(checklist);
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
    if (!props.card || isReadOnly.value) return;
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
                recurrence: data.recurrence || null,
                recurrence_ends_on: data.recurrence ? data.recurrence_ends_on || null : null,
                cover_color: data.cover_color || null,
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

function deleteCard() {
    if (!props.card?.id || !props.isAdmin) return;
    const title = props.card.title || 'esta tarefa';
    if (
        !window.confirm(
            `Excluir "${title}"?\n\nA tarefa e todos os seus anexos, comentários e checklists serão removidos.`,
        )
    ) {
        return;
    }

    router.delete(route('admin.tarefas.cards.destroy', props.card.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

function archiveCard() {
    if (!props.card?.id || !props.isAdmin || props.card.is_archived) return;
    const title = props.card.title || 'esta tarefa';
    if (!window.confirm(`Arquivar "${title}"?`)) {
        return;
    }

    router.post(route('admin.tarefas.cards.arquivar', props.card.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            emit('refresh');
        },
    });
}

function restoreCard() {
    if (!props.card?.id || !props.isAdmin) return;

    router.post(route('admin.tarefas.cards.restaurar', props.card.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            emit('refresh');
        },
    });
}

function reloadBoardPayloadAndSyncCard() {
    if (!props.card) return;
    router.reload({
        only: ['boardPayload'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            emit('sync-card', props.card.id);
        },
    });
}

function parseChecklistItemsText(text) {
    return String(text || '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);
}

function scheduleFocusNewChecklistItemInput(checklistId) {
    if (!checklistId) return;
    pendingFocusChecklistItemInputId.value = Number(checklistId);
}

function focusPendingChecklistItemInput() {
    const checklistId = pendingFocusChecklistItemInputId.value;
    if (!checklistId) return;

    nextTick(() => {
        const el = document.querySelector(
            `[data-checklist-new-item="${checklistId}"]`,
        );
        if (el && typeof el.focus === 'function') {
            el.focus();
            pendingFocusChecklistItemInputId.value = null;
        }
    });
}

watch(
    () => props.card?.checklists,
    (lists) => {
        if (pendingFocusChecklistItemInputId.value === 'new' && lists?.length) {
            const before = checklistIdsBeforeCreate.value;
            const added = before
                ? lists.find((cl) => !before.has(Number(cl.id)))
                : lists.reduce((latest, cl) =>
                      !latest || Number(cl.id) > Number(latest.id) ? cl : latest,
                  null);

            if (added?.id) {
                scheduleFocusNewChecklistItemInput(added.id);
                checklistIdsBeforeCreate.value = null;
            }
        }

        focusPendingChecklistItemInput();
    },
    { deep: true },
);

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
    if (isReadOnly.value) return;
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

    const items = parseChecklistItemsText(checklistForm.items_text);
    checklistIdsBeforeCreate.value = new Set(
        (props.card.checklists || []).map((cl) => Number(cl.id)),
    );
    pendingFocusChecklistItemInputId.value = 'new';

    checklistForm
        .transform((data) => ({
            name: data.name.trim(),
            items,
        }))
        .post(route('admin.tarefas.cards.checklists.store', props.card.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                checklistForm.reset('name', 'items_text');
                reloadBoardPayloadAndSyncCard();
            },
        });
}

function startInlineEditChecklist(checklist) {
    if (!props.isAdmin || !checklist?.id) return;
    editingChecklistId.value = checklist.id;
    editingChecklistName.value = checklist.name || '';
}

function cancelInlineEditChecklist() {
    editingChecklistId.value = null;
    editingChecklistName.value = '';
}

function saveInlineEditChecklist(checklist) {
    if (!props.isAdmin || !checklist?.id) return;
    const name = editingChecklistName.value.trim();
    if (!name || name === checklist.name) {
        cancelInlineEditChecklist();
        return;
    }

    router.patch(
        route('admin.tarefas.checklists.update', checklist.id),
        { name },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                cancelInlineEditChecklist();
                reloadBoardPayloadAndSyncCard();
            },
        },
    );
}

function deleteChecklist(checklist) {
    if (!props.isAdmin || !checklist?.id) return;
    const display = (checklist.name || '').trim() || 'sem nome';
    if (
        !window.confirm(
            `Excluir a checklist "${display}"?\n\nTodos os itens desta checklist serão removidos.`,
        )
    ) {
        return;
    }

    router.delete(route('admin.tarefas.checklists.destroy', checklist.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (editingChecklistId.value === checklist.id) cancelInlineEditChecklist();
            reloadBoardPayloadAndSyncCard();
        },
    });
}

function startInlineEditItem(item) {
    if (isReadOnly.value || !item || !item.id) return;
    editingChecklistItemId.value = item.id;
    editingChecklistItemText.value = item.text || '';
}

function cancelInlineEditItem() {
    editingChecklistItemId.value = null;
    editingChecklistItemText.value = '';
}

function checklistItemUpdateUrl(item) {
    return props.isAdmin
        ? route('admin.tarefas.checklist-itens.update', item.id)
        : route('client.tarefas.checklist-itens.update', item.id);
}

function saveInlineEditItem(item) {
    if (!item || !item.id) return;
    const text = editingChecklistItemText.value.trim();
    if (!text || text === item.text) {
        cancelInlineEditItem();
        return;
    }

    router.patch(
        checklistItemUpdateUrl(item),
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

function itemDescriptionDraft(item) {
    if (!item?.id) return '';
    if (itemDescriptionDrafts.value[item.id] === undefined) {
        itemDescriptionDrafts.value = {
            ...itemDescriptionDrafts.value,
            [item.id]: item.description || '',
        };
    }
    return itemDescriptionDrafts.value[item.id];
}

function setItemDescriptionDraft(item, value) {
    if (!item?.id) return;
    itemDescriptionDrafts.value = {
        ...itemDescriptionDrafts.value,
        [item.id]: value,
    };
}

function saveItemDescription(item) {
    if (isReadOnly.value || !item?.id) return;
    const description = (itemDescriptionDrafts.value[item.id] ?? item.description ?? '').trim();
    const previous = (item.description || '').trim();
    if (description === previous) return;

    router.patch(
        checklistItemUpdateUrl(item),
        { description: description || null },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => reloadBoardPayloadAndSyncCard(),
        },
    );
}

function deleteChecklistItem(item) {
    if (!props.isAdmin || !item?.id) return;
    const display = (item.text || '').trim() || 'esta etapa';
    if (!window.confirm(`Excluir "${display}"?`)) {
        return;
    }

    router.delete(route('admin.tarefas.checklist-itens.destroy', item.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (editingChecklistItemId.value === item.id) cancelInlineEditItem();
            reloadBoardPayloadAndSyncCard();
        },
    });
}

function onChecklistItemsReorder(checklist, evt) {
    if (!props.isAdmin || !checklist?.id || evt?.oldIndex === evt?.newIndex) {
        return;
    }

    const items = localChecklistItemsById.value[checklist.id] || [];

    router.post(
        route('admin.tarefas.checklists.itens.reorder', checklist.id),
        { item_ids: items.map((it) => it.id) },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => syncLocalChecklistItems(props.card),
        },
    );
}

function onChecklistsReorder(evt) {
    if (!props.isAdmin || !props.card?.id || evt?.oldIndex === evt?.newIndex) {
        return;
    }

    router.post(
        route('admin.tarefas.cards.checklists.reorder', props.card.id),
        { checklist_ids: localChecklists.value.map((cl) => cl.id) },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => syncLocalChecklists(props.card),
        },
    );
}

function toggleChecklistCompletion(checklist) {
    if (isReadOnly.value || !checklist || !checklist.id) return;
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
    const items = checklistItemsFor(checklist);
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
    if (isReadOnly.value) return;
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

function formatDateLabel(value) {
    if (!value) return '—';
    return formatRelativeDate(value);
}

function formatDateTitle(value) {
    if (!value) return undefined;
    const relative = formatRelativeDate(value);
    const absolute = formatDateNumeric(value);
    if (!absolute || relative === absolute) return absolute;
    return `${relative} (${absolute})`;
}

function newItemDraft(checklistId) {
    if (!newChecklistItems.value[checklistId]) {
        newChecklistItems.value = {
            ...newChecklistItems.value,
            [checklistId]: { text: '', description: '', due_date: '' },
        };
    }
    return newChecklistItems.value[checklistId];
}

function addChecklistItem(checklist) {
    if (!props.isAdmin || !checklist?.id) return;
    const draft = newItemDraft(checklist.id);
    const text = draft.text?.trim();
    if (!text) return;

    scheduleFocusNewChecklistItemInput(checklist.id);

    router.post(
        route('admin.tarefas.checklists.itens.store', checklist.id),
        {
            text,
            description: draft.description?.trim() || null,
            due_date: draft.due_date || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                newChecklistItems.value = {
                    ...newChecklistItems.value,
                    [checklist.id]: { text: '', description: '', due_date: '' },
                };
                reloadBoardPayloadAndSyncCard();
            },
        },
    );
}

function updateItemDueDate(item, dueDate) {
    if (isReadOnly.value || !item?.id) return;
    const url = props.isAdmin
        ? route('admin.tarefas.checklist-itens.update', item.id)
        : route('client.tarefas.checklist-itens.update', item.id);

    router.patch(
        url,
        { due_date: dueDate || null },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => reloadBoardPayloadAndSyncCard(),
        },
    );
}

function itemDueClass(item) {
    if (item.is_completed) return 'border-slate-200 bg-slate-50 text-slate-500';
    if (!item.due_date) return 'border-slate-200 bg-white text-slate-700';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const [y, m, d] = String(item.due_date).split('-').map(Number);
    const due = new Date(y, (m || 1) - 1, d || 1);
    const diff = (due - today) / 86_400_000;
    if (diff < 0) return 'border-rose-200 bg-rose-50 text-rose-800';
    if (diff <= 2) return 'border-amber-200 bg-amber-50 text-amber-900';
    return 'border-slate-200 bg-white text-slate-700';
}
</script>

<template>
    <Modal :show="show" max-width="5xl" @close="emit('close')">
        <div v-if="card" class="flex max-h-[92vh] flex-col overflow-hidden rounded-xl bg-white">
            <div class="border-b border-slate-100 px-6 pt-5">
                <p class="text-sm font-semibold text-slate-900">
                    {{ isReadOnly ? 'Tarefa' : 'Planejar atividade' }}
                </p>
                <p v-if="isReadOnly" class="mt-1 text-xs text-slate-500">
                    Pode visualizar e comentar. Edições ao conteúdo são feitas pela equipe Talents.
                </p>
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
                    <template v-if="isReadOnly">
                        <div class="space-y-1">
                            <InputLabel value="Título" />
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ card.title }}</p>
                        </div>
                        <div class="space-y-1">
                            <InputLabel value="Descrição / observações" />
                            <TaskDescriptionEditor
                                :model-value="card.description"
                                readonly
                                :show-attachment="false"
                            />
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel value="Início" />
                                <p class="mt-1 text-sm text-slate-800" :title="formatDateTitle(card.start_date)">
                                    {{ formatDateLabel(card.start_date) }}
                                </p>
                            </div>
                            <div>
                                <InputLabel value="Vencimento" />
                                <p class="mt-1 text-sm text-slate-800" :title="formatDateTitle(card.due_date)">
                                    {{ formatDateLabel(card.due_date) }}
                                </p>
                            </div>
                            <div v-if="card.recurrence_label">
                                <InputLabel value="Repetição" />
                                <p class="mt-1 text-sm text-slate-800">{{ card.recurrence_label }}</p>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="space-y-1">
                            <InputLabel value="Título" />
                            <TextInput
                                v-model="cardUpdate.title"
                                class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                            />
                        </div>
                        <div class="space-y-1">
                            <InputLabel value="Descrição / observações" />
                            <TaskDescriptionEditor
                                v-model="cardUpdate.description"
                                @attach="uploadAttachment"
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
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <InputLabel value="Repetir" />
                                <select
                                    v-model="cardUpdate.recurrence"
                                    class="mt-1 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option value="">Não se repete</option>
                                    <option
                                        v-for="r in recurrenceOptions"
                                        :key="r.value"
                                        :value="r.value"
                                    >
                                        {{ r.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="showRecurrenceEnd">
                                <InputLabel value="Repetir até" />
                                <TextInput
                                    v-model="cardUpdate.recurrence_ends_on"
                                    type="date"
                                    class="mt-1 w-full border-slate-200 bg-white text-sm shadow-none"
                                />
                            </div>
                        </div>
                    </template>
                </div>

                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <ClipboardDocumentListIcon class="h-4 w-4 text-slate-500" aria-hidden="true" />
                        Checklist de tarefas
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
                    <div v-if="isAdmin" class="space-y-2 rounded-md border border-dashed border-slate-200 bg-white p-3">
                        <TextInput
                            v-model="checklistForm.name"
                            class="w-full border-slate-200 bg-white text-sm shadow-none"
                            placeholder="Nome da checklist"
                            @keydown.enter.prevent="createChecklist"
                        />
                        <textarea
                            v-model="checklistForm.items_text"
                            rows="4"
                            :placeholder="'Etapas (opcional) — uma por linha\nEx.: Revisar briefing\nPublicar vaga\nEnviar relatório'"
                            class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-none focus:border-talents-500 focus:ring-talents-500"
                        />
                        <p class="text-[11px] text-slate-500">
                            Você pode criar várias etapas de uma vez. Depois de salvar, o cursor permanece no campo
                            para adicionar mais etapas na mesma checklist.
                        </p>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            :disabled="checklistForm.processing || !checklistForm.name.trim()"
                            @click="createChecklist"
                        >
                            Criar checklist
                        </button>
                    </div>

                    <VueDraggable
                        v-model="localChecklists"
                        item-key="id"
                        tag="div"
                        class="mt-2 space-y-3"
                        handle=".checklist-group-drag-handle"
                        :disabled="!isAdmin"
                        :animation="150"
                        ghost-class="opacity-40"
                        @end="onChecklistsReorder"
                    >
                        <div
                            v-for="cl in localChecklists"
                            :key="cl.id"
                            class="rounded-md border border-slate-200 p-2"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <button
                                    v-if="isAdmin"
                                    type="button"
                                    class="checklist-group-drag-handle shrink-0 cursor-grab rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing"
                                    title="Arrastar checklist"
                                >
                                    <Bars3Icon class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <div class="min-w-0 flex-1">
                                    <TextInput
                                        v-if="isAdmin && editingChecklistId === cl.id"
                                        v-model="editingChecklistName"
                                        class="h-8 w-full border-slate-200 bg-white text-xs font-semibold shadow-none"
                                        @keydown.enter.prevent="saveInlineEditChecklist(cl)"
                                        @keydown.esc.prevent="cancelInlineEditChecklist"
                                        @blur="saveInlineEditChecklist(cl)"
                                    />
                                    <button
                                        v-else-if="isAdmin"
                                        type="button"
                                        class="block max-w-full truncate text-left text-xs font-semibold text-slate-700 hover:text-talents-700"
                                        title="Clique para editar o nome"
                                        @click="startInlineEditChecklist(cl)"
                                    >
                                        {{ cl.name }}
                                    </button>
                                    <p v-else class="truncate text-xs font-semibold text-slate-700">{{ cl.name }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button
                                        v-if="isAdmin"
                                        type="button"
                                        class="text-[11px] text-red-600 underline hover:text-red-700"
                                        @click="deleteChecklist(cl)"
                                    >
                                        Excluir
                                    </button>
                                    <button
                                        v-if="!isReadOnly"
                                        type="button"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="checklistBulkProcessing[cl.id]"
                                        @click="toggleChecklistCompletion(cl)"
                                    >
                                        {{ checklistStats(cl).done ? 'Reabrir' : 'Concluir' }}
                                    </button>
                                </div>
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
                            <VueDraggable
                                v-if="localChecklistItemsById[cl.id]"
                                v-model="localChecklistItemsById[cl.id]"
                                item-key="id"
                                tag="ul"
                                class="mt-2 space-y-1.5 text-sm"
                                handle=".checklist-drag-handle"
                                :disabled="!isAdmin"
                                :animation="150"
                                ghost-class="opacity-40"
                                @end="(e) => onChecklistItemsReorder(cl, e)"
                            >
                                <li
                                    v-for="it in localChecklistItemsById[cl.id]"
                                    :key="it.id"
                                    class="rounded-md bg-white px-1 py-1.5"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="isAdmin"
                                            type="button"
                                            class="checklist-drag-handle shrink-0 cursor-grab rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing"
                                            title="Arrastar para reordenar"
                                        >
                                            <Bars3Icon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                        <input
                                            type="checkbox"
                                            :checked="it.is_completed"
                                            class="shrink-0 rounded border-slate-300"
                                            :disabled="isReadOnly"
                                            @change="toggleItem(it)"
                                        />
                                        <TextInput
                                            v-if="!isReadOnly && editingChecklistItemId === it.id"
                                            v-model="editingChecklistItemText"
                                            class="h-8 min-w-0 flex-1 border-slate-200 bg-white text-sm shadow-none"
                                            placeholder="Título"
                                            @keydown.enter.prevent="saveInlineEditItem(it)"
                                            @keydown.esc.prevent="cancelInlineEditItem"
                                            @blur="saveInlineEditItem(it)"
                                        />
                                        <button
                                            v-else-if="!isReadOnly"
                                            type="button"
                                            class="min-w-0 flex-1 text-left"
                                            @click="startInlineEditItem(it)"
                                        >
                                            <span :class="it.is_completed ? 'text-slate-400 line-through' : ''">{{ it.text }}</span>
                                        </button>
                                        <span
                                            v-else
                                            class="min-w-0 flex-1 text-left text-sm"
                                            :class="it.is_completed ? 'text-slate-400 line-through' : 'text-slate-800'"
                                        >
                                            {{ it.text }}
                                        </span>
                                        <label
                                            v-if="isAdmin"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px]"
                                            :class="itemDueClass(it)"
                                            :title="it.due_date ? 'Vencimento da etapa' : 'Definir vencimento'"
                                        >
                                            <CalendarDaysIcon class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                                            <input
                                                type="date"
                                                :value="it.due_date || ''"
                                                class="w-[7.25rem] border-0 bg-transparent p-0 text-[11px] focus:ring-0"
                                                @change="updateItemDueDate(it, $event.target.value)"
                                            />
                                        </label>
                                        <span
                                            v-else-if="it.due_date"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px]"
                                            :class="itemDueClass(it)"
                                            :title="formatDateTitle(it.due_date)"
                                        >
                                            <CalendarDaysIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                            {{ formatDateLabel(it.due_date) }}
                                        </span>
                                        <button
                                            v-if="isAdmin"
                                            type="button"
                                            class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                            title="Excluir etapa"
                                            @click="deleteChecklistItem(it)"
                                        >
                                            <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <div
                                        class="mt-1.5 space-y-1"
                                        :class="isAdmin ? 'pl-12' : 'pl-7'"
                                    >
                                        <textarea
                                            v-if="!isReadOnly"
                                            :value="itemDescriptionDraft(it)"
                                            rows="2"
                                            class="w-full resize-y rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 shadow-none placeholder:text-slate-400 focus:border-talents-500 focus:ring-talents-500"
                                            placeholder="Descrição (opcional)"
                                            @input="setItemDescriptionDraft(it, $event.target.value)"
                                            @blur="saveItemDescription(it)"
                                        />
                                        <p
                                            v-else-if="it.description"
                                            class="whitespace-pre-wrap text-xs text-slate-600"
                                        >
                                            {{ it.description }}
                                        </p>
                                        <p
                                            v-if="it.created_at"
                                            class="text-[11px] text-slate-400"
                                            :title="formatDateTitle(it.created_at)"
                                        >
                                            Criado em {{ formatDateLabel(it.created_at) }}
                                        </p>
                                    </div>
                                </li>
                            </VueDraggable>
                            <div v-if="isAdmin" class="mt-2 space-y-2 border-t border-slate-100 pt-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <TextInput
                                        v-model="newItemDraft(cl.id).text"
                                        :data-checklist-new-item="cl.id"
                                        class="min-w-0 flex-1 border-slate-200 bg-white text-sm shadow-none"
                                        placeholder="Título da etapa…"
                                        @keydown.enter.prevent="addChecklistItem(cl)"
                                    />
                                    <label
                                        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-white px-1.5 py-1 text-[11px] text-slate-600"
                                        title="Vencimento da etapa (opcional)"
                                    >
                                        <CalendarDaysIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                        <input
                                            v-model="newItemDraft(cl.id).due_date"
                                            type="date"
                                            class="w-[7.25rem] border-0 bg-transparent p-0 text-[11px] focus:ring-0"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-md border border-slate-300 px-2 py-1.5 text-[11px] font-medium text-slate-700 hover:bg-slate-50"
                                        @click="addChecklistItem(cl)"
                                    >
                                        Adicionar
                                    </button>
                                </div>
                                <textarea
                                    v-model="newItemDraft(cl.id).description"
                                    rows="2"
                                    class="w-full resize-y rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 shadow-none placeholder:text-slate-400 focus:border-talents-500 focus:ring-talents-500"
                                    placeholder="Descrição (opcional)"
                                />
                            </div>
                        </div>
                    </VueDraggable>

                    <p v-if="!localChecklists.length" class="text-xs text-slate-500">
                        Nenhuma checklist criada ainda.
                    </p>
                </div>

                <div class="space-y-2 rounded-lg border border-slate-100 p-4">
                    <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <PaperClipIcon class="h-4 w-4 text-slate-500" />
                        Anexos
                    </h4>
                    <ul class="mt-2 space-y-1 text-xs">
                        <li v-for="a in card.attachments || []" :key="a.id">
                            <a :href="a.url" target="_blank" class="text-talents-700 underline">{{ a.original_name }}</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else-if="activeTab === 'settings'" class="overflow-y-auto p-6">
                <div class="space-y-4 rounded-lg border border-slate-100 p-4">
                    <div v-if="isAdmin">
                        <InputLabel value="Cor do cartão" />
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full border border-dashed border-slate-300 text-[11px] font-medium text-slate-500 hover:border-slate-400 hover:text-slate-700"
                                :class="{ 'ring-2 ring-talents-500 ring-offset-1': !cardUpdate.cover_color }"
                                title="Sem cor"
                                @click="setCoverColor(null)"
                            >
                                ×
                            </button>
                            <button
                                v-for="preset in COVER_COLOR_PRESETS"
                                :key="preset.value"
                                type="button"
                                class="h-7 w-7 rounded-full border border-white shadow ring-1 ring-slate-200 transition hover:scale-110"
                                :class="{
                                    'ring-2 ring-talents-500 ring-offset-1':
                                        cardUpdate.cover_color?.toLowerCase() === preset.value,
                                }"
                                :style="{ backgroundColor: preset.value }"
                                :title="preset.label"
                                @click="setCoverColor(preset.value)"
                            />
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            A cor aparece como faixa no topo do cartão no quadro.
                        </p>
                    </div>

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
                            <div class="mt-1 max-h-52 space-y-3 overflow-y-auto rounded-md border border-slate-200 bg-slate-50/60 p-2 text-sm">
                                <div v-if="assignableTeamUsers.length">
                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                        Equipe Talents
                                    </p>
                                    <label
                                        v-for="u in assignableTeamUsers"
                                        :key="`team-${u.id}`"
                                        class="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-white"
                                    >
                                        <input v-model="cardUpdate.member_ids" type="checkbox" :value="u.id" />
                                        {{ u.name }}
                                    </label>
                                </div>
                                <div v-if="cardUpdate.company_id">
                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                        Cliente
                                    </p>
                                    <label
                                        v-for="u in usersForSelectedCompany"
                                        :key="`company-${u.id}`"
                                        class="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-white"
                                    >
                                        <input v-model="cardUpdate.member_ids" type="checkbox" :value="u.id" />
                                        {{ u.name }}
                                    </label>
                                    <p v-if="!usersForSelectedCompany.length" class="text-xs text-slate-500">
                                        Nenhum utilizador ativo nesta empresa.
                                    </p>
                                </div>
                                <p
                                    v-else-if="!assignableTeamUsers.length"
                                    class="text-xs text-slate-500"
                                >
                                    Selecione uma empresa para responsáveis do cliente ou atribua a equipe Talents.
                                </p>
                                <p
                                    v-else-if="!cardUpdate.company_id"
                                    class="text-xs text-slate-500"
                                >
                                    Selecione uma empresa acima para incluir responsáveis do cliente.
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
                <template v-if="isReadOnly">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="emit('close')"
                    >
                        Fechar
                    </button>
                </template>
                <template v-else>
                    <button
                        v-if="card?.is_archived"
                        type="button"
                        class="mr-auto inline-flex items-center gap-1.5 rounded-md border border-talents-300 px-3 py-2 text-sm font-medium text-talents-700 transition hover:bg-talents-50"
                        @click="restoreCard"
                    >
                        <ArrowUturnLeftIcon class="h-4 w-4" />
                        Restaurar tarefa
                    </button>
                    <template v-else>
                        <button
                            type="button"
                            class="mr-auto inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="archiveCard"
                        >
                            <ArchiveBoxArrowDownIcon class="h-4 w-4" />
                            Arquivar tarefa
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-rose-300 px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50"
                            @click="deleteCard"
                        >
                            Excluir tarefa
                        </button>
                    </template>
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
                </template>
            </div>
        </div>
    </Modal>
</template>
