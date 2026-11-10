<script setup>
import BoardDescriptionToggle from '@/Components/Tasks/BoardDescriptionToggle.vue';
import ColorPresetPicker from '@/Components/Tasks/ColorPresetPicker.vue';
import CompanySearchSelect from '@/Components/CompanySearchSelect.vue';
import TaskToolbarMenu from '@/Components/Tasks/TaskToolbarMenu.vue';
import { descriptionPlainText } from '@/utils/taskBoards';
import { router, useForm } from '@inertiajs/vue3';
import {
    ArchiveBoxIcon,
    BuildingOffice2Icon,
    ChevronRightIcon,
    EllipsisHorizontalIcon,
    ListBulletIcon,
    PencilSquareIcon,
    StarIcon as StarOutlineIcon,
    TrashIcon,
    UserPlusIcon,
    ViewColumnsIcon,
} from '@heroicons/vue/24/outline';
import { StarIcon as StarSolidIcon } from '@heroicons/vue/24/solid';
import { computed, nextTick, ref, watch } from 'vue';
import { confirmDialog } from '@/composables/useConfirmDialog';

/**
 * Cabeçalho de cada quadro da tela de Tarefas (uma linha; no celular rola na horizontal).
 * Funciona recolhido (só com o resumo do quadro, `board`) e expandido (com o conteúdo, `boardPayload`):
 * membros e Convidar aparecem quando o conteúdo já carregou; Ver arquivados e Kanban/Lista, só expandido.
 */
const props = defineProps({
    /** Item da lista de quadros (nome, cor, empresa, favorito, resumo). */
    board: { type: Object, required: true },
    /** Conteúdo do quadro (listas, cartões, membros); null enquanto não carregou. */
    boardPayload: { type: Object, default: null },
    expanded: { type: Boolean, default: false },
    /** id do corpo da seção (aria-controls do chevron). */
    bodyId: { type: String, default: undefined },
    isAdmin: { type: Boolean, default: false },
    companyUsers: { type: Array, default: () => [] },
    /** Usuários internos Talents (super admins) para quadros globais/internos. */
    teamUsers: { type: Array, default: () => [] },
    /** Empresas ativas — partilha em lote de cartões em quadros internos. */
    companies: { type: Array, default: () => [] },
    viewMode: { type: String, default: 'kanban' },
    showArchived: { type: Boolean, default: false },
});

const emit = defineEmits(['refresh', 'update:viewMode', 'toggle-archived', 'toggle']);

/** Nome, cor, empresa e descrição vêm da lista de quadros (sempre atualizada); o conteúdo completa o resto. */
const info = computed(() => ({ ...(props.boardPayload ?? {}), ...props.board }));

const isStarred = ref(Boolean(props.board?.is_starred));
watch(
    () => props.board?.is_starred,
    (value) => {
        isStarred.value = Boolean(value);
    },
);
const inviting = ref(false);
const inviteUserId = ref(null);
const inviteRole = ref('viewer');
const editingName = ref(false);
const nameInput = ref(null);
const sharingCards = ref(false);
const shareCompanyId = ref('');
const shareProcessing = ref(false);

const boardForm = useForm({
    name: info.value?.name || '',
    description: info.value?.description || '',
    cover_color: info.value?.cover_color || '',
});

watch(
    info,
    (val) => {
        boardForm.name = val?.name || '';
        boardForm.description = val?.description || '';
        boardForm.cover_color = val?.cover_color || '';
    },
    { deep: true },
);

const members = computed(() => props.boardPayload?.members ?? []);
const visibleMembers = computed(() => members.value.slice(0, 4));
const extraMembers = computed(() => Math.max(0, members.value.length - visibleMembers.value.length));

const memberIds = computed(() => new Set(members.value.map((m) => Number(m.id))));

const byUserName = (a, b) =>
    String(a?.name ?? '').localeCompare(String(b?.name ?? ''), 'pt-BR', {
        sensitivity: 'base',
        numeric: true,
    });

const eligibleCompanyUsers = computed(() => {
    const list = props.companyUsers || [];
    const companyId = info.value?.company_id;
    if (!companyId) {
        return list;
    }
    return list.filter((u) => Number(u.company_id) === Number(companyId));
});

const availableTeamToInvite = computed(() =>
    (props.teamUsers || [])
        .filter((u) => !memberIds.value.has(Number(u.id)))
        .slice()
        .sort(byUserName),
);

const availableCompanyToInvite = computed(() =>
    eligibleCompanyUsers.value
        .filter((u) => !memberIds.value.has(Number(u.id)))
        .slice()
        .sort(byUserName),
);

const hasAnyoneToInvite = computed(
    () => availableTeamToInvite.value.length > 0 || availableCompanyToInvite.value.length > 0,
);

const boardMemberRoleLabels = {
    owner: 'Proprietário',
    editor: 'Editar',
    commenter: 'Comentar',
    viewer: 'Visualizar',
};

function memberRoleLabel(role) {
    return boardMemberRoleLabels[role] || role || 'Membro';
}

const palette = [
    'bg-amber-500',
    'bg-rose-500',
    'bg-fuchsia-500',
    'bg-violet-500',
    'bg-indigo-500',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-teal-500',
    'bg-orange-500',
];

function avatarInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    const first = parts[0]?.[0] ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
    return (first + last).toUpperCase().slice(0, 2);
}

function avatarColor(seed) {
    const n = Number(seed);
    if (Number.isFinite(n)) return palette[Math.abs(Math.trunc(n)) % palette.length];
    let hash = 0;
    for (const ch of String(seed ?? '')) hash = (hash * 31 + ch.charCodeAt(0)) | 0;
    return palette[Math.abs(hash) % palette.length];
}

function toggleStar() {
    const willStar = !isStarred.value;
    isStarred.value = willStar;

    const routeName = props.isAdmin
        ? willStar
            ? 'admin.tarefas.quadros.favoritar'
            : 'admin.tarefas.quadros.desfavoritar'
        : willStar
            ? 'client.tarefas.favoritar'
            : 'client.tarefas.desfavoritar';

    const method = willStar ? 'post' : 'delete';

    router[method](
        route(routeName, props.board.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['boards'],
            onError: () => {
                isStarred.value = !willStar;
            },
        },
    );
}

function inviteSelected() {
    if (!inviteUserId.value) return;
    router.post(
        route('admin.tarefas.quadros.membros.store', props.board.id),
        { user_id: inviteUserId.value, role: inviteRole.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                inviteUserId.value = null;
                inviteRole.value = 'viewer';
                emit('refresh');
            },
        },
    );
}

function openInviteModal() {
    inviteUserId.value = null;
    inviteRole.value = 'viewer';
    inviting.value = true;
}

function openShareCardsModal() {
    shareCompanyId.value = '';
    sharingCards.value = true;
}

async function submitShareCards() {
    if (!shareCompanyId.value || shareProcessing.value) {
        return;
    }
    const company = (props.companies || []).find((c) => Number(c.id) === Number(shareCompanyId.value));
    const label = company?.name || 'a empresa selecionada';
    if (
        !(await confirmDialog(
            `Partilhar com ${label} todos os cartões deste quadro que ainda não estão vinculados a uma empresa? Eles passam a aparecer no portal do cliente.`,
        ))
    ) {
        return;
    }
    shareProcessing.value = true;
    router.post(
        route('admin.tarefas.quadros.partilhar-cartoes', props.board.id),
        { company_id: Number(shareCompanyId.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                shareProcessing.value = false;
            },
            onSuccess: () => {
                sharingCards.value = false;
                emit('refresh');
            },
        },
    );
}

async function removeMember(userId) {
    if (!(await confirmDialog('Remover este membro do quadro?'))) return;
    router.delete(
        route('admin.tarefas.quadros.membros.destroy', [props.board.id, userId]),
        {
            preserveScroll: true,
            onSuccess: () => emit('refresh'),
        },
    );
}

function patchBoard(fields) {
    if (!props.isAdmin) {
        return;
    }

    router.patch(route('admin.tarefas.quadros.update', props.board.id), fields, {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
}

function startEditingName() {
    if (!props.isAdmin) return;
    boardForm.name = info.value?.name || '';
    editingName.value = true;
    nextTick(() => {
        nameInput.value?.focus();
        nameInput.value?.select();
    });
}

function saveBoardName() {
    if (!props.isAdmin) {
        return;
    }
    patchBoard({
        name: boardForm.name,
        description: boardForm.description || null,
    });
    editingName.value = false;
}

function saveCoverColor(color) {
    if (!props.isAdmin) {
        return;
    }
    boardForm.cover_color = color || '';
    patchBoard({
        name: info.value.name,
        cover_color: color || null,
    });
}

async function deleteBoard() {
    if (!props.isAdmin) return;
    const name = info.value?.name || 'este quadro';
    if (!(await confirmDialog(
            `Excluir "${name}" permanentemente?\n\nTodas as listas, tarefas, anexos, comentários e checklists serão removidos. Esta ação NÃO pode ser desfeita.`))) {
        return;
    }

    router.delete(route('admin.tarefas.quadros.destroy', props.board.id), {
        preserveScroll: false,
    });
}

const viewOptions = [
    { value: 'kanban', label: 'Kanban', icon: ViewColumnsIcon },
    { value: 'lista', label: 'Lista', icon: ListBulletIcon },
];

const toolbarButton =
    'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500';
const iconButton =
    'inline-flex shrink-0 items-center justify-center rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500';
const menuItem =
    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
</script>

<template>
    <div
        class="flex items-center gap-1 overflow-x-auto px-2 py-1.5 [scrollbar-width:thin]"
        role="toolbar"
        :aria-label="`Ações do quadro ${info.name}`"
        data-task-board-toolbar
    >
        <form v-if="editingName && isAdmin" class="flex shrink-0 items-center gap-1.5" @submit.prevent="saveBoardName">
            <input
                ref="nameInput"
                v-model="boardForm.name"
                aria-label="Nome do quadro"
                class="w-56 rounded-md border border-slate-300 px-2 py-1 text-sm font-semibold text-slate-900 focus:border-talents-500 focus:ring-talents-500"
                maxlength="255"
                required
                @keydown.esc.prevent="editingName = false"
            />
            <button type="submit" class="rounded bg-talents-600 px-2 py-1 text-xs font-semibold text-white hover:bg-talents-700">
                Salvar
            </button>
            <button
                type="button"
                class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
                @click="editingName = false"
            >
                Cancelar
            </button>
        </form>
        <h3 v-else class="min-w-0 shrink">
            <button
                type="button"
                class="inline-flex min-w-0 max-w-[18rem] items-center gap-2 rounded-lg px-1.5 py-1 text-left transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500 sm:max-w-md"
                :aria-expanded="expanded ? 'true' : 'false'"
                :aria-controls="bodyId"
                :title="expanded ? 'Recolher quadro' : 'Expandir quadro'"
                data-board-toggle
                @click="emit('toggle')"
            >
                <ChevronRightIcon
                    class="h-4 w-4 shrink-0 text-slate-500 transition-transform motion-reduce:transition-none"
                    :class="expanded ? 'rotate-90' : ''"
                    aria-hidden="true"
                />
                <span
                    class="h-5 w-1 shrink-0 rounded-full"
                    :class="info.cover_color ? '' : 'bg-slate-300'"
                    :style="info.cover_color ? { backgroundColor: info.cover_color } : {}"
                    aria-hidden="true"
                />
                <span class="truncate text-sm font-semibold text-slate-900 sm:text-base">{{ info.name }}</span>
            </button>
        </h3>

        <button
            v-if="isAdmin && !editingName"
            type="button"
            :class="iconButton"
            title="Renomear quadro"
            aria-label="Renomear quadro"
            @click="startEditingName"
        >
            <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
        </button>

        <button
            type="button"
            :class="[iconButton, 'hover:text-amber-500']"
            :title="isStarred ? 'Remover dos favoritos' : 'Adicionar aos favoritos'"
            :aria-label="isStarred ? 'Remover dos favoritos' : 'Adicionar aos favoritos'"
            :aria-pressed="isStarred ? 'true' : 'false'"
            @click="toggleStar"
        >
            <StarSolidIcon v-if="isStarred" class="h-4 w-4 text-amber-400" aria-hidden="true" />
            <StarOutlineIcon v-else class="h-4 w-4" aria-hidden="true" />
        </button>

        <span
            v-if="info.company"
            class="inline-block max-w-[12rem] shrink-0 truncate rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
            :title="`Empresa: ${info.company.name}`"
        >
            {{ info.company.name }}
        </span>
        <span
            v-else-if="info.is_internal"
            class="inline-flex shrink-0 items-center rounded-md bg-talents-50 px-2 py-0.5 text-xs font-medium text-talents-700"
        >
            Interno
        </span>

        <div v-if="descriptionPlainText(info.description)" class="max-w-xs shrink-0">
            <BoardDescriptionToggle :description="descriptionPlainText(info.description)" />
        </div>

        <span class="mx-1 h-5 w-px shrink-0 bg-slate-200" aria-hidden="true" />

        <component
            :is="isAdmin ? 'button' : 'div'"
            v-if="boardPayload && members.length"
            :type="isAdmin ? 'button' : undefined"
            class="flex shrink-0 -space-x-1.5 rounded-full"
            :class="isAdmin ? 'focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500' : ''"
            :title="isAdmin ? 'Ver e gerenciar membros' : `${members.length} membro(s)`"
            :aria-label="isAdmin ? `Membros do quadro (${members.length})` : undefined"
            @click="isAdmin && openInviteModal()"
        >
            <span
                v-for="m in visibleMembers"
                :key="m.id"
                :title="m.name + (m.role ? ' · ' + memberRoleLabel(m.role) : '')"
                class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold text-white ring-2 ring-white"
                :class="avatarColor(m.id)"
            >
                {{ avatarInitials(m.name) }}
            </span>
            <span
                v-if="extraMembers"
                class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-300 text-[11px] font-semibold text-slate-700 ring-2 ring-white"
                :title="`+${extraMembers} membros`"
            >
                +{{ extraMembers }}
            </span>
        </component>

        <button
            v-if="isAdmin && boardPayload"
            type="button"
            :class="[toolbarButton, 'bg-talents-600 text-white hover:bg-talents-700']"
            title="Convidar para o quadro"
            @click="openInviteModal"
        >
            <UserPlusIcon class="h-4 w-4" aria-hidden="true" />
            <span class="hidden md:inline">Convidar</span>
            <span class="sr-only md:hidden">Convidar</span>
        </button>

        <button
            v-if="isAdmin && info.is_internal"
            type="button"
            :class="[toolbarButton, 'text-talents-800 hover:bg-talents-50']"
            title="Partilhar cartões com o portal de uma empresa"
            @click="openShareCardsModal"
        >
            <BuildingOffice2Icon class="h-4 w-4" aria-hidden="true" />
            <span class="hidden lg:inline">Partilhar com empresa</span>
            <span class="sr-only lg:hidden">Partilhar com empresa</span>
        </button>

        <span class="min-w-2 flex-1" aria-hidden="true" />

        <button
            v-if="isAdmin && expanded && viewMode === 'kanban'"
            type="button"
            :class="[
                toolbarButton,
                showArchived ? 'bg-amber-100 text-amber-900 hover:bg-amber-200' : 'text-slate-600 hover:bg-slate-100',
            ]"
            :aria-pressed="showArchived ? 'true' : 'false'"
            :title="showArchived ? 'Ocultar arquivados' : 'Ver arquivados'"
            @click="emit('toggle-archived')"
        >
            <ArchiveBoxIcon class="h-4 w-4" aria-hidden="true" />
            <span class="hidden xl:inline">{{ showArchived ? 'Ocultar arquivados' : 'Ver arquivados' }}</span>
            <span class="sr-only xl:hidden">{{ showArchived ? 'Ocultar arquivados' : 'Ver arquivados' }}</span>
        </button>

        <div v-if="expanded" class="inline-flex shrink-0 rounded-lg bg-slate-100 p-0.5" role="group" aria-label="Modo de exibição">
            <button
                v-for="option in viewOptions"
                :key="option.value"
                type="button"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-talents-500"
                :class="viewMode === option.value ? 'bg-white text-talents-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                :aria-pressed="viewMode === option.value ? 'true' : 'false'"
                @click="emit('update:viewMode', option.value)"
            >
                <component :is="option.icon" class="h-4 w-4" aria-hidden="true" />
                <span class="hidden sm:inline">{{ option.label }}</span>
                <span class="sr-only sm:hidden">{{ option.label }}</span>
            </button>
        </div>

        <TaskToolbarMenu
            v-if="isAdmin"
            align="right"
            :width="288"
            label="Mais ações do quadro"
            title="Mais ações do quadro"
            :button-class="iconButton"
        >
            <template #trigger>
                <EllipsisHorizontalIcon class="h-5 w-5" aria-hidden="true" />
            </template>
            <template #default="{ close }">
                <button type="button" :class="menuItem" @click="close(); startEditingName()">
                    <PencilSquareIcon class="h-4 w-4 text-slate-500" aria-hidden="true" />
                    Renomear quadro
                </button>
                <div class="border-y border-slate-100 px-3 py-2">
                    <ColorPresetPicker
                        :model-value="boardForm.cover_color"
                        label="Cor de capa do quadro"
                        hint="Aparece ao lado do nome do quadro."
                        @update:model-value="saveCoverColor"
                    />
                </div>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:outline-none"
                    @click="close(); deleteBoard()"
                >
                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                    Excluir quadro…
                </button>
            </template>
        </TaskToolbarMenu>

        <Teleport to="body">
            <div
                v-if="inviting"
                class="fixed inset-0 z-[100] flex items-start justify-center bg-slate-900/40 px-4 py-12"
                @click.self="inviting = false"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-slate-900">Convidar para o quadro</h3>
                        <button
                            type="button"
                            class="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                            @click="inviting = false"
                        >
                            <span class="sr-only">Fechar</span>
                            ✕
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-xs font-medium text-slate-600">Adicionar usuário</label>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <select
                                    v-model="inviteUserId"
                                    class="block min-w-0 flex-1 rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                >
                                    <option :value="null">Selecione…</option>
                                    <optgroup
                                        v-if="availableTeamToInvite.length"
                                        label="Equipe Talents"
                                    >
                                        <option
                                            v-for="u in availableTeamToInvite"
                                            :key="`team-${u.id}`"
                                            :value="u.id"
                                        >
                                            {{ u.name }}{{ u.email ? ` — ${u.email}` : '' }}
                                        </option>
                                    </optgroup>
                                    <optgroup
                                        v-if="availableCompanyToInvite.length"
                                        :label="info.company ? 'Empresa do quadro' : 'Clientes'"
                                    >
                                        <option
                                            v-for="u in availableCompanyToInvite"
                                            :key="`company-${u.id}`"
                                            :value="u.id"
                                        >
                                            {{ u.name }}{{ u.email ? ` — ${u.email}` : '' }}
                                        </option>
                                    </optgroup>
                                </select>
                                <select
                                    v-model="inviteRole"
                                    class="w-36 shrink-0 rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                                    title="Permissão no quadro"
                                >
                                    <option value="viewer">Visualizar</option>
                                    <option value="commenter">Comentar</option>
                                    <option value="editor">Editar</option>
                                </select>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-lg bg-talents-600 px-3 text-sm font-semibold text-white shadow hover:bg-talents-700 disabled:opacity-50"
                                    :disabled="!inviteUserId"
                                    @click="inviteSelected"
                                >
                                    Adicionar
                                </button>
                            </div>
                            <p v-if="!hasAnyoneToInvite" class="mt-2 text-xs text-slate-500">
                                Não há usuários elegíveis para adicionar. Todos da equipe ou da empresa já estão no quadro.
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Membros atuais ({{ members.length }})
                            </p>
                            <ul class="mt-2 max-h-72 space-y-1 overflow-y-auto pr-1">
                                <li
                                    v-for="m in members"
                                    :key="m.id"
                                    class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-2 py-1.5"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold text-white"
                                            :class="avatarColor(m.id)"
                                        >
                                            {{ avatarInitials(m.name) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-800">{{ m.name }}</p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ m.email }}
                                                <span v-if="m.role" class="text-slate-400"> · {{ memberRoleLabel(m.role) }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs font-medium text-rose-600 hover:underline"
                                        @click="removeMember(m.id)"
                                    >
                                        Remover
                                    </button>
                                </li>
                                <li v-if="!members.length" class="text-xs text-slate-500">
                                    Nenhum membro vinculado ainda.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="sharingCards"
                class="fixed inset-0 z-[100] flex items-start justify-center bg-slate-900/40 px-4 py-12"
                @click.self="sharingCards = false"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-slate-900">Partilhar cartões com empresa</h3>
                        <button
                            type="button"
                            class="rounded-md p-1 text-slate-500 hover:bg-slate-100"
                            @click="sharingCards = false"
                        >
                            <span class="sr-only">Fechar</span>
                            ✕
                        </button>
                    </div>
                    <p class="mt-2 text-sm text-slate-600">
                        Os cartões deste quadro interno que ainda não têm empresa passam a aparecer no
                        portal do cliente selecionado. Cartões já vinculados a outra empresa não são alterados.
                    </p>
                    <div class="mt-4">
                        <label class="text-xs font-medium text-slate-600">Empresa</label>
                        <CompanySearchSelect
                            v-model="shareCompanyId"
                            :companies="companies"
                            as-string
                            required
                            placeholder="Selecione a empresa…"
                            class="mt-1"
                            input-class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-talents-500 focus:ring-talents-500"
                        />
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            @click="sharingCards = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-talents-600 px-3 py-2 text-sm font-semibold text-white hover:bg-talents-700 disabled:opacity-50"
                            :disabled="!shareCompanyId || shareProcessing"
                            @click="submitShareCards"
                        >
                            {{ shareProcessing ? 'A partilhar…' : 'Partilhar cartões' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
